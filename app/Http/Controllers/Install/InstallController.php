<?php

namespace App\Http\Controllers\Install;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Install\Installer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Throwable;

/**
 * Asistent de instalare in 4 pasi. Nu foloseste sesiune sau cookie-uri (inainte de instalare nu
 * exista nici cheie de criptare, nici baza de date): fiecare pas retrimite codul de acces.
 */
class InstallController extends Controller
{
    public function __construct(private readonly Installer $installer) {}

    public function start(Request $request)
    {
        $this->installer->accessCode();

        return view('install.start', ['error' => null]);
    }

    public function requirements(Request $request)
    {
        if (! $this->installer->checkCode($request->input('code'))) {
            usleep(500000);

            return response()->view('install.start', ['error' => 'Codul nu este corect. Îl găsești în fișierul storage/app/install-code.txt.'], 422);
        }
        $checks = $this->installer->requirements($request->isSecure());

        return view('install.requirements', ['code' => $request->input('code'), 'checks' => $checks,
            'blocked' => collect($checks)->contains(fn ($c) => ! $c['ok'] && $c['blocking'])]);
    }

    public function configure(Request $request)
    {
        abort_unless($this->installer->checkCode($request->input('code')), 403);

        $values = $request->only(['app_url', 'db_host', 'db_port', 'db_database', 'db_username', 'mail_host', 'mail_port', 'mail_username', 'mail_from']);
        if (! $request->has('db_host')) {
            return view('install.configure', ['code' => $request->input('code'), 'values' => $values + ['app_url' => $request->root(), 'db_host' => 'localhost', 'db_port' => 3306, 'mail_port' => 465], 'errors' => []]);
        }

        $errors = [];
        foreach (['app_url' => 'Adresa site-ului', 'db_host' => 'Serverul bazei de date', 'db_database' => 'Numele bazei de date', 'db_username' => 'Utilizatorul bazei de date'] as $field => $label) {
            if (trim((string) $request->input($field)) === '') {
                $errors[] = $label.' este obligatoriu.';
            }
        }
        if (! filter_var($request->input('app_url'), FILTER_VALIDATE_URL)) {
            $errors[] = 'Adresa site-ului trebuie să fie un URL complet, de exemplu https://hikevet.com.';
        }
        if (! $errors) {
            $dbError = $this->installer->testDatabase(['host' => $request->input('db_host'), 'port' => $request->input('db_port', 3306), 'database' => $request->input('db_database'),
                'username' => $request->input('db_username'), 'password' => (string) $request->input('db_password'), 'allow_non_empty' => $request->boolean('allow_non_empty')]);
            if ($dbError) {
                $errors[] = $dbError;
            }
        }
        if ($errors) {
            // Parolele nu se retrimit niciodata catre browser.
            return response()->view('install.configure', ['code' => $request->input('code'), 'values' => $values, 'errors' => $errors], 422);
        }

        $this->installer->writeEnv($request->only(['app_url', 'db_host', 'db_port', 'db_database', 'db_username', 'db_password', 'mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_from']));

        return view('install.database', ['code' => $request->input('code'), 'error' => null, 'done' => false, 'output' => '']);
    }

    /** Ruleaza migrarile si datele de baza. Se executa intr-o cerere noua, dupa ce .env a fost scris. */
    public function database(Request $request)
    {
        abort_unless($this->installer->checkCode($request->input('code')), 403);

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();
            Artisan::call('db:seed', ['--force' => true]);
            // Fisierele JS / CSS ale panoului de administrare (sunt si in pachet; aici se asigura ca sunt la zi).
            Artisan::call('filament:assets');
            Artisan::call('optimize:clear');
        } catch (Throwable $e) {
            report($e);

            return response()->view('install.database', ['code' => $request->input('code'), 'done' => false, 'output' => '',
                'error' => 'Migrările au eșuat: '.mb_substr($e->getMessage(), 0, 300)], 500);
        }

        return view('install.admin', ['code' => $request->input('code'), 'errors' => [], 'values' => [], 'output' => trim($output)]);
    }

    public function admin(Request $request)
    {
        abort_unless($this->installer->checkCode($request->input('code')), 403);
        abort_unless(Schema::hasTable('users'), 409, 'Baza de date nu este pregătită.');

        $validator = validator($request->all(), [
            'name' => ['required', 'string', 'min:3', 'max:120'], 'email' => ['required', 'email:rfc', 'max:190'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ], [], ['name' => 'numele', 'email' => 'emailul', 'password' => 'parola']);

        if ($validator->fails()) {
            return response()->view('install.admin', ['code' => $request->input('code'), 'errors' => $validator->errors()->all(), 'values' => $request->only(['name', 'email']), 'output' => ''], 422);
        }

        $user = User::query()->firstOrNew(['email' => mb_strtolower($request->input('email'))]);
        $user->forceFill(['name' => $request->input('name'), 'password' => $request->input('password'), 'is_active' => true, 'email_verified_at' => now()])->save();
        $user->syncRoles([Role::SuperAdmin->value]);

        $this->installer->lock($user->email);

        return view('install.done', ['adminUrl' => $request->root().'/admin', 'siteUrl' => $request->root().'/']);
    }
}
