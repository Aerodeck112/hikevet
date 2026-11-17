{{-- Antetul HiKeVet al documentelor campaniei (pdf_add_brand_header din plugin). --}}
<style>
    @page { margin: {{ $top ?? 74 }}pt {{ $side ?? 24 }}pt 34pt {{ $side ?? 24 }}pt; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 8.5pt; color: #0f172a; margin: 0; }
    .brand { position: fixed; top: -{{ $top ?? 74 }}pt; left: -{{ $side ?? 24 }}pt; right: -{{ $side ?? 24 }}pt; height: 58pt; background: #0f766e; color: #fff; padding: 10pt {{ $side ?? 24 }}pt 0; }
    .brand .k { font-size: 8pt; font-weight: bold; color: #d1fae5; }
    .brand .t { font-size: 15pt; font-weight: bold; margin-top: 4pt; }
    .brand .s { position: absolute; right: {{ $side ?? 24 }}pt; top: 12pt; font-size: 7pt; color: #ecfdf5; text-align: right; }
    .foot { position: fixed; bottom: -22pt; left: 0; right: 0; font-size: 6.5pt; color: #64748b; border-top: 1px solid #cbd5e1; padding-top: 3pt; }
    .foot .pg { position: absolute; right: 0; top: 3pt; }
    .foot .pg:after { content: "Pagina " counter(page) "/" counter(pages); }
</style>
<div class="brand"><div class="k">HIKEVET | CLINICA VETERINARA</div><div class="t">{{ $title }}</div>@if(!empty($subtitle))<div class="s">{{ $subtitle }}</div>@endif</div>
<div class="foot">{{ $footer }}@if($pages ?? true)<span class="pg"></span>@endif</div>
