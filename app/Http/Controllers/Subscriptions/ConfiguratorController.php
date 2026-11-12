<?php

namespace App\Http\Controllers\Subscriptions;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitContractRequest;
use App\Services\Subscriptions\ContractService;
use App\Services\Subscriptions\PricingEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConfiguratorController extends Controller
{
    /** Oferta se calculeaza pe server; browserul nu trimite si nu decide niciun pret. */
    public function offer(Request $request, PricingEngine $pricing): JsonResponse
    {
        $data = $request->validate([
            'species' => ['required', Rule::in(['dog', 'cat'])],
            'weight' => ['required', 'numeric', 'min:0.1', 'max:200'],
        ]);

        [$min, $max] = $pricing->weightBounds($data['species']);
        $weight = (float) $data['weight'];
        $offer = ($weight >= $min && $weight <= $max)
            ? $pricing->offer($data['species'], $weight, $pricing->existingTierLabels($request->user()))
            : null;

        if ($offer === null) {
            return response()->json(['message' => $data['species'] === 'dog'
                ? 'Greutatea câinelui trebuie să fie între 1,25 și 60 kg.'
                : 'Greutatea pisicii trebuie să fie între 0,5 și 70 kg.'], 422);
        }

        return response()->json($offer->toArray());
    }

    public function submit(SubmitContractRequest $request, ContractService $contracts): JsonResponse
    {
        $result = $contracts->submit($request->validated(), $request->user(), $request->ip(), $request->userAgent());
        $contract = $result['contract'];

        return response()->json([
            'message' => $result['duplicate']
                ? 'Contractul a fost deja înregistrat cu plata fizică la clinică.'
                : 'Abonamentul a fost înregistrat. Plata se face fizic la clinică.',
            'number' => $contract->number,
            'duplicate' => $result['duplicate'],
            'account_created' => $result['account_created'],
            'account_url' => site_url($request->user() ? '/panou-cont/' : '/acces-cont/'),
            'valid_until' => $contract->valid_until?->format('d.m.Y'),
        ]);
    }
}
