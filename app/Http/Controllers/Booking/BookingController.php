<?php

namespace App\Http\Controllers\Booking;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\TeamMember;
use App\Services\Booking\BookingService;
use App\Services\Seo\Seo;
use App\Support\Booking;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function create(Request $request, Seo $seo)
    {
        // Daca s-a ales din Setari ca programarile sa ramana in Digitail, pagina proprie e doar previzualizare pentru personal.
        if (Booking::isExternal() && ! $request->user()?->can('appointments.manage')) {
            return redirect()->away(Booking::url());
        }
        $seo->page('Cerere de programare', 'Alege serviciul, ziua și intervalul care îți convine. Clinica îți confirmă ora exactă.');
        $services = Service::published()->with('category:id,name')->orderBy('sort')->get(['id', 'slug', 'name', 'service_category_id', 'duration_minutes']);
        $preselect = $services->firstWhere('slug', (string) $request->query('serviciu'))?->id;

        return view('booking.create', ['seo' => $seo, 'preview' => Booking::isExternal(), 'services' => $services, 'preselect' => $preselect,
            'doctors' => TeamMember::query()->where('is_published', true)
                ->where(fn ($q) => $q->whereHas('schedules')->orWhere('role_title', 'like', '%medic%')->orWhere('name', 'like', 'Dr%'))
                ->orderBy('sort')->withCount('schedules')->get(['id', 'name', 'role_title']),
            'periods' => BookingService::PERIODS]);
    }

    public function slots(Request $request, BookingService $booking)
    {
        $data = $request->validate([
            'doctor' => ['required', 'integer', 'exists:team_members,id'], 'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'service' => ['nullable', 'integer', 'exists:services,id'],
        ]);
        $duration = $data['service'] ?? null ? (int) Service::query()->whereKey($data['service'])->value('duration_minutes') : 30;

        return response()->json(['slots' => $booking->freeSlots(TeamMember::findOrFail($data['doctor']), CarbonImmutable::parse($data['date']), $duration)]);
    }

    public function store(Request $request, BookingService $booking)
    {
        abort_if(Booking::isExternal() && ! $request->user()?->can('appointments.manage'), 403);

        if ($request->filled('website')) {
            return redirect()->away(site_url('/programare/'))->with('error', 'Cererea nu a putut fi trimisă.');
        }

        $data = $request->validate([
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('is_published', true)],
            'team_member_id' => ['nullable', 'integer', Rule::exists('team_members', 'id')->where('is_published', true)],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.now()->addDays(BookingService::MAX_DAYS_AHEAD)->toDateString()],
            'time' => ['nullable', 'date_format:H:i', 'required_without:preferred_period'],
            'preferred_period' => ['nullable', Rule::in(array_keys(BookingService::PERIODS)), 'required_without:time'],
            'client_name' => ['required', 'string', 'min:3', 'max:120'], 'client_phone' => ['required', 'string', 'min:6', 'max:30'],
            'client_email' => ['required', 'email:rfc', 'max:190'], 'pet_name' => ['required', 'string', 'max:80'],
            'pet_species' => ['required', Rule::in(['Câine', 'Pisică', 'Altă specie'])], 'notes' => ['nullable', 'string', 'max:1000'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'Avem nevoie de acordul tău pentru a înregistra cererea.',
            'preferred_period.required_without' => 'Alege intervalul care îți convine (sau o oră exactă).',
            'time.required_without' => 'Alege intervalul care îți convine (sau o oră exactă).',
        ], [
            'service_id' => 'serviciul', 'team_member_id' => 'medicul', 'date' => 'ziua', 'time' => 'ora', 'preferred_period' => 'intervalul',
            'client_name' => 'numele', 'client_phone' => 'telefonul', 'client_email' => 'emailul', 'pet_name' => 'numele animalului', 'pet_species' => 'specia',
        ]);

        $base = $data + ['consent_processing' => true, 'user_id' => $request->user()?->id];
        try {
            // Ora exacta exista doar cand medicul ales are orar; altfel trimitem cererea pe interval.
            $appointment = ! empty($data['time']) && ! empty($data['team_member_id'])
                ? $booking->book($base + ['starts_at' => $data['date'].' '.$data['time']])
                : $booking->request($base + ['preferred_period' => $data['preferred_period'] ?? 'dimineata']);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors([! empty($data['time']) ? 'time' : 'preferred_period' => $e->getMessage()]);
        }

        return redirect()->route('booking.show', $appointment->manage_token)->with('status', 'Cererea de programare a fost trimisă.');
    }

    public function show(Seo $seo, string $token)
    {
        $appointment = Appointment::query()->where('manage_token', $token)->with(['doctor', 'service'])->firstOrFail();
        $seo->page('Programarea ta');
        $seo->noindex = true;

        return view('booking.show', ['seo' => $seo, 'appointment' => $appointment, 'token' => $token]);
    }

    public function cancel(BookingService $booking, string $token)
    {
        $appointment = Appointment::query()->where('manage_token', $token)->firstOrFail();
        if ($appointment->status->holdsSlot() && $appointment->status !== AppointmentStatus::Completed && $appointment->starts_at->isFuture()) {
            $booking->setStatus($appointment, AppointmentStatus::Cancelled, 'Anulată de client');
        }

        return redirect()->route('booking.show', $token)->with('status', 'Programarea a fost anulată.');
    }
}
