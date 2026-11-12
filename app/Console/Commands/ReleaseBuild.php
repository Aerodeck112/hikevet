<?php

namespace App\Console\Commands;

use App\Services\Updates\ReleasePackage;
use Illuminate\Console\Command;

class ReleaseBuild extends Command
{
    protected $signature = 'hikevet:release
        {action : keygen, build sau install-zip}
        {--release-version= : Versiunea pachetului (implicit cea din config/hikevet.php)}
        {--key-file= : Fișierul cu cheia privată de semnare (base64)}
        {--changelog= : Text scurt cu modificările}
        {--out= : Fișierul ZIP rezultat}';

    protected $description = 'Unelte pentru publicarea versiunilor: generare chei de semnare, pachet de actualizare semnat, pachet de instalare cPanel.';

    public function handle(ReleasePackage $package): int
    {
        $version = $this->option('release-version') ?: config('hikevet.version');

        return match ($this->argument('action')) {
            'keygen' => $this->keygen(),
            'build' => $this->build($package, $version),
            'install-zip' => $this->installZip($package, $version),
            default => self::FAILURE,
        };
    }

    private function keygen(): int
    {
        $file = $this->option('key-file') ?: storage_path('app/release-signing.key');
        if (is_file($file)) {
            $this->error('Fișierul de cheie există deja și nu este suprascris: '.$file);

            return self::FAILURE;
        }
        $pair = sodium_crypto_sign_keypair();
        file_put_contents($file, base64_encode(sodium_crypto_sign_secretkey($pair)));
        chmod($file, 0600);
        $this->info('Cheia privată a fost scrisă în '.$file.'. Păstreaz-o offline; nu o urca în Git și nu o pune pe server.');
        $this->line('Cheia publică, de pus în .env pe server:');
        $this->line('HIKEVET_UPDATE_PUBLIC_KEY='.base64_encode(sodium_crypto_sign_publickey($pair)));

        return self::SUCCESS;
    }

    private function build(ReleasePackage $package, string $version): int
    {
        $keyFile = $this->option('key-file') ?: storage_path('app/release-signing.key');
        if (! is_file($keyFile)) {
            $this->error('Lipsește cheia privată de semnare. Rulează întâi: php artisan hikevet:release keygen');

            return self::FAILURE;
        }
        $out = $this->option('out') ?: storage_path('app/releases/hikevet-update-'.$version.'.zip');
        @mkdir(dirname($out), 0750, true);
        $result = $package->build(base_path(), $out, $version, trim(file_get_contents($keyFile)), (string) $this->option('changelog'));
        $this->info(sprintf('Pachet de actualizare %s: %d fișiere, %.1f MB', basename($out), $result['files'], $result['bytes'] / 1048576));
        $this->line('SHA-256: '.$result['sha256']);

        return self::SUCCESS;
    }

    /** Pachet complet pentru prima instalare: cod, vendor si asseturi precompilate, fara .env, fara date. */
    private function installZip(ReleasePackage $package, string $version): int
    {
        $out = $this->option('out') ?: storage_path('app/releases/hikevet-install-'.$version.'.zip');
        @mkdir(dirname($out), 0750, true);
        $zip = new \ZipArchive;
        $zip->open($out, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $count = 0;
        $base = base_path();

        foreach (ReleasePackage::ALLOWED_ROOTS as $root) {
            $absolute = $base.'/'.rtrim($root, '/');
            $paths = is_file($absolute) ? [$absolute] : (is_dir($absolute)
                ? array_map(fn ($f) => $f->getPathname(), array_filter(iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS))), fn ($f) => $f->isFile()))
                : []);
            foreach ($paths as $path) {
                $relative = ltrim(substr($path, strlen($base)), '/');
                if (preg_match('#(^|/)\.git(/|$)#', $relative) || (str_starts_with($relative, 'vendor/') && preg_match('#/(tests?|Tests?|docs?|\.github)/#', $relative))) {
                    continue;
                }
                $zip->addFile($path, 'hikevet/'.$relative);
                $count++;
            }
        }
        foreach (['storage/app/private', 'storage/app/backups', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache', 'public/uploads'] as $dir) {
            $zip->addFromString('hikevet/'.$dir.'/.gitkeep', '');
        }
        // .env minim: productie, fara cheie si fara date; instalatorul il rescrie.
        $zip->addFromString('hikevet/.env', "APP_ENV=production\nAPP_DEBUG=false\nAPP_KEY=\nAPP_URL=\nDB_CONNECTION=mysql\nSESSION_DRIVER=file\nCACHE_STORE=file\nQUEUE_CONNECTION=sync\nLOG_CHANNEL=daily\n"
            .'HIKEVET_UPDATE_PUBLIC_KEY='.config('hikevet.update_public_key')."\n");
        foreach (['INSTALL_CPANEL.md', 'UPDATES.md', 'SECURITY.md', 'CHANGELOG.md'] as $doc) {
            if (is_file($base.'/'.$doc)) {
                $zip->addFile($base.'/'.$doc, 'hikevet/'.$doc);
            }
        }
        $zip->close();
        $this->info(sprintf('Pachet de instalare %s: %d fișiere, %.1f MB', basename($out), $count, filesize($out) / 1048576));
        $this->line('SHA-256: '.hash_file('sha256', $out));

        return self::SUCCESS;
    }
}
