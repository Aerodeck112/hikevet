# Runbook: importul datelor și comutarea

Pașii, în ordine. Fiecare etapă are un criteriu de trecere; fără el nu se merge mai departe.
**Până la pasul 8, site-ul WordPress rămâne neatins și funcțional.**

## 0. Ce trebuie furnizat

| Element | Cum se transmite |
|---|---|
| Export SQL complet al bazei WordPress | canal privat (nu email necriptat, nu chat, nu Git) |
| Arhiva `wp-content/uploads` | idem |
| Sursa modulului de consimțământ din producție (pare 1.5.2) | ZIP |
| Versiunea PHP și limitele găzduirii | captură din cPanel |
| Textul politicii de confidențialitate, programul clinicii | document |

Exportul conține CNP-uri și semnături. Se păstrează doar pe mediul de probă și se șterge după migrare.

## 1. Mediu de probă

Instalare pe un subdomeniu (`INSTALL_CPANEL.md`). După instalare, în `.env` se schimbă
`APP_ENV=production` în `APP_ENV=staging`: `robots.txt` interzice atunci indexarea, iar importul
nu cere `--force`. La comutare se pune înapoi `production`.

## 2. Copia bazei WordPress

Dacă sursa este un backup All-in-One WP Migration (`.wpress`), se desface fără WordPress:

```bash
php artisan hikevet:wpress backup.wpress --list
```

```bash
php artisan hikevet:wpress backup.wpress storage/imports/wpress --only=database --only=uploads --only=plugins
```

Rezultă `database.restored.sql` (cu prefixul original al tabelelor), `uploads/` și `plugins/`.
Comanda verifică CRC-ul fiecărui fișier și oprește extragerea dacă arhiva este incompletă.

Exportul se încarcă într-o **bază separată**, nu în baza platformei. În `.env`:

```
WP_DB_HOST=127.0.0.1
WP_DB_DATABASE=nume_baza_copie
WP_DB_USERNAME=utilizator
WP_DB_PASSWORD=parola
WP_UPLOADS_PATH=/home/UTILIZATOR/copie-uploads
```

Utilizatorul are nevoie doar de drept de citire (`SELECT`).

**Setul de caractere contează.** `database.restored.sql` începe cu `SET NAMES utf8mb4`, așa că se încarcă
corect din phpMyAdmin sau din linia de comandă. Dacă se folosește alt fișier, încărcarea se face cu
`mysql --default-character-set=utf8mb4 ...` (în phpMyAdmin: setul de caractere al fișierului `utf-8`).
Altfel diacriticele sunt codate de două ori („câine” devine „cÃ¢ine”) și datele serializate din WordPress
(consimțămintele, de exemplu) nu se mai pot citi. Importul verifică acest lucru și refuză să pornească.

### Ce a arătat proba pe backupul real (17.11.2026)

- Backupul All-in-One se desface și se verifică integral (CRC pe fiecare fișier).
- Pluginul de backup scrie valorile binare goale ca `0x`; comanda `hikevet:wpress` le corectează
  (doar în tabele de sistem, de ex. Wordfence) și afișează tabelele atinse.
- Starea de plată a contractelor nu este fiabilă în meta contractului: Wallet Core o scria doar pe
  ultimul contract al clientului. Importul reconstruiește starea din plățile reale din registru.
- Pot exista contracte fără cont și cu email invalid (teste din perioada de dezvoltare): sunt
  raportate, iar PDF-urile lor se păstrează în Arhiva WordPress.
- Pachetele generate pentru Primărie (`uploads/hikevet-city-package-*.zip`) se păstrează cu
  `--keep-file`. Directoarele cu același nume sunt fișiere intermediare ale generării și nu mai sunt necesare.
- La prima probă baza a fost încărcată cu setul de caractere greșit: 331 de consimțăminte păreau fără
  conținut. Cauza nu era backupul (fișierul SQL este corect), ci încărcarea; de aceea există acum
  verificarea de mai sus. Refăcută corect, proba a citit toate cele 456 de consimțăminte.
- Consimțămintele aduc și lista pacienților vizitei (345), legătura cu vizita și lanțul variantelor
  corectate (31, cu motivul corecției).
- **Testele automate nu se rulează niciodată în același terminal cu setările unei baze reale.**
  Platforma refuză oricum, din 17.11.2026, orice bază al cărei nume nu indică o bază de test.

## 3. Proba

```bash
php artisan hikevet:import-wordpress
```

Nu scrie nimic. Raportul apare în terminal și în admin, la *Sistem → Migrare date*.

**Criteriu de trecere**, de verificat împreună cu clinica:

- prefixul detectat este cel corect; nicio tabelă așteptată nu lipsește;
- coloanele în plus sau în minus față de schema din cod sunt înțelese;
- numărul de conturi, animale, contracte, tranzacții și dosare corespunde cu ce se vede în WordPress;
- dosarele finalizate pe luni corespund rapoartelor trimise Primăriei;
- lista de erori (emailuri invalide, contracte fără date, coduri duplicate) este acceptată sau rezolvată în sursă;
- soldurile negative raportate sunt explicate.

## 4. Importul

```bash
php artisan hikevet:backup --label=inainte-de-import --verify
```

```bash
php artisan hikevet:import-wordpress --execute --uploads=/home/UTILIZATOR/copie-uploads --dump=/cale/database.restored.sql
```

Ordinea este fixă: conturi, animale, contracte (cu PDF-urile vechi), registru, tratamente,
consimțăminte, notificări, jurnale, notițe, apoi arhiva de sterilizări (care se leagă de
consimțăminte) și la final arhiva brută a întregii baze. Se poate rula pe părți cu
`--only=core`, `--only=sterilization` sau `--only=legacy`.

## 5. Verificare după import

| Verificare | Unde |
|---|---|
| 10 clienți aleși de clinică: animale, contracte, sold de puncte și Wallet față de WordPress | *Clinică → Clienți* |
| 10 dosare de sterilizare: toate câmpurile, fotografia, actele, istoricul | *Campanie sterilizare → Dosare* |
| Dosarele cu „Lipsă în sursă” sunt cele așteptate | filtrul din listă și *Raport din datele originale* |
| Un consimțământ semnat: text, semnături, dată | *Clinică → Consimțăminte* |
| Un client se autentifică cu parola din WordPress | `/acces-cont/` |
| Pentru o lună deja raportată: același număr de intervenții, aceleași numere de pacient | *Campanie sterilizare → Raport Primărie* |
| Desfășurătorul Excel și centralizatorul unei luni, comparate cu cele trimise Primăriei | idem |
| Programările viitoare ale campaniei apar în calendar, cu orele corecte | *Campanie sterilizare → Programări* |
| A doua rulare a importului nu creează nimic nou | terminal |

Dacă arhiva nu este corectă: `php artisan hikevet:import-wordpress --rollback=<ID lot>`, corecție, din nou.
Pentru restul datelor, revenirea se face din backupul de la pasul 4.

## 6. Aprobare

Clinica confirmă în scris: conținutul și textele (inclusiv cele 61 de formulări cu „asigurare”),
prețurile afișate, deciziile N1–N8 și abaterile din `docs/FUNCTIONAL_PARITY_CHECKLIST.md`,
politica de confidențialitate, modul de programare (Digitail sau propriu).

## 7. Pregătirea comutării

1. Cu 48 de ore înainte: TTL-ul DNS coborât, dacă se schimbă serverul.
2. SMTP configurat și testat cu un email real către o adresă a clinicii.
3. Cron activ; „Actualizări și sănătate” fără avertismente.
4. Conturile personalului create, cu autentificare în doi pași.
5. Backup complet al contului WordPress din cPanel, descărcat.

## 8. Comutarea

Într-un interval fără activitate (seara, după ultima intervenție a zilei și fără programări în desfășurare):

1. WordPress în mod mentenanță: din acest moment nu mai apar date noi în sursă (nici programări,
   nici dosare, nici semnături pe tabletă).
2. Export SQL **nou** și copie nouă a `uploads`; pașii 2–5 repetați pe datele finale (importul
   actualizează ce există și adaugă ce este nou).
3. Document root al domeniului principal mutat către `hikevet/public` (`INSTALL_CPANEL.md` §4);
   `APP_ENV=production`, `APP_URL=https://hikevet.com`.
4. Verificări: prima pagină, un serviciu, `/abonamente/`, autentificare client, `/admin`,
   `/robots.txt`, un redirect vechi, formularul de contact.
5. Search Console: sitemap nou.

### Campania de sterilizare: doar istoric

Campania s-a încheiat. În platforma nouă ea rămâne **oprită** (implicit) și servește doar ca istoric,
pentru eventualele întrebări ale Primăriei:

- nu se trimite niciun email al campaniei, nici automat (reamintiri, follow-up), nici manual;
- nu se fac programări noi și nu se creează dosare noi; programările viitoare importate rămân
  neatinse, ca istoric, și nu se mai lucrează cu ele;
- pagina de încărcare a fotografiilor de pe telefon nu mai răspunde;
- dosarele, documentele, consimțămintele, istoricul și rapoartele pentru Primărie (Excel, PDF, ZIP)
  rămân disponibile; un dosar existent se mai poate corecta dacă Primăria o cere.

Repornirea se poate face doar din *Sistem → Setări clinică → Campanie sterilizare* și se scrie în jurnal.

## 9. Revenire

WordPress rămâne intact în `public_html` (sau în directorul redenumit). Dacă apare o problemă
gravă în primele zile, document root se mută înapoi. Contractele și operațiunile făcute între
timp în platforma nouă ar trebui reintroduse manual în WordPress, deci decizia de revenire se
ia repede sau deloc.

## 10. După comutare

- Două săptămâni: WordPress păstrat, dar inaccesibil public.
- Apoi: arhivare și ștergere de pe server. Astfel dispar contractele PDF publice și README-urile expuse.
- Exportul SQL și copia `uploads` de pe mediul de probă: șterse.
- Cheile și parolele care au circulat în timpul migrării: schimbate.

## Limite

- ZIP-ul pentru Primărie pe toată perioada (469 de dosare în proba reală) are aproximativ 630 MB și se
  generează în aproximativ 2,5 minute. Pe găzduiri cu limită mică de timp sau memorie se descarcă pe luni
  (septembrie, 137 de dosare: aproximativ 110 MB, un minut).
- Fișierele pe care backupul nu le conținea (4 acte de identitate și 2 fotografii, în proba reală) rămân
  marcate „Lipsă în sursă”; în ZIP apar ca fișiere `_EROARE` cu acest motiv.
