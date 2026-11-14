<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo->title }}</title>
    <meta name="description" content="{{ $seo->description }}">
    <link rel="canonical" href="{{ $seo->canonical }}">
    @if ($seo->noindex)<meta name="robots" content="noindex, nofollow">@endif
    <meta property="og:type" content="{{ $seo->type }}">
    <meta property="og:locale" content="ro_RO">
    <meta property="og:site_name" content="{{ $clinic['name'] }}">
    <meta property="og:title" content="{{ $seo->title }}">
    <meta property="og:description" content="{{ $seo->description }}">
    <meta property="og:url" content="{{ $seo->canonical }}">
    <meta property="og:image" content="{{ $seo->image ?? asset('media/2023/05/0h7a9586-scaled-e1689027689830.webp') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#21b073">
    @if ($clinic['verification'])<meta name="google-site-verification" content="{{ $clinic['verification'] }}">@endif
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @foreach ($seo->jsonLd as $schema)
    <script type="application/ld+json">{!! json_encode(array_filter($schema, fn ($v) => $v !== null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endforeach
    @vite(['resources/css/site.css', 'resources/js/site.js'])
    @include('partials.tracking-head')
</head>
<body>
@include('partials.tracking-body')
<a class="skip" href="#continut">Sari la conținut</a>

<header class="site-header">
    <div class="wrap">
        <nav class="nav" aria-label="Meniu principal" x-data="{ open: false }" @keydown.escape.window="open = false" :class="{ 'is-open': open }">
            <a class="nav-logo" href="{{ site_url('/') }}" aria-label="{{ $clinic['name'] }} – pagina principală">
                <img src="{{ asset('media/2023/05/negru-640.webp') }}" alt="Clinica HiKeVet" width="640" height="396">
            </a>
            <button class="nav-toggle" type="button" @click="open = !open" :aria-expanded="open.toString()" aria-controls="meniu">
                <span class="sr-only">Meniu</span>
                <x-hv-icon name="menu" width="22" height="22" x-show="!open" />
                <x-hv-icon name="close" width="22" height="22" x-show="open" x-cloak />
            </button>
            <ul class="nav-links" id="meniu">
                @foreach ([['Servicii', 'servicii'], ['Abonamente', 'abonamente'], ['Echipa', 'echipa'], ['Blog', 'blog'], ['Întrebări', 'intrebari-frecvente'], ['Contact', 'hikevet-contact']] as [$label, $slug])
                    <li><a href="{{ site_url('/'.$slug.'/') }}" @if (request()->is($slug)) aria-current="page" @endif>{{ $label }}</a></li>
                @endforeach
            </ul>
            <div class="nav-cta">
                <a class="btn btn-ghost btn-sm" href="{{ site_url(auth()->check() ? '/panou-cont/' : '/acces-cont/') }}">
                    <x-hv-icon name="user" width="18" height="18" /> {{ auth()->check() ? 'Contul meu' : 'Cont client' }}
                </a>
                <a class="btn btn-primary btn-sm" href="{{ $clinic['booking_url'] }}" @if (\App\Support\Booking::isExternal()) rel="noopener" @endif data-track="booking_click">Programează-te</a>
            </div>
        </nav>
    </div>
</header>

<main id="continut">
    @yield('content')
</main>

<footer class="site-footer">
    <div class="wrap">
        <div class="footer-top">
            <div>
                <h2>{{ $clinic['name'] }}</h2>
                <p class="measure">{{ $clinic['tagline'] }}</p>
                <p><a class="btn btn-primary" href="{{ $clinic['booking_url'] }}" data-track="booking_click">Programează-te</a></p>
            </div>
            <div>
                <h2>Locație</h2>
                <ul>
                    <li>{{ $clinic['address'] }}<br>{{ $clinic['city'] }}</li>
                    <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $clinic['phone']) }}" data-track="phone_click">{{ $clinic['phone'] }}</a></li>
                    <li><a href="mailto:{{ $clinic['email'] }}">{{ $clinic['email'] }}</a></li>
                </ul>
            </div>
            <div>
                <h2>Utile</h2>
                <ul>
                    <li><a href="{{ site_url('/abonamente/') }}">Abonamente</a></li>
                    <li><a href="{{ site_url('/servicii/') }}">Servicii</a></li>
                    <li><a href="{{ site_url('/video-vet/') }}">Video Vet</a></li>
                    <li><a href="{{ site_url('/tur-3d/') }}">Tur 3D</a></li>
                    <li><a href="{{ site_url('/acces-cont/') }}">Cont client</a></li>
                </ul>
            </div>
            <div>
                <h2>Documente</h2>
                <ul>
                    <li><a href="{{ site_url('/termeni-si-conditii-abonamente/') }}">Termeni și condiții abonamente</a></li>
                    <li><a href="{{ site_url('/contract-abonament/') }}">Contract abonament</a></li>
                    <li><a href="{{ site_url('/ghid-proprietar/') }}">Ghidul proprietarului</a></li>
                    @if ($clinic['instagram'])<li><a href="{{ $clinic['instagram'] }}" rel="noopener">Instagram</a></li>@endif
                    @if ($clinic['review_url'])<li><a href="{{ $clinic['review_url'] }}" rel="noopener">Lasă o recenzie pe Google</a></li>@endif
                </ul>
            </div>
        </div>
        <div class="footer-word" aria-hidden="true">HiKeVet</div>
        <div class="footer-bottom">
            <span>© {{ date('Y') }} {{ $clinic['name'] }}. Toate drepturile rezervate.</span>
            <div class="footer-badges">
                <a href="https://reclamatiisal.anpc.ro/" rel="noopener"><img src="{{ asset('media/2026/03/anpc-sal1-1-5.webp') }}" alt="ANPC – Soluționarea alternativă a litigiilor" width="500" height="124" loading="lazy"></a>
                <a href="https://consumer-redress.ec.europa.eu/site-relocation_en?event=main.home2.show&lng=RO" rel="noopener"><img src="{{ asset('media/2026/03/anpc-sol-5.webp') }}" alt="Soluționarea online a litigiilor" width="500" height="124" loading="lazy"></a>
            </div>
        </div>
    </div>
</footer>

<div class="mobile-bar no-print">
    <a class="btn btn-ghost" href="tel:{{ preg_replace('/[^0-9+]/', '', $clinic['phone']) }}" data-track="phone_click"><x-hv-icon name="phone" width="20" height="20" /> Sună</a>
    <a class="btn btn-primary" href="{{ $clinic['booking_url'] }}" data-track="booking_click">Programează-te</a>
</div>
</body>
</html>
