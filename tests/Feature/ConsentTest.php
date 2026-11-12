<?php

namespace Tests\Feature;

use App\Enums\ConsentStatus;
use App\Enums\Role;
use App\Models\Consent;
use App\Services\Consents\ConsentService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Fluxul modulului hikevet-consent-tablet 1.4.1, cu intaririle de securitate adaugate. */
class ConsentTest extends TestCase
{
    use RefreshDatabase;

    private ConsentService $consents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBusiness();
        $this->consents = app(ConsentService::class);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['owner_name' => 'Ionescu Test', 'owner_phone' => '0700000002', 'owner_cnp' => '2900101000000', 'owner_ci' => 'ZZ 000001',
            'pet_name' => 'Luna', 'pet_species_breed' => 'Pisica / Europeana', 'procedure' => 'Ovariohisterectomie', 'vet_name' => 'Dr. Test Medic'], $overrides);
    }

    private function signInput(array $overrides = []): array
    {
        return array_merge(['food_status' => 'nu_hrana', 'food_detail' => '20:00', 'media_medical' => 'da', 'media_education' => 'nu', 'media_publication' => 'nu',
            'media_data_public' => 'nu', 'owner_final_gdpr' => 1, 'owner_final_read' => 1, 'owner_final_risk' => 1, 'owner_final_lucid' => 1,
            'owner_signature' => $this->signature()], $overrides);
    }

    public function test_tablet_flow_code_opens_document_and_signature_locks_it(): void
    {
        [$consent, $code] = $this->consents->create($this->payload(), $this->staff());

        $this->post('/consimtamant-tableta/cod', ['code' => $code])->assertRedirect(route('tablet.document'));
        $this->get(route('tablet.document'))->assertOk()->assertSee('CONSIMTAMANT PENTRU ACCEPTAREA PROCEDURILOR MEDICALE')->assertSee('Ionescu Test')->assertSee('Ovariohisterectomie');
        $this->post('/consimtamant-tableta/semnare', $this->signInput())->assertRedirect(route('tablet'));

        $consent->refresh();
        $this->assertSame(ConsentStatus::Signed, $consent->status);
        $this->assertNotNull($consent->signed_at);
        $this->assertNull($consent->code_hash, 'Codul nu mai poate fi refolosit dupa semnare.');
        $this->assertSame(1, $consent->payload['owner_final_gdpr']);
        $this->assertTrue($this->consents->verifyIntegrity($consent));

        // Documentul semnat nu mai poate fi deschis, semnat sau modificat.
        $this->post('/consimtamant-tableta/cod', ['code' => $code])->assertSessionHasErrors('code');
        $this->expectException(DomainException::class);
        $this->consents->update($consent, $this->payload(['owner_name' => 'Alt Nume']));
    }

    public function test_code_default_anesthesia_and_default_date(): void
    {
        [$consent, $code] = $this->consents->create($this->payload(), $this->staff());

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertSame(['anestezie_generala_injectabila'], $consent->payload['anesthesia']);
        $this->assertSame(now()->toDateString(), $consent->payload['consent_date']);
        $this->assertStringNotContainsString($code, json_encode(DB::table('consents')->first()));
    }

    public function test_gdpr_and_every_final_confirmation_are_mandatory(): void
    {
        foreach (['owner_final_gdpr', 'owner_final_read', 'owner_final_risk', 'owner_final_lucid'] as $key) {
            [$consent] = $this->consents->create($this->payload(), $this->staff());
            try {
                $this->consents->sign($consent, $this->signInput([$key => 0]));
                $this->fail("Semnarea fara $key ar fi trebuit refuzata.");
            } catch (DomainException) {
                $this->assertSame(ConsentStatus::Pending, $consent->refresh()->status);
            }
        }
    }

    public function test_refused_services_require_a_separate_refusal_signature(): void
    {
        [$consent] = $this->consents->create($this->payload(['refused_services' => ['ecografie']]), $this->staff());

        try {
            $this->consents->sign($consent, $this->signInput());
            $this->fail('Lipsa semnaturii de refuz ar fi trebuit sa opreasca semnarea.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('refuz informat', $e->getMessage());
        }
        $signed = $this->consents->sign($consent, $this->signInput(['refusal_signature' => $this->signature()]));
        $this->assertNotNull($signed->refusal_signature);
    }

    public function test_wrong_expired_and_unknown_codes_are_rejected_with_the_same_message(): void
    {
        [$consent, $code] = $this->consents->create($this->payload(), $this->staff());

        $this->post('/consimtamant-tableta/cod', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->travel(13)->hours();
        $this->post('/consimtamant-tableta/cod', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertNull($this->consents->findByCode($code));

        $fresh = $this->consents->regenerateCode($consent->refresh());
        $this->assertNotNull($this->consents->findByCode($fresh));
        $this->assertNull($this->consents->findByCode($code));
    }

    public function test_code_entry_is_rate_limited(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->post('/consimtamant-tableta/cod', ['code' => '111111']);
        }
        $this->post('/consimtamant-tableta/cod', ['code' => '111111'])->assertStatus(429);
    }

    public function test_doctor_signature_can_be_added_after_owner_signed_without_touching_the_document(): void
    {
        [$consent] = $this->consents->create($this->payload(), $this->staff());
        $signed = $this->consents->sign($consent, $this->signInput());
        $fingerprint = $signed->document_sha256;
        $ownerSignature = $signed->owner_signature;
        $doctor = $this->staff();

        $this->consents->doctorSign($signed, $this->signature(), $doctor);
        $signed->refresh();

        $this->assertNotNull($signed->doctor_signature);
        $this->assertSame($doctor->id, $signed->doctor_signed_by);
        $this->assertSame($fingerprint, $signed->document_sha256);
        $this->assertSame($ownerSignature, $signed->owner_signature);
        $this->assertTrue($this->consents->verifyIntegrity($signed));
    }

    public function test_tampering_with_a_signed_payload_is_detected(): void
    {
        [$consent] = $this->consents->create($this->payload(), $this->staff());
        $signed = $this->consents->sign($consent, $this->signInput());

        $payload = $signed->payload;
        $payload['procedure'] = 'Alta procedura';
        $signed->payload = $payload;
        $signed->save();

        $this->assertFalse($this->consents->verifyIntegrity($signed->refresh()));
    }

    public function test_shared_visit_consent_lists_all_patients_and_correction_creates_a_new_document(): void
    {
        [$consent, $code] = $this->consents->create($this->payload(['pet_name' => '', 'visit_patients' => [
            ['pet_name' => 'Luna', 'species' => 'Pisica', 'sex' => 'femela', 'procedure' => 'Sterilizare pisica'],
            ['pet_name' => 'Tom', 'species' => 'Pisica', 'sex' => 'mascul', 'procedure' => 'Castrare motan'],
        ]]), $this->staff());

        $this->assertTrue($consent->isMultiPatient());
        $this->assertSame('Luna, Tom', $consent->pet_name);
        $this->post('/consimtamant-tableta/cod', ['code' => $code]);
        $this->get(route('tablet.document'))->assertSee('Pacientii inclusi in aceasta vizita')->assertSee('Castrare motan');

        $signed = $this->consents->sign($consent, $this->signInput());
        [$corrected] = $this->consents->supersede($signed, array_merge($signed->payload, ['owner_name' => 'Ionescu Corectat']), $this->staff());

        $this->assertSame($signed->id, $corrected->supersedes_id);
        $this->assertSame(ConsentStatus::Pending, $corrected->status);
        $this->assertSame('Ionescu Test', $signed->refresh()->payload['owner_name'], 'Documentul semnat ramane neschimbat.');
        $this->assertSame(2, Consent::count());
    }

    public function test_admin_screens_and_print_view_respect_permissions(): void
    {
        [$consent] = $this->consents->create($this->payload(), $this->staff());
        $reception = $this->staff(Role::Reception);

        $this->actingAs($reception)->get('/admin/consents')->assertOk();
        $this->actingAs($reception)->get('/admin/consents/create')->assertOk();
        $this->actingAs($reception)->get('/admin/consents/'.$consent->id)->assertOk()->assertSee('Ionescu Test');
        $this->actingAs($reception)->get('/admin/consents/'.$consent->id.'/edit')->assertOk();
        $this->actingAs($reception)->get(route('admin.consents.print', $consent))->assertOk()->assertSee('Identificare generată automat');
        $this->actingAs($this->staff(Role::Editor))->get('/admin/consents')->assertForbidden();
        $this->actingAs($this->staff(Role::Editor))->get(route('admin.consents.print', $consent))->assertForbidden();

        $this->consents->sign($consent, $this->signInput());
        $this->actingAs($reception)->get('/admin/consents/'.$consent->id.'/edit')->assertForbidden();
        $this->actingAs($reception)->get('/admin/contracts')->assertOk();
    }
}
