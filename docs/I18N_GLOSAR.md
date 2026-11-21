# Traducerea site-ului (RO → EN, HU)

Româna este limba de bază (adresele de la rădăcină); engleza și maghiara au prefix: `/en/...`, `/hu/...`.
Adminul, tableta de consimțământ, contractele PDF și emailurile interne rămân în română.

## Cum se traduce interfața
- Textul românesc din șabloane se pune în `__('…')`, iar **cheia este chiar textul românesc exact** (româna se afișează neschimbată).
- Traducerile sunt în `resources/lang/en.json` și `resources/lang/hu.json` (cheie = textul românesc, valoare = traducerea).
- Variabile: `__('Bună, :name!', ['name' => $nume])`. Nu lipi bucăți de frază; fiecare frază completă e o cheie.
- HTML în text doar când e necesar: `{!! __('… <strong>:x</strong> …', ['x' => e($y)]) !!}`; date de la utilizatori întotdeauna escapate.
- Atribute: `aria-label`, `placeholder`, `alt`, `title` se traduc la fel. În Alpine/JS: `@js(__('…'))`.
- Conținutul din baza de date (servicii, pagini, articole, întrebări, echipă) se traduce separat, în tabelul `translations`.

## Ton
- Română: „tu”, prietenos. Engleză: engleză internațională neutră, prietenoasă. Maghiară: „te” (tegezés), consecvent, natural.
- Documente legale: registru formal; versiunile EN/HU sunt informative, textul în română prevalează.

## Glosar (RO → EN → HU)
| Română | English | Magyar |
|---|---|---|
| abonament (plan de servicii, NU asigurare) | subscription / care plan | előfizetés |
| asigurare (nu se folosește pentru abonament) | insurance | biztosítás |
| Wallet / portofel virtual | Wallet | Wallet (virtuális pénztárca) |
| bonus servicii / bonus produse | service bonus / product bonus | szolgáltatási bónusz / termékbónusz |
| puncte abonament | subscription points | előfizetési pontok |
| programare | appointment | időpont / időpontfoglalás |
| cerere de programare | appointment request | időpontkérés |
| Programează-te | Book an appointment | Időpontot kérek |
| Cont client / Contul meu | Client account / My account | Ügyfélfiók / Fiókom |
| clinică veterinară | veterinary clinic | állatorvosi rendelő |
| medic veterinar | veterinarian | állatorvos |
| consultație | consultation | vizsgálat / konzultáció |
| animăluț / animal de companie | pet | kedvenc / házi kedvenc |
| câine / pisică | dog / cat | kutya / macska |
| deparazitare internă / externă | internal / external parasite treatment | belső / külső parazitamentesítés |
| vaccinare | vaccination | oltás |
| urgență | emergency | sürgős eset |
| categoria de greutate | weight category | súlykategória |
| lunar / 6 luni / 12 luni | monthly / 6 months / 12 months | havi / 6 hónap / 12 hónap |
| lei | lei | lej |
| Târgu Mureș | Târgu Mureș | Marosvásárhely |
| Hiko (câinele) / Raza (pisica) | Hiko / Raza | Hiko / Raza |
| Întreabă-l pe Hiko | Ask Hiko | Kérdezd Hikót |
| Termeni și condiții | Terms and conditions | Általános szerződési feltételek |
| Politica de confidențialitate | Privacy policy | Adatvédelmi tájékoztató |
