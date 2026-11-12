<?php

namespace Tests\Feature;

use App\Enums\BalanceType;
use App\Enums\ConsentStatus;
use App\Models\Consent;
use App\Models\ImportBatch;
use App\Models\SterilizationCase;
use App\Models\User;
use App\Services\Import\SterilizationImporter;
use App\Services\Import\WordPressCoreImporter;
use App\Services\Import\WordPressSource;
use App\Services\Wallet\WalletLedger;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

/**
 * Importul din WordPress, pe o baza sursa SINTETICA construita dupa schemele reale din pluginuri
 * (database/import-spec/wp-tables.json). Niciun rand de aici nu provine din date reale.
 */
class WordPressImportTest extends TestCase
{
    use RefreshDatabase;

    private const P = 'wpx7_';

    private string $uploads;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBusiness();
        config(['database.connections.wp' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
        DB::purge('wp');
        $this->buildSource();
    }

    protected function tearDown(): void
    {
        if (isset($this->uploads) && is_dir($this->uploads)) {
            array_map('unlink', glob($this->uploads.'/2026/08/*') ?: []);
            @rmdir($this->uploads.'/2026/08');
            @rmdir($this->uploads.'/2026');
            @rmdir($this->uploads);
        }
        parent::tearDown();
    }

    private function buildSource(): void
    {
        $schema = Schema::connection('wp');
        foreach (json_decode(file_get_contents(database_path('import-spec/wp-tables.json')), true) as $table => $spec) {
            $schema->create(self::P.$table, function (Blueprint $t) use ($spec) {
                foreach ($spec['columns'] as $column) {
                    $column['name'] === 'id' ? $t->unsignedBigInteger('id')->primary() : $t->text($column['name'])->nullable();
                }
            });
        }
        $schema->create(self::P.'users', fn (Blueprint $t) => [$t->unsignedBigInteger('ID')->primary(), $t->string('user_email'), $t->string('user_pass'), $t->string('display_name'), $t->string('user_registered')]);
        $schema->create(self::P.'usermeta', fn (Blueprint $t) => [$t->id('umeta_id'), $t->unsignedBigInteger('user_id'), $t->string('meta_key'), $t->text('meta_value')->nullable()]);
        $schema->create(self::P.'posts', fn (Blueprint $t) => [$t->unsignedBigInteger('ID')->primary(), $t->string('post_type'), $t->string('post_title')->nullable(), $t->string('post_date'), $t->string('post_modified')->nullable()]);
        $schema->create(self::P.'postmeta', fn (Blueprint $t) => [$t->id('meta_id'), $t->unsignedBigInteger('post_id'), $t->string('meta_key'), $t->text('meta_value')->nullable()]);
        $schema->create(self::P.'options', fn (Blueprint $t) => [$t->id('option_id'), $t->string('option_name'), $t->text('option_value')->nullable()]);

        $wp = DB::connection('wp');

        // Client cu doua animale (unul arhivat), parola phpass ("parola-test-123" nu este stocata nicaieri in clar).
        $wp->table(self::P.'users')->insert(['ID' => 41, 'user_email' => 'Client.Import@Example.com', 'user_pass' => '$wp'.password_hash(base64_encode(hash_hmac('sha384', 'parola-test-123', 'wp-sha384', true)), PASSWORD_BCRYPT),
            'display_name' => 'Client Import', 'user_registered' => '2026-03-02 10:00:00']);
        $meta = fn ($user, $key, $value) => $wp->table(self::P.'usermeta')->insert(['user_id' => $user, 'meta_key' => $key, 'meta_value' => $value]);
        $meta(41, self::P.'capabilities', serialize(['hikevet_client' => true]));
        $meta(41, 'billing_phone', '0700000041');
        $meta(41, 'hv_pets', serialize([
            ['id' => 1, 'pet_name' => 'Azor', 'species' => 'dog', 'sex' => 'male', 'weight' => '12,5', 'tier_label' => '10-20 kg', 'dob' => '2021-01-01', 'archived_at' => ''],
            ['id' => 2, 'pet_name' => 'Fifi', 'species' => 'cat', 'weight' => '3', 'tier_label' => '2-4 kg', 'archived_at' => '2026-06-01 09:00:00', 'archive_reason' => 'donat'],
        ]));

        // Contract platit + registru wallet.
        $wp->table(self::P.'posts')->insert(['ID' => 900, 'post_type' => 'hv_abon_sub', 'post_title' => 'Client Import - Azor', 'post_date' => '2026-03-02 10:05:00', 'post_modified' => '2026-03-03 11:00:00']);
        $pm = fn ($post, $key, $value) => $wp->table(self::P.'postmeta')->insert(['post_id' => $post, 'meta_key' => $key, 'meta_value' => $value]);
        $pm(900, 'hv_payload', json_encode(['pet_name' => 'Azor', 'species' => 'dog', 'tier_label' => '10-20 kg', 'owner_email' => 'client.import@example.com', 'owner_cnp' => '1900101000000',
            'price_monthly' => 248, 'price_6m' => 1335, 'price_12m' => 2376, 'discount_percent' => 0, 'pet_ordinal' => 1, 'signature' => 'data:image/png;base64,AAAA']));
        foreach (['hv_client_user_id' => 41, 'hv_payment_status' => 'paid', 'hv_payment_period_months' => 12, 'hv_payment_amount' => 2376, 'hv_payment_paid_at' => now()->subMonth()->format('Y-m-d H:i:s'),
            'hv_submission_token' => 'tok-900', 'hv_pdf' => '/var/www/wp-content/uploads/hikevet-contracte/contract_x.pdf', 'hv_total_normal' => 4110, 'hv_total_abon' => 2438] as $k => $v) {
            $pm(900, $k, $v);
        }
        $tx = fn (int $id, string $type, string $balance, float $amount, int $points) => $wp->table(self::P.'hv_wallet_tx')->insert(['id' => $id, 'user_id' => 41, 'type' => $type, 'balance_type' => $balance,
            'amount_delta' => $amount, 'points_delta' => $points, 'note' => 'sintetic', 'created_at' => '2026-03-03 11:00:00', 'actor_user_id' => 7]);
        $tx(1, 'subscription_validated', 'subscription', 2376, 2976);
        $tx(2, 'treatment_consume', 'subscription', 0, -50);
        $tx(3, 'wallet_deposit_principal', 'wallet_principal', 100, 0);
        $tx(4, 'wallet_bonus_services', 'wallet_service_bonus', 100, 0);
        $wp->table(self::P.'hv_wallet_tx')->insert(['id' => 5, 'user_id' => 999, 'type' => 'x', 'balance_type' => 'subscription', 'amount_delta' => 0, 'points_delta' => 10, 'created_at' => '2026-03-03 11:00:00']);
        $wp->table(self::P.'hv_treatments')->insert(['id' => 1, 'user_id' => 41, 'pet_name' => 'Azor', 'treatment_name' => 'Consultatie generala', 'price_standard' => 50, 'points_used' => 50, 'payment_category' => 'subscription', 'created_at' => '2026-04-01 12:00:00']);

        // Consimtamant semnat, legat de dosarul 2.
        $wp->table(self::P.'posts')->insert(['ID' => 950, 'post_type' => 'hv_consent', 'post_date' => '2026-08-04 08:00:00', 'post_modified' => '2026-08-04 09:00:00']);
        $pm(950, 'hv_consent_payload', serialize(['owner_name' => 'Proprietar Sintetic', 'pet_name' => 'Mura', 'procedure' => 'Ovariohisterectomie pisica', 'vet_name' => 'Dr. Test', 'consent_date' => '0000-00-00', 'owner_final_gdpr' => 1]));
        $pm(950, 'hv_consent_status', 'signed');
        $pm(950, 'hv_consent_owner_signature', $this->signature());
        $pm(950, 'hv_consent_signed_at', '2026-08-04 08:40:12');
        $pm(950, 'hv_st_case_id', 2);

        // Trei dosare: finalizat complet, finalizat cu lipsuri si data invalida, programat.
        $this->uploads = sys_get_temp_dir().'/hv-uploads-'.uniqid();
        mkdir($this->uploads.'/2026/08', 0777, true);
        file_put_contents($this->uploads.'/2026/08/pacient-2.jpg', 'continut-sintetic-fotografie');
        $case = fn (array $row) => $wp->table(self::P.'hv_st_cases')->insert($row);
        $case(['id' => 1, 'cod_caz' => 'HV-ST-2026-001', 'nume_proprietar' => 'Proprietar Unu', 'nume_pacient' => 'Rex', 'specie' => 'Caine', 'sex' => 'Mascul', 'localitate' => 'Targu Mures',
            'data_programare' => '2026-07-10', 'status_caz' => 'Programat', 'status_interventie' => '', 'timestamp_creat' => '2026-07-01 09:00:00']);
        $case(['id' => 2, 'cod_caz' => 'HV-ST-2026-002', 'nume_proprietar' => 'Proprietar Sintetic', 'nume_pacient' => 'Mura', 'specie' => 'Pisica', 'sex' => 'Femela', 'localitate' => 'Targu Mures',
            'data_interventie' => '2026-08-04', 'status_caz' => 'Externat', 'status_interventie' => 'Finalizat', 'procedura_efectuata' => 'Ovariohisterectomie', 'medic_operator' => 'Dr. Test',
            'numar_pacient' => '17', 'poza_pacient' => 'https://hikevet.com/wp-content/uploads/2026/08/pacient-2.jpg', 'consent_id' => 950, 'visit_group_id' => 2, 'validat_pentru_primarie' => 'Da',
            'mentiuni_intra_postoperatorii' => 'Fără observații', 'timestamp_creat' => '2026-08-01 10:00:00', 'timestamp_actualizat' => '2026-08-04 12:00:00']);
        $case(['id' => 3, 'cod_caz' => 'HV-ST-2026-003', 'nume_proprietar' => 'Proprietar Trei', 'nume_pacient' => 'Tom', 'specie' => 'Pisica', 'sex' => 'Mascul',
            'data_interventie' => '0000-00-00', 'status_caz' => 'Operat', 'status_interventie' => 'Operat', 'poza_pacient' => 'https://hikevet.com/wp-content/uploads/2026/08/lipsa.jpg']);
        $wp->table(self::P.'hv_st_appointments')->insert(['id' => 1, 'appointment_code' => 'HV-AP-1', 'case_id' => 2, 'appointment_date' => '2026-08-04', 'start_time' => '09:00', 'status' => 'Programat', 'owner_name' => 'Proprietar Sintetic']);
        $wp->table(self::P.'hv_st_email_log')->insert(['id' => 1, 'case_id' => 2, 'to_email' => 'x@example.com', 'email_type' => 'postop_instant', 'status' => 'sent', 'created_at' => '2026-08-04 13:00:00']);
        $wp->table(self::P.'options')->insert(['option_name' => 'hv_st_reported_through_september_2026', 'option_value' => serialize(['HV-ST-2026-002' => '2026-08'])]);
    }

    private function source(): WordPressSource
    {
        return new WordPressSource('wp');
    }

    public function test_prefix_is_detected_and_dry_run_writes_nothing(): void
    {
        $this->assertSame(self::P, $this->source()->prefix());

        $core = (new WordPressCoreImporter($this->source()))->run(true);
        $archive = (new SterilizationImporter($this->source()))->run(true, $this->uploads);

        $this->assertSame('finished', $core->status);
        $this->assertSame(1, $core->counts['users_in_source']);
        $this->assertSame(2, $core->counts['pets_in_source']);
        $this->assertSame(5, $core->counts['wallet_tx_in_source']);
        $this->assertSame(3, $archive->counts['cases_in_source']);
        $this->assertSame(3, $archive->counts['cases_new']);
        $this->assertSame(2, $archive->counts['finalized']);
        $this->assertSame(['2026-08' => 1, 'fără dată' => 1], $archive->counts['finalized_by_month']);
        $this->assertSame(64, strlen($archive->source_sha256));

        $this->assertSame(0, SterilizationCase::count());
        $this->assertSame(0, Consent::count());
        $this->assertNull(User::query()->where('email', 'client.import@example.com')->first());
        $this->assertSame(0, DB::table('wallet_transactions')->count());
    }

    public function test_real_import_keeps_every_source_row_identical_and_flags_what_is_missing(): void
    {
        (new WordPressCoreImporter($this->source()))->run(false);
        $batch = (new SterilizationImporter($this->source()))->run(false, $this->uploads);

        $this->assertSame('finished', $batch->status);
        $this->assertSame(3, SterilizationCase::count());

        // Fiecare rand sursa este regasit identic, cheie cu cheie.
        foreach (DB::connection('wp')->table(self::P.'hv_st_cases')->get() as $row) {
            $case = SterilizationCase::query()->where('source_id', $row->id)->sole();
            $this->assertSame((array) $row, $case->source_row);
            $this->assertSame(hash('sha256', json_encode((array) $row, JSON_UNESCAPED_UNICODE)), $case->row_sha256);
        }

        $complete = SterilizationCase::query()->where('cod_caz', 'HV-ST-2026-002')->sole();
        $this->assertSame('2026-08-04', $complete->data_interventie->toDateString());
        $this->assertSame('17', $complete->numar_pacient);
        $this->assertSame('2026-08', $complete->reported_month);
        $this->assertNull($complete->missing_in_source);
        $this->assertNotNull($complete->consent_id, 'Dosarul este legat de consimtamantul importat.');

        $photo = $complete->documents()->sole();
        $this->assertFalse($photo->is_missing);
        $this->assertSame(hash('sha256', 'continut-sintetic-fotografie'), $photo->sha256);
        $this->assertSame('continut-sintetic-fotografie', Storage::disk('private')->get($photo->stored_path));

        // Dosarul cu lipsuri: nimic inventat, data invalida ramane goala, lipsurile sunt enumerate.
        $partial = SterilizationCase::query()->where('cod_caz', 'HV-ST-2026-003')->sole();
        $this->assertNull($partial->data_interventie);
        $this->assertSame('0000-00-00', $partial->source('data_interventie'));
        $this->assertContains('numar_pacient', $partial->missing_in_source);
        $this->assertContains('medic_operator', $partial->missing_in_source);
        $this->assertTrue($partial->documents()->sole()->is_missing);
        $this->assertSame(1, $batch->counts['documents_missing']);
        $this->assertSame(["HV-ST-2026-003"], array_column($batch->missing, "cod_caz"));
    }

    public function test_import_is_idempotent_and_can_be_rolled_back(): void
    {
        (new WordPressCoreImporter($this->source()))->run(false);
        $importer = new SterilizationImporter($this->source());
        $first = $importer->run(false, $this->uploads);
        $second = $importer->run(false, $this->uploads);

        $this->assertSame(3, SterilizationCase::count());
        $this->assertSame(3, $second->counts['cases_unchanged']);
        $this->assertSame(0, $second->counts['cases_new']);
        $this->assertSame($first->source_sha256, $second->source_sha256);

        (new WordPressCoreImporter($this->source()))->run(false);
        $this->assertSame(1, User::query()->whereNotNull('wp_user_id')->count());
        $this->assertSame(4, DB::table('wallet_transactions')->count());
        $this->assertSame(1, Consent::count());

        $this->assertSame(3, $importer->rollback($first));
        $this->assertSame(0, SterilizationCase::count());
        $this->assertSame(0, DB::table('sterilization_documents')->count());
        $this->assertSame(0, $importer->rollback(ImportBatch::query()->where('dry_run', true)->first() ?? $first->fresh()->replicate()->fill(['dry_run' => true])));
    }

    public function test_archive_rows_are_read_only_through_the_model(): void
    {
        (new SterilizationImporter($this->source()))->run(false);
        $case = SterilizationCase::query()->firstOrFail();

        try {
            $case->update(['nume_pacient' => 'Modificat']);
            $this->fail('Arhiva ar fi trebuit sa refuze modificarea.');
        } catch (LogicException) {
        }
        $this->expectException(LogicException::class);
        $case->delete();
    }

    public function test_core_import_brings_users_pets_contracts_ledger_and_signed_consent_unchanged(): void
    {
        $batch = (new WordPressCoreImporter($this->source()))->run(false);
        $user = User::query()->where('email', 'client.import@example.com')->sole();
        $ledger = app(WalletLedger::class);

        $this->assertSame(41, $user->wp_user_id);
        $this->assertSame('0700000041', $user->phone);
        $this->assertSame('2026-03-02', $user->created_at->toDateString());
        $this->assertSame(1, $user->activePets()->count());
        $this->assertSame(2, $user->pets()->count());
        $this->assertSame('12.50', $user->pets()->where('name', 'Azor')->sole()->weight_kg);

        // Soldurile rezulta din registrul copiat rand cu rand, cu data originala.
        $this->assertSame(2926, $ledger->points($user));
        $this->assertSame(100.0, $ledger->amount($user, BalanceType::WalletPrincipal));
        $this->assertSame('2026-03-03 11:00:00', $user->walletTransactions()->oldest('id')->first()->created_at->toDateTimeString());
        $this->assertSame(1, $batch->counts['wallet_tx_orphan']);
        $this->assertTrue($ledger->hasActiveSubscription($user));

        $contract = $user->contracts()->sole();
        $this->assertSame(['WP-900', 248, 12, 'active'], [$contract->number, $contract->price_monthly, $contract->period_months, $contract->status->value]);
        $this->assertSame('1900101000000', $contract->payload['owner_cnp']);
        $this->assertArrayNotHasKey('signature', $contract->payload);
        $this->assertStringNotContainsString('1900101000000', DB::table('contracts')->value('payload'));

        $consent = Consent::query()->sole();
        $this->assertSame(ConsentStatus::Signed, $consent->status);
        $this->assertSame('2026-08-04 08:40:12', $consent->signed_at->toDateTimeString());
        $this->assertSame($this->signature(), $consent->owner_signature);
        $this->assertSame('0000-00-00', $consent->payload['consent_date'], 'Payload-ul semnat ramane exact ca in sursa.');
        $this->assertNull($consent->consent_date);
    }

    public function test_migrated_client_logs_in_with_the_wordpress_password_and_is_rehashed(): void
    {
        (new WordPressCoreImporter($this->source()))->run(false);
        $user = User::query()->where('email', 'client.import@example.com')->sole();
        $this->assertNotNull($user->wp_password_hash);

        $this->post('/acces-cont/autentificare', ['email' => 'client.import@example.com', 'password' => 'gresita-123'])->assertSessionHasErrors('email');
        $this->post('/acces-cont/autentificare', ['email' => 'client.import@example.com', 'password' => 'parola-test-123'])->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertNull($user->wp_password_hash);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('parola-test-123', $user->password));
    }

    public function test_archive_admin_is_read_only_filtered_and_exports_are_logged(): void
    {
        (new WordPressCoreImporter($this->source()))->run(false);
        (new SterilizationImporter($this->source()))->run(false, $this->uploads);
        $case = SterilizationCase::query()->where('cod_caz', 'HV-ST-2026-002')->sole();
        $doctor = $this->staff(\App\Enums\Role::Doctor);
        $admin = $this->staff(\App\Enums\Role::Admin);

        $this->actingAs($doctor)->get('/admin/sterilization-archive')->assertOk()->assertSee('HV-ST-2026-002');
        $this->actingAs($doctor)->get('/admin/sterilization-archive/'.$case->id)->assertOk()->assertSee('Ovariohisterectomie')->assertSee('Păstrat');
        $this->actingAs($doctor)->get('/admin/sterilization-archive/'.SterilizationCase::query()->where('cod_caz', 'HV-ST-2026-003')->value('id'))->assertOk()->assertSee('Lipsă în sursă');
        $this->actingAs($doctor)->get('/admin/sterilization-reports?month=2026-08')->assertOk()->assertSee('Centralizator 2026-08')->assertSee('Pisica / Femela');
        $this->actingAs($this->staff(\App\Enums\Role::Reception))->get('/admin/sterilization-archive')->assertForbidden();
        $this->actingAs($this->staff(\App\Enums\Role::Editor))->get('/admin/sterilization-reports')->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'sterilization.viewed', 'subject_id' => $case->id]);

        $exporter = app(\App\Services\Sterilization\ArchiveExporter::class);
        $report = $exporter->monthlyReport('2026-08');
        $this->assertSame(1, $report['total']);
        $this->assertSame(['Pisica / Femela' => 1], $report['summary']);
        $this->assertSame(['2026-08' => 1, 'fără dată' => 1], $exporter->finalizedByMonth());

        ob_start();
        $exporter->csv(SterilizationCase::query(), 'x.csv')->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString('HV-ST-2026-002;17;2026-08-04', $csv);
        $this->assertSame(4, substr_count(trim($csv), "\n") + 1);

        $zipPath = $exporter->caseZip($case)->getFile()->getPathname();
        $zip = new \ZipArchive;
        $zip->open($zipPath);
        $this->assertSame('continut-sintetic-fotografie', $zip->getFromName('documente/01-fotografie_pacient.jpg'));
        $this->assertSame($case->source_row, json_decode($zip->getFromName('dosar-original.json'), true));
        $zip->close();
        unset($admin);
    }

    public function test_command_defaults_to_dry_run(): void
    {
        $this->artisan('hikevet:import-wordpress', ['--uploads' => $this->uploads])->expectsOutputToContain('PROBĂ (dry-run)')->assertSuccessful();
        $this->assertSame(0, SterilizationCase::count());
        $this->assertSame(2, ImportBatch::query()->where('dry_run', true)->count());

        $this->artisan('hikevet:import-wordpress', ['--execute' => true, '--uploads' => $this->uploads])->assertSuccessful();
        $this->assertSame(3, SterilizationCase::count());
    }
}
