# Raport de testare

Data: 17.11.2026 (actualizat după portarea campaniei de sterilizare) · versiunea 0.1.0 · PHP 8.3.6 · Laravel 13.42

## 1. Rezumat

| Ce | Rezultat |
|---|---|
| Teste automate pe SQLite | 199 de teste, 198 trecute, 1 sărit (relevant doar pe MySQL), peste 18 000 de verificări |
| Teste automate pe MariaDB 10.11 | 199 din 199 trecute |
| Import complet al backupului real (copie de probă, nu producția) | 49 de conturi, 37 de animale, 61 de contracte, 91 de tranzacții, 456 de consimțăminte, 713 dosare de sterilizare, arhiva brută integrală |
| Raport Primărie pe datele reale de probă | iulie 104, august 123, septembrie 137 (+ selecția fixă de 145 confirmată 145/145), octombrie 100 de intervenții; Excel, PDF, centralizator și ZIP generate fără erori |
| ZIP Primărie pe toată perioada (469 de dosare) | 627 MB, ~150 s, maximum 319 MB memorie; 4 acte de identitate absente din backup marcate „Lipsă în sursă” |
| Instalare curată din ZIP, în browser, pe Apache 2.4 + MariaDB | reușită |
| Actualizare reală din panou (0.1.0 → 0.1.1) | reușită |
| Actualizare din linia de comandă (0.1.1 → 0.1.2) | reușită |
| Pachet modificat după semnare | respins |
| Pachet cu migrare stricată (0.1.2 → 0.1.3) | anulat automat; site funcțional pe 0.1.2 |
| Lighthouse pe 7 pagini | accesibilitate, bune practici și SEO 100; performanță 87–100 |

**Ce nu a fost testat:** comutarea propriu-zisă și lucrul zilnic al personalului pe date reale, un cont cPanel real, trimiterea
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
| `WordPressImportTest` | 15 | prefix, probă fără scriere, rânduri identice cu sursa, lipsuri semnalate, repetabil, anulare, parola WordPress, istoricul, plățile din registru, legăturile campaniei, lista vizitei și variantele corectate ale consimțămintelor, refuzul unei copii cu diacritice codate de două ori |
| `CampaignFlowTest` / `CampaignScreensTest` | 13 / 8 | fluxul campaniei: programare multipacient, recepție, consult, intervenție, externare, numerotare, consimțământ corectat, emailuri, reminder, disponibilitate, ștergere cu motiv, ecrane și permisiuni |
| `CampaignReportsTest` | 13 | luna de raportare fixată, octombrie cu excluderi, lista de 145, Excel oficial (25 de coloane, fără statusuri interne), centralizator, audit (dubluri, poze reutilizate, documente lipsă, pacient adăugat după semnare), ZIP general și pe dosar, confirmarea pozelor, registrul, alinierea, validarea în masă, permisiunile de export |
| `WpressExtractCommandTest` | 1 | fișierul SQL extras declară `utf8mb4` și are prefixul real |
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
| **Implementat și testat** | site public, configurator, contracte, cont client, Wallet și puncte, consimțământ, campania de sterilizare cu rapoartele pentru Primărie (și pe datele reale de probă), programări, roluri, instalator, actualizări, backup |
| **Implementat, verificat doar manual** | editarea de conținut din admin, calendarul, rapoartele lunare pe ecran, biblioteca media |
| **Doar interfață sau configurare, fără integrare** | Google (se completează ID-uri; nicio conectare prin API), turul 3D (lipsește adresa) |
| **Așteaptă clinica** | numerele trimise efectiv Primăriei în iulie–septembrie (pentru reconciliere), confirmarea abaterilor C1–C8, SMTP |
| **Așteaptă cod** | șabloane de email editabile; SMS / WhatsApp; lista de prețuri editabilă; diferențele consimțământului 1.5.2 |

Detaliul pe funcții: `docs/FUNCTIONAL_PARITY_CHECKLIST.md`.
