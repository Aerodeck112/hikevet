<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Controllers\Admin\CampaignController;
use App\Models\SterilizationAppointment;
use App\Models\SterilizationCase;
use App\Models\SterilizationDocument;
use App\Services\Campaign\CampaignAppointments;
use App\Services\Campaign\CampaignCases;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Ecranele si actiunile campaniei prin HTTP, cu permisiunile pe roluri. */
class CampaignScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBusiness();
        $this->travelTo('2026-11-20 09:00:00');
    }

    private function bookOne(): SterilizationCase
    {
        app(CampaignAppointments::class)->createForPatients(['owner_name' => 'Popescu Ana', 'client_phone' => '0740000001', 'client_email' => 'ana.sintetic@example.com',
            'appointment_date' => '2026-11-24'], [['patient_name' => 'Mitzi', 'appointment_type' => 'Sterilizare pisica', 'start_time' => '09:00', 'vaccinat' => 'Da']], $this->staff(Role::Doctor));

        return SterilizationCase::query()->sole();
    }

    public function test_every_tab_and_the_list_render_for_staff(): void
    {
        $case = $this->bookOne();
        $doctor = $this->staff(Role::Doctor);
        $this->actingAs($doctor)->get('/admin/campanie/dosare')->assertOk()->assertSee('HV-ST-2026-001')->assertSee('Mitzi');
        $this->actingAs($doctor)->get('/admin/campanie/dosare?q=Popescu&completeness=incomplete')->assertOk()->assertSee('Mitzi');
        foreach (['receptie', 'preoperator', 'interventie', 'externare', 'validare', 'corectare', 'rezumat', 'istoric'] as $tab) {
            $this->actingAs($doctor)->get('/admin/campanie/dosar?case='.$case->id.'&tab='.$tab)->assertOk()->assertSee('HV-ST-2026-001');
        }
        $this->actingAs($doctor)->get('/admin/campanie/dosar')->assertOk()->assertSee('Programare telefonică');
    }

    public function test_saving_a_section_over_http_with_a_photo_and_the_reception_cannot_validate(): void
    {
        $case = $this->bookOne();
        $reception = $this->staff(Role::Reception);

        $this->actingAs($reception)->post(route('admin.campaign.save'), [
            'case_id' => $case->id, 'section' => 'interventie',
            'data' => ['tip_anestezie' => 'Injectabila', 'substanta1' => 'S', 'medic_operator' => 'Dr. Anca Ion', 'mentiuni_intra_postoperatorii' => 'Fără observații', 'numar_pacient' => '7'],
            'poza_pacient_file' => UploadedFile::fake()->image('p.jpg', 400, 300),
        ])->assertRedirect()->assertSessionHas('campaign_saved')->assertSessionHas('campaign_number_saved', '7');
        $case->refresh();
        $this->assertSame('Finalizat', $case->status_interventie);
        $doc = SterilizationDocument::query()->where('sterilization_case_id', $case->id)->sole();
        $this->actingAs($reception)->get(route('admin.campaign.document', $doc))->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        $this->actingAs($reception)->post(route('admin.campaign.save'), ['case_id' => $case->id, 'section' => 'validare', 'data' => ['validat_pentru_primarie' => 'Da']])->assertForbidden();
        $this->actingAs($this->staff(Role::Editor))->get('/admin/campanie/dosar?case='.$case->id)->assertForbidden();
        $this->actingAs($this->client())->post(route('admin.campaign.save'), ['case_id' => $case->id, 'section' => 'corectare'])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->post(route('admin.campaign.save'), ['case_id' => $case->id])->assertRedirect();
    }

    public function test_missing_mandatory_notes_return_to_the_form_with_the_plugin_message(): void
    {
        $case = $this->bookOne();
        $this->actingAs($this->staff(Role::Doctor))->from('/admin/campanie/dosar?case='.$case->id.'&tab=interventie')
            ->post(route('admin.campaign.save'), ['case_id' => $case->id, 'section' => 'interventie', 'data' => ['tip_anestezie' => 'Injectabila']])
            ->assertRedirect('/admin/campanie/dosar?case='.$case->id.'&tab=interventie')->assertSessionHasErrors('mentiuni_intra_postoperatorii');
        $this->assertNull($case->refresh()->status_interventie);
    }

    public function test_phone_upload_works_only_with_the_case_token_and_freezes_the_report_number(): void
    {
        $case = $this->bookOne();
        $token = $case->photo_upload_token;
        $this->get('/campanie/foto/'.str_repeat('x', 40))->assertNotFound();
        $this->get('/campanie/foto/'.$token)->assertOk()->assertSee('Mitzi')->assertSee('Deschide camera')->assertHeader('X-Robots-Tag', 'noindex');

        $this->post('/campanie/foto/'.$token, ['photo' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])->assertSessionHasErrors('photo');
        $this->post('/campanie/foto/'.$token, ['photo' => UploadedFile::fake()->image('cam.jpg', 640, 480)])->assertRedirect(route('campaign.phone', ['token' => $token, 'uploaded' => 1]));

        $row = $case->refresh()->row();
        $this->assertSame('Telefon QR', $row['photo_upload_source']);
        $this->assertSame('confirmat_foto_qr', $row['numar_pacient_raport_sursa']);
        $this->assertSame('1', $row['numar_pacient_raport']);
        $this->actingAs($this->staff(Role::Doctor))->getJson(route('admin.campaign.photo-status', $case))->assertOk()->assertJsonPath('source', 'Telefon QR');
    }

    public function test_visit_pet_and_delete_actions_from_the_screen(): void
    {
        $case = $this->bookOne();
        $doctor = $this->staff(Role::Doctor);
        $this->actingAs($doctor)->post(route('admin.campaign.visit-pet', $case), ['visit_pet' => ['patient_name' => 'Tom', 'species' => 'Pisica', 'sex' => 'Mascul']])
            ->assertRedirect()->assertSessionHas('campaign_message');
        $this->assertSame(2, SterilizationCase::query()->active()->count());

        $this->actingAs($doctor)->post(route('admin.campaign.delete', $case), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($doctor)->post(route('admin.campaign.delete', $case), ['reason' => 'Programare dublată'])->assertRedirect('/admin/campanie/dosare');
        $this->assertNotNull($case->refresh()->deleted_at);
    }

    public function test_calendar_booking_edit_cancel_and_start_consultation_over_http(): void
    {
        $first = $this->bookOne();
        $reception = $this->staff(Role::Reception);
        $this->actingAs($reception)->get('/admin/campanie/programari?week=2026-11-24')->assertOk()->assertSee('Mitzi')->assertSee('Agenda săptămânii');
        $this->actingAs($reception)->get('/admin/campanie/programari?new=1&date=2026-11-25&owner_case='.$first->id)->assertOk()->assertSee('Programare nouă')->assertSee('Popescu Ana');
        $this->actingAs($reception)->getJson(route('admin.campaign.appointments.availability', ['date' => '2026-11-24']))->assertOk()->assertJsonPath('count', 1);

        // Acelasi proprietar, doi pacienti noi: datele legale se preiau din dosarul lui.
        $first = app(CampaignCases::class)->saveSection($first, 'receptie', ['adresa_ci' => 'Str. Exemplu 10', 'serie_numar_ci' => 'MS 1', 'localitate' => 'Targu Mures'], [], $reception)['case'];
        $this->actingAs($reception)->post(route('admin.campaign.appointments.store'), [
            'appointment' => ['owner_name' => 'Popescu Ana', 'client_phone' => '0740000001', 'client_email' => 'ana.sintetic@example.com', 'appointment_date' => '2026-11-25', 'owner_case_id' => $first->id],
            'patients' => [['patient_name' => 'Bubu', 'appointment_type' => 'Castrare caine', 'start_time' => '10:00', 'vaccinat' => 'Nu'],
                ['patient_name' => 'Lola', 'appointment_type' => 'Sterilizare catea', 'start_time' => '11:00', 'vaccinat' => 'Da']],
        ])->assertRedirect()->assertSessionHas('campaign_message');
        $bubu = SterilizationCase::query()->where('nume_pacient', 'Bubu')->sole();
        $this->assertSame('Str. Exemplu 10', $bubu->row()['adresa_ci']);
        $this->assertSame('Castrare câine', $bubu->procedura_programata);

        $a = SterilizationAppointment::query()->where('patient_name', 'Bubu')->sole();
        $this->actingAs($reception)->get('/admin/campanie/programari?week=2026-11-25&appointment='.$a->id)->assertOk()->assertSee($a->appointment_code);
        $this->actingAs($reception)->post(route('admin.campaign.appointments.update', $a), ['appointment' => ['appointment_date' => '2026-11-26', 'start_time' => '12:30',
            'appointment_type' => 'Castrare caine', 'owner_name' => 'Popescu Ana', 'client_phone' => '0740000001', 'client_email' => 'ana.sintetic@example.com', 'patient_name' => 'Bubu']])->assertRedirect();
        $this->assertSame(['2026-11-26', '12:30'], [$bubu->refresh()->row()['data_programare'], $bubu->row()['ora_programare']]);

        $this->actingAs($reception)->post(route('admin.campaign.appointments.consultation', $a))->assertRedirect(CampaignController::caseUrl($bubu->id, 'preoperator'));
        $this->assertSame('Prezentat', $bubu->refresh()->status_caz);
        $this->actingAs($reception)->post(route('admin.campaign.appointments.cancel', $a))->assertRedirect();
        $this->assertSame('Anulat', $a->refresh()->status);
        $this->actingAs($this->staff(Role::Editor))->post(route('admin.campaign.appointments.store'), [])->assertForbidden();
    }
}
