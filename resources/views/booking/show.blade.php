@extends('layouts.site')

@section('content')
@php
    $status = $appointment->status->value;
    $flexible = $appointment->preferred_period && $status === 'requested';
    $period = \App\Services\Booking\BookingService::periodLabel($appointment->preferred_period);
    $fresh = (bool) session('status');
    $cat = $appointment->pet_species === 'Pisică';
    $lines = match (true) {
        $status === 'requested' && $cat => [($appointment->pet_name ? __('Miau! O aștept pe :name.', ['name' => $appointment->pet_name]) : __('Miau, am primit cererea!')), __('Te sunăm noi pentru ora exactă.')],
        $status === 'requested' => [($appointment->pet_name ? __('Gata! Îl aștept pe :name.', ['name' => $appointment->pet_name]) : __('Gata, am primit cererea!')), __('Te sunăm noi pentru ora exactă. Ham!')],
        $status === 'confirmed' => [$cat ? __('Ne vedem curând! Eu țin locul cald.') : __('Ne vedem curând! Eu aduc mingea.'), __('Programarea e confirmată.')],
        default => [$cat ? __('Miau… poate data viitoare.') : __('Ham… poate data viitoare.'), __('Ne poți scrie oricând.')],
    };
@endphp
@include('site._head', ['title' => $status === 'requested' ? __('Am primit cererea ta') : __('Programarea ta'), 'buddy' => ['kind' => $cat ? 'cat' : 'dog', 'pose' => $cat ? 'sit' : 'peek', 'say' => $lines]])
<section class="bk-done" style="padding-bottom: var(--section)">
    @if ($fresh)<div class="paw-burst" aria-hidden="true">@for ($i = 0; $i < 14; $i++)<i style="--i: {{ $i }}"></i>@endfor</div>@endif
    <div class="wrap bk-done-grid">
        <div class="bk-step">
            @if (session('status'))<p class="notice notice--ok" role="status">{{ session('status') }}</p>@endif
            <p><span class="badge {{ ['confirmed' => 'badge--ok', 'requested' => 'badge--warn'][$status] ?? 'badge--off' }}">{{ $status === 'requested' ? __('Cerere în așteptare') : __($appointment->status->label()) }}</span></p>
            <h2 class="display h-md" style="margin:.75rem 0 1rem">
                {{ \App\Support\Locales::date($appointment->starts_at) }}<br>
                <span style="color:var(--turq-deep)">{{ $flexible ? $period : __('ora :time', ['time' => $appointment->starts_at->format('H:i')]) }}</span>
            </h2>
            <dl class="bk-facts">
                @if ($appointment->pet_name)<div><dt>{{ __('Pacient') }}</dt><dd>{{ $appointment->pet_name }}{{ $appointment->pet_species ? ', '.mb_strtolower(__($appointment->pet_species)) : '' }}</dd></div>@endif
                <div><dt>{{ __('Pentru') }}</dt><dd>{{ $appointment->service?->name ?? __('Consultație') }}</dd></div>
                <div><dt>{{ __('Medic') }}</dt><dd>{{ $appointment->doctor?->name ?? __('Oricare medic disponibil') }}</dd></div>
                <div><dt>{{ __('Cod') }}</dt><dd>{{ $appointment->code }}</dd></div>
            </dl>
            @if (in_array($status, ['requested', 'confirmed'], true) && $appointment->ends_at->isFuture())
                <form method="post" action="{{ site_url('/programare/anulare/'.$token) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from($status === 'requested' ? __('Anulezi cererea?') : __('Anulezi programarea?')) }})">@csrf<button class="btn btn-ghost btn-sm" type="submit">{{ $status === 'requested' ? __('Anulează cererea') : __('Anulează programarea') }}</button></form>
            @endif
        </div>

        @if ($status === 'requested')
            <div class="bk-next">
                <h2>{{ __('Ce urmează') }}</h2>
                <ol>
                    <li class="done"><strong>{{ __('Am primit cererea.') }}</strong> {{ $appointment->client_email ? __('Ți-am trimis și un email cu detaliile, la :email.', ['email' => $appointment->client_email]) : __('Ți-am trimis și un email cu detaliile.') }}</li>
                    <li>{!! __('<strong>Te contactăm</strong> în programul clinicii, la :phone, ca să stabilim ora exactă.', ['phone' => e($appointment->client_phone)]) !!}</li>
                    <li>{!! __('<strong>Primești confirmarea</strong> pe email. Abia atunci programarea este fixată.') !!}</li>
                </ol>
                <p class="bk-urgent">{!! __('E o urgență? Nu aștepta: sună-ne la :phone.', ['phone' => '<a href="tel:'.e(preg_replace('/[^0-9+]/', '', $clinic['phone'])).'">'.e($clinic['phone']).'</a>']) !!}</p>
            </div>
        @endif
    </div>
</section>
@endsection
