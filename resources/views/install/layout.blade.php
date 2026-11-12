<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>Instalare HiKeVet – @yield('title')</title>
<style>
:root{--green:#21b073;--deep:#0b5c3b;--ink:#1d2a23;--mint:#ecf2e3;--line:#d5dfd0;--bad:#b3261e}
*{box-sizing:border-box}body{margin:0;background:var(--mint);color:#47524b;font:17px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
main{max-width:760px;margin:0 auto;padding:2rem 1.25rem 4rem}h1{color:var(--ink);font-size:1.9rem;line-height:1.15;margin:0 0 .5rem}h2{color:var(--ink);font-size:1.15rem;margin:1.75rem 0 .6rem}
.card{background:#fff;border-radius:22px;padding:1.75rem;border:1px solid var(--line)}.steps{display:flex;gap:.4rem;margin:0 0 1.5rem;padding:0;list-style:none;font-size:.9rem;flex-wrap:wrap}
.steps li{padding:.35rem .9rem;border-radius:999px;background:#fff;border:1px solid var(--line)}.steps li[aria-current]{background:var(--ink);color:#fff;border-color:var(--ink)}
label{display:block;font-weight:600;color:var(--ink);margin:1rem 0 .3rem}input[type=text],input[type=email],input[type=password],input[type=number],input[type=url]{width:100%;padding:.75rem 1rem;border:2px solid var(--line);border-radius:14px;font:inherit;color:var(--ink)}
input:focus{outline:3px solid #ff5b2e;outline-offset:2px}.row{display:grid;grid-template-columns:1fr 1fr;gap:0 1rem}@media(max-width:600px){.row{grid-template-columns:1fr}}
button{margin-top:1.5rem;background:#ff5b2e;color:#2b0e04;border:0;border-radius:999px;padding:.9rem 1.7rem;font:600 1rem system-ui;cursor:pointer}button[disabled]{opacity:.5;cursor:not-allowed}
.hint{font-size:.9rem;margin:.3rem 0 0}.err{background:#fde4e1;color:#7a1a14;border-radius:14px;padding:.9rem 1.1rem;margin:0 0 1rem}.ok{color:var(--deep);font-weight:600}.bad{color:var(--bad);font-weight:600}
table{width:100%;border-collapse:collapse;font-size:.95rem}td{padding:.5rem .4rem;border-bottom:1px solid var(--line);vertical-align:top}code{background:var(--mint);padding:.1rem .35rem;border-radius:6px}pre{background:#0f172a;color:#e2e8f0;padding:1rem;border-radius:14px;overflow:auto;font-size:.82rem}
</style>
</head>
<body>
<main>
<p style="font-weight:700;color:var(--deep);letter-spacing:.02em">HiKeVet – instalare</p>
<ol class="steps" aria-label="Pași de instalare">
@foreach (['Cod de acces', 'Verificări', 'Bază de date și email', 'Tabele', 'Administrator'] as $i => $label)
    <li @if ($i + 1 === $step) aria-current="step" @endif>{{ $i + 1 }}. {{ $label }}</li>
@endforeach
</ol>
<div class="card">@yield('content')</div>
</main>
</body>
</html>
