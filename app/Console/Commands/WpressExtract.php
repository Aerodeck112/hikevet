<?php

namespace App\Console\Commands;

use App\Services\Import\WpressArchive;
use Illuminate\Console\Command;

/** Desface un backup All-in-One WP Migration, pentru import. Nu are nevoie de WordPress. */
class WpressExtract extends Command
{
    protected $signature = 'hikevet:wpress {file : Backupul .wpress}
        {target? : Directorul în care se extrage (implicit storage/imports/wpress)}
        {--list : Doar rezumatul conținutului}
        {--only=* : Doar anumite părți: database, plugins, uploads, themes, config}';

    protected $description = 'Citește sau desface un backup All-in-One WP Migration (.wpress).';

    public function handle(): int
    {
        $archive = new WpressArchive($this->argument('file'));
        $package = $archive->package();

        $this->info('WordPress '.($package['WordPress']['Version'] ?? '?').' · plugin '.($package['Plugin']['Version'] ?? '?')
            .' · prefix tabele '.($package['Database']['Prefix'] ?? '?')
            .' · compresie '.($archive->compression() ?? 'nu').' · parolă '.($archive->isEncrypted() ? 'DA' : 'nu'));

        if ($this->option('list')) {
            $groups = [];
            foreach ($archive->entries() as $e) {
                $group = $this->group($e['path']);
                $groups[$group] ??= ['files' => 0, 'bytes' => 0];
                $groups[$group]['files']++;
                $groups[$group]['bytes'] += $e['size'];
            }
            ksort($groups);
            $this->table(['Parte', 'Fișiere', 'MB'], array_map(fn ($g, $k) => [$k, $g['files'], number_format($g['bytes'] / 1048576, 1)], $groups, array_keys($groups)));

            return self::SUCCESS;
        }

        $only = $this->option('only');
        $target = $this->argument('target') ?: storage_path('imports/wpress');
        $stats = $archive->extract($target, $only ? fn (string $p) => in_array($this->group($p), $only, true) : null,
            function (array $s) {
                if ($s['files'] % 2000 === 0) {
                    $this->line(sprintf('  %d fișiere, %.0f MB', $s['files'], $s['bytes'] / 1048576));
                }
            });

        // In database.sql pluginul inlocuieste prefixul tabelelor cu SERVMASK_PREFIX_; il punem la loc, ca baza sa arate ca pe site.
        $sql = $target.'/database.sql';
        $prefix = $package['Database']['Prefix'] ?? null;
        if (is_file($sql) && is_string($prefix) && preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
            $in = fopen($sql, 'rb');
            $out = fopen($target.'/database.restored.sql', 'wb');
            while (($line = fgets($in)) !== false) {
                fwrite($out, str_replace('SERVMASK_PREFIX_', $prefix, $line));
            }
            fclose($in);
            fclose($out);
            $this->line('Baza de date cu prefixul original ('.$prefix.'): '.$target.'/database.restored.sql');
        }

        $this->info(sprintf('Extrase %d fișiere (%.1f MB) în %s; %d sărite.', $stats['files'], $stats['bytes'] / 1048576, $target, $stats['skipped']));
        $this->line('CRC verificat la '.$stats['crc_checked'].' fișiere; nepotriviri: '.count($stats['crc_mismatch']));
        foreach (array_slice($stats['crc_mismatch'], 0, 20) as $path) {
            $this->warn('  CRC diferit: '.$path);
        }

        return $stats['crc_mismatch'] ? self::FAILURE : self::SUCCESS;
    }

    private function group(string $path): string
    {
        return match (true) {
            $path === 'database.sql' => 'database',
            str_starts_with($path, 'plugins/') => 'plugins',
            str_starts_with($path, 'uploads/') => 'uploads',
            str_starts_with($path, 'themes/') => 'themes',
            in_array($path, ['package.json', 'multisite.json'], true) || str_ends_with($path, '.list') => 'config',
            default => 'other',
        };
    }
}
