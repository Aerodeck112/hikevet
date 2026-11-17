<?php

namespace App\Services\Campaign;

use App\Models\SterilizationAppointment;
use App\Models\SterilizationCase;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Programarile campaniei: programare telefonica (cu mai multi pacienti), sincronizare cu dosarul,
 * disponibilitatea zilei si reamintirile automate, ca in pluginul 2.7.6.
 */
class CampaignAppointments
{
    public const DAY_START = 8 * 60;

    public const DAY_END = 20 * 60;

    public function __construct(private readonly CampaignCases $cases) {}

    public function nextCode(): string
    {
        $prefix = 'HV-AP-'.now()->format('Y').'-';
        $max = SterilizationAppointment::query()->where('appointment_code', 'like', $prefix.'%')->pluck('appointment_code')
            ->map(fn ($c) => preg_match('/-(\d+)$/', (string) $c, $m) ? (int) $m[1] : 0)->max() ?? 0;

        return $prefix.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Ora de sfarsit implicita: +60 de minute fata de inceput. */
    public static function normalizeTimes(string $start, string $end): array
    {
        $start = CaseFields::sanitizeTime($start) ?: '10:00';
        $end = CaseFields::sanitizeTime($end);
        if ($end === '' || CaseFields::minutes($end) <= CaseFields::minutes($start)) {
            $end = CaseFields::timeFromMinutes(CaseFields::minutes($start) + 60);
        }

        return [$start, $end];
    }

    /**
     * Programare noua pentru unul sau mai multi pacienti ai aceluiasi proprietar (save_multi_patient_appointments):
     * pentru fiecare pacient se creeaza un dosar si o programare, apoi se trimite confirmarea pe email.
     *
     * @param  array{owner_name:string,client_phone:string,client_email:string,appointment_date:string,owner_case_id?:?int}  $owner
     * @param  list<array{patient_name:string,appointment_type:string,start_time:string,end_time?:string,vaccinat?:string}>  $patients
     * @return list<SterilizationAppointment>
     */
    public function createForPatients(array $owner, array $patients, User $actor): array
    {
        $ownerName = trim(strip_tags((string) ($owner['owner_name'] ?? '')));
        $phone = trim(strip_tags((string) ($owner['client_phone'] ?? '')));
        $email = mb_strtolower(trim((string) ($owner['client_email'] ?? '')));
        $date = CaseFields::sanitizeDate($owner['appointment_date'] ?? '');
        if ($ownerName === '' || $phone === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $date) {
            throw ValidationException::withMessages(['owner' => 'Selectează sau completează proprietarul, telefonul, emailul și data programării.']);
        }
        $clean = [];
        foreach ($patients as $p) {
            $name = trim(strip_tags((string) ($p['patient_name'] ?? '')));
            if ($name === '') {
                continue;
            }
            if (! in_array($p['appointment_type'] ?? '', CaseFields::choices()['appointment_type'], true)) {
                throw ValidationException::withMessages(['patients' => 'Selectează tipul programării pentru fiecare pacient.']);
            }
            [$start, $end] = self::normalizeTimes((string) ($p['start_time'] ?? ''), (string) ($p['end_time'] ?? ''));
            $clean[] = ['patient_name' => $name, 'appointment_type' => $p['appointment_type'], 'start_time' => $start, 'end_time' => $end,
                'vaccinat' => in_array($p['vaccinat'] ?? '', ['Da', 'Nu'], true) ? $p['vaccinat'] : ''];
        }
        if (! $clean) {
            throw ValidationException::withMessages(['patients' => 'Adaugă cel puțin un pacient și completează numele lui.']);
        }

        // Datele legale ale unui proprietar existent (adresa, CI, localitate) se preiau in fiecare dosar nou.
        $ownerData = null;
        if (! empty($owner['owner_case_id']) && ($source = SterilizationCase::query()->active()->find((int) $owner['owner_case_id']))) {
            $ownerData = array_intersect_key($source->row(), array_flip(['nume_proprietar', 'telefon', 'email', 'adresa_ci', 'serie_numar_ci', 'localitate']));
        }

        $created = DB::transaction(function () use ($clean, $ownerName, $phone, $email, $date, $ownerData, $actor) {
            $out = [];
            foreach ($clean as $p) {
                $a = ['appointment_date' => $date, 'owner_name' => $ownerName, 'client_phone' => $phone, 'client_email' => $email] + $p;
                $case = $this->cases->createFromAppointment($a, $ownerData, $actor);
                $appointment = SterilizationAppointment::query()->create([
                    'appointment_code' => $this->nextCode(), 'sterilization_case_id' => $case->id, 'appointment_date' => $date,
                    'start_time' => $p['start_time'], 'end_time' => $p['end_time'], 'appointment_type' => $p['appointment_type'],
                    'title' => $p['appointment_type'].' - '.$p['patient_name'], 'status' => 'Programat', 'owner_name' => $ownerName,
                    'client_email' => $email, 'client_phone' => $phone, 'patient_name' => $p['patient_name'],
                    'source' => count($clean) > 1 ? 'Programare telefonica - mai multi pacienti' : 'Programare telefonica',
                    'reminder_enabled' => 'Da', 'reminder_days' => 1, 'created_by_name' => $actor->name, 'updated_by_name' => $actor->name,
                ]);
                $this->cases->syncFromAppointment($case, $appointment, $p['vaccinat'], $actor);
                $out[] = $appointment;
            }

            return $out;
        });

        foreach ($created as $appointment) {
            app(CampaignMailer::class)->sendAppointmentEmail($appointment, 'booking_confirmation', $actor);
            Audit::log('sterilization.appointment_created', $appointment, 'Programare '.$appointment->appointment_code.' pentru '.$appointment->patient_name);
        }

        return $created;
    }

    /** Editarea unei programari existente; modificarile trec si in dosar. */
    public function update(SterilizationAppointment $a, array $input, User $actor): SterilizationAppointment
    {
        [$start, $end] = self::normalizeTimes((string) ($input['start_time'] ?? $a->start_time), (string) ($input['end_time'] ?? $a->end_time));
        $type = (string) ($input['appointment_type'] ?? $a->appointment_type);
        if (! in_array($type, CaseFields::choices()['appointment_type'], true)) {
            $type = CaseFields::appointmentTypeFromProcedure($a->title);
        }
        $data = [
            'appointment_date' => CaseFields::sanitizeDate($input['appointment_date'] ?? '') ?: $a->appointment_date?->format('Y-m-d'),
            'start_time' => $start, 'end_time' => $end, 'appointment_type' => $type,
            'owner_name' => trim(strip_tags((string) ($input['owner_name'] ?? $a->owner_name))),
            'client_phone' => trim(strip_tags((string) ($input['client_phone'] ?? $a->client_phone))),
            'client_email' => mb_strtolower(trim((string) ($input['client_email'] ?? $a->client_email))),
            'patient_name' => trim(strip_tags((string) ($input['patient_name'] ?? $a->patient_name))),
            'notes' => trim(strip_tags((string) ($input['notes'] ?? $a->notes))),
            'reminder_enabled' => 'Da', 'reminder_days' => 1, 'updated_by_name' => $actor->name,
        ];
        foreach (['owner_name', 'client_phone', 'client_email', 'patient_name'] as $required) {
            if ($data[$required] === '') {
                throw ValidationException::withMessages([$required => 'Completează numele proprietarului, telefonul, emailul, numele pacientului, data, ora de început, ora de sfârșit și tipul programării.']);
            }
        }
        if (in_array($input['status'] ?? null, CaseFields::choices()['appointment_status'], true)) {
            $data['status'] = $input['status'];
        }
        $data['title'] = $type.' - '.($data['patient_name'] ?: $data['owner_name']);
        $a->update($data);
        if ($a->sterilization_case_id && ($case = SterilizationCase::query()->active()->find($a->sterilization_case_id))) {
            $this->cases->syncFromAppointment($case, $a, $input['vaccinat'] ?? null, $actor);
        }

        return $a->refresh();
    }

    public function cancel(SterilizationAppointment $a, User $actor): void
    {
        $a->update(['status' => 'Anulat', 'updated_by_name' => $actor->name]);
        Audit::log('sterilization.appointment_cancelled', $a, 'Programare anulată: '.$a->appointment_code);
    }

    /** Stergerea unei programari: dosarul ramane, dar nu mai are programare initiala. */
    public function delete(SterilizationAppointment $a, User $actor): void
    {
        if ($a->sterilization_case_id && ($case = SterilizationCase::query()->find($a->sterilization_case_id))) {
            $row = $case->row();
            if ((int) $row['initial_appointment_id'] === $a->id) {
                $row['initial_appointment_id'] = 0;
                $row['initial_appointment_synced_at'] = '';
                $this->cases->persist($case, $row, $actor, 'appointment_deleted', 'programare', 'Programarea '.$a->appointment_code.' a fost ștearsă', false);
            }
        }
        Audit::log('sterilization.appointment_deleted', $a, 'Programare ștearsă: '.$a->appointment_code.' ('.$a->appointment_date?->format('d.m.Y').' '.$a->start_time.', '.$a->label().')');
        $a->delete();
    }

    /**
     * sync_initial_appointment_for_case: data si ora programarii din dosar tin programarea din calendar la zi
     * (durata existenta se pastreaza). Intoarce programarea creata sau actualizata.
     */
    public function syncInitialForCase(SterilizationCase $case, ?User $actor): ?SterilizationAppointment
    {
        $row = $case->row();
        if (empty($row['data_programare']) || empty($row['ora_programare'])) {
            return null;
        }
        $procedure = trim((string) $row['procedura_programata']) ?: CaseFields::scheduledProcedure($row);
        if ($procedure === '') {
            return null;
        }
        $a = ((int) $row['initial_appointment_id'] ? SterilizationAppointment::query()->find((int) $row['initial_appointment_id']) : null)
            ?? SterilizationAppointment::query()->where('sterilization_case_id', $case->id)->orderBy('id')->first();
        $duration = $a ? max(0, CaseFields::minutes($a->end_time) - CaseFields::minutes($a->start_time)) : 0;
        $duration = $duration > 0 ? $duration : 60;
        $type = CaseFields::appointmentTypeFromProcedure($procedure);
        $patient = (string) $row['nume_pacient'];
        $data = [
            'sterilization_case_id' => $case->id, 'appointment_date' => $row['data_programare'], 'start_time' => $row['ora_programare'],
            'end_time' => CaseFields::timeFromMinutes(CaseFields::minutes($row['ora_programare']) + $duration), 'appointment_type' => $type,
            'owner_name' => (string) $row['nume_proprietar'], 'client_email' => (string) $row['email'], 'client_phone' => (string) $row['telefon'],
            'patient_name' => $patient, 'source' => 'Programare telefonica', 'title' => $type.' - '.($patient ?: $row['nume_proprietar']),
            'notes' => 'Programare telefonica. Interventie programata: '.$procedure.'.', 'reminder_enabled' => 'Da', 'reminder_days' => 1,
            'updated_by_name' => $actor?->name,
        ];
        if ($a) {
            if (! $a->status || $a->status === 'Anulat') {
                $data['status'] = 'Programat';
            }
            $a->update($data);
        } else {
            $a = SterilizationAppointment::query()->create($data + ['appointment_code' => $this->nextCode(), 'status' => 'Programat', 'created_by_name' => $actor?->name]);
        }
        if ((int) $row['initial_appointment_id'] !== $a->id || $row['procedura_programata'] !== $procedure) {
            $row['initial_appointment_id'] = $a->id;
            $row['initial_appointment_synced_at'] = CaseFields::now();
            $row['procedura_programata'] = $procedure;
            $this->cases->persist($case, $row, $actor, 'appointment_sync', 'programare', null, false);
        }

        return $a->refresh();
    }

    /**
     * Disponibilitatea unei zile (appointment_day_availability): ferestre de 60 de minute din 30 in 30, intre 08:00 si 20:00;
     * „load” = cate programari active se suprapun.
     */
    public function dayAvailability(string $date, int $excludeId = 0): array
    {
        $date = CaseFields::sanitizeDate($date);
        if (! $date) {
            return ['date' => '', 'appointments' => [], 'count' => 0, 'free_count' => 0, 'busy_count' => 0, 'next_free' => '', 'slots' => []];
        }
        $active = SterilizationAppointment::query()->whereDate('appointment_date', $date)->where('status', '!=', 'Anulat')
            ->whereKeyNot($excludeId)->orderBy('start_time')->get();
        $slots = [];
        $free = $busy = 0;
        $next = '';
        for ($m = self::DAY_START; $m <= self::DAY_END - 60; $m += 30) {
            $overlaps = $active->filter(function (SterilizationAppointment $a) use ($m) {
                $s = CaseFields::minutes($a->start_time);
                $e = CaseFields::minutes($a->endTime());

                return $s < $m + 60 && $e > $m;
            })->map(fn ($a) => ['id' => $a->id, 'start' => $a->start_time, 'end' => $a->endTime(), 'patient' => $a->label(), 'type' => $a->appointment_type, 'status' => $a->status])->values()->all();
            $time = CaseFields::timeFromMinutes($m);
            if (! $overlaps) {
                $free++;
                $next = $next ?: $time;
            } else {
                $busy++;
            }
            $slots[] = ['time' => $time, 'end' => CaseFields::timeFromMinutes($m + 60), 'load' => count($overlaps), 'appointments' => $overlaps];
        }

        return ['date' => $date, 'appointments' => $active->all(), 'count' => $active->count(), 'free_count' => $free, 'busy_count' => $busy, 'next_free' => $next, 'slots' => $slots];
    }

    /**
     * Reamintirile automate (run_appointment_reminders): programarile Programat/Confirmat care nu au primit inca
     * reamintirea, in intervalul [ora programarii - N zile, ora programarii). Ruleaza din ora in ora prin cron.
     */
    public function runReminders(?Carbon $now = null): array
    {
        $now ??= now();
        $sent = $failed = 0;
        $rows = SterilizationAppointment::query()->where('reminder_enabled', 'Da')->whereNull('reminder_sent_at')->whereIn('status', ['Programat', 'Confirmat'])
            ->whereDate('appointment_date', '>=', $now->toDateString())->orderBy('appointment_date')->orderBy('start_time')->get();
        foreach ($rows as $a) {
            $at = Carbon::parse($a->appointment_date->format('Y-m-d').' '.($a->start_time ?: '00:00'));
            $threshold = $at->copy()->subDays(max(1, (int) $a->reminder_days));
            if ($now->gte($threshold) && $now->lt($at)) {
                [$ok] = app(CampaignMailer::class)->sendAppointmentEmail($a, 'automatic', null);
                $ok ? $sent++ : $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}
