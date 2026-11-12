@extends('layouts.site')

@section('content')
@include('site._head', ['title' => 'Programarea ta'])
<section style="padding-bottom: var(--section)">
    <div class="wrap" style="max-width:640px;margin-left:max(1.25rem, calc((100% - 1240px) / 2))">
        @if (session('status'))<p class="notice notice--ok" role="status">{{ session('status') }}</p>@endif
        <div class="card">
            <p><span class="badge {{ ['confirmed' => 'badge--ok', 'requested' => 'badge--warn'][$appointment->status->value] ?? 'badge--off' }}">{{ $appointment->status->label() }}</span></p>
            <h2 class="display h-md" style="margin:.5rem 0 1rem">{{ $appointment->starts_at->translatedFormat('l, j F Y') }}, ora {{ $appointment->starts_at->format('H:i') }}</h2>
            <p>Medic: <strong>{{ $appointment->doctor->name }}</strong>@if ($appointment->service)<br>Serviciu: <strong>{{ $appointment->service->name }}</strong>@endif @if ($appointment->pet_name)<br>Pacient: <strong>{{ $appointment->pet_name }}</strong>@endif<br>Cod: {{ $appointment->code }}</p>
            @if ($appointment->status->value === 'requested')<p class="notice">Cererea ta așteaptă confirmarea clinicii. Primești un email când este confirmată.</p>@endif
            @if (in_array($appointment->status->value, ['requested', 'confirmed'], true) && $appointment->starts_at->isFuture())
                <form method="post" action="{{ site_url('/programare/anulare/'.$token) }}" onsubmit="return confirm('Anulezi programarea?')">@csrf<button class="btn btn-ghost" type="submit">Anulează programarea</button></form>
            @endif
        </div>
    </div>
</section>
@endsection
