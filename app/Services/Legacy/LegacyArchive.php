<?php

namespace App\Services\Legacy;

use App\Models\LegacyFile;
use App\Models\LegacyRow;
use App\Models\LegacyTable;
use App\Services\Import\WordPressSource;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Arhiva bruta a site-ului WordPress, pastrata in platforma noua dupa oprirea lui.
 *
 * - Fiecare tabela din baza sursa este copiata rand cu rand. Randurile sunt criptate (APP_KEY), cu amprenta
 *   SHA-256 a continutului; valorile care nu sunt text UTF-8 valid se pastreaza in base64, deci nimic nu se pierde.
 * - Fisierul SQL original se pastreaza criptat, cu amprenta, si poate fi descarcat de cine are dreptul.
 * Arhiva nu inlocuieste importul in ecranele noi; este copia de referinta pentru orice nu are ecran propriu.
 */
class LegacyArchive
{
    private const BATCH = 500;

    private const BATCH_BYTES = 2_000_000;

    /** @return array{tables:int,rows:int} */
    public function snapshot(WordPressSource $source, ?int $batchId = null, ?callable $progress = null, array $skip = []): array
    {
        $stats = ['tables' => 0, 'rows' => 0];
        $db = $source->db();
        $schema = Schema::connection($source->connectionName());

        foreach ($source->tables() as $name) {
            if (in_array($name, $skip, true)) {
                continue;
            }
            $columns = $schema->getColumnListing($name);
            $primary = collect($schema->getIndexes($name))->firstWhere('primary', true);
            $pk = $primary && count($primary['columns']) === 1 ? $primary['columns'][0] : null;
            $order = $pk ?? $columns[0];

            DB::transaction(function () use ($db, $name, $columns, $pk, $order, $batchId, &$stats, $progress) {
                $existing = LegacyTable::query()->where('name', $name)->first();
                if ($existing) {
                    // O noua copie inlocuieste integral copia anterioara a aceleiasi tabele.
                    DB::table('legacy_rows')->where('legacy_table_id', $existing->id)->delete();
                }
                $table = LegacyTable::query()->updateOrCreate(['name' => $name], ['columns' => $columns, 'primary_key' => $pk, 'import_batch_id' => $batchId]);

                $ordinal = 0;
                $hashes = hash_init('sha256');
                $buffer = [];
                $bytes = 0;
                foreach ($db->table($name)->orderBy($order)->cursor() as $row) {
                    $values = array_map(fn ($v) => is_string($v) && ! mb_check_encoding($v, 'UTF-8') ? ['__base64' => base64_encode($v)] : $v, (array) $row);
                    $json = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                    $sha = hash('sha256', $json);
                    hash_update($hashes, $sha."\n");
                    $buffer[] = ['legacy_table_id' => $table->id, 'ordinal' => ++$ordinal, 'pk' => $pk !== null ? mb_substr((string) $row->{$pk}, 0, 191) : null,
                        'data' => $encrypted = Crypt::encryptString($json), 'sha256' => $sha];
                    $bytes += strlen($encrypted);
                    // Loturi limitate si ca dimensiune: pe hosting shared max_allowed_packet poate fi mic.
                    if (count($buffer) >= self::BATCH || $bytes >= self::BATCH_BYTES) {
                        DB::table('legacy_rows')->insert($buffer);
                        $buffer = [];
                        $bytes = 0;
                    }
                }
                if ($buffer) {
                    DB::table('legacy_rows')->insert($buffer);
                }
                $table->update(['row_count' => $ordinal, 'sha256' => hash_final($hashes), 'snapshotted_at' => now()]);
                $stats['tables']++;
                $stats['rows'] += $ordinal;
                if ($progress) {
                    $progress($name, $ordinal);
                }
            });
        }

        return $stats;
    }

    /** @return array<string,mixed> */
    public function decodeRow(string $encrypted): array
    {
        $values = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);

        return array_map(fn ($v) => is_array($v) && array_key_exists('__base64', $v) ? base64_decode($v['__base64']) : $v, $values);
    }

    /** Verifica fiecare rand al unei tabele fata de amprenta lui. @return list<int> ordinalele cu probleme */
    public function verify(LegacyTable $table): array
    {
        $bad = [];
        foreach (LegacyRow::query()->where('legacy_table_id', $table->id)->orderBy('ordinal')->lazyById(500) as $row) {
            try {
                if (hash('sha256', Crypt::decryptString($row->data)) !== $row->sha256) {
                    $bad[] = $row->ordinal;
                }
            } catch (\Throwable) {
                $bad[] = $row->ordinal;
            }
        }

        return $bad;
    }

    /**
     * Cautare text intr-o tabela. Randurile sunt criptate, deci se decripteaza pe rand, in ordine,
     * pana la $limit rezultate sau $maxScan randuri; $next spune de unde se continua.
     *
     * @return array{matches:list<array{ordinal:int,pk:?string,values:array}>,next:?int,scanned:int}
     */
    public function search(LegacyTable $table, string $term, int $fromOrdinal = 0, int $limit = 25, int $maxScan = 20000): array
    {
        $needle = mb_strtolower(trim($term));
        $matches = [];
        $scanned = 0;
        $last = $fromOrdinal;
        foreach (LegacyRow::query()->where('legacy_table_id', $table->id)->where('ordinal', '>', $fromOrdinal)->orderBy('ordinal')->cursor() as $row) {
            $scanned++;
            $last = $row->ordinal;
            $values = $this->decodeRow($row->data);
            if ($needle === '' || str_contains(mb_strtolower(json_encode($values, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)), $needle)) {
                $matches[] = ['ordinal' => $row->ordinal, 'pk' => $row->pk, 'values' => $values];
                if (count($matches) >= $limit) {
                    break;
                }
            }
            if ($scanned >= $maxScan) {
                break;
            }
        }

        return ['matches' => $matches, 'next' => $last < $table->row_count ? $last : null, 'scanned' => $scanned];
    }

    /** Scrie toata tabela ca CSV (decriptat) in fluxul dat. */
    public function writeCsv(LegacyTable $table, $stream): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $table->columns, ';', '"', '');
        foreach (LegacyRow::query()->where('legacy_table_id', $table->id)->orderBy('ordinal')->lazyById(500) as $row) {
            $values = $this->decodeRow($row->data);
            fputcsv($stream, array_map(fn ($c) => is_scalar($values[$c] ?? null) || ($values[$c] ?? null) === null ? (string) ($values[$c] ?? '') : json_encode($values[$c]), $table->columns), ';', '"', '');
        }
    }

    /** Pastreaza un fisier (de ex. database.sql original) criptat pe discul privat, cu amprenta continutului. */
    public function storeFile(string $path, string $kind, string $label, ?int $userId = null): LegacyFile
    {
        if (! is_file($path)) {
            throw new RuntimeException('Fișierul nu există: '.$path);
        }
        $sha = hash_file('sha256', $path);
        $relative = 'legacy/'.$kind.'-'.substr($sha, 0, 16).'.hvenc';
        $target = Storage::disk('private')->path($relative);
        @mkdir(dirname($target), 0750, true);

        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->fileKey());
        $in = fopen($path, 'rb');
        $out = fopen($target, 'wb');
        fwrite($out, "HVLG1\n".$header);
        while (! feof($in)) {
            $chunk = (string) fread($in, 1048576);
            $tag = feof($in) ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
            fwrite($out, sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag));
        }
        fclose($in);
        fclose($out);

        // Verificare prin recitire: amprenta continutului decriptat trebuie sa fie cea a originalului.
        $check = hash_init('sha256');
        $this->decryptFile($relative, fn (string $chunk) => hash_update($check, $chunk));
        if (hash_final($check) !== $sha) {
            Storage::disk('private')->delete($relative);
            throw new RuntimeException('Copia criptată nu corespunde originalului.');
        }

        // Acelasi continut poate veni din mai multe surse (de ex. contracte de test identice): etichetele se cumuleaza.
        $existing = LegacyFile::query()->where('sha256', $sha)->where('kind', $kind)->first();
        if ($existing && ! str_contains($existing->label, $label)) {
            $label = mb_substr($existing->label.'; '.$label, 0, 4000);
        } elseif ($existing) {
            $label = $existing->label;
        }

        return LegacyFile::query()->updateOrCreate(['sha256' => $sha, 'kind' => $kind],
            ['label' => $label, 'path' => $relative, 'bytes' => filesize($path), 'created_by' => $userId]);
    }

    /** Decripteaza in bucati, pentru descarcare sau verificare. Orice alterare opreste citirea. */
    public function decryptFile(string $relative, callable $onChunk): void
    {
        $in = fopen(Storage::disk('private')->path($relative), 'rb');
        if (! $in || fread($in, 6) !== "HVLG1\n") {
            throw new RuntimeException('Fișier de arhivă invalid.');
        }
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES), $this->fileKey());
        $final = false;
        $size = 1048576 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;
        while (! feof($in) && ($chunk = fread($in, $size)) !== '' && $chunk !== false) {
            $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);
            if ($result === false) {
                throw new RuntimeException('Fișierul de arhivă este corupt sau a fost criptat cu altă cheie (APP_KEY).');
            }
            $onChunk($result[0]);
            $final = $result[1] === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
        }
        fclose($in);
        if (! $final) {
            throw new RuntimeException('Fișierul de arhivă este incomplet.');
        }
    }

    private function fileKey(): string
    {
        $appKey = (string) config('app.key');
        if ($appKey === '') {
            throw new RuntimeException('APP_KEY lipsește.');
        }
        $raw = str_starts_with($appKey, 'base64:') ? base64_decode(substr($appKey, 7)) : $appKey;

        return sodium_crypto_generichash('hikevet-legacy-v1'.$raw, '', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);
    }
}
