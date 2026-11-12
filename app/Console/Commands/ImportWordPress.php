<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Services\Import\SterilizationImporter;
use App\Services\Import\WordPressCoreImporter;
use App\Services\Import\WordPressSource;
use Illuminate\Console\Command;

class ImportWordPress extends Command
{
    protected $signature = 'hikevet:import-wordpress
        {--execute : Scrie datele. Fără această opțiune rulează doar proba (dry-run)}
        {--only= : core sau sterilization (implicit ambele, în această ordine)}
        {--uploads= : Calea către copia wp-content/uploads, pentru documente și fotografii}
        {--connection=wp : Conexiunea către copia bazei WordPress}
        {--rollback= : Anulează importul real cu ID-ul dat (doar arhiva de sterilizări)}
        {--force : Permite scrierea în mediul de producție}';

    protected $description = 'Importă datele din copia bazei WordPress. Implicit rulează de probă și nu scrie nimic.';

    public function handle(): int
    {
        $source = new WordPressSource($this->option('connection'));
        $sterilization = new SterilizationImporter($source);

        if ($id = $this->option('rollback')) {
            $deleted = $sterilization->rollback(ImportBatch::findOrFail($id));
            $this->info("Import anulat: {$deleted} dosare șterse din arhivă.");

            return self::SUCCESS;
        }

        $execute = (bool) $this->option('execute');
        if ($execute && app()->isProduction() && ! $this->option('force')) {
            $this->error('În producție importul real cere --force, după ce proba (dry-run) a fost verificată și aprobată.');

            return self::FAILURE;
        }

        try {
            $this->line('Prefix tabele WordPress: '.$source->prefix());
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info($execute ? 'IMPORT REAL' : 'PROBĂ (dry-run): nu se scrie nimic');
        $only = $this->option('only');
        $failed = false;

        if (! $only || $only === 'core') {
            // Consimtamintele se importa inaintea dosarelor, ca legaturile dosar - consimtamant sa poata fi rezolvate.
            $failed = $this->report('Conturi, animale, contracte, Wallet, consimțăminte', (new WordPressCoreImporter($source))->run(! $execute)) || $failed;
        }
        if (! $only || $only === 'sterilization') {
            foreach ($sterilization->preflight()['tables'] as $table => $info) {
                $this->line(sprintf('  %-22s %s', $table, ! $info['present'] ? 'LIPSEȘTE' : $info['rows'].' rânduri'
                    .($info['missing_columns'] ? ' | coloane lipsă: '.implode(', ', $info['missing_columns']) : '')
                    .($info['extra_columns'] ? ' | coloane în plus: '.implode(', ', $info['extra_columns']) : '')));
            }
            $failed = $this->report('Arhivă sterilizări', $sterilization->run(! $execute, $this->option('uploads'))) || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function report(string $title, ImportBatch $batch): bool
    {
        $this->newLine();
        $this->components->twoColumnDetail("<options=bold>{$title}</>", 'lot #'.$batch->id.' · '.$batch->status);
        foreach ($batch->counts ?? [] as $key => $value) {
            $this->components->twoColumnDetail($key, is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value);
        }
        if ($batch->source_sha256) {
            $this->components->twoColumnDetail('checksum sursă', $batch->source_sha256);
        }
        $this->components->twoColumnDetail('erori', (string) count($batch->errors ?? []));
        $this->components->twoColumnDetail('dosare cu date lipsă în sursă', (string) count($batch->missing ?? []));
        foreach (array_slice($batch->errors ?? [], 0, 10) as $error) {
            $this->line('  - '.json_encode($error, JSON_UNESCAPED_UNICODE));
        }

        return $batch->status !== 'finished';
    }
}
