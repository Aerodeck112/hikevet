<?php

namespace Tests\Feature;

use App\Enums\BalanceType;
use App\Enums\ConsentStatus;
use App\Enums\Role;
use App\Models\Consent;
use App\Models\Contract;
use App\Models\ImportBatch;
use App\Models\LegacyFile;
use App\Models\SterilizationAppointment;
use App\Models\SterilizationCase;
use App\Models\User;
use App\Services\Campaign\CampaignCases;
use App\Services\Import\SterilizationImporter;
use App\Services\Import\WordPressCoreImporter;
use App\Services\Import\WordPressSource;
use App\Services\Sterilization\ArchiveExporter;
use App\Services\Subscriptions\SubscriptionActivation;
use App\Services\Wallet\WalletLedger;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
            array_map('unlink', glob($this->uploads.'/hikevet-contracte/*') ?: []);
            @rmdir($this->uploads.'/hikevet-contracte');
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

        // Angajat (nu se importa ca utilizator), istoric de notificari, emailuri, audit si notite.
        $wp->table(self::P.'users')->insert(['ID' => 7, 'user_email' => 'medic.sintetic@example.com', 'user_pass' => 'x', 'display_name' => 'Dr. Medic Sintetic', 'user_registered' => '2025-01-01 08:00:00']);
        $wp->table(self::P.'hv_notifications')->insert(['id' => 11, 'user_id' => 41, 'subject' => 'Abonament validat', 'message' => '<p>Mesaj sintetic</p>', 'channel' => 'email', 'related_type' => 'contract', 'related_id' => 900, 'created_at' => '2026-03-03 11:01:00']);
        $wp->table(self::P.'hv_notifications')->insert(['id' => 12, 'user_id' => 999, 'subject' => 'Fara client', 'message' => 'x', 'created_at' => '2026-03-03 11:01:00']);
        $wp->table(self::P.'hv_email_log')->insert(['id' => 21, 'user_id' => 41, 'to_email' => 'client.import@example.com', 'subject' => 'Abonament validat', 'context' => 'subscription_validated', 'status' => 'sent', 'created_at' => '2026-03-03 11:01:00']);
        $wp->table(self::P.'hv_email_log')->insert(['id' => 22, 'user_id' => 0, 'to_email' => 'clinica@example.com', 'subject' => 'Contract nou', 'context' => 'contract', 'status' => 'failed', 'error_message' => 'SMTP indisponibil', 'created_at' => '2026-03-02 10:06:00']);
        $wp->table(self::P.'hv_audit_log')->insert(['id' => 31, 'user_id' => 41, 'entity_type' => 'wallet', 'entity_id' => 3, 'action' => 'manual_balance_adjust', 'summary' => 'Corectie sintetica',
            'before_value' => json_encode(['puncte' => 10]), 'after_value' => serialize(['puncte' => 20]), 'actor_user_id' => 7, 'created_at' => '2026-03-05 09:00:00']);
        $wp->table(self::P.'hv_doctor_notes')->insert(['id' => 41, 'user_id' => 41, 'pet_id' => 1, 'note_text' => 'Alergie sintetica la puiul de test', 'priority' => 'high', 'note_status' => 'open', 'actor_user_id' => 7, 'created_at' => '2026-03-06 10:00:00', 'updated_at' => '2026-03-06 10:00:00']);
        $wp->table(self::P.'hv_doctor_notes')->insert(['id' => 42, 'user_id' => 41, 'pet_id' => 0, 'note_text' => 'Notita stearsa', 'priority' => 'normal', 'note_status' => 'resolved', 'actor_user_id' => 7, 'created_at' => '2026-03-06 10:00:00', 'deleted_at' => '2026-03-07 10:00:00', 'deleted_by' => 7]);

        // PDF-ul vechi al contractului si semnatura lui, in structura din uploads.
        mkdir($this->uploads.'/hikevet-contracte', 0777, true);
        file_put_contents($this->uploads.'/hikevet-contracte/contract_20260302_100500_abc123.pdf', '%PDF-1.4 contract sintetic');
        file_put_contents($this->uploads.'/hikevet-contracte/semnatura_20260302_100500_abc123.jpg', 'jpeg-sintetic');
        $wp->table(self::P.'postmeta')->where('post_id', 900)->where('meta_key', 'hv_pdf')->update(['meta_value' => '/home/cont/public_html/wp-content/uploads/hikevet-contracte/contract_20260302_100500_abc123.pdf']);
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
        // 0000-00-00 inseamna „necompletat” in MySQL: la un dosar finalizat lipseste data, dar nu este o valoare invalida.
        $this->assertContains('data_interventie', $partial->missing_in_source);
        $this->assertEmpty(array_filter($partial->missing_in_source, fn ($f) => str_contains($f, 'valoare invalidă')));
        $this->assertContains('medic_operator', $partial->missing_in_source);
        $this->assertTrue($partial->documents()->sole()->is_missing);
        $this->assertSame(1, $batch->counts['documents_missing']);
        $this->assertSame(['HV-ST-2026-003'], array_column($batch->missing, 'cod_caz'));
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
        $this->assertTrue(Hash::check('parola-test-123', $user->password));
    }

    public function test_archive_admin_is_read_only_filtered_and_exports_are_logged(): void
    {
        (new WordPressCoreImporter($this->source()))->run(false);
        (new SterilizationImporter($this->source()))->run(false, $this->uploads);
        $case = SterilizationCase::query()->where('cod_caz', 'HV-ST-2026-002')->sole();
        $doctor = $this->staff(Role::Doctor);
        $admin = $this->staff(Role::Admin);

        $this->actingAs($doctor)->get('/admin/sterilization-archive')->assertOk()->assertSee('HV-ST-2026-002');
        $this->actingAs($doctor)->get('/admin/sterilization-archive/'.$case->id)->assertOk()->assertSee('Ovariohisterectomie')->assertSee('Păstrat');
        $this->actingAs($doctor)->get('/admin/sterilization-archive/'.SterilizationCase::query()->where('cod_caz', 'HV-ST-2026-003')->value('id'))->assertOk()->assertSee('Lipsă în sursă');
        $this->actingAs($doctor)->get('/admin/sterilization-reports?month=2026-08')->assertOk()->assertSee('Centralizator 2026-08')->assertSee('Pisica / Femela');
        // Receptia lucreaza in campanie (programari, dosare), deci vede si arhiva; editorul de continut nu.
        $this->actingAs($this->staff(Role::Reception))->get('/admin/sterilization-archive')->assertOk();
        $this->actingAs($this->staff(Role::Editor))->get('/admin/sterilization-archive')->assertForbidden();
        $this->actingAs($this->staff(Role::Editor))->get('/admin/sterilization-reports')->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'sterilization.viewed', 'subject_id' => $case->id]);

        $exporter = app(ArchiveExporter::class);
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

    public function test_history_tables_old_contract_pdfs_and_staff_names_are_kept(): void
    {
        $dry = (new WordPressCoreImporter($this->source()))->run(true, null, $this->uploads);
        $this->assertSame(2, $dry->counts['notifications_in_source']);
        $this->assertSame(1, $dry->counts['notifications_orphan']);
        $this->assertSame(1, $dry->counts['email_log_failed_in_source']);
        $this->assertSame(1, $dry->counts['contract_pdfs_found']);
        $this->assertSame(0, DB::table('client_notifications')->count() + DB::table('doctor_notes')->count());

        (new WordPressCoreImporter($this->source()))->run(false, null, $this->uploads);
        $batch = (new WordPressCoreImporter($this->source()))->run(false, null, $this->uploads); // repetabil
        $this->assertSame('finished', $batch->status);
        $client = User::query()->where('wp_user_id', 41)->sole();

        // Notificari: doar cele cu client, marcate citite (sunt istoric).
        $this->assertSame(1, DB::table('client_notifications')->count());
        $this->assertNotNull(DB::table('client_notifications')->value('read_at'));

        // Jurnal emailuri: inclusiv cel esuat si fara client.
        $this->assertSame(2, DB::table('email_logs')->whereNotNull('wp_email_log_id')->count());
        $this->assertSame('SMTP indisponibil', DB::table('email_logs')->where('wp_email_log_id', 22)->value('error_message'));

        // Audit: autorul cu nume, valorile JSON si serializate pastrate.
        $audit = DB::table('audit_logs')->where('wp_audit_id', 31)->first();
        $this->assertSame('Dr. Medic Sintetic (WordPress #7)', $audit->actor_name);
        $this->assertSame($client->id, (int) $audit->client_id);
        $this->assertSame(['puncte' => 10], json_decode($audit->before, true));
        $this->assertSame(['puncte' => 20], json_decode($audit->after, true));

        // Notite: legate de animal, cu autorul si stergerea pastrate.
        $note = DB::table('doctor_notes')->where('wp_note_id', 41)->first();
        $this->assertSame('Azor', DB::table('pets')->where('id', $note->pet_id)->value('name'));
        $this->assertSame('Dr. Medic Sintetic (WordPress #7)', $note->actor_name);
        $this->assertNotNull(DB::table('doctor_notes')->where('wp_note_id', 42)->value('deleted_at'));
        $this->assertSame(2, DB::table('doctor_notes')->count());

        // Registrul Wallet are acum numele angajatului, nu doar ID-ul.
        $this->assertSame('Dr. Medic Sintetic (WordPress #7)', DB::table('wallet_transactions')->where('wp_tx_id', 1)->value('actor_name'));

        // PDF-ul vechi si semnatura: copiate pe discul privat, cu amprenta, descarcabile de client.
        $contract = Contract::query()->where('wp_post_id', 900)->sole();
        $this->assertStringStartsWith('contracts/legacy/', $contract->pdf_path);
        $this->assertSame(hash('sha256', '%PDF-1.4 contract sintetic'), $contract->pdf_sha256);
        $this->assertSame('jpeg-sintetic', Storage::disk('private')->get($contract->signature_path));
        $this->assertStringContainsString('contract_20260302_100500_abc123.pdf', $contract->legacy_pdf_reference);
        $this->actingAs($client)->get(route('account.contract.pdf', $contract))->assertOk();

        // Angajatul nu devine cont in platforma noua.
        $this->assertNull(User::query()->where('wp_user_id', 7)->first());
    }

    public function test_missing_contract_pdf_is_reported_without_blocking_the_import(): void
    {
        unlink($this->uploads.'/hikevet-contracte/contract_20260302_100500_abc123.pdf');
        $batch = (new WordPressCoreImporter($this->source()))->run(false, null, $this->uploads);

        $this->assertSame('finished', $batch->status);
        $this->assertSame(0, $batch->counts['contract_pdfs_found']);
        $this->assertStringContainsString('PDF-ul contractului nu a fost găsit', json_encode($batch->errors, JSON_UNESCAPED_UNICODE));
        $this->assertNull(Contract::query()->where('wp_post_id', 900)->value('pdf_path'));
    }

    /** Cazurile gasite in backupul real, reproduse cu date sintetice. */
    public function test_contract_state_is_rebuilt_from_ledger_payments_and_damaged_payloads_are_recovered(): void
    {
        $wp = DB::connection('wp');
        $pm = fn ($post, $key, $value) => $wp->table(self::P.'postmeta')->insert(['post_id' => $post, 'meta_key' => $key, 'meta_value' => $value]);
        $payload = fn (string $pet) => json_encode(['pet_name' => $pet, 'species' => 'cat', 'tier_label' => '2-4 kg', 'owner_email' => 'client.import@example.com',
            'price_monthly' => 225, 'price_6m' => 1215, 'price_12m' => 2160, 'legal' => ['contract' => 1]]);

        // 901: platit si reinnoit, fara stare pe contract (Wallet Core scria doar pe „ultimul contract”).
        $wp->table(self::P.'posts')->insert(['ID' => 901, 'post_type' => 'hv_abon_sub', 'post_date' => '2026-03-20 10:00:00']);
        $pm(901, 'hv_payload', $payload('Mitzi'));
        $pm(901, 'hv_client_user_id', 41);
        $wp->table(self::P.'hv_wallet_tx')->insert(['id' => 50, 'user_id' => 41, 'type' => 'subscription_validated', 'balance_type' => 'subscription', 'amount_delta' => 225, 'points_delta' => 225, 'related_type' => 'subscription_validate', 'related_id' => 901, 'created_at' => '2026-03-26 14:00:02']);
        $wp->table(self::P.'hv_wallet_tx')->insert(['id' => 51, 'user_id' => 41, 'type' => 'subscription_validated', 'balance_type' => 'subscription', 'amount_delta' => 1215, 'points_delta' => 1350, 'related_type' => 'subscription_validate', 'related_id' => 901, 'created_at' => '2026-05-25 12:00:52']);
        // Ultima validare a clientului, cu expirarea calculata de WordPress.
        foreach (['hv_last_validated_submission_id' => 901, 'hv_last_validated_period_months' => 6, 'hv_subscription_expires_at' => '2026-11-25 12:00:52', 'hv_subscription_active' => 1] as $k => $v) {
            $wp->table(self::P.'usermeta')->insert(['user_id' => 41, 'meta_key' => $k, 'meta_value' => $v]);
        }

        // 902: neplatit, semnat demult. 903: payload cu HTML stricat (ghilimele neescapate) in ultimul camp.
        $wp->table(self::P.'posts')->insert(['ID' => 902, 'post_type' => 'hv_abon_sub', 'post_date' => '2026-02-16 13:26:23']);
        $pm(902, 'hv_payload', $payload('Bubu'));
        $pm(902, 'hv_client_user_id', 41);
        $wp->table(self::P.'posts')->insert(['ID' => 903, 'post_type' => 'hv_abon_sub', 'post_date' => '2026-02-17 09:00:00']);
        $broken = substr($payload('Pufi'), 0, -1).',"signature":"data:image/png;base64,AAAA","contract_preview_html":"<div class="contract">text</div>"}';
        $this->assertNull(json_decode($broken, true));
        $pm(903, 'hv_payload', $broken);
        $pm(903, 'hv_client_user_id', 41);

        $this->travelTo('2026-11-17 09:00:00');
        $dry = (new WordPressCoreImporter($this->source()))->run(true);
        $this->assertSame(0, count(array_filter($dry->errors ?? [], fn ($e) => isset($e['wp_post_id']) && $e['wp_post_id'] >= 901)));
        $this->assertSame(1, $dry->counts['contracts_payload_repaired']);

        (new WordPressCoreImporter($this->source()))->run(false);
        (new WordPressCoreImporter($this->source()))->run(false); // repetabil: istoricul nu se dubleaza

        $renewed = Contract::query()->where('wp_post_id', 901)->sole();
        $this->assertSame('active', $renewed->status->value);
        $this->assertSame('2026-11-25 12:00:52', $renewed->expires_at->format('Y-m-d H:i:s'));
        $this->assertSame(6, $renewed->period_months);
        $this->assertSame('2026-03-26', $renewed->starts_at->format('Y-m-d'));
        $notes = DB::table('contract_status_history')->where('contract_id', $renewed->id)->orderBy('created_at')->pluck('note')->all();
        $this->assertSame(['WordPress: contract semnat online', 'WordPress: plată validată la clinică, 225 lei, 1 lună (tranzacția #50)',
            'WordPress: reînnoire validată la clinică, 1215 lei, 6 luni (tranzacția #51)'], $notes);

        $this->assertSame('offer_lapsed', Contract::query()->where('wp_post_id', 902)->sole()->status->value);

        $repaired = Contract::query()->where('wp_post_id', 903)->sole();
        $this->assertSame('Pufi', $repaired->pet_name);
        $this->assertSame(225, $repaired->price_monthly);
        $this->assertSame($broken, $repaired->payload['_source_raw']);
        $this->assertStringContainsString('contract_preview_html', $repaired->payload['_import_note']);
        $this->assertArrayNotHasKey('signature', $repaired->payload);

        // Dupa expirare, intretinerea zilnica il trece singur pe expirat.
        $this->travelTo('2026-11-26 08:00:00');
        app(SubscriptionActivation::class)->runDailyMaintenance();
        $this->assertSame('expired', $renewed->refresh()->status->value);
    }

    public function test_contract_without_valid_client_is_reported_and_its_pdf_is_kept_in_the_archive(): void
    {
        $wp = DB::connection('wp');
        $wp->table(self::P.'posts')->insert(['ID' => 904, 'post_type' => 'hv_abon_sub', 'post_date' => '2026-03-06 10:00:00']);
        foreach (['hv_payload' => json_encode(['pet_name' => 'Rex', 'owner_email' => 'abcd', 'price_monthly' => 240]),
            'hv_pdf' => '/home/cont/public_html/wp-content/uploads/hikevet-contracte/contract_20260306_100000_zzz.pdf'] as $k => $v) {
            $wp->table(self::P.'postmeta')->insert(['post_id' => 904, 'meta_key' => $k, 'meta_value' => $v]);
        }
        file_put_contents($this->uploads.'/hikevet-contracte/contract_20260306_100000_zzz.pdf', '%PDF contract fara client');

        $batch = (new WordPressCoreImporter($this->source()))->run(false, null, $this->uploads);

        $this->assertSame(1, $batch->counts['contracts_without_client']);
        $this->assertNull(Contract::query()->where('wp_post_id', 904)->first());
        $this->assertStringContainsString('email invalid', json_encode($batch->errors, JSON_UNESCAPED_UNICODE));
        $file = LegacyFile::query()->where('kind', 'contract-fara-client')->sole();
        $this->assertSame(hash('sha256', '%PDF contract fara client'), $file->sha256);
        $this->assertStringContainsString('WP-904', $file->label);
    }

    public function test_imported_cases_become_live_with_links_translated_and_live_edits_survive_a_reimport(): void
    {
        (new WordPressCoreImporter($this->source()))->run(false);
        (new SterilizationImporter($this->source()))->run(false, $this->uploads);

        $case = SterilizationCase::query()->where('cod_caz', 'HV-ST-2026-002')->sole();
        $consent = Consent::query()->where('wp_post_id', 950)->sole();
        $appointment = SterilizationAppointment::query()->where('source_id', 1)->sole();
        $row = $case->row();
        // ID-urile WordPress (2, 950) devin ID-urile noi; randul original ramane neschimbat.
        $this->assertSame($case->id, $row['visit_group_id']);
        $this->assertSame($consent->id, $row['consent_id']);
        $this->assertSame($consent->id, $case->consent_id);
        $this->assertSame($case->id, $consent->refresh()->sterilization_case_id);
        $this->assertSame($case->id, $appointment->sterilization_case_id);
        $this->assertSame(950, (int) $case->source('consent_id'));
        $this->assertSame('Fără observații', $row['mentiuni_intra_postoperatorii']);

        // Dosarul se lucreaza in platforma noua, apoi WordPress il schimba si el: varianta noua ramane.
        $doctor = $this->staff(Role::Doctor);
        app(CampaignCases::class)->saveSection($case, 'corectare', ['rasa' => 'Europeana'], [], $doctor);
        DB::connection('wp')->table(self::P.'hv_st_cases')->where('id', 2)->update(['rasa' => 'Siameza']);
        $batch = (new SterilizationImporter($this->source()))->run(false, $this->uploads);

        $this->assertSame(1, $batch->counts['cases_kept_live_edits']);
        $this->assertSame('Europeana', $case->refresh()->row()['rasa']);
        $this->assertNotSame('Siameza', $case->source('rasa'), 'Randul importat initial ramane neschimbat.');
        $this->assertStringContainsString('platforma nouă', json_encode($batch->errors, JSON_UNESCAPED_UNICODE));
    }

    public function test_consent_visit_list_corrections_and_unreadable_payloads_are_kept(): void
    {
        $wp = DB::connection('wp');
        $pm = fn (int $id, string $k, $v) => $wp->table(self::P.'postmeta')->insert(['post_id' => $id, 'meta_key' => $k, 'meta_value' => $v]);
        $wp->table(self::P.'posts')->insert(['ID' => 951, 'post_type' => 'hv_consent', 'post_date' => '2026-08-05 08:00:00', 'post_modified' => '2026-08-05 08:00:00']);
        $pm(951, 'hv_consent_payload', serialize(['owner_name' => 'Proprietar Sintetic', 'pet_name' => 'Mura']));
        $pm(951, 'hv_consent_status', 'pending');
        $pm(951, 'hv_st_case_id', '2');
        $pm(951, 'hv_consent_visit_group_id', '2');
        $pm(951, 'hv_consent_visit_patients', serialize([['case_id' => 2, 'pet_name' => 'Mura'], ['case_id' => 77, 'pet_name' => 'Adăugat după']]));
        $pm(951, 'hv_consent_supersedes', '950');
        $pm(951, 'hv_consent_correction_reason', 'Reparare automată integritate raport Primărie');
        $pm(950, 'hv_consent_superseded_by', '951');
        $wp->table(self::P.'posts')->insert(['ID' => 952, 'post_type' => 'hv_consent', 'post_date' => '2026-08-05 08:00:00', 'post_modified' => '2026-08-05 08:00:00']);
        // Serializare deteriorata (lungimea nu corespunde): continutul se pastreaza exact si se raporteaza.
        $pm(952, 'hv_consent_payload', 'a:1:{s:10:"owner_name";s:3:"câine";}');
        $pm(952, 'hv_consent_status', 'signed');

        $batch = (new WordPressCoreImporter($this->source()))->run(false);
        (new SterilizationImporter($this->source()))->run(false, $this->uploads);

        $this->assertSame(1, $batch->counts['consents_payload_unreadable']);
        $this->assertStringContainsString('"wp_consent_id":952', json_encode($batch->errors));
        $broken = Consent::query()->where('wp_post_id', 952)->sole();
        $this->assertSame('a:1:{s:10:"owner_name";s:3:"câine";}', $broken->payload['_wordpress_payload_unreadable']);

        $case = SterilizationCase::query()->where('cod_caz', 'HV-ST-2026-002')->sole();
        $old = Consent::query()->where('wp_post_id', 950)->sole();
        $new = Consent::query()->where('wp_post_id', 951)->sole();
        $this->assertSame([$old->id, $new->id], [$new->supersedes_id, $old->superseded_by_id]);
        $this->assertSame('Reparare automată integritate raport Primărie', $new->correction_reason);
        $this->assertSame([$case->id, $case->id], [$new->sterilization_case_id, $new->visit_group_id]);
        $this->assertSame([$case->id, 0], array_column($new->visit_patients, 'case_id'), 'ID-urile dosarelor din vizita sunt traduse; cel inexistent ramane 0.');
        $this->assertSame([2, 77], array_column($new->visit_patients, 'wp_case_id'));
    }

    public function test_import_refuses_a_wordpress_copy_loaded_with_the_wrong_character_set(): void
    {
        DB::connection('wp')->table(self::P.'posts')->insert(['ID' => 960, 'post_type' => 'post', 'post_title' => 'Castrare cÃ¢ine', 'post_date' => '2026-08-05 08:00:00']);

        $this->artisan('hikevet:import-wordpress')->expectsOutputToContain('codate de două ori')->assertFailed();
        $this->assertSame(['wpx7_posts' => 1], $this->source()->doubleEncoding());
    }
}
