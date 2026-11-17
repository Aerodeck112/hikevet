<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\SterilizationAppointment;
use App\Models\SterilizationEmailLog;
use App\Services\Campaign\CampaignAppointments;
use App\Services\Campaign\CampaignCases;
use App\Services\Campaign\CampaignMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Programarile campaniei: creare (unul sau mai multi pacienti), editare, anulare, stergere, email, inceperea consultatiei. */
class CampaignAppointmentController extends Controller
{
    public function __construct(private readonly CampaignAppointments $appointments) {}

    private function authorize(Request $request): void
    {
        abort_unless($request->user()?->can(Permission::CAMPAIGN_MANAGE), 403);
    }

    private static function calendarUrl(?string $week, array $extra = []): string
    {
        return url('/admin/campanie/programari').'?'.http_build_query(array_filter(['week' => $week] + $extra));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize($request);
        $owner = (array) $request->input('appointment', []);
        $created = $this->appointments->createForPatients($owner, array_values((array) $request->input('patients', [])), $request->user());
        $sent = $created ? SterilizationEmailLog::query()->whereIn('appointment_id', array_map(fn ($a) => $a->id, $created))->where('status', 'sent')->count() : 0;

        return redirect()->to(self::calendarUrl($owner['appointment_date'] ?? null))->with('campaign_message',
            count($created).' '.(count($created) === 1 ? 'programare creată' : 'programări create').', cu câte un dosar pentru fiecare pacient. Emailuri de confirmare trimise: '.$sent.' din '.count($created).'.');
    }

    public function update(Request $request, SterilizationAppointment $appointment): RedirectResponse
    {
        $this->authorize($request);
        $a = $this->appointments->update($appointment, (array) $request->input('appointment', []), $request->user());

        return redirect()->to(self::calendarUrl($a->appointment_date?->format('Y-m-d'), ['appointment' => $a->id]))->with('campaign_message', 'Programarea a fost salvată, iar dosarul legat a fost actualizat.');
    }

    public function cancel(Request $request, SterilizationAppointment $appointment): RedirectResponse
    {
        $this->authorize($request);
        $this->appointments->cancel($appointment, $request->user());

        return redirect()->to(self::calendarUrl($appointment->appointment_date?->format('Y-m-d')))->with('campaign_message', 'Programarea '.$appointment->appointment_code.' a fost anulată.');
    }

    public function destroy(Request $request, SterilizationAppointment $appointment): RedirectResponse
    {
        $this->authorize($request);
        $week = $appointment->appointment_date?->format('Y-m-d');
        $this->appointments->delete($appointment, $request->user());

        return redirect()->to(self::calendarUrl($week))->with('campaign_message', 'Programarea a fost ștearsă. Dosarul legat rămâne.');
    }

    public function email(Request $request, SterilizationAppointment $appointment): RedirectResponse
    {
        $this->authorize($request);
        [$sent, $error] = app(CampaignMailer::class)->sendAppointmentEmail($appointment, 'manual', $request->user());

        return back()->with($sent ? 'campaign_message' : 'campaign_warning', $sent ? 'Emailul cu programarea a fost retrimis proprietarului.' : 'Emailul nu a putut fi trimis: '.$error);
    }

    public function startConsultation(Request $request, SterilizationAppointment $appointment): RedirectResponse
    {
        $this->authorize($request);
        $case = app(CampaignCases::class)->startConsultation($appointment, $request->user());

        return redirect()->to(CampaignController::caseUrl($case->id, 'preoperator'))->with('campaign_message', 'Programarea a deschis direct consultația preoperatorie. Completează fișa, apoi recepția finalizează consimțământul și GDPR în prima secțiune.');
    }

    public function availability(Request $request)
    {
        abort_unless($request->user()?->can(Permission::CAMPAIGN_MANAGE) || $request->user()?->can(Permission::STERILIZATION_VIEW), 403);

        return response()->json($this->appointments->dayAvailability((string) $request->query('date'), $request->integer('exclude')), 200, [], JSON_UNESCAPED_UNICODE);
    }
}
