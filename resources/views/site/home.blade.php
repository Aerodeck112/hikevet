@extends('layouts.site')

@section('content')
<section class="hero">
    <div class="wrap hero-grid">
        <div>
            <p class="script">{{ $b['hero_script'] ?? '' }}</p>
            <h1 class="display h-xl">{{ $b['hero_title'] ?? $page->title }}</h1>
            <p class="lead">{{ $page->lead }}</p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="{{ $clinic['booking_url'] }}" data-track="booking_click">Programează o vizită</a>
                <a class="btn btn-ghost" href="{{ site_url('/abonamente/') }}">Vezi abonamentele</a>
            </div>
            <ul class="hero-facts">
                <li><span>Telefon</span><a href="tel:{{ preg_replace('/[^0-9+]/', '', $clinic['phone']) }}" data-track="phone_click">{{ $clinic['phone'] }}</a></li>
                <li><span>Adresă</span><a href="{{ site_url('/hikevet-contact/') }}">{{ $clinic['address'] }}, {{ $clinic['city'] }}</a></li>
                <li><span>Consult video</span><a href="{{ site_url('/video-vet/') }}">Video Vet</a></li>
            </ul>
        </div>
        <div class="capsules" aria-hidden="true">
            <div class="capsule capsule--a"><x-img src="media/2023/05/0h7a9513.webp" sizes="(max-width: 880px) 33vw, 16vw" eager /></div>
            <div class="capsule capsule--b"><x-img :src="$b['hero_image'] ?? ''" sizes="(max-width: 880px) 33vw, 16vw" eager style="object-position: 38% 50%" /></div>
            <div class="capsule capsule--c"><x-img src="media/2023/07/black-schnauzer-cowboy-dog-2022-08-11-19-54-34-utc-min.webp" sizes="(max-width: 880px) 33vw, 16vw" eager /></div>
        </div>
    </div>
</section>

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
            <article class="person">
                <div class="person-photo"><x-img :src="$b['staff_image'] ?? ''" alt="Membră a echipei HiKeVet îmbrățișând un câine în cabinet" sizes="(max-width: 880px) 100vw, 33vw" /></div>
                <div>
                    <h3>Alături de pacienți, în fiecare zi</h3>
                    <p>{{ $clinic['address'] }}, {{ $clinic['city'] }}. <a href="{{ site_url('/tur-3d/') }}">Vezi turul 3D al clinicii</a></p>
                </div>
            </article>
        </div>
    </div>
</section>

<section class="section section--green" id="abonamente">
    <div class="wrap calc" x-data="priceCalc(@js($tiers))">
        <div>
            <h2 class="display h-lg">Abonamente pentru animăluțe</h2>
            <p class="measure" style="margin-top:1.5rem">Un plan de servicii veterinare plătit lunar, pe 6 sau pe 12 luni: consultații, ecografii, radiografii, analize, vaccin și detartraj la preț de abonament. Alegi specia și greutatea și vezi prețul pe loc.</p>
            <ul class="ticks">
                <li><x-hv-icon name="check" /> Prețul depinde doar de specie și de greutate.</li>
                <li><x-hv-icon name="check" /> Reducere pentru al doilea, al treilea și al patrulea animal.</li>
                <li><x-hv-icon name="check" /> Plata se face la clinică; abonamentul nu este o asigurare.</li>
            </ul>
            <a class="btn btn-primary" href="{{ site_url('/abonamente/') }}#configurator" data-track="subscription_start">Configurează abonamentul</a>
        </div>
        <div class="calc-panel">
            <h3>Cât costă pentru animalul tău?</h3>
            <div class="choice" role="group" aria-label="Specie">
                <button type="button" :aria-pressed="(species === 'dog').toString()" @click="setSpecies('dog')">Câine</button>
                <button type="button" :aria-pressed="(species === 'cat').toString()" @click="setSpecies('cat')">Pisică</button>
            </div>
            <div class="calc-weight">
                <label for="calc-weight">Greutate</label>
                <output for="calc-weight"><span x-text="weightLabel"></span> kg</output>
            </div>
            <input class="range" id="calc-weight" type="range" :min="bounds.min" :max="bounds.max" step="0.25" x-model.number="weight">
            <dl class="calc-result" aria-live="polite">
                <div><dt>Lunar</dt><dd><span x-text="tier ? tier.monthly : '–'"></span> <small>lei</small></dd></div>
                <div><dt>6 luni</dt><dd><span x-text="tier ? tier.m6 : '–'"></span> <small>lei</small></dd></div>
                <div><dt>12 luni</dt><dd><span x-text="tier ? tier.m12 : '–'"></span> <small>lei</small></dd></div>
            </dl>
            <p class="calc-note">Categoria <strong x-text="tier ? tier.label : ''"></strong>. Preț standard pentru primul animal. Deparazitările se achită separat.</p>
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

@if (! empty($b['instagram']))
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
            @foreach ($b['instagram'] as $i => $image)
                <a href="{{ $b['instagram_url'] ?? '#' }}" rel="noopener" aria-label="Fotografie din cabinet {{ $i + 1 }}, pe Instagram"><x-img :src="$image" alt="" sizes="(max-width: 880px) 33vw, 16vw" /></a>
            @endforeach
        </div>
    </div>
</section>
@endif
@endsection
