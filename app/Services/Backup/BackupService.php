<?php

namespace App\Services\Backup;

use App\Support\Tables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Backup si restaurare fara utilitare de sistem (mysqldump nu exista mereu pe hosting shared).
 *
 * Baza de date se exporta tabel cu tabel in JSON Lines, apoi fisierul este comprimat si criptat
 * (XChaCha20-Poly1305, cheie derivata din APP_KEY). Backupurile stau in storage/app/backups,
 * in afara webroot-ului. Restaurarea reincarca datele in schema curenta, deci migrarile trebuie
 * sa fie aditive (fara coloane sterse) pentru ca un backup mai vechi sa poata fi readus.
 */
class BackupService
{
    public const SKIP_TABLES = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'migrations'];

    public function directory(): string
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        return $dir;
    }

    /** @return array{path:string,tables:int,rows:int,bytes:int,sha256:string} */
    public function create(string $label = 'manual'): array
    {
        $tables = collect($this->tablesInDependencyOrder());
        $plain = tempnam(sys_get_temp_dir(), 'hvbk');
        $out = gzopen($plain, 'wb6');
        $rows = 0;

        gzwrite($out, json_encode(['meta' => ['version' => config('hikevet.version'), 'created_at' => now()->toIso8601String(), 'driver' => DB::getDriverName(),
            'migrations' => DB::table('migrations')->orderBy('id')->pluck('migration')->all()]])."\n");
        foreach ($tables as $table) {
            $columns = Schema::getColumnListing($table);
            $order = in_array('id', $columns, true) ? 'id' : $columns[0];
            gzwrite($out, json_encode(['table' => $table, 'columns' => $columns])."\n");
            foreach (DB::table($table)->orderBy($order)->cursor() as $row) {
                gzwrite($out, json_encode(['r' => array_values((array) $row)], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)."\n");
                $rows++;
            }
        }
        gzclose($out);

        $path = $this->directory().'/'.now()->format('Ymd-His').'-'.preg_replace('/[^a-z0-9\-]/', '', strtolower($label)).'.hvbackup';
        $this->encryptFile($plain, $path);
        unlink($plain);
        @chmod($path, 0600);

        return ['path' => $path, 'tables' => $tables->count(), 'rows' => $rows, 'bytes' => filesize($path), 'sha256' => hash_file('sha256', $path)];
    }

    /** Verifica faptul ca un backup poate fi decriptat si citit integral, fara a scrie in baza de date. */
    public function verify(string $path): array
    {
        $tables = 0;
        $rows = 0;
        $meta = null;
        $this->read($path, function (array $line) use (&$tables, &$rows, &$meta) {
            isset($line['meta']) ? $meta = $line['meta'] : (isset($line['table']) ? $tables++ : $rows++);
        });

        return ['meta' => $meta, 'tables' => $tables, 'rows' => $rows];
    }

    /** Inlocuieste datele curente cu cele din backup, intr-o singura tranzactie. */
    public function restore(string $path): array
    {
        $stats = ['tables' => 0, 'rows' => 0];
        $existing = array_flip(Tables::of());

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($path, &$stats, $existing) {
                // Trecerea 1: lista tabelelor din backup. Backupul le are in ordinea dependentelor (parinti intai),
                // deci golirea se face in ordine inversa si reincarcarea in ordinea din fisier.
                $inBackup = [];
                $this->read($path, function (array $line) use (&$inBackup, $existing) {
                    if (isset($line['table'], $existing[$line['table']])) {
                        $inBackup[] = $line['table'];
                    }
                });
                foreach (array_reverse($inBackup) as $name) {
                    DB::table($name)->delete();
                }

                $table = null;
                $columns = [];
                $buffer = [];
                $flush = function () use (&$buffer, &$table) {
                    if ($buffer && $table) {
                        DB::table($table)->insert($buffer);
                    }
                    $buffer = [];
                };

                $this->read($path, function (array $line) use (&$table, &$columns, &$buffer, &$stats, $flush, $existing) {
                    if (isset($line['table'])) {
                        $flush();
                        $table = isset($existing[$line['table']]) ? $line['table'] : null;
                        if ($table) {
                            $present = array_flip(Schema::getColumnListing($table));
                            $columns = array_map(fn ($c) => isset($present[$c]) ? $c : null, $line['columns']);
                            $stats['tables']++;
                        }
                    } elseif (isset($line['r']) && $table) {
                        $row = [];
                        foreach ($columns as $i => $column) {
                            if ($column !== null) {
                                $row[$column] = $line['r'][$i] ?? null;
                            }
                        }
                        $buffer[] = $row;
                        $stats['rows']++;
                        if (count($buffer) >= 200) {
                            $flush();
                        }
                    }
                });
                $flush();
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $stats;
    }

    /** Tabelele ordonate astfel incat fiecare tabel sa apara dupa cele la care trimite prin chei straine. @return list<string> */
    public function tablesInDependencyOrder(): array
    {
        $tables = array_values(array_filter(Tables::of(), fn ($t) => ! in_array($t, self::SKIP_TABLES, true)));
        $deps = [];
        foreach ($tables as $table) {
            $deps[$table] = array_values(array_unique(array_filter(array_map(fn ($fk) => $fk['foreign_table'], Schema::getForeignKeys($table)),
                fn ($parent) => $parent !== $table && in_array($parent, $tables, true))));
        }

        $ordered = [];
        $visit = function (string $table, array $path = []) use (&$visit, &$ordered, $deps) {
            if (in_array($table, $ordered, true) || in_array($table, $path, true)) {
                return;
            }
            foreach ($deps[$table] as $parent) {
                $visit($parent, [...$path, $table]);
            }
            $ordered[] = $table;
        };
        foreach ($tables as $table) {
            $visit($table);
        }

        return $ordered;
    }

    /** Pastreaza cele mai noi $keep backupuri si le sterge pe celelalte. */
    public function prune(int $keep = 14): int
    {
        $files = glob($this->directory().'/*.hvbackup') ?: [];
        rsort($files);
        $deleted = 0;
        foreach (array_slice($files, $keep) as $file) {
            $deleted += (int) @unlink($file);
        }

        return $deleted;
    }

    /** @return list<array{name:string,bytes:int,created:int}> */
    public function list(): array
    {
        $files = glob($this->directory().'/*.hvbackup') ?: [];
        rsort($files);

        return array_map(fn ($f) => ['name' => basename($f), 'bytes' => filesize($f), 'created' => filemtime($f)], $files);
    }

    private function key(): string
    {
        $appKey = (string) config('app.key');
        if ($appKey === '') {
            throw new RuntimeException('APP_KEY lipsește: backupul nu poate fi criptat.');
        }
        $raw = str_starts_with($appKey, 'base64:') ? base64_decode(substr($appKey, 7)) : $appKey;

        return sodium_crypto_generichash('hikevet-backup-v1'.$raw, '', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);
    }

    private function encryptFile(string $source, string $target): void
    {
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->key());
        $in = fopen($source, 'rb');
        $out = fopen($target, 'wb');
        fwrite($out, "HVBK1\n".$header);
        while (! feof($in)) {
            $chunk = fread($in, 65536);
            $tag = feof($in) ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
            fwrite($out, sodium_crypto_secretstream_xchacha20poly1305_push($state, (string) $chunk, '', $tag));
        }
        fclose($in);
        fclose($out);
    }

    /** Decripteaza, decomprima si livreaza linie cu linie catre $onLine. Orice alterare a fisierului opreste citirea. */
    private function read(string $path, callable $onLine): void
    {
        $in = fopen($path, 'rb');
        if (! $in || fread($in, 6) !== "HVBK1\n") {
            throw new RuntimeException('Fișierul nu este un backup HiKeVet valid.');
        }
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES), $this->key());
        $plain = tempnam(sys_get_temp_dir(), 'hvrs');
        $out = fopen($plain, 'wb');
        $final = false;
        $size = 65536 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;
        while (! feof($in) && ($chunk = fread($in, $size)) !== '' && $chunk !== false) {
            $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);
            if ($result === false) {
                fclose($out);
                unlink($plain);
                throw new RuntimeException('Backupul este corupt sau a fost criptat cu altă cheie (APP_KEY).');
            }
            fwrite($out, $result[0]);
            $final = $result[1] === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
        }
        fclose($in);
        fclose($out);
        if (! $final) {
            unlink($plain);
            throw new RuntimeException('Backupul este incomplet.');
        }

        $gz = gzopen($plain, 'rb');
        while (($line = gzgets($gz)) !== false) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $onLine($decoded);
            }
        }
        gzclose($gz);
        unlink($plain);
    }
}
