# Manifestul versiunii 0.1.0

| | |
|---|---|
| Versiune | 0.1.0 (de probă; neinstalată în producție) |
| Data | 14.11.2026 |
| Cod sursă | ramura `claude/gifted-clarke-bf74zj` |
| PHP minim | 8.3.0 |
| Bază de date | MySQL 8 / MariaDB 10.6+ (testat pe MariaDB 10.11) |
| Framework | Laravel 13.42, Filament 5.11, Livewire 4 |

## Pachetul de instalare

| | |
|---|---|
| Fișier | `hikevet-install-0.1.0.zip` |
| Dimensiune | 50438363 octeți (aproximativ 48 MB) |
| Fișiere | 16 699 |
| SHA-256 | `228bb149e1e7b8b7469dc4f579a3ff13a6aa25e2e60aa46cfe0d72d3e6e2b2a0` |
| Verificare | instalat din browser pe Apache 2.4 + PHP 8.3 + MariaDB 10.11; paginile principale, backupul și `schedule:run` au funcționat |

Pachetul **nu este în Git** (este un fișier binar mare). Se reconstruiește oricând din cod; vezi mai jos.
Amprenta se schimbă la fiecare reconstruire, pentru că arhiva conține datele fișierelor.

Conține: codul aplicației, dependențele de producție, asseturile compilate, imaginile site-ului,
un `.env` minimal fără chei și ghidurile `INSTALL_CPANEL.md`, `UPDATES.md`, `SECURITY.md`, `CHANGELOG.md`.

Nu conține: date de clienți, chei sau parole, cheia de semnare, sursele pluginurilor WordPress,
teste, dependențe de dezvoltare. `HIKEVET_UPDATE_PUBLIC_KEY` este gol: cheia se generează de
către cine va publica actualizările (`UPDATES.md` §1) și se trece în `.env` după instalare.

## Reconstruire

Într-o copie curată a ramurii:

```bash
composer install --no-dev --optimize-autoloader
```

```bash
php artisan hikevet:release install-zip
```

Rezultatul apare în `storage/app/releases/`, împreună cu `hikevet-install-0.1.0.manifest.json`
(lista fișierelor cu amprente), care trebuie păstrat: este baza pentru primul pachet incremental.
Asseturile din `public/build` sunt deja în Git; `npm run build` este necesar doar dacă s-a
modificat CSS sau JS.

## Date de bază încărcate la instalare

| Set | Sursă | Conținut |
|---|---|---|
| Roluri și permisiuni | `RolesSeeder` | 6 roluri |
| Lista de prețuri `wp-simulator-2.7.0` | `PriceListSeeder`, valori copiate din plugin | 10 categorii, 15 servicii pe specie, șablonul de contract |
| Conținutul site-ului | `SiteContentSeeder`, preluat de pe hikevet.com | 14 pagini, 23 de servicii în 5 categorii, 2 membri ai echipei, 4 articole, 12 întrebări, 19 parteneri, 9 redirecturi |
| Imagini | `public/media` | 78 de imagini WebP, în două dimensiuni |

Niciun client, contract sau dosar nu este încărcat la instalare.

## Migrări

7 fișiere proprii (`2026_11_12_000001` – `000007`), cele standard Laravel și cele ale pachetului
de permisiuni: 11 fișiere în total.

## Stare cunoscută

Vezi `TEST_REPORT.md` §6 și `docs/FUNCTIONAL_PARITY_CHECKLIST.md`. Pe scurt: platforma este
completă pentru probă; nu a fost rulată pe date reale și nici pe găzduirea finală.
