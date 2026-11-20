{{-- Simulator Wallet, cu aceleasi reguli ca la clinica. Nu incaseaza nimic; doar arata cum se imparte suma. --}}
<div class="walletsim" x-data="walletCalc()">
    <div class="walletsim-input">
        <label for="ws-amount">Cât depui în Wallet</label>
        <output for="ws-amount"><span x-text="money(amount)"></span> lei</output>
        <input class="range" id="ws-amount" type="range" min="50" max="2000" step="50" x-model.number="amount">
    </div>

    <div class="jars" aria-live="polite">
        <div class="jar jar--principal">
            <div class="jar-glass"><span class="jar-fill" :style="'--h:' + bar(principal) + '%'"></span></div>
            <strong x-text="money(principal) + ' lei'"></strong>
            <span>Sold principal</span>
        </div>
        <div class="jar jar--service">
            <div class="jar-glass"><span class="jar-fill" :style="'--h:' + bar(serviceBonus) + '%'"></span></div>
            <strong x-text="money(serviceBonus) + ' lei'"></strong>
            <span>Bonus pentru servicii</span>
        </div>
        <div class="jar jar--product">
            <div class="jar-glass"><span class="jar-fill" :style="'--h:' + bar(productBonus) + '%'"></span></div>
            <strong x-text="money(productBonus) + ' lei'"></strong>
            <span>Bonus pentru produse</span>
        </div>
    </div>
    <p class="walletsim-total">În Wallet ai în total <strong x-text="money(total) + ' lei'"></strong> pentru <span x-text="money(amount)"></span> lei depuși.</p>

    <div class="walletsim-example">
        <label for="ws-service">Exemplu: un serviciu de <output for="ws-service"><span x-text="money(service)"></span> lei</output></label>
        <input class="range" id="ws-service" type="range" min="50" max="1500" step="25" x-model.number="service">
        <div class="split-bar" role="img" :aria-label="money(serviceSplit.bonus) + ' lei din bonus și ' + money(serviceSplit.principal) + ' lei din soldul principal'">
            <span class="sb-bonus" :style="'flex:' + serviceSplit.bonus"><b x-text="money(serviceSplit.bonus) + ' lei bonus'"></b></span>
            <span class="sb-principal" :style="'flex:' + serviceSplit.principal"><b x-text="money(serviceSplit.principal) + ' lei principal'"></b></span>
        </div>
    </div>
    <ul class="walletsim-rules">
        <li>Bonusul pentru servicii acoperă cel mult jumătate din prețul unui serviciu; restul se plătește din soldul principal.</li>
        <li>Bonusul pentru produse acoperă cel mult o treime din prețul unui produs.</li>
        <li>Bonusul nu se folosește la medicamente, consumabile și analize trimise la laboratoare externe.</li>
        <li>Wallet-ul se alimentează doar cât timp ai un abonament activ.</li>
    </ul>
</div>
