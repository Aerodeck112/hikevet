<?php

namespace App\Services\Updates;

use RuntimeException;
use ZipArchive;

/**
 * Pachet de actualizare: ZIP cu release.json (manifest), release.sig (semnatura Ed25519 a
 * manifestului) si directorul files/. Manifestul listeaza fiecare fisier cu amprenta SHA-256.
 *
 * Un ZIP este acceptat doar daca: semnatura manifestului este valida pentru cheia publica
 * configurata, fiecare fisier corespunde amprentei din manifest, nu exista intrari in afara
 * manifestului, caile sunt in directoarele permise si dimensiunile sunt in limite. Un ZIP cu
 * cod PHP oarecare, nesemnat, nu este niciodata tratat ca sursa de cod.
 */
class ReleasePackage
{
    public const ALLOWED_ROOTS = ['app/', 'bootstrap/app.php', 'bootstrap/providers.php', 'config/', 'database/migrations/', 'database/seeders/', 'database/import-spec/',
        'public/build/', 'public/media/', 'public/fonts/', 'public/js/', 'public/css/', 'public/index.php', 'public/.htaccess', 'public/favicon.svg', 'resources/', 'routes/', 'vendor/', 'artisan', 'composer.json', 'composer.lock'];

    public const MAX_FILES = 40000;

    public const MAX_UNCOMPRESSED = 600 * 1024 * 1024;

    public const MAX_RATIO = 200;

    /** @return array{manifest:array,files:array<string,string>} manifestul verificat si lista cale => intrare ZIP */
    public function verify(string $zipPath, string $publicKeyBase64): array
    {
        $publicKey = base64_decode($publicKeyBase64, true);
        if (! $publicKey || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('Cheia publică pentru actualizări nu este configurată (HIKEVET_UPDATE_PUBLIC_KEY). Fără ea niciun pachet nu poate fi verificat.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Fișierul nu este o arhivă ZIP validă.');
        }

        try {
            if ($zip->numFiles > self::MAX_FILES) {
                throw new RuntimeException('Arhiva conține prea multe fișiere.');
            }
            $manifestJson = $zip->getFromName('release.json');
            $signature = base64_decode((string) $zip->getFromName('release.sig'), true);
            if ($manifestJson === false || ! $signature || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
                throw new RuntimeException('Pachetul nu are manifest sau semnătură.');
            }
            if (! sodium_crypto_sign_verify_detached($signature, $manifestJson, $publicKey)) {
                throw new RuntimeException('Semnătura pachetului nu este validă. Pachetul nu provine de la sursa autorizată sau a fost modificat.');
            }

            $manifest = json_decode($manifestJson, true);
            if (! is_array($manifest) || ! isset($manifest['version'], $manifest['files']) || ! is_array($manifest['files'])) {
                throw new RuntimeException('Manifestul pachetului este invalid.');
            }
            // Pachet incremental: fisierele de sters trec prin aceleasi verificari de cale ca cele copiate.
            foreach ((array) ($manifest['remove'] ?? []) as $relative) {
                if (! is_string($relative) || isset($manifest['files'][$relative])) {
                    throw new RuntimeException('Manifestul pachetului este invalid.');
                }
                $this->assertSafePath($relative);
            }

            $entries = [];
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                if (in_array($name, ['release.json', 'release.sig'], true) || str_ends_with($name, '/')) {
                    continue;
                }
                if (! str_starts_with($name, 'files/')) {
                    throw new RuntimeException('Intrare neașteptată în arhivă: '.$name);
                }
                $relative = substr($name, 6);
                $this->assertSafePath($relative);
                if (! isset($manifest['files'][$relative])) {
                    throw new RuntimeException('Fișier în afara manifestului: '.$relative);
                }
                $total += $stat['size'];
                if ($total > self::MAX_UNCOMPRESSED || ($stat['comp_size'] > 0 && $stat['size'] / $stat['comp_size'] > self::MAX_RATIO && $stat['size'] > 1048576)) {
                    throw new RuntimeException('Arhiva depășește limitele de dimensiune (posibilă bombă ZIP).');
                }
                $entries[$relative] = $name;
            }

            foreach ($manifest['files'] as $relative => $sha) {
                if (! isset($entries[$relative])) {
                    throw new RuntimeException('Fișier din manifest lipsă în arhivă: '.$relative);
                }
                $stream = $zip->getStream($entries[$relative]);
                $ctx = hash_init('sha256');
                hash_update_stream($ctx, $stream);
                fclose($stream);
                if (! hash_equals((string) $sha, hash_final($ctx))) {
                    throw new RuntimeException('Amprenta fișierului nu corespunde manifestului: '.$relative);
                }
            }

            return ['manifest' => $manifest, 'files' => $entries];
        } finally {
            $zip->close();
        }
    }

    /** Refuza caile absolute, cu "..", cu caractere de control sau din afara directoarelor permise (Zip Slip). */
    public function assertSafePath(string $path): void
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, "\0") || preg_match('#(^|/)\.\.?(/|$)#', $path) || preg_match('/[\x00-\x1f]/', $path) || preg_match('#^[A-Za-z]:#', $path)) {
            throw new RuntimeException('Cale nepermisă în arhivă: '.$path);
        }
        if (preg_match('#(^|/)\.env|^storage/|^public/uploads/#', $path)) {
            throw new RuntimeException('Pachetul nu are voie să atingă datele sau configurarea: '.$path);
        }
        foreach (self::ALLOWED_ROOTS as $root) {
            if ($path === $root || (str_ends_with($root, '/') && str_starts_with($path, $root))) {
                return;
            }
        }

        throw new RuntimeException('Fișier în afara directoarelor permise: '.$path);
    }

    /**
     * Fisierele care alcatuiesc o versiune, cu amprenta lor. Aceeasi lista este folosita pentru pachetul de
     * instalare, pentru pachetele de actualizare si ca baza pentru pachetele incrementale.
     *
     * @return array<string,string> cale relativa => sha256
     */
    public function scan(string $basePath): array
    {
        $all = [];
        foreach (self::ALLOWED_ROOTS as $root) {
            $absolute = $basePath.'/'.rtrim($root, '/');
            $paths = is_file($absolute) ? [$absolute] : (is_dir($absolute)
                ? array_map(fn ($f) => $f->getPathname(), array_filter(iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS))), fn ($f) => $f->isFile()))
                : []);
            foreach ($paths as $path) {
                $relative = ltrim(str_replace('\\', '/', substr($path, strlen($basePath))), '/');
                // Istoricul Git, testele si documentatia dependentelor nu fac parte din pachet.
                if (preg_match('#(^|/)\.git(/|$)#', $relative)
                    || (str_starts_with($relative, 'vendor/') && preg_match('#/(tests?|Tests?|docs?|\.github)/|\.(md|MD)$#', $relative))
                    || str_ends_with($relative, '.hvtmp') || preg_match('#^bootstrap/cache/#', $relative)) {
                    continue;
                }
                $all[$relative] = hash_file('sha256', $path);
            }
        }
        ksort($all);

        return $all;
    }

    /**
     * Construieste si semneaza un pachet din arborele curent. Folosit de cine publica versiunile.
     *
     * @param  array{version:string,files:array<string,string>}|null  $base  manifestul versiunii instalate; cu el pachetul contine
     *                                                                       doar fisierele schimbate si lista celor disparute
     */
    public function build(string $basePath, string $target, string $version, string $secretKeyBase64, string $changelog = '', ?string $requiresFrom = null, ?array $base = null): array
    {
        $secret = base64_decode($secretKeyBase64, true);
        if (! $secret || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException('Cheia privată de semnare nu este validă.');
        }

        $zip = new ZipArchive;
        $zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $files = [];
        $all = $this->scan($basePath);
        foreach ($all as $relative => $sha) {
            if ($base === null || ($base['files'][$relative] ?? null) !== $sha) {
                $files[$relative] = $sha;
                $zip->addFile($basePath.'/'.$relative, 'files/'.$relative);
            }
        }
        ksort($files);
        $remove = $base === null ? [] : array_values(array_diff(array_keys($base['files']), array_keys($all)));

        $manifest = json_encode([
            'version' => $version, 'built_at' => gmdate('c'), 'min_php' => '8.3.0', 'requires_from' => $requiresFrom,
            'has_migrations' => (bool) array_filter(array_keys($files), fn ($f) => str_starts_with($f, 'database/migrations/')),
            'changelog' => $changelog, 'base_version' => $base['version'] ?? null, 'remove' => $remove, 'files' => $files,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $zip->addFromString('release.json', $manifest);
        $zip->addFromString('release.sig', base64_encode(sodium_crypto_sign_detached($manifest, $secret)));
        $zip->close();

        return ['path' => $target, 'files' => count($files), 'removed' => count($remove), 'bytes' => filesize($target), 'sha256' => hash_file('sha256', $target),
            'tree' => ['version' => $version, 'files' => $all]];
    }
}
