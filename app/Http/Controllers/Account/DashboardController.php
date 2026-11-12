<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\WalletTopupRequest;
use App\Services\Clients\ClientAccounts;
use App\Services\Notifications\Notifier;
use App\Services\Seo\Seo;
use App\Services\Wallet\WalletLedger;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function index(Request $request, Seo $seo, WalletLedger $ledger)
    {
        $user = $request->user();
        $seo->page('Contul meu');
        $seo->noindex = true;

        return view('account.dashboard', [
            'seo' => $seo, 'user' => $user,
            'pets' => $user->activePets()->get(),
            'contracts' => $user->contracts()->with('pet')->get(),
            'balances' => $ledger->balances($user),
            'hasActive' => $ledger->hasActiveSubscription($user),
            'transactions' => $user->walletTransactions()->latest('id')->limit(25)->get(),
            'treatments' => $user->treatments()->whereNull('deleted_at')->latest('id')->limit(15)->get(),
            'notifications' => $user->clientNotifications()->limit(10)->get(),
            'topups' => WalletTopupRequest::query()->where('user_id', $user->id)->latest('id')->limit(5)->get(),
        ]);
    }

    /** Contractul se descarca doar de catre titular; fisierul nu are URL public. */
    public function contractPdf(Request $request, Contract $contract)
    {
        abort_unless($contract->user_id === $request->user()->id, 403);
        abort_unless($contract->pdf_path && Storage::disk('private')->exists($contract->pdf_path), 404);
        Audit::log('contract.downloaded', $contract, 'Contract descărcat de client', null, null, $contract->user_id);

        return Storage::disk('private')->download($contract->pdf_path, 'contract-'.$contract->number.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    /** Clientul doar solicita alimentarea; soldul se schimba numai cand personalul confirma plata la clinica. */
    public function requestTopup(Request $request, WalletLedger $ledger, Notifier $notifier)
    {
        $user = $request->user();
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:1', 'max:20000']], [], ['amount' => 'suma']);

        if (! $ledger->hasActiveSubscription($user)) {
            return back()->with('error', 'Wallet-ul poate fi alimentat doar cu abonament activ.');
        }

        $amount = round((float) $data['amount'], 2);
        $topup = WalletTopupRequest::create(['user_id' => $user->id, 'amount' => $amount]);
        $html = 'Clientul <strong>'.e($user->name).'</strong> a solicitat o depunere de <strong>'.WalletLedger::money($amount).' lei</strong>.<br>'
            .'După confirmarea plății la clinică: principal '.WalletLedger::money($amount).' lei, bonus servicii '.WalletLedger::money($amount).' lei, bonus produse '.WalletLedger::money($amount / 2).' lei.';
        $notifier->client($user, 'Solicitare alimentare Wallet promoțional', $html, 'wallet_request', $topup->id);
        $notifier->send(Settings::emails('mail.clinic_recipients'), 'HikeVet - solicitare wallet la clinică', $html, 'wallet_request', 'wallet_request', $topup->id, $user->id);

        return back()->with('status', 'Solicitarea a fost trimisă. Plata se face la clinică, iar soldul apare după confirmare.');
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'], 'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:250'], 'city' => ['nullable', 'string', 'max:120'],
            'marketing' => ['nullable', 'boolean'],
        ]);

        $user->forceFill(['name' => $data['name'], 'phone' => $data['phone'] ?? null, 'address' => $data['address'] ?? null, 'city' => $data['city'] ?? null,
            'marketing_opt_in_at' => $request->boolean('marketing') ? ($user->marketing_opt_in_at ?? now()) : null])->save();

        return back()->with('status', 'Datele au fost salvate.');
    }

    public function resendActivation(Request $request, ClientAccounts $accounts)
    {
        $accounts->sendActivation($request->user());

        return back()->with('status', 'Am retrimis emailul pentru setarea parolei.');
    }
}
