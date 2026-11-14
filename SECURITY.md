# Securitate și protecția datelor

Documentul descrie ce face platforma, ce rămâne în sarcina clinicii și ce limite are. Nu este un
audit extern și nu ține loc de consultanță juridică GDPR.

## 1. Acces

| Măsură | Implementare |
|---|---|
| Roluri | Super Admin, Administrator, Recepție, Medic, Editor, Client; permisiuni verificate pe server la fiecare ecran și acțiune |
| Parole | bcrypt; minimum 10 caractere pentru conturi, 12 cu simboluri pentru administratori; parolele apărute în scurgeri publice sunt refuzate |
| Doi pași (TOTP) | disponibil pentru personal, cu coduri de recuperare. **Nu este impus automat.** |
| Limitare încercări | autentificare 6/minut pe email și IP; formulare 5/minut; contract 4/minut; cod tabletă 8/minut |
| Sesiuni | cookie `Secure`, `HttpOnly`, `SameSite=Lax`; sesiune criptată; reînnoită la autentificare |
| Conturi dezactivate | nu pot intra nici în admin, nici în contul de client |
| Parole WordPress | la import rămâne doar hash-ul original; la prima autentificare reușită este înlocuit cu bcrypt |
| Client | vede doar propriile contracte, animale și tranzacții (testat: contractul altui client → 403) |

## 2. Date sensibile

| Date | Protecție |
|---|---|
| CNP, serie și număr CI, adresă (în contract) | criptate în baza de date (AES-256, cheia `APP_KEY`) |
| Conținutul consimțământului și semnăturile | criptate; amprentă SHA-256 a documentului semnat, verificabilă ulterior |
| Contracte PDF, semnături, documentele dosarelor | disc privat (`storage/app/private`), fără adresă publică; descărcare doar prin aplicație, cu verificarea dreptului și înregistrare în jurnal |
| Cod tabletă (6 cifre) | stocat doar ca HMAC; expiră (implicit 12 ore); blocat după 5 încercări greșite |
| Backupuri | criptate, în afara site-ului public |
| Chei, parole SMTP, baza de date | doar în `.env` (permisiuni 600), niciodată în Git sau în pachetele de actualizare |

Față de sistemul WordPress, aceste măsuri închid problemele din `docs/PLUGIN_AUDIT.md` §1.2:
contracte PDF publice, CNP în clar, cod de tabletă fără expirare, reducere obținută cu emailul altui client.

## 3. Integritate

- **Registrul Wallet și de puncte** se poate doar completa: orice corecție este o tranzacție
  nouă, cu autor și motiv. Soldul este mereu suma tranzacțiilor.
- **Jurnalul de audit** nu poate fi modificat sau șters din aplicație. Înregistrează autentificări,
  validări de plată, ajustări, descărcări de documente, exporturi, importuri și actualizări.
- **Arhiva de sterilizări** este doar pentru citire. Fiecare dosar păstrează rândul original
  complet și amprenta lui.
- **Un consimțământ semnat** nu mai poate fi editat; o corecție înseamnă un document nou care îl
  înlocuiește, cu originalul păstrat.
- **Prețurile** se calculează pe server; valorile trimise de browser sunt ignorate.

## 4. Aplicație web

- Protecție CSRF pe toate formularele; interogări parametrizate; ieșire escapată implicit.
- Textul din editorul de conținut este curățat (listă de etichete permise) înainte de afișare.
- Încărcări: doar JPEG, PNG și WebP, verificate după conținut și recodate; fără SVG. În
  `public/uploads` nu se execută scripturi.
- Antete: `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`,
  `Permissions-Policy`, HSTS pe HTTPS; paginile cu date personale au `Cache-Control: no-store`.
- Fișierele ascunse (`.env`, `.git`) sunt blocate și din `.htaccess`. Verificat pe Apache:
  `/.env` → 403, `/vendor/…` și `/storage/…` → 404.
- Instalatorul cere un cod citit din fișierele contului și se blochează definitiv după instalare.
- Actualizările sunt acceptate doar semnate (vezi `UPDATES.md`).

## 5. GDPR

| Subiect | Stare |
|---|---|
| Acorduri la abonament | cele 6 confirmări obligatorii, salvate cu dată, IP și versiunea documentelor |
| Marketing | acord separat, opțional, cu dată |
| Cookie-uri și măsurare | GTM / GA4 se încarcă doar după accept; refuzul este la fel de ușor ca acceptul |
| Minimizare | formularele cer doar câmpurile din contractul actual |
| Jurnal de acces la documente | da |
| Export și ștergere la cererea persoanei | **neimplementat ca funcție**; se poate face manual de către administrator |
| Termene de păstrare | **nedefinite**; trebuie stabilite de clinică (contracte, consimțăminte, dosare) |
| Politica de confidențialitate | **lipsește textul**; pagina actuală este goală |
| Semnătura desenată | este o semnătură electronică simplă, **nu una calificată** în sensul eIDAS; valoarea ei probatorie se sprijină pe dată, IP, amprentă și jurnal |

## 6. Limite cunoscute

1. **Fără Content-Security-Policy.** Adminul (Filament, Livewire) și etichetele de măsurare
   folosesc scripturi inline; o politică strictă cere lucru separat.
2. **Doi pași nu este obligatoriu** pentru personal.
3. **Fără scanare antivirus** a fișierelor încărcate (doar tip și recodare a imaginilor).
4. **Criptarea depinde de `APP_KEY`.** Pierderea cheii înseamnă pierderea datelor criptate;
   scurgerea ei, împreună cu baza de date, le expune.
5. **Nu a fost făcut un test de penetrare** și nici o verificare pe găzduirea reală.
6. **Repository-ul este public.** Nu conține chei, date reale sau pluginurile private, dar
   logica și tarifele sunt vizibile. Recomandare: repository privat.
7. **Site-ul WordPress actual rămâne vulnerabil** la problemele din audit până la oprirea lui.

## 7. Reguli de dezvoltare

- Niciun export SQL sau document real în Git, în capturi sau în teste; testele folosesc doar date inventate.
- În dezvoltare emailurile se scriu în jurnal (`MAIL_MAILER=log`); nu se trimit mesaje reale.
- Importul rulează implicit ca probă și refuză scrierea în producție fără `--force`.

## 8. Raportarea unei probleme

Problemele de securitate se anunță direct administratorului platformei, nu într-un tichet public.
