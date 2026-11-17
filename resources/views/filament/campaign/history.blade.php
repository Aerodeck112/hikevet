<section class="hv-card">
    <h3>Jurnalul modificărilor</h3>
    <p class="hv-cp-note">Fiecare salvare din platforma nouă, cu autorul și valorile dinainte și de după. Jurnalul nu se poate modifica.</p>
    <table class="hv-table" style="font-size:.85rem">
        <thead><tr><th>Data</th><th>Cine</th><th>Acțiune</th><th>Modificări</th></tr></thead>
        @forelse ($changes as $change)
            <tr>
                <td style="white-space:nowrap">{{ $change->created_at?->format('d.m.Y H:i') }}</td>
                <td>{{ $change->actor_name }}</td>
                <td>{{ $change->action }}{{ $change->section ? ' · '.$change->section : '' }}@if ($change->note)<div class="hv-muted">{{ $change->note }}</div>@endif</td>
                <td>
                    @foreach ((array) $change->changes as $field => [$before, $after])
                        <div><code>{{ $field }}</code>: <span class="hv-muted">{{ \Illuminate\Support\Str::limit($before, 60) ?: '—' }}</span> → {{ \Illuminate\Support\Str::limit($after, 60) ?: '—' }}</div>
                    @endforeach
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="hv-muted">Dosarul nu a fost modificat în platforma nouă.</td></tr>
        @endforelse
    </table>
</section>

<section class="hv-card">
    <h3>Programări</h3>
    <table class="hv-table" style="font-size:.85rem">
        @forelse ($appointments as $a)
            <tr><td>{{ $a->appointment_code }}</td><td>{{ $a->appointment_date?->format('d.m.Y') }} {{ $a->start_time }}–{{ $a->endTime() }}</td><td>{{ $a->appointment_type }}</td><td>{{ $a->status }}</td><td>{{ $a->source }}</td></tr>
        @empty
            <tr><td class="hv-muted">Nicio programare.</td></tr>
        @endforelse
    </table>
</section>

<section class="hv-card">
    <h3>Emailuri ({{ $emailsTotal }})</h3>
    @if ($emailsTotal > $emails->count())<p class="hv-cp-note">Se afișează ultimele {{ $emails->count() }}.</p>@endif
    <table class="hv-table" style="font-size:.85rem">
        @forelse ($emails as $mail)
            <tr><td style="white-space:nowrap">{{ ($mail->created_at ?? $mail->sent_at)?->format('d.m.Y H:i') }}</td><td>{{ $mail->email_type }}</td>
                <td><span class="hv-pill {{ $mail->status === 'sent' ? 'hv-pill--ok' : 'hv-pill--bad' }}">{{ $mail->status === 'sent' ? 'trimis' : 'eșuat' }}</span></td>
                <td>{{ $mail->subject }}@if ($mail->error_message)<div class="hv-muted">{{ $mail->error_message }}</div>@endif</td></tr>
        @empty
            <tr><td class="hv-muted">Niciun email.</td></tr>
        @endforelse
    </table>
    @if ($canEdit)
        <div class="hv-cp-actions" style="margin-top:.75rem">
            @if ($row['data_externare'])
                <form method="post" action="{{ route('admin.campaign.resend-final', $model->id) }}">@csrf<button class="hv-cp-btn" type="submit">{{ $row['final_summary_email_sent_at'] ? 'Retrimite rezumatul ACTUALIZAT' : 'Trimite rezumatul de externare' }}</button></form>
                <form method="post" action="{{ route('admin.campaign.followup', $model->id) }}">@csrf<button class="hv-cp-btn" type="submit">{{ $row['postop_email_sent_at'] ? 'Retrimite follow-up' : 'Trimite follow-up acum' }}</button></form>
            @endif
        </div>
    @endif
</section>

<section class="hv-card">
    <h3>Documente (inclusiv cele înlocuite)</h3>
    <table class="hv-table" style="font-size:.85rem">
        @forelse ($documents as $doc)
            <tr><td>{{ $doc->kind === 'fotografie_pacient' ? 'Fotografie' : 'Act identitate' }}</td><td>{{ $doc->created_at?->format('d.m.Y H:i') }} · {{ $doc->source ?: 'import' }}</td>
                <td>@if ($doc->superseded_at)<span class="hv-pill hv-pill--off">înlocuit {{ $doc->superseded_at->format('d.m.Y') }}</span>@else<span class="hv-pill hv-pill--ok">curent</span>@endif @if ($doc->is_missing)<span class="hv-pill hv-pill--bad">lipsă în sursă</span>@endif</td>
                <td>@unless ($doc->is_missing)<a href="{{ route('admin.campaign.document', $doc) }}" target="_blank">Deschide</a>@endunless</td></tr>
        @empty
            <tr><td class="hv-muted">Niciun document.</td></tr>
        @endforelse
    </table>
</section>

@if ($model->isImported())
    <section class="hv-card">
        <h3>Rândul original din WordPress</h3>
        <p class="hv-cp-note">Copia neatinsă a dosarului, așa cum a fost importată (amprentă SHA-256 {{ substr((string) $model->row_sha256, 0, 16) }}…). Nu se modifică niciodată.</p>
        <details><summary class="hv-cp-btn" style="display:inline-flex">Arată toate cele {{ count($model->source_row) }} de câmpuri</summary>
            <table class="hv-table" style="font-size:.8rem;margin-top:.5rem">@foreach ($model->source_row as $k => $v)<tr><th>{{ $k }}</th><td style="white-space:pre-wrap;word-break:break-word">{{ is_scalar($v) || $v === null ? $v : json_encode($v) }}</td></tr>@endforeach</table>
        </details>
    </section>
@endif
