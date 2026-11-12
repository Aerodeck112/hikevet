<x-filament-panels::page>
    <div class="hv-client">
        <dl class="hv-stats">
            <div class="hv-stat"><dt>Stare</dt><dd><span class="hv-pill {{ ['signed' => 'hv-pill--ok', 'pending' => 'hv-pill--warn', 'cancelled' => 'hv-pill--bad'][$consent->status->value] }}">{{ $consent->status->label() }}</span></dd></div>
            @if ($consent->status->value === 'pending')
                <div class="hv-stat">
                    <dt>Cod pentru tabletă</dt>
                    @if ($plainCode)
                        <dd style="letter-spacing:.25em">{{ $plainCode }}</dd>
                        <p class="hv-muted">Valabil până la {{ $consent->code_expires_at?->format('d.m.Y H:i') }}. Se afișează o singură dată.</p>
                    @else
                        <dd style="font-size:1rem">ascuns</dd>
                        <p class="hv-muted">Codul nu se păstrează în clar. Generează unul nou dacă este nevoie.</p>
                    @endif
                </div>
                <div class="hv-stat"><dt>Pagina tabletei</dt><dd style="font-size:1rem;word-break:break-all">{{ $tabletUrl }}</dd></div>
            @endif
            @if ($consent->signed_at)<div class="hv-stat"><dt>Semnat de proprietar</dt><dd style="font-size:1.1rem">{{ $consent->signed_at->format('d.m.Y H:i') }}</dd></div>@endif
            @if ($intact !== null)
                <div class="hv-stat"><dt>Integritate document</dt><dd><span class="hv-pill {{ $intact ? 'hv-pill--ok' : 'hv-pill--bad' }}">{{ $intact ? 'Neschimbat de la semnare' : 'Diferă de amprenta de la semnare' }}</span></dd></div>
            @endif
        </dl>

        @if ($consent->supersedes_id)
            <p class="hv-pill hv-pill--warn" style="padding:.5rem 1rem">Versiune corectată a consimțământului #{{ $consent->supersedes_id }}, care rămâne păstrat pentru audit.</p>
        @endif

        <div class="hv-doc">@include('consent.document', ['p' => $p, 'mode' => 'print'])</div>

        <div class="hv-grid-2">
            <section class="hv-card">
                <h3>Semnătura proprietarului</h3>
                @if ($consent->owner_signature)<img src="{{ $consent->owner_signature }}" alt="Semnătura proprietarului" style="max-height:120px;background:#fff;border-radius:.5rem">
                @else <p class="hv-muted">Documentul nu a fost încă semnat pe tabletă.</p> @endif
                @if ($consent->refusal_signature)
                    <h3 style="margin-top:1rem">Semnătura pentru refuz informat</h3>
                    <img src="{{ $consent->refusal_signature }}" alt="Semnătura pentru refuz informat" style="max-height:120px;background:#fff;border-radius:.5rem">
                @endif
            </section>

            <section class="hv-card">
                <h3>Semnătura medicului</h3>
                @if ($consent->doctor_signature)
                    <img src="{{ $consent->doctor_signature }}" alt="Semnătura medicului" style="max-height:120px;background:#fff;border-radius:.5rem">
                    <p class="hv-muted">Salvată la {{ $consent->doctor_signed_at?->format('d.m.Y H:i') }} de {{ $consent->doctorSigner?->name }}.</p>
                @else
                    <p class="hv-muted">Fără semnătură desenată: documentul tipărit afișează numele medicului, marcat ca identificare generată automat.</p>
                @endif
                @if ($canDoctorSign)
                    <div x-data="{ drawing: false, last: null, ink: false, ctx: null,
                            init() { this.ctx = this.$refs.pad.getContext('2d'); this.clear(); },
                            pos(e) { const r = this.$refs.pad.getBoundingClientRect(); return { x: (e.clientX - r.left) * (this.$refs.pad.width / r.width), y: (e.clientY - r.top) * (this.$refs.pad.height / r.height) }; },
                            down(e) { this.$refs.pad.setPointerCapture(e.pointerId); this.drawing = true; this.last = this.pos(e); },
                            move(e) { if (!this.drawing) return; const p = this.pos(e); this.ctx.beginPath(); this.ctx.moveTo(this.last.x, this.last.y); this.ctx.lineTo(p.x, p.y); this.ctx.stroke(); this.last = p; this.ink = true; },
                            clear() { this.ctx.fillStyle = '#fff'; this.ctx.fillRect(0, 0, 700, 180); this.ctx.lineWidth = 2.3; this.ctx.lineCap = 'round'; this.ctx.strokeStyle = '#0f172a'; this.ink = false; },
                            save() { if (!this.ink) return; $wire.set('doctorSignature', this.$refs.pad.toDataURL('image/png')).then(() => $wire.saveDoctorSignature()).then(() => this.clear()); } }" style="margin-top:1rem">
                        <canvas x-ref="pad" width="700" height="180" @pointerdown.prevent="down($event)" @pointermove.prevent="move($event)" @pointerup="drawing = false" @pointercancel="drawing = false"
                                style="width:100%;height:auto;border:2px dashed #cbd5e1;border-radius:.75rem;background:#fff;touch-action:none;cursor:crosshair" aria-label="Chenar pentru semnătura medicului" role="img"></canvas>
                        <div style="display:flex;gap:.5rem;margin-top:.5rem">
                            <x-filament::button color="gray" size="sm" x-on:click="clear()">Șterge</x-filament::button>
                            <x-filament::button size="sm" x-on:click="save()" x-bind:disabled="!ink">Salvează semnătura medicului</x-filament::button>
                        </div>
                        <p class="hv-muted" style="margin-top:.5rem">Datele clinice și semnătura proprietarului rămân blocate; se modifică doar semnătura medicului.</p>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-filament-panels::page>
