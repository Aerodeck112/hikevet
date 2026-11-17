<?php

namespace App\Services\Campaign;

use App\Models\SterilizationCase;
use App\Models\SterilizationDocument;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Raportul pentru Primarie, pe datele live ale campaniei (render_reports si functiile lui din plugin):
 * selectia lunii, numerele de raport inghetate, auditul de integritate, verificarea dosarelor pentru ZIP,
 * controlul numerelor dupa fotografie, registrul 1 → N si reconcilierea iulie–septembrie 2026.
 *
 * Fisierele (Excel, PDF, ZIP) se construiesc in CityExports.
 */
class CityReports
{
    public const FINAL = ['Finalizat', 'Operat'];

    /** Raportul din octombrie 2026 foloseste strict data interventiei si exclude cazurile raportate pana in septembrie. */
    public const OCTOBER = '2026-10';

    public const SEPTEMBER_145 = 'september-2026-145';

    public const BULK_VALIDATOR = 'Dr. Festeu Radu';

    public const RECONCILIATION_MONTHS = ['2026-07' => 'Iulie 2026', '2026-08' => 'August 2026', '2026-09' => 'Septembrie 2026'];

    /** Numerele confirmate in plugin ca sarite in seria fizica (registry_reconciliation_settings). */
    public const VOID_DEFAULT = '92,214,267-268,270-273,275-276,379';

    private const MONTHS = [1 => 'Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'];

    /** @var array<int, array<string, SterilizationDocument>>|null */
    private ?array $documents = null;

    public function __construct(private readonly CampaignCases $cases, private readonly CityChecks $checks, private readonly CampaignConsents $consents) {}

    public static function validMonth(?string $month): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $month);
    }

    public static function monthLabel(?string $month): string
    {
        if (! self::validMonth($month)) {
            return 'Toată perioada';
        }

        return self::MONTHS[(int) substr($month, 5, 2)].' '.substr($month, 0, 4);
    }

    /** Data de raportare: data fixata pentru Primarie, altfel ziua finalizarii interventiei, altfel data interventiei. */
    public static function effectiveDate(array $row): string
    {
        return CaseFields::sanitizeDate($row['data_raportare_primarie'] ?? '')
            ?? CaseFields::sanitizeDate(substr((string) ($row['timestamp_interventie'] ?? ''), 0, 10))
            ?? CaseFields::sanitizeDate($row['data_interventie'] ?? '')
            ?? '';
    }

    public static function isFinal(array $row): bool
    {
        return in_array($row['status_interventie'] ?? '', self::FINAL, true);
    }

    /** @return list<array> toate dosarele active, ca randuri */
    private function activeRows(): array
    {
        return SterilizationCase::query()->active()->orderBy('id')->get()->map->row()->all();
    }

    /** @return list<SterilizationCase> */
    private function finalizedCases(): array
    {
        return SterilizationCase::query()->active()->whereIn('status_interventie', self::FINAL)->orderBy('id')->get()->all();
    }

    private function save(SterilizationCase $case, array $row, ?User $actor, string $action, string $note, bool $numbers = false): SterilizationCase
    {
        $this->documents = null;

        return $this->cases->persist($case, $row, $actor, $action, 'raport', $note, $numbers);
    }

    // ---------------------------------------------------------------- selectie

    /**
     * Pregateste raportul (finalized_intervention_rows): fixeaza data de raportare si numarul de raport pentru
     * interventiile finalizate care nu le au inca. Valorile deja fixate nu se schimba.
     */
    public function prepare(?string $month, ?User $actor): array
    {
        $this->backfillReportingDates($actor);

        return $this->backfillReportNumbers($month, $actor);
    }

    /** backfill_reporting_dates() */
    public function backfillReportingDates(?User $actor): int
    {
        $count = 0;
        foreach ($this->finalizedCases() as $case) {
            $row = $case->row();
            if (CaseFields::sanitizeDate($row['data_raportare_primarie'] ?? '') || ($date = self::effectiveDate($row)) === '') {
                continue;
            }
            $row['data_raportare_primarie'] = $date;
            $this->save($case, $row, $actor, 'report_date_fixed', 'Data raportării către Primărie fixată automat ('.$date.')');
            $count++;
        }

        return $count;
    }

    /** backfill_report_patient_numbers(): numarul din raport se ingheata; un numar istoric sigur se restaureaza doar pentru raport. */
    public function backfillReportNumbers(?string $month, ?User $actor): array
    {
        $result = ['checked' => 0, 'frozen_current' => [], 'restored_history' => [], 'unresolved_history' => []];
        $all = null;
        foreach ($this->finalizedCases() as $case) {
            $row = $case->row();
            if (self::validMonth($month) && substr(self::effectiveDate($row), 0, 7) !== $month) {
                continue;
            }
            $result['checked']++;
            if (trim((string) $row['numar_pacient_raport']) !== '') {
                continue;
            }
            $current = trim((string) $row['numar_pacient']);
            $old = trim((string) $row['numar_pacient_vechi']);
            $all ??= $this->activeRows();

            if ($old !== '' && $this->historicalCandidateIsSafe($row, $old, $all)) {
                $row['numar_pacient_raport'] = $old;
                $row['numar_pacient_raport_sursa'] = 'istoric_auto_restaurat';
                $row['numar_pacient_raport_confirmat_at'] = CaseFields::now();
                $this->save($case, $row, $actor, 'report_number_fixed', 'Număr raport restaurat din istoric: '.$old);
                $result['restored_history'][] = ['id' => $case->id, 'code' => $row['cod_caz'], 'patient' => $row['nume_pacient'], 'old' => $old, 'current' => $current];

                continue;
            }
            if ($current !== '') {
                $row['numar_pacient_raport'] = $current;
                $row['numar_pacient_raport_sursa'] = 'backfill_curent';
                $row['numar_pacient_raport_confirmat_at'] = CaseFields::now();
                $this->save($case, $row, $actor, 'report_number_fixed', 'Număr raport fixat: '.$current);
                $result['frozen_current'][] = ['id' => $case->id, 'code' => $row['cod_caz'], 'patient' => $row['nume_pacient'], 'number' => $current];
                if ($old !== '' && $old !== $current && trim((string) $row['numar_pacient_auto_at']) !== '') {
                    $result['unresolved_history'][] = ['id' => $case->id, 'code' => $row['cod_caz'], 'patient' => $row['nume_pacient'], 'old' => $old, 'current' => $current];
                }
            }
        }

        return $result;
    }

    /** historical_report_number_candidate_is_safe() */
    private function historicalCandidateIsSafe(array $row, string $candidate, array $all): bool
    {
        $current = trim((string) $row['numar_pacient']);
        if (! preg_match('/^\d+$/', $candidate) || empty($row['id']) || empty($row['poza_pacient']) || trim((string) $row['numar_pacient_auto_at']) === ''
            || $current === '' || $current === $candidate) {
            return false;
        }
        $year = CityChecks::patientNumberYear($row);
        $history = 0;
        foreach ($all as $other) {
            if ((int) $other['id'] !== (int) $row['id'] && CityChecks::reportNumber($other) === $candidate && CityChecks::patientNumberYear($other) === $year
                && (CaseFields::sanitizeDate($other['data_interventie']) || CaseFields::sanitizeDate($other['data_programare']) || CaseFields::sanitizeDate($other['data_fisa']))) {
                return false;
            }
            if (trim((string) $other['numar_pacient_vechi']) === $candidate) {
                $history++;
            }
        }

        return $history === 1;
    }

    /**
     * Interventiile finalizate ale perioadei, ordonate dupa numarul din raport (finalized_intervention_rows).
     *
     * @return list<array>
     */
    public function finalizedRows(?string $month, bool $excludeReported = true): array
    {
        $october = $month === self::OCTOBER;
        $rows = [];
        foreach ($this->finalizedCases() as $case) {
            $row = $case->row();
            $row['data_raportare_effective'] = $october ? (CaseFields::sanitizeDate($row['data_interventie']) ?? '') : self::effectiveDate($row);
            if ($october) {
                if (substr($row['data_raportare_effective'], 0, 7) !== self::OCTOBER) {
                    continue;
                }
            } elseif (self::validMonth($month) && substr($row['data_raportare_effective'], 0, 7) !== $month) {
                continue;
            }
            $rows[] = $row;
        }
        usort($rows, function (array $a, array $b) {
            $na = CityChecks::reportNumber($a);
            $nb = CityChecks::reportNumber($b);
            $ia = preg_match('/^\d+$/', $na) ? (int) $na : PHP_INT_MAX;
            $ib = preg_match('/^\d+$/', $nb) ? (int) $nb : PHP_INT_MAX;
            if ($ia !== $ib) {
                return $ia <=> $ib;
            }
            $da = (string) ($a['data_raportare_effective'] ?: $a['data_interventie']);
            $db = (string) ($b['data_raportare_effective'] ?: $b['data_interventie']);

            return $da !== $db ? strcmp($da, $db) : strcmp((string) $a['cod_caz'], (string) $b['cod_caz']);
        });

        return $excludeReported ? $this->excludePriorReports($rows, $month) : $rows;
    }

    /** @return array<string,string> cod => luna raportarii anterioare */
    public function priorCodes(): array
    {
        return DB::table('sterilization_reference_lists')->where('list_key', 'reported_through_september_2026')->orderBy('cod_caz')
            ->pluck('value', 'cod_caz')->map(fn ($v) => (string) $v)->all();
    }

    /** exclude_prior_reports(): doar pentru raportul din octombrie 2026. */
    public function excludePriorReports(array $rows, ?string $month): array
    {
        if ($month !== self::OCTOBER) {
            return $rows;
        }
        $codes = $this->priorCodes();
        if (! $codes) {
            throw new RuntimeException('Lista cazurilor raportate până în septembrie 2026 lipsește. Fără ea, raportul din octombrie ar include cazuri deja raportate. Importă lista din backup sau completeaz-o în secțiunea „Octombrie 2026”.');
        }

        return array_values(array_filter($rows, fn ($r) => ! isset($codes[strtoupper(trim((string) $r['cod_caz']))])));
    }

    public static function listHash(array $codes): string
    {
        ksort($codes);

        return hash('sha256', json_encode($codes));
    }

    /** handle_save_prior_reports(): un cod pe linie, optional urmat de luna 2026-07 / 08 / 09. */
    public function savePriorCodes(string $raw, string $expectedHash, User $actor): int
    {
        if (strlen($raw) > 250000) {
            throw new RuntimeException('Lista este prea mare.');
        }
        $codes = [];
        foreach (preg_split('/\R/', trim($raw)) as $line) {
            $line = strtoupper(trim($line));
            if ($line === '') {
                continue;
            }
            if (! preg_match('/^(HV-ST-2026-[0-9]+)(?:[;, \t]+(2026-0[789]))?$/D', $line, $m)) {
                throw new RuntimeException('Linie invalidă: '.Str::limit($line, 60).'. Folosește un cod HV-ST-2026-xxx pe linie, opțional urmat de luna 2026-07, 2026-08 sau 2026-09.');
            }
            $codes[$m[1]] = $m[2] ?? '2026-09';
        }
        ksort($codes);
        $current = $this->priorCodes();
        if (! hash_equals(self::listHash($current), $expectedHash)) {
            throw new RuntimeException('Lista a fost modificată între timp. Reîncarcă raportul înainte de salvare.');
        }
        DB::transaction(function () use ($codes, $current, $actor) {
            Settings::set('campaign.prior_reports_previous', ['codes' => $current, 'replaced_at' => now()->toIso8601String(), 'replaced_by' => $actor->name]);
            DB::table('sterilization_reference_lists')->where('list_key', 'reported_through_september_2026')->delete();
            foreach ($codes as $code => $period) {
                DB::table('sterilization_reference_lists')->insert(['list_key' => 'reported_through_september_2026', 'cod_caz' => $code, 'value' => $period]);
            }
        });
        Audit::log('campaign.prior_reports_saved', null, 'Lista cazurilor raportate până în septembrie 2026: '.count($current).' → '.count($codes).' coduri');

        return count($codes);
    }

    /**
     * Selectia fixa de 145 de dosare pentru septembrie 2026 (september_145_rows). Fara potrivire exacta nu se exporta nimic.
     *
     * @return array{rows:list<array>,error:?string}
     */
    public function september145(): array
    {
        $manifest = DB::table('sterilization_reference_lists')->where('list_key', 'september_2026_145')->orderBy('id')->pluck('value', 'cod_caz')->all();
        if (count($manifest) !== 145) {
            return ['rows' => [], 'error' => 'Lista fixă Septembrie 2026 trebuie să conțină exact 145 de dosare (găsite: '.count($manifest).').'];
        }
        foreach ($manifest as $code => $number) {
            if (! preg_match('/^HV-ST-2026-[0-9]+$/', (string) $code) || ! ctype_digit((string) $number)) {
                return ['rows' => [], 'error' => 'Lista fixă de dosare este invalidă.'];
            }
        }
        $byCode = SterilizationCase::query()->active()->whereIn('cod_caz', array_keys($manifest))->get()->keyBy('cod_caz');
        $rows = [];
        $errors = [];
        foreach ($manifest as $code => $number) {
            $case = $byCode->get($code);
            if (! $case) {
                $errors[] = $code.': dosar lipsă';

                continue;
            }
            $row = $case->row();
            if (! self::isFinal($row)) {
                $errors[] = $code.': intervenția nu este Finalizat/Operat';
            }
            if (CityChecks::reportNumber($row) !== (string) $number) {
                $errors[] = $code.': numărul din raport diferă de numărul verificat '.$number;
            }
            $row['data_raportare_effective'] = self::effectiveDate($row);
            $rows[] = $row;
        }

        return $errors ? ['rows' => [], 'error' => implode(' | ', $errors)] : ['rows' => $rows, 'error' => null];
    }

    // ---------------------------------------------------------------- desfasurator si centralizator

    public static function substances(array $r): string
    {
        $parts = [];
        foreach ([['substanta1', 'doza1'], ['substanta2', 'doza2'], ['substanta3', 'doza3']] as [$s, $d]) {
            if (trim((string) ($r[$s] ?? '')) !== '') {
                $parts[] = trim($r[$s].' '.($r[$d] ?? ''));
            }
        }
        if (trim((string) ($r['alte_substante'] ?? '')) !== '') {
            $parts[] = $r['alte_substante'];
        }

        return implode('; ', $parts);
    }

    /** report_map(): un rand al desfasuratorului; cheile interne nu apar in exportul oficial. */
    public function reportMap(array $rows): array
    {
        $out = [];
        $nr = 1;
        foreach ($rows as $r) {
            $effective = (string) ($r['data_raportare_effective'] ?? '');
            $lunaSource = $effective ?: (string) $r['data_interventie'];
            $consent = $this->checks->consentIsSigned($r);
            $mentions = trim((string) $r['mentiuni_intra_postoperatorii']) !== '' ? $r['mentiuni_intra_postoperatorii']
                : trim(trim((string) $r['complicatii']).' | '.trim((string) ($r['mentiuni_medicale'] ?? '')), ' |');
            $out[] = [
                'NrCrt' => $nr++,
                'CodCaz' => (string) $r['cod_caz'],
                'NumarPacient' => CityChecks::reportNumber($r),
                'LunaRaportare' => CaseFields::sanitizeDate($lunaSource) ? substr($lunaSource, 5, 2).'.'.substr($lunaSource, 0, 4) : '',
                'DataInterventiei' => (string) ($r['data_interventie'] ?: $effective),
                'NumeProprietar' => (string) $r['nume_proprietar'],
                'AdresaCI' => (string) $r['adresa_ci'],
                'Telefon' => (string) $r['telefon'],
                'Specie' => (string) $r['specie'],
                'Sex' => (string) $r['sex'],
                'NumePacient' => (string) $r['nume_pacient'],
                'Rasa' => (string) $r['rasa'],
                'CuloareSemnalmente' => (string) $r['culoare_semnalmente'],
                'Varsta' => (string) $r['varsta_estimata'],
                'GreutateKg' => (string) (($r['greutate_reala'] !== '' && $r['greutate_reala'] !== null) ? $r['greutate_reala'] : $r['greutate_estimativa']),
                'AnimalCuStapan' => (string) $r['animal_cu_stapan'],
                'Microcip' => (string) $r['microcip'],
                'Vaccinat' => (string) ($r['vaccinat'] ?: ($r['carnet_sanatate'] ?? '')),
                'DataVaccinare' => (string) $r['data_vaccinare'],
                'Procedura' => (string) ($r['procedura_efectuata'] ?: $r['procedura_programata']),
                'TipAnestezie' => (string) $r['tip_anestezie'],
                'SubstanteFolosite' => self::substances($r),
                'ComplicatiiMentiuni' => (string) $mentions,
                'MedicOperator' => (string) $r['medic_operator'],
                'TehnicianAsistent' => (string) $r['tehnician_asistent'],
                'ConsimtamantSemnat' => $consent ? 'Da' : 'Nu',
                'ActIdentitate' => ! empty($r['documente_scanate']) ? 'Da' : 'Nu',
                'DosarComplet' => (! empty($r['poza_pacient']) && $consent && self::isFinal($r)) ? 'Da' : 'Nu',
                'ValidatPentruPrimarie' => (string) $r['validat_pentru_primarie'],
                'ValidatDe' => (string) $r['validat_de'],
                'ObservatiiValidare' => (string) $r['observatii_validare'],
            ];
        }

        return $out;
    }

    /** Coloanele oficiale ale desfasuratorului pentru Primarie (report_xlsx_columns). */
    public const OFFICIAL_COLUMNS = [
        ['NrCrt', 'Nr. crt.', 9], ['CodCaz', 'Cod caz', 18], ['NumarPacient', 'Nr. pacient', 15], ['LunaRaportare', 'Luna raportare', 15],
        ['DataInterventiei', 'Data intervenției', 17], ['NumeProprietar', 'Nume proprietar', 25], ['AdresaCI', 'Adresă proprietar', 32],
        ['Telefon', 'Telefon', 17], ['Specie', 'Specie', 12], ['Sex', 'Sex', 11], ['NumePacient', 'Nume pacient', 20], ['Rasa', 'Rasă', 20],
        ['CuloareSemnalmente', 'Culoare / semnalmente', 25], ['Varsta', 'Vârstă', 13], ['GreutateKg', 'Greutate (kg)', 14],
        ['AnimalCuStapan', 'Animal cu stăpân', 17], ['Microcip', 'Microcip', 20], ['Vaccinat', 'Vaccinat', 13], ['DataVaccinare', 'Data vaccinării', 17],
        ['Procedura', 'Procedură', 25], ['TipAnestezie', 'Tip anestezie', 20], ['SubstanteFolosite', 'Substanțe folosite', 34],
        ['ComplicatiiMentiuni', 'Complicații / mențiuni', 38], ['MedicOperator', 'Medic operator', 23], ['TehnicianAsistent', 'Tehnician / asistent', 23],
    ];

    /** report_summary() */
    public static function reportSummary(array $mapped): array
    {
        $s = ['total' => count($mapped), 'validated' => 0, 'unvalidated' => 0, 'dogs' => 0, 'cats' => 0, 'female' => 0, 'male' => 0, 'procedures' => []];
        foreach ($mapped as $row) {
            ($row['ValidatPentruPrimarie'] ?? '') === 'Da' ? $s['validated']++ : $s['unvalidated']++;
            $species = mb_strtolower(Str::ascii(trim((string) ($row['Specie'] ?? ''))));
            if (in_array($species, ['caine', 'dog'], true)) {
                $s['dogs']++;
            }
            if (in_array($species, ['pisica', 'cat'], true)) {
                $s['cats']++;
            }
            $sex = mb_strtolower(Str::ascii(trim((string) ($row['Sex'] ?? ''))));
            if (str_contains($sex, 'fem')) {
                $s['female']++;
            }
            if (str_contains($sex, 'mas') || str_contains($sex, 'male')) {
                $s['male']++;
            }
            $procedure = trim((string) ($row['Procedura'] ?? '')) ?: 'Nespecificată';
            $s['procedures'][$procedure] = ($s['procedures'][$procedure] ?? 0) + 1;
        }
        arsort($s['procedures']);

        return $s;
    }

    /** general_animal_category(): CAINE / CATEA / MOTAN / PISICA, sau '' daca specia si sexul nu sunt clare. */
    public static function animalCategory(array $row): string
    {
        $species = mb_strtolower(Str::ascii(trim((string) ($row['Specie'] ?? $row['specie'] ?? ''))));
        $sex = mb_strtolower(Str::ascii(trim((string) ($row['Sex'] ?? $row['sex'] ?? ''))));
        $dog = in_array($species, ['caine', 'dog'], true) || str_contains($species, 'cain');
        $cat = in_array($species, ['pisica', 'cat'], true) || str_contains($species, 'pisic');
        $female = str_contains($sex, 'fem') || in_array($sex, ['f', 'female'], true);
        $male = str_contains($sex, 'mas') || str_contains($sex, 'male') || $sex === 'm';

        return match (true) {
            $dog && $male => 'CAINE', $dog && $female => 'CATEA', $cat && $male => 'MOTAN', $cat && $female => 'PISICA', default => '',
        };
    }

    public static function animalSummary(array $rows): array
    {
        $s = ['CAINE' => 0, 'CATEA' => 0, 'MOTAN' => 0, 'PISICA' => 0, 'total' => 0, 'unclassified' => []];
        foreach ($rows as $row) {
            $category = self::animalCategory($row);
            if ($category === '') {
                $s['unclassified'][] = ['id' => (int) ($row['id'] ?? 0), 'code' => $row['cod_caz'] ?? '', 'patient' => $row['nume_pacient'] ?? '',
                    'species' => $row['specie'] ?? '', 'sex' => $row['sex'] ?? ''];

                continue;
            }
            $s[$category]++;
            $s['total']++;
        }

        return $s;
    }

    // ---------------------------------------------------------------- audit si verificarea dosarelor pentru ZIP

    /** Documentele curente ale dosarelor (fotografie, CI), incarcate o singura data. */
    public function document(int $caseId, string $kind): ?SterilizationDocument
    {
        if ($this->documents === null) {
            $this->documents = [];
            foreach (SterilizationDocument::query()->whereNull('superseded_at')->orderBy('id')->get() as $doc) {
                $this->documents[$doc->sterilization_case_id][$doc->kind] = $doc;
            }
        }

        return $this->documents[$caseId][$kind] ?? null;
    }

    /** Amprenta fotografiei: fisierul curent, altfel referinta salvata in dosar. */
    private function photoKey(array $row): string
    {
        $ref = trim((string) ($row['poza_pacient'] ?? ''));
        if ($ref === '') {
            return '';
        }

        return $this->document((int) $row['id'], SterilizationDocument::PHOTO)?->sha256 ?: $ref;
    }

    /** city_integrity_audit() */
    public function integrityAudit(array $rows): array
    {
        $numbers = [];
        $photos = [];
        foreach ($rows as $row) {
            if (($n = CityChecks::reportNumber($row)) !== '') {
                $numbers[$n][] = $row;
            }
            if (($p = $this->photoKey($row)) !== '') {
                $photos[$p][] = $row;
            }
        }
        $numbers = array_filter($numbers, fn ($items) => count($items) > 1);
        $photos = array_filter($photos, fn ($items) => count($items) > 1);

        $items = [];
        $critical = 0;
        $document = 0;
        foreach ($rows as $row) {
            $issues = $this->integrityIssues($row, $numbers, $photos);
            if (! $issues) {
                continue;
            }
            foreach ($issues as $issue) {
                $issue['severity'] === 'critical' ? $critical++ : $document++;
            }
            $items[] = ['row' => $row, 'issues' => $issues];
        }

        return ['items' => $items, 'critical' => $critical, 'document' => $document, 'affected_cases' => count($items), 'clean' => $critical === 0];
    }

    /**
     * city_integrity_issues_for_row(). ABATERE: pluginul verifica lipsa si dublura pe numarul operational, dar
     * grupa dublurile dupa numarul din raport; aici ambele folosesc numarul din raport (cel care ajunge la Primarie).
     * Fotografia reutilizata se recunoaste dupa continutul fisierului, nu dupa adresa lui.
     */
    private function integrityIssues(array $row, array $duplicateNumbers, array $duplicatePhotos): array
    {
        $issues = [];
        $issue = fn (string $code, string $severity, string $label, string $detail, string $tab, string $focus, bool $reissue = false) => compact('code', 'severity', 'label', 'detail', 'tab', 'focus', 'reissue');
        $number = CityChecks::reportNumber($row);
        $names = fn (array $items) => implode(', ', array_filter(array_unique(array_map(fn ($i) => trim((string) ($i['nume_pacient'] ?? '')), $items))));

        if ($number === '') {
            $issues[] = $issue('patient_number_missing', 'critical', 'Număr pacient lipsă', 'Completează numărul care identifică pacientul în raport și fotografie.', 'interventie', 'numar_pacient');
        } elseif (! empty($duplicateNumbers[$number])) {
            $issues[] = $issue('patient_number_duplicate', 'critical', 'Număr pacient duplicat: '.$number, 'Același număr este folosit la: '.$names($duplicateNumbers[$number]).'.', 'interventie', 'numar_pacient');
        }
        if (! CaseFields::cityAddressIsValid($row['adresa_ci'] ?? '')) {
            $issues[] = $issue('address_invalid', 'critical', 'Adresă CI invalidă', 'Câmpul adresă este gol, prea scurt sau pare să conțină doar CNP/numere.', 'corectare', 'adresa_ci');
        }
        if (empty($row['documente_scanate'])) {
            $issues[] = $issue('id_missing', 'critical', 'Lipsește fotografia/scanul CI', 'Pentru dosarul Primăriei actul de identitate al proprietarului trebuie atașat.', 'corectare', 'documente_scanate');
        } elseif (! $this->checks->idCardConsistent($row)) {
            $issues[] = $issue('id_owner_mismatch', 'critical', 'CI folosit la proprietari diferiți', 'Același fișier de identitate este legat și de un dosar cu alt nume de proprietar. Verifică și înlocuiește CI-ul.', 'corectare', 'documente_scanate');
        }
        if (empty($row['poza_pacient'])) {
            $issues[] = $issue('photo_missing', 'document', 'Lipsește fotografia pacientului', 'Dosarul nu poate intra în arhiva oficială fără fotografie.', 'interventie', 'poza_pacient');
        } elseif (($key = $this->photoKey($row)) !== '' && ! empty($duplicatePhotos[$key])) {
            $list = array_filter(array_unique(array_map(fn ($i) => trim((string) ($i['nume_pacient'] ?? '')), $duplicatePhotos[$key])));
            if (count($list) > 1) {
                $issues[] = $issue('photo_duplicate', 'critical', 'Aceeași fotografie la pacienți diferiți', 'Fișierul foto este folosit la: '.implode(', ', $list).'.', 'interventie', 'poza_pacient');
            }
        }
        if (! $this->checks->consentIsSigned($row)) {
            $issues[] = $issue('consent_missing', 'critical', 'Consimțământ nesemnat', 'Consimțământul digital trebuie semnat înainte de export.', 'receptie', 'consent');
        } else {
            $identity = $this->checks->identity($row);
            if (! $identity['owner_ok']) {
                $issues[] = $issue('consent_owner_mismatch', 'critical', 'Proprietar diferit în consimțământ', 'Dosar: '.(trim((string) $row['nume_proprietar']) ?: '-').' · Consimțământ: '.($identity['consent_owner'] ?: '-').'.', 'receptie', 'consent', true);
            }
            if (! $identity['patient_ok']) {
                $key = CaseFields::identityKey($row['nume_pacient'] ?? '');
                $addedLater = $key !== '' && collect($this->checks->consentFor($row)?->visit_patients ?? [])
                    ->contains(fn ($p) => is_array($p) && CaseFields::identityKey($p['pet_name'] ?? '') === $key);
                $issues[] = $addedLater
                    ? $issue('consent_patient_added_after_signing', 'critical', 'Pacient adăugat în vizită după semnare', 'Proprietarul a semnat consimțământul pentru: '.($identity['consent_patient'] ?: '-').'. '.trim((string) $row['nume_pacient']).' a fost adăugat ulterior și nu apare în documentul semnat.', 'receptie', 'consent', true)
                    : $issue('consent_patient_mismatch', 'critical', 'Pacient diferit în consimțământ', 'Dosar: '.(trim((string) $row['nume_pacient']) ?: '-').' · Consimțământ: '.($identity['consent_patient'] ?: '-').'.', 'receptie', 'consent', true);
            }
        }

        return $issues;
    }

    /** Numele folderului unui dosar in ZIP, doar cu caractere sigure pe orice sistem de fisiere. */
    public static function safeName(string $value, string $fallback = ''): string
    {
        $value = preg_replace('/[^A-Za-z0-9._-]+/', '-', Str::ascii(trim($value)));
        $value = trim(preg_replace('/-{2,}/', '-', (string) $value), '.-_');

        return $value !== '' ? mb_substr($value, 0, 80) : $fallback;
    }

    /** package_scan_rows(): toate interventiile finalizate intra in ZIP; „complet” este doar un indicator de calitate. */
    public function scan(array $rows): array
    {
        $issuesByCase = [];
        foreach ($this->integrityAudit($rows)['items'] as $item) {
            $issuesByCase[(int) $item['row']['id']] = $item['issues'];
        }
        $out = [];
        $index = 1;
        foreach ($rows as $row) {
            $effective = $row['data_raportare_effective'] ?? null;
            $row = $this->checks->autoValidation($row);
            if ($effective !== null) {
                $row['data_raportare_effective'] = $effective;
            }
            $final = self::isFinal($row);
            $integrity = $issuesByCase[(int) $row['id']] ?? [];
            $critical = array_filter($integrity, fn ($i) => $i['severity'] === 'critical');
            $missing = [];
            foreach ([
                [! $final, 'intervenție nefinalizată'],
                [$row['date_proprietar_complete'] !== 'Da', 'date proprietar incomplete'],
                [$row['adresa_ci_valida'] !== 'Da', 'adresă CI invalidă'],
                [$row['ci_document_incarcat'] !== 'Da', 'poză / scan CI proprietar'],
                [$row['ci_proprietar_consistent'] !== 'Da', 'CI asociat și altui proprietar'],
                [$row['date_pacient_complete'] !== 'Da', 'date pacient incomplete'],
                [$row['numar_pacient_unic'] !== 'Da', 'număr pacient lipsă sau duplicat'],
                [$row['consimtamant_complet'] !== 'Da', 'consimțământ digital semnat'],
                [$row['consent_identitate_corecta'] !== 'Da', 'identitate diferită între dosar și consimțământ'],
                [$row['anestezie_completata'] !== 'Da', 'tip anestezie lipsă'],
                [$row['substante_completate'] !== 'Da', 'substanță principală lipsă'],
                [$row['medic_operator_completat'] !== 'Da', 'medic operator lipsă'],
                [$row['poza_incarcata'] !== 'Da', 'poză pacient'],
                [$row['poza_pacient_unica'] !== 'Da', 'fotografie reutilizată la alt pacient'],
                [$row['complicatii_mentiuni_completate'] !== 'Da', 'mențiuni intra/postoperatorii lipsă'],
            ] as [$condition, $reason]) {
                if ($condition) {
                    $missing[] = $reason;
                }
            }
            $folder = sprintf('%03d_%s_%s', $index, self::safeName((string) $row['cod_caz'], 'caz-'.$index), self::safeName((string) $row['nume_pacient'], 'pacient'));
            $out[] = [
                'row' => $row, 'index' => $index, 'folder' => $folder, 'finalized_ok' => $final,
                'photo_ok' => ! empty($row['poza_pacient']), 'consent_ok' => $this->checks->consentIsSigned($row), 'id_ok' => ! empty($row['documente_scanate']),
                'integrity_ok' => ! $critical, 'integrity_issues' => $integrity, 'gdpr_warning' => $row['gdpr_complet'] !== 'Da',
                'complete' => $final && CityChecks::isReady($row) && ! $critical, 'zip_included' => $final, 'missing' => array_values(array_unique($missing)),
            ];
            $index++;
        }

        return $out;
    }

    public static function scanSummary(array $scan): array
    {
        $s = ['total_finalized' => count($scan), 'zip_included' => 0, 'ready_export' => 0, 'incomplete_export' => 0, 'missing_photo' => 0, 'missing_consent' => 0,
            'missing_both' => 0, 'with_id' => 0, 'missing_id' => 0, 'integrity_errors' => 0, 'gdpr_warnings' => 0, 'reason_counts' => []];
        foreach ($scan as $item) {
            $s['zip_included'] += $item['zip_included'] ? 1 : 0;
            if ($item['complete']) {
                $s['ready_export']++;
            } else {
                $s['incomplete_export']++;
                foreach ($item['missing'] as $reason) {
                    $s['reason_counts'][$reason] = ($s['reason_counts'][$reason] ?? 0) + 1;
                }
            }
            $s['missing_photo'] += $item['photo_ok'] ? 0 : 1;
            $s['missing_consent'] += $item['consent_ok'] ? 0 : 1;
            $s['missing_both'] += (! $item['photo_ok'] && ! $item['consent_ok']) ? 1 : 0;
            $item['id_ok'] ? $s['with_id']++ : $s['missing_id']++;
            $s['integrity_errors'] += $item['integrity_ok'] ? 0 : 1;
            $s['gdpr_warnings'] += $item['gdpr_warning'] ? 1 : 0;
        }
        arsort($s['reason_counts']);

        return $s;
    }

    // ---------------------------------------------------------------- control numere

    /** report_number_sequence_audit(): dubluri si goluri; golurile nu se completeaza cu pacienti. */
    public static function numberSequenceAudit(array $rows): array
    {
        $byNumber = [];
        foreach ($rows as $row) {
            $number = CityChecks::reportNumber($row);
            if (preg_match('/^\d+$/', $number) && (int) $number > 0) {
                $byNumber[(int) $number][] = $row;
            }
        }
        ksort($byNumber);
        $numbers = array_keys($byNumber);
        $gaps = [];
        if (count($numbers) >= 2) {
            for ($n = min($numbers); $n <= max($numbers); $n++) {
                if (! isset($byNumber[$n])) {
                    $gaps[] = $n;
                }
            }
        }

        return ['numbers' => $numbers, 'duplicates' => array_filter($byNumber, fn ($items) => count($items) > 1), 'gaps' => $gaps, 'count' => count($numbers)];
    }

    public static function compactRanges(array $numbers): string
    {
        $numbers = array_values(array_unique(array_map('intval', $numbers)));
        sort($numbers, SORT_NUMERIC);
        if (! $numbers) {
            return '';
        }
        $ranges = [];
        $start = $prev = $numbers[0];
        foreach (array_slice($numbers, 1) as $n) {
            if ($n === $prev + 1) {
                $prev = $n;

                continue;
            }
            $ranges[] = $start === $prev ? (string) $start : $start.'–'.$prev;
            $start = $prev = $n;
        }
        $ranges[] = $start === $prev ? (string) $start : $start.'–'.$prev;

        return implode(', ', $ranges);
    }

    /** verification_date_for_row() */
    public static function verificationDate(array $row): array
    {
        foreach ([['data_raportare_primarie', 'Raportare'], ['data_interventie', 'Intervenție']] as [$field, $source]) {
            if ($date = CaseFields::sanitizeDate(substr((string) ($row[$field] ?? ''), 0, 10))) {
                return ['date' => $date, 'source' => $source];
            }
        }
        foreach ([['photo_uploaded_at', 'Încărcare fotografie'], ['timestamp_interventie', 'Finalizare intervenție']] as [$field, $source]) {
            if ($date = CaseFields::sanitizeDate(substr((string) ($row[$field] ?? ''), 0, 10))) {
                return ['date' => $date, 'source' => $source];
            }
        }
        foreach ([['data_programare', 'Programare'], ['data_fisa', 'Fișă'], ['data_prezentare', 'Prezentare'], ['timestamp_creat', 'Creare dosar']] as [$field, $source]) {
            if ($date = CaseFields::sanitizeDate(substr((string) ($row[$field] ?? ''), 0, 10))) {
                return ['date' => $date, 'source' => $source];
            }
        }

        return ['date' => '', 'source' => 'Fără dată'];
    }

    /**
     * verification_rows(): TOATE dosarele perioadei (nu doar cele finalizate), ca sa apara pacientii cu numar si
     * fotografie care nu au intrat in raport.
     */
    public function verificationRows(?string $month): array
    {
        $reportIds = [];
        try {
            foreach ($this->finalizedRows($month) as $r) {
                $reportIds[(int) $r['id']] = true;
            }
        } catch (RuntimeException) {
            // Fara lista de excludere pentru octombrie, toate interventiile lunii raman „in raport”.
            foreach ($this->finalizedRows($month, false) as $r) {
                $reportIds[(int) $r['id']] = true;
            }
        }
        $result = [];
        foreach ($this->activeRows() as $row) {
            $info = self::verificationDate($row);
            if (self::validMonth($month) && substr($info['date'], 0, 7) !== $month) {
                continue;
            }
            if (trim((string) $row['nume_pacient']) === '' && trim((string) $row['numar_pacient']) === '' && trim((string) $row['numar_pacient_raport']) === '' && trim((string) $row['poza_pacient']) === '') {
                continue;
            }
            $report = CityChecks::reportNumber($row);
            $working = trim((string) $row['numar_pacient']);
            $photo = trim((string) $row['numar_pacient_foto_confirmat']);
            $row += ['_verify_date' => $info['date'], '_verify_date_source' => $info['source'], '_verify_report_number' => $report, '_verify_working_number' => $working,
                '_verify_photo_number' => $photo, '_verify_has_photo' => trim((string) $row['poza_pacient']) !== '', '_verify_in_report' => isset($reportIds[(int) $row['id']]),
                '_verify_photo_checked' => $photo !== '', '_verify_photo_mismatch' => $photo !== '' && $report !== '' && $photo !== $report,
                '_verify_working_report_mismatch' => $working !== '' && $report !== '' && $working !== $report, '_verify_duplicate' => false];
            $result[] = $row;
        }
        $map = [];
        foreach ($result as $i => $row) {
            if ($row['_verify_report_number'] !== '') {
                $map[$row['_verify_report_number']][] = $i;
            }
        }
        foreach ($map as $indexes) {
            if (count($indexes) > 1) {
                foreach ($indexes as $i) {
                    $result[$i]['_verify_duplicate'] = true;
                }
            }
        }
        usort($result, function ($a, $b) {
            if ($a['_verify_date'] !== $b['_verify_date']) {
                return strcmp($a['_verify_date'], $b['_verify_date']);
            }
            $ia = preg_match('/^\d+$/', $a['_verify_report_number']) ? (int) $a['_verify_report_number'] : PHP_INT_MAX;
            $ib = preg_match('/^\d+$/', $b['_verify_report_number']) ? (int) $b['_verify_report_number'] : PHP_INT_MAX;

            return $ia !== $ib ? $ia <=> $ib : strcmp((string) $a['cod_caz'], (string) $b['cod_caz']);
        });

        return $result;
    }

    public const VERIFY_STATUSES = [
        'all' => 'Toate dosarele', 'photo_not_report' => 'Au poză, dar NU sunt în raport', 'not_report' => 'Nu sunt în raport', 'in_report' => 'Sunt în raport',
        'unverified_photo' => 'Au poză, numărul nu a fost verificat vizual', 'photo_mismatch' => 'Număr confirmat din poză ≠ număr raport',
        'working_report_mismatch' => 'Număr intern ≠ număr raport', 'missing_number' => 'Fără număr oficial de raport', 'duplicate' => 'Număr oficial duplicat',
        'missing_photo' => 'Fără fotografie',
    ];

    public static function hasFilters(array $f): bool
    {
        return ($f['date'] ?? '') !== '' || ($f['number'] ?? '') !== '' || ($f['from'] ?? '') !== '' || ($f['to'] ?? '') !== '' || ! in_array($f['status'] ?? 'all', ['', 'all'], true);
    }

    /** verification_filtered_rows() */
    public static function filterVerification(array $rows, array $f): array
    {
        $date = trim((string) ($f['date'] ?? ''));
        $number = trim((string) ($f['number'] ?? ''));
        $from = trim((string) ($f['from'] ?? ''));
        $to = trim((string) ($f['to'] ?? ''));
        $status = trim((string) ($f['status'] ?? 'all'));

        return array_values(array_filter($rows, function ($r) use ($date, $number, $from, $to, $status) {
            if ($date !== '' && $r['_verify_date'] !== $date) {
                return false;
            }
            if ($number !== '' && ! in_array($number, [$r['_verify_report_number'], $r['_verify_working_number'], $r['_verify_photo_number']], true)) {
                return false;
            }
            $numeric = preg_match('/^\d+$/', $r['_verify_report_number']) ? (int) $r['_verify_report_number'] : null;
            if ($from !== '' && ctype_digit($from) && ($numeric === null || $numeric < (int) $from)) {
                return false;
            }
            if ($to !== '' && ctype_digit($to) && ($numeric === null || $numeric > (int) $to)) {
                return false;
            }

            return match ($status) {
                'photo_not_report' => $r['_verify_has_photo'] && ! $r['_verify_in_report'],
                'not_report' => ! $r['_verify_in_report'],
                'in_report' => $r['_verify_in_report'],
                'unverified_photo' => $r['_verify_has_photo'] && ! $r['_verify_photo_checked'],
                'photo_mismatch' => $r['_verify_photo_mismatch'],
                'working_report_mismatch' => $r['_verify_working_report_mismatch'],
                'missing_number' => $r['_verify_report_number'] === '',
                'duplicate' => $r['_verify_duplicate'],
                'missing_photo' => ! $r['_verify_has_photo'],
                default => true,
            };
        }));
    }

    public static function verificationSummary(array $rows): array
    {
        $s = array_fill_keys(['in_report', 'not_report', 'with_photo', 'photo_not_report', 'unverified_photo', 'photo_mismatch', 'working_report_mismatch', 'missing_number', 'duplicates'], 0);
        $s['total'] = count($rows);
        foreach ($rows as $r) {
            $r['_verify_in_report'] ? $s['in_report']++ : $s['not_report']++;
            $s['with_photo'] += $r['_verify_has_photo'] ? 1 : 0;
            $s['photo_not_report'] += ($r['_verify_has_photo'] && ! $r['_verify_in_report']) ? 1 : 0;
            $s['unverified_photo'] += ($r['_verify_has_photo'] && ! $r['_verify_photo_checked']) ? 1 : 0;
            $s['photo_mismatch'] += $r['_verify_photo_mismatch'] ? 1 : 0;
            $s['working_report_mismatch'] += $r['_verify_working_report_mismatch'] ? 1 : 0;
            $s['missing_number'] += $r['_verify_report_number'] === '' ? 1 : 0;
            $s['duplicates'] += $r['_verify_duplicate'] ? 1 : 0;
        }

        return $s;
    }

    /** handle_mark_photo_number_checked(): numarul din raport a fost comparat vizual cu cel din fotografie. */
    public function markPhotoChecked(SterilizationCase $case, User $actor): void
    {
        $row = $case->row();
        $number = CityChecks::reportNumber($row);
        if ($number === '') {
            throw new RuntimeException('Dosarul nu are număr oficial de pacient. Corectează numărul înainte de confirmarea fotografiei.');
        }
        if (empty($row['poza_pacient'])) {
            throw new RuntimeException('Dosarul nu are fotografie de pacient.');
        }
        $row['numar_pacient_foto_confirmat'] = $number;
        $row['numar_pacient_foto_verificat_de'] = $actor->name;
        $row['numar_pacient_foto_verificat_at'] = CaseFields::now();
        $this->save($case, $row, $actor, 'photo_number_checked', 'Număr confirmat din fotografie: '.$number);
    }

    /** handle_bulk_mark_photo_numbers_checked() */
    public function bulkMarkPhotoChecked(?string $month, array $filters, User $actor): array
    {
        $rows = $this->verificationRows($month);
        if (self::hasFilters($filters)) {
            $rows = self::filterVerification($rows, $filters);
        }
        $result = ['updated' => 0, 'already_ok' => 0, 'skipped_no_photo' => 0, 'skipped_no_number' => 0, 'filtered' => self::hasFilters($filters)];
        foreach ($rows as $r) {
            if (! $r['_verify_has_photo']) {
                $result['skipped_no_photo']++;

                continue;
            }
            if ($r['_verify_report_number'] === '') {
                $result['skipped_no_number']++;

                continue;
            }
            if (trim((string) $r['numar_pacient_foto_confirmat']) === $r['_verify_report_number'] && trim((string) $r['numar_pacient_foto_verificat_at']) !== '') {
                $result['already_ok']++;

                continue;
            }
            $this->markPhotoChecked(SterilizationCase::query()->active()->findOrFail($r['id']), $actor);
            $result['updated']++;
        }
        Audit::log('campaign.photo_numbers_checked', null, 'Confirmare în masă a numerelor din fotografii: '.$result['updated'].' dosare');

        return $result;
    }

    // ---------------------------------------------------------------- registru si reconciliere

    public function reconciliationSettings(): array
    {
        $saved = Settings::get('campaign.registry_reconciliation');

        return (is_array($saved) ? $saved : []) + ['void_numbers' => self::VOID_DEFAULT, 'submitted_2026_07' => '', 'submitted_2026_08' => '', 'submitted_2026_09' => ''];
    }

    /** parse_number_expression(): „1-40, 42; 44–90” → lista de numere. */
    public static function parseNumbers(?string $value): array
    {
        $value = str_replace(['–', '—', ';', "\n", "\r", "\t"], ['-', '-', ',', ',', ',', ','], trim((string) $value));
        $numbers = [];
        foreach (preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            if (preg_match('/^(\d+)-(\d+)$/', $part, $m)) {
                [$a, $b] = [(int) $m[1], (int) $m[2]];
                if ($a <= 0 || $b <= 0) {
                    continue;
                }
                [$a, $b] = [min($a, $b), max($a, $b)];
                if ($b - $a > 5000) {
                    continue;
                }
                for ($n = $a; $n <= $b; $n++) {
                    $numbers[$n] = $n;
                }
            } elseif (preg_match('/^\d+$/', $part) && (int) $part > 0) {
                $numbers[(int) $part] = (int) $part;
            }
        }
        ksort($numbers);

        return array_values($numbers);
    }

    public function saveReconciliation(array $input, User $actor): void
    {
        $settings = $this->reconciliationSettings();
        $settings['void_numbers'] = implode(',', self::parseNumbers($input['void_numbers'] ?? $settings['void_numbers']));
        foreach (array_keys(self::RECONCILIATION_MONTHS) as $month) {
            $key = 'submitted_'.str_replace('-', '_', $month);
            $settings[$key] = implode(',', self::parseNumbers($input[$key] ?? ''));
        }
        Settings::set('campaign.registry_reconciliation', $settings);
        Audit::log('campaign.reconciliation_saved', null, 'Setările registrului de numere au fost salvate');
    }

    /** verified_photo_registry_rows(): doar dosarele cu fotografie si numar confirmat vizual. */
    public function registryRows(): array
    {
        $result = [];
        foreach ($this->activeRows() as $row) {
            $photo = trim((string) $row['numar_pacient_foto_confirmat']);
            if ($photo === '' || trim((string) $row['poza_pacient']) === '' || ! preg_match('/^\d+$/', $photo)) {
                continue;
            }
            $info = self::verificationDate($row);
            $internal = trim((string) $row['numar_pacient']);
            $report = CityChecks::reportNumber($row);
            $row += ['_registry_photo_number' => $photo, '_registry_internal_number' => $internal, '_registry_report_number' => $report,
                '_registry_date' => $info['date'], '_registry_date_source' => $info['source'], '_registry_aligned' => $internal === $photo && $report === $photo];
            $result[] = $row;
        }
        usort($result, fn ($a, $b) => [(int) $a['_registry_photo_number'], $a['_registry_date'], (int) $a['id']] <=> [(int) $b['_registry_photo_number'], $b['_registry_date'], (int) $b['id']]);

        return $result;
    }

    /** number_registry_matrix() */
    public function registryMatrix(): array
    {
        $rows = $this->registryRows();
        $void = self::parseNumbers($this->reconciliationSettings()['void_numbers']);
        $voidSet = array_flip($void);
        $byNumber = [];
        foreach ($rows as $row) {
            $byNumber[(int) $row['_registry_photo_number']][] = $row;
        }
        $max = $byNumber ? max(array_keys($byNumber)) : 0;
        $matrix = [];
        $missing = [];
        $duplicates = [];
        $voidConflicts = [];
        $aligned = 0;
        $mismatch = 0;
        for ($n = 1; $n <= $max; $n++) {
            $items = $byNumber[$n] ?? [];
            $isVoid = isset($voidSet[$n]);
            if (! $items) {
                if (! $isVoid) {
                    $missing[] = $n;
                }
                $matrix[] = ['number' => $n, 'missing' => ! $isVoid, 'void' => $isVoid, 'void_conflict' => false, 'duplicate' => false, 'rows' => []];

                continue;
            }
            if (count($items) > 1) {
                $duplicates[$n] = $items;
            }
            if ($isVoid) {
                $voidConflicts[$n] = $items;
            }
            foreach ($items as $item) {
                $item['_registry_aligned'] ? $aligned++ : $mismatch++;
            }
            $matrix[] = ['number' => $n, 'missing' => false, 'void' => $isVoid, 'void_conflict' => $isVoid, 'duplicate' => count($items) > 1, 'rows' => $items];
        }

        return compact('rows', 'matrix', 'max', 'aligned', 'mismatch', 'missing', 'duplicates', 'voidConflicts') + ['verified' => count($rows), 'void_numbers' => $void];
    }

    /** handle_align_numbers_from_photo(): numarul intern si cel din raport devin numarul confirmat din fotografie. */
    public function alignFromPhoto(User $actor): array
    {
        $registry = $this->registryMatrix();
        $result = ['updated' => 0, 'already_aligned' => 0, 'skipped_duplicate' => 0, 'skipped_void' => 0, 'errors' => 0];
        foreach ($registry['rows'] as $r) {
            $photo = $r['_registry_photo_number'];
            if (in_array((int) $photo, $registry['void_numbers'], true)) {
                $result['skipped_void']++;

                continue;
            }
            if (! empty($registry['duplicates'][(int) $photo])) {
                $result['skipped_duplicate']++;

                continue;
            }
            $internal = trim((string) $r['numar_pacient']);
            if ($internal === $photo && CityChecks::reportNumber($r) === $photo) {
                $result['already_aligned']++;

                continue;
            }
            $case = SterilizationCase::query()->active()->find($r['id']);
            if (! $case) {
                $result['errors']++;

                continue;
            }
            $row = $case->row();
            if ($internal !== '' && $internal !== $photo) {
                $row['numar_pacient_vechi'] = $internal;
            }
            $row['numar_pacient'] = $photo;
            $row['numar_pacient_raport'] = $photo;
            $row['numar_pacient_raport_sursa'] = 'aliniat_dupa_foto_verificata';
            $row['numar_pacient_raport_confirmat_at'] = CaseFields::now();
            $this->save($case, $row, $actor, 'numbers_aligned', 'Număr intern și număr raport aliniate după fotografia verificată: '.$photo);
            $result['updated']++;
        }
        Audit::log('campaign.numbers_aligned', null, 'Aliniere după fotografiile verificate: '.$result['updated'].' dosare actualizate');

        return $result;
    }

    /** reconciliation_three_months(): cazurile neraportate raman cu data lor reala si apar „de regularizat”. */
    public function reconciliation(): array
    {
        $rows = $this->registryRows();
        $settings = $this->reconciliationSettings();
        $voidSet = array_flip(self::parseNumbers($settings['void_numbers']));
        $result = [];
        foreach (self::RECONCILIATION_MONTHS as $month => $label) {
            $submitted = self::parseNumbers($settings['submitted_'.str_replace('-', '_', $month)] ?? '');
            $submittedSet = array_flip($submitted);
            $monthRows = [];
            foreach ($rows as $row) {
                $n = (int) $row['_registry_photo_number'];
                if (substr($row['_registry_date'], 0, 7) !== $month || $n <= 0) {
                    continue;
                }
                $row['_recon_void'] = isset($voidSet[$n]);
                $row['_recon_submitted'] = isset($submittedSet[$n]);
                $monthRows[] = $row;
            }
            $reported = 0;
            $toRegularize = [];
            $voidConflicts = [];
            foreach ($monthRows as $row) {
                if ($row['_recon_void']) {
                    $voidConflicts[] = $row;
                } elseif ($row['_recon_submitted']) {
                    $reported++;
                } elseif ($submitted) {
                    $toRegularize[] = $row;
                }
            }
            $result[$month] = ['label' => $label, 'rows' => $monthRows, 'verified' => count($monthRows), 'submitted_numbers' => $submitted,
                'submitted_count' => count($submitted), 'matched_reported' => $reported, 'to_regularize' => $toRegularize, 'void_conflicts' => $voidConflicts,
                'has_submitted_data' => (bool) $submitted];
        }

        return $result;
    }

    // ---------------------------------------------------------------- validare si reparare

    /** bulk_validate_complete_city_cases(): doar dosarele complete; cele incomplete nu se ating. */
    public function bulkValidate(string $validator, User $actor): array
    {
        $result = ['scanned' => 0, 'validated' => 0, 'incomplete' => 0, 'validator' => $validator];
        $cases = SterilizationCase::query()->active()->where(fn ($q) => $q->whereNull('validat_pentru_primarie')->orWhere('validat_pentru_primarie', '<>', 'Da'))->orderBy('id')->get();
        foreach ($cases as $case) {
            $result['scanned']++;
            $row = $this->checks->autoValidation($case->row());
            if (! CityChecks::isReady($row)) {
                $result['incomplete']++;

                continue;
            }
            $row = array_diff_key($row, array_flip(CityChecks::computedKeys()));
            $row['validat_pentru_primarie'] = 'Da';
            $row['validat_de'] = $validator;
            $row['data_validare'] = CaseFields::today();
            $row['status_caz'] = 'Validat primarie';
            $note = trim((string) $row['observatii_validare']);
            $auto = 'Validat automat după verificarea completitudinii dosarului de '.$validator.'.';
            $row['observatii_validare'] = $note === '' ? $auto : (str_contains($note, $auto) ? $note : $note."\n".$auto);
            $this->cases->persist($case, $row, $actor, 'bulk_validated', 'validare', 'Validare în masă a dosarelor complete ('.$validator.')');
            $result['validated']++;
        }
        Audit::log('campaign.bulk_validated', null, 'Validare în masă: '.$result['validated'].' din '.$result['scanned'].' dosare verificate, validator '.$validator);

        return $result;
    }

    /**
     * auto_repair_integrity(): numere lipsa/duplicate (fara dosarele protejate de fotografie) si consimtaminte.
     * Un consimtamant semnat cu alte nume nu se modifica: se creeaza o varianta corectata pentru resemnare.
     */
    public function autoRepair(?string $month, User $actor): array
    {
        $history = $this->backfillReportNumbers($month, $actor);
        $renumbered = [];
        $pendingSynced = 0;
        $corrected = [];
        $groups = [];
        foreach ($this->finalizedRows($month, ! ($month === self::OCTOBER && ! $this->priorCodes())) as $item) {
            $case = SterilizationCase::query()->active()->find($item['id']);
            if (! $case) {
                continue;
            }
            $row = $case->row();
            $numbers = $this->cases->ensureUniqueNumber($row);
            if ($numbers['changed']) {
                $case = $this->save($case, $row, $actor, 'auto_repair', 'Număr pacient reparat automat: '.($numbers['old'] ?: 'fără număr').' → '.$numbers['new']);
                $renumbered[] = ['case_id' => $case->id, 'code' => $row['cod_caz'], 'patient' => $row['nume_pacient'], 'old' => $numbers['old'], 'new' => $numbers['new']];
                $row = $case->row();
            }
            $group = (int) ($row['visit_group_id'] ?: $row['id']);
            if (isset($groups[$group])) {
                continue;
            }
            $groups[$group] = true;
            $groupRows = $this->cases->groupRows($row);
            $consent = $this->checks->consentFor($row);
            $before = (int) ($row['consent_id'] ?: $row['shared_consent_id']);
            if ($consent && $consent->isSigned()) {
                if (! $this->checks->identity($row)['ok']) {
                    $id = $this->consents->createCorrected($row, $groupRows, 'Reparare automată integritate raport Primărie', $actor);
                    $this->cases->assignConsentToGroup($row, $id, $actor);
                    $corrected[] = ['consent_id' => $id, 'case_id' => $case->id, 'code' => $this->consents->lastCode, 'owner' => $row['nume_proprietar'], 'patient' => $row['nume_pacient']];
                }
            } elseif ($id = $this->consents->sync($row, $groupRows, $actor)) {
                if ($id !== $before) {
                    $this->cases->assignConsentToGroup($row, $id, $actor);
                }
                $pendingSynced++;
            }
        }
        $this->checks->forget();
        Audit::log('campaign.auto_repair', null, 'Reparare automată '.self::monthLabel($month).': '.count($renumbered).' numere, '.$pendingSynced.' consimțăminte sincronizate, '.count($corrected).' corectate');

        return ['renumbered' => $renumbered, 'pending_synced' => $pendingSynced, 'corrected_consents' => $corrected,
            'report_numbers_restored' => $history['restored_history'], 'report_numbers_frozen' => $history['frozen_current'], 'report_numbers_unresolved' => $history['unresolved_history']];
    }
}
