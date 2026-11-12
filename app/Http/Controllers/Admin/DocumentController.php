<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\Contract;
use App\Services\Consents\ConsentService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Documente cu date personale: servite doar personalului autorizat, cu fiecare acces trecut in jurnal. */
class DocumentController extends Controller
{
    public function contractPdf(Request $request, Contract $contract)
    {
        abort_unless($request->user()->can(Permission::CONTRACTS_VIEW), 403);
        abort_unless($contract->pdf_path && Storage::disk('private')->exists($contract->pdf_path), 404);
        Audit::log('contract.viewed', $contract, 'Contract '.$contract->number.' deschis de personal', null, null, $contract->user_id);

        return Storage::disk('private')->response($contract->pdf_path, 'contract-'.$contract->number.'.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'no-store, private']);
    }

    public function consentPrint(Request $request, Consent $consent, ConsentService $consents)
    {
        abort_unless($request->user()->can(Permission::CONSENTS_MANAGE), 403);
        Audit::log('consent.viewed', $consent, 'Consimțământ #'.$consent->id.' deschis pentru tipărire', null, null, $consent->client_id);

        return response()->view('consent.print', [
            'consent' => $consent, 'p' => $consent->payload,
            'intact' => $consent->isSigned() ? $consents->verifyIntegrity($consent) : null,
        ])->header('Cache-Control', 'no-store, private');
    }
}
