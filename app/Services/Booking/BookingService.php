<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\TeamMember;
use App\Services\Notifications\Notifier;
use App\Support\Audit;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Programarile generale ale clinicii. Sunt complet separate de programarile istorice ale
 * campaniei de sterilizare (tabele, stari si notificari diferite).
 */
class BookingService
{
    public const GRID_MINUTES = 15;

    public const MIN_LEAD_MINUTES = 120;

    public const MAX_DAYS_AHEAD = 60;

    public function __construct(private readonly Notifier $notifier) {}

    /** @return list<string> orele de inceput libere (HH:MM) pentru medic, in ziua data */
    public function freeSlots(TeamMember $doctor, CarbonImmutable $date, int $durationMinutes): array
    {
        $durationMinutes = $this->normalizeDuration($durationMinutes);
        $windows = $doctor->schedules()->where('weekday', $date->dayOfWeekIso)->orderBy('starts_at')->get();
        if ($windows->isEmpty()) {
            return [];
        }

        $dayStart = $date->startOfDay();
        $dayEnd = $date->endOfDay();
        $taken = AppointmentSlot::query()->where('team_member_id', $doctor->id)->whereBetween('slot_at', [$dayStart, $dayEnd])
            ->pluck('slot_at')->map(fn ($t) => $t->format('H:i'))->flip();
        $blocks = ScheduleBlock::query()->where(fn ($q) => $q->whereNull('team_member_id')->orWhere('team_member_id', $doctor->id))
            ->where('starts_at', '<', $dayEnd)->where('ends_at', '>', $dayStart)->get();
        $earliest = CarbonImmutable::now()->addMinutes(self::MIN_LEAD_MINUTES);

        $free = [];
        foreach ($windows as $window) {
            $cursor = $date->setTimeFromTimeString($window->starts_at);
            $end = $date->setTimeFromTimeString($window->ends_at);

            while ($cursor->addMinutes($durationMinutes)->lte($end)) {
                $slotEnd = $cursor->addMinutes($durationMinutes);
                $ok = $cursor->gte($earliest);

                for ($t = $cursor; $ok && $t->lt($slotEnd); $t = $t->addMinutes(self::GRID_MINUTES)) {
                    $ok = ! $taken->has($t->format('H:i'));
                }
                foreach ($blocks as $block) {
                    if ($ok && $cursor->lt($block->ends_at) && $slotEnd->gt($block->starts_at)) {
                        $ok = false;
                    }
                }
                if ($ok) {
                    $free[] = $cursor->format('H:i');
                }
                $cursor = $cursor->addMinutes(self::GRID_MINUTES);
            }
        }

        return array_values(array_unique($free));
    }

    public function book(array $data, ?int $createdBy = null, bool $byStaff = false): Appointment
    {
        $doctor = TeamMember::query()->findOrFail($data['team_member_id']);
        $service = isset($data['service_id']) ? Service::query()->find($data['service_id']) : null;
        $duration = $this->normalizeDuration((int) ($data['duration_minutes'] ?? $service?->duration_minutes ?? 30));
        $start = CarbonImmutable::parse($data['starts_at']);

        if ($start->minute % self::GRID_MINUTES !== 0 || $start->second !== 0) {
            throw new DomainException('Ora aleasă nu este validă.');
        }
        if (! $byStaff) {
            if ($start->gt(CarbonImmutable::now()->addDays(self::MAX_DAYS_AHEAD))) {
                throw new DomainException('Programările se pot face cu cel mult '.self::MAX_DAYS_AHEAD.' de zile înainte.');
            }
            if (! in_array($start->format('H:i'), $this->freeSlots($doctor, $start->startOfDay(), $duration), true)) {
                throw new DomainException('Intervalul ales nu mai este disponibil. Alege altă oră.');
            }
        }

        try {
            return DB::transaction(function () use ($data, $doctor, $service, $duration, $start, $createdBy, $byStaff) {
                $appointment = Appointment::create([
                    'code' => 'HV-'.Str::upper(Str::random(8)), 'service_id' => $service?->id, 'team_member_id' => $doctor->id,
                    'user_id' => $data['user_id'] ?? null, 'starts_at' => $start, 'ends_at' => $start->addMinutes($duration),
                    'status' => $byStaff ? AppointmentStatus::Confirmed : AppointmentStatus::Requested,
                    'client_name' => $data['client_name'], 'client_phone' => $data['client_phone'], 'client_email' => $data['client_email'] ?? null,
                    'pet_name' => $data['pet_name'] ?? null, 'pet_species' => $data['pet_species'] ?? null, 'notes' => $data['notes'] ?? null,
                    'consent_processing' => (bool) ($data['consent_processing'] ?? false), 'manage_token' => Str::random(48), 'created_by' => $createdBy,
                ]);

                // Cheia unica (medic, interval) respinge a doua rezervare chiar daca doua cereri ajung simultan.
                for ($t = $start; $t->lt($start->addMinutes($duration)); $t = $t->addMinutes(self::GRID_MINUTES)) {
                    AppointmentSlot::create(['appointment_id' => $appointment->id, 'team_member_id' => $doctor->id, 'slot_at' => $t]);
                }

                Audit::log('appointment.created', $appointment, 'Programare '.$appointment->code.' la '.$start->format('d.m.Y H:i'));
                DB::afterCommit(fn () => $this->notify($appointment, 'created'));

                return $appointment;
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Intervalul tocmai a fost ocupat de altcineva. Alege altă oră.');
        }
    }

    public function setStatus(Appointment $appointment, AppointmentStatus $status, string $reason = ''): void
    {
        DB::transaction(function () use ($appointment, $status, $reason) {
            $appointment->status = $status;
            if ($status === AppointmentStatus::Cancelled) {
                $appointment->cancelled_at = now();
                $appointment->cancel_reason = $reason ?: null;
            }
            $appointment->save();

            if (! $status->holdsSlot()) {
                $appointment->slots()->delete();
            }
            Audit::log('appointment.status', $appointment, 'Programare '.$appointment->code.': '.$status->label());
        });

        if (in_array($status, [AppointmentStatus::Confirmed, AppointmentStatus::Cancelled], true)) {
            $this->notify($appointment, $status->value);
        }
    }

    private function notify(Appointment $appointment, string $event): void
    {
        $appointment->loadMissing(['doctor', 'service']);
        $when = $appointment->starts_at->format('d.m.Y').', ora '.$appointment->starts_at->format('H:i');
        $details = '<p><strong>Data:</strong> '.$when.'<br><strong>Medic:</strong> '.e($appointment->doctor->name)
            .($appointment->service ? '<br><strong>Serviciu:</strong> '.e($appointment->service->name) : '')
            .($appointment->pet_name ? '<br><strong>Pacient:</strong> '.e($appointment->pet_name) : '').'</p>';
        $subject = ['created' => 'Am primit cererea ta de programare', 'confirmed' => 'Programarea ta este confirmată', 'cancelled' => 'Programarea a fost anulată'][$event] ?? 'Programare HiKeVet';

        if ($appointment->client_email) {
            $this->notifier->send([$appointment->client_email], $subject, '<p>Bună, '.e($appointment->client_name).'!</p>'.$details
                .($event !== 'cancelled' ? '<p>Poți vedea sau anula programarea aici: <a href="'.route('booking.show', $appointment->manage_token).'">detalii programare</a>.</p>' : '')
                .'<p>Telefon clinică: '.e(Settings::get('clinic.phone')).'</p>', 'appointment_'.$event, 'appointment', $appointment->id, $appointment->user_id);
        }
        if ($event === 'created') {
            $this->notifier->send(Settings::emails('mail.clinic_recipients'), 'Cerere nouă de programare: '.$appointment->client_name,
                '<p><strong>'.e($appointment->client_name).'</strong>, '.e($appointment->client_phone).'</p>'.$details.($appointment->notes ? '<p>'.e($appointment->notes).'</p>' : ''),
                'appointment_clinic', 'appointment', $appointment->id);
        }
    }

    private function normalizeDuration(int $minutes): int
    {
        $minutes = max(self::GRID_MINUTES, min(240, $minutes));

        return (int) (ceil($minutes / self::GRID_MINUTES) * self::GRID_MINUTES);
    }
}
