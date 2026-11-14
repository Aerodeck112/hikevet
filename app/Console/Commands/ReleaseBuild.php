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
        {--since= : Manifestul versiunii instalate (.manifest.json); pachetul va conține doar ce s-a schimbat}
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
        $base = null;
        if ($since = $this->option('since')) {
            $base = is_file($since) ? json_decode(file_get_contents($since), true) : null;
            if (! is_array($base) || ! is_string($base['version'] ?? null) || ! is_array($base['files'] ?? null)) {
                $this->error('Manifestul de bază nu poate fi citit: '.$since);

                return self::FAILURE;
            }
            if (! version_compare($version, $base['version'], '>')) {
                $this->error('Versiunea nouă ('.$version.') trebuie să fie mai mare decât cea de bază ('.$base['version'].'). Actualizează config/hikevet.php.');

                return self::FAILURE;
            }
        }
        $result = $package->build(base_path(), $out, $version, trim(file_get_contents($keyFile)), (string) $this->option('changelog'), null, $base);
        file_put_contents($this->manifestPath($out), json_encode($result['tree'], JSON_UNESCAPED_SLASHES));
        $this->info(sprintf('Pachet de actualizare %s%s: %d fișiere, %d de șters, %.1f MB', basename($out), $base ? ' (incremental față de '.$base['version'].')' : ' (complet)',
            $result['files'], $result['removed'], $result['bytes'] / 1048576));
        $this->line('SHA-256: '.$result['sha256']);
        $this->line('Manifestul versiunii (bază pentru următorul pachet incremental): '.$this->manifestPath($out));

        return self::SUCCESS;
    }

    /** Pachet complet pentru prima instalare: cod, vendor si asseturi precompilate, fara .env, fara date. */
    private function installZip(ReleasePackage $package, string $version): int
    {
        $out = $this->option('out') ?: storage_path('app/releases/hikevet-install-'.$version.'.zip');
        @mkdir(dirname($out), 0750, true);
        $zip = new \ZipArchive;
        $zip->open($out, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $base = base_path();
        $tree = $package->scan($base);
        foreach ($tree as $relative => $sha) {
            $zip->addFile($base.'/'.$relative, 'hikevet/'.$relative);
        }
        $count = count($tree);
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
        file_put_contents($this->manifestPath($out), json_encode(['version' => $version, 'files' => $tree], JSON_UNESCAPED_SLASHES));
        $this->info(sprintf('Pachet de instalare %s: %d fișiere, %.1f MB', basename($out), $count, filesize($out) / 1048576));
        $this->line('SHA-256: '.hash_file('sha256', $out));
        $this->line('Manifestul versiunii (bază pentru pachetele incrementale): '.$this->manifestPath($out));

        return self::SUCCESS;
    }

    private function manifestPath(string $zip): string
    {
        return preg_replace('/\.zip$/', '', $zip).'.manifest.json';
    }
}
