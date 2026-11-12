<?php

namespace App\Http\Controllers\Tablet;

use App\Enums\ConsentStatus;
use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Services\Consents\ConsentService;
use App\Services\Seo\Seo;
use DomainException;
use Illuminate\Http\Request;

/**
 * Pagina fixa a tabletei de la receptie. Codul de 6 cifre deschide documentul; tokenul ramane
 * in sesiune si nu apare niciodata in adresa.
 */
class ConsentTabletController extends Controller
{
    private const SESSION_KEY = 'tablet_consent_id';

    public function index(Request $request, Seo $seo)
    {
        $seo->page('Consimțământ pe tabletă');
        $seo->noindex = true;

        return view('tablet.code', ['seo' => $seo]);
    }

    public function open(Request $request, ConsentService $consents)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']], ['code.required' => 'Introdu codul de 6 cifre primit de la recepție.']);
        $consent = $consents->findByCode($data['code']);

        if (! $consent) {
            // Acelasi mesaj pentru cod gresit, expirat sau blocat.
            return back()->withErrors(['code' => 'Codul nu este valid sau a expirat. Cere un cod nou la recepție.']);
        }

        $request->session()->put(self::SESSION_KEY, $consent->id);
        $request->session()->regenerate();

        return redirect()->route('tablet.document');
    }

    public function document(Request $request, Seo $seo)
    {
        $consent = $this->current($request);
        if (! $consent) {
            return redirect()->route('tablet');
        }
        $seo->page('Consimțământ informat');
        $seo->noindex = true;

        return view('tablet.document', ['seo' => $seo, 'consent' => $consent, 'p' => $consent->payload,
            'hasRefused' => ! empty($consent->payload['refused_services']) || ! empty($consent->payload['refused_other'])]);
    }

    public function sign(Request $request, ConsentService $consents)
    {
        $consent = $this->current($request);
        if (! $consent) {
            return redirect()->route('tablet')->withErrors(['code' => 'Sesiunea a expirat. Introdu din nou codul.']);
        }

        try {
            $consents->sign($consent, $request->only(['food_status', 'food_detail', 'owner_signature', 'refusal_signature',
                ...ConsentService::MEDIA_KEYS, ...ConsentService::FINAL_KEYS]), $request->ip());
        } catch (DomainException $e) {
            return back()->withInput($request->except(['owner_signature', 'refusal_signature']))->withErrors(['sign' => $e->getMessage()]);
        }

        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('tablet')->with('signed', true);
    }

    public function close(Request $request)
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('tablet');
    }

    private function current(Request $request): ?Consent
    {
        $id = $request->session()->get(self::SESSION_KEY);
        $consent = $id ? Consent::query()->find($id) : null;

        return $consent && $consent->status === ConsentStatus::Pending ? $consent : null;
    }
}
