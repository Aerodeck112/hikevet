@extends('layouts.bare')

@section('content')
<section class="section--tight">
    <div class="wrap" style="max-width:900px">
        @error('sign')<p class="notice notice--error" role="alert">{{ $message }}</p>@enderror
        <form method="post" action="{{ site_url('/consimtamant-tableta/semnare') }}" x-data="{ owner: false, refusal: false, sending: false }"
              @submit="$refs.ownerData.value = Alpine.$data($refs.ownerPad).data(); if ($refs.refusalPad) $refs.refusalData.value = Alpine.$data($refs.refusalPad).data(); if (!$refs.ownerData.value) { $event.preventDefault(); $refs.ownerError.hidden = false; return; } sending = true">
            @csrf
            <div class="card">
                @include('consent.document', ['p' => $p, 'mode' => 'tablet'])
            </div>

            @if ($hasRefused)
                <div class="card" style="margin-top:1rem;border-color:#f3b4ad;background:#fef4f2">
                    <h2 class="h-sm" id="t-refusal-label">Semnătura proprietarului pentru refuz informat</h2>
                    <p>Semnează aici doar pentru serviciile recomandate refuzate, afișate la punctul 6.</p>
                    <input type="hidden" name="refusal_signature" x-ref="refusalData">
                    <div x-data="signaturePad" x-ref="refusalPad">
                        <div class="sign-wrap"><canvas x-ref="canvas" width="800" height="180" role="img" aria-labelledby="t-refusal-label"></canvas></div>
                        <p style="margin-top:.75rem"><button class="btn btn-ghost btn-sm" type="button" @click="clear()">Șterge semnătura</button></p>
                    </div>
                </div>
            @endif

            <div class="card" style="margin-top:1rem;border-color:#9fd9bd;background:#f1fbf5">
                <h2 class="h-sm">Acord privind datele personale</h2>
                <label class="check"><input type="checkbox" name="owner_final_gdpr" value="1" required><span>Sunt de acord cu prelucrarea datelor mele cu caracter personal și a datelor necesare dosarului medical veterinar, pentru furnizarea serviciilor, comunicare, documente și îndeplinirea obligațiilor legale, conform informării GDPR din acest document.</span></label>
            </div>

            <div class="card" style="margin-top:1rem">
                <h2 class="h-sm">Semnătura finală</h2>
                <label class="check"><input type="checkbox" name="owner_final_read" value="1" required><span>Confirm că am citit sau mi-a fost citit documentul, am înțeles explicațiile și am putut pune întrebări.</span></label>
                <label class="check"><input type="checkbox" name="owner_final_risk" value="1" required><span>Confirm că înțeleg riscurile medicale inerente procedurii și acordul exprimat.</span></label>
                <label class="check"><input type="checkbox" name="owner_final_lucid" value="1" required><span>Declar că sunt deplin lucid/ă și informat/ă la momentul semnării.</span></label>
                <p class="label" id="t-owner-label" style="margin-top:1.25rem">Semnătura proprietarului / reprezentantului legal</p>
                <input type="hidden" name="owner_signature" x-ref="ownerData">
                <div x-data="signaturePad" x-ref="ownerPad">
                    <div class="sign-wrap"><canvas x-ref="canvas" width="800" height="200" role="img" aria-labelledby="t-owner-label"></canvas></div>
                    <p style="margin-top:.75rem"><button class="btn btn-ghost btn-sm" type="button" @click="clear()">Șterge semnătura</button></p>
                </div>
                <p class="form-error" x-ref="ownerError" hidden role="alert">Semnează în chenar înainte de a trimite.</p>
                <p class="hint">Semnătura desenată este o semnătură electronică simplă. După trimitere documentul nu mai poate fi modificat.</p>
                <p style="margin-top:1.25rem;display:flex;gap:.75rem;flex-wrap:wrap">
                    <button class="btn btn-primary" type="submit" :disabled="sending" x-text="sending ? 'Se trimite…' : 'Semnez și trimit documentul'">Semnez și trimit documentul</button>
                </p>
            </div>
        </form>
        <form method="post" action="{{ site_url('/consimtamant-tableta/inchide') }}" style="margin-top:1rem">@csrf<button class="btn btn-ghost btn-sm" type="submit">Închide fără a semna</button></form>
    </div>
</section>
@endsection
