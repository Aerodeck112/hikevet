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

Exportul se încarcă într-o **bază separată**, nu în baza platformei. În `.env`:

```
WP_DB_HOST=127.0.0.1
WP_DB_DATABASE=nume_baza_copie
WP_DB_USERNAME=utilizator
WP_DB_PASSWORD=parola
WP_UPLOADS_PATH=/home/UTILIZATOR/copie-uploads
```

Utilizatorul are nevoie doar de drept de citire (`SELECT`).

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
php artisan hikevet:import-wordpress --execute --uploads=/home/UTILIZATOR/copie-uploads
```

Ordinea este fixă: conturi, animale, contracte, registru, tratamente, consimțăminte, apoi arhiva
de sterilizări (care se leagă de consimțăminte). Se poate rula pe părți cu `--only=core` sau
`--only=sterilization`.

## 5. Verificare după import

| Verificare | Unde |
|---|---|
| 10 clienți aleși de clinică: animale, contracte, sold de puncte și Wallet față de WordPress | *Clinică → Clienți* |
| 10 dosare de sterilizare: toate câmpurile, fotografia, actele | *Clinică → Arhivă sterilizări* |
| Dosarele cu „Lipsă în sursă” sunt cele așteptate | filtrul din arhivă |
| Un consimțământ semnat: text, semnături, dată | *Clinică → Consimțăminte* |
| Un client se autentifică cu parola din WordPress | `/acces-cont/` |
| Raportul lunar pentru o lună deja raportată dă aceleași numere | *Clinică → Rapoarte sterilizări* |
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

Într-un interval fără activitate (seara):

1. WordPress în mod mentenanță: din acest moment nu mai apar date noi în sursă.
2. Export SQL **nou** și copie nouă a `uploads`; pașii 2–5 repetați pe datele finale (importul
   actualizează ce există și adaugă ce este nou).
3. Document root al domeniului principal mutat către `hikevet/public` (`INSTALL_CPANEL.md` §4);
   `APP_ENV=production`, `APP_URL=https://hikevet.com`.
4. Verificări: prima pagină, un serviciu, `/abonamente/`, autentificare client, `/admin`,
   `/robots.txt`, un redirect vechi, formularul de contact.
5. Search Console: sitemap nou.

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

- Fluxul activ al campaniei de sterilizare (programări, dosare noi) **nu există** în platforma
  nouă. Dacă este încă în uz, comutarea completă se amână sau WordPress rămâne pornit pentru el.
- Importatoarele pentru notificări, jurnalul de emailuri, jurnalul de audit vechi și notițele
  medicilor nu sunt scrise.
- PDF-urile contractelor vechi nu sunt copiate automat.
