{{-- Antet comun pentru paginile interioare --}}
<header class="page-head">
    <div class="wrap">
        @if (! empty($seo->breadcrumbs))
            <nav aria-label="Ești aici">
                <ol class="crumbs">
                    @foreach ($seo->breadcrumbs as $crumb)
                        <li>@if (! $loop->last)<a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>@else<span aria-current="page">{{ $crumb['name'] }}</span>@endif</li>
                    @endforeach
                </ol>
            </nav>
        @endif
        <h1 class="display h-lg">{{ $title }}</h1>
        @if (! empty($lead))<p class="lead" style="margin-top:1.25rem;max-width:52ch">{{ $lead }}</p>@endif
        @if (! empty($preview))<p class="notice notice--warn" style="margin-top:1.5rem">Previzualizare: pagina este în lucru și nu este vizibilă public.</p>@endif
    </div>
</header>
