@extends('layouts.site')

@section('content')
@include('site._head', ['title' => $page->title])
<section style="padding-bottom: var(--section)">
    <div class="wrap with-aside">
        <div>
            <div class="prose" style="margin-bottom:2rem">{!! $page->body !!}</div>
            <div class="faq">
                @foreach ($faqs as $faq)
                    <details>
                        <summary>{{ $faq->question }}</summary>
                        <div class="answer prose">{!! $faq->answer !!}</div>
                    </details>
                @endforeach
            </div>
        </div>
        <aside class="aside-card">
            <h2 class="h-sm">Nu ai găsit răspunsul?</h2>
            <p style="margin:.75rem 0 1.25rem">Scrie-ne sau sună-ne. Pentru urgențe, sună direct.</p>
            <p><a class="btn btn-dark" style="width:100%" href="{{ site_url('/hikevet-contact/') }}">Scrie-ne</a></p>
            <p><a class="btn btn-ghost" style="width:100%" href="tel:{{ preg_replace('/[^0-9+]/', '', $clinic['phone']) }}" data-track="phone_click">{{ $clinic['phone'] }}</a></p>
        </aside>
    </div>
</section>
@endsection
