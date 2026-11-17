<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\Payments\StripeCheckout;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Notificarile Stripe: acceptate doar cu semnatura valida; procesarea este idempotenta. */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeCheckout $stripe): Response
    {
        $payload = $request->getContent();
        if (! StripeCheckout::validSignature($payload, $request->header('Stripe-Signature'), StripeCheckout::webhookSecret())) {
            return response('Semnătură invalidă', 400);
        }
        $event = json_decode($payload, true);
        if (! is_array($event)) {
            return response('Conținut invalid', 400);
        }
        $stripe->handleEvent($event);

        return response('ok', 200);
    }
}
