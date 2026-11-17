<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Services\Import\SterilizationImporter;
use App\Services\Import\WordPressCoreImporter;
use App\Services\Import\WordPressSource;
use App\Services\Legacy\LegacyArchive;
use App\Support\Audit;
use Illuminate\Console\Command;

class ImportWordPress extends Command
{
    protected $signature = 'hikevet:import-wordpress
        {--execute : Scrie datele. Fără această opțiune rulează doar proba (dry-run)}
        {--only= : core, sterilization sau legacy (implicit toate, în această ordine)}
        {--uploads= : Calea către copia wp-content/uploads, pentru documente și fotografii}
        {--connection=wp : Conexiunea către copia bazei WordPress}
        {--rollback= : Anulează importul real cu ID-ul dat (doar arhiva de sterilizări)}
        {--force : Permite scrierea în mediul de producție}
        {--dump= : Fișierul SQL original, păstrat criptat în arhiva WordPress (doar cu --execute)}
        {--skip-legacy=* : Tabele lăsate în afara arhivei brute (de ex. cache-uri mari)}
        {--keep-file=* : Fișiere originale păstrate criptat în arhivă (de ex. pachetele trimise Primăriei)}';

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

        if ($broken = $source->doubleEncoding()) {
            $this->error('Baza WordPress pare încărcată cu alt set de caractere: diacriticele sunt codate de două ori (de ex. „câine” apare ca „cÃ¢ine”).');
            foreach ($broken as $table => $rows) {
                $this->line("  {$table}: {$rows} rânduri afectate");
            }
            $this->line('Reîncarcă baza din database.restored.sql cu setul utf8mb4 (fișierul generat de hikevet:wpress îl declară deja;');
            $this->line('din linia de comandă: mysql --default-character-set=utf8mb4 ...), apoi rulează din nou. Nu s-a importat nimic.');

            return self::FAILURE;
        }

        $this->components->info($execute ? 'IMPORT REAL' : 'PROBĂ (dry-run): nu se scrie nimic');
        $only = $this->option('only');
        $failed = false;

        if (! $only || $only === 'core') {
            // Consimtamintele se importa inaintea dosarelor, ca legaturile dosar - consimtamant sa poata fi rezolvate.
            $failed = $this->report('Conturi, animale, contracte, Wallet, consimțăminte', (new WordPressCoreImporter($source))->run(! $execute, null, $this->option('uploads') ?: config('hikevet.wp_uploads_path'))) || $failed;
        }
        if (! $only || $only === 'sterilization') {
            foreach ($sterilization->preflight()['tables'] as $table => $info) {
                $this->line(sprintf('  %-22s %s', $table, ! $info['present'] ? 'LIPSEȘTE' : $info['rows'].' rânduri'
                    .($info['missing_columns'] ? ' | coloane lipsă: '.implode(', ', $info['missing_columns']) : '')
                    .($info['extra_columns'] ? ' | coloane în plus: '.implode(', ', $info['extra_columns']) : '')));
            }
            $failed = $this->report('Arhivă sterilizări', $sterilization->run(! $execute, $this->option('uploads'))) || $failed;
        }
        if (! $only || $only === 'legacy') {
            $this->newLine();
            $this->components->twoColumnDetail('<options=bold>Arhiva brută WordPress</>', $execute ? 'copiere' : 'probă');
            $skip = array_map(fn ($t) => str_starts_with($t, $source->prefix()) ? $t : $source->prefix().$t, $this->option('skip-legacy'));
            if (! $execute) {
                $rows = 0;
                foreach (array_diff($source->tables(), $skip) as $table) {
                    $rows += $source->db()->table($table)->count();
                }
                $this->components->twoColumnDetail('tabele / rânduri de copiat', count(array_diff($source->tables(), $skip)).' / '.$rows);
            } else {
                $stats = app(LegacyArchive::class)->snapshot($source, null, fn ($t, $n) => $this->line(sprintf('  %-40s %d', $t, $n)), $skip);
                $this->components->twoColumnDetail('tabele / rânduri copiate', $stats['tables'].' / '.$stats['rows']);
                if ($dump = $this->option('dump')) {
                    $file = app(LegacyArchive::class)->storeFile($dump, 'wordpress-sql', 'Baza WordPress originală ('.basename($dump).')');
                    $this->components->twoColumnDetail('fișier SQL original', 'păstrat criptat, SHA-256 '.$file->sha256);
                }
                foreach ($this->option('keep-file') as $keep) {
                    $file = app(LegacyArchive::class)->storeFile($keep, 'fisier-original', basename($keep));
                    $this->components->twoColumnDetail(basename($keep), sprintf('%.1f MB, SHA-256 %s', $file->bytes / 1048576, $file->sha256));
                }
                Audit::log('legacy.snapshot', null, 'Arhiva WordPress copiată: '.$stats['tables'].' tabele, '.$stats['rows'].' rânduri');
            }
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
