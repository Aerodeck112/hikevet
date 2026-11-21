{{-- Asistentul AI (Hiko): fereastra de chat. Raspunsurile vin de la /asistent/intreaba. --}}
<div class="ai" x-data="assistantChat({ url: @js(route('assistant.ask')), token: @js(csrf_token()), phone: @js($clinic['phone']) })" x-cloak @keydown.escape.window="open = false">
    <button class="ai-launch" type="button" @click="toggle()" :aria-expanded="open.toString()" aria-controls="ai-panel" x-show="!open">
        <span class="ai-face" aria-hidden="true"></span>
        <span>Întreabă-l pe Hiko</span>
    </button>
    <section class="ai-panel" id="ai-panel" x-show="open" x-transition.origin.bottom.right role="dialog" aria-label="Asistentul HiKeVet">
        <header class="ai-head">
            <span class="ai-face" aria-hidden="true"></span>
            <div>
                <h2>Hiko, asistentul HiKeVet</h2>
                <p>Răspunsuri automate (Claude, Anthropic) despre clinică. Nu înlocuiesc consultul.</p>
            </div>
            <button class="ai-close" type="button" @click="open = false" aria-label="Închide asistentul"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg></button>
        </header>
        <div class="ai-log" x-ref="log" aria-live="polite">
            <div class="ai-msg ai-msg--bot"><p>Ham! Sunt Hiko. Te ajut cu informații despre servicii, abonamente, Wallet și programări. Pentru urgențe, sună direct la <a :href="'tel:' + phone.replace(/[^0-9+]/g, '')" x-text="phone"></a>.</p></div>
            <template x-for="(m, i) in messages" :key="i">
                <div class="ai-msg" :class="m.role === 'user' ? 'ai-msg--me' : 'ai-msg--bot'">
                    <template x-if="m.role === 'user'"><p x-text="m.content"></p></template>
                    <template x-if="m.role !== 'user'"><div x-html="m.html"></div></template>
                </div>
            </template>
            <div class="ai-msg ai-msg--bot ai-typing" x-show="busy"><span></span><span></span><span></span><span class="sr-only">Hiko scrie…</span></div>
        </div>
        <div class="ai-suggest" x-show="messages.length === 0">
            <template x-for="s in suggestions" :key="s"><button type="button" @click="send(s)" x-text="s"></button></template>
        </div>
        <form class="ai-form" @submit.prevent="send()">
            <label class="sr-only" for="ai-input">Întrebarea ta</label>
            <textarea id="ai-input" x-ref="input" x-model="draft" rows="1" maxlength="800" placeholder="Scrie întrebarea ta…" @keydown.enter.prevent="if (!$event.shiftKey) send()" :disabled="busy"></textarea>
            <button class="ai-send" type="submit" :disabled="busy || !draft.trim()" aria-label="Trimite"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12l16-8-6 16-2-7z" fill="currentColor"/></svg></button>
        </form>
    </section>
</div>
