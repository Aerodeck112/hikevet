# HiKeVet Next

Platformă web proprie pentru clinica veterinară HiKeVet (Târgu Mureș), care înlocuiește site-ul
WordPress: site public, administrare conținut, abonamente cu contract PDF și semnătură, conturi
de client cu puncte și Wallet, consimțământ pe tabletă, arhivă de sterilizări, programări,
instalator cPanel și actualizări ZIP semnate.

Laravel 13 · PHP 8.3+ · MySQL/MariaDB · Filament 5 pentru admin · fără WordPress la rulare.

## Stare (14.11.2026)

Codul rulează și este testat local (135 de teste, pe SQLite și pe MariaDB 10.11). **Nu este încă
în producție și nu conține date reale**: exportul SQL și fișierele din `wp-content/uploads` nu au
fost furnizate. Ce este implementat, ce este doar pregătit și ce așteaptă date este descris în
[`TEST_REPORT.md`](TEST_REPORT.md) și [`docs/FUNCTIONAL_PARITY_CHECKLIST.md`](docs/FUNCTIONAL_PARITY_CHECKLIST.md).

## Pornire locală

```bash
composer install
```

```bash
cp .env.example .env && php artisan key:generate
```

```bash
touch database/database.sqlite && php artisan migrate --seed
```

```bash
php artisan hikevet:create-admin admin@exemplu.ro "Nume Prenume"
```

```bash
php artisan serve
```

Site-ul este la `http://127.0.0.1:8000/`, adminul la `/admin`. Asseturile sunt deja compilate în
`public/build`; se recompilează cu `npm install && npm run build` doar când se schimbă CSS sau JS.

`php artisan migrate --seed` încarcă datele de bază: rolurile, lista de prețuri reală (din
simulatorul 2.7.0) și conținutul public preluat de pe hikevet.com. Nu încarcă niciun client.

## Teste

```bash
php artisan test
```

```bash
php artisan test -c phpunit.mysql.xml
```

A doua comandă rulează aceeași suită pe MySQL/MariaDB (cere o bază goală; vezi fișierul).
`tools/t.sh` afișează un rezumat scurt. Testul `SimulatorParityTest` compară motorul de prețuri
cu funcțiile reale din pluginul WordPress și se sare automat dacă `source-wordpress/` lipsește.

## Structură

| Zonă | Unde |
|---|---|
| Logică de business | `app/Services` (Subscriptions, Wallet, Consents, Booking, Import, Updates, Backup, Install) |
| Admin | `app/Filament` |
| Site public și cont client | `app/Http/Controllers`, `resources/views` |
| Date de bază | `database/seeders`, `database/seeders/data` |
| Unelte de audit și conținut | `tools/` |
| Documentație | fișierele `.md` din rădăcină și `docs/` |

## Documente

| Document | Conținut |
|---|---|
| [`AUDIT.md`](AUDIT.md) | Auditul site-ului public și tabelul existent / de portat / necesită sursa / propunere nouă |
| [`docs/PLUGIN_AUDIT.md`](docs/PLUGIN_AUDIT.md) | Auditul celor patru pluginuri, cu dovezi din cod |
| [`MIGRATION_MAP.md`](MIGRATION_MAP.md) | Entități și tabele WordPress → Laravel |
| [`docs/SOURCE_TO_LARAVEL_MATRIX.md`](docs/SOURCE_TO_LARAVEL_MATRIX.md) | Funcție WordPress → serviciu, model, rută, test |
| [`docs/IMPORT_RUNBOOK.md`](docs/IMPORT_RUNBOOK.md) | Pașii importului, de la exportul SQL la comutare |
| [`INSTALL_CPANEL.md`](INSTALL_CPANEL.md) | Instalarea pe cPanel |
| [`UPDATES.md`](UPDATES.md) | Actualizări ZIP semnate, backup și restaurare |
| [`SECURITY.md`](SECURITY.md) | Măsuri de securitate și GDPR, limite cunoscute |
| [`SEO_MIGRATION.md`](SEO_MIGRATION.md) | URL-uri păstrate, redirecturi 301, date structurate |
| [`TEST_REPORT.md`](TEST_REPORT.md) | Ce a fost testat, cum și cu ce rezultat |

## Reguli care nu se negociază în cod

- Prețurile, reducerile și sumele se calculează numai pe server, din lista de prețuri versionată.
- Registrul Wallet și jurnalul de audit se pot doar completa; soldurile sunt mereu suma tranzacțiilor.
- Arhiva de sterilizări este doar pentru citire; ce lipsește în sursă rămâne marcat „Lipsă în sursă”.
- CNP, CI, semnături și contracte stau criptate sau pe disc privat și nu au adrese publice.
- Niciun fișier cu date reale (`.sql`, exporturi) nu intră în Git.
