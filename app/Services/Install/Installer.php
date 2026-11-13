<?php

namespace App\Services\Install;

use Illuminate\Support\Str;
use PDO;
use Throwable;

/**
 * Instalarea initiala pe cPanel, fara linie de comanda.
 *
 * Instalatorul este activ doar pana la crearea fisierului de blocare. Accesul cere un cod pe care
 * il poate citi doar cine are acces la fisierele contului (File Manager / FTP), astfel incat un
 * vizitator care gaseste /install inaintea operatorului nu poate prelua instalarea.
 */
class Installer
{
    public const REQUIRED_EXTENSIONS = ['pdo_mysql', 'mbstring', 'openssl', 'gd', 'zip', 'intl', 'fileinfo', 'dom', 'curl', 'sodium', 'ctype', 'json', 'tokenizer', 'xml', 'session', 'filter', 'hash'];

    public const MIN_PHP = '8.3.0';

    public function lockPath(): string
    {
        return config('hikevet.install_lock') ?: storage_path('app/installed.lock');
    }

    public function codePath(): string
    {
        return dirname($this->lockPath()).'/install-code.txt';
    }

    public function envPath(): string
    {
        return config('hikevet.install_env_path') ?: base_path('.env');
    }

    public function isInstalled(): bool
    {
        return (bool) config('hikevet.assume_installed') || is_file($this->lockPath());
    }

    /** Codul de acces: generat la prima vizita si scris intr-un fisier din afara webroot-ului. */
    public function accessCode(): string
    {
        $path = $this->codePath();
        if (! is_file($path)) {
            @mkdir(dirname($path), 0750, true);
            file_put_contents($path, Str::upper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4)));
            @chmod($path, 0600);
        }

        return trim((string) file_get_contents($path));
    }

    public function checkCode(?string $code): bool
    {
        return is_string($code) && $code !== '' && hash_equals($this->accessCode(), strtoupper(trim($code)));
    }

    /** @return list<array{label:string,ok:bool,detail:string,blocking:bool}> */
    public function requirements(bool $isHttps): array
    {
        $checks = [];
        $add = function (string $label, bool $ok, string $detail, bool $blocking = true) use (&$checks) {
            $checks[] = compact('label', 'ok', 'detail', 'blocking');
        };

        $add('PHP '.self::MIN_PHP.' sau mai nou', version_compare(PHP_VERSION, self::MIN_PHP, '>='), 'Versiune găsită: '.PHP_VERSION.'. Laravel 13 cere PHP 8.3+. Se schimbă din cPanel, „Select PHP Version”.');
        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $add('Extensia PHP '.$extension, extension_loaded($extension), extension_loaded($extension) ? 'activă' : 'Lipsește. Se activează din cPanel, „Select PHP Version” → Extensions.');
        }
        $add('GD cu suport WebP', function_exists('imagewebp'), 'Necesar pentru optimizarea imaginilor încărcate.', false);
        foreach (['storage', 'storage/app', 'storage/framework', 'storage/logs', 'bootstrap/cache'] as $dir) {
            $path = base_path($dir);
            $add('Drept de scriere: '.$dir, is_dir($path) && is_writable($path), is_writable($path) ? 'ok' : 'Setează permisiunile 755 (sau 775) din File Manager.');
        }
        $env = $this->envPath();
        $add('Fișierul .env poate fi scris', is_file($env) ? is_writable($env) : is_writable(dirname($env)), 'Instalatorul scrie aici datele bazei de date și cheia aplicației.');
        $add('Conexiune HTTPS', $isHttps, $isHttps ? 'ok' : 'Activează certificatul SSL (AutoSSL / Let’s Encrypt) înainte de instalare: parolele se trimit prin acest formular.',
            app()->isProduction() && ! in_array(request()->getHost(), ['127.0.0.1', 'localhost'], true));
        $add('Aplicația este în afara directorului public', ! $this->looksExposed(), 'Document root trebuie să fie directorul public/. Fișierele .env, vendor/ și storage/ nu au voie să fie accesibile din browser.', false);
        $add('Spațiu liber pe disc', ($free = @disk_free_space(base_path())) === false || $free > 200 * 1024 * 1024, $free === false ? 'nu poate fi citit' : round($free / 1048576).' MB liberi (minimum recomandat 200 MB).', false);

        return $checks;
    }

    /** Document root gresit: daca directorul curent al scriptului nu este public/, aplicatia este probabil expusa. */
    private function looksExposed(): bool
    {
        $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';

        return $script !== '' && ! app()->runningUnitTests() && php_sapi_name() !== 'cli-server' && dirname($script) !== realpath(public_path());
    }

    /** Incearca o conexiune reala la MySQL / MariaDB cu datele introduse. Returneaza null daca reuseste. */
    public function testDatabase(array $db): ?string
    {
        try {
            $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['database']), $db['username'], $db['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8]);
            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            if ((int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn() > 0 && empty($db['allow_non_empty'])) {
                return 'Baza de date nu este goală ('.$version.'). Folosește o bază nouă sau confirmă explicit că vrei să instalezi peste ea.';
            }

            return null;
        } catch (Throwable $e) {
            // Mesajul driverului poate contine numele utilizatorului, dar niciodata parola.
            return 'Conexiunea a eșuat: '.preg_replace('/\s+/', ' ', mb_substr($e->getMessage(), 0, 240));
        }
    }

    /** Scrie .env pentru productie. Valorile sunt puse intre ghilimele si curatate de caractere de control. */
    public function writeEnv(array $v): void
    {
        $q = fn ($value) => '"'.str_replace(['\\', '"', '$', "\n", "\r"], ['\\\\', '\\"', '\\$', '', ''], (string) $value).'"';
        $key = 'base64:'.base64_encode(random_bytes(32));

        $lines = [
            'APP_NAME=HiKeVet', 'APP_ENV=production', 'APP_KEY='.$key, 'APP_DEBUG=false', 'APP_URL='.$q(rtrim($v['app_url'], '/')),
            'APP_LOCALE=ro', 'APP_FALLBACK_LOCALE=ro', 'APP_FAKER_LOCALE=ro_RO', 'APP_TIMEZONE=Europe/Bucharest', '',
            'LOG_CHANNEL=daily', 'LOG_LEVEL=warning', '',
            'DB_CONNECTION=mysql', 'DB_HOST='.$q($v['db_host']), 'DB_PORT='.(int) $v['db_port'], 'DB_DATABASE='.$q($v['db_database']),
            'DB_USERNAME='.$q($v['db_username']), 'DB_PASSWORD='.$q($v['db_password']), '',
            'SESSION_DRIVER=database', 'SESSION_LIFETIME=120', 'SESSION_ENCRYPT=true', 'SESSION_SECURE_COOKIE=true', 'SESSION_SAME_SITE=lax',
            'CACHE_STORE=database', 'QUEUE_CONNECTION=database', 'FILESYSTEM_DISK=local', '',
            'MAIL_MAILER='.(($v['mail_host'] ?? '') !== '' ? 'smtp' : 'log'), 'MAIL_HOST='.$q($v['mail_host'] ?? ''), 'MAIL_PORT='.(int) ($v['mail_port'] ?? 465),
            'MAIL_USERNAME='.$q($v['mail_username'] ?? ''), 'MAIL_PASSWORD='.$q($v['mail_password'] ?? ''), 'MAIL_SCHEME='.(((int) ($v['mail_port'] ?? 465)) === 465 ? 'smtps' : 'smtp'),
            'MAIL_FROM_ADDRESS='.$q($v['mail_from'] ?? 'contact@hikevet.com'), 'MAIL_FROM_NAME=HiKeVet', '',
            '# Copia bazei WordPress pentru import (optional, vezi docs/IMPORT_RUNBOOK.md)', 'WP_DB_HOST=127.0.0.1', 'WP_DB_DATABASE=', 'WP_DB_USERNAME=', 'WP_DB_PASSWORD=', 'WP_UPLOADS_PATH=', '',
            '# Cheia publica pentru verificarea pachetelor de actualizare', 'HIKEVET_UPDATE_PUBLIC_KEY='.$q($v['update_public_key'] ?? config('hikevet.update_public_key')), '',
        ];

        $path = $this->envPath();
        file_put_contents($path, implode("\n", $lines), LOCK_EX);
        @chmod($path, 0600);
    }

    public function lock(string $adminEmail): void
    {
        file_put_contents($this->lockPath(), json_encode(['installed_at' => date('c'), 'version' => config('hikevet.version'), 'admin' => $adminEmail], JSON_PRETTY_PRINT), LOCK_EX);
        @unlink($this->codePath());
    }
}
