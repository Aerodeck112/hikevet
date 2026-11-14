<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Contract;
use App\Models\User;
use App\Services\Backup\BackupService;
use App\Services\Updates\ReleasePackage;
use App\Services\Updates\UpdateService;
use App\Support\Tables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/** Actualizari ZIP semnate si backup criptat. Actualizarile se aplica intr-un director de proba, nu peste aplicatie. */
class UpdateAndBackupTest extends TestCase
{
    use RefreshDatabase;

    private string $work;

    private string $secret;

    private string $public;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBusiness();
        $pair = sodium_crypto_sign_keypair();
        $this->secret = base64_encode(sodium_crypto_sign_secretkey($pair));
        $this->public = base64_encode(sodium_crypto_sign_publickey($pair));
        $this->work = sys_get_temp_dir().'/hv-update-'.uniqid();
        mkdir($this->work.'/src/app', 0777, true);
        mkdir($this->work.'/src/config', 0777, true);
        mkdir($this->work.'/live/app', 0777, true);
        file_put_contents($this->work.'/src/app/Feature.php', "<?php // versiunea noua\n");
        file_put_contents($this->work.'/src/app/Brand.php', "<?php // fisier nou\n");
        file_put_contents($this->work.'/src/config/demo.php', "<?php return ['v' => 2];\n");
        file_put_contents($this->work.'/live/app/Feature.php', "<?php // versiunea veche\n");
        config(['hikevet.update_public_key' => $this->public, 'hikevet.update_base_path' => $this->work.'/live', 'hikevet.version' => '1.0.0']);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->work));
        foreach (glob(storage_path('app/backups/*inainte-de-*')) ?: [] as $f) {
            @unlink($f);
        }
        exec('rm -rf '.escapeshellarg(storage_path('app/backups')).'/update-*-9.*');
        @unlink(storage_path('app/update.lock'));
        parent::tearDown();
    }

    private function build(string $version = '9.1.0', ?string $secret = null): string
    {
        $zip = $this->work.'/update-'.$version.'-'.uniqid().'.zip';
        app(ReleasePackage::class)->build($this->work.'/src', $zip, $version, $secret ?? $this->secret, 'modificări de test');

        return $zip;
    }

    /** Rescrie arhiva: modifica un fisier, adauga intrari sau inlocuieste manifestul, pastrand semnatura originala. */
    private function tamper(string $zipPath, callable $change): string
    {
        $zip = new ZipArchive;
        $zip->open($zipPath);
        $change($zip);
        $zip->close();

        return $zipPath;
    }

    public function test_valid_signed_package_passes_preflight_and_is_applied_with_file_backup(): void
    {
        $service = app(UpdateService::class);
        $zip = $this->build();

        $preflight = $service->preflight($zip);
        $this->assertTrue($preflight['ok']);
        $this->assertSame('9.1.0', $preflight['manifest']['version']);

        $run = $service->apply($zip, null);

        $this->assertSame('succeeded', $run->status, (string) $run->log);
        $this->assertStringContainsString('versiunea noua', file_get_contents($this->work.'/live/app/Feature.php'));
        $this->assertFileExists($this->work.'/live/app/Brand.php');
        $this->assertFileExists($this->work.'/live/config/demo.php');
        // Fisierul inlocuit este pastrat in copia de siguranta, alaturi de un backup verificat al bazei de date.
        $this->assertStringContainsString('versiunea veche', file_get_contents($run->backup_path.'/files/app/Feature.php'));
        $manifest = json_decode(file_get_contents($run->backup_path.'/manifest.json'), true);
        $this->assertSame(['app/Feature.php'], $manifest['replaced']);
        $this->assertFileExists(storage_path('app/backups/'.$manifest['database_backup']));
        $this->assertFileDoesNotExist(storage_path('app/update.lock'));
    }

    public function test_package_signed_with_another_key_or_unsigned_is_rejected(): void
    {
        $other = base64_encode(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair()));
        foreach ([
            'alta cheie' => $this->build('9.1.0', $other),
            'fara semnatura' => $this->tamper($this->build(), fn (ZipArchive $z) => $z->deleteName('release.sig')),
            'zip oarecare cu php' => (function () {
                $path = $this->work.'/evil.zip';
                $z = new ZipArchive;
                $z->open($path, ZipArchive::CREATE);
                $z->addFromString('files/app/Shell.php', '<?php system($_GET["c"]);');
                $z->close();

                return $path;
            })(),
        ] as $case => $zip) {
            try {
                app(UpdateService::class)->preflight($zip);
                $this->fail("Pachetul ($case) ar fi trebuit respins.");
            } catch (RuntimeException $e) {
                $this->assertMatchesRegularExpression('/[Ss]emnătur|manifest/u', $e->getMessage(), $case);
            }
        }
        $this->assertStringContainsString('versiunea veche', file_get_contents($this->work.'/live/app/Feature.php'));
    }

    public function test_modified_file_extra_file_and_edited_manifest_are_rejected(): void
    {
        $cases = [
            'fisier modificat' => $this->tamper($this->build(), fn (ZipArchive $z) => $z->addFromString('files/app/Feature.php', '<?php // alterat')),
            'fisier in plus' => $this->tamper($this->build(), fn (ZipArchive $z) => $z->addFromString('files/app/Extra.php', '<?php')),
            'intrare in afara files' => $this->tamper($this->build(), fn (ZipArchive $z) => $z->addFromString('index.php', '<?php')),
            'manifest rescris' => $this->tamper($this->build(), function (ZipArchive $z) {
                $m = json_decode($z->getFromName('release.json'), true);
                $m['version'] = '99.0.0';
                $z->addFromString('release.json', json_encode($m));
            }),
        ];

        foreach ($cases as $case => $zip) {
            try {
                app(UpdateService::class)->preflight($zip);
                $this->fail("Pachetul ($case) ar fi trebuit respins.");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_zip_slip_env_and_storage_paths_are_refused_even_when_signed(): void
    {
        $package = app(ReleasePackage::class);
        foreach (['../outside.php', 'app/../../etc/passwd', '/etc/passwd', 'app\\..\\x.php', '.env', 'storage/app/private/x.php', 'public/uploads/shell.php', 'public/shell.php', 'C:/x.php', "app/x\0.php"] as $path) {
            try {
                $package->assertSafePath($path);
                $this->fail("Calea $path ar fi trebuit refuzata.");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        $package->assertSafePath('app/Models/User.php');
        $package->assertSafePath('public/build/assets/site.css');

        // Un manifest semnat corect, dar cu o cale periculoasa, este tot refuzat.
        $manifest = json_encode(['version' => '9.9.9', 'files' => ['../evil.php' => hash('sha256', 'x')]]);
        $zipPath = $this->work.'/slip.zip';
        $z = new ZipArchive;
        $z->open($zipPath, ZipArchive::CREATE);
        $z->addFromString('release.json', $manifest);
        $z->addFromString('release.sig', base64_encode(sodium_crypto_sign_detached($manifest, base64_decode($this->secret))));
        $z->addFromString('files/../evil.php', 'x');
        $z->close();

        $this->expectException(RuntimeException::class);
        app(UpdateService::class)->preflight($zipPath);
    }

    public function test_downgrade_is_blocked_and_missing_public_key_blocks_everything(): void
    {
        config(['hikevet.version' => '9.5.0']);
        $preflight = app(UpdateService::class)->preflight($this->build('9.1.0'));
        $this->assertFalse($preflight['ok']);
        $this->assertFalse(collect($preflight['checks'])->firstWhere('label', 'Versiune mai nouă')['ok']);

        try {
            app(UpdateService::class)->apply($this->build('9.1.0'), null);
            $this->fail('O versiune mai veche nu trebuie instalata.');
        } catch (RuntimeException) {
        }

        config(['hikevet.update_public_key' => '']);
        $this->expectException(RuntimeException::class);
        app(UpdateService::class)->preflight($this->build('9.9.0'));
    }

    public function test_failure_during_copy_restores_previous_files(): void
    {
        // Un director pus in locul unui fisier face copierea sa esueze dupa ce primul fisier a fost deja inlocuit.
        mkdir($this->work.'/live/config/demo.php', 0777, true);
        $run = app(UpdateService::class)->apply($this->build(), null);

        $this->assertSame('rolled_back', $run->status);
        $this->assertStringContainsString('EROARE', $run->log);
        $this->assertStringContainsString('versiunea veche', file_get_contents($this->work.'/live/app/Feature.php'));
        $this->assertFileDoesNotExist($this->work.'/live/app/Brand.php');
        $this->assertFileDoesNotExist(storage_path('app/update.lock'));
    }

    public function test_incremental_package_ships_only_changes_removes_stale_files_and_is_tied_to_its_base_version(): void
    {
        $package = app(ReleasePackage::class);
        // Versiunea instalata: Feature (veche) + un fisier care dispare in versiunea noua.
        file_put_contents($this->work.'/live/app/Stale.php', "<?php // disparut in versiunea noua\n");
        mkdir($this->work.'/live/config', 0777, true);
        copy($this->work.'/src/config/demo.php', $this->work.'/live/config/demo.php');
        $base = ['version' => '1.0.0', 'files' => $package->scan($this->work.'/live')];

        $zip = $this->work.'/delta.zip';
        $built = $package->build($this->work.'/src', $zip, '9.1.0', $this->secret, 'incremental', null, $base);

        // config/demo.php este identic, deci nu calatoreste; Stale.php este marcat pentru stergere.
        $this->assertSame(2, $built['files']);
        $this->assertSame(1, $built['removed']);
        $manifest = app(UpdateService::class)->preflight($zip)['manifest'];
        $this->assertSame(['app/Brand.php', 'app/Feature.php'], array_keys($manifest['files']));
        $this->assertSame(['app/Stale.php'], $manifest['remove']);
        $this->assertSame('1.0.0', $manifest['base_version']);

        $run = app(UpdateService::class)->apply($zip, null);
        $this->assertSame('succeeded', $run->status);
        $this->assertFileDoesNotExist($this->work.'/live/app/Stale.php');
        $this->assertFileExists($run->backup_path.'/files/app/Stale.php');
        $this->assertStringContainsString('versiunea noua', file_get_contents($this->work.'/live/app/Feature.php'));

        // Acelasi pachet pe o alta versiune instalata este refuzat: fisierele lipsa din el nu ar exista pe server.
        config(['hikevet.version' => '0.9.0']);
        $preflight = app(UpdateService::class)->preflight($zip);
        $this->assertFalse($preflight['ok']);
        $this->assertContains('Pachet potrivit versiunii instalate', array_column(array_filter($preflight['checks'], fn ($c) => ! $c['ok']), 'label'));
    }

    public function test_failed_incremental_update_keeps_files_marked_for_removal_and_removal_cannot_target_protected_paths(): void
    {
        $package = app(ReleasePackage::class);
        file_put_contents($this->work.'/live/app/Stale.php', "<?php // trebuie sa revina\n");
        $base = ['version' => '1.0.0', 'files' => $package->scan($this->work.'/live')];
        // Stergerile se fac abia dupa ce toate fisierele au fost copiate si verificate. Un director pus in locul unui
        // fisier opreste copierea, deci la esec fisierul marcat pentru stergere trebuie sa fie inca la locul lui.
        mkdir($this->work.'/live/config/demo.php', 0777, true);
        $zip = $this->work.'/delta-fail.zip';
        $package->build($this->work.'/src', $zip, '9.1.0', $this->secret, '', null, $base);

        $run = app(UpdateService::class)->apply($zip, null);
        $this->assertSame('rolled_back', $run->status);
        $this->assertFileExists($this->work.'/live/app/Stale.php');
        $this->assertStringContainsString('versiunea veche', file_get_contents($this->work.'/live/app/Feature.php'));

        // Un manifest semnat corect care cere stergerea .env sau a datelor este respins la verificare.
        foreach (['.env', 'storage/app/private/contracts/x.pdf', '../outside.php', 'public/uploads/poza.jpg'] as $target) {
            $evil = $this->work.'/evil-'.md5($target).'.zip';
            $manifest = json_encode(['version' => '9.2.0', 'min_php' => '8.3.0', 'has_migrations' => false, 'base_version' => '1.0.0', 'remove' => [$target], 'files' => []]);
            $archive = new ZipArchive;
            $archive->open($evil, ZipArchive::CREATE);
            $archive->addFromString('release.json', $manifest);
            $archive->addFromString('release.sig', base64_encode(sodium_crypto_sign_detached($manifest, base64_decode($this->secret))));
            $archive->close();
            try {
                app(UpdateService::class)->preflight($evil);
                $this->fail('Stergerea ar fi trebuit refuzata: '.$target);
            } catch (RuntimeException $e) {
                $this->assertMatchesRegularExpression('/nepermis|nu are voie|afara/u', $e->getMessage());
            }
        }
    }

    public function test_backup_is_encrypted_and_restore_brings_data_back(): void
    {
        $this->postJson('/abonamente/contract', $this->contractData())->assertOk();
        $backups = app(BackupService::class);
        $result = $backups->create('test-restaurare');

        try {
            $raw = file_get_contents($result['path']);
            $this->assertStringStartsWith("HVBK1\n", $raw);
            $this->assertStringNotContainsString('popescu.test@example.com', $raw);
            $this->assertStringNotContainsString('Rex', $raw);
            $this->assertSame($result['rows'], $backups->verify($result['path'])['rows']);

            // Date pierdute si date adaugate dupa backup.
            $number = Contract::query()->value('number');
            Contract::query()->delete();
            User::factory()->create(['email' => 'aparut.dupa.backup@example.com']);

            $stats = $backups->restore($result['path']);
            $this->assertGreaterThan(0, $stats['rows']);
            $this->assertSame($number, Contract::query()->sole()->number);
            $this->assertSame('1900101000000', Contract::query()->sole()->payload['owner_cnp']);
            $this->assertNull(User::query()->where('email', 'aparut.dupa.backup@example.com')->first());

            // Un backup alterat sau criptat cu alta cheie este refuzat inainte de a atinge baza de date.
            file_put_contents($result['path'], substr_replace($raw, 'XXXX', 200, 4));
            try {
                $backups->restore($result['path']);
                $this->fail('Backupul alterat ar fi trebuit refuzat.');
            } catch (RuntimeException) {
                $this->assertSame(1, Contract::count());
            }
        } finally {
            @unlink($result['path']);
        }
    }

    /** Pe hosting shared acelasi utilizator MySQL poate vedea si alte baze (de exemplu pe cea WordPress). */
    public function test_table_listing_and_backup_ignore_other_databases_visible_to_the_same_user(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Relevant doar pe MySQL / MariaDB.');
        }
        $other = DB::getDatabaseName().'_vecin';
        // Conexiune separata: CREATE DATABASE ar inchide tranzactia in care ruleaza testul.
        $config = DB::getConfig();
        try {
            $admin = new \PDO(sprintf('mysql:host=%s;port=%d', $config['host'], $config['port'] ?? 3306), $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $admin->exec("CREATE DATABASE IF NOT EXISTS `{$other}`");
            $admin->exec("CREATE TABLE IF NOT EXISTS `{$other}`.`wp_tabel_strain` (id INT PRIMARY KEY)");
        } catch (\Throwable $e) {
            $this->markTestSkipped('Utilizatorul de test nu poate crea o a doua bază: '.$e->getMessage());
        }

        try {
            $this->assertNotContains('wp_tabel_strain', Tables::of());
            $this->assertNotContains('wp_tabel_strain', app(BackupService::class)->tablesInDependencyOrder());
            $backup = app(BackupService::class)->create('vecin');
            $this->assertGreaterThan(0, app(BackupService::class)->verify($backup['path'])['tables']);
            @unlink($backup['path']);
        } finally {
            $admin->exec("DROP DATABASE IF EXISTS `{$other}`");
        }
    }

    public function test_only_super_admin_sees_the_updates_screen(): void
    {
        $this->actingAs($this->staff(Role::SuperAdmin))->get('/admin/platform-updates')->assertOk()->assertSee('Sănătatea sistemului');
        $this->actingAs($this->staff(Role::Admin))->get('/admin/platform-updates')->assertForbidden();
        $this->actingAs($this->staff(Role::Doctor))->get('/admin/platform-updates')->assertForbidden();
    }
}
