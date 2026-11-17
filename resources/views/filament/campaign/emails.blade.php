<x-filament-panels::page>
<div class="hv-cp hv-client">
    @if (session('campaign_message'))<div class="hv-cp-flash hv-cp-flash--ok">{{ session('campaign_message') }}</div>@endif
    @if (session('campaign_warning'))<div class="hv-cp-flash hv-cp-flash--warn">{{ session('campaign_warning') }}</div>@endif
    <section class="hv-card">
        <p>Rezumatul de externare pleacă imediat la salvarea externării. Follow-up-ul („Cum decurge recuperarea?”) pleacă automat la 48 de ore după externare.
            Ultima verificare automată: <strong>{{ $lastRun ? \Illuminate\Support\Carbon::parse($lastRun)->format('d.m.Y H:i') : 'niciuna încă' }}</strong>.</p>
        <p class="hv-cp-note">Dacă trimiterea automată a follow-up-ului eșuează, se reîncearcă de cel mult {{ $maxAttempts }} ori; apoi rămâne pentru trimitere manuală (în sistemul vechi reîncercările nu se opreau niciodată).</p>
        <div class="hv-cp-actions"><a class="hv-cp-btn {{ $show === 'pending' ? 'hv-cp-btn--primary' : '' }}" href="?show=pending">Netrimise / de verificat</a><a class="hv-cp-btn {{ $show === 'all' ? 'hv-cp-btn--primary' : '' }}" href="?show=all">Toate externările ({{ $total }})</a></div>
    </section>
    <section class="hv-card" style="overflow-x:auto">
        <table class="hv-table" style="font-size:.82rem">
            <thead><tr><th>Dosar</th><th>Externare</th><th>Rezumat externare</th><th>Follow-up 48 h</th><th></th></tr></thead>
            @forelse ($rows as $r)
                <tr>
                    <td><a href="{{ url('/admin/campanie/dosar') }}?case={{ $r['id'] }}&tab=externare"><strong>{{ $r['code'] }}</strong></a><div>{{ $r['patient'] }} · {{ $r['owner'] }}</div><div class="hv-muted">{{ $r['email'] ?: 'fără email' }}</div></td>
                    <td>{{ $r['discharge'] }}</td>
                    <td>@if ($r['final_status'] === 'sent')<span class="hv-pill hv-pill--ok">trimis</span> <span class="hv-muted">{{ $r['final_at'] }}</span>@elseif ($r['final_status'] === 'failed')<span class="hv-pill hv-pill--bad">eșuat</span><div class="hv-muted">{{ $r['final_error'] }}</div>@else<span class="hv-pill hv-pill--off">netrimis</span>@endif</td>
                    <td>@if ($r['follow_at'])<span class="hv-pill hv-pill--ok">trimis</span> <span class="hv-muted">{{ $r['follow_at'] }}</span>
                        @else<span class="hv-pill {{ $r['follow_status'] === 'failed' ? 'hv-pill--bad' : 'hv-pill--warn' }}">{{ $r['follow_status'] === 'failed' ? 'eșuat' : 'programat' }}</span> <span class="hv-muted">scadent {{ $r['due'] ?: '-' }}</span>
                            @if ($failedFollowups[$r['id']] ?? 0)<div class="hv-muted">{{ $failedFollowups[$r['id']] }} încercări automate eșuate · {{ $r['follow_error'] }}</div>@endif
                        @endif</td>
                    <td class="hv-cp-actions" style="justify-content:flex-end">
                        <form method="post" action="{{ route('admin.campaign.resend-final', $r['id']) }}">@csrf<button class="hv-cp-btn" type="submit">{{ $r['final_status'] === 'sent' ? 'Retrimite datele actualizate' : 'Trimite rezumatul' }}</button></form>
                        <form method="post" action="{{ route('admin.campaign.followup', $r['id']) }}">@csrf<button class="hv-cp-btn" type="submit">{{ $r['follow_at'] ? 'Retrimite follow-up' : 'Trimite follow-up acum' }}</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="hv-muted">Nimic de afișat.</td></tr>
            @endforelse
        </table>
    </section>
</div>
</x-filament-panels::page>
