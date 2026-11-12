@extends('layouts.site')

@section('content')
@include('site._head', ['title' => 'Tur 3D al clinicii'])
<section style="padding-bottom: var(--section)">
    <div class="wrap">
        <div class="prose" style="margin-bottom:2rem">{!! $page->body !!}</div>
        @if (! empty($b['embed_url']))
            <div class="split-media" style="aspect-ratio:16/9"><iframe src="{{ $b['embed_url'] }}" title="Tur virtual 3D al clinicii HiKeVet" loading="lazy" allowfullscreen style="width:100%;height:100%;border:0"></iframe></div>
        @else
            <p class="notice notice--warn">Turul 3D va fi afișat aici după ce adresa de inserare este adăugată din administrare.</p>
        @endif
    </div>
</section>
@endsection
