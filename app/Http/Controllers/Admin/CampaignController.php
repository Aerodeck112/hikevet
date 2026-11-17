<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\SterilizationCase;
use App\Models\SterilizationDocument;
use App\Services\Campaign\CampaignCases;
use App\Services\Campaign\CampaignConsents;
use App\Services\Campaign\CampaignMailer;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Actiunile pe dosarele campaniei, apelate din ecranele de administrare. */
class CampaignController extends Controller
{
    public function __construct(private readonly CampaignCases $cases) {}

    public static function caseUrl(int $id, string $tab = 'receptie', array $extra = []): string
    {
        return url('/admin/campanie/dosar').'?'.http_build_query(['case' => $id, 'tab' => $tab] + $extra);
    }

    private function authorize(Request $request, string $permission = Permission::CAMPAIGN_MANAGE): void
    {
        abort_unless($request->user()?->can($permission), 403);
    }

    public function save(Request $request): RedirectResponse
    {
        $section = (string) $request->input('section', 'receptie');
        $this->authorize($request, $section === 'validare' ? Permission::CAMPAIGN_VALIDATE : Permission::CAMPAIGN_MANAGE);
        $case = $request->integer('case_id') ? $this->cases->find($request->integer('case_id')) : null;
        $request->validate([
            'documente_scanate_file' => ['nullable', 'file', 'max:20480', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'],
            'poza_pacient_file' => ['nullable', 'file', 'max:20480', 'mimetypes:image/jpeg,image/png,image/webp'],
        ], [], ['documente_scanate_file' => 'actul de identitate', 'poza_pacient_file' => 'fotografia']);

        $result = $this->cases->saveSection($case, $section, (array) $request->input('data', []), [
            'ci' => $request->file('documente_scanate_file'), 'photo' => $request->file('poza_pacient_file'),
            'remove_ci' => $request->boolean('remove_documente_scanate'), 'remove_photo' => $request->boolean('remove_poza_pacient'),
        ], $request->user());

        $flash = ['campaign_saved' => true];
        foreach (['validation_blocked', 'number_saved', 'booking_mail', 'consent_code'] as $key) {
            if (! empty($result[$key])) {
                $flash['campaign_'.$key] = $result[$key];
            }
        }
        if ($result['number_saved'] !== null) {
            $flash['campaign_renumbered'] = count($result['renumbered']);
            $flash['campaign_unresolved'] = count($result['unresolved']);
        }

        return redirect()->to(self::caseUrl($result['case']->id, $case ? $section : 'receptie'))->with($flash);
    }

    public function addVisitPet(Request $request, int $case): RedirectResponse
    {
        $this->authorize($request);
        $child = $this->cases->addVisitPet($this->cases->find($case), (array) $request->input('visit_pet', []), $request->user());

        return redirect()->to(self::caseUrl($child->id, 'preoperator'))->with('campaign_message', 'Animalul a fost adăugat în aceeași vizită, fără programare nouă. Datele proprietarului și documentele au fost preluate automat.');
    }

    public function removeVisitPet(Request $request, int $case): RedirectResponse
    {
        $this->authorize($request);
        $model = $this->cases->find($case);
        $replacement = $this->cases->removeVisitPet($model, (string) $request->input('reason', ''), $request->user());

        return redirect()->to(self::caseUrl($replacement->id, 'receptie'))->with('campaign_message', 'Pacientul '.$model->nume_pacient.' a fost scos din vizită. Dosarul lui rămâne în istoric, marcat șters.');
    }

    public function delete(Request $request, int $case): RedirectResponse
    {
        $this->authorize($request);
        $model = $this->cases->find($case);
        $this->cases->softDelete($model, (string) $request->input('reason', ''), $request->user());

        return redirect()->to(url('/admin/campanie/dosare'))->with('campaign_message', 'Dosarul '.$model->cod_caz.' a fost marcat șters. Datele rămân în istoric.');
    }

    public function resendFinal(Request $request, int $case): RedirectResponse
    {
        $this->authorize($request);
        [$sent, $error] = app(CampaignMailer::class)->sendFinalSummary($this->cases->find($case), true, $request->user());

        return back()->with($sent ? 'campaign_message' : 'campaign_warning', $sent
            ? 'Email final retransmis. Proprietarul a primit versiunea actualizată, generată din datele curente ale dosarului.'
            : 'Emailul final nu a putut fi retransmis: '.$error);
    }

    public function sendFollowup(Request $request, int $case): RedirectResponse
    {
        $this->authorize($request);
        [$sent, $error] = app(CampaignMailer::class)->sendFollowup($this->cases->find($case), true, $request->user());

        return back()->with($sent ? 'campaign_message' : 'campaign_warning', $sent ? 'Emailul de follow-up a fost trimis.' : 'Emailul de follow-up nu a putut fi trimis: '.$error);
    }

    public function consentCode(Request $request, int $case): RedirectResponse
    {
        $this->authorize($request);
        $consent = $this->cases->checks()->consentFor($this->cases->find($case)->row());
        if (! $consent || $consent->isSigned()) {
            throw ValidationException::withMessages(['consent' => 'Dosarul nu are un consimțământ în așteptarea semnăturii.']);
        }

        return back()->with('campaign_consent_code', app(CampaignConsents::class)->regenerateCode($consent));
    }

    /** Fotografia sau actul de identitate, servite doar personalului, cu jurnal. */
    public function document(Request $request, SterilizationDocument $document)
    {
        $this->authorize($request, Permission::STERILIZATION_VIEW);
        abort_if($document->is_missing || ! $document->stored_path || ! Storage::disk('private')->exists($document->stored_path), 404);
        if ($document->kind === SterilizationDocument::ID_CARD) {
            Audit::log('sterilization.id_card_viewed', $document, 'Act de identitate deschis pentru dosarul #'.$document->sterilization_case_id);
        }

        return response()->file(Storage::disk('private')->path($document->stored_path), [
            'Content-Type' => $document->mime ?: 'application/octet-stream', 'Cache-Control' => 'no-store, private',
            'Content-Disposition' => 'inline; filename="'.($document->kind === SterilizationDocument::PHOTO ? 'poza-pacient' : 'act-identitate').'.'.pathinfo($document->stored_path, PATHINFO_EXTENSION).'"',
        ]);
    }

    /** Pagina de pe telefon pentru fotografia pacientului (link din codul QR al dosarului). */
    public function phonePage(Request $request, string $token)
    {
        $case = SterilizationCase::query()->active()->where('photo_upload_token', $token)->first();

        return response()->view('campaign.phone-photo', ['case' => $case, 'token' => $token, 'uploaded' => $request->boolean('uploaded')], $case ? 200 : 404)
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex');
    }

    public function phoneUpload(Request $request, string $token): RedirectResponse
    {
        $case = SterilizationCase::query()->active()->where('photo_upload_token', $token)->firstOrFail();
        $request->validate(['photo' => ['required', 'file', 'max:20480', 'mimetypes:image/jpeg,image/png,image/webp']], [
            'photo.required' => 'Alege o fotografie: Deschide camera sau Alege din galerie.',
        ]);
        $this->cases->storePhoneUpload($case, $request->file('photo'));
        Audit::log('sterilization.photo_phone_upload', $case, 'Fotografie încărcată de pe telefon pentru '.$case->cod_caz);

        return redirect()->route('campaign.phone', ['token' => $token, 'uploaded' => 1]);
    }

    /** Pentru actualizarea automata a fotografiei in pagina dosarului (ajax_photo_status). */
    public function photoStatus(Request $request, int $case)
    {
        $this->authorize($request, Permission::STERILIZATION_VIEW);
        $model = $this->cases->find($case);
        $doc = $this->cases->checks()->currentDocument($model->id, SterilizationDocument::PHOTO);
        $row = $model->row();

        return response()->json(['document_id' => $doc?->id, 'url' => $doc && ! $doc->is_missing ? route('admin.campaign.document', $doc) : null,
            'uploaded_at' => $row['photo_uploaded_at'], 'source' => $row['photo_upload_source']]);
    }
}
