<section class="hv-card" id="hv-print">
    <div class="hv-cp-actions" style="justify-content:space-between">
        <div><strong style="font-size:1.2rem">HiKeVet</strong><br><span class="hv-muted">Rezumat pacient și externare · Dosar {{ $row['cod_caz'] }}</span></div>
        <button class="hv-cp-btn hv-cp-btn--primary" type="button" onclick="window.print()">Tipărește rezumatul pacientului</button>
    </div>
    <p class="hv-cp-note">Rezumat complet pentru proprietar: fișa consultației, intervenția și externarea. Substanțele, dozele, protocolul și fotografia rămân interne.</p>
    <h3>Proprietar și documente</h3>
    <table class="hv-table">
        @foreach (['Proprietar' => $row['nume_proprietar'], 'Telefon' => $row['telefon'], 'Email' => $row['email'], 'Adresa' => trim($row['adresa_ci'].($row['localitate'] ? ', '.$row['localitate'] : ''), ', '),
            'Consimțământ + GDPR' => ($consent?->isSigned() ? 'Semnat digital' : 'Nesemnat'), 'Act identitate scanat' => $row['documente_scanate'] ? 'Da' : 'Nu'] as $label => $value)
            <tr><th style="width:38%">{{ $label }}</th><td>{{ $value ?: '-' }}</td></tr>
        @endforeach
    </table>
    <h3 style="margin-top:1rem">Fișa pacientului / consultație preoperatorie</h3>
    <table class="hv-table">@foreach ($consultation as $label => $value)<tr><th style="width:38%">{{ $label }}</th><td style="white-space:pre-line">{{ $value }}</td></tr>@endforeach</table>
    <h3 style="margin-top:1rem">Intervenție intra/postoperatorie</h3>
    <table class="hv-table">@foreach ($intervention as $label => $value)<tr><th style="width:38%">{{ $label }}</th><td style="white-space:pre-line">{{ $value }}</td></tr>@endforeach</table>
    <h3 style="margin-top:1rem">Externare și recomandări postoperatorii</h3>
    <table class="hv-table">
        <tr><th style="width:38%">Data / ora externării</th><td>{{ trim(($row['data_externare'] ?: '-').' '.$row['ora_externare']) }}</td></tr>
        <tr><th>Recontrol postoperator recomandat</th><td>{{ $row['control_recomandat'] ?: '-' }}</td></tr>
    </table>
    <div style="white-space:pre-line;margin-top:.75rem;padding:.9rem;border:1px solid rgb(228 228 231);border-radius:.6rem">{{ $recommendations }}</div>
    <p class="hv-muted" style="margin-top:1rem;font-size:.8rem">HiKeVet · Strada Băneasa 23, Târgu Mureș · +40 771 280 345 · contact@hikevet.com</p>
</section>
<style>@media print { .fi-sidebar, .fi-topbar, .fi-header, .hv-cp-tabs, .hv-cp-actions button { display: none !important; } .hv-card:not(#hv-print) { display: none !important; } }</style>
