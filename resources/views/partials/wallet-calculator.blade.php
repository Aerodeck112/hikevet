{{-- Simulator Wallet, cu aceleasi reguli ca la clinica. Nu incaseaza nimic; doar arata cum se imparte suma. --}}
<div class="walletsim" x-data="walletCalc()">
    <div class="walletsim-input">
        <label for="ws-amount">{{ __('Cât depui în Wallet') }}</label>
        <output for="ws-amount"><span x-text="money(amount)"></span> {{ __('lei') }}</output>
        <input class="range" id="ws-amount" type="range" min="50" max="2000" step="50" x-model.number="amount">
    </div>

    <div class="jars" aria-live="polite">
        <div class="jar jar--principal">
            <div class="jar-glass"><span class="jar-fill" :style="'--h:' + bar(principal) + '%'"></span></div>
            <strong x-text="@js(__(':amount lei')).replace(':amount', money(principal))"></strong>
            <span>{{ __('Sold principal') }}</span>
        </div>
        <div class="jar jar--service">
            <div class="jar-glass"><span class="jar-fill" :style="'--h:' + bar(serviceBonus) + '%'"></span></div>
            <strong x-text="@js(__(':amount lei')).replace(':amount', money(serviceBonus))"></strong>
            <span>{{ __('Bonus pentru servicii') }}</span>
        </div>
        <div class="jar jar--product">
            <div class="jar-glass"><span class="jar-fill" :style="'--h:' + bar(productBonus) + '%'"></span></div>
            <strong x-text="@js(__(':amount lei')).replace(':amount', money(productBonus))"></strong>
            <span>{{ __('Bonus pentru produse') }}</span>
        </div>
    </div>
    <p class="walletsim-total">{!! __('În Wallet ai în total :total pentru :amount lei depuși.', ['total' => '<strong x-text="'.e(\Illuminate\Support\Js::from(__(':amount lei'))).'.replace(\':amount\', money(total))"></strong>', 'amount' => '<span x-text="money(amount)"></span>']) !!}</p>

    <div class="walletsim-example">
        <label for="ws-service">{!! __('Exemplu: un serviciu de :amount', ['amount' => '<output for="ws-service"><span x-text="money(service)"></span> '.e(__('lei')).'</output>']) !!}</label>
        <input class="range" id="ws-service" type="range" min="50" max="1500" step="25" x-model.number="service">
        <div class="split-bar" role="img" :aria-label="@js(__(':bonus lei din bonus și :principal lei din soldul principal')).replace(':bonus', money(serviceSplit.bonus)).replace(':principal', money(serviceSplit.principal))">
            <span class="sb-bonus" :style="'flex:' + serviceSplit.bonus"><b x-text="@js(__(':amount lei bonus')).replace(':amount', money(serviceSplit.bonus))"></b></span>
            <span class="sb-principal" :style="'flex:' + serviceSplit.principal"><b x-text="@js(__(':amount lei principal')).replace(':amount', money(serviceSplit.principal))"></b></span>
        </div>
    </div>
    <ul class="walletsim-rules">
        <li>{{ __('Bonusul pentru servicii acoperă cel mult jumătate din prețul unui serviciu; restul se plătește din soldul principal.') }}</li>
        <li>{{ __('Bonusul pentru produse acoperă cel mult o treime din prețul unui produs.') }}</li>
        <li>{{ __('Bonusul nu se folosește la medicamente, consumabile și analize trimise la laboratoare externe.') }}</li>
        <li>{{ __('Wallet-ul se alimentează doar cât timp ai un abonament activ.') }}</li>
    </ul>
</div>
