@extends('layouts.site')

@section('content')
@include('site._head', ['title' => $service->name])
<section style="padding-bottom: var(--section)">
    <div class="wrap with-aside">
        <article class="prose">
            {!! $service->body !!}
        </article>
        <aside class="aside-card">
            <h2 class="h-sm">Programează {{ mb_strtolower($service->name) }}</h2>
            <p style="margin:.75rem 0 1.25rem">Alege ziua și ora online sau sună-ne și stabilim împreună.</p>
            <p><a class="btn btn-primary" style="width:100%" href="{{ $service->booking_url && \App\Support\Booking::isExternal() ? $service->booking_url : $clinic['booking_url'] }}" data-track="booking_click">Programează</a></p>
            <p><a class="btn btn-ghost" style="width:100%" href="tel:{{ preg_replace('/[^0-9+]/', '', $clinic['phone']) }}" data-track="phone_click"><x-hv-icon name="phone" width="18" height="18" /> {{ $clinic['phone'] }}</a></p>
            @if ($related->isNotEmpty())
                <h3 class="label" style="margin:1.75rem 0 .6rem">Servicii apropiate</h3>
                <ul style="list-style:none;padding:0;margin:0;display:grid;gap:.4rem">
                    @foreach ($related as $item)
                        <li><a href="{{ site_url($item->path()) }}">{{ $item->name }}</a></li>
                    @endforeach
                </ul>
            @endif
        </aside>
    </div>
</section>
@include('partials.subscription-band')
@endsection
