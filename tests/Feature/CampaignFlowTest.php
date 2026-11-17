<?php

namespace Tests\Feature;

use App\Enums\ConsentStatus;
use App\Enums\Role;
use App\Models\Consent;
use App\Models\SterilizationCase;
use App\Models\SterilizationCaseChange;
use App\Models\SterilizationEmailLog;
use App\Models\User;
use App\Services\Campaign\CampaignAppointments;
use App\Services\Campaign\CampaignCases;
use App\Services\Campaign\CampaignMailer;
use App\Services\Campaign\CityChecks;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

/** Fluxul activ al campaniei de sterilizare, pe date sintetice. */
class CampaignFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBusiness();
        // Fluxul activ se testeaza cu campania pornita; implicit este oprita (doar istoric).
        Settings::set('campaign.active', true);
        $this->doctor = $this->staff(Role::Doctor);
        $this->travelTo('2026-11-20 09:00:00');
    }

    private function cases(): CampaignCases
    {
        return app(CampaignCases::class);
    }

    private function book(array $patients, string $date = '2026-11-24', array $owner = []): array
    {
        return app(CampaignAppointments::class)->createForPatients($owner + ['owner_name' => 'Popescu Ana', 'client_phone' => '0740000001',
            'client_email' => 'ana.sintetic@example.com', 'appointment_date' => $date], $patients, $this->doctor);
    }

    /** Duce un dosar prin receptie (date legale) si consult, ca sa poata primi consimtamant. */
    private function reception(SterilizationCase $case, array $extra = []): SterilizationCase
    {
        return $this->cases()->saveSection($case, 'receptie', $extra + ['adresa_ci' => 'Str. Exemplu 10, Targu Mures', 'serie_numar_ci' => 'MS 123456',
            'localitate' => 'Targu Mures'], ['ci' => UploadedFile::fake()->image('ci.jpg', 600, 400)], $this->doctor)['case'];
    }

    private function consult(SterilizationCase $case, array $extra = []): array
    {
        return $this->cases()->saveSection($case, 'preoperator', $extra + ['rasa' => 'Europeana', 'greutate_reala' => '3,5', 'temperatura' => '38.5',
            'pacient_apt' => 'Da', 'responsabil_caz' => 'Dr. Festeu Radu'], [], $this->doctor);
    }

    private function signConsent(Consent $consent): void
    {
        $consent->forceFill(['status' => ConsentStatus::Signed, 'signed_at' => now(), 'payload' => ['owner_final_gdpr' => 1] + $consent->payload,
            'owner_signature' => 'data:image/png;base64,'.base64_encode(UploadedFile::fake()->image('s.png', 40, 20)->getContent())])->save();
    }

    public function test_phone_booking_with_two_patients_creates_a_case_and_appointment_each_and_emails_the_owner(): void
    {
        Event::fake([MessageSent::class]);
        $created = $this->book([
            ['patient_name' => 'Mitzi', 'appointment_type' => 'Sterilizare pisica', 'start_time' => '09:00', 'vaccinat' => 'Da'],
            ['patient_name' => 'Rex', 'appointment_type' => 'Castrare caine', 'start_time' => '10:00', 'end_time' => '09:00', 'vaccinat' => 'Nu'],
        ]);

        $this->assertCount(2, $created);
        [$cat, $dog] = SterilizationCase::query()->orderBy('id')->get()->map->row()->all();
        $this->assertSame(['HV-ST-2026-001', 'HV-ST-2026-002'], [$cat['cod_caz'], $dog['cod_caz']]);
        $this->assertSame(['Pisica', 'Femela', 'Sterilizare pisică', 'Da'], [$cat['specie'], $cat['sex'], $cat['procedura_programata'], $cat['vaccinat']]);
        $this->assertSame(['Caine', 'Mascul', 'Castrare câine'], [$dog['specie'], $dog['sex'], $dog['procedura_programata']]);
        $this->assertSame('Programat', $cat['status_caz']);
        $this->assertSame(['1', '2'], [$cat['numar_pacient'], $dog['numar_pacient']]);
        // Ora de sfarsit invalida devine +60 de minute; fiecare dosar stie programarea lui.
        $this->assertSame('11:00', $created[1]->end_time);
        $this->assertSame($created[0]->id, $cat['initial_appointment_id']);
        $this->assertSame('HV-AP-2026-0001', $created[0]->appointment_code);

        $logs = SterilizationEmailLog::query()->where('email_type', 'booking_confirmation')->get();
        $this->assertCount(2, $logs);
        $this->assertSame('site@hikevet.com', $logs[0]->cc_email);
        $this->assertSame('HiKeVet – Programare intervenție chirurgicală pentru Mitzi – 24.11.2026', $logs[0]->subject);
        Event::assertDispatchedTimes(MessageSent::class, 2);
    }

    public function test_booking_requires_owner_contact_and_a_valid_procedure(): void
    {
        $this->expectException(ValidationException::class);
        $this->book([['patient_name' => 'Mitzi', 'appointment_type' => 'Altceva', 'start_time' => '09:00']]);
    }

    public function test_clinical_flow_consult_intervention_discharge_and_emails(): void
    {
        Event::fake([MessageSent::class]);
        [$appointment] = $this->book([['patient_name' => 'Mitzi', 'appointment_type' => 'Sterilizare pisica', 'start_time' => '09:00', 'vaccinat' => 'Da']]);
        $case = SterilizationCase::query()->sole();

        $case = $this->cases()->startConsultation($appointment, $this->doctor);
        $this->assertSame('Prezentat', $case->status_caz);
        $this->assertSame('Prezentat', $appointment->refresh()->status);

        $case = $this->reception($case);
        $result = $this->consult($case);
        $case = $result['case'];
        $this->assertSame('Apt interventie', $case->status_caz);
        $this->assertSame(3.5, $case->row()['greutate_reala']);
        // Consimtamantul se creeaza automat cand dosarul are datele necesare, cu cod pentru tableta.
        $consent = $case->consent;
        $this->assertNotNull($consent);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $result['consent_code']);
        $this->assertSame('sterilization', $consent->origin);
        $this->assertSame(['anestezie_generala_injectabila'], $consent->payload['anesthesia']);
        $this->assertSame('3.5 kg', $consent->payload['pet_weight']);

        try {
            $this->cases()->saveSection($case, 'interventie', ['tip_anestezie' => 'Injectabila'], [], $this->doctor);
            $this->fail('Mentiunile intra/postoperatorii sunt obligatorii.');
        } catch (ValidationException) {
        }
        $case = $this->cases()->saveSection($case, 'interventie', ['tip_anestezie' => 'Injectabila', 'substanta1' => 'Substanta test', 'doza1' => '1 ml',
            'medic_operator' => 'Dr. Festeu Radu', 'mentiuni_intra_postoperatorii' => 'Fără observații', 'ora_start' => '09:30', 'ora_final' => '10:10'],
            ['photo' => UploadedFile::fake()->image('pacient.jpg', 800, 600)], $this->doctor)['case'];
        $row = $case->row();
        $this->assertSame(['Finalizat', 'Operat', 'OVH pisica'], [$row['status_interventie'], $row['status_caz'], $row['procedura_efectuata']]);
        $this->assertSame('2026-11-24', $row['data_raportare_primarie']);
        $this->assertSame('1', $row['numar_pacient_raport']);
        $this->assertSame('confirmat_foto', $row['numar_pacient_raport_sursa']);
        $this->assertStringStartsWith('doc:', $row['poza_pacient']);

        $case = $this->cases()->saveSection($case, 'externare', ['data_externare' => '2026-11-24', 'ora_externare' => '14:00', 'control_recomandat' => 'Da'], [], $this->doctor)['case'];
        $row = $case->row();
        $this->assertSame(['Externat', '2026-12-08', '2026-11-26 14:00:00'], [$row['status_caz'], $row['data_control'], $row['postop_email_due_at']]);
        $this->assertSame('sent', $row['final_summary_email_status']);
        $this->assertSame(1, SterilizationEmailLog::query()->where('email_type', 'postop_instant_auto')->count());

        // Follow-up-ul pleaca o singura data, dupa 48 de ore.
        $mailer = app(CampaignMailer::class);
        $this->assertSame(0, $mailer->runFollowups(now()->setDateTime(2026, 11, 26, 13, 0))['sent']);
        $this->assertSame(1, $mailer->runFollowups(now()->setDateTime(2026, 11, 26, 15, 0))['sent']);
        $this->assertSame(0, $mailer->runFollowups(now()->setDateTime(2026, 11, 26, 16, 0))['sent']);

        // Retrimiterea manuala dupa corectare este versiunea ACTUALIZATA.
        $mailer->sendFinalSummary($case->refresh(), true, $this->doctor);
        $this->assertSame('postop_instant_manual_corrected', SterilizationEmailLog::query()->latest('id')->value('email_type'));
        $this->assertStringStartsWith('HiKeVet – Rezumat postoperator ACTUALIZAT', SterilizationEmailLog::query()->latest('id')->value('subject'));
    }

    public function test_failed_automatic_followup_is_retried_a_limited_number_of_times(): void
    {
        [$a] = $this->book([['patient_name' => 'Mitzi', 'appointment_type' => 'Sterilizare pisica', 'start_time' => '09:00', 'vaccinat' => 'Da']]);
        $case = SterilizationCase::query()->sole();
        $this->cases()->saveSection($case, 'externare', ['data_externare' => '2026-11-20', 'ora_externare' => '08:00'], [], $this->doctor);
        Mail::shouldReceive('html')->andThrow(new \RuntimeException('SMTP indisponibil'));

        $mailer = app(CampaignMailer::class);
        $results = [];
        foreach (range(1, 6) as $h) {
            $this->travelTo(now()->setDateTime(2026, 11, 22, 8 + $h, 0));
            $results[] = $mailer->runFollowups();
        }

        $this->assertSame(3, SterilizationEmailLog::query()->where('email_type', 'followup_48h_auto')->count());
        $this->assertSame(1, $results[5]['stopped_after_retries']);
        $this->assertStringContainsString('SMTP indisponibil', (string) $case->refresh()->row()['postop_email_error']);
    }

    public function test_patient_numbers_are_unique_per_year_and_a_manual_number_from_the_photo_wins(): void
    {
        $this->book([
            ['patient_name' => 'A', 'appointment_type' => 'Sterilizare pisica', 'start_time' => '09:00', 'vaccinat' => 'Da'],
            ['patient_name' => 'B', 'appointment_type' => 'Sterilizare pisica', 'start_time' => '10:00', 'vaccinat' => 'Da'],
            ['patient_name' => 'C', 'appointment_type' => 'Sterilizare pisica', 'start_time' => '11:00', 'vaccinat' => 'Da'],
        ]);
        [$a, $b, $c] = SterilizationCase::query()->orderBy('id')->get()->all();
        // C are fotografie, deci numarul lui nu se mai schimba automat.
        $this->cases()->saveSection($c, 'corectare', [], ['photo' => UploadedFile::fake()->image('c.jpg', 300, 300)], $this->doctor);

        // Pe dosarul A se scrie manual numarul 2 (cel din fotografie): B (fara poza) primeste numar nou.
        $result = $this->cases()->saveSection($a, 'corectare', ['numar_pacient' => '2'], [], $this->doctor);
        $this->assertSame('2', $result['number_saved']);
        $this->assertSame('2', $a->refresh()->row()['numar_pacient']);
        $this->assertSame('corectare_manuala_dupa_foto', $a->row()['numar_pacient_raport_sursa']);
        $this->assertSame([['id' => $b->id, 'code' => 'HV-ST-2026-002', 'patient' => 'B', 'old' => '2', 'new' => '4']], $result['renumbered']);
        $this->assertSame('2', $b->refresh()->row()['numar_pacient_vechi']);

        // Pe A se scrie numarul lui C: C are poza, deci conflictul ramane vizibil, nu se rezolva automat.
        $result = $this->cases()->saveSection($a->refresh(), 'corectare', ['numar_pacient' => '3'], [], $this->doctor);
        $this->assertSame('3', $c->refresh()->row()['numar_pacient']);
        $this->assertSame($c->id, $result['unresolved'][0]['id']);
        $this->assertFalse(app(CityChecks::class)->patientNumberUnique($a->refresh()->row()));
    }

    public function test_signed_consent_is_never_rewritten_and_an_identity_change_issues_a_corrected_one(): void
    {
        $this->book([['patient_name' => 'Mitzi', 'appointment_type' => 'Sterilizare pisica', 'start_time' => '09:00', 'vaccinat' => 'Da']]);
        $case = $this->reception(SterilizationCase::query()->sole());
        $case = $this->consult($case)['case'];
        $consent = $case->consent;

        // Inainte de semnare, modificarile din dosar ajung in document.
        $this->cases()->saveSection($case, 'corectare', ['nume_proprietar' => 'Popescu Ana Maria'], [], $this->doctor);
        $this->assertSame('Popescu Ana Maria', $consent->refresh()->payload['owner_name']);

        $this->signConsent($consent);
        $signedPayload = $consent->refresh()->payload;
        $case = $this->cases()->saveSection($case->refresh(), 'corectare', ['nume_pacient' => 'Mitzi Pufi'], [], $this->doctor)['case'];

        $this->assertSame($signedPayload, $consent->refresh()->payload, 'Documentul semnat ramane neschimbat.');
        $new = $case->consent;
        $this->assertNotSame($consent->id, $new->id);
        $this->assertSame($consent->id, $new->supersedes_id);
        $this->assertSame($new->id, $consent->superseded_by_id);
        $this->assertSame(ConsentStatus::Pending, $new->status);
        $this->assertSame('Mitzi Pufi', $new->payload['pet_name']);
    }

    public function test_city_validation_is_blocked_until_the_case_passes_every_check(): void
    {
        $this->book([['patient_name' => 'Mitzi', 'appointment_type' => 'Sterilizare pisica', 'start_time' => '09:00', 'vaccinat' => 'Da']]);
        $case = SterilizationCase::query()->sole();
        $result = $this->cases()->saveSection($case, 'validare', ['validat_pentru_primarie' => 'Da', 'validat_de' => 'Dr. Festeu Radu'], [], $this->doctor);
        $this->assertTrue($result['validation_blocked']);
        $this->assertSame('Nu', $result['case']->row()['validat_pentru_primarie']);
        $checklist = app(CityChecks::class)->checklist($result['case']->row());
        $this->assertSame(21, $checklist['total']);
        $this->assertFalse($checklist['ready']);

        $case = $this->reception($result['case']);
        $case = $this->consult($case)['case'];
        $this->signConsent($case->consent);
        $case = $this->cases()->saveSection($case->refresh(), 'interventie', ['tip_anestezie' => 'Injectabila', 'substanta1' => 'S', 'medic_operator' => 'Dr. Anca Ion',
            'mentiuni_intra_postoperatorii' => 'Fără observații'], ['photo' => UploadedFile::fake()->image('p.jpg', 300, 300)], $this->doctor)['case'];
        $this->assertTrue(app(CityChecks::class)->checklist($case->row())['ready'], json_encode(array_column(array_filter(app(CityChecks::class)->checklist($case->row())['checks'], fn ($c) => ! $c['ok']), 'label')));

        $result = $this->cases()->saveSection($case, 'validare', ['validat_pentru_primarie' => 'Da', 'validat_de' => 'Dr. Festeu Radu'], [], $this->doctor);
        $this->assertFalse($result['validation_blocked']);
        $this->assertSame(['Da', 'Validat primarie', '2026-11-20'], [$result['case']->row()['validat_pentru_primarie'], $result['case']->status_caz, $result['case']->row()['data_validare']]);
    }

    public function test_visit_with_several_pets_shares_owner_documents_and_consent(): void
    {
        $this->book([['patient_name' => 'Mitzi', 'appointment_type' => 'Sterilizare pisica', 'start_time' => '09:00', 'vaccinat' => 'Da']]);
        $parent = $this->reception(SterilizationCase::query()->sole());
        $parent = $this->consult($parent)['case'];
        $child = $this->cases()->addVisitPet($parent, ['patient_name' => 'Tom', 'species' => 'Pisica', 'sex' => 'Mascul', 'breed' => 'Europeana', 'weight' => '4', 'vaccinat' => 'Da'], $this->doctor);

        $this->assertSame($parent->id, (int) $child->visit_group_id);
        $this->assertSame('Castrare motan', $child->row()['procedura_programata']);
        $this->assertSame($parent->consent_id, $child->consent_id);
        $this->assertStringStartsWith('doc:', $child->row()['documente_scanate'], 'CI-ul proprietarului este comun vizitei.');
        $this->assertSame(['Mitzi', 'Tom'], array_column($parent->consent->refresh()->visit_patients, 'pet_name'));

        // Proprietarul corectat intr-un dosar se schimba in toata vizita.
        $this->cases()->saveSection($parent->refresh(), 'corectare', ['telefon' => '0740000999'], [], $this->doctor);
        $this->assertSame('0740000999', $child->refresh()->telefon);

        // Scoaterea unui animal: dosarul ramane, marcat sters, iar consimtamantul comun pastreaza doar pacientii ramasi.
        $this->cases()->removeVisitPet($child, 'Adăugat din greșeală', $this->doctor);
        $this->assertNotNull($child->refresh()->deleted_at);
        $this->assertSame(['Mitzi'], array_column($parent->consent->refresh()->visit_patients, 'pet_name'));
        $this->assertSame('deleted', $child->changes()->first()->action);
    }

    public function test_reminder_is_sent_once_the_day_before_and_not_for_cancelled_appointments(): void
    {
        Event::fake([MessageSent::class]);
        [$a] = $this->book([['patient_name' => 'Mitzi', 'appointment_type' => 'Sterilizare pisica', 'start_time' => '09:00', 'vaccinat' => 'Da']], '2026-11-21');
        [$b] = $this->book([['patient_name' => 'Rex', 'appointment_type' => 'Castrare caine', 'start_time' => '10:00', 'vaccinat' => 'Da']], '2026-11-21');
        app(CampaignAppointments::class)->cancel($b, $this->doctor);
        $service = app(CampaignAppointments::class);

        $this->assertSame(0, $service->runReminders(now()->setDateTime(2026, 11, 20, 8, 0))['sent']);
        $this->assertSame(1, $service->runReminders(now()->setDateTime(2026, 11, 20, 9, 30))['sent']);
        $this->assertSame(0, $service->runReminders(now()->setDateTime(2026, 11, 20, 10, 30))['sent']);
        $log = SterilizationEmailLog::query()->where('email_type', 'automatic')->sole();
        $this->assertStringStartsWith('HiKeVet – Reamintire intervenție chirurgicală pentru Mitzi', $log->subject);
        $this->assertStringContainsString('08:30 – 12:00', CampaignMailer::appointmentBody($a->refresh(), 'automatic'));
        $this->assertStringNotContainsString('la ora <strong>09:00', CampaignMailer::appointmentBody($a, 'automatic'));
    }

    public function test_day_availability_counts_overlaps_in_one_hour_windows(): void
    {
        $this->book([['patient_name' => 'A', 'appointment_type' => 'Sterilizare pisica', 'start_time' => '09:00', 'vaccinat' => 'Da'],
            ['patient_name' => 'B', 'appointment_type' => 'Sterilizare pisica', 'start_time' => '09:30', 'vaccinat' => 'Da']]);
        $day = app(CampaignAppointments::class)->dayAvailability('2026-11-24');

        $this->assertSame(2, $day['count']);
        $this->assertSame('08:00', $day['next_free'] === '08:00' ? '08:00' : $day['next_free']);
        $slot = collect($day['slots'])->firstWhere('time', '09:00');
        $this->assertSame(2, $slot['load']);
        $this->assertSame(1, collect($day['slots'])->firstWhere('time', '10:00')['load']);
        $this->assertSame(0, collect($day['slots'])->firstWhere('time', '11:00')['load']);
        $this->assertCount(23, $day['slots']);
    }

    public function test_every_change_is_logged_and_cases_cannot_be_written_or_deleted_outside_the_service(): void
    {
        $this->book([['patient_name' => 'Mitzi', 'appointment_type' => 'Sterilizare pisica', 'start_time' => '09:00', 'vaccinat' => 'Da']]);
        $case = SterilizationCase::query()->sole();
        $this->cases()->saveSection($case, 'corectare', ['rasa' => 'Siameza'], [], $this->doctor);

        $change = SterilizationCaseChange::query()->where('sterilization_case_id', $case->id)->where('section', 'corectare')->latest('id')->first();
        $this->assertSame(['', 'Siameza'], $change->changes['rasa']);
        $this->assertSame($this->doctor->id, $change->actor_id);

        $this->expectException(LogicException::class);
        $case->refresh()->forceFill(['nume_pacient' => 'Altul'])->save();
    }

    public function test_soft_delete_requires_a_reason_and_cancels_the_appointment(): void
    {
        [$a] = $this->book([['patient_name' => 'Mitzi', 'appointment_type' => 'Sterilizare pisica', 'start_time' => '09:00', 'vaccinat' => 'Da']]);
        $case = SterilizationCase::query()->sole();
        try {
            $this->cases()->softDelete($case, '', $this->doctor);
            $this->fail('Motivul este obligatoriu.');
        } catch (ValidationException) {
        }
        $this->cases()->softDelete($case, 'Proprietarul a renunțat', $this->doctor);
        $this->assertSame('Anulat', $a->refresh()->status);
        $this->assertSame(0, SterilizationCase::query()->active()->count());
        $this->assertSame(1, SterilizationCase::query()->count());
    }

    public function test_hourly_command_sends_due_reminders_and_followups(): void
    {
        $this->book([['patient_name' => 'Mitzi', 'appointment_type' => 'Sterilizare pisica', 'start_time' => '09:00', 'vaccinat' => 'Da']], '2026-11-21');
        $this->travelTo('2026-11-20 10:00:00');
        $this->artisan('hikevet:campaign-emails')->expectsOutputToContain('Reamintiri: 1 trimise')->assertSuccessful();
        $this->assertNotNull(Settings::get('campaign.last_email_run'));
        $this->actingAs($this->doctor)->get('/admin/campanie/emailuri?show=all')->assertOk();
    }

    public function test_stopped_campaign_sends_nothing_and_accepts_no_new_work_but_keeps_history_and_reports(): void
    {
        Event::fake([MessageSent::class]);
        [$appointment] = $this->book([['patient_name' => 'Mitzi', 'appointment_type' => 'Sterilizare pisica', 'start_time' => '09:00']], '2026-11-21');
        $case = $this->reception(SterilizationCase::query()->sole());
        $token = $case->row()['photo_upload_token'];
        $this->get('/campanie/foto/'.$token)->assertOk();
        $sentBefore = SterilizationEmailLog::query()->where('status', 'sent')->count();
        Settings::set('campaign.active', false);
        $this->assertFalse(CampaignMailer::active());

        // Automat: nici reamintiri, nici follow-up, nici comanda orara.
        $this->artisan('hikevet:campaign-emails')->expectsOutputToContain('oprită')->assertSuccessful();
        $this->assertSame(['sent' => 0, 'failed' => 0], app(CampaignAppointments::class)->runReminders());
        $this->assertNull($appointment->fresh()->reminder_sent_at);

        // Manual: niciun email, nicio programare noua, niciun dosar nou, nicio fotografie de pe telefon.
        $admin = $this->staff(Role::SuperAdmin);
        $this->actingAs($admin)->post('/admin/campanie/programari/'.$appointment->id.'/email')->assertSessionHas('campaign_warning');
        $this->actingAs($admin)->post('/admin/campanie/dosar/'.$case->id.'/email-final')->assertSessionHas('campaign_warning');
        $this->actingAs($admin)->post('/admin/campanie/programari', ['appointment' => ['owner_name' => 'X', 'client_phone' => '0740000009', 'appointment_date' => '2026-11-25'],
            'patients' => [['patient_name' => 'Nou', 'appointment_type' => 'Sterilizare pisica', 'start_time' => '10:00']]])->assertSessionHas('campaign_warning');
        $this->actingAs($admin)->post('/admin/campanie/programari/'.$appointment->id.'/anuleaza')->assertSessionHas('campaign_warning');
        $this->actingAs($admin)->post('/admin/campanie/dosar', ['section' => 'receptie', 'data' => ['nume_proprietar' => 'Nou']])->assertSessionHas('campaign_warning');
        $this->assertSame([false, CampaignMailer::STOPPED_MESSAGE], app(CampaignMailer::class)->sendFollowup($case, true, $admin));
        $this->get('/campanie/foto/'.$token)->assertNotFound();
        $this->post('/campanie/foto/'.$token)->assertNotFound();

        Event::assertDispatchedTimes(MessageSent::class, $sentBefore);
        $this->assertSame(1, SterilizationCase::query()->count());
        $this->assertSame('Programat', $appointment->fresh()->status);

        // Istoricul ramane consultabil, cu avertismentul vizibil.
        $this->actingAs($admin)->get('/admin/campanie/dosar?case='.$case->id.'&tab=rezumat')->assertOk()->assertSee('Campania de sterilizare este oprită');
        $this->actingAs($admin)->get('/admin/campanie/raport-primarie')->assertOk();
    }
}
