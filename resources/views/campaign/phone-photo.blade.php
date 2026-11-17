<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Încărcare poză – HiKeVet</title>
<style>
body{margin:0;background:#f1f5f9;color:#0f172a;font-family:system-ui,Arial,sans-serif}.p{max-width:620px;margin:0 auto;padding:22px}
.c{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:24px;box-shadow:0 18px 46px rgba(15,23,42,.08)}h1{margin:0 0 8px;font-size:26px}
.s{color:#475569;margin:0 0 18px}.f{border:2px dashed #0b5c3b;border-radius:14px;padding:18px;background:#f0fdf4}.g{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.b{display:block;text-align:center;padding:14px 10px;border-radius:10px;color:#fff;font-weight:800;cursor:pointer}.b input{display:none}
.v{display:none;width:100%;max-height:340px;object-fit:contain;border-radius:12px;margin-top:14px;border:1px solid #cbd5e1}
.go{width:100%;margin-top:18px;border:0;border-radius:12px;background:#21b073;color:#fff;padding:15px;font-weight:800;font-size:17px}
.ok{background:#ecfdf5;border-color:#bbf7d0;color:#166534}.err{background:#fef2f2;border-color:#fecaca;color:#991b1b}
</style>
</head>
<body><main class="p">
@if (! $case)
    <div class="c err"><h1>Link indisponibil</h1><p>Linkul pentru încărcare foto nu este valid sau a fost regenerat de clinică.</p></div>
@elseif ($uploaded)
    <div class="c ok"><h1>Poza a fost încărcată</h1><p>Fotografia pentru <strong>{{ $case->nume_pacient }}</strong> a ajuns în dosarul clinicii. Poți închide această pagină.</p></div>
@else
    <div class="c">
        <h1>Încărcare poză pacient</h1>
        <p class="s">Dosar <strong>{{ $case->cod_caz }}</strong> · Pacient: <strong>{{ $case->nume_pacient ?: '-' }}</strong></p>
        @if ($errors->any())<p class="c err" style="padding:12px">{{ $errors->first() }}</p>@endif
        <form method="post" action="{{ route('campaign.phone.upload', ['token' => $token]) }}" enctype="multipart/form-data">
            @csrf
            <div class="f">
                <div class="g">
                    <label class="b" style="background:#0b5c3b">Deschide camera<input class="src" type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment"></label>
                    <label class="b" style="background:#334155">Alege din galerie<input class="src" type="file" accept="image/jpeg,image/png,image/webp"></label>
                </div>
                <p style="margin:12px 0 0;color:#475569;font-size:13px">Alege una dintre opțiuni: camera foto sau biblioteca de imagini.</p>
                <img id="v" class="v" alt="Previzualizare poză">
            </div>
            <button class="go" type="submit">Trimite poza în dosar</button>
        </form>
    </div>
    <script>
    (function(){var inputs=document.querySelectorAll('.src'),v=document.getElementById('v');inputs.forEach(function(i){i.addEventListener('change',function(){var f=i.files&&i.files[0];if(!f)return;inputs.forEach(function(o){if(o!==i){o.value='';o.removeAttribute('name');}});i.setAttribute('name','photo');v.src=URL.createObjectURL(f);v.style.display='block';});});})();
    </script>
@endif
</main></body></html>
