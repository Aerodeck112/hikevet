<?php

namespace App\Services\Import;

use RuntimeException;

/**
 * Citeste backupurile All-in-One WP Migration (.wpress) fara WordPress si fara plugin.
 *
 * Formatul (din sursa pluginului, versiunea 7.x): fiecare fisier are un antet de 4377 de octeti
 *   nume 255 | dimensiune 14 | mtime 12 | cale 4088 | crc32 8 (hex, optional)
 * urmat de continut. Arhiva se termina cu un bloc de sfarsit (gol, sau cu dimensiunea si CRC-ul arhivei).
 * Daca package.json declara compresie, continutul fiecarui fisier (in afara de package.json si
 * multisite.json) este o serie de bucati: 4 octeti lungime (big-endian) + date gzcompress/bzcompress.
 * Arhivele protejate cu parola sunt refuzate: parola nu se cere si nu se transmite prin aceasta unealta.
 */
class WpressArchive
{
    public const HEADER_SIZE = 4377;

    private const CONFIG_FILES = ['package.json', 'multisite.json'];

    /** @var resource */
    private $handle;

    private ?array $package = null;

    public function __construct(private readonly string $path)
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Fișierul nu există sau nu poate fi citit: '.$path);
        }
        $this->handle = fopen($path, 'rb');
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /**
     * Parcurge antetele fara a citi continutul.
     *
     * @return \Generator<array{name:string,path:string,size:int,mtime:int,crc:?string,offset:int}>
     */
    public function entries(): \Generator
    {
        fseek($this->handle, 0);
        while (true) {
            $block = fread($this->handle, self::HEADER_SIZE);
            if ($block === false || $block === '') {
                throw new RuntimeException('Arhiva se termină brusc, fără blocul de sfârșit. Fișierul este probabil descărcat incomplet.');
            }
            if (strlen($block) !== self::HEADER_SIZE) {
                throw new RuntimeException('Antet incomplet la poziția '.(ftell($this->handle) - strlen($block)).'. Fișierul este probabil descărcat incomplet.');
            }
            $h = unpack('a255name/a14size/a12mtime/a4088path/a8crc', $block);
            $name = rtrim($h['name'], "\0");
            if ($name === '') {
                return; // bloc de sfarsit (v1 gol sau v2 cu dimensiune + CRC arhiva)
            }
            $size = (int) rtrim($h['size'], "\0");
            $prefix = rtrim($h['path'], "\0");
            $crc = rtrim($h['crc'], "\0");
            $entry = [
                'name' => $name,
                'path' => ($prefix === '' || $prefix === '.') ? $name : $prefix.'/'.$name,
                'size' => $size,
                'mtime' => (int) rtrim($h['mtime'], "\0"),
                'crc' => preg_match('/^[0-9a-f]{8}$/i', $crc) ? strtolower($crc) : null,
                'offset' => ftell($this->handle),
            ];
            yield $entry;
            fseek($this->handle, $entry['offset'] + $size);
        }
    }

    /** Continutul package.json (versiunea WordPress, prefixul, compresia, criptarea). */
    public function package(): array
    {
        if ($this->package !== null) {
            return $this->package;
        }
        foreach ($this->entries() as $entry) {
            if ($entry['path'] === 'package.json') {
                fseek($this->handle, $entry['offset']);
                $json = json_decode((string) fread($this->handle, $entry['size']), true);

                return $this->package = is_array($json) ? $json : [];
            }
        }

        return $this->package = [];
    }

    public function compression(): ?string
    {
        $p = $this->package();

        return ! empty($p['Compression']['Enabled']) ? (string) ($p['Compression']['Type'] ?? 'gzip') : null;
    }

    public function isEncrypted(): bool
    {
        return ! empty($this->package()['Encrypted']);
    }

    /**
     * Extrage fisierele care trec filtrul. Caile sunt verificate: nimic nu se scrie in afara directorului tinta.
     *
     * @param  callable(string):bool|null  $filter
     * @return array{files:int,bytes:int,crc_checked:int,crc_mismatch:list<string>,skipped:int}
     */
    public function extract(string $target, ?callable $filter = null, ?callable $progress = null): array
    {
        if ($this->isEncrypted()) {
            throw new RuntimeException('Backupul este protejat cu parolă. Fă un export nou fără parolă (Advanced options) și păstrează-l pe un canal privat.');
        }
        $compression = $this->compression();
        if ($compression && ! in_array($compression, ['gzip', 'bzip2'], true)) {
            throw new RuntimeException('Tip de compresie necunoscut: '.$compression);
        }
        if ($compression === 'bzip2' && ! function_exists('bzdecompress')) {
            throw new RuntimeException('Backupul folosește compresie bzip2, iar extensia PHP bz2 lipsește.');
        }

        @mkdir($target, 0750, true);
        $root = realpath($target);
        $stats = ['files' => 0, 'bytes' => 0, 'crc_checked' => 0, 'crc_mismatch' => [], 'skipped' => 0];

        foreach ($this->entries() as $entry) {
            $relative = $this->safeRelative($entry['path']);
            if ($filter && ! $filter($relative)) {
                $stats['skipped']++;

                continue;
            }
            $destination = $root.'/'.$relative;
            @mkdir(dirname($destination), 0750, true);
            if (! str_starts_with(realpath(dirname($destination)) ?: '', $root)) {
                throw new RuntimeException('Cale nepermisă în arhivă: '.$entry['path']);
            }

            fseek($this->handle, $entry['offset']);
            $out = fopen($destination, 'wb');
            $hash = hash_init('crc32b');
            $remaining = $entry['size'];
            $chunked = $compression && ! in_array($entry['name'], self::CONFIG_FILES, true);
            while ($remaining > 0) {
                if ($chunked) {
                    $len = unpack('Nsize', (string) fread($this->handle, 4))['size'] ?? 0;
                    $remaining -= 4;
                    $raw = $len > 0 ? (string) fread($this->handle, $len) : '';
                    $remaining -= $len;
                    $data = $compression === 'gzip' ? @gzuncompress($raw) : @bzdecompress($raw);
                    if (! is_string($data)) {
                        fclose($out);
                        throw new RuntimeException('Bucată comprimată invalidă în '.$entry['path']);
                    }
                } else {
                    $data = (string) fread($this->handle, min(1048576, $remaining));
                    $remaining -= strlen($data);
                    if ($data === '') {
                        fclose($out);
                        throw new RuntimeException('Arhiva se termină în mijlocul fișierului '.$entry['path']);
                    }
                }
                hash_update($hash, $data);
                fwrite($out, $data);
                $stats['bytes'] += strlen($data);
            }
            fclose($out);
            if ($entry['mtime'] > 0) {
                @touch($destination, $entry['mtime']);
            }
            if ($entry['crc']) {
                $stats['crc_checked']++;
                if (hash_final($hash) !== $entry['crc']) {
                    $stats['crc_mismatch'][] = $relative;
                }
            }
            $stats['files']++;
            if ($progress) {
                $progress($stats, $entry);
            }
        }

        return $stats;
    }

    /** Respinge caile absolute, cu "..", cu caractere de control. */
    private function safeRelative(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_starts_with($path, '/') || preg_match('#(^|/)\.\.(/|$)#', $path) || preg_match('/[\x00-\x1f]/', $path) || preg_match('#^[A-Za-z]:#', $path)) {
            throw new RuntimeException('Cale nepermisă în arhivă: '.$path);
        }

        return ltrim(preg_replace('#/+#', '/', $path), './');
    }
}
