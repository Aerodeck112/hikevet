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
        // Cat timp programarile se fac in Digitail, pagina proprie nu accepta cereri: un singur sistem activ.
        if (Booking::isExternal() && ! $request->user()?->can('appointments.manage')) {
            return redirect()->away(Booking::url());
        }
        $seo->page('Programare online', 'Alege serviciul, medicul și ora. Primești confirmarea pe email.');

        return view('booking.create', ['seo' => $seo, 'preview' => Booking::isExternal(),
            'services' => Service::published()->where('is_bookable', true)->orderBy('sort')->get(['id', 'name', 'duration_minutes']),
            'doctors' => TeamMember::query()->where('is_published', true)->whereHas('schedules')->orderBy('sort')->get(['id', 'name', 'role_title'])]);
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
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_bookable', true)],
            'team_member_id' => ['required', 'integer', 'exists:team_members,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'time' => ['required', 'date_format:H:i'],
            'client_name' => ['required', 'string', 'min:3', 'max:120'], 'client_phone' => ['required', 'string', 'min:6', 'max:30'],
            'client_email' => ['required', 'email:rfc', 'max:190'], 'pet_name' => ['required', 'string', 'max:80'],
            'pet_species' => ['required', Rule::in(['Câine', 'Pisică', 'Altă specie'])], 'notes' => ['nullable', 'string', 'max:1000'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Avem nevoie de acordul tău pentru a înregistra programarea.'], [
            'service_id' => 'serviciul', 'team_member_id' => 'medicul', 'date' => 'data', 'time' => 'ora', 'client_name' => 'numele',
            'client_phone' => 'telefonul', 'client_email' => 'emailul', 'pet_name' => 'numele animalului', 'pet_species' => 'specia',
        ]);

        try {
            $appointment = $booking->book($data + ['starts_at' => $data['date'].' '.$data['time'], 'consent_processing' => true, 'user_id' => $request->user()?->id]);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['time' => $e->getMessage()]);
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
