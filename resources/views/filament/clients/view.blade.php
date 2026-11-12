<x-filament-panels::page>
    <div class="hv-client">
        @foreach ($alerts as [$kind, $text])
            <p class="hv-pill hv-pill--{{ $kind === 'bad' ? 'bad' : ($kind === 'warn' ? 'warn' : 'off') }}" style="padding:.5rem 1rem;font-size:.9rem">{{ $text }}</p>
        @endforeach

        <dl class="hv-stats">
            <div class="hv-stat"><dt>Abonament</dt><dd><span class="hv-pill {{ $hasActive ? 'hv-pill--ok' : 'hv-pill--off' }}">{{ $hasActive ? 'Activ' : 'Inactiv' }}</span></dd></div>
            <div class="hv-stat"><dt>Puncte abonament</dt><dd>{{ number_format($balances['subscription_points'], 0, ',', '.') }}</dd></div>
            @if ($canWallet)
                <div class="hv-stat"><dt>Wallet, sold principal</dt><dd>{{ \App\Services\Wallet\WalletLedger::money($balances['principal']) }} <small>lei</small></dd></div>
                <div class="hv-stat"><dt>Bonus servicii</dt><dd>{{ \App\Services\Wallet\WalletLedger::money($balances['service_bonus']) }} <small>lei</small></dd></div>
                <div class="hv-stat"><dt>Bonus produse</dt><dd>{{ \App\Services\Wallet\WalletLedger::money($balances['product_bonus']) }} <small>lei</small></dd></div>
            @endif
        </dl>

        <div class="hv-grid-2">
            <section class="hv-card">
                <h3>Proprietar</h3>
                <p>{{ $client->email }}@if ($client->phone) · {{ $client->phone }}@endif</p>
                <p class="hv-muted">{{ collect([$client->address, $client->city])->filter()->implode(', ') ?: 'Adresă necompletată' }} · ID client {{ $client->id }}</p>
                @if ($client->internal_notes)<p style="margin-top:.5rem"><strong>Observații interne:</strong> {{ $client->internal_notes }}</p>@endif
                @foreach ($topups as $topup)
                    <p class="hv-pill hv-pill--warn" style="margin-top:.5rem">Solicită alimentare Wallet: {{ \App\Services\Wallet\WalletLedger::money((float) $topup->amount) }} lei ({{ $topup->created_at->format('d.m.Y') }})</p>
                @endforeach
            </section>

            <section class="hv-card">
                <h3>Animale</h3>
                <div class="hv-scroll"><table class="hv-table">
                    <thead><tr><th>Nume</th><th>Specie, rasă</th><th>Greutate</th><th>Categorie</th><th>Microcip</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($pets as $pet)
                        <tr @if ($pet->isArchived()) style="opacity:.55" @endif>
                            <td><strong>{{ $pet->name }}</strong>@if ($pet->isArchived())<br><span class="hv-pill hv-pill--off">Arhivat</span>@endif</td>
                            <td>{{ $pet->speciesLabel() }}{{ $pet->breed ? ', '.$pet->breed : '' }}</td>
                            <td>{{ $pet->weight_kg ? rtrim(rtrim(number_format((float) $pet->weight_kg, 2, ',', ''), '0'), ',').' kg' : '–' }}</td>
                            <td>{{ $pet->tier_label ?: '–' }}</td>
                            <td>{{ $pet->microchip ?: '–' }}</td>
                            <td style="white-space:nowrap">
                                @if ($pet->isArchived()) {{ ($this->restorePetAction)(['id' => $pet->id]) }}
                                @else {{ ($this->editPetAction)(['id' => $pet->id]) }} {{ ($this->archivePetAction)(['id' => $pet->id]) }} @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="hv-muted">Niciun animal în cont.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </section>
        </div>

        @if ($canContracts)
        <section class="hv-card">
            <h3>Contracte și abonamente</h3>
            <div class="hv-scroll"><table class="hv-table">
                <thead><tr><th>Număr</th><th>Animal</th><th>Categorie</th><th>Preț (lunar / 6 / 12)</th><th>Reducere</th><th>Stare</th><th>Plată</th><th>Expiră</th><th></th></tr></thead>
                <tbody>
                @forelse ($contracts as $contract)
                    <tr>
                        <td><strong>{{ $contract->number }}</strong><br><span class="hv-muted">semnat {{ $contract->signed_at?->format('d.m.Y') }}</span></td>
                        <td>{{ $contract->pet_name }}</td>
                        <td>{{ $contract->tier_label }}</td>
                        <td>{{ $contract->price_monthly }} / {{ $contract->price_6m }} / {{ $contract->price_12m }} lei</td>
                        <td>{{ $contract->discount_percent }}%<br><span class="hv-muted">animalul nr. {{ $contract->pet_ordinal }}</span></td>
                        <td><span class="hv-pill {{ ['active' => 'hv-pill--ok', 'awaiting_clinic' => 'hv-pill--warn', 'cancelled' => 'hv-pill--bad'][$contract->status->value] ?? 'hv-pill--off' }}">{{ $contract->status->label() }}</span></td>
                        <td>{{ number_format((float) $contract->payment_amount, 0, ',', '.') }} lei, {{ $contract->period_months === 1 ? 'lunar' : $contract->period_months.' luni' }}@if ($contract->paid_at)<br><span class="hv-muted">confirmată {{ $contract->paid_at->format('d.m.Y') }}{{ $contract->validator ? ' de '.$contract->validator->name : '' }}</span>@endif</td>
                        <td>{{ $contract->expires_at?->format('d.m.Y') ?? ($contract->valid_until ? 'ofertă până la '.$contract->valid_until->format('d.m.Y') : '–') }}</td>
                        <td>@if ($contract->pdf_path)<a class="fi-link" style="color:#0b5c3b;font-weight:600" href="{{ route('admin.contracts.pdf', $contract) }}" target="_blank" rel="noopener">PDF</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="hv-muted">Niciun contract.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
        @endif

        <section class="hv-card">
            <h3>Tratamente și plăți din Wallet</h3>
            <div class="hv-scroll"><table class="hv-table">
                <thead><tr><th>Data</th><th>Animal</th><th>Serviciu / produs</th><th>Tip</th><th>Consum</th><th>Observații</th><th></th></tr></thead>
                <tbody>
                @forelse ($treatments as $t)
                    <tr @if ($t->isDeleted()) style="opacity:.55" @endif>
                        <td>{{ $t->created_at->format('d.m.Y H:i') }}</td>
                        <td>{{ $t->pet_name ?: '–' }}</td>
                        <td>{{ $t->name }}@if ($t->isDeleted())<br><span class="hv-pill hv-pill--off">Șters: {{ $t->delete_reason }}</span>@endif</td>
                        <td>{{ ['subscription' => 'Abonament', 'service' => 'Wallet, serviciu', 'product' => 'Wallet, produs'][$t->payment_category] ?? $t->payment_category }}</td>
                        <td>@if ($t->payment_category === 'subscription') {{ $t->points_used }} puncte @else {{ \App\Services\Wallet\WalletLedger::money((float) $t->principal_used) }} lei principal + {{ \App\Services\Wallet\WalletLedger::money((float) $t->bonus_used) }} lei bonus @endif</td>
                        <td>{{ $t->notes }}</td>
                        <td>@if ($t->isDeleted()) {{ ($this->restoreTreatmentAction)(['id' => $t->id]) }} @else {{ ($this->deleteTreatmentAction)(['id' => $t->id]) }} @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="hv-muted">Nicio operațiune înregistrată.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>

        <div class="hv-grid-2">
            @if ($canWallet)
            <section class="hv-card">
                <h3>Registru puncte și Wallet</h3>
                <p class="hv-muted" style="margin-bottom:.5rem">Registrul nu se poate modifica. Corecțiile se fac prin operațiuni inverse.</p>
                <div class="hv-scroll" style="max-height:26rem;overflow-y:auto"><table class="hv-table">
                    <thead><tr><th>Data</th><th>Sold</th><th>Valoare</th><th>Detalii</th></tr></thead>
                    <tbody>
                    @forelse ($transactions as $tx)
                        <tr>
                            <td>{{ $tx->created_at->format('d.m.Y H:i') }}</td>
                            <td>{{ $tx->balance_type->label() }}</td>
                            <td style="white-space:nowrap;font-weight:600">{{ $tx->balance_type->isPoints() ? sprintf('%+d p', $tx->points_delta) : sprintf('%+.2f lei', (float) $tx->amount_delta) }}</td>
                            <td>{{ $tx->note }}<br><span class="hv-muted">{{ $tx->actor_name ?? $tx->actor?->name ?? 'sistem' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="hv-muted">Fără tranzacții.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </section>
            @endif

            <section class="hv-card">
                <h3>Notițe interne</h3>
                @forelse ($notes as $note)
                    <div style="padding:.6rem 0;border-bottom:1px solid rgb(244 244 245)">
                        <span class="hv-pill {{ $note->status === 'open' ? ($note->priority === 'normal' ? 'hv-pill--warn' : 'hv-pill--bad') : 'hv-pill--off' }}">{{ $note->status === 'open' ? 'Nerezolvată' : 'Rezolvată' }}{{ $note->priority !== 'normal' ? ' · prioritate '.['high' => 'ridicată', 'urgent' => 'urgentă'][$note->priority] : '' }}</span>
                        <p style="margin:.35rem 0">{{ $note->note_text }}</p>
                        <span class="hv-muted">{{ $note->created_at->format('d.m.Y H:i') }} · {{ $note->actor?->name }}{{ $note->pet ? ' · '.$note->pet->name : '' }}</span>
                        {{ ($this->toggleNoteAction)(['id' => $note->id]) }}
                    </div>
                @empty
                    <p class="hv-muted">Nicio notiță.</p>
                @endforelse
            </section>
        </div>

        @if ($audit->isNotEmpty())
        <section class="hv-card">
            <h3>Jurnal</h3>
            <div class="hv-scroll" style="max-height:20rem;overflow-y:auto"><table class="hv-table">
                <thead><tr><th>Data</th><th>Cine</th><th>Acțiune</th><th>Detalii</th></tr></thead>
                <tbody>
                @foreach ($audit as $entry)
                    <tr><td>{{ $entry->created_at->format('d.m.Y H:i') }}</td><td>{{ $entry->actor_name ?? 'sistem' }}</td><td>{{ $entry->action }}</td><td>{{ $entry->summary }}</td></tr>
                @endforeach
                </tbody>
            </table></div>
        </section>
        @endif
    </div>
</x-filament-panels::page>
