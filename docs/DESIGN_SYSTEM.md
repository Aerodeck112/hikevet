# Sistemul de design

Brieful inițial cerea întâi o copie fidelă a site-ului actual, apoi propuneri de modernizare. Pe
12.11.2026 clientul a schimbat direcția: păstrează fotografiile, culorile și identitatea, dar
designul poate fi nou și distinct. Ce urmează descrie rezultatul. Etapa de „copie 1:1” și
variantele alternative de design **nu au fost realizate**.

## 1. Ce s-a păstrat din identitatea HiKeVet

- **Culorile**, măsurate pe site-ul actual: verde `#21B073`, portocaliu `#FF5B2E`, galben
  `#FAC44D`, verde pal `#ECF2E3`, albastru deschis `#BEDCF5`.
- **Logoul** și cele 78 de imagini reale de pe site (cabinet, echipă, parteneri), optimizate.
  Nicio imagine nu a fost generată sau înlocuită.
- **Forma de capsulă** pentru fotografii, deja prezentă pe site, devenită motivul principal.
- **Tonul**: titluri mari și rotunde, accent scris de mână, mult spațiu.
- Toate textele, cu excepția formulărilor „asigurare” (vezi `docs/generated/flagged-texts.md`).

## 2. Ce s-a schimbat și de ce

| Schimbare | Motiv |
|---|---|
| Titluri în **Dela Gothic One** în loc de Bowlby One | Bowlby One nu are ă, ș, ț; pe site-ul actual aceste litere apar în alt font |
| Accent în **Caveat** în loc de Nothing You Could Do | aceeași problemă de diacritice |
| Text în **Jost** | păstrat |
| Verde închis `#0B5C3B` și negru-verzui `#1D2A23` adăugate | contrast suficient pentru text (verdele de brand pe alb nu trece pragul AA la text mic) |
| Meniu plutitor în formă de capsulă; bară de acțiuni jos pe mobil | „Sună” și „Programare” la un deget distanță |
| Fonturi servite local | fără cereri către Google Fonts, încărcare mai rapidă |

## 3. Elemente de bază

| Element | Valoare |
|---|---|
| Culori | `--green #21b073`, `--green-deep #0b5c3b`, `--orange #ff5b2e`, `--sun #fac44d`, `--mint #ecf2e3`, `--sky #bedcf5`, `--paper #fbfdf8`, `--ink #1d2a23`, `--body #47524b` |
| Fonturi | titluri Dela Gothic One; text Jost (variabil); accent Caveat (variabil) |
| Scară tipografică | 6 trepte fluide (`clamp`), de la 0,88 rem la 6,1 rem |
| Lățime | conținut de cel mult 1240 px |
| Colțuri | 28 px pentru carduri; capsulă pentru butoane și fotografii |
| Spațiu între secțiuni | 4 – 8,5 rem, fluid |
| Focus | contur portocaliu de 3 px, vizibil pe orice fundal |

Sursa: `resources/css/site.css` (CSS scris de mână, fără framework). Panoul de administrare are
tema lui (`resources/css/admin.css`, Filament), cu aceleași culori și font.

## 4. Componente

Meniu capsulă și meniu mobil; secțiune principală cu fotografie în capsulă; carduri de serviciu;
calculator de preț; configurator în 6 pași cu câmp de semnătură; bandă de abonament; acordeon de
întrebări; carduri de articol; formular; bară de acord pentru cookie-uri; bară de acțiuni mobilă;
subsol. Pictogramele sunt un set propriu (`<x-hv-icon>`), imaginile trec prin `<x-img>` (WebP,
două dimensiuni, `width` și `height`, încărcare întârziată).

## 5. Accesibilitate

- Lighthouse: 100 la accesibilitate pe cele 7 pagini măsurate (test automat; nu înlocuiește
  verificarea cu cititor de ecran, care nu a fost făcută).
- Contrast AA pentru text; un singur H1 pe pagină; link „Sari la conținut”; etichete pe toate câmpurile.
- Meniul mobil anunță starea (`aria-expanded`) și se închide cu Esc.
- Animațiile rulează doar dacă utilizatorul nu a cerut reducerea mișcării (`prefers-reduced-motion`).
- Butoanele au cel puțin 52 px înălțime; câmpul de semnătură merge cu degetul, stiloul și mouse-ul.

## 6. Mișcare

Intrări discrete la derulare și o tranziție la meniu; nimic care blochează conținutul. Fără
biblioteci de animație și fără 3D: s-a preferat viteza de încărcare (vezi `SEO_MIGRATION.md` §4).

## 7. De hotărât împreună cu clinica

1. Dacă noul font de titlu este acceptat ca parte a identității.
2. Fotografii reale în locul celor de stoc (multe imagini actuale provin din bănci de imagini;
   licența lor trebuie confirmată).
3. Fotografie și biografie pentru Dregics Szidonia (pe site există doar numele și funcția).
4. Adresa de inserare a turului 3D.
