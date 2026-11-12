@extends('layouts.site')

@section('content')
@include('site._head', ['title' => 'Echipa HiKeVet'])
<section style="padding-bottom: var(--section)">
    <div class="wrap split" style="align-items:start">
        <div class="prose">{!! $page->body !!}</div>
        <div class="split-media split-media--capsule"><x-img :src="$page->hero_image" alt="Medic veterinar HiKeVet lângă aparatura clinicii" eager /></div>
    </div>
</section>
@foreach ($team as $member)
<section class="section {{ $loop->odd ? 'section--mint' : '' }}" id="{{ $member->slug }}">
    <div class="wrap split">
        <div class="person-photo" style="max-width:440px;width:100%;justify-self:center;{{ $loop->even ? 'order:2' : '' }}"><x-img :src="$member->photo" :alt="$member->name" /></div>
        <div>
            <p class="script">{{ $member->role_title }}</p>
            <h2 class="display h-md" style="margin:.4rem 0 1.25rem">{{ $member->name }}</h2>
            @if ($member->specializations)<p><strong>{{ $member->specializations }}</strong></p>@endif
            <div class="prose">{!! $member->bio !!}</div>
        </div>
    </div>
</section>
@endforeach
@endsection
