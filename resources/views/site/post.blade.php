@extends('layouts.site')

@section('content')
@include('site._head', ['title' => $post->title])
<article style="padding-bottom: var(--section)">
    <div class="wrap">
        <p style="margin-top:-1.5rem"><time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('j F Y') }}</time></p>
        @if ($post->image)
            <div class="split-media" style="margin:1.5rem 0 2.5rem;max-height:520px"><x-img :src="$post->image" alt="" sizes="100vw" eager /></div>
        @endif
        <div class="prose">{!! $post->body !!}</div>
    </div>
</article>
@if ($more->isNotEmpty())
<section class="section section--mint">
    <div class="wrap">
        <div class="section-head"><h2 class="display h-md">Citește și</h2></div>
        <div class="posts">
            @foreach ($more as $item)
                @include('partials.post-card', ['post' => $item])
            @endforeach
        </div>
    </div>
</section>
@endif
@endsection
