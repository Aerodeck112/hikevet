# Actualizări și backup

## Pe scurt

O actualizare este un fișier ZIP **semnat**. Platforma îl acceptă numai dacă semnătura corespunde
cheii publice de pe server și dacă fiecare fișier are amprenta din manifest. Înainte de a schimba
ceva, face backup; dacă un pas eșuează, pune versiunea veche la loc.

**Verificat pe o instalare reală de test** (Apache + MariaDB): actualizare 0.1.0 → 0.1.1 din
panou (cu migrare, fișier modificat și fișier șters), respingerea unui pachet modificat după
semnare, actualizare 0.1.1 → 0.1.2 din linia de comandă și un pachet cu migrare stricată
intenționat, care a fost anulat automat cu site-ul rămas funcțional pe 0.1.2.

## 1. Cheile de semnare (o singură dată)

Pe calculatorul de unde se publică versiunile, **nu pe server**:

```bash
php artisan hikevet:release keygen
```

Comanda scrie cheia privată în `storage/app/release-signing.key` și afișează cheia publică.

- **Cheia privată** rămâne offline, cu o copie de siguranță. Nu se urcă în Git, pe server sau în
  chat. Cine o are poate publica actualizări acceptate de site.
- **Cheia publică** se pune pe server, în `.env`:

```
HIKEVET_UPDATE_PUBLIC_KEY="…valoarea afișată…"
```

Fără cheia publică, platforma refuză orice pachet. Dacă cheia privată se pierde sau este
compromisă: se generează o pereche nouă și se înlocuiește cheia publică din `.env`.

## 2. Construirea unui pachet

1. Crește versiunea în `config/hikevet.php` și notează modificările în `CHANGELOG.md`.
2. Rulează testele (`php artisan test`).
3. Într-o copie curată a codului, cu dependențele de producție (`composer install --no-dev`):

Pachet incremental (recomandat; conține doar fișierele schimbate):

```bash
php artisan hikevet:release build --since=hikevet-install-0.1.0.manifest.json --changelog="Ce s-a schimbat"
```

Pachet complet (orice versiune mai veche se poate actualiza cu el; aproximativ 50 MB):

```bash
php artisan hikevet:release build --changelog="Ce s-a schimbat"
```

Fiecare construire scrie lângă ZIP un fișier `.manifest.json`: lista fișierelor versiunii, cu
amprente. El este baza (`--since`) pentru următorul pachet incremental, deci se păstrează.

Un pachet incremental se instalează **numai** peste versiunea față de care a fost construit și
șterge de pe server fișierele care nu mai există în versiunea nouă.

Pachetul de instalare inițială se construiește cu:

```bash
php artisan hikevet:release install-zip
```

## 3. Instalarea unei actualizări

**Din panou** (*Sistem → Actualizări și sănătate*, doar Super Admin):

1. „Încarcă pachet de actualizare”. Pachetul este doar verificat; apar versiunea, numărul de
   fișiere, amprenta SHA-256 și lista de verificări.
2. „Instalează actualizarea” și confirmă.

**Pachet urcat prin File Manager.** Dacă găzduirea limitează încărcarea prin browser
(`upload_max_filesize`), pune ZIP-ul în `hikevet/storage/app/private/updates/`; în aceeași pagină
apare butonul „Pachet urcat prin File Manager”. Verificările sunt aceleași.

**Din linia de comandă** (Terminal cPanel sau SSH):

```bash
php artisan hikevet:update storage/app/private/updates/hikevet-update-0.1.1.zip
```

Cu `--check` pachetul este doar verificat.

## 4. Ce se întâmplă la instalare

| Pas | Detaliu |
|---|---|
| Verificare | semnătură Ed25519, amprenta fiecărui fișier, fără fișiere în plus, versiune mai nouă, PHP, spațiu, drepturi de scriere, nicio altă actualizare în curs |
| Backup | baza de date (criptat, recitit pentru verificare) și fiecare fișier care urmează să fie înlocuit sau șters |
| Mentenanță | vizitatorii văd pagina de mentenanță |
| Copiere | fiecare fișier este scris temporar, verificat și abia apoi redenumit |
| Migrări | doar dacă pachetul conține fișiere de migrare |
| Final | golire cache, verificarea bazei de date, ieșire din mentenanță |

Un pachet nu poate atinge `.env`, `storage/` sau `public/uploads/`, nici măcar semnat corect, și
nu poate scrie în afara directoarelor aplicației (căi cu `..`, absolute sau necunoscute sunt
refuzate). Sunt refuzate și arhivele cu dimensiuni anormale.

## 5. Dacă ceva eșuează

Automat: fișierele vechi sunt puse la loc, fișierele noi sunt șterse, tabelele create de o
migrare eșuată sunt șterse, datele revin din backupul făcut la început, site-ul iese din
mentenanță. Raportul complet rămâne în „Istoricul actualizărilor”.

**Limită cunoscută:** dacă o migrare apucă să *modifice* un tabel existent (coloană ștearsă sau
redenumită) și eșuează după aceea, structura acelui tabel nu poate fi refăcută automat. Regulă
pentru cine publică versiuni: migrările din pachete adaugă (tabele, coloane), nu șterg și nu
redenumesc, și se probează întâi pe o copie a bazei reale.

Restaurare manuală a bazei de date:

```bash
php artisan hikevet:backup --restore=NUME-FISIER.hvbackup
```

Fișierele înlocuite la fiecare actualizare stau în `storage/app/backups/update-DATA-VERSIUNE/files/`.

## 6. Backup

| | |
|---|---|
| Automat | zilnic la 02:15 (prin cron), ultimele 14 păstrate |
| Manual | butonul „Backup acum” sau `php artisan hikevet:backup --label=manual --verify` |
| Loc | `storage/app/backups/*.hvbackup`, în afara site-ului public |
| Format | toate tabelele, comprimat și criptat (XChaCha20-Poly1305) cu o cheie derivată din `APP_KEY` |

De reținut:

1. **Backupul conține baza de date, nu și fișierele.** Contractele PDF, semnăturile și documentele
   din `storage/app/private`, plus imaginile din `public/uploads`, trebuie salvate separat
   (backupul complet din cPanel sau JetBackup).
2. **Backupul stă pe același server.** Descarcă periodic o copie în alt loc.
3. **Fără `APP_KEY` backupurile nu pot fi citite**, la fel CNP-urile și semnăturile criptate din
   bază. Păstrează o copie a fișierului `.env` într-un loc sigur, separat de backupuri.
4. Restaurarea a fost testată automat (date șterse, apoi readuse) pe SQLite și MariaDB.
