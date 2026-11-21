@extends('layouts.site')

@section('content')
@include('site._head', ['title' => $page->title])
<section style="padding-bottom: var(--section)">
    <div class="wrap">
        <div class="legal">
            @if (app()->getLocale() !== 'ro')<p class="notice">{{ __('Aceasta este o traducere informativă. Textul în limba română este cel care produce efecte juridice.') }}</p>@endif
            @if ($page->document_version)<p class="badge">{{ __('Versiune document: :version', ['version' => $page->document_version]) }}</p>@endif
            <div class="prose" style="max-width:78ch">{!! $page->body !!}</div>
        </div>
        <p class="no-print" style="margin-top:1.5rem"><button class="btn btn-ghost btn-sm" type="button" onclick="window.print()">{{ __('Tipărește documentul') }}</button></p>
    </div>
</section>
@endsection
