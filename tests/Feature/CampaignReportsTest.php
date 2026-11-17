<?php

namespace Tests\Feature;

use App\Enums\ConsentStatus;
use App\Enums\Role;
use App\Models\Consent;
use App\Models\SterilizationCase;
use App\Models\SterilizationDocument;
use App\Models\User;
use App\Services\Campaign\CampaignCases;
use App\Services\Campaign\CampaignFiles;
use App\Services\Campaign\CaseFields;
use App\Services\Campaign\CityExports;
use App\Services\Campaign\CityReports;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/** Raportul pentru Primarie (desfasurator, centralizator, ZIP, audit, control numere), pe date sintetice. */
class CampaignReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $doctor;

    private int $size = 300;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBusiness();
        $this->doctor = $this->staff(Role::Doctor);
        $this->travelTo('2026-11-20 09:00:00');
    }

    private function reports(): CityReports
    {
        return app(CityReports::class);
    }

    /** Un dosar finalizat complet (sau cu lipsurile cerute), scris prin serviciul campaniei. */
    private function finalCase(array $over = [], bool $photo = true, bool $ci = true, bool $consent = true, ?string $photoName = null): SterilizationCase
    {
        $cases = app(CampaignCases::class);
        $row = array_merge(CaseFields::defaults(), [
            'cod_caz' => $cases->nextCode(), 'nume_proprietar' => 'Popescu Ana', 'telefon' => '0740000001', 'adresa_ci' => 'Str. Exemplu 10, Targu Mures',
            'localitate' => 'Targu Mures', 'serie_numar_ci' => 'MS 123456', 'nume_pacient' => 'Pisi '.Str::random(4), 'specie' => 'Pisica', 'sex' => 'Femela', 'rasa' => 'Europeana',
            'greutate_reala' => '3.5', 'tip_anestezie' => 'Injectabila', 'substanta1' => 'Ketamina', 'doza1' => '0.2', 'medic_operator' => 'Dr. Festeu Radu',
            'mentiuni_intra_postoperatorii' => 'Fără complicații', 'procedura_efectuata' => 'OVH pisică', 'status_interventie' => 'Finalizat',
            'data_interventie' => '2026-09-10', 'timestamp_interventie' => '2026-09-10 12:00:00',
        ], $over);
        $case = $cases->persist(null, $row, $this->doctor, 'created', null, null, ! isset($over['numar_pacient']));
        $row = $case->row();
        $files = app(CampaignFiles::class);
        if ($photo) {
            $row['poza_pacient'] = $files->store($case, UploadedFile::fake()->image($photoName ?? 'p.jpg', $photoName ? 50 : $this->size++, 40), SterilizationDocument::PHOTO, 'test', $this->doctor);
        }
        if ($ci) {
            $row['documente_scanate'] = $files->store($case, UploadedFile::fake()->image('ci.jpg', 60, $this->size++), SterilizationDocument::ID_CARD, 'test', $this->doctor);
        }
        if ($consent) {
            $c = Consent::query()->create(['token' => Str::random(40), 'status' => ConsentStatus::Signed, 'origin' => 'sterilization', 'signed_at' => now(),
                'payload' => ['owner_name' => $row['nume_proprietar'], 'pet_name' => $row['nume_pacient'], 'procedure' => 'OVH pisică', 'vet_name' => 'Dr. Festeu Radu',
                    'owner_final_gdpr' => 1, 'consent_date' => '2026-09-10'],
                'owner_signature' => 'data:image/png;base64,'.base64_encode(UploadedFile::fake()->image('s.png', 40, 20)->getContent()),
                'sterilization_case_id' => $case->id]);
            $row['consent_id'] = $row['shared_consent_id'] = $c->id;
        }

        return $cases->persist($case, $row, $this->doctor, 'test', null, null, false);
    }

    public function test_month_selection_uses_the_fixed_reporting_date_and_orders_by_report_number(): void
    {
        $a = $this->finalCase(['numar_pacient' => '12', 'data_interventie' => '2026-09-03']);
        // Data interventiei corectata ulterior in octombrie: ramane in luna in care a fost finalizata.
        $b = $this->finalCase(['numar_pacient' => '7', 'data_interventie' => '2026-10-02', 'timestamp_interventie' => '2026-09-29 10:00:00']);
        $this->finalCase(['numar_pacient' => '30', 'status_interventie' => 'Programat', 'timestamp_interventie' => '']);

        $prepared = $this->reports()->prepare('2026-09', $this->doctor);
        $rows = $this->reports()->finalizedRows('2026-09');

        $this->assertSame([$b->id, $a->id], array_column($rows, 'id'));
        $this->assertSame('2026-09-29', $b->fresh()->row()['data_raportare_primarie']);
        $this->assertSame('7', $b->fresh()->row()['numar_pacient_raport']);
        // Numerele introduse manual nu se ingheata la salvare; raportul le fixeaza o singura data.
        $this->assertSame(2, count($prepared['frozen_current']));
        $this->assertSame('backfill_curent', $b->fresh()->row()['numar_pacient_raport_sursa']);
        $this->assertSame(0, count($this->reports()->prepare('2026-09', $this->doctor)['frozen_current']));
        $this->assertDatabaseHas('sterilization_case_changes', ['sterilization_case_id' => $b->id, 'action' => 'report_date_fixed']);
        $this->assertSame('09.2026', $this->reports()->reportMap($rows)[0]['LunaRaportare']);
    }

    public function test_october_uses_the_real_intervention_date_and_excludes_previously_reported_cases(): void
    {
        $reported = $this->finalCase(['data_interventie' => '2026-10-05']);
        $new = $this->finalCase(['data_interventie' => '2026-10-06']);
        $this->finalCase(['data_interventie' => '2026-09-30', 'data_raportare_primarie' => '2026-10-01']);

        try {
            $this->reports()->finalizedRows('2026-10');
            $this->fail('Fara lista de excludere, octombrie nu se raporteaza.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('lipsește', $e->getMessage());
        }

        DB::table('sterilization_reference_lists')->insert(['list_key' => 'reported_through_september_2026', 'cod_caz' => $reported->cod_caz, 'value' => '2026-09']);
        $this->assertSame([$new->id], array_column($this->reports()->finalizedRows('2026-10'), 'id'));
        $this->assertCount(2, $this->reports()->finalizedRows('2026-10', false));

        $codes = $this->reports()->priorCodes();
        $this->expectExceptionMessage('modificată între timp');
        $this->reports()->savePriorCodes("HV-ST-2026-999;2026-08\n", 'hash-vechi', $this->doctor);
    }

    public function test_prior_list_is_saved_with_the_previous_version_kept(): void
    {
        DB::table('sterilization_reference_lists')->insert(['list_key' => 'reported_through_september_2026', 'cod_caz' => 'HV-ST-2026-001', 'value' => '2026-07']);
        $hash = CityReports::listHash($this->reports()->priorCodes());

        $this->assertSame(2, $this->reports()->savePriorCodes("hv-st-2026-002;2026-08\nHV-ST-2026-003\n", $hash, $this->doctor));
        $this->assertSame(['HV-ST-2026-002' => '2026-08', 'HV-ST-2026-003' => '2026-09'], $this->reports()->priorCodes());
        $this->assertSame(['HV-ST-2026-001' => '2026-07'], Settings::get('campaign.prior_reports_previous')['codes']);

        $this->expectExceptionMessage('Linie invalidă');
        $this->reports()->savePriorCodes('HV-ST-2025-1', CityReports::listHash($this->reports()->priorCodes()), $this->doctor);
    }

    public function test_official_excel_has_the_25_columns_and_no_internal_status(): void
    {
        $this->finalCase(['numar_pacient' => '5', 'nume_pacient' => 'Mițu', 'greutate_reala' => '3.5']);
        $mapped = $this->reports()->reportMap($this->reports()->finalizedRows('2026-09'));
        $path = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($path, app(CityExports::class)->xlsx($mapped, 'Septembrie 2026'));
        $zip = new ZipArchive;
        $zip->open($path);
        $sheet = $zip->getFromName('xl/worksheets/sheet2.xml');
        $summary = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        $this->assertSame(25, substr_count(explode('<row r="9"', $sheet)[0], 's="6" t="inlineStr"'));
        $this->assertStringContainsString('Mițu', $sheet);
        $this->assertStringContainsString('<c r="O9" s="7"><v>3.5</v></c>', $sheet);
        $this->assertStringNotContainsString('Validat', $sheet);
        $this->assertStringContainsString('Toate intervențiile finalizate din perioada selectată', $sheet);
        $this->assertStringContainsString('Primăria Municipiului Târgu Mureș', $summary);
    }

    public function test_centralizator_counts_categories_and_refuses_unclear_species_or_sex(): void
    {
        $this->finalCase(['specie' => 'Caine', 'sex' => 'Mascul']);
        $this->finalCase(['specie' => 'Câine', 'sex' => 'Femelă']);
        $this->finalCase();
        $rows = $this->reports()->finalizedRows('2026-09');
        $summary = CityReports::animalSummary($rows);
        $this->assertSame([1, 1, 0, 1, 3], [$summary['CAINE'], $summary['CATEA'], $summary['MOTAN'], $summary['PISICA'], $summary['total']]);
        $this->assertStringStartsWith('%PDF', app(CityExports::class)->centralizatorPdf($rows, 'Septembrie 2026'));

        $this->finalCase(['specie' => 'Iepure']);
        $this->expectExceptionMessage('nu pot fi încadrați');
        app(CityExports::class)->centralizatorPdf($this->reports()->finalizedRows('2026-09'), 'Septembrie 2026');
    }

    public function test_integrity_audit_finds_duplicates_reused_photos_missing_documents_and_patients_added_after_signing(): void
    {
        $this->finalCase(['numar_pacient' => '40', 'data_interventie' => '2026-09-01']);
        $dup = $this->finalCase(['numar_pacient' => '41', 'data_interventie' => '2026-09-02']);
        // Acelasi numar intr-un alt an nu este dublura; aici fortam dublura in raport.
        $row = $dup->row();
        $row['numar_pacient_raport'] = '40';
        app(CampaignCases::class)->persist($dup, $row, $this->doctor, 'test', null, null, false);
        $this->finalCase(['nume_pacient' => 'Alfa'], photoName: 'same.jpg');
        $this->finalCase(['nume_pacient' => 'Beta'], photoName: 'same.jpg');
        $noDocs = $this->finalCase(['adresa_ci' => '1234567890123'], photo: false, ci: false, consent: false);
        $added = $this->finalCase(['nume_pacient' => 'Fulgușor']);
        $consent = Consent::query()->find($added->row()['consent_id']);
        $consent->forceFill(['payload' => ['pet_name' => 'Tomiță'] + $consent->payload, 'visit_patients' => [['pet_name' => 'Tomiță'], ['pet_name' => 'Fulgușor']]])->save();

        $audit = $this->reports()->integrityAudit($this->reports()->finalizedRows('2026-09'));
        $codes = collect($audit['items'])->flatMap(fn ($i) => array_column($i['issues'], 'code'))->countBy()->all();

        $this->assertSame(2, $codes['patient_number_duplicate']);
        $this->assertSame(2, $codes['photo_duplicate']);
        $this->assertSame(1, $codes['address_invalid']);
        $this->assertSame(1, $codes['id_missing']);
        $this->assertSame(1, $codes['photo_missing']);
        $this->assertSame(1, $codes['consent_missing']);
        $this->assertSame(1, $codes['consent_patient_added_after_signing']);
        $item = collect($audit['items'])->firstWhere('row.id', $noDocs->id);
        $this->assertSame('corectare', collect($item['issues'])->firstWhere('code', 'address_invalid')['tab']);

        $scan = $this->reports()->scan($this->reports()->finalizedRows('2026-09'));
        $this->assertTrue(collect($scan)->every('zip_included'), 'Toate interventiile finalizate intra in ZIP.');
        $this->assertSame(0, CityReports::scanSummary($scan)['ready_export']);
    }

    public function test_city_zip_contains_every_finalized_case_with_missing_document_markers(): void
    {
        $complete = $this->finalCase(['nume_pacient' => 'Mițu Mic']);
        $this->finalCase(['nume_pacient' => 'Fără Acte'], photo: false, ci: false, consent: false);
        $rows = $this->reports()->finalizedRows('2026-09');

        $package = app(CityExports::class)->cityZip($rows, '2026-09');
        $zip = new ZipArchive;
        $zip->open($package['path']);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        $this->assertSame('Interventii_Finalizate_Primarie_2026-09.zip', $package['name']);
        $this->assertContains('00_Desfasurator_HiKeVet.xlsx', $names);
        $this->assertContains('01_Desfasurator_HiKeVet.pdf', $names);
        $this->assertContains('02_Centralizator_General_HiKeVet.pdf', $names);
        $first = 'Dosare/001_'.$complete->cod_caz.'_Mitu-Mic/';
        $files = array_map(fn ($n) => substr($n, strlen($first)), array_values(array_filter($names, fn ($n) => str_starts_with($n, $first) && $n !== $first)));
        sort($files);
        $this->assertSame(['00_Manifest_dosar.txt', '01_Fisa_pacient.pdf', '02_Poza_pacient.jpg', '03_Consimtamant_semnat.pdf', '04_Act_identitate.jpg'], $files);
        $second = collect($names)->first(fn ($n) => str_contains($n, '_Fara-Acte/03_Consimtamant_LIPSA.txt'));
        $this->assertNotNull($second);
        $this->assertSame("Consimțământul digital semnat lipsește sau nu este finalizat.\n", $zip->getFromName($second));
        $this->assertStringContainsString('Numar pacient: '.$complete->row()['numar_pacient'], $zip->getFromName($first.'00_Manifest_dosar.txt'));
        $this->assertStringStartsWith('%PDF', $zip->getFromName($first.'03_Consimtamant_semnat.pdf'));
        $zip->close();
        unlink($package['path']);
        $this->assertSame([], glob(storage_path('app/private/tmp/hv-*'), GLOB_ONLYDIR) ?: [], 'Fisierele temporare se sterg.');
    }

    public function test_single_case_zip_lists_missing_items_in_the_manifest(): void
    {
        $case = $this->finalCase(['numar_pacient' => '9', 'nume_pacient' => 'Rex'], ci: false);
        $package = app(CityExports::class)->singleCaseZip($case);
        $zip = new ZipArchive;
        $zip->open($package['path']);
        $folder = 'Dosar_Pacient_9_'.$case->cod_caz.'_Rex/';
        $manifest = $zip->getFromName($folder.'00_Manifest_dosar.txt');

        $this->assertSame('Dosar_HiKeVet_Pacient_9_'.$case->cod_caz.'.zip', $package['name']);
        $this->assertStringContainsString("Dosar complet: Nu\n", $manifest);
        $this->assertStringContainsString('- poză / scan CI proprietar', $manifest);
        $this->assertSame("Poza / scanul actului de identitate lipsește din dosar.\n", $zip->getFromName($folder.'04_Act_identitate_LIPSA.txt'));
        $zip->close();
        unlink($package['path']);
    }

    public function test_photo_confirmation_registry_and_alignment(): void
    {
        $a = $this->finalCase(['numar_pacient' => '1']);
        $b = $this->finalCase(['numar_pacient' => '3']);
        $c = $this->finalCase(['numar_pacient' => '92']);
        $this->reports()->markPhotoChecked($a, $this->doctor);
        $this->reports()->markPhotoChecked($b, $this->doctor);
        $this->reports()->markPhotoChecked($c, $this->doctor);
        $this->assertSame('1', $a->fresh()->row()['numar_pacient_foto_confirmat']);
        $this->assertSame($this->doctor->name, $a->fresh()->row()['numar_pacient_foto_verificat_de']);

        // Fotografia arata alt numar decat cel din dosar: alinierea il ia pe cel din fotografie.
        $row = $b->fresh()->row();
        $row['numar_pacient_foto_confirmat'] = '2';
        app(CampaignCases::class)->persist($b->fresh(), $row, $this->doctor, 'test', null, null, false);

        $registry = $this->reports()->registryMatrix();
        $this->assertSame(92, $registry['max']);
        $this->assertNotContains(92, $registry['missing']);
        $this->assertContains(3, $registry['missing']);
        $this->assertNotContains(214, $registry['missing']);
        $this->assertSame([92], array_keys($registry['voidConflicts']), '92 este scos din uz implicit, dar are fotografie.');

        $result = $this->reports()->alignFromPhoto($this->doctor);
        $this->assertSame(['updated' => 1, 'already_aligned' => 1, 'skipped_duplicate' => 0, 'skipped_void' => 1, 'errors' => 0], $result);
        $aligned = $b->fresh()->row();
        $this->assertSame(['2', '2', '3', 'aliniat_dupa_foto_verificata'], [$aligned['numar_pacient'], $aligned['numar_pacient_raport'], $aligned['numar_pacient_vechi'], $aligned['numar_pacient_raport_sursa']]);
        $this->assertSame([1, 2, 3, 5, 6, 7], CityReports::parseNumbers('7, 1-3; 5–6'));
    }

    public function test_verification_control_finds_photos_that_are_not_in_the_report(): void
    {
        $this->finalCase(['numar_pacient' => '1']);
        $pending = $this->finalCase(['numar_pacient' => '2', 'status_interventie' => 'Programat', 'data_raportare_primarie' => '2026-09-12']);

        $rows = $this->reports()->verificationRows('2026-09');
        $summary = CityReports::verificationSummary($rows);
        $this->assertSame([2, 1, 1, 2], [$summary['total'], $summary['in_report'], $summary['photo_not_report'], $summary['unverified_photo']]);
        $this->assertSame([$pending->id], array_column(CityReports::filterVerification($rows, ['status' => 'photo_not_report']), 'id'));

        $result = $this->reports()->bulkMarkPhotoChecked('2026-09', ['status' => 'photo_not_report'], $this->doctor);
        $this->assertSame(1, $result['updated']);
        $this->assertSame('2', $pending->fresh()->row()['numar_pacient_foto_confirmat']);
    }

    public function test_bulk_validation_touches_only_complete_cases(): void
    {
        $complete = $this->finalCase();
        $incomplete = $this->finalCase(photo: false);

        $result = $this->reports()->bulkValidate('Dr. Festeu Radu', $this->doctor);

        $this->assertSame(1, $result['validated']);
        $row = $complete->fresh()->row();
        $this->assertSame(['Da', 'Dr. Festeu Radu', '2026-11-20', 'Validat primarie'], [$row['validat_pentru_primarie'], $row['validat_de'], $row['data_validare'], $row['status_caz']]);
        $this->assertStringContainsString('Validat automat după verificarea completitudinii dosarului de Dr. Festeu Radu.', $row['observatii_validare']);
        $this->assertNotSame('Da', $incomplete->fresh()->row()['validat_pentru_primarie']);
        $this->assertArrayNotHasKey('adresa_ci_valida', $complete->fresh()->data, 'Starile calculate nu se salveaza in dosar.');
    }

    public function test_september_selection_requires_the_exact_reviewed_list(): void
    {
        $this->assertStringContainsString('exact 145', $this->reports()->september145()['error']);
        foreach (range(1, 145) as $n) {
            DB::table('sterilization_reference_lists')->insert(['list_key' => 'september_2026_145', 'cod_caz' => sprintf('HV-ST-2026-%03d', $n), 'value' => (string) $n]);
        }
        $this->assertStringContainsString('HV-ST-2026-001: dosar lipsă', $this->reports()->september145()['error']);
    }

    public function test_report_screens_and_export_permissions(): void
    {
        $this->finalCase(['numar_pacient' => '3']);
        $admin = $this->staff(Role::SuperAdmin);
        $reception = $this->staff(Role::Reception);

        $this->actingAs($admin)->get('/admin/campanie/raport-primarie?month=2026-09')->assertOk()->assertSee('Audit integritate înainte de Primărie')->assertSee('Registru numere pacienți');
        $this->actingAs($admin)->get('/admin/campanie/raport/excel?month=2026-09')->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="Desfasurator_HiKeVet_2026-09.xlsx"');
        $this->actingAs($admin)->get('/admin/campanie/raport/pdf?month=2026-09')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($admin)->get('/admin/campanie/raport/reconciliere/2026-07.pdf')->assertOk();
        $this->actingAs($admin)->get('/admin/campanie/raport/excel?selection=altceva')->assertRedirect()->assertSessionHas('campaign_error');
        $this->assertDatabaseHas('audit_logs', ['action' => 'sterilization.exported']);

        // Receptia lucreaza in raport, dar nu descarca date personale in masa si nu valideaza.
        $this->actingAs($reception)->get('/admin/campanie/raport-primarie?month=2026-09')->assertOk()->assertDontSee('Excel desfășurător');
        $this->actingAs($reception)->get('/admin/campanie/raport/excel?month=2026-09')->assertForbidden();
        $this->actingAs($reception)->post('/admin/campanie/raport/validare-in-masa')->assertForbidden();
        $this->actingAs($reception)->post('/admin/campanie/raport/aliniaza-dupa-foto')->assertForbidden();
        $case = SterilizationCase::query()->first();
        $this->actingAs($reception)->post('/admin/campanie/raport/dosar/'.$case->id.'/foto-verificata', ['month' => '2026-09'])->assertRedirect()->assertSessionHas('campaign_message');
    }
}
