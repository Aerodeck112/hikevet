<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class Backup extends Command
{
    protected $signature = 'hikevet:backup {--label=zilnic} {--keep=14 : Câte backupuri se păstrează} {--verify : Recitește backupul creat}
        {--restore= : Numele unui backup din storage/app/backups de restaurat} {--force}';

    protected $description = 'Creează un backup criptat al bazei de date sau restaurează unul existent.';

    public function handle(BackupService $backups): int
    {
        if ($name = $this->option('restore')) {
            $path = $backups->directory().'/'.basename($name);
            if (! is_file($path)) {
                $this->error('Backupul nu există: '.basename($name));

                return self::FAILURE;
            }
            if (! $this->option('force') && ! $this->confirm('Restaurarea înlocuiește TOATE datele curente cu cele din backup. Continui?')) {
                return self::FAILURE;
            }
            $safety = $backups->create('inainte-de-restaurare');
            $stats = $backups->restore($path);
            $this->info("Restaurat: {$stats['tables']} tabele, {$stats['rows']} rânduri. Starea anterioară a fost salvată în ".basename($safety['path']).'.');

            return self::SUCCESS;
        }

        $result = $backups->create((string) $this->option('label'));
        $this->info(sprintf('Backup creat: %s (%d tabele, %d rânduri, %.1f KB)', basename($result['path']), $result['tables'], $result['rows'], $result['bytes'] / 1024));
        if ($this->option('verify')) {
            $check = $backups->verify($result['path']);
            $this->line("Verificat: {$check['tables']} tabele, {$check['rows']} rânduri citite înapoi.");
            if ($check['rows'] !== $result['rows']) {
                $this->error('Numărul de rânduri citite diferă de cel scris.');

                return self::FAILURE;
            }
        }
        $this->line('Backupuri vechi șterse: '.$backups->prune((int) $this->option('keep')));

        return self::SUCCESS;
    }
}
