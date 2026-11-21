<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\OnlinePayment;
use App\Services\Payments\StripeCheckout;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Plata online din contul clientului: Wallet si abonament, prin pagina securizata Stripe. */
class PaymentController extends Controller
{
    public function __construct(private readonly StripeCheckout $stripe) {}

    public function wallet(Request $request): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:'.StripeCheckout::WALLET_MIN, 'max:'.StripeCheckout::WALLET_MAX]], [], ['amount' => __('suma')]);
        try {
            $start = $this->stripe->startWalletTopup($request->user(), (float) $data['amount']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->away($start['url']);
    }

    public function subscription(Request $request, Contract $contract): RedirectResponse
    {
        abort_unless($contract->user_id === $request->user()->id, 403);
        $data = $request->validate(['months' => ['required', 'in:1,6,12']]);
        try {
            $start = $this->stripe->startSubscription($request->user(), $contract, (int) $data['months']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->away($start['url']);
    }

    public function confirm(Request $request, OnlinePayment $payment): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 403);
        $payment = $this->stripe->confirmReturn($payment);

        return redirect()->to(site_url('/panou-cont/'))->with(...match ($payment->status) {
            'paid' => ['status', $payment->purpose === OnlinePayment::WALLET
                ? __('Plata a fost primită. Wallet-ul a fost alimentat, inclusiv cu bonusurile.')
                : __('Plata a fost primită. Abonamentul este activ.')],
            'paid_needs_review' => ['status', __('Plata a fost primită. Clinica o verifică și te contactează dacă este nevoie de ceva.')],
            default => ['status', __('Plata este în curs de confirmare. Dacă ai plătit, contul se actualizează în câteva minute.')],
        });
    }

    public function cancel(Request $request, OnlinePayment $payment): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 403);

        return redirect()->to(site_url('/panou-cont/'))->with('error', __('Plata nu a fost finalizată. Nu s-a încasat nimic.'));
    }
}
