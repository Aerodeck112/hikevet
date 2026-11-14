# Raport de testare

Data: 14.11.2026 · versiunea 0.1.0 · PHP 8.3.6 · Laravel 13.42

## 1. Rezumat

| Ce | Rezultat |
|---|---|
| Teste automate pe SQLite | 139 de teste, 138 trecute, 1 sărit (relevant doar pe MySQL), 17 744 de verificări |
| Teste automate pe MariaDB 10.11 | 139 din 139 trecute |
| Instalare curată din ZIP, în browser, pe Apache 2.4 + MariaDB | reușită |
| Actualizare reală din panou (0.1.0 → 0.1.1) | reușită |
| Actualizare din linia de comandă (0.1.1 → 0.1.2) | reușită |
| Pachet modificat după semnare | respins |
| Pachet cu migrare stricată (0.1.2 → 0.1.3) | anulat automat; site funcțional pe 0.1.2 |
| Lighthouse pe 7 pagini | accesibilitate, bune practici și SEO 100; performanță 87–100 |

**Ce nu a fost testat:** date reale (lipsește exportul SQL), un cont cPanel real, trimiterea
reală de emailuri, sarcina cron pe server, browsere în afară de Chromium, cititoare de ecran,
încărcare mare, securitate prin test de penetrare.

## 2. Teste automate

```bash
php artisan test
```

| Suită | Teste | Ce verifică |
|---|---|---|
| `PricingEngineTest` | 5 (24 de cazuri) | categorii, tarife, reduceri, servicii pe categorie |
| `SimulatorParityTest` | 3 | **aceleași rezultate ca funcțiile reale din plugin**: fiecare pas de greutate, toate combinațiile de reducere, șablonul de contract (16 897 de comparații) |
| `ContractFlowTest` | 13 | contract de la formular la PDF, criptarea CNP, prețuri din browser ignorate, dublă trimitere, reducere cu emailul altuia refuzată, validarea plății, reînnoire, expirare, acces la PDF, limitare de cereri |
| `WalletLedgerTest` | 11 | alimentare, plafoane de bonus, excluderi, puncte, ajustare, ștergere și restaurare, registru nemodificabil, sold = suma tranzacțiilor |
| `ConsentTest` | 10 | flux pe tabletă, confirmări obligatorii, semnătură de refuz, cod greșit sau expirat, semnătura medicului, detectarea modificărilor, permisiuni |
| `BookingTest` | 6 | intervale libere, dublă rezervare imposibilă, anulare, comutarea Digitail / intern |
| `AdminPanelTest` | 4 (38 de cazuri) | fiecare ecran se încarcă pentru Super Admin; restricții pe rol; vizitatorii și clienții nu intră |
| `WordPressImportTest` | 8 | prefix, probă fără scriere, rânduri identice cu sursa, lipsuri semnalate, repetabil, anulare, parola WordPress |
| `InstallerTest` | 5 | cod de acces, bază greșită, `.env`, blocare după instalare |
| `UpdateAndBackupTest` | 11 | semnătură, fișier modificat, Zip Slip, `.env` protejat, retrogradare, pachet incremental, revenire la eșec, backup criptat și restaurare, alte baze ignorate |
| `PublicSiteTest` | 10 | fiecare adresă WordPress răspunde, redirecturi, SEO, ciorne ascunse, contact, acord cookie-uri, antete, `.htaccess` |

`SimulatorParityTest` are nevoie de sursele pluginurilor în `source-wordpress/` (care nu sunt în
Git); fără ele se sare singur. Importul este testat pe o bază WordPress **sintetică**, construită
după schema din cod, cu prefixul `wpx7_`.

Pe MySQL / MariaDB:

```bash
php artisan test -c phpunit.mysql.xml
```

## 3. Verificări în browser (Chromium, automatizate)

| Scenariu | Rezultat |
|---|---|
| Configurator: specie, greutate, date, confirmări, semnătură, trimitere | contract `HV-AB-2026-00001`, PDF de 3 pagini |
| Instalator: cod greșit, cod corect, parolă de bază greșită, tabele, administrator | „Instalarea s-a încheiat”; `/install` → 404 |
| Autentificare în admin după instalare | panoul se deschide cu stiluri |
| Încărcarea unui pachet modificat | „Amprenta fișierului nu corespunde manifestului” |
| Încărcarea și instalarea pachetului 0.1.1 | 2 fișiere înlocuite, 1 nou, 1 șters, migrare rulată, versiune 0.1.1 |
| Capturi pe desktop (1440 px) și mobil (390 px) pentru paginile principale | verificate vizual |

## 4. Verificări pe Apache (cu `.htaccess`)

| Cerere | Răspuns |
|---|---|
| `/servicii/` | 200 |
| `/servicii`, `/radiografie` | 301 către varianta cu slash |
| `/asigurari/` | 301 → `/abonamente/` |
| `/shop/` | 301 → `/` |
| `/category/uncategorized/` | 301 → `/blog/` |
| `/.env`, `/.git/config` | 403 |
| `/vendor/autoload.php`, `/storage/logs/laravel.log`, `/composer.json` | 404 |
| `/uploads/x.php` | 403 |
| `/programare/` | 302 → Digitail |
| fișier CSS cu amprentă | 200, gzip, cache de un an |

## 5. Probleme găsite prin testare și reparate

| Problemă | Cum a fost găsită |
|---|---|
| Buclă de redirecturi pe Apache (regula `.htaccess` implicită contra slash-ului final) | recitirea `.htaccess` la scrierea ghidului; confirmat pe Apache |
| Lista de tabele includea alte baze vizibile aceluiași utilizator MySQL: backup eșuat | suita pe MariaDB cu două baze diferite |
| Tabelele create de o migrare eșuată rămâneau după revenire | testul cu pachet stricat |
| Pachetele complete (50 MB) depășesc limita obișnuită de încărcare | proba pe Apache cu limita implicită de 2 MB; rezolvat prin pachete incrementale și preluare din File Manager |
| Instalatorul cerea `bcmath`; limitarea de cereri folosea un tabel încă inexistent | instalarea curată |
| Panoul fără stiluri după instalare (asseturi lipsă din pachet) | instalarea curată |
| Coloană prea scurtă pe MariaDB (`quantity_label`) | suita pe MariaDB |
| Restaurarea backupului încălca cheile externe | suita pe MariaDB |
| Redirecturile cu mai multe segmente dădeau 404 | testul adreselor |
| Contrast insuficient la un buton din subsol | Lighthouse |

## 6. Implementat, parțial, în așteptare

| | |
|---|---|
| **Implementat și testat** | site public, configurator, contracte, cont client, Wallet și puncte, consimțământ, arhivă (pe date sintetice), programări, roluri, instalator, actualizări, backup |
| **Implementat, verificat doar manual** | editarea de conținut din admin, calendarul, rapoartele lunare pe ecran, biblioteca media |
| **Doar interfață sau configurare, fără integrare** | Google (se completează ID-uri; nicio conectare prin API), turul 3D (lipsește adresa) |
| **Așteaptă date** | importul real, reconcilierea cu rapoartele Primăriei, PDF-urile vechi ale contractelor |
| **Așteaptă cod** | importatoarele pentru notificări, jurnale și notițe; rapoarte PDF/XLSX pentru Primărie; șabloane de email editabile; SMS / WhatsApp; lista de prețuri editabilă; funcțiile consimțământului 1.5.x |

Detaliul pe funcții: `docs/FUNCTIONAL_PARITY_CHECKLIST.md`.
