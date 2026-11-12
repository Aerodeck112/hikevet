<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Services\Install\Installer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBusiness();
        $this->dir = sys_get_temp_dir().'/hv-install-'.uniqid();
        mkdir($this->dir);
        config(['hikevet.assume_installed' => false, 'hikevet.install_lock' => $this->dir.'/installed.lock', 'hikevet.install_env_path' => $this->dir.'/.env']);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dir));
        parent::tearDown();
    }

    public function test_before_installation_every_page_leads_to_the_installer(): void
    {
        $this->get('/')->assertRedirect('http://localhost:8000/install');
        $this->get('/admin')->assertRedirect('http://localhost:8000/install');
        $this->get('/install')->assertOk()->assertSee('install-code.txt');
    }

    public function test_installer_requires_the_code_written_to_disk(): void
    {
        $this->post('/install/verificari', ['code' => 'GRESIT-COD'])->assertStatus(422)->assertSee('Codul nu este corect');
        $this->post('/install/configurare', ['code' => 'GRESIT-COD'])->assertForbidden();
        $this->post('/install/tabele', [])->assertForbidden();
        $this->post('/install/administrator', ['code' => ''])->assertForbidden();

        $code = app(Installer::class)->accessCode();
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code);
        $this->post('/install/verificari', ['code' => strtolower($code)])->assertOk()->assertSee('Verificarea serverului')->assertSee('Extensia PHP sodium');
    }

    public function test_bad_database_credentials_are_reported_and_passwords_are_never_echoed(): void
    {
        $code = app(Installer::class)->accessCode();
        $response = $this->post('/install/configurare', ['code' => $code, 'app_url' => 'https://exemplu.test', 'db_host' => '127.0.0.1', 'db_port' => 1,
            'db_database' => 'nu_exista', 'db_username' => 'cineva', 'db_password' => 'Parola-Secreta-123', 'mail_password' => 'Alt-Secret-456']);

        $response->assertStatus(422)->assertSee('Conexiunea a eșuat')->assertDontSee('Parola-Secreta-123')->assertDontSee('Alt-Secret-456');
        $this->assertFileDoesNotExist($this->dir.'/.env');
    }

    public function test_env_file_is_written_for_production_with_a_fresh_key_and_escaped_values(): void
    {
        app(Installer::class)->writeEnv(['app_url' => 'https://exemplu.test/', 'db_host' => 'localhost', 'db_port' => 3306, 'db_database' => 'baza', 'db_username' => 'user',
            'db_password' => "pa\"ro\$la\nINJECT=1", 'mail_host' => '', 'mail_from' => 'contact@exemplu.test']);
        $env = file_get_contents($this->dir.'/.env');

        $this->assertStringContainsString('APP_ENV=production', $env);
        $this->assertStringContainsString('APP_DEBUG=false', $env);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/=]{44}$/m', $env);
        $this->assertStringContainsString('APP_URL="https://exemplu.test"', $env);
        $this->assertStringContainsString('DB_PASSWORD="pa\\"ro\\$laINJECT=1"', $env);
        $this->assertStringNotContainsString("\nINJECT=1", $env);
        $this->assertStringContainsString('MAIL_MAILER=log', $env);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($this->dir.'/.env')), -4));
    }

    public function test_tables_and_admin_steps_finish_the_installation_and_lock_the_installer(): void
    {
        $code = app(Installer::class)->accessCode();

        $this->post('/install/tabele', ['code' => $code])->assertOk()->assertSee('Primul administrator');
        $this->post('/install/administrator', ['code' => $code, 'name' => 'Admin Test', 'email' => 'admin@exemplu.test', 'password' => 'scurta', 'password_confirmation' => 'scurta'])->assertStatus(422);
        $this->assertFileDoesNotExist($this->dir.'/installed.lock');

        $this->post('/install/administrator', ['code' => $code, 'name' => 'Admin Test', 'email' => 'admin@exemplu.test',
            'password' => 'Parola-Lunga-2026!', 'password_confirmation' => 'Parola-Lunga-2026!'])->assertOk()->assertSee('Instalarea s-a încheiat');

        $admin = User::query()->where('email', 'admin@exemplu.test')->sole();
        $this->assertTrue($admin->hasRole(Role::SuperAdmin->value));
        $this->assertFileExists($this->dir.'/installed.lock');
        $this->assertFileDoesNotExist($this->dir.'/install-code.txt');

        // Instalatorul nu mai exista, nici cu vechiul cod.
        $this->get('/install')->assertNotFound();
        $this->post('/install/administrator', ['code' => $code, 'name' => 'Intrus', 'email' => 'intrus@exemplu.test', 'password' => 'Parola-Lunga-2026!', 'password_confirmation' => 'Parola-Lunga-2026!'])->assertNotFound();
        $this->assertNull(User::query()->where('email', 'intrus@exemplu.test')->first());
    }
}
