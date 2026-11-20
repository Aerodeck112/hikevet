@extends('layouts.site')

@section('content')
<section class="wall" data-wall>
    <div class="wrap wall-grid">
        <div class="wall-stage">
            <span class="paint dots deco-dots" aria-hidden="true"></span>
            <div class="perch-cat">@include('partials.mascot-cat')</div>
            <div class="bubble">
                @include('partials.bubble-shape')
                <h1 class="display"><span>Clinica</span> <span>HiKeVet</span></h1>
                <p>{{ $b['wall_lead'] ?? 'Clinică veterinară în Târgu Mureș: consultații, ecografie, chirurgie și spitalizare separată pentru câini și pisici.' }}</p>
                <div class="bubble-actions">
                    <a class="btn btn-primary" href="{{ $clinic['booking_url'] }}" data-track="booking_click">Programează o vizită</a>
                    <a class="btn btn-ghost" href="{{ site_url('/abonamente/') }}">Vezi abonamentele</a>
                </div>
                <button class="peek-dog" type="button" aria-label="Câinele HiKeVet: apasă și latră">
                    @include('partials.mascot-dog')
                    <span class="woof" aria-live="polite"></span>
                </button>
            </div>
        </div>
        <div class="posters">
            <span class="paint paint--tri deco-tri-m" aria-hidden="true"></span>
            <span class="paint deco-arrow" aria-hidden="true"></span>
            <figure class="poster poster--main"><x-img src="media/2026/clinica/fatada-ciobanesc.webp" alt="Intrarea în clinica HiKeVet, cu un ciobănesc german pictat pe perete" sizes="(max-width: 920px) 90vw, 44vw" eager /><figcaption>Intrarea în clinică</figcaption></figure>
            <figure class="poster poster--side"><x-img src="media/2026/clinica/fatada-bula.webp" alt="Bula albastră cu numele clinicii, pictată pe fațadă" sizes="(max-width: 920px) 45vw, 20vw" eager style="object-position: 30% 50%" /><figcaption>Ne găsești după bulă</figcaption></figure>
            <span class="sticker-sun" aria-hidden="true">Clinică nouă!</span>
            <span class="paint paint--tri deco-tri-o" aria-hidden="true"></span>
        </div>
    </div>
    <div class="wrap">
        <ul class="wall-facts">
            <li><span>Telefon</span><a href="tel:{{ preg_replace('/[^0-9+]/', '', $clinic['phone']) }}" data-track="phone_click">{{ $clinic['phone'] }}</a></li>
            <li><span>Adresă</span><a href="{{ site_url('/hikevet-contact/') }}">{{ $clinic['address'] }}, {{ $clinic['city'] }}</a></li>
            <li><span>Consult de acasă</span><a href="{{ site_url('/video-vet/') }}">Video Vet</a></li>
        </ul>
    </div>
</section>
<div class="stripe" aria-hidden="true"></div>

<section class="section section--mint" id="servicii">
    <div class="wrap">
        <div class="section-head">
            <div>
                <h2 class="display h-lg">Servicii</h2>
                <p>{{ $b['staff_line'] ?? '' }}</p>
            </div>
            <a class="btn btn-dark" href="{{ site_url('/servicii/') }}">Toate serviciile</a>
        </div>
        <div class="svc-compact">
            @foreach ($categories as $category)
                @continue($category->services->isEmpty())
                <div class="svc-compact-row">
                    <h3>{{ $category->name }}</h3>
                    <ul>
                        @foreach ($category->services as $service)
                            <li><a href="{{ site_url($service->path()) }}"><x-hv-icon :name="$service->icon" /> {{ $service->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="tour" data-tour aria-labelledby="tur-titlu">
    <div class="tour-pin">
        <div class="wrap tour-head">
            <div>
                <h2 class="display h-lg" id="tur-titlu">Intră cu noi</h2>
                <p>Clinica nouă, cameră cu cameră. Derulează și te plimbi prin ea ca și cum ai fi acolo.</p>
            </div>
            <span class="tour-count" aria-hidden="true">01 / 08</span>
        </div>
        <div class="tour-viewport">
            <div class="tour-track">
                <figure class="room"><div class="room-frame"><x-img src="media/2026/clinica/sala-asteptare.webp" alt="Sala de așteptare, clinica HiKeVet" sizes="(max-width: 900px) 82vw, 42vw" /></div><figcaption><strong>Sala de așteptare</strong><span>Pisicile de pe pereți îți țin companie până intri.</span></figcaption></figure>
                <figure class="room"><div class="room-frame"><x-img src="media/2026/clinica/cabinet-albastru.webp" alt="Cabinetul albastru, clinica HiKeVet" sizes="(max-width: 900px) 82vw, 42vw" /></div><figcaption><strong>Cabinetul albastru</strong><span>Consultații, vaccinări și controale.</span></figcaption></figure>
                <figure class="room"><div class="room-frame"><x-img src="media/2026/clinica/imagistica.webp" alt="Cabinetul pentru pisici, clinica HiKeVet" sizes="(max-width: 900px) 82vw, 42vw" /></div><figcaption><strong>Cabinetul pentru pisici</strong><span>Cu rafturi de cățărat pe perete, ca pisica să se simtă în largul ei.</span></figcaption></figure>
                <figure class="room"><div class="room-frame"><x-img src="media/2026/clinica/cabinet-verde.webp" alt="Cabinetul verde, clinica HiKeVet" sizes="(max-width: 900px) 82vw, 42vw" /></div><figcaption><strong>Cabinetul verde</strong><span>Încă un cabinet de consultații, ca să nu aștepți mult.</span></figcaption></figure>
                <figure class="room"><div class="room-frame"><x-img src="media/2026/clinica/sala-operatii.webp" alt="Sala de operații, clinica HiKeVet" sizes="(max-width: 900px) 82vw, 42vw" /></div><figcaption><strong>Sala de operații</strong><span>Anestezie inhalatorie și monitorizare pe toată durata intervenției.</span></figcaption></figure>
                <figure class="room"><div class="room-frame"><x-img src="media/2026/clinica/spitalizare.webp" alt="Spitalizare, clinica HiKeVet" sizes="(max-width: 900px) 82vw, 42vw" /></div><figcaption><strong>Spitalizare</strong><span>Pacienții internați stau aproape de echipă, sub supraveghere.</span></figcaption></figure>
                <figure class="room"><div class="room-frame"><x-img src="media/2026/clinica/spitalizare-pisici.webp" alt="Spitalizare pentru pisici, clinica HiKeVet" sizes="(max-width: 900px) 82vw, 42vw" /></div><figcaption><strong>Spitalizare pentru pisici</strong><span>Separată de câini, ca să fie liniște.</span></figcaption></figure>
                <figure class="room"><div class="room-frame"><x-img src="media/2026/clinica/camera-caini.webp" alt="Camera câinilor, clinica HiKeVet" sizes="(max-width: 900px) 82vw, 42vw" /></div><figcaption><strong>Camera câinilor</strong><span>Pereți pictați cu câini care se joacă.</span></figcaption></figure>
            </div>
        </div>
    </div>
</section>

<section class="section" id="echipa">
    <div class="wrap">
        <div class="section-head">
            <div>
                <h2 class="display h-lg">Personalul HiKeVet</h2>
            </div>
            <a class="btn btn-ghost" href="{{ site_url('/echipa/') }}">Cunoaște echipa</a>
        </div>
        <div class="team">
            @foreach ($team as $member)
                <article class="person">
                    <div class="person-photo"><x-img :src="$member->photo" :alt="$member->name" sizes="(max-width: 880px) 100vw, 33vw" /></div>
                    <div>
                        <h3>{{ $member->name }}</h3>
                        <p>{{ $member->role_title }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="section section--green" id="abonamente">
    <div class="wrap calc">
        <div>
            <h2 class="display h-lg">Abonamente pentru animăluțe</h2>
            <p class="measure" style="margin-top:1.5rem">Un plan de servicii veterinare plătit lunar, pe 6 sau pe 12 luni: consultații, ecografii, radiografii, analize, vaccin și detartraj la preț de abonament. Alegi specia și greutatea și vezi prețul pe loc.</p>
            <ul class="ticks">
                <li><x-hv-icon name="check" /> Prețul depinde doar de specie și de greutate.</li>
                <li><x-hv-icon name="check" /> Reducere pentru al doilea, al treilea și al patrulea animal.</li>
                <li><x-hv-icon name="check" /> {{ \App\Services\Payments\StripeCheckout::enabled() ? 'Plătești la clinică sau online, cu cardul.' : 'Plata se face la clinică.' }} Abonamentul nu este o asigurare.</li>
            </ul>
            <a class="btn btn-primary" href="{{ site_url('/abonamente/') }}#configurator" data-track="subscription_start">Configurează abonamentul</a>
        </div>
        @include('partials.pet-calculator', ['id' => 'calc-weight-home'])
    </div>
</section>

@if ($episodes)
<section class="section" aria-labelledby="episoade-titlu">
    <div class="wrap">
        <div class="section-head">
            <div>
                <h2 class="display h-lg" id="episoade-titlu">Povești din cabinet</h2>
                <p>Pacienții noștri, de la primul vaccin la controlul de după operație. Fiecare episod e filmat la noi în clinică.</p>
            </div>
            @if ($clinic['instagram'])<a class="btn btn-ghost" href="{{ $clinic['instagram'] }}" rel="noopener">Mai multe pe Instagram</a>@endif
        </div>
        <div class="episodes" data-episodes>
            @foreach ($episodes as $i => $ep)
                <article class="episode">
                    <div class="episode-frame">
                        @if ($ep['poster'])<img src="{{ $ep['poster'] }}" alt="" loading="lazy" width="540" height="960">@endif
                        <span class="episode-no">Ep. {{ $i + 1 }}</span>
                        <span class="episode-len">{{ $ep['length'] }}</span>
                        <button class="episode-play" type="button" data-src="{{ $ep['video'] }}" data-poster="{{ $ep['poster'] }}" aria-label="Pornește episodul: {{ $ep['title'] }}">
                            <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4l14 8-14 8z" fill="currentColor"/></svg></span>
                        </button>
                    </div>
                    <h3>{{ $ep['title'] }}</h3>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="section section--sun" aria-labelledby="inaugurare-titlu">
    <div class="wrap">
        <div class="section-head">
            <div>
                <h2 class="display h-lg" id="inaugurare-titlu">Ziua în care am deschis ușa</h2>
                <p>La inaugurare au venit vecini, prieteni și o mulțime de pacienți cu blană. Mulțumim că ați fost alături de noi!</p>
            </div>
        </div>
        <div class="opening">
            <figure class="o1"><x-img src="media/2026/clinica/fatada-bula.webp" alt="Fațada clinicii, cu bula albastră și pisica cu ochelari" sizes="(max-width: 720px) 100vw, 42vw" /><figcaption>Fațada, proaspăt pictată</figcaption></figure>
            <figure class="o2"><x-img src="media/2026/clinica/inaugurare-samoyezi.webp" alt="Doi samoyezi albi la inaugurarea clinicii" sizes="(max-width: 720px) 50vw, 33vw" /></figure>
            <figure class="o3"><x-img src="media/2026/clinica/inaugurare-teckel.webp" alt="Un pui de teckel în brațe, la inaugurare" sizes="(max-width: 720px) 50vw, 25vw" /></figure>
            <figure class="o4"><x-img src="media/2026/clinica/inaugurare-bernez.webp" alt="Un câine negru cu pieptul alb, printre invitați" sizes="(max-width: 720px) 50vw, 33vw" /></figure>
            <figure class="o5"><x-img src="media/2026/clinica/fatada-pisica.webp" alt="Pisica mare pictată pe peretele din curte" sizes="(max-width: 720px) 50vw, 25vw" /></figure>
            <figure class="o6"><x-img src="media/2026/clinica/inaugurare-oaspeti.webp" alt="Invitați în sala de așteptare pictată cu pisici" sizes="(max-width: 720px) 50vw, 33vw" /></figure>
            <figure class="o7"><x-img src="media/2026/clinica/inaugurare-samoyed-mural.webp" alt="Un samoyed și stăpânul lui în fața muralului" sizes="(max-width: 720px) 100vw, 42vw" /><figcaption>Prieteni vechi, clinică nouă</figcaption></figure>
            <figure class="o8"><x-img src="media/2026/clinica/fatada-panorama.webp" alt="Fațada clinicii HiKeVet, pictată în culori vii" sizes="(max-width: 720px) 50vw, 58vw" /></figure>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap split">
        <div class="split-media split-media--capsule"><x-img src="media/2023/07/photo-of-dog-and-computer-on-a-fitness-mat-2022-11-11-10-52-27-utc.webp" alt="Câine lângă un laptop, în timpul unui consult video" /></div>
        <div>
            <p class="script">Cel mai nou serviciu</p>
            <h2 class="display h-md" style="margin:.4rem 0 1.25rem">Video Vet, asistentul tău virtual</h2>
            <div class="prose">{!! $b['video_vet_intro'] ?? '' !!}</div>
            <ul class="ticks">
                @foreach ($b['video_vet_points'] ?? [] as $point)
                    <li><x-hv-icon name="check" /> {{ $point }}</li>
                @endforeach
            </ul>
            <a class="btn btn-dark" href="{{ site_url('/video-vet/') }}">Cum funcționează Video Vet</a>
        </div>
    </div>
</section>

@if (! empty($b['counters']))
<section class="section section--mint section--tight">
    <div class="wrap">
        <dl class="figures">
            @foreach ($b['counters'] as $counter)
                <div class="figure"><dd>{{ $counter['value'] }}{{ $counter['suffix'] }}</dd><dt>{{ $counter['label'] === 'Experienta' ? 'Ani de experiență' : 'Clienți mulțumiți' }}</dt></div>
            @endforeach
        </dl>
    </div>
</section>
@endif

@if ($posts->isNotEmpty())
<section class="section">
    <div class="wrap">
        <div class="section-head">
            <h2 class="display h-lg">Din blog</h2>
            <a class="btn btn-ghost" href="{{ site_url('/blog/') }}">Toate articolele</a>
        </div>
        <div class="posts">
            @foreach ($posts as $post)
                @include('partials.post-card', ['post' => $post])
            @endforeach
        </div>
    </div>
</section>
@endif

@if (! empty($b['partners']))
<section class="section section--mint">
    <div class="wrap">
        <div class="section-head">
            <div>
                <h2 class="display h-md">Parteneri</h2>
                <div class="measure" style="margin-top:1rem">{!! $b['partners_text'] ?? '' !!}</div>
            </div>
        </div>
        <ul class="logos">
            @foreach ($b['partners'] as $partner)
                <li><img src="{{ asset($partner['image']) }}" alt="{{ $partner['alt'] ?: 'Partener HiKeVet' }}" loading="lazy" width="160" height="80"></li>
            @endforeach
        </ul>
    </div>
</section>
@endif

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <div>
                <h2 class="display h-md">Din cabinet, pe Instagram</h2>
                <p>Postăm zilnic poze și videoclipuri din cabinetul nostru.</p>
            </div>
            <a class="btn btn-dark" href="{{ $b['instagram_url'] ?? '#' }}" rel="noopener">Urmărește-ne</a>
        </div>
        <div class="insta">
            @foreach (['consult-caine' => 'Consult pe masa din cabinetul albastru', 'pisica-alba-raft' => 'Pisică albă pe un raft cu lăbuțe pictate', 'medic-pisica-raft' => 'Medic și o pisică curioasă pe raft', 'vaccin-pui' => 'Pui de pisică la vaccin', 'microcip' => 'Citirea microcipului', 'medic-pisica-alba' => 'Medic ținând în brațe o pisică albă'] as $photo => $alt)
                <a href="{{ $b['instagram_url'] ?? $clinic['instagram'] ?: '#' }}" rel="noopener"><x-img :src="'media/2026/clinica/'.$photo.'.webp'" :alt="$alt" sizes="(max-width: 880px) 33vw, 16vw" /></a>
            @endforeach
        </div>
    </div>
</section>
@endsection
