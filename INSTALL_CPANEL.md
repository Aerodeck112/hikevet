# Instalare pe cPanel

Ghidul descrie instalarea HiKeVet Next dintr-un pachet ZIP, fără linie de comandă.

**Ce a fost verificat:** instalarea completă din pachet, în browser, pe Apache 2.4 + PHP 8.3
(mod_php) + MariaDB 10.11, cu `.htaccess` activ. **Ce nu a fost verificat:** un cont cPanel real
(LiteSpeed, CloudLinux, limitele găzduirii HiKeVet). Prima instalare trebuie făcută pe un
subdomeniu de probă, nu peste site-ul actual.

## 1. Cerințe

| Cerință | Valoare |
|---|---|
| PHP | 8.3 sau mai nou (cPanel → *Select PHP Version* / *MultiPHP Manager*) |
| Extensii PHP | pdo_mysql, mbstring, openssl, gd (cu WebP), zip, intl, fileinfo, dom, curl, sodium, ctype, json, tokenizer, xml, session, filter, hash |
| Bază de date | MySQL 8 sau MariaDB 10.6+ |
| HTTPS | certificat activ (AutoSSL / Let's Encrypt) înainte de instalare |
| Spațiu | aproximativ 250 MB pentru aplicație, plus documente și backupuri |
| Cron | o sarcină pe minut |

Instalatorul verifică singur toate acestea și spune exact ce lipsește.

## 2. Structura de directoare

Pachetul se dezarhivează **în afara** directorului public. Doar `hikevet/public` este vizibil din internet.

```
/home/UTILIZATOR/
├── hikevet/                 ← aplicația (privat)
│   ├── app/  config/  database/  resources/  routes/  vendor/
│   ├── storage/             ← contracte, semnături, backupuri, jurnale (privat)
│   ├── .env                 ← configurare și chei (privat)
│   └── public/              ← SINGURUL director servit de server
│       ├── index.php  .htaccess
│       ├── build/  media/  fonts/  js/  css/
│       └── uploads/         ← imaginile încărcate din admin
└── public_html/             ← site-ul WordPress actual; nu se atinge
```

## 3. Pași

### 3.1 Subdomeniu de probă

cPanel → *Domains* → *Create A New Domain*: de exemplu `nou.hikevet.com`. Debifează „Share document
root” și scrie la **Document Root**: `hikevet/public`. Așteaptă certificatul SSL.

### 3.2 Bază de date

cPanel → *MySQL Databases*: creează o bază nouă și goală, un utilizator cu parolă generată și
acordă-i *All Privileges* pe acea bază. Notează numele bazei, utilizatorul și parola. Nu folosi
baza WordPress.

### 3.3 Încărcarea pachetului

cPanel → *File Manager* → directorul de bază (`/home/UTILIZATOR`) → *Upload*
`hikevet-install-X.Y.Z.zip` → *Extract*. Rezultă directorul `hikevet/`.

### 3.4 Instalatorul

1. Deschide `https://nou.hikevet.com/`. Ești trimis la `/install`.
2. **Cod de acces.** Instalatorul cere un cod pe care îl găsești în File Manager, în
   `hikevet/storage/app/install-code.txt`. Astfel, cine nimerește adresa înaintea ta nu poate
   prelua instalarea.
3. **Verificarea serverului.** Fiecare cerință apare cu „în regulă” sau cu ce trebuie schimbat.
4. **Configurare.** Adresa site-ului, datele bazei de date și, opțional, SMTP. Conexiunea este
   testată înainte de a merge mai departe. O bază care nu este goală este refuzată.
5. **Tabele.** Se creează tabelele și se încarcă datele de bază: roluri, lista de prețuri și
   conținutul public. Nu se încarcă niciun client.
6. **Primul administrator.** Nume, email și o parolă de cel puțin 12 caractere, cu litere mari și
   mici, cifre și simboluri.

La final instalatorul se blochează: scrie `storage/app/installed.lock`, șterge codul de acces, iar
`/install` răspunde cu 404. Fișierul `.env` primește permisiunile 600.

### 3.5 Sarcina cron

cPanel → *Cron Jobs* → o sarcină **în fiecare minut** (`* * * * *`):

```bash
cd /home/UTILIZATOR/hikevet && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Calea către PHP 8.3 diferă între furnizori (de exemplu `/opt/cpanel/ea-php83/root/usr/bin/php`);
cPanel o afișează în pagina *Cron Jobs*. Această singură sarcină pornește: verificarea zilnică a
abonamentelor (07:30), backupul zilnic (02:15), trimiterea emailurilor din coadă și curățarea
linkurilor de parolă expirate. Nu este nevoie de un proces permanent.

Verificare: în admin, *Sistem → Actualizări și sănătate*, rândul „Sarcini programate (cron)”
trebuie să devină „în regulă” în câteva minute.

### 3.6 După instalare

1. Intră în `/admin` și activează autentificarea în doi pași din profil. Este disponibilă pentru
   tot personalul; **nu este impusă automat**, deci trebuie cerută fiecărui angajat.
2. *Sistem → Setări clinică*: date de contact, program, texte.
3. Dacă nu ai completat SMTP la instalare, emailurile se scriu doar în jurnal. Datele SMTP se pun
   în `.env` (`MAIL_*`), prin File Manager.
4. Pune cheia publică de actualizare în `.env` (vezi `UPDATES.md`).
5. Creează conturile personalului din *Sistem → Personal*.

## 4. Domeniul principal

Pe domeniul principal cPanel folosește `public_html` și de obicei nu permite schimbarea
directorului. Variante, în ordinea preferinței:

1. **Furnizorul schimbă document root** către `hikevet/public` (cerere la suport). Cea mai curată.
2. **Legătură simbolică:** `public_html` redenumit (păstrat ca rezervă) și înlocuit cu o legătură
   către `hikevet/public`. Cere Terminal sau SSH. *Netestat în acest proiect.*

Copierea conținutului din `public/` în `public_html` **nu este suportată**: actualizările scriu în
`hikevet/public` și site-ul ar rămâne cu fișiere vechi.

Comutarea domeniului principal se face abia după import, verificări și aprobare; pașii sunt în
`docs/IMPORT_RUNBOOK.md`. Până atunci WordPress rămâne neatins în `public_html`.

## 5. Probleme frecvente

| Simptom | Cauză probabilă | Rezolvare |
|---|---|---|
| Pagină albă sau eroare 500 imediat după dezarhivare | PHP mai vechi de 8.3 | Schimbă versiunea PHP pentru domeniu |
| „Drept de scriere: storage” | permisiuni | 755 pe directoare, 644 pe fișiere, proprietar = utilizatorul contului |
| Se vede lista de fișiere sau `.env` se descarcă | document root greșit | Document root trebuie să fie `hikevet/public` |
| Adminul apare fără stiluri | `public/js`, `public/css` lipsă | Dezarhivează din nou pachetul complet |
| Emailurile nu pleacă | SMTP necompletat sau cron lipsă | Vezi 3.5 și 3.6 |
| Pachetul de actualizare nu se încarcă | limita `upload_max_filesize` | Vezi `UPDATES.md`, „Pachet urcat prin File Manager” |

## 6. Dezinstalare

Șterge subdomeniul, directorul `hikevet/` și baza de date. WordPress nu este afectat de nimic din acest ghid.
