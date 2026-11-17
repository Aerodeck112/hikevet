<section class="hv-card">
    <h3>Programarea {{ $a->appointment_code }} · {{ $a->label() }}</h3>
    <form method="post" action="{{ route('admin.campaign.appointments.update', $a) }}" class="hv-cp">
        @csrf
        <div class="hv-cp-grid">
            <div class="hv-cp-field"><label>Data *</label><input type="date" name="appointment[appointment_date]" value="{{ $a->appointment_date?->format('Y-m-d') }}" required @disabled(! $canEdit)></div>
            <div class="hv-cp-field"><label>Ora început *</label><input type="time" name="appointment[start_time]" value="{{ $a->start_time }}" required @disabled(! $canEdit)></div>
            <div class="hv-cp-field"><label>Ora sfârșit</label><input type="time" name="appointment[end_time]" value="{{ $a->endTime() }}" @disabled(! $canEdit)></div>
            <div class="hv-cp-field"><label>Tip programare *</label><select name="appointment[appointment_type]" @disabled(! $canEdit)>@foreach ($types as $t)<option @selected($a->appointment_type === $t)>{{ $t }}</option>@endforeach</select></div>
            <div class="hv-cp-field"><label>Status</label><select name="appointment[status]" @disabled(! $canEdit)>@foreach ($statuses as $s)<option @selected($a->status === $s)>{{ $s }}</option>@endforeach</select></div>
            <div class="hv-cp-field"><label>Proprietar *</label><input type="text" name="appointment[owner_name]" value="{{ $a->owner_name }}" required @disabled(! $canEdit)></div>
            <div class="hv-cp-field"><label>Telefon *</label><input type="tel" name="appointment[client_phone]" value="{{ $a->client_phone }}" required @disabled(! $canEdit)></div>
            <div class="hv-cp-field"><label>Email *</label><input type="email" name="appointment[client_email]" value="{{ $a->client_email }}" required @disabled(! $canEdit)></div>
            <div class="hv-cp-field"><label>Pacient *</label><input type="text" name="appointment[patient_name]" value="{{ $a->patient_name }}" required @disabled(! $canEdit)></div>
            <div class="hv-cp-field is-full"><label>Note</label><textarea name="appointment[notes]" @disabled(! $canEdit)>{{ $a->notes }}</textarea></div>
        </div>
        <p class="hv-cp-note">Reamintirea automată se trimite cu o zi înainte, cu intervalul de prezentare 08:30–12:00. {{ $a->reminder_sent_at ? 'Reamintirea a fost trimisă la '.$a->reminder_sent_at->format('d.m.Y H:i').'.' : '' }}</p>
        @if ($canEdit)<div class="hv-cp-actions"><button class="hv-cp-btn hv-cp-btn--primary" type="submit">Salvează programarea</button>
            @if ($a->sterilization_case_id)<a class="hv-cp-btn" href="{{ url('/admin/campanie/dosar') }}?case={{ $a->sterilization_case_id }}">Deschide dosarul</a>@endif
            <a class="hv-cp-btn" href="?week={{ $a->appointment_date?->format('Y-m-d') }}">Închide</a></div>@endif
    </form>
    @if ($canEdit)
        <div class="hv-cp-actions" style="margin-top:.75rem">
            @if ($a->sterilization_case_id && ! in_array($a->status, ['Anulat', 'Finalizat'], true))
                <form method="post" action="{{ route('admin.campaign.appointments.consultation', $a) }}">@csrf<button class="hv-cp-btn hv-cp-btn--primary" type="submit">Începe consultația</button></form>
            @endif
            <form method="post" action="{{ route('admin.campaign.appointments.email', $a) }}">@csrf<button class="hv-cp-btn" type="submit">Retrimite emailul de programare</button></form>
            @if ($a->status !== 'Anulat')<form method="post" action="{{ route('admin.campaign.appointments.cancel', $a) }}" onsubmit="return confirm('Anulezi programarea?')">@csrf<button class="hv-cp-btn hv-cp-btn--danger" type="submit">Anulează</button></form>@endif
            <form method="post" action="{{ route('admin.campaign.appointments.destroy', $a) }}" onsubmit="return confirm('Ștergi programarea? Dosarul legat rămâne.')">@csrf<button class="hv-cp-btn hv-cp-btn--danger" type="submit">Șterge</button></form>
        </div>
    @endif
    @if ($a->emailLogs->isNotEmpty())
        <h4 style="margin-top:1rem">Istoric email</h4>
        <table class="hv-table" style="font-size:.8rem">@foreach ($a->emailLogs->take(25) as $log)<tr><td>{{ ($log->created_at ?? $log->sent_at)?->format('d.m.Y H:i') }}</td><td>{{ $log->email_type }}</td><td>{{ $log->to_email }}</td><td><span class="hv-pill {{ $log->status === 'sent' ? 'hv-pill--ok' : 'hv-pill--bad' }}">{{ $log->status }}</span> {{ $log->error_message }}</td></tr>@endforeach</table>
    @endif
</section>
