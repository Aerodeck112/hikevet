@extends('layouts.site')

@section('content')
@include('site._head', ['title' => 'Video Vet', 'lead' => $page->lead])
<section style="padding-bottom: var(--section)">
    <div class="wrap split">
        <div>
            <div class="prose">{!! $page->body !!}</div>
            <p style="margin-top:1.5rem"><a class="btn btn-primary" href="{{ \App\Support\Booking::isExternal() ? ($b['booking_url'] ?? $clinic['booking_url']) : $clinic['booking_url'] }}" data-track="booking_click">Programează un consult video</a></p>
        </div>
        <div class="split-media split-media--capsule"><x-img :src="$b['image'] ?? ''" alt="Câine lângă un laptop, în timpul unui consult video" eager /></div>
    </div>
</section>
<section class="section section--mint">
    <div class="wrap split" style="align-items:start">
        <div>
            <h2 class="display h-md">Avantajele Video Vet</h2>
            <ul class="ticks">
                @foreach ($b['advantages'] ?? [] as $advantage)
                    <li><x-hv-icon name="check" /> {{ $advantage }}</li>
                @endforeach
            </ul>
        </div>
        <div>
            <h2 class="display h-md">Cum funcționează?</h2>
            <div class="prose" style="margin-top:1.5rem">{!! $b['how'] ?? '' !!}</div>
        </div>
    </div>
</section>
@include('partials.subscription-band')
@endsection
