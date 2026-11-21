<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\TeamMember;
use App\Services\Booking\BookingService;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private TeamMember $doctor;

    private Service $service;

    private CarbonImmutable $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBusiness();
        $this->travelTo('2026-11-16 07:00:00'); // luni
        $this->day = CarbonImmutable::parse('2026-11-18'); // miercuri
        $this->doctor = TeamMember::create(['slug' => 'medic-test', 'name' => 'Dr. Test Medic']);
        $this->doctor->schedules()->create(['weekday' => 3, 'starts_at' => '09:00', 'ends_at' => '12:00']);
        $this->service = Service::create(['slug' => 'consult-test', 'name' => 'Consult test', 'duration_minutes' => 30, 'is_bookable' => true]);
    }

    private function data(string $time, array $overrides = []): array
    {
        return array_merge(['team_member_id' => $this->doctor->id, 'service_id' => $this->service->id, 'starts_at' => $this->day->toDateString().' '.$time,
            'client_name' => 'Client Programare', 'client_phone' => '0700000003', 'client_email' => 'programare@example.com', 'pet_name' => 'Bobi'], $overrides);
    }

    public function test_free_slots_follow_schedule_duration_bookings_and_blocks(): void
    {
        $booking = app(BookingService::class);

        $this->assertSame(['09:00', '09:15', '09:30', '09:45', '10:00', '10:15', '10:30', '10:45', '11:00', '11:15', '11:30'], $booking->freeSlots($this->doctor, $this->day, 30));
        $this->assertSame([], $booking->freeSlots($this->doctor, $this->day->addDay(), 30), 'Fara orar in ziua respectiva nu exista intervale.');

        $booking->book($this->data('10:00'));
        $this->assertNotContains('10:00', $booking->freeSlots($this->doctor, $this->day, 30));
        $this->assertNotContains('09:45', $booking->freeSlots($this->doctor, $this->day, 30), 'Un consult de 30 de minute inceput la 09:45 s-ar suprapune.');
        $this->assertContains('09:30', $booking->freeSlots($this->doctor, $this->day, 30));

        ScheduleBlock::create(['team_member_id' => null, 'starts_at' => $this->day->setTime(11, 0), 'ends_at' => $this->day->setTime(12, 0), 'reason' => 'pauză']);
        $this->assertNotContains('11:00', $booking->freeSlots($this->doctor, $this->day, 30));
        $this->assertNotContains('10:45', $booking->freeSlots($this->doctor, $this->day, 30));
    }

    public function test_double_booking_is_impossible_even_when_availability_check_is_bypassed(): void
    {
        $booking = app(BookingService::class);
        $booking->book($this->data('09:00'));

        foreach (['09:00', '09:15'] as $time) {
            try {
                $booking->book($this->data($time, ['client_name' => 'Alt Client']), null, byStaff: true);
                $this->fail("Suprapunerea la $time ar fi trebuit refuzata.");
            } catch (DomainException $e) {
                $this->assertStringContainsString('ocupat', $e->getMessage());
            }
        }
        $this->assertSame(1, Appointment::count());
        $this->assertSame(2, AppointmentSlot::count());
    }

    public function test_cancelling_frees_the_slot(): void
    {
        $booking = app(BookingService::class);
        $appointment = $booking->book($this->data('09:00'));
        $booking->setStatus($appointment, AppointmentStatus::Cancelled, 'test');

        $this->assertContains('09:00', $booking->freeSlots($this->doctor, $this->day, 30));
        $this->assertSame(AppointmentStatus::Confirmed, $booking->book($this->data('09:00'), null, byStaff: true)->status);
    }

    public function test_booking_page_is_on_the_site_by_default_and_digitail_only_when_chosen(): void
    {
        $this->get('/programare/')->assertOk()->assertSee('Cerere de programare')->assertSee('Dr. Test Medic')->assertSee('Oricare medic disponibil');
        $this->getJson('/programare/intervale?doctor='.$this->doctor->id.'&date='.$this->day->toDateString().'&service='.$this->service->id)->assertOk()->assertJsonFragment(['09:00']);

        Settings::set('booking.mode', 'digitail');
        $this->get('/programare/')->assertRedirect('https://vet.digitail.io/clinics/clinica-veterinara-hikevet');
        $this->post('/programare', [])->assertForbidden();
    }

    public function test_request_with_preferred_period_and_any_doctor_is_confirmed_by_reception_with_an_exact_time(): void
    {
        $form = ['date' => $this->day->toDateString(), 'preferred_period' => 'dimineata', 'client_name' => 'Client Interval', 'client_phone' => '0700000005',
            'client_email' => 'interval@example.com', 'pet_name' => 'Rex', 'pet_species' => 'Câine', 'notes' => 'Vaccin anual', 'consent' => '1'];

        $this->post('/programare', array_merge($form, ['preferred_period' => '']))->assertSessionHasErrors('preferred_period');
        $this->post('/programare', array_merge($form, ['preferred_period' => 'noaptea']))->assertSessionHasErrors('preferred_period');
        $this->post('/programare', $form)->assertRedirect();

        $request = Appointment::query()->sole();
        $this->assertSame(AppointmentStatus::Requested, $request->status);
        $this->assertNull($request->team_member_id);
        $this->assertSame('dimineata', $request->preferred_period);
        $this->assertSame('2026-11-18 09:00', $request->starts_at->format('Y-m-d H:i'));
        $this->assertSame(0, AppointmentSlot::count(), 'O cerere pe interval nu ocupa orarul pana la confirmare.');
        $this->assertContains('09:00', app(BookingService::class)->freeSlots($this->doctor, $this->day, 30));

        $this->get(route('booking.show', $request->manage_token))->assertOk()->assertSee('Oricare medic disponibil')->assertSee('Dimineața (09:00–12:00)')->assertSee('Ce urmează');

        // Fara ora exacta, cererea nu poate fi confirmata direct
        try {
            app(BookingService::class)->setStatus($request, AppointmentStatus::Confirmed);
            $this->fail('Confirmarea fara ora exacta trebuia refuzata.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('ora exactă', $e->getMessage());
        }

        app(BookingService::class)->confirmRequest($request, $this->doctor->id, '2026-11-18 10:30', 30);
        $request->refresh();
        $this->assertSame(AppointmentStatus::Confirmed, $request->status);
        $this->assertSame($this->doctor->id, $request->team_member_id);
        $this->assertSame('10:30', $request->starts_at->format('H:i'));
        $this->assertSame(2, AppointmentSlot::count());
        $this->assertNotContains('10:30', app(BookingService::class)->freeSlots($this->doctor, $this->day, 30));
        $this->assertDatabaseHas('email_logs', ['context' => 'appointment_confirmed', 'subject' => 'Programarea ta este confirmată']);
    }

    public function test_request_rejects_past_periods_and_days_too_far_ahead(): void
    {
        $this->travelTo('2026-11-18 16:00:00');
        $base = ['client_name' => 'Client Test', 'client_phone' => '0700000006', 'client_email' => 'test@example.com', 'pet_name' => 'Tom', 'pet_species' => 'Pisică', 'consent' => '1'];
        $this->post('/programare', $base + ['date' => '2026-11-18', 'preferred_period' => 'dimineata'])->assertSessionHasErrors('preferred_period');
        $this->post('/programare', $base + ['date' => '2027-03-01', 'preferred_period' => 'seara'])->assertSessionHasErrors('date');
        $this->post('/programare', $base + ['date' => '2026-11-18', 'preferred_period' => 'seara'])->assertRedirect();
        $this->assertSame(1, Appointment::count());
    }

    public function test_public_booking_creates_a_request_and_client_can_cancel_with_the_private_link(): void
    {
        $form = ['service_id' => $this->service->id, 'team_member_id' => $this->doctor->id, 'date' => $this->day->toDateString(), 'time' => '09:30',
            'client_name' => 'Client Public', 'client_phone' => '0700000004', 'client_email' => 'public@example.com', 'pet_name' => 'Pufi', 'pet_species' => 'Pisică', 'consent' => '1'];

        $this->post('/programare', $form)->assertRedirect();
        $appointment = Appointment::query()->sole();
        $this->assertSame(AppointmentStatus::Requested, $appointment->status);

        $this->post('/programare', $form)->assertSessionHasErrors('time');
        $this->post('/programare', array_merge($form, ['time' => '10:30', 'consent' => '']))->assertSessionHasErrors('consent');
        $this->get('/programare/confirmare/token-gresit')->assertNotFound();

        $this->get(route('booking.show', $appointment->manage_token))->assertOk()->assertSee('Pufi');
        $this->post('/programare/anulare/'.$appointment->manage_token)->assertRedirect();
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);
    }

    public function test_admin_screens_load_for_reception(): void
    {
        app(BookingService::class)->book($this->data('09:00'));
        $reception = $this->staff(Role::Reception);

        foreach (['/admin/appointments', '/admin/appointments/create', '/admin/appointment-calendar', '/admin/schedule-blocks', '/admin/schedule-blocks/create'] as $url) {
            $this->actingAs($reception)->get($url)->assertOk();
        }
        $this->actingAs($reception)->get('/admin/appointment-calendar?week=2026-11-16')->assertSee('Client Programare');
        $this->actingAs($this->staff(Role::Editor))->get('/admin/appointment-calendar')->assertForbidden();
    }

    public function test_reception_schedules_a_period_request_from_the_admin_list(): void
    {
        $request = app(BookingService::class)->request(['date' => $this->day->toDateString(), 'preferred_period' => 'pranz', 'client_name' => 'Client Admin',
            'client_phone' => '0700000007', 'client_email' => 'admin-test@example.com', 'pet_name' => 'Lola', 'pet_species' => 'Câine']);
        $reception = $this->staff(Role::Reception);
        $this->actingAs($reception)->get('/admin/appointments')->assertOk()->assertSee('Preferă: La prânz (12:00–15:00)')->assertSee('Oricare');

        \Livewire\Livewire::actingAs($reception)->test(\App\Filament\Resources\Appointments\Pages\ListAppointments::class)
            ->callTableAction('schedule', $request, data: ['team_member_id' => $this->doctor->id, 'starts_at' => '2026-11-18 12:30:00', 'duration_minutes' => 30])
            ->assertHasNoTableActionErrors();

        $request->refresh();
        $this->assertSame(AppointmentStatus::Confirmed, $request->status);
        $this->assertSame('12:30', $request->starts_at->format('H:i'));
    }
}
