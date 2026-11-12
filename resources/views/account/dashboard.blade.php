@extends('layouts.site')

@section('content')
<header class="page-head">
    <div class="wrap" style="display:flex;flex-wrap:wrap;gap:1rem;justify-content:space-between;align-items:end">
        <div>
            <p class="script">Bine ai revenit</p>
            <h1 class="display h-lg">{{ $user->name }}</h1>
        </div>
        <form method="post" action="{{ site_url('/acces-cont/iesire') }}">@csrf<button class="btn btn-ghost btn-sm" type="submit">Ieși din cont</button></form>
    </div>
</header>

<section style="padding-bottom: var(--section)">
    <div class="wrap">
        @if (session('status'))<p class="notice notice--ok" role="status">{{ session('status') }}</p>@endif
        @if (session('error'))<p class="notice notice--error" role="alert">{{ session('error') }}</p>@endif
        @if ($user->must_set_password)
            <div class="notice notice--warn">
                Contul a fost creat automat la semnarea contractului și nu are încă o parolă aleasă de tine.
                <form method="post" action="{{ site_url('/panou-cont/retrimite-activare') }}" style="display:inline">@csrf<button class="btn btn-dark btn-sm" type="submit">Retrimite emailul de setare a parolei</button></form>
            </div>
        @endif

        <dl class="acct-stats">
            <div><dt>Abonament</dt><dd><span class="badge {{ $hasActive ? 'badge--ok' : 'badge--off' }}">{{ $hasActive ? 'Activ' : 'Inactiv' }}</span></dd></div>
            <div><dt>Puncte abonament</dt><dd>{{ number_format($balances['subscription_points'], 0, ',', '.') }}</dd></div>
            <div><dt>Wallet, sold principal</dt><dd>{{ \App\Services\Wallet\WalletLedger::money($balances['principal']) }} <small>lei</small></dd></div>
            <div><dt>Bonus servicii</dt><dd>{{ \App\Services\Wallet\WalletLedger::money($balances['service_bonus']) }} <small>lei</small></dd></div>
            <div><dt>Bonus produse</dt><dd>{{ \App\Services\Wallet\WalletLedger::money($balances['product_bonus']) }} <small>lei</small></dd></div>
        </dl>
        <p class="hint">Punctele și bonusurile dau dreptul la servicii în condițiile din contract și din Termeni; nu sunt bani și nu se pot retrage în numerar.</p>

        <div class="acct-grid">
            <div>
                <div class="section-head" style="margin:2.5rem 0 1rem"><h2 class="h-sm">Animalele mele</h2><a class="btn btn-primary btn-sm" href="{{ site_url('/abonamente/') }}?hv_new_pet=1#configurator">Adaugă animăluț cu abonament</a></div>
                @forelse ($pets as $pet)
                    <article class="card acct-pet">
                        <div class="acct-avatar" aria-hidden="true"><x-hv-icon name="paw" /></div>
                        <div>
                            <h3 class="h-sm" style="font-size:1.2rem">{{ $pet->name }}</h3>
                            <p style="margin:0">{{ $pet->speciesLabel() }}{{ $pet->breed ? ', '.$pet->breed : '' }}{{ $pet->weight_kg ? ', '.rtrim(rtrim(number_format((float) $pet->weight_kg, 2, ',', ''), '0'), ',').' kg' : '' }}</p>
                            @if ($pet->tier_label)<p style="margin:.35rem 0 0"><span class="badge">Categoria {{ $pet->tier_label }}</span></p>@endif
                        </div>
                    </article>
                @empty
                    <p class="notice">Nu ai încă niciun animal în cont. Adaugă primul animăluț din configuratorul de abonamente.</p>
                @endforelse

                <h2 class="h-sm" style="margin:2.5rem 0 1rem">Contractele mele</h2>
                @forelse ($contracts as $contract)
                    <article class="card" style="margin-bottom:.75rem">
                        <div style="display:flex;flex-wrap:wrap;gap:.5rem 1rem;justify-content:space-between;align-items:start">
                            <div>
                                <h3 class="h-sm" style="font-size:1.15rem">{{ $contract->pet_name }} <span style="font-weight:400">· {{ $contract->number }}</span></h3>
                                <p style="margin:.25rem 0 0">Categoria {{ $contract->tier_label }}. {{ $contract->price_monthly }} lei/lună, 6 luni {{ $contract->price_6m }} lei, 12 luni {{ $contract->price_12m }} lei.</p>
                            </div>
                            <span class="badge {{ ['active' => 'badge--ok', 'awaiting_clinic' => 'badge--warn'][$contract->status->value] ?? 'badge--off' }}">{{ $contract->status->label() }}</span>
                        </div>
                        <p style="margin:.75rem 0 0">
                            @if ($contract->status->value === 'awaiting_clinic')
                                De plată la clinică: <strong>{{ number_format((float) $contract->payment_amount, 0, ',', '.') }} lei</strong> ({{ $contract->period_months === 1 ? 'lunar' : $contract->period_months.' luni' }})@if ($contract->valid_until), până la {{ $contract->valid_until->format('d.m.Y') }}@endif.
                            @elseif ($contract->expires_at)
                                Valabil până la <strong>{{ $contract->expires_at->format('d.m.Y') }}</strong>.
                            @endif
                        </p>
                        @if ($contract->pdf_path)
                            <p style="margin:.75rem 0 0"><a class="btn btn-ghost btn-sm" href="{{ route('account.contract.pdf', $contract) }}"><x-hv-icon name="download" width="18" height="18" /> Descarcă contractul (PDF)</a></p>
                        @endif
                    </article>
                @empty
                    <p class="notice">Nu ai încă niciun contract.</p>
                @endforelse

                <h2 class="h-sm" style="margin:2.5rem 0 1rem">Istoric puncte și Wallet</h2>
                @if ($transactions->isEmpty())
                    <p class="notice">Încă nu există operațiuni.</p>
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th scope="col">Data</th><th scope="col">Operațiune</th><th scope="col">Sold</th><th scope="col">Valoare</th></tr></thead>
                        <tbody>
                        @foreach ($transactions as $tx)
                            <tr><td>{{ $tx->created_at->format('d.m.Y') }}</td><td>{{ $tx->note }}</td><td>{{ $tx->balance_type->label() }}</td>
                                <td style="white-space:nowrap">{{ $tx->balance_type->isPoints() ? sprintf('%+d puncte', $tx->points_delta) : sprintf('%+.2f lei', (float) $tx->amount_delta) }}</td></tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>

            <aside>
                <div class="card" style="margin-top:2.5rem">
                    <h2 class="h-sm">Alimentează Wallet-ul</h2>
                    @if ($hasActive)
                        <p style="margin:.6rem 0 1rem">La 100 lei depuși primești 100 lei sold principal, 100 lei bonus pentru servicii și 50 lei bonus pentru produse. Plata se face la clinică.</p>
                        <form method="post" action="{{ site_url('/panou-cont/wallet/solicitare') }}">
                            @csrf
                            <div class="field"><label for="w-amount">Suma dorită (lei)</label><input id="w-amount" type="number" name="amount" min="1" step="1" inputmode="numeric" required>@error('amount')<span class="error">{{ $message }}</span>@enderror</div>
                            <button class="btn btn-dark" type="submit">Trimite solicitarea</button>
                        </form>
                        @foreach ($topups as $topup)
                            <p class="hint" style="margin:.75rem 0 0">{{ $topup->created_at->format('d.m.Y') }}: {{ \App\Services\Wallet\WalletLedger::money((float) $topup->amount) }} lei, {{ ['pending' => 'în așteptare', 'confirmed' => 'confirmată', 'rejected' => 'respinsă'][$topup->status] ?? $topup->status }}</p>
                        @endforeach
                    @else
                        <p style="margin:.6rem 0 0">Wallet-ul poate fi alimentat doar cu un abonament activ.</p>
                    @endif
                </div>

                <div class="card" style="margin-top:1rem">
                    <h2 class="h-sm">Datele mele</h2>
                    <form method="post" action="{{ site_url('/panou-cont/profil') }}" style="margin-top:1rem">
                        @csrf
                        <div class="field"><label for="pr-name">Nume și prenume</label><input id="pr-name" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required></div>
                        <div class="field"><label for="pr-phone">Telefon</label><input id="pr-phone" type="tel" name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel"></div>
                        <div class="field"><label for="pr-address">Adresă</label><input id="pr-address" name="address" value="{{ old('address', $user->address) }}" autocomplete="street-address"></div>
                        <div class="field"><label for="pr-city">Localitate</label><input id="pr-city" name="city" value="{{ old('city', $user->city) }}" autocomplete="address-level2"></div>
                        <label class="check"><input type="checkbox" name="marketing" value="1" @checked($user->marketing_opt_in_at)><span>Vreau să primesc noutăți și oferte HiKeVet pe email. Mesajele despre contract și programări le primești oricum.</span></label>
                        <p style="margin-top:1rem"><button class="btn btn-ghost" type="submit">Salvează</button></p>
                    </form>
                </div>

                <div class="card" style="margin-top:1rem">
                    <h2 class="h-sm">Notificări</h2>
                    @forelse ($notifications as $n)
                        <div style="padding:.75rem 0;border-bottom:1px solid var(--line)">
                            <strong style="color:var(--ink)">{{ $n->subject }}</strong><br>
                            <span class="hint">{{ $n->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                    @empty
                        <p style="margin:.6rem 0 0">Nu ai notificări.</p>
                    @endforelse
                </div>
            </aside>
        </div>
    </div>
</section>
@endsection
