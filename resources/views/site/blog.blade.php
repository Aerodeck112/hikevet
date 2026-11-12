@extends('layouts.site')

@section('content')
@include('site._head', ['title' => 'Blog', 'lead' => 'Sfaturi de la medicii HiKeVet pentru un animal sănătos.'])
<section style="padding-bottom: var(--section)">
    <div class="wrap">
        @if ($posts->isEmpty())
            <p class="notice">Încă nu am publicat articole.</p>
        @else
            <div class="posts">
                @foreach ($posts as $post)
                    @include('partials.post-card', ['post' => $post])
                @endforeach
            </div>
            <div style="margin-top:2rem">{{ $posts->links('partials.pagination') }}</div>
        @endif
    </div>
</section>
@endsection
