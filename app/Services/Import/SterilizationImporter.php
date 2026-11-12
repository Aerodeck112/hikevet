<?php

namespace App\Services\Import;

use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Importul arhivei istorice a campaniei de sterilizare (hikevet-sterilization-campaign 2.7.6).
 *
 * Reguli: fiecare rand sursa se pastreaza integral si neschimbat (source_row + row_sha256);
 * nimic nu se corecteaza, nu se renumeroteaza si nu se completeaza. Ce lipseste in sursa este
 * marcat "Lipsa in sursa". Importul este idempotent (cheie: ID-ul original) si poate fi anulat.
 */
class SterilizationImporter
{
    public const KIND = 'sterilization';

    /** Campuri fara de care un dosar finalizat este semnalat in raportul de import. */
    public const EXPECTED_WHEN_FINALIZED = ['nume_proprietar', 'nume_pacient', 'specie', 'sex', 'data_interventie', 'procedura_efectuata', 'medic_operator', 'numar_pacient', 'poza_pacient'];

    /** Coloanele copiate separat pentru cautare; restul raman doar in source_row. */
    private const INDEXED = ['cod_caz', 'numar_pacient', 'numar_pacient_raport', 'ora_programare', 'nume_proprietar', 'telefon', 'email', 'localitate', 'specie', 'sex',
        'nume_pacient', 'rasa', 'microcip', 'procedura_efectuata', 'tip_anestezie', 'medic_operator', 'status_caz', 'status_interventie', 'validat_pentru_primarie', 'validat_de'];

    private const DATES = ['data_programare', 'data_prezentare', 'data_interventie', 'data_raportare_primarie', 'data_externare', 'data_validare'];

    public function __construct(private readonly WordPressSource $source) {}

    /** Verificari inainte de import: tabele, coloane fata de schema din cod, numar de randuri. */
    public function preflight(): array
    {
        $spec = json_decode(file_get_contents(database_path('import-spec/wp-tables.json')), true);
        $report = ['prefix' => $this->source->prefix(), 'tables' => []];

        foreach (['hv_st_cases', 'hv_st_appointments', 'hv_st_email_log'] as $table) {
            if (! $this->source->has($table)) {
                $report['tables'][$table] = ['present' => false];

                continue;
            }
            $expected = array_column($spec[$table]['columns'], 'name');
            $actual = $this->source->columns($table);
            $report['tables'][$table] = [
                'present' => true, 'rows' => $this->source->table($table)->count(),
                'missing_columns' => array_values(array_diff($expected, $actual)),
                'extra_columns' => array_values(array_diff($actual, $expected)),
            ];
        }

        return $report;
    }

    public function run(bool $dryRun = true, ?string $uploadsDir = null, ?int $userId = null): ImportBatch
    {
        $batch = ImportBatch::create(['kind' => self::KIND, 'source_label' => $this->source->prefix().'hv_st_*', 'dry_run' => $dryRun,
            'status' => 'running', 'started_by' => $userId, 'started_at' => now()]);

        $counts = ['cases_in_source' => 0, 'cases_new' => 0, 'cases_unchanged' => 0, 'cases_changed_in_source' => 0, 'finalized' => 0,
            'appointments' => 0, 'email_logs' => 0, 'documents' => 0, 'documents_missing' => 0, 'consent_links' => 0, 'consent_links_unresolved' => 0];
        $errors = [];
        $missing = [];
        $hashes = [];
        $byMonth = [];
        $seenCodes = [];

        $work = function () use (&$counts, &$errors, &$missing, &$hashes, &$byMonth, &$seenCodes, $dryRun, $uploadsDir, $batch) {
            if (! $this->source->has('hv_st_cases')) {
                $errors[] = ['table' => 'hv_st_cases', 'error' => 'Tabela lipsește din sursă.'];

                return;
            }

            $existing = DB::table('sterilization_cases')->pluck('row_sha256', 'source_id');
            $consentMap = DB::table('consents')->whereNotNull('wp_post_id')->pluck('id', 'wp_post_id');

            foreach ($this->source->table('hv_st_cases')->orderBy('id')->cursor() as $row) {
                $row = (array) $row;
                $counts['cases_in_source']++;
                $sourceId = (int) $row['id'];
                $code = trim((string) ($row['cod_caz'] ?? ''));
                $hash = hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE));
                $hashes[] = $sourceId.':'.$hash;

                if ($code === '') {
                    $errors[] = ['source_id' => $sourceId, 'error' => 'Dosar fără cod de caz; nu a fost importat.'];

                    continue;
                }
                if (isset($seenCodes[$code])) {
                    $errors[] = ['source_id' => $sourceId, 'cod_caz' => $code, 'error' => 'Cod de caz duplicat în sursă (și la ID '.$seenCodes[$code].').'];

                    continue;
                }
                $seenCodes[$code] = $sourceId;

                $finalized = in_array($row['status_interventie'] ?? '', ['Finalizat', 'Operat'], true);
                $lacking = [];
                foreach (self::DATES as $column) {
                    $raw = trim((string) ($row[$column] ?? ''));
                    if ($raw !== '' && WordPressSource::date($raw) === null) {
                        $lacking[] = $column.' (valoare invalidă în sursă: '.$raw.')';
                    }
                }
                if ($finalized) {
                    $counts['finalized']++;
                    foreach (self::EXPECTED_WHEN_FINALIZED as $column) {
                        if (trim((string) ($row[$column] ?? '')) === '' || str_starts_with((string) ($row[$column] ?? ''), '0000-00-00')) {
                            $lacking[] = $column;
                        }
                    }
                    $month = substr((string) (WordPressSource::date($row['data_interventie'] ?? '') ?? ''), 0, 7) ?: 'fără dată';
                    $byMonth[$month] = ($byMonth[$month] ?? 0) + 1;
                }
                if ($lacking) {
                    $missing[] = ['cod_caz' => $code, 'fields' => array_values(array_unique($lacking))];
                }

                $sourceConsent = (int) (($row['consent_id'] ?? 0) ?: ($row['shared_consent_id'] ?? 0));
                if ($sourceConsent) {
                    isset($consentMap[$sourceConsent]) ? $counts['consent_links']++ : $counts['consent_links_unresolved']++;
                }

                $state = ! isset($existing[$sourceId]) ? 'new' : ($existing[$sourceId] === $hash ? 'unchanged' : 'changed');
                $counts[['new' => 'cases_new', 'unchanged' => 'cases_unchanged', 'changed' => 'cases_changed_in_source'][$state]]++;

                $documents = $this->documentsFor($row, $uploadsDir);
                $counts['documents'] += count($documents);
                $counts['documents_missing'] += count(array_filter($documents, fn ($d) => $d['is_missing']));

                if ($dryRun || $state === 'unchanged') {
                    continue;
                }

                $data = ['import_batch_id' => $batch->id, 'source_id' => $sourceId, 'visit_group_id' => ($row['visit_group_id'] ?? 0) ?: null,
                    'source_consent_id' => ($row['consent_id'] ?? 0) ?: null, 'source_shared_consent_id' => ($row['shared_consent_id'] ?? 0) ?: null,
                    'consent_id' => $consentMap[$sourceConsent] ?? null, 'source_row' => json_encode($row, JSON_UNESCAPED_UNICODE), 'row_sha256' => $hash,
                    'missing_in_source' => $lacking ? json_encode(array_values(array_unique($lacking)), JSON_UNESCAPED_UNICODE) : null,
                    'source_created_at' => WordPressSource::date($row['timestamp_creat'] ?? ''), 'source_updated_at' => WordPressSource::date($row['timestamp_actualizat'] ?? ''),
                    'imported_at' => now()];
                foreach (self::INDEXED as $column) {
                    $data[$column] = ($v = trim((string) ($row[$column] ?? ''))) === '' ? null : $v;
                }
                foreach (self::DATES as $column) {
                    $data[$column] = WordPressSource::date($row[$column] ?? '');
                }

                // Scriere directa in tabel: modelul SterilizationCase este read-only prin constructie.
                DB::table('sterilization_cases')->updateOrInsert(['source_id' => $sourceId], $data);
                $caseId = DB::table('sterilization_cases')->where('source_id', $sourceId)->value('id');
                DB::table('import_id_map')->updateOrInsert(['entity' => 'sterilization_case', 'source_id' => (string) $sourceId],
                    ['import_batch_id' => $batch->id, 'target_id' => $caseId, 'row_sha256' => $hash]);

                DB::table('sterilization_documents')->where('sterilization_case_id', $caseId)->delete();
                foreach ($documents as $document) {
                    DB::table('sterilization_documents')->insert($this->storeDocument($document, $caseId) + ['created_at' => now(), 'updated_at' => now()]);
                }
            }

            $this->importAppointments($dryRun, $counts);
            $this->importEmailLogs($dryRun, $counts);
            if (! $dryRun) {
                $this->importReferenceLists();
            }
        };

        try {
            $dryRun ? $work() : DB::transaction($work);
            $status = 'finished';
        } catch (\Throwable $e) {
            report($e);
            $errors[] = ['error' => 'Importul a fost oprit și anulat: '.mb_substr($e->getMessage(), 0, 500)];
            $status = 'failed';
        }

        ksort($byMonth);
        $counts['finalized_by_month'] = $byMonth;
        $counts['cases_in_archive_after'] = DB::table('sterilization_cases')->count();
        $batch->update(['status' => $status, 'counts' => $counts, 'errors' => array_slice($errors, 0, 500), 'missing' => array_slice($missing, 0, 2000),
            'source_sha256' => hash('sha256', implode("\n", $hashes)), 'finished_at' => now()]);

        return $batch;
    }

    /** Anuleaza un import real: sterge exact randurile aduse de acel lot. Se foloseste inainte de activarea in productie. */
    public function rollback(ImportBatch $batch): int
    {
        if ($batch->dry_run || $batch->kind !== self::KIND) {
            return 0;
        }

        return DB::transaction(function () use ($batch) {
            $ids = DB::table('sterilization_cases')->where('import_batch_id', $batch->id)->pluck('id');
            foreach (DB::table('sterilization_documents')->whereIn('sterilization_case_id', $ids)->whereNotNull('stored_path')->pluck('stored_path') as $path) {
                Storage::disk('private')->delete($path);
            }
            DB::table('sterilization_documents')->whereIn('sterilization_case_id', $ids)->delete();
            DB::table('sterilization_appointments')->whereIn('sterilization_case_id', $ids)->delete();
            DB::table('sterilization_email_logs')->whereIn('sterilization_case_id', $ids)->delete();
            DB::table('import_id_map')->where('import_batch_id', $batch->id)->delete();
            $deleted = DB::table('sterilization_cases')->whereIn('id', $ids)->delete();
            $batch->update(['rolled_back_at' => now(), 'status' => 'rolled_back']);

            return $deleted;
        });
    }

    /** @return list<array{kind:string,reference:string,path:?string,is_missing:bool}> */
    private function documentsFor(array $row, ?string $uploadsDir): array
    {
        $documents = [];
        foreach (['poza_pacient' => 'fotografie_pacient', 'documente_scanate' => 'act_identitate'] as $column => $kind) {
            foreach (preg_split('/[\s,;|]+/', trim((string) ($row[$column] ?? '')), -1, PREG_SPLIT_NO_EMPTY) as $reference) {
                $path = $this->resolveFile($reference, $uploadsDir);
                $documents[] = ['kind' => $kind, 'reference' => $reference, 'path' => $path, 'is_missing' => $path === null];
            }
        }
        if (in_array($row['status_interventie'] ?? '', ['Finalizat', 'Operat'], true) && trim((string) ($row['poza_pacient'] ?? '')) === '') {
            $documents[] = ['kind' => 'fotografie_pacient', 'reference' => '', 'path' => null, 'is_missing' => true];
        }

        return $documents;
    }

    /** Gaseste fisierul in copia wp-content/uploads, fara a iesi din directorul indicat. */
    private function resolveFile(string $reference, ?string $uploadsDir): ?string
    {
        if (! $uploadsDir || ! is_dir($uploadsDir)) {
            return null;
        }
        $relative = preg_replace('#^.*?/wp-content/uploads/#', '', parse_url($reference, PHP_URL_PATH) ?: $reference);
        $candidate = realpath(rtrim($uploadsDir, '/').'/'.ltrim($relative, '/'));
        $root = realpath($uploadsDir);

        return ($candidate && $root && str_starts_with($candidate, $root.DIRECTORY_SEPARATOR) && is_file($candidate)) ? $candidate : null;
    }

    private function storeDocument(array $document, int $caseId): array
    {
        $stored = null;
        $hash = null;
        $size = null;
        $mime = null;

        if ($document['path']) {
            $hash = hash_file('sha256', $document['path']);
            $size = filesize($document['path']);
            $mime = mime_content_type($document['path']) ?: null;
            $stored = 'sterilization/'.$caseId.'/'.$hash.'.'.strtolower(pathinfo($document['path'], PATHINFO_EXTENSION) ?: 'bin');
            // Originalul se copiaza bit cu bit; amprenta se verifica dupa copiere.
            Storage::disk('private')->put($stored, file_get_contents($document['path']));
            if (hash('sha256', Storage::disk('private')->get($stored)) !== $hash) {
                throw new \RuntimeException('Amprenta fișierului copiat diferă de original: '.$document['reference']);
            }
        }

        return ['sterilization_case_id' => $caseId, 'kind' => $document['kind'], 'source_reference' => $document['reference'] ?: null,
            'stored_path' => $stored, 'sha256' => $hash, 'size' => $size, 'mime' => $mime, 'is_missing' => $document['is_missing']];
    }

    private function importAppointments(bool $dryRun, array &$counts): void
    {
        if (! $this->source->has('hv_st_appointments')) {
            return;
        }
        $caseMap = DB::table('sterilization_cases')->pluck('id', 'source_id');

        foreach ($this->source->table('hv_st_appointments')->orderBy('id')->cursor() as $row) {
            $row = (array) $row;
            $counts['appointments']++;
            if ($dryRun) {
                continue;
            }
            DB::table('sterilization_appointments')->updateOrInsert(['source_id' => (int) $row['id']], [
                'appointment_code' => $row['appointment_code'] ?? null, 'source_case_id' => ($row['case_id'] ?? 0) ?: null,
                'sterilization_case_id' => $caseMap[(int) ($row['case_id'] ?? 0)] ?? null, 'appointment_date' => WordPressSource::date($row['appointment_date'] ?? ''),
                'start_time' => $row['start_time'] ?? null, 'end_time' => $row['end_time'] ?? null, 'appointment_type' => $row['appointment_type'] ?? null,
                'status' => $row['status'] ?? null, 'owner_name' => $row['owner_name'] ?? null, 'patient_name' => $row['patient_name'] ?? null,
                'source_row' => json_encode($row, JSON_UNESCAPED_UNICODE), 'row_sha256' => hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE)),
            ]);
        }
    }

    private function importEmailLogs(bool $dryRun, array &$counts): void
    {
        if (! $this->source->has('hv_st_email_log')) {
            return;
        }
        $caseMap = DB::table('sterilization_cases')->pluck('id', 'source_id');

        foreach ($this->source->table('hv_st_email_log')->orderBy('id')->cursor() as $row) {
            $row = (array) $row;
            $counts['email_logs']++;
            if ($dryRun) {
                continue;
            }
            DB::table('sterilization_email_logs')->updateOrInsert(['source_id' => (int) $row['id']], [
                'source_case_id' => ($row['case_id'] ?? 0) ?: null, 'sterilization_case_id' => $caseMap[(int) ($row['case_id'] ?? 0)] ?? null,
                'email_type' => $row['email_type'] ?? null, 'status' => $row['status'] ?? null, 'sent_at' => WordPressSource::date($row['created_at'] ?? ''),
                'source_row' => json_encode($row, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    /**
     * Listele de referinta ale raportarilor: optiunea salvata in WordPress daca exista, altfel fisierele JSON
     * livrate cu pluginul (database/import-spec). Sunt liste de coduri, nu dosare.
     */
    private function importReferenceLists(): void
    {
        $lists = [];
        if ($this->source->has('options')) {
            $option = $this->source->table('options')->where('option_name', 'hv_st_reported_through_september_2026')->value('option_value');
            $decoded = WordPressSource::meta($option);
            if (is_array($decoded) && $decoded) {
                $lists['reported_through_september_2026'] = $decoded;
            }
        }
        foreach (['reported_through_september_2026' => 'reported-through-september-2026.json', 'september_2026_145' => 'september-2026-145.json'] as $key => $file) {
            foreach ([database_path('import-spec/'.$file), base_path('source-wordpress/hikevet-sterilization-campaign/'.$file)] as $path) {
                if (! isset($lists[$key]) && is_file($path)) {
                    $lists[$key] = json_decode(file_get_contents($path), true) ?: [];
                }
            }
        }

        foreach ($lists as $key => $items) {
            foreach ($items as $code => $value) {
                DB::table('sterilization_reference_lists')->updateOrInsert(['list_key' => $key, 'cod_caz' => (string) $code], ['value' => is_scalar($value) ? (string) $value : null]);
            }
            if ($key === 'reported_through_september_2026') {
                foreach ($items as $code => $month) {
                    DB::table('sterilization_cases')->where('cod_caz', (string) $code)->update(['reported_month' => is_string($month) ? substr($month, 0, 7) : null]);
                }
            }
        }
    }
}
