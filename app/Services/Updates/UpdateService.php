<?php

namespace App\Services\Updates;

use App\Models\UpdateRun;
use App\Services\Backup\BackupService;
use App\Support\Tables;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Aplica un pachet de actualizare verificat.
 *
 * Flux: verificare semnatura si amprente -> verificari de compatibilitate -> backup baza de date
 * si copie a fisierelor care vor fi inlocuite -> mod mentenanta -> copiere fisiere -> migrari ->
 * golire cache -> verificare de sanatate -> revenire online. La orice eroare: fisierele vechi sunt
 * puse la loc si, daca migrarile au rulat, baza de date este readusa din backup.
 *
 * Pe hosting shared nu exista schimbare atomica de director: fisierele se inlocuiesc pe rand, cu
 * site-ul in mentenanta. Daca procesul PHP este oprit la mijloc (limita de timp), restaurarea se
 * face manual din copia pastrata in storage/app/backups (vezi UPDATES.md).
 */
class UpdateService
{
    public function __construct(private readonly ReleasePackage $package, private readonly BackupService $backups) {}

    public function basePath(): string
    {
        return config('hikevet.update_base_path') ?: base_path();
    }

    /** Verificari facute inainte de a atinge ceva. @return array{manifest:array,checks:list<array{label:string,ok:bool,detail:string}>,ok:bool} */
    public function preflight(string $zipPath): array
    {
        $verified = $this->package->verify($zipPath, (string) config('hikevet.update_public_key'));
        $m = $verified['manifest'];
        $current = (string) config('hikevet.version');
        $bytes = filesize($zipPath);
        $free = @disk_free_space($this->basePath());
        $base = $this->basePath();
        $unwritable = array_values(array_filter(array_unique(array_map(fn ($f) => explode('/', $f)[0], array_keys($verified['files']))),
            fn ($top) => file_exists($base.'/'.$top) && ! is_writable($base.'/'.$top)));

        $checks = [
            ['label' => 'Semnătură și amprente', 'ok' => true, 'detail' => count($verified['files']).' fișiere verificate'],
            ['label' => 'Versiune mai nouă', 'ok' => version_compare($m['version'], $current, '>'), 'detail' => $current.' → '.$m['version']],
            ['label' => 'Versiune PHP', 'ok' => version_compare(PHP_VERSION, $m['min_php'] ?? '8.3.0', '>='), 'detail' => 'server '.PHP_VERSION.', necesar '.($m['min_php'] ?? '8.3.0')],
            ['label' => 'Versiune de pornire', 'ok' => empty($m['requires_from']) || version_compare($current, $m['requires_from'], '>='), 'detail' => empty($m['requires_from']) ? 'orice versiune' : 'necesar cel puțin '.$m['requires_from']],
            ['label' => 'Pachet potrivit versiunii instalate', 'ok' => empty($m['base_version']) || $m['base_version'] === $current,
                'detail' => empty($m['base_version']) ? 'pachet complet' : 'pachet incremental pentru '.$m['base_version']],
            ['label' => 'Spațiu pe disc', 'ok' => $free === false || $free > $bytes * 4 + 50 * 1048576, 'detail' => $free === false ? 'nu poate fi citit' : round($free / 1048576).' MB liberi'],
            ['label' => 'Drepturi de scriere', 'ok' => $unwritable === [], 'detail' => $unwritable ? 'fără drept de scriere: '.implode(', ', $unwritable) : 'ok'],
            ['label' => 'Nicio altă actualizare în curs', 'ok' => ! is_file($this->lockFile()), 'detail' => is_file($this->lockFile()) ? 'există un blocaj din '.date('d.m.Y H:i', filemtime($this->lockFile())) : 'ok'],
        ];

        return ['manifest' => $m, 'checks' => $checks, 'ok' => ! in_array(false, array_column($checks, 'ok'), true), 'sha256' => hash_file('sha256', $zipPath), 'bytes' => $bytes];
    }

    public function apply(string $zipPath, ?int $actorId = null): UpdateRun
    {
        $preflight = $this->preflight($zipPath);
        if (! $preflight['ok']) {
            throw new RuntimeException('Verificările dinaintea actualizării nu au trecut: '.implode('; ', array_map(fn ($c) => $c['label'], array_filter($preflight['checks'], fn ($c) => ! $c['ok']))));
        }

        $manifest = $preflight['manifest'];
        $run = UpdateRun::create(['from_version' => config('hikevet.version'), 'to_version' => $manifest['version'], 'package_sha256' => $preflight['sha256'],
            'status' => 'running', 'actor_id' => $actorId, 'started_at' => now()]);
        $log = [];
        $note = function (string $line) use (&$log, $run) {
            $log[] = date('H:i:s').' '.$line;
            $run->forceFill(['log' => implode("\n", $log)])->save();
        };

        $lock = fopen($this->lockFile(), 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $run->update(['status' => 'failed', 'log' => 'O altă actualizare este deja în curs.', 'finished_at' => now()]);
            throw new RuntimeException('O altă actualizare este deja în curs.');
        }

        $base = $this->basePath();
        $backupDir = $this->backups->directory().'/update-'.date('Ymd-His').'-'.$manifest['version'];
        $replaced = [];
        $created = [];
        $removed = [];
        $migrated = false;
        $tablesBefore = [];
        $dbBackup = null;
        $isLive = $base === base_path();

        try {
            $dbBackup = $this->backups->create('inainte-de-'.$manifest['version']);
            $this->backups->verify($dbBackup['path']);
            $note('Backup bază de date: '.basename($dbBackup['path']).' ('.$dbBackup['rows'].' rânduri), verificat prin recitire');
            mkdir($backupDir.'/files', 0750, true);
            $run->forceFill(['backup_path' => $backupDir])->save();

            if ($isLive) {
                Artisan::call('down', ['--retry' => 30]);
                $note('Mod mentenanță activat');
            }

            $zip = new ZipArchive;
            $zip->open($zipPath, ZipArchive::RDONLY);
            foreach ($manifest['files'] as $relative => $sha) {
                $this->package->assertSafePath($relative);
                $target = $base.'/'.$relative;
                if (is_file($target)) {
                    if (hash_file('sha256', $target) === $sha) {
                        continue;
                    }
                    @mkdir(dirname($backupDir.'/files/'.$relative), 0750, true);
                    copy($target, $backupDir.'/files/'.$relative);
                    $replaced[] = $relative;
                } else {
                    $created[] = $relative;
                }
                @mkdir(dirname($target), 0755, true);
                // Scriere in fisier temporar + redenumire: un fisier nu ramane niciodata scris pe jumatate.
                $tmp = $target.'.hvtmp';
                $stream = $zip->getStream('files/'.$relative);
                $out = fopen($tmp, 'wb');
                stream_copy_to_stream($stream, $out);
                fclose($stream);
                fclose($out);
                if (hash_file('sha256', $tmp) !== $sha) {
                    @unlink($tmp);
                    throw new RuntimeException('Fișierul copiat nu corespunde manifestului: '.$relative);
                }
                rename($tmp, $target);
            }
            $zip->close();
            // Fisierele care nu mai exista in versiunea noua se sterg, dupa ce au fost copiate in backup.
            foreach ((array) ($manifest['remove'] ?? []) as $relative) {
                $this->package->assertSafePath($relative);
                $target = $base.'/'.$relative;
                if (is_file($target)) {
                    @mkdir(dirname($backupDir.'/files/'.$relative), 0750, true);
                    copy($target, $backupDir.'/files/'.$relative);
                    $removed[] = $relative;
                    unlink($target);
                }
            }
            file_put_contents($backupDir.'/manifest.json', json_encode(['from' => config('hikevet.version'), 'to' => $manifest['version'], 'replaced' => $replaced, 'created' => $created,
                'removed' => $removed, 'database_backup' => basename($dbBackup['path'])], JSON_PRETTY_PRINT));
            $note(count($replaced).' fișiere înlocuite, '.count($created).' fișiere noi, '.count($removed).' fișiere șterse');

            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            if ($isLive) {
                if (! empty($manifest['has_migrations'])) {
                    $tablesBefore = Tables::of();
                    $migrated = true;
                    Artisan::call('migrate', ['--force' => true]);
                    $note('Migrări rulate: '.trim(preg_replace('/\s+/', ' ', Artisan::output())));
                }
                Artisan::call('optimize:clear');
                $note('Cache golit');
                DB::select('select 1');
                Cache::forever('hikevet.last_update', now()->toIso8601String());
                $note('Verificare de sănătate: baza de date răspunde');
                Artisan::call('up');
                $note('Site-ul este din nou online');
            }

            $run->update(['status' => 'succeeded', 'finished_at' => now(), 'log' => implode("\n", $log)]);
        } catch (Throwable $e) {
            report($e);
            $note('EROARE: '.mb_substr($e->getMessage(), 0, 400));
            $this->rollbackFiles($base, $backupDir, array_merge($replaced, $removed), $created);
            $note('Fișierele anterioare au fost puse la loc ('.(count($replaced) + count($removed)).' restaurate, '.count($created).' șterse)');
            if ($migrated && $dbBackup) {
                // Structura: tabelele aparute in timpul migrarii esuate se sterg (fisierele lor de migrare au fost deja scoase),
                // apoi datele, inclusiv evidenta migrarilor, revin din backup. O migrare care a apucat sa modifice
                // un tabel existent nu poate fi anulata automat; de aceea migrarile din pachete trebuie sa fie aditive.
                try {
                    $new = array_diff(Tables::of(), $tablesBefore);
                    Schema::disableForeignKeyConstraints();
                    foreach ($new as $table) {
                        Schema::drop($table);
                    }
                    Schema::enableForeignKeyConstraints();
                    if ($new) {
                        $note('Tabele create de migrarea eșuată, șterse: '.implode(', ', $new));
                    }
                    $this->backups->restore($dbBackup['path']);
                    $note('Baza de date a fost readusă din backup');
                } catch (Throwable $restoreError) {
                    $note('ATENȚIE: restaurarea automată a bazei de date a eșuat ('.mb_substr($restoreError->getMessage(), 0, 160).'). Restaurează manual: php artisan hikevet:backup --restore='.basename($dbBackup['path']));
                }
            }
            if ($isLive) {
                try {
                    Artisan::call('optimize:clear');
                    Artisan::call('up');
                } catch (Throwable) {
                }
            }
            $run->update(['status' => 'rolled_back', 'finished_at' => now(), 'log' => implode("\n", $log)]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($this->lockFile());
        }

        return $run->refresh();
    }

    private function rollbackFiles(string $base, string $backupDir, array $replaced, array $created): void
    {
        foreach ($replaced as $relative) {
            if (is_file($backupDir.'/files/'.$relative)) {
                copy($backupDir.'/files/'.$relative, $base.'/'.$relative);
            }
        }
        foreach ($created as $relative) {
            @unlink($base.'/'.$relative);
            @unlink($base.'/'.$relative.'.hvtmp');
        }
        foreach ($replaced as $relative) {
            @unlink($base.'/'.$relative.'.hvtmp');
        }
    }

    private function lockFile(): string
    {
        return storage_path('app/update.lock');
    }
}
