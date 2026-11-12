@extends('layouts.site')

@section('content')
@include('site._head', ['title' => $page->title, 'lead' => $page->lead])
<section class="section--mint" style="padding: 1rem 0 var(--section)">
    <div class="wrap">
        @foreach ($categories as $category)
            @continue($category->services->isEmpty())
            <div class="svc-group">
                <h2 class="h-sm" style="position:sticky;top:6.5rem;align-self:start">{{ $category->name }}</h2>
                <ul class="svc-list">
                    @foreach ($category->services as $service)
                        <li>
                            <a class="svc-row" href="{{ site_url($service->path()) }}">
                                <x-hv-icon :name="$service->icon" />
                                <strong>{{ $service->name }}</strong>
                                <span>{{ $service->excerpt }}</span>
                                <i class="go"><x-hv-icon name="arrow" /></i>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</section>
@include('partials.subscription-band')
@endsection
