@extends('layouts.site')

@section('content')
@php
    $status = $appointment->status->value;
    $flexible = $appointment->preferred_period && $status === 'requested';
    $period = \App\Services\Booking\BookingService::periodLabel($appointment->preferred_period);
    $fresh = (bool) session('status');
    $cat = $appointment->pet_species === 'Pisică';
    $lines = match (true) {
        $status === 'requested' && $cat => [($appointment->pet_name ? 'Miau! O aștept pe '.$appointment->pet_name.'.' : 'Miau, am primit cererea!'), 'Te sunăm noi pentru ora exactă.'],
        $status === 'requested' => [($appointment->pet_name ? 'Gata! Îl aștept pe '.$appointment->pet_name.'.' : 'Gata, am primit cererea!'), 'Te sunăm noi pentru ora exactă. Ham!'],
        $status === 'confirmed' => [$cat ? 'Ne vedem curând! Eu țin locul cald.' : 'Ne vedem curând! Eu aduc mingea.', 'Programarea e confirmată.'],
        default => [$cat ? 'Miau… poate data viitoare.' : 'Ham… poate data viitoare.', 'Ne poți scrie oricând.'],
    };
@endphp
@include('site._head', ['title' => $status === 'requested' ? 'Am primit cererea ta' : 'Programarea ta', 'buddy' => ['kind' => $cat ? 'cat' : 'dog', 'pose' => $cat ? 'sit' : 'peek', 'say' => $lines]])
<section class="bk-done" style="padding-bottom: var(--section)">
    @if ($fresh)<div class="paw-burst" aria-hidden="true">@for ($i = 0; $i < 14; $i++)<i style="--i: {{ $i }}"></i>@endfor</div>@endif
    <div class="wrap bk-done-grid">
        <div class="bk-step">
            @if (session('status'))<p class="notice notice--ok" role="status">{{ session('status') }}</p>@endif
            <p><span class="badge {{ ['confirmed' => 'badge--ok', 'requested' => 'badge--warn'][$status] ?? 'badge--off' }}">{{ $status === 'requested' ? 'Cerere în așteptare' : $appointment->status->label() }}</span></p>
            <h2 class="display h-md" style="margin:.75rem 0 1rem">
                {{ \Illuminate\Support\Str::ucfirst($appointment->starts_at->translatedFormat('l, j F Y')) }}<br>
                <span style="color:var(--turq-deep)">{{ $flexible ? $period : 'ora '.$appointment->starts_at->format('H:i') }}</span>
            </h2>
            <dl class="bk-facts">
                @if ($appointment->pet_name)<div><dt>Pacient</dt><dd>{{ $appointment->pet_name }}{{ $appointment->pet_species ? ', '.mb_strtolower($appointment->pet_species) : '' }}</dd></div>@endif
                <div><dt>Pentru</dt><dd>{{ $appointment->service?->name ?? 'Consultație' }}</dd></div>
                <div><dt>Medic</dt><dd>{{ $appointment->doctor?->name ?? 'Oricare medic disponibil' }}</dd></div>
                <div><dt>Cod</dt><dd>{{ $appointment->code }}</dd></div>
            </dl>
            @if (in_array($status, ['requested', 'confirmed'], true) && $appointment->ends_at->isFuture())
                <form method="post" action="{{ site_url('/programare/anulare/'.$token) }}" onsubmit="return confirm('Anulezi {{ $status === 'requested' ? 'cererea' : 'programarea' }}?')">@csrf<button class="btn btn-ghost btn-sm" type="submit">Anulează {{ $status === 'requested' ? 'cererea' : 'programarea' }}</button></form>
            @endif
        </div>

        @if ($status === 'requested')
            <div class="bk-next">
                <h2>Ce urmează</h2>
                <ol>
                    <li class="done"><strong>Am primit cererea.</strong> Ți-am trimis și un email cu detaliile{{ $appointment->client_email ? ', la '.$appointment->client_email : '' }}.</li>
                    <li><strong>Te contactăm</strong> în programul clinicii, la {{ $appointment->client_phone }}, ca să stabilim ora exactă.</li>
                    <li><strong>Primești confirmarea</strong> pe email. Abia atunci programarea este fixată.</li>
                </ol>
                <p class="bk-urgent">E o urgență? Nu aștepta: sună-ne la <a href="tel:{{ preg_replace('/[^0-9+]/', '', $clinic['phone']) }}">{{ $clinic['phone'] }}</a>.</p>
            </div>
        @endif
    </div>
</section>
@endsection
