<?php

namespace App\Console\Commands;

use App\Services\Updates\UpdateService;
use App\Support\Audit;
use Illuminate\Console\Command;
use Throwable;

/** Aceeasi actualizare ca din panou, pentru conturile cu Terminal / SSH sau cand incarcarea prin browser este limitata. */
class ApplyUpdate extends Command
{
    protected $signature = 'hikevet:update {file : Pachetul ZIP semnat} {--check : Doar verifică pachetul, fără instalare} {--force : Fără întrebare de confirmare}';

    protected $description = 'Verifică și instalează un pachet de actualizare semnat.';

    public function handle(UpdateService $updates): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error('Fișierul nu există: '.$file);

            return self::FAILURE;
        }

        try {
            $preflight = $updates->preflight($file);
        } catch (Throwable $e) {
            $this->error('Pachet respins: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Pachet valid: versiunea '.$preflight['manifest']['version'].', '.count($preflight['manifest']['files']).' fișiere.');
        foreach ($preflight['checks'] as $check) {
            $this->line(($check['ok'] ? '  [ok]      ' : '  [BLOCHEAZĂ] ').$check['label'].' – '.$check['detail']);
        }
        if (! $preflight['ok']) {
            return self::FAILURE;
        }
        if ($this->option('check') || (! $this->option('force') && ! $this->confirm('Instalezi versiunea '.$preflight['manifest']['version'].'? Site-ul intră în mentenanță câteva momente.'))) {
            return self::SUCCESS;
        }

        $run = $updates->apply($file);
        Audit::log('update.applied', $run, 'Actualizare din linia de comandă '.$run->from_version.' → '.$run->to_version.': '.$run->status);
        $this->line($run->log);

        return $run->status === 'succeeded' ? self::SUCCESS : self::FAILURE;
    }
}
