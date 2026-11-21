{{-- Pagina comuna pentru erori, cu unul dintre personaje. Variabile: $code, $title, $text, $kind, $pose, $say. --}}
@php
    $title = __($title);
    $text = __($text);
    $seo = (new \App\Services\Seo\Seo)->page($title);
    $seo->noindex = true;
    $digits = str_split((string) $code);
@endphp
@extends('layouts.site')

@section('content')
<section class="oops">
    <div class="wrap">
        <div>
            <p class="oops-code" aria-hidden="true">{{ $digits[0] }}<span>{{ $digits[1] ?? '' }}</span>{{ implode('', array_slice($digits, 2)) }}</p>
            <h1 class="display h-lg">{{ $title }}</h1>
            <p class="lead" style="margin-top:1rem;max-width:44ch">{{ $text }}</p>
            <div class="oops-actions">
                <a class="btn btn-primary" href="{{ site_url('/') }}">{{ __('Mergi la prima pagină') }}</a>
                @if (! in_array((int) $code, [503], true))
                    <a class="btn btn-ghost" href="{{ site_url('/hikevet-contact/') }}">{{ __('Contact') }}</a>
                @endif
                @if (! empty($clinic['phone']))
                    <a class="btn btn-ghost" href="tel:{{ preg_replace('/[^0-9+]/', '', $clinic['phone']) }}">{{ __('Sună: :phone', ['phone' => $clinic['phone']]) }}</a>
                @endif
            </div>
        </div>
        <x-buddy :kind="$kind" :pose="$pose" :say="$say" />
    </div>
</section>
@endsection
