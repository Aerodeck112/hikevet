<?php

namespace App\Services\Campaign;

use App\Models\Consent;
use App\Models\SterilizationAppointment;
use App\Models\SterilizationCase;
use App\Models\SterilizationCaseChange;
use App\Models\SterilizationDocument;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Dosarele campaniei de sterilizare: salvare pe sectiuni (handle_save_case), numerotare (ensure_unique_patient_number,
 * freeze_report_patient_number), vizite cu mai multi pacienti si jurnalul modificarilor. Regulile sunt cele din
 * pluginul 2.7.6; abaterile sunt marcate „ABATERE”.
 */
class CampaignCases
{
    public const SECTIONS = ['receptie', 'preoperator', 'interventie', 'externare', 'validare', 'corectare'];

    /** Coloanele copiate din `data` pentru cautare, filtre si rapoarte; lungimea maxima a fiecareia. */
    private const INDEXED = ['cod_caz' => 50, 'numar_pacient' => 80, 'numar_pacient_raport' => 80, 'ora_programare' => 10, 'ora_start' => 10,
        'nume_proprietar' => 255, 'telefon' => 60, 'email' => 255, 'localitate' => 120, 'specie' => 50, 'sex' => 50, 'nume_pacient' => 255,
        'rasa' => 255, 'microcip' => 255, 'procedura_efectuata' => 255, 'procedura_programata' => 255, 'tip_anestezie' => 255,
        'medic_operator' => 255, 'responsabil_caz' => 255, 'status_caz' => 80, 'status_interventie' => 80, 'validat_pentru_primarie' => 20, 'validat_de' => 255];

    private const DATES = ['data_programare', 'data_prezentare', 'data_interventie', 'data_raportare_primarie', 'data_externare', 'data_validare', 'data_fisa'];

    public function __construct(private readonly CityChecks $checks, private readonly CampaignConsents $consents, private readonly CampaignFiles $files) {}

    public function checks(): CityChecks
    {
        return $this->checks;
    }

    public function find(int $id): SterilizationCase
    {
        return SterilizationCase::query()->active()->findOrFail($id);
    }

    /** Toate dosarele vizitei (visit_group_cases): acelasi visit_group_id sau dosarul care da ID-ul grupului. @return list<array> */
    public function groupRows(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $group = (int) (($row['visit_group_id'] ?? 0) ?: $id);
        if (! $id || ! $group) {
            return [$row];
        }
        $rows = SterilizationCase::query()->active()->where(fn ($q) => $q->where('visit_group_id', $group)->orWhere('id', $group))
            ->orderBy('id')->get()->map->row()->all();

        return $rows ?: [$row];
    }

    public function nextCode(): string
    {
        $prefix = 'HV-ST-'.now()->format('Y').'-';
        $max = SterilizationCase::query()->where('cod_caz', 'like', $prefix.'%')->pluck('cod_caz')
            ->map(fn ($c) => preg_match('/-(\d+)$/', $c, $m) ? (int) $m[1] : 0)->max() ?? 0;

        return $prefix.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    public function nextPatientNumber(int $year, int $excludeId = 0): string
    {
        if ($year < 2000 || $year > 2200) {
            $year = (int) now()->format('Y');
        }
        $used = SterilizationCase::query()->active()->whereKeyNot($excludeId)->whereRaw(CityChecks::yearSql().' = ?', [(string) $year])
            ->pluck('numar_pacient')->filter(fn ($n) => preg_match('/^\d+$/', (string) $n) && (int) $n > 0)->map(fn ($n) => (int) $n)->flip()->all();
        $candidate = max(1, ($used ? max(array_keys($used)) : 0) + 1);
        while (isset($used[$candidate])) {
            $candidate++;
        }

        return (string) $candidate;
    }

    /** patient_number_conflicts_for_row: numarul de raport al dosarului e folosit ca numar operational de alt dosar din acelasi an. */
    public function numberConflicts(array $row): bool
    {
        $number = CityChecks::reportNumber($row);
        if ($number === '') {
            return true;
        }

        return SterilizationCase::query()->active()->whereKeyNot((int) ($row['id'] ?? 0))->where('numar_pacient', $number)
            ->whereRaw(CityChecks::yearSql().' = ?', [(string) CityChecks::patientNumberYear($row)])->exists();
    }

    /** ensure_unique_patient_number (v2.5.7): numarul introdus manual si numarul unui dosar cu fotografie nu se schimba automat. */
    public function ensureUniqueNumber(array &$row, bool $manual = false): array
    {
        $old = trim((string) ($row['numar_pacient'] ?? ''));
        $conflict = $old === '' ? true : $this->numberConflicts($row);
        if ($old !== '' && ! $conflict) {
            return ['changed' => false, 'old' => $old, 'new' => $old, 'conflict' => false];
        }
        if ($manual || (! empty($row['id']) && ! empty($row['poza_pacient']))) {
            return ['changed' => false, 'old' => $old, 'new' => $old, 'conflict' => $conflict, 'protected' => true];
        }
        $new = $this->nextPatientNumber(CityChecks::patientNumberYear($row), (int) ($row['id'] ?? 0));
        if ($old !== $new) {
            if ($old !== '') {
                $row['numar_pacient_vechi'] = $old;
            }
            $row['numar_pacient'] = $new;
            $row['numar_pacient_auto_at'] = CaseFields::now();
        }

        return ['changed' => $old !== $new, 'old' => $old, 'new' => $new, 'conflict' => $conflict, 'protected' => false];
    }

    public static function freezeReportNumber(array &$row, string $source): bool
    {
        $current = trim((string) ($row['numar_pacient'] ?? ''));
        if ($current === '' || trim((string) ($row['numar_pacient_raport'] ?? '')) !== '') {
            return false;
        }
        $row['numar_pacient_raport'] = $current;
        $row['numar_pacient_raport_sursa'] = $source;
        $row['numar_pacient_raport_confirmat_at'] = CaseFields::now();

        return true;
    }

    /**
     * Salveaza dosarul (save_row): aplica regulile de numerotare, scrie `data` si coloanele de cautare si adauga
     * modificarile in jurnal. $numbers=false pentru scrieri care nu trebuie sa atinga numerotarea.
     */
    public function persist(?SterilizationCase $case, array $row, ?User $actor, string $action, ?string $section = null, ?string $note = null, bool $numbers = true, bool $manualNumber = false): SterilizationCase
    {
        if ($numbers) {
            $this->ensureUniqueNumber($row, $manualNumber);
            if ($manualNumber) {
                if (($current = trim((string) $row['numar_pacient'])) !== '') {
                    $row['numar_pacient_raport'] = $current;
                    $row['numar_pacient_raport_sursa'] = 'corectare_manuala_dupa_foto';
                    $row['numar_pacient_raport_confirmat_at'] = CaseFields::now();
                }
            } elseif (trim((string) ($row['numar_pacient_raport'] ?? '')) === '' && (! empty($row['poza_pacient']) || in_array($row['status_interventie'] ?? '', ['Finalizat', 'Operat'], true))) {
                self::freezeReportNumber($row, ! empty($row['poza_pacient']) ? 'confirmat_foto' : 'finalizare_interventie');
            }
        }

        $name = $actor?->name ?: 'Sistem';
        $now = CaseFields::now();
        $isNew = $case === null;
        $before = $isNew ? [] : $case->row();
        $data = array_intersect_key($row, CaseFields::defaults());
        foreach (CaseFields::decimalFields() as $f) {
            $data[$f] = ($data[$f] === '' || $data[$f] === null) ? '' : (float) $data[$f];
        }
        if ($isNew) {
            $data['creat_de'] = $name;
            $data['timestamp_creat'] = $now;
        }
        $data['actualizat_de'] = $name;
        $data['timestamp_actualizat'] = $now;

        return DB::transaction(function () use ($case, $isNew, $data, $before, $actor, $action, $section, $note) {
            return SterilizationCase::writing(function () use ($case, $isNew, $data, $before, $actor, $action, $section, $note) {
                $case ??= new SterilizationCase;
                $case->data = $data;
                foreach (self::INDEXED as $column => $max) {
                    $value = trim((string) ($data[$column] ?? ''));
                    $case->{$column} = $value === '' ? null : mb_substr($value, 0, $max);
                }
                foreach (self::DATES as $column) {
                    $case->{$column} = CaseFields::sanitizeDate($data[$column] ?? '');
                }
                $case->visit_group_id = ((int) ($data['visit_group_id'] ?? 0)) ?: null;
                $consentId = (int) (($data['consent_id'] ?? 0) ?: ($data['shared_consent_id'] ?? 0));
                $case->consent_id = $consentId && Consent::query()->whereKey($consentId)->exists() ? $consentId : null;
                $case->photo_upload_token = ($data['photo_upload_token'] ?? '') ?: null;
                if ($isNew) {
                    $case->created_by = $actor?->id;
                    $case->live_created_at = now();
                }
                $case->updated_by = $actor?->id;
                $case->live_updated_at = now();
                $case->save();

                $changes = [];
                foreach ($data as $field => $value) {
                    if (in_array($field, ['actualizat_de', 'timestamp_actualizat', 'creat_de', 'timestamp_creat'], true)) {
                        continue;
                    }
                    $old = $before[$field] ?? null;
                    if ((string) ($old ?? '') !== (string) ($value ?? '')) {
                        $changes[$field] = [mb_substr((string) ($old ?? ''), 0, 500), mb_substr((string) ($value ?? ''), 0, 500)];
                    }
                }
                if ($isNew || $changes || $note) {
                    SterilizationCaseChange::query()->create(['sterilization_case_id' => $case->id, 'actor_id' => $actor?->id, 'actor_name' => $actor?->name ?: 'Sistem',
                        'action' => $isNew ? 'created' : $action, 'section' => $section, 'changes' => $changes ?: null, 'note' => $note ? mb_substr($note, 0, 250) : null]);
                }

                return $case;
            });
        });
    }

    /**
     * Salvarea unei sectiuni a dosarului (handle_save_case).
     *
     * @param  array{photo?:?UploadedFile,ci?:?UploadedFile,remove_photo?:bool,remove_ci?:bool}  $files
     * @return array{case:SterilizationCase,validation_blocked:bool,number_saved:?string,renumbered:array,unresolved:array,booking_mail:?string,consent_code:?string}
     */
    public function saveSection(?SterilizationCase $case, string $section, array $input, array $files, User $actor): array
    {
        $section = ['fisa' => 'preoperator', 'documente' => 'receptie'][$section] ?? $section;
        if (! in_array($section, self::SECTIONS, true)) {
            throw ValidationException::withMessages(['section' => 'Secțiune necunoscută.']);
        }
        $existing = $case?->row();
        $postedNumber = array_key_exists('numar_pacient', $input) ? trim(strip_tags((string) $input['numar_pacient'])) : null;
        $manualNumber = $existing && $postedNumber !== null && $postedNumber !== trim((string) ($existing['numar_pacient'] ?? ''));

        $row = CaseFields::sanitizeInto($input, $existing ?? (['id' => 0] + CaseFields::defaults()));
        $isNew = ! $existing;
        if ($manualNumber) {
            // O schimbare de numar anuleaza confirmarea vizuala veche: numarul trebuie comparat din nou cu fotografia.
            $row['numar_pacient_foto_confirmat'] = $row['numar_pacient_foto_verificat_de'] = $row['numar_pacient_foto_verificat_at'] = '';
        }
        if (empty($row['photo_upload_token'])) {
            $row['photo_upload_token'] = Str::random(40);
        }

        if ($isNew) {
            $row['cod_caz'] = $row['cod_caz'] ?: $this->nextCode();
            if (empty($row['procedura_programata'])) {
                $row['procedura_programata'] = CaseFields::scheduledProcedure($row);
            }
            foreach (['nume_proprietar', 'telefon', 'email', 'nume_pacient', 'data_programare', 'ora_programare', 'specie', 'sex', 'vaccinat', 'procedura_programata'] as $required) {
                if (trim((string) ($row[$required] ?? '')) === '') {
                    throw ValidationException::withMessages(['data' => 'Completează câmpurile obligatorii pentru programare: nume proprietar, telefon, email, nume pacient, data și ora programării, specie, sex, vaccinat și intervenția programată.']);
                }
            }
            if (SterilizationCase::query()->where('cod_caz', $row['cod_caz'])->exists()) {
                throw ValidationException::withMessages(['cod_caz' => 'Există deja un dosar cu acest CodCaz.']);
            }
            $row['data_interventie'] = $row['data_interventie'] ?: $row['data_programare'];
        }
        if (empty($row['nr_fisa'])) {
            $row['nr_fisa'] = $row['cod_caz'] ?: '';
        }
        if (empty($row['data_fisa'])) {
            $row['data_fisa'] = CaseFields::today();
        }
        if (empty($row['cod_identificare'])) {
            $row['cod_identificare'] = $row['microcip'] ?: ($row['cod_caz'] ?: '');
        }

        $externalChanged = false;
        if ($section === 'preoperator') {
            $row['completat_preoperator_de'] = $actor->name;
            $row['timestamp_preoperator'] = CaseFields::now();
            $row['status_preoperator'] = 'Completat';
            $row['status_caz'] = match ($row['pacient_apt']) {
                'Da' => 'Apt interventie', 'Nu' => 'Amanat medical', default => 'Prezentat'
            };
            $row['data_prezentare'] = $row['data_prezentare'] ?: CaseFields::today();
            $row['data_interventie'] = $row['data_interventie'] ?: $row['data_programare'];
        }
        if ($section === 'interventie') {
            $row['data_interventie'] = CaseFields::sanitizeDate($row['data_interventie']) ?: (CaseFields::sanitizeDate($row['data_programare']) ?: CaseFields::today());
            // Luna raportului se fixeaza la finalizarea interventiei; corectarile ulterioare nu o mai muta.
            if (empty($row['data_raportare_primarie'])) {
                $row['data_raportare_primarie'] = $row['data_interventie'];
            }
            $row['procedura_efectuata'] = $row['procedura_efectuata'] ?: CaseFields::medicalProcedure($row);
            if (trim((string) $row['mentiuni_intra_postoperatorii']) === '') {
                throw ValidationException::withMessages(['mentiuni_intra_postoperatorii' => 'Completează Mențiuni intra/postoperatorii. Dacă nu există observații, scrie „Fără observații”.']);
            }
            $row['completat_interventie_de'] = $actor->name;
            $row['timestamp_interventie'] = CaseFields::now();
            $row['status_interventie'] = 'Finalizat';
            $row['status_caz'] = 'Operat';
        }
        if ($section === 'externare') {
            $row['completat_externare_de'] = $actor->name;
            $row['timestamp_externare'] = CaseFields::now();
            $row['recomandari_postoperatorii'] = 'Da';
            $row['followup_efectuat'] = '';
            $row['data_followup'] = null;
            $row['status_externare'] = 'Externat';
            $row['status_caz'] = 'Externat';
            $row['data_control'] = ($row['control_recomandat'] === 'Da' && ! empty($row['data_externare']))
                ? date('Y-m-d', strtotime($row['data_externare'].' +14 days')) : null;
            $row['postop_email_due_at'] = CampaignMailer::postopDueAt($row);
            $externalChanged = ! $existing || ($existing['data_externare'] ?? '') !== ($row['data_externare'] ?? '') || ($existing['ora_externare'] ?? '') !== ($row['ora_externare'] ?? '')
                || ($existing['email'] ?? '') !== ($row['email'] ?? '') || ($existing['recomandari_postoperatorii_text'] ?? '') !== ($row['recomandari_postoperatorii_text'] ?? '');
            if ($externalChanged) {
                $row['postop_email_sent_at'] = $row['postop_email_status'] = $row['postop_email_error'] = $row['postop_email_manual_at'] = '';
            }
        }
        if ($section === 'corectare') {
            $row['procedura_programata'] = $row['procedura_programata'] ?: CaseFields::scheduledProcedure($row);
        }

        $row = $this->checks->autoValidation($row);
        $blocked = false;
        if ($section === 'validare') {
            if ($row['validat_pentru_primarie'] === 'Da' && ! CityChecks::isReady($row)) {
                $row['validat_pentru_primarie'] = 'Nu';
                $blocked = true;
            } elseif ($row['validat_pentru_primarie'] === 'Da') {
                $row['status_caz'] = 'Validat primarie';
            }
            if (empty($row['data_validare'])) {
                $row['data_validare'] = CaseFields::today();
            }
        }

        return DB::transaction(function () use ($case, $row, $section, $files, $actor, $isNew, $manualNumber, $blocked, $externalChanged) {
            // Fisierele: CI la receptie/corectare, fotografie la interventie/corectare. Ca in plugin, se aplica inainte
            // de salvare (pentru un dosar nou, imediat dupa ce primeste ID).
            if ($isNew) {
                $case = $this->persist(null, $row, $actor, 'created', $section, null, true, $manualNumber);
                $row = $case->row();
                if ($this->applyFiles($case, $row, $section, $files, $actor)) {
                    $case = $this->persist($case, $this->checks->autoValidation($row), $actor, 'file', $section);
                }
            } else {
                if ($this->applyFiles($case, $row, $section, $files, $actor)) {
                    $row = $this->checks->autoValidation($row);
                }
                $case = $this->persist($case, $row, $actor, 'updated', $section, null, true, $manualNumber);
            }
            $row = $case->row();

            $resolution = ['renumbered' => [], 'unresolved' => []];
            if ($manualNumber) {
                $resolution = $this->resolveManualConflicts($row, $actor);
            }
            if (! $isNew) {
                $row = $this->ensureVisitGroup($case, $actor);
                $this->syncVisitOwner($row, true, $actor);
            }

            $bookingMail = null;
            if (in_array($section, ['receptie', 'preoperator'], true)) {
                $appointment = app(CampaignAppointments::class)->syncInitialForCase($case, $actor);
                if ($appointment && $isNew) {
                    [$sent] = app(CampaignMailer::class)->sendAppointmentEmail($appointment, 'booking_confirmation', $actor);
                    $bookingMail = $sent ? 'sent' : 'failed';
                }
                $case->refresh();
                $row = $case->row();
            }

            $this->consents->lastCode = null;
            $consentId = $this->consents->sync($row, $this->groupRows($row), $actor);
            if ($consentId) {
                $this->assignConsentToGroup($row, $consentId, $actor);
                $case->refresh();
            }
            if ($section === 'externare' && $externalChanged) {
                app(CampaignMailer::class)->sendFinalSummary($case->refresh(), false, $actor);
            }

            return ['case' => $case->refresh(), 'validation_blocked' => $blocked, 'number_saved' => $manualNumber ? trim((string) $row['numar_pacient']) : null,
                'renumbered' => $resolution['renumbered'], 'unresolved' => $resolution['unresolved'], 'booking_mail' => $bookingMail, 'consent_code' => $this->consents->lastCode];
        });
    }

    /** Aplica incarcarile/stergerile de fisiere ale sectiunii in $row. Intoarce true daca s-a schimbat ceva. */
    private function applyFiles(SterilizationCase $case, array &$row, string $section, array $files, User $actor): bool
    {
        $changed = false;
        if (in_array($section, ['receptie', 'corectare'], true)) {
            if (! empty($files['remove_ci'])) {
                $this->files->supersede($case, SterilizationDocument::ID_CARD);
                $row['documente_scanate'] = '';
                $row['ci_verificat'] = 'Nu';
                $changed = true;
            } elseif (($files['ci'] ?? null) instanceof UploadedFile) {
                $row['documente_scanate'] = $this->files->store($case, $files['ci'], SterilizationDocument::ID_CARD, $section === 'corectare' ? 'Corectare dosar' : 'Recepție', $actor);
                $row['ci_verificat'] = 'Da';
                $row['completat_documente_de'] = $actor->name;
                $row['timestamp_documente'] = CaseFields::now();
                $changed = true;
            }
        }
        if (in_array($section, ['interventie', 'corectare'], true)) {
            if (! empty($files['remove_photo'])) {
                $this->files->supersede($case, SterilizationDocument::PHOTO);
                $row['poza_pacient'] = $row['photo_uploaded_at'] = $row['photo_upload_source'] = '';
                $changed = true;
            } elseif (($files['photo'] ?? null) instanceof UploadedFile) {
                $source = $section === 'corectare' ? 'Corectare dosar' : 'Incarcare locala';
                $row['poza_pacient'] = $this->files->store($case, $files['photo'], SterilizationDocument::PHOTO, $source, $actor);
                $row['photo_uploaded_at'] = CaseFields::now();
                $row['photo_upload_source'] = $source;
                $changed = true;
            }
        }

        return $changed;
    }

    /** resolve_manual_patient_number_conflicts: dosarele FARA fotografie cu acelasi numar primesc numar nou; cele cu fotografie nu. */
    public function resolveManualConflicts(array $row, ?User $actor): array
    {
        $result = ['renumbered' => [], 'unresolved' => []];
        $number = trim((string) ($row['numar_pacient'] ?? ''));
        if ($number === '') {
            return $result;
        }
        $others = SterilizationCase::query()->active()->whereKeyNot((int) $row['id'])->where('numar_pacient', $number)
            ->whereRaw(CityChecks::yearSql().' = ?', [(string) CityChecks::patientNumberYear($row)])->orderBy('id')->get();
        foreach ($others as $other) {
            $o = $other->row();
            if (! empty($o['poza_pacient'])) {
                $result['unresolved'][] = ['id' => $other->id, 'code' => $o['cod_caz'], 'patient' => $o['nume_pacient'], 'number' => $number];

                continue;
            }
            $new = $this->nextPatientNumber(CityChecks::patientNumberYear($o), $other->id);
            if ($new === '' || $new === $number) {
                continue;
            }
            $o['numar_pacient_vechi'] = $number;
            $o['numar_pacient'] = $new;
            $o['numar_pacient_auto_at'] = CaseFields::now();
            $this->persist($other, $o, $actor, 'renumbered', null, 'Număr eliberat pentru dosarul '.$row['cod_caz'], false);
            $result['renumbered'][] = ['id' => $other->id, 'code' => $o['cod_caz'], 'patient' => $o['nume_pacient'], 'old' => $number, 'new' => $new];
        }

        return $result;
    }

    public function ensureVisitGroup(SterilizationCase $case, ?User $actor): array
    {
        $row = $case->row();
        if ((int) ($row['visit_group_id'] ?? 0) === 0) {
            $row['visit_group_id'] = $case->id;
            $this->persist($case, $row, $actor, 'visit', null, null, false);
        }

        return $case->refresh()->row();
    }

    /** sync_visit_owner_from_case: proprietarul (si CI-ul) sunt aceleasi in toate dosarele vizitei. */
    public function syncVisitOwner(array $row, bool $syncDocument, ?User $actor): void
    {
        $source = SterilizationCase::query()->find((int) $row['id']);
        foreach ($this->groupRows($row) as $other) {
            if ((int) $other['id'] === (int) $row['id']) {
                continue;
            }
            $o = $other;
            foreach (['nume_proprietar', 'telefon', 'email', 'adresa_ci', 'serie_numar_ci', 'localitate'] as $f) {
                $o[$f] = $row[$f];
            }
            $model = SterilizationCase::query()->find((int) $other['id']);
            if ($syncDocument) {
                $o['ci_verificat'] = $row['ci_verificat'];
                $o['observatii_documente'] = $row['observatii_documente'];
                $o['documente_scanate'] = $source ? (string) ($this->files->shareIdCard($source, $model) ?? '') : '';
            }
            $differs = false;
            foreach ($o as $k => $v) {
                $differs = $differs || (string) ($v ?? '') !== (string) ($other[$k] ?? '');
            }
            if ($differs) {
                $this->persist($model, $o, $actor, 'visit_sync', null, 'Date proprietar sincronizate din dosarul '.$row['cod_caz'], false);
            }
        }
        if ($appointmentId = (int) ($row['initial_appointment_id'] ?? 0)) {
            SterilizationAppointment::query()->whereKey($appointmentId)->update(['owner_name' => $row['nume_proprietar'], 'client_phone' => $row['telefon'],
                'client_email' => $row['email'], 'updated_by_name' => $actor?->name, 'updated_at' => now()]);
        }
        if ($consent = $this->checks->consentFor($row)) {
            $this->consents->syncOwner($consent, $row);
            $this->consents->syncVisitPatients($consent, $this->groupRows($row));
        }
    }

    /** Consimtamantul comun se leaga de toate dosarele vizitei (assign_consent_to_visit_group). */
    public function assignConsentToGroup(array $row, int $consentId, ?User $actor): void
    {
        foreach ($this->groupRows($row) as $other) {
            if ((int) ($other['consent_id'] ?? 0) === $consentId && (int) ($other['shared_consent_id'] ?? 0) === $consentId) {
                continue;
            }
            $other['consent_id'] = $other['shared_consent_id'] = $consentId;
            $other['consent_synced_at'] = CaseFields::now();
            $this->persist(SterilizationCase::query()->find((int) $other['id']), $other, $actor, 'consent', null, null, false);
        }
    }

    /** Dosar nou creat dintr-o programare telefonica (create_case_from_appointment). */
    public function createFromAppointment(array $a, ?array $owner, ?User $actor): SterilizationCase
    {
        $map = CaseFields::caseFieldsForAppointmentType($a['appointment_type'] ?? '');
        $row = ['id' => 0] + CaseFields::defaults();
        $row['cod_caz'] = $this->nextCode();
        $row['data_programare'] = $a['appointment_date'];
        $row['ora_programare'] = $a['start_time'];
        $row['data_interventie'] = $a['appointment_date'];
        foreach (['nume_proprietar', 'telefon', 'email', 'adresa_ci', 'serie_numar_ci', 'localitate'] as $f) {
            if ($owner && array_key_exists($f, $owner) && $owner[$f] !== '') {
                $row[$f] = $owner[$f];
            }
        }
        $row['nume_proprietar'] = ($a['owner_name'] ?? '') ?: $row['nume_proprietar'];
        $row['telefon'] = ($a['client_phone'] ?? '') ?: $row['telefon'];
        $row['email'] = ($a['client_email'] ?? '') ?: $row['email'];
        $row['specie'] = $map['specie'];
        $row['sex'] = $map['sex'];
        $row['vaccinat'] = in_array($a['vaccinat'] ?? '', ['Da', 'Nu'], true) ? $a['vaccinat'] : '';
        $row['nume_pacient'] = (string) ($a['patient_name'] ?? '');
        $row['animal_cu_stapan'] = 'Cu stapan';
        $row['procedura_programata'] = $map['procedura'];
        $row['status_caz'] = 'Programat';
        $row['photo_upload_token'] = Str::random(40);
        $row['nr_fisa'] = $row['cod_caz'];
        $row['data_fisa'] = CaseFields::today();
        $row['cod_identificare'] = $row['cod_caz'];

        return $this->persist(null, $this->checks->autoValidation($row), $actor, 'created', 'programare', 'Creat din programare telefonică');
    }

    /** sync_case_from_appointment: data, ora, proprietarul si tipul programarii trec in dosar. */
    public function syncFromAppointment(SterilizationCase $case, SterilizationAppointment $a, ?string $vaccinat, ?User $actor): SterilizationCase
    {
        $row = $case->row();
        $map = CaseFields::caseFieldsForAppointmentType((string) $a->appointment_type);
        $row['data_programare'] = $a->appointment_date?->format('Y-m-d') ?? $row['data_programare'];
        $row['ora_programare'] = $a->start_time ?: $row['ora_programare'];
        $row['data_interventie'] = $row['data_interventie'] ?: $row['data_programare'];
        $row['nume_proprietar'] = (string) ($a->owner_name ?? $row['nume_proprietar']);
        $row['telefon'] = (string) ($a->client_phone ?? $row['telefon']);
        if ($a->client_email) {
            $row['email'] = (string) $a->client_email;
        }
        if ($a->patient_name) {
            $row['nume_pacient'] = (string) $a->patient_name;
        }
        $row['specie'] = $map['specie'];
        $row['sex'] = $map['sex'];
        $row['procedura_programata'] = $map['procedura'];
        if (in_array($vaccinat, ['Da', 'Nu'], true)) {
            $row['vaccinat'] = $vaccinat;
        }
        $row['initial_appointment_id'] = $a->id;
        $row['initial_appointment_synced_at'] = CaseFields::now();

        return $this->persist($case, $this->checks->autoValidation($row), $actor, 'appointment_sync', 'programare');
    }

    /** handle_add_visit_pet: alt animal al aceluiasi proprietar, in aceeasi vizita, cu dosar propriu. */
    public function addVisitPet(SterilizationCase $parent, array $input, User $actor): SterilizationCase
    {
        $name = trim(strip_tags((string) ($input['patient_name'] ?? '')));
        $species = (string) ($input['species'] ?? '');
        $sex = (string) ($input['sex'] ?? '');
        if ($name === '' || ! in_array($species, CaseFields::choices()['specie'], true) || ! in_array($sex, CaseFields::choices()['sex'], true)) {
            throw ValidationException::withMessages(['visit_pet' => 'Completează numele, specia și sexul noului pacient.']);
        }

        return DB::transaction(function () use ($parent, $input, $actor, $name, $species, $sex) {
            $p = $this->ensureVisitGroup($parent, $actor);
            $row = ['id' => 0] + CaseFields::defaults();
            foreach (['data_programare', 'ora_programare', 'initial_appointment_id', 'initial_appointment_synced_at', 'nume_proprietar', 'telefon', 'email',
                'adresa_ci', 'serie_numar_ci', 'localitate', 'animal_cu_stapan', 'ci_verificat', 'carnet_sau_fisa', 'observatii_documente'] as $f) {
                $row[$f] = $p[$f] ?? $row[$f];
            }
            $row['cod_caz'] = $this->nextCode();
            $row['visit_group_id'] = (int) $p['visit_group_id'];
            $row['nume_pacient'] = $name;
            $row['specie'] = $species;
            $row['sex'] = $sex;
            $row['rasa'] = trim(strip_tags((string) ($input['breed'] ?? '')));
            $row['greutate_reala'] = CaseFields::sanitizeAmount($input['weight'] ?? '') ?? '';
            $row['vaccinat'] = in_array($input['vaccinat'] ?? '', ['Da', 'Nu'], true) ? $input['vaccinat'] : '';
            $row['animal_cu_stapan'] = $row['animal_cu_stapan'] ?: 'Cu stapan';
            $row['procedura_programata'] = CaseFields::scheduledProcedure($row);
            $row['status_caz'] = 'Prezentat';
            $row['data_prezentare'] = CaseFields::today();
            $row['data_interventie'] = $p['data_interventie'] ?: ($p['data_programare'] ?: CaseFields::today());
            $row['photo_upload_token'] = Str::random(40);
            $row['nr_fisa'] = $row['cod_caz'];
            $row['data_fisa'] = CaseFields::today();
            $row['cod_identificare'] = $row['cod_caz'];
            $shared = (int) (($p['consent_id'] ?? 0) ?: ($p['shared_consent_id'] ?? 0));
            if ($shared) {
                $row['consent_id'] = $row['shared_consent_id'] = $shared;
                $row['consent_synced_at'] = CaseFields::now();
            }
            $child = $this->persist(null, $this->checks->autoValidation($row), $actor, 'created', 'vizita', 'Animal adăugat la vizita dosarului '.$p['cod_caz']);
            $documents = $this->files->shareIdCard($parent, $child);
            if ($documents) {
                $c = $child->row();
                $c['documente_scanate'] = $documents;
                $child = $this->persist($child, $c, $actor, 'file', 'vizita', null, false);
            }

            if ($consent = $this->checks->consentFor($p)) {
                $this->consents->syncVisitPatients($consent, $this->groupRows($child->row()));
            } else {
                $fresh = $parent->refresh()->row();
                if ($id = $this->consents->sync($fresh, $this->groupRows($fresh), $actor)) {
                    $this->assignConsentToGroup($fresh, $id, $actor);
                }
            }
            Audit::log('sterilization.visit_pet_added', $child, 'Animal adăugat în vizită: '.$name.' ('.$child->cod_caz.')');

            return $child->refresh();
        });
    }

    /**
     * Scoate un animal dintr-o vizita cu mai multi pacienti. ABATERE: dosarul nu se sterge fizic (pluginul il
     * stergea impreuna cu jurnalul de emailuri); se marcheaza sters, cu motiv, si ramane in istoric.
     */
    public function removeVisitPet(SterilizationCase $case, string $reason, User $actor): SterilizationCase
    {
        $row = $case->row();
        $group = $this->groupRows($row);
        if (count($group) <= 1) {
            throw ValidationException::withMessages(['visit_pet' => 'Acesta este singurul pacient al vizitei. Pentru el folosește acțiunea „Șterge dosarul”.']);
        }
        $remaining = array_values(array_filter($group, fn ($g) => (int) $g['id'] !== $case->id));
        $replacement = SterilizationCase::query()->findOrFail((int) $remaining[0]['id']);

        return DB::transaction(function () use ($case, $row, $remaining, $replacement, $reason, $actor) {
            foreach (SterilizationAppointment::query()->where('sterilization_case_id', $case->id)->get() as $a) {
                $a->update(['sterilization_case_id' => $replacement->id, 'patient_name' => $replacement->nume_pacient ?: $a->patient_name,
                    'title' => trim($a->appointment_type.' - '.($replacement->nume_pacient ?: $a->patient_name), ' -'), 'updated_by_name' => $actor->name]);
            }
            $this->softDelete($case, 'Scos din vizită: '.$reason, $actor);
            $oldGroup = (int) ($row['visit_group_id'] ?? 0) ?: $case->id;
            if ($oldGroup === $case->id) {
                foreach ($remaining as $r) {
                    $r['visit_group_id'] = $replacement->id;
                    $this->persist(SterilizationCase::query()->find((int) $r['id']), $r, $actor, 'visit', null, 'Vizita regrupată după scoaterea dosarului '.$row['cod_caz'], false);
                }
            }
            $fresh = $replacement->refresh()->row();
            if ($consent = $this->checks->consentFor($row)) {
                $this->assignConsentToGroup($fresh, $consent->id, $actor);
                $this->consents->syncVisitPatients($consent, $this->groupRows($fresh));
            }

            return $replacement->refresh();
        });
    }

    /** ABATERE: stergerea unui dosar il marcheaza sters (cu motiv); datele, fisierele si jurnalul raman. */
    public function softDelete(SterilizationCase $case, string $reason, User $actor): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['reason' => 'Scrie motivul ștergerii (minimum 5 caractere).']);
        }
        SterilizationCase::writing(function () use ($case, $reason, $actor) {
            $case->forceFill(['deleted_at' => now(), 'updated_by' => $actor->id, 'live_updated_at' => now()])->save();
            SterilizationCaseChange::query()->create(['sterilization_case_id' => $case->id, 'actor_id' => $actor->id, 'actor_name' => $actor->name,
                'action' => 'deleted', 'note' => mb_substr($reason, 0, 250)]);
        });
        SterilizationAppointment::query()->where('sterilization_case_id', $case->id)->where('status', '!=', 'Anulat')
            ->update(['status' => 'Anulat', 'updated_by_name' => $actor->name, 'updated_at' => now()]);
        Audit::log('sterilization.case_deleted', $case, 'Dosar marcat șters: '.$case->cod_caz.' – '.$reason);
    }

    /** handle_start_consultation: pacientul a sosit; dosarul devine Prezentat si se deschide consultatia. */
    public function startConsultation(SterilizationAppointment $a, User $actor): SterilizationCase
    {
        $case = SterilizationCase::query()->active()->find($a->sterilization_case_id);
        if (! $case) {
            throw ValidationException::withMessages(['appointment' => 'Programarea nu are un dosar asociat.']);
        }
        $row = $case->row();
        $row['status_caz'] = 'Prezentat';
        $row['data_prezentare'] = $row['data_prezentare'] ?: CaseFields::today();
        $case = $this->persist($case, $row, $actor, 'consultation_started', 'preoperator', null, false);
        if (! in_array($a->status, ['Finalizat', 'Anulat'], true)) {
            $a->update(['status' => 'Prezentat', 'updated_by_name' => $actor->name]);
        }

        return $case;
    }

    /** Fotografia incarcata de pe telefon prin codul QR al dosarului. */
    public function storePhoneUpload(SterilizationCase $case, UploadedFile $file): SterilizationCase
    {
        $row = $case->row();
        $row['poza_pacient'] = $this->files->store($case, $file, SterilizationDocument::PHOTO, 'Telefon (QR)', null);
        $row['photo_uploaded_at'] = CaseFields::now();
        $row['photo_upload_source'] = 'Telefon (QR)';
        if (trim((string) $row['numar_pacient_raport']) === '') {
            self::freezeReportNumber($row, 'confirmat_foto');
        }

        return $this->persist($case, $this->checks->autoValidation($row), null, 'file', 'interventie', 'Fotografie încărcată de pe telefon', false);
    }
}
