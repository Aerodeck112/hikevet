@extends('layouts.site')

@section('content')
@php
    $firstName = \Illuminate\Support\Str::before(trim($user->name), ' ') ?: $user->name;
@endphp
<header class="page-head page-head--buddy">
    <div class="wrap">
      <div class="page-panel">
        <div class="page-head-text" style="display:flex;flex-wrap:wrap;gap:1rem;justify-content:space-between;align-items:end">
            <div>
                <p class="script">{{ __('Bine ai revenit') }}</p>
                <h1 class="display h-lg">{{ $user->name }}</h1>
            </div>
            <form method="post" action="{{ site_url('/acces-cont/iesire') }}">@csrf<button class="btn btn-ghost btn-sm" type="submit">{{ __('Ieși din cont') }}</button></form>
        </div>
        <x-buddy class="head-buddy" kind="dog" pose="peek" :say="[__('Ham! Ce bine că ai venit, :name!', ['name' => $firstName]), __('Aici îți ții animăluții, contractele și Wallet-ul.'), __('Ai grijă de bonusuri. Și de mine.')]" />
      </div>
    </div>
</header>

<section style="padding-bottom: var(--section)">
    <div class="wrap">
        @if (session('status'))<p class="notice notice--ok" role="status">{{ session('status') }}</p>@endif
        @if (session('error'))<p class="notice notice--error" role="alert">{{ session('error') }}</p>@endif
        @if ($user->must_set_password)
            <div class="notice notice--warn">
                {{ __('Contul a fost creat automat la semnarea contractului și nu are încă o parolă aleasă de tine.') }}
                <form method="post" action="{{ site_url('/panou-cont/retrimite-activare') }}" style="display:inline">@csrf<button class="btn btn-dark btn-sm" type="submit">{{ __('Retrimite emailul de setare a parolei') }}</button></form>
            </div>
        @endif

        <dl class="acct-stats">
            <div><dt>{{ __('Abonament') }}</dt><dd><span class="badge {{ $hasActive ? 'badge--ok' : 'badge--off' }}">{{ $hasActive ? __('Activ') : __('Inactiv') }}</span></dd></div>
            <div><dt>{{ __('Puncte abonament') }}</dt><dd>{{ number_format($balances['subscription_points'], 0, ',', '.') }}</dd></div>
            <div><dt>{{ __('Wallet, sold principal') }}</dt><dd>{{ \App\Services\Wallet\WalletLedger::money($balances['principal']) }} <small>{{ __('lei') }}</small></dd></div>
            <div><dt>{{ __('Bonus servicii') }}</dt><dd>{{ \App\Services\Wallet\WalletLedger::money($balances['service_bonus']) }} <small>{{ __('lei') }}</small></dd></div>
            <div><dt>{{ __('Bonus produse') }}</dt><dd>{{ \App\Services\Wallet\WalletLedger::money($balances['product_bonus']) }} <small>{{ __('lei') }}</small></dd></div>
        </dl>
        <p class="hint">{{ __('Punctele și bonusurile dau dreptul la servicii în condițiile din contract și din Termeni; nu sunt bani și nu se pot retrage în numerar.') }}</p>

        <div class="acct-grid">
            <div>
                <div class="section-head" style="margin:2.5rem 0 1rem"><h2 class="h-sm">{{ __('Animalele mele') }}</h2><a class="btn btn-primary btn-sm" href="{{ site_url('/abonamente/') }}?hv_new_pet=1#configurator">{{ __('Adaugă animăluț cu abonament') }}</a></div>
                @forelse ($pets as $pet)
                    <article class="card acct-pet">
                        <div class="acct-avatar" aria-hidden="true"><x-hv-icon name="paw" /></div>
                        <div>
                            <h3 class="h-sm" style="font-size:1.2rem">{{ $pet->name }}</h3>
                            <p style="margin:0">{{ $pet->speciesLabel() }}{{ $pet->breed ? ', '.$pet->breed : '' }}{{ $pet->weight_kg ? ', '.rtrim(rtrim(number_format((float) $pet->weight_kg, 2, ',', ''), '0'), ',').' kg' : '' }}</p>
                            @if ($pet->tier_label)<p style="margin:.35rem 0 0"><span class="badge">{{ __('Categoria :tier', ['tier' => $pet->tier_label]) }}</span></p>@endif
                        </div>
                    </article>
                @empty
                    <div class="empty-buddy">
                        <x-buddy kind="cat" :greet="false" :say="[__('Locul ăsta e liber? Atunci mă așez eu.'), __('Adaugă-ți animăluțul, îi țin locul cald.')]" />
                        <p class="notice">{{ __('Nu ai încă niciun animal în cont. Adaugă primul animăluț din configuratorul de abonamente.') }}</p>
                    </div>
                @endforelse

                <h2 class="h-sm" style="margin:2.5rem 0 1rem">{{ __('Contractele mele') }}</h2>
                @forelse ($contracts as $contract)
                    <article class="card" style="margin-bottom:.75rem">
                        <div style="display:flex;flex-wrap:wrap;gap:.5rem 1rem;justify-content:space-between;align-items:start">
                            <div>
                                <h3 class="h-sm" style="font-size:1.15rem">{{ $contract->pet_name }} <span style="font-weight:400">· {{ $contract->number }}</span></h3>
                                <p style="margin:.25rem 0 0">{{ __('Categoria :tier. :monthly lei/lună, 6 luni :six lei, 12 luni :twelve lei.', ['tier' => $contract->tier_label, 'monthly' => $contract->price_monthly, 'six' => $contract->price_6m, 'twelve' => $contract->price_12m]) }}</p>
                            </div>
                            <span class="badge {{ ['active' => 'badge--ok', 'awaiting_clinic' => 'badge--warn'][$contract->status->value] ?? 'badge--off' }}">{{ $contract->status->label() }}</span>
                        </div>
                        <p style="margin:.75rem 0 0">
                            @if ($contract->status->value === 'awaiting_clinic')
                                @php
                                    $payArgs = [
                                        'label' => e($cardPayments && \App\Services\Payments\StripeCheckout::canPayOnline($contract) ? __('De plată (la clinică sau online cu cardul)') : __('De plată la clinică')),
                                        'amount' => e(number_format((float) $contract->payment_amount, 0, ',', '.')),
                                        'period' => e($contract->period_months === 1 ? __('lunar') : __(':count luni', ['count' => $contract->period_months])),
                                    ];
                                @endphp
                                @if ($contract->valid_until)
                                    {!! __(':label: <strong>:amount lei</strong> (:period), până la :date.', $payArgs + ['date' => e($contract->valid_until->format('d.m.Y'))]) !!}
                                @else
                                    {!! __(':label: <strong>:amount lei</strong> (:period).', $payArgs) !!}
                                @endif
                            @elseif ($contract->expires_at)
                                {!! __('Valabil până la <strong>:date</strong>.', ['date' => e($contract->expires_at->format('d.m.Y'))]) !!}
                                @if ($contract->status->value === 'active' && $contract->starts_at && $contract->expires_at->isFuture())
                                    @php
                                        $total = max(1, $contract->starts_at->diffInDays($contract->expires_at));
                                        $left = (int) ceil(now()->diffInDays($contract->expires_at));
                                    @endphp
                                    <span class="term" style="--done: {{ max(4, min(100, round(($total - $left) / $total * 100))) }}%"><span></span></span>
                                    <span class="hint">{{ $left === 1 ? __('Mai este o zi din abonament.') : __('Mai sunt :count zile din abonament.', ['count' => $left]) }}</span>
                                @endif
                            @endif
                        </p>
                        @if ($cardPayments && \App\Services\Payments\StripeCheckout::canPayOnline($contract))
                            <form method="post" action="{{ route('account.pay.subscription', $contract) }}" class="pay-inline" style="margin:.9rem 0 0;display:flex;flex-wrap:wrap;gap:.5rem;align-items:end">
                                @csrf
                                <div class="field" style="margin:0"><label for="pay-{{ $contract->id }}">{{ $contract->status->value === 'awaiting_clinic' ? __('Plătește online') : __('Reînnoiește online') }}</label>
                                    <select id="pay-{{ $contract->id }}" name="months">
                                        @foreach ([1 => __('1 lună'), 6 => __('6 luni'), 12 => __('12 luni')] as $m => $label)
                                            <option value="{{ $m }}" @selected($m === ($contract->period_months ?: 1))>{{ $label }} · {{ __(':amount lei', ['amount' => number_format($contract->priceForPeriod($m), 0, ',', '.')]) }}</option>
                                        @endforeach
                                    </select></div>
                                <button class="btn btn-primary btn-sm" type="submit">{{ __('Plătește cu cardul') }}</button>
                            </form>
                        @endif
                        @if ($contract->pdf_path)
                            <p style="margin:.75rem 0 0"><a class="btn btn-ghost btn-sm" href="{{ route('account.contract.pdf', $contract) }}"><x-hv-icon name="download" width="18" height="18" /> {{ __('Descarcă contractul (PDF)') }}</a></p>
                        @endif
                    </article>
                @empty
                    <div class="empty-buddy">
                        <x-buddy kind="dog" pose="peek" :greet="false" :say="[__('Niciun contract încă. Eu zic să alegi 12 luni.'), __('Calculatorul de la Abonamente îți arată prețul exact.')]" />
                        <p class="notice">{{ __('Nu ai încă niciun contract.') }}</p>
                    </div>
                @endforelse

                <h2 class="h-sm" style="margin:2.5rem 0 1rem">{{ __('Istoric puncte și Wallet') }}</h2>
                @if ($transactions->isEmpty())
                    <p class="notice">{{ __('Încă nu există operațiuni.') }}</p>
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th scope="col">{{ __('Data') }}</th><th scope="col">{{ __('Operațiune') }}</th><th scope="col">{{ __('Sold') }}</th><th scope="col">{{ __('Valoare') }}</th></tr></thead>
                        <tbody>
                        @foreach ($transactions as $tx)
                            <tr><td>{{ $tx->created_at->format('d.m.Y') }}</td><td>{{ $tx->note }}</td><td>{{ $tx->balance_type->label() }}</td>
                                <td style="white-space:nowrap">{{ $tx->balance_type->isPoints() ? __(':points puncte', ['points' => sprintf('%+d', $tx->points_delta)]) : __(':amount lei', ['amount' => sprintf('%+.2f', (float) $tx->amount_delta)]) }}</td></tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>

            <aside>
                <div class="card" style="margin-top:2.5rem">
                    <h2 class="h-sm">{{ __('Alimentează Wallet-ul') }}</h2>
                    @if ($hasActive)
                        <div x-data="walletCalc()" x-init="amount = 200">
                            <p style="margin:.6rem 0 .9rem">{{ __('Alege suma; vezi pe loc cât primești.') }}{{ $cardPayments ? ' '.__('Plătești online cu cardul sau la clinică.') : ' '.__('Plata se face la clinică.') }}</p>
                            <div class="amount-chips" role="group" aria-label="{{ __('Sume rapide') }}">
                                @foreach ([100, 200, 500, 1000] as $chip)
                                    <button type="button" :aria-pressed="(amount === {{ $chip }}).toString()" @click="amount = {{ $chip }}">{{ __(':amount lei', ['amount' => $chip]) }}</button>
                                @endforeach
                            </div>
                            <div class="field" style="margin-top:.75rem"><label for="w-amount">{{ __('Altă sumă (lei)') }}</label><input id="w-amount" type="number" min="{{ $cardPayments ? \App\Services\Payments\StripeCheckout::WALLET_MIN : 1 }}" max="{{ \App\Services\Payments\StripeCheckout::WALLET_MAX }}" step="1" inputmode="numeric" x-model.number="amount">@error('amount')<span class="error">{{ $message }}</span>@enderror</div>
                            <dl class="topup-preview" aria-live="polite">
                                <div class="tp-principal"><dt>{{ __('Sold principal') }}</dt><dd x-text="money(principal || 0) + ' ' + @js(__('lei'))"></dd></div>
                                <div class="tp-service"><dt>{{ __('Bonus servicii') }}</dt><dd x-text="'+' + money(serviceBonus || 0) + ' ' + @js(__('lei'))"></dd></div>
                                <div class="tp-product"><dt>{{ __('Bonus produse') }}</dt><dd x-text="'+' + money(productBonus || 0) + ' ' + @js(__('lei'))"></dd></div>
                            </dl>
                            @if ($cardPayments)
                                <form method="post" action="{{ route('account.pay.wallet') }}">
                                    @csrf
                                    <input type="hidden" name="amount" :value="amount">
                                    <button class="btn btn-primary" type="submit" style="width:100%" :disabled="!amount || amount < {{ \App\Services\Payments\StripeCheckout::WALLET_MIN }}">{!! __('Plătește :amount lei cu cardul', ['amount' => '<span x-text="money(amount || 0)"></span>']) !!}</button>
                                    <p class="hint" style="margin:.5rem 0 1rem">{{ __('Plata se face pe pagina securizată Stripe; datele cardului nu ajung la noi.') }}</p>
                                </form>
                            @endif
                            <form method="post" action="{{ site_url('/panou-cont/wallet/solicitare') }}">
                                @csrf
                                <input type="hidden" name="amount" :value="amount">
                                <button class="btn {{ $cardPayments ? 'btn-ghost' : 'btn-dark' }}" type="submit" style="width:100%" :disabled="!amount || amount < 1">{{ __('Plătesc la clinică') }}</button>
                            </form>
                        </div>
                        @foreach ($onlinePayments as $op)
                            <p class="hint" style="margin:.75rem 0 0">{{ $op->created_at->format('d.m.Y') }}: {{ $op->purposeLabel() }}, {{ __(':amount lei', ['amount' => \App\Services\Wallet\WalletLedger::money((float) $op->amount)]) }}, {{ __('card') }}, {{ ['paid' => __('plătită'), 'paid_needs_review' => __('plătită, în verificare la clinică'), 'pending' => __('în curs')][$op->status] ?? $op->status }}</p>
                        @endforeach
                        @foreach ($topups as $topup)
                            <p class="hint" style="margin:.75rem 0 0">{{ $topup->created_at->format('d.m.Y') }}: {{ __(':amount lei', ['amount' => \App\Services\Wallet\WalletLedger::money((float) $topup->amount)]) }}, {{ ['pending' => __('în așteptare'), 'confirmed' => __('confirmată'), 'rejected' => __('respinsă')][$topup->status] ?? $topup->status }}</p>
                        @endforeach
                    @else
                        <p style="margin:.6rem 0 0">{{ __('Wallet-ul poate fi alimentat doar cu un abonament activ.') }}</p>
                    @endif
                </div>

                <div class="card" style="margin-top:1rem">
                    <h2 class="h-sm">{{ __('Datele mele') }}</h2>
                    <form method="post" action="{{ site_url('/panou-cont/profil') }}" style="margin-top:1rem">
                        @csrf
                        <div class="field"><label for="pr-name">{{ __('Nume și prenume') }}</label><input id="pr-name" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required></div>
                        <div class="field"><label for="pr-phone">{{ __('Telefon') }}</label><input id="pr-phone" type="tel" name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel"></div>
                        <div class="field"><label for="pr-address">{{ __('Adresă') }}</label><input id="pr-address" name="address" value="{{ old('address', $user->address) }}" autocomplete="street-address"></div>
                        <div class="field"><label for="pr-city">{{ __('Localitate') }}</label><input id="pr-city" name="city" value="{{ old('city', $user->city) }}" autocomplete="address-level2"></div>
                        <label class="check"><input type="checkbox" name="marketing" value="1" @checked($user->marketing_opt_in_at)><span>{{ __('Vreau să primesc noutăți și oferte HiKeVet pe email. Mesajele despre contract și programări le primești oricum.') }}</span></label>
                        <p style="margin-top:1rem"><button class="btn btn-ghost" type="submit">{{ __('Salvează') }}</button></p>
                    </form>
                </div>

                <div class="card" style="margin-top:1rem">
                    <h2 class="h-sm">{{ __('Notificări') }}</h2>
                    @forelse ($notifications as $n)
                        <div style="padding:.75rem 0;border-bottom:1px solid var(--line)">
                            <strong style="color:var(--ink)">{{ $n->subject }}</strong><br>
                            <span class="hint">{{ $n->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                    @empty
                        <p style="margin:.6rem 0 0">{{ __('Nu ai notificări.') }}</p>
                    @endforelse
                </div>
            </aside>
        </div>
    </div>
</section>
@endsection
