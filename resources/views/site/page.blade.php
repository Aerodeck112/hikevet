@extends('layouts.site')

@section('content')
@include('site._head', ['title' => $page->title, 'lead' => $page->lead])
<section style="padding-bottom: var(--section)">
    <div class="wrap">
        @if ($page->hero_image)<div class="split-media" style="margin-bottom:2.5rem;max-height:520px"><x-img :src="$page->hero_image" alt="" sizes="100vw" eager /></div>@endif
        <div class="prose">{!! $page->body !!}</div>
    </div>
</section>
@endsection
