# Plăți online cu cardul (Stripe)

Clienții pot plăti din contul lor, cu cardul, **alimentarea Wallet-ului** și **abonamentul** (prima plată
sau reînnoirea). Plata se face pe pagina securizată Stripe: datele cardului nu ajung niciodată pe serverul
clinicii. Factura se emite **manual**, ca până acum; platforma ține evidența ei.

## Ce primește clientul

| Plată | Reguli (aceleași ca la clinică) |
|---|---|
| Alimentare Wallet | doar cu abonament activ; între 10 și 20.000 lei; X lei principal + X lei bonus servicii + X/2 lei bonus produse |
| Abonament | contract care așteaptă plata (în termenul ofertei), expirat sau activ în ultimele 7 zile; 1, 6 sau 12 luni, la prețul din contract; punctele se adaugă automat |

După plată, contul se actualizează imediat. Clinica primește un email „plată online primită, de facturat”.

## Configurare (o singură dată)

1. Cont Stripe pe numele firmei (stripe.com), cu datele firmei și contul bancar.
2. **Întâi în modul de test.** În Stripe: *Developers → API keys*, cheia secretă de test (`sk_test_…`).
3. În admin: *Sistem → Setări clinică → Plăți online cu cardul (Stripe)*: se lipește cheia, se salvează.
   Cheia se păstrează criptat și nu mai este afișată. Nu se trimite pe email, chat sau WhatsApp.
4. Notificările Stripe (recomandat): în Stripe, *Developers → Webhooks → Add endpoint*, adresa
   `https://hikevet.com/stripe/webhook`, evenimentele `checkout.session.completed`,
   `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `checkout.session.expired`.
   Secretul afișat de Stripe (`whsec_…`) se lipește în același loc din setări.
   Fără ele plățile se confirmă tot: la întoarcerea clientului și printr-o verificare la 15 minute.
5. Se bifează „Clienții pot plăti online cu cardul”.
6. Probă în modul de test, cu cardul de test Stripe `4242 4242 4242 4242` (orice dată viitoare, orice CVC):
   o alimentare Wallet și o plată de abonament. Se verifică soldurile, punctele și emailul către clinică.
7. **Trecerea la plăți reale:** în setări se înlocuiesc cheia și secretul webhook-ului cu cele din modul
   real (`sk_live_…`, alt `whsec_…`, pentru un webhook creat în modul real). Pagina „Plăți online” arată
   permanent în ce mod se lucrează.

## Lucrul zilnic: facturile

*Abonamente → Plăți online* arată implicit plățile încasate fără factură; meniul are un contor.

1. Se emite factura manual, în programul de facturare, cu datele din rând (client, ce s-a plătit, suma, data).
2. Pe rând: **Factură emisă** → seria și numărul, data. Rândul dispare din lista „de emis”.

O plată **„Plătită, de verificat”** înseamnă că banii au fost încasați, dar operațiunea nu a putut fi aplicată
automat (de exemplu abonamentul fusese deja plătit la clinică între timp). Motivul apare pe rând. Se rezolvă
manual: se aplică din fișa clientului sau se rambursează din contul Stripe, apoi se emite factura sau nu,
după caz.

## Rambursări

Se fac din contul Stripe (*Payments → plata → Refund*). Platforma nu le face singură: dacă un abonament sau o
alimentare se anulează, se corectează și în fișa clientului (ajustare de sold, schimbarea stării contractului),
cu motiv, ca să rămână urmă.

## Siguranță

- Datele cardului sunt doar la Stripe.
- Plata se confirmă numai după ce platforma citește direct de la Stripe că a fost încasată; adresa paginii de
  întoarcere nu este de ajuns. Notificările Stripe se acceptă doar cu semnătură validă și recentă.
- Suma și moneda încasate se compară cu cele pornite; orice diferență oprește aplicarea automată.
- Aceeași plată nu poate fi aplicată de două ori, oricâte confirmări sosesc.
- Cheile se păstrează criptat; schimbarea lor se scrie în jurnalul de audit.
