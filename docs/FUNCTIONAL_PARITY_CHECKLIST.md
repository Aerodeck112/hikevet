# Lista de paritate funcțională

Starea fiecărei funcții față de sistemul WordPress, la 17.11.2026 (secțiunea 5 actualizată după portarea campaniei active, modulul 2.7.6).

| Semn | Înseamnă |
|---|---|
| ✅ | implementat și acoperit de teste automate |
| 🟡 | implementat, verificat doar manual sau parțial |
| 🔶 | abatere deliberată față de plugin; **cere aprobarea clinicii** |
| ⬜ | neimplementat |
| ⛔ | neportat intenționat |

## 1. Abonamente

| Funcție | Stare | Observații |
|---|---|---|
| Categorii de greutate și tarife (1 / 6 / 12 luni) | ✅ | identice cu pluginul la fiecare pas de 0,01 kg |
| Reducere pentru al 2-lea, al 3-lea, al 4-lea animal și 100% la aceeași categorie | ✅ | toate combinațiile comparate cu pluginul |
| Servicii incluse pe categorie, totaluri fixe | ✅ | fără deparazitări |
| Cele 6 confirmări obligatorii | ✅ | salvate cu dată și versiunea documentelor |
| Semnătură desenată | ✅ | |
| Contract PDF | ✅ | același text (50 de linii, fără diacritice, ca în plugin); numele introduse apar corect cu diacritice |
| Protecție la dublă trimitere | ✅ | |
| Emailuri către clinică și client | ✅ | trimitere reală doar cu SMTP configurat |
| Plata la clinică, validată de personal | ✅ | |
| Puncte = preț lunar × luni | ✅ | reducerea de perioadă nu micșorează punctele |
| Notificare înainte de expirare (5 zile) | ✅ | o singură dată per expirare |
| Reducerea multi-animal pentru vizitator, după email | 🔶 A1 | doar clientul autentificat o primește |
| Abonament per animal, cu expirare proprie | 🔶 A2 | în plugin: o singură expirare per client |
| Reînnoire doar în ultimele 7 zile, prelungire de la data expirării | 🔶 A3 | în plugin: oricând, de la data plății |
| Oferta neplătită expiră automat după 5 zile | 🔶 A4 | în plugin era doar text în contract |
| Stări noi: suspendat, anulat | 🔶 A5 | cu istoric și motiv |
| Greutate maximă pisică 70 kg și pe server | 🔶 A6 | pluginul accepta 999 kg pe server |
| Listă de prețuri nouă din admin | ⬜ | ecranul este doar de vizualizare; prețurile se schimbă printr-o actualizare |

## 2. Cont client

| Funcție | Stare | Observații |
|---|---|---|
| Cont creat automat la contract, cu link de activare | ✅ | |
| Autentificare, parolă uitată | ✅ | |
| Autentificare cu parola din WordPress după import | ✅ | |
| Animale, contracte, descărcare contract | ✅ | doar ale clientului |
| Sold de puncte și Wallet, istoric | ✅ | |
| Cerere de alimentare Wallet | ✅ | nu modifică soldul |
| Profil, acord de marketing | 🟡 | |
| Notificări în cont | 🟡 | |
| Înregistrare fără contract | 🟡 | |

## 3. Wallet și puncte (personal)

| Funcție | Stare | Observații |
|---|---|---|
| Alimentare: X principal + X bonus servicii + X/2 bonus produse | ✅ | doar cu abonament activ |
| Alimentare și plata abonamentului online cu cardul (Stripe) | ✅ nou | testat cu Stripe simulat; factura se emite manual și se trece în *Plăți online* (`docs/PLATI_ONLINE.md`) |
| Plată: bonus cel mult 1/2 la servicii, 1/3 la produse | ✅ | |
| Excluderea automată și manuală a bonusului | ✅ | aceeași listă de cuvinte |
| Serviciu din abonament, pe puncte | ✅ | refuz la puncte insuficiente și la deparazitări |
| Ajustare manuală cu numele angajatului | ✅ | soldul nu devine negativ |
| Ștergere și restaurare cu motiv | ✅ | prin tranzacții inverse |
| Registru care nu poate fi modificat | ✅ | |
| Note ale medicilor | ✅ | inclusiv cele importate, cu autorul |
| Graficele din Wallet Core 3.1.0 | ⬜ | |
| Categorie la recalculare cu regula `(min, max]` | 🔶 A7 | se folosește peste tot regula din contract `[min, max)` (N1) |

## 4. Consimțământ pe tabletă

| Funcție | Stare | Observații |
|---|---|---|
| Creare de către personal, toate câmpurile din 1.4.1 | ✅ | |
| Deschidere pe tabletă cu cod de 6 cifre | ✅ | |
| Confirmările finale, inclusiv GDPR separat | ✅ | |
| Semnătură de refuz pentru serviciile refuzate | ✅ | |
| Semnătura medicului, ulterior | ✅ | |
| Vizită cu mai mulți pacienți | ✅ | |
| Tipărire | ✅ | |
| Codul expiră (12 ore) și se blochează după 5 greșeli | 🔶 A8 | în plugin nu expira |
| Corecție = document nou; originalul rămâne | 🔶 A9 | în plugin se putea edita |
| Amprentă de integritate | ✅ | nou |
| Funcțiile din 1.5.x | ⬜ | necesită sursa |

## 5. Campania de sterilizare (modulul 2.7.6)

**Campania s-a încheiat și este oprită implicit: rămâne doar ca istoric.** Nu se trimit emailuri, nu se fac
programări sau dosare noi și nu se încarcă fotografii; dosarele și rapoartele pentru Primărie rămân
disponibile (*Setări clinică → Campanie sterilizare* o poate reporni). Funcțiile de mai jos sunt portate și
testate, dar cele de lucru zilnic nu mai sunt folosite. Rândul original din
WordPress rămâne neschimbat lângă fiecare dosar (`source_row` + amprentă SHA-256); starea curentă se
lucrează separat și orice modificare intră în istoricul dosarului.

| Funcție | Stare | Observații |
|---|---|---|
| Dosare: listă, căutare, filtre (status, completitudine, vizite multipacient) | ✅ | |
| Dosar pe file: recepție, consult preoperator, intervenție, externare, validare, corectare, rezumat, istoric | ✅ | aceleași câmpuri și reguli de salvare pe secțiuni |
| Coduri `HV-ST-AAAA-NNN`, numerotare pacient unică pe an, protecția numerelor manuale și a celor cu fotografie | ✅ | regulile v2.5.7 / v2.6.4 |
| Fotografie de pe telefon prin cod QR | ✅ | link cu token aleatoriu |
| Vizite cu mai multe animale: CI și consimțământ comune | ✅ | |
| Consimțământ creat automat, cod pentru tabletă, variantă corectată la schimbarea identității | ✅ | |
| Checklist Primărie (21 de puncte), validare | ✅ | |
| Calendar săptămânal, programare telefonică multipacient, disponibilitate, anulare | ✅ | |
| Emailuri: confirmare, reminder cu o zi înainte, rezumat la externare, follow-up la 48 h, rezumat actualizat | ✅ | texte identice; trimise doar cu SMTP configurat |
| Raport Primărie: lună de raportare fixată, octombrie 2026 cu excluderea celor 347 de coduri, selecția fixă de 145 | ✅ | |
| Desfășurător Excel (25 de coloane oficiale) și PDF, centralizator PDF (Câine/Cățea/Motan/Pisică) | ✅ | verificat și pe datele reale de probă |
| ZIP Primărie cu toate intervențiile finalizate (fișă pacient, foto, consimțământ semnat, CI, LIPSA/EROARE, manifest) și ZIP pe dosar | ✅ | proba reală: septembrie 137 dosare în ~1 min; toată perioada 469 dosare, 627 MB, ~2,5 min |
| Audit integritate, reparare automată, consimțământ corectat pentru resemnare, validare în masă | ✅ | |
| Control numere după fotografie (individual și în masă), registru 1 → N, aliniere, reconciliere iulie–septembrie (PDF, CSV) | ✅ | |
| Arhiva WordPress a campaniei (rândurile originale, emailurile vechi) | ✅ | doar citire |
| Lista „documente lipsă” Excel | ⛔ | funcția exista în plugin, dar nu era folosită nicăieri |

### Abateri ale campaniei față de plugin

| # | Abatere | De ce |
|---|---|---|
| C1 | Ștergerea unui dosar îl marchează șters, cu motiv; datele, fișierele și istoricul rămân | în plugin ștergerea era definitivă |
| C2 | Identitatea pacientului se compară cu documentul semnat, nu cu lista vizitei actualizată după semnare; auditul arată „Pacient adăugat în vizită după semnare” | în plugin un animal adăugat ulterior trecea drept acoperit de un consimțământ pe care proprietarul nu l-a semnat pentru el (pe datele reale de probă: 17 dosare) |
| C3 | Follow-up-ul automat se reîncearcă de cel mult 3 ori | în plugin reîncercările nu se opreau |
| C4 | Antetul desfășurătorului Excel spune „Toate intervențiile finalizate din perioada selectată” | pluginul scria „cu documente obligatorii”, deși includea toate intervențiile |
| C5 | Fotografia reutilizată se recunoaște după conținutul fișierului, nu după adresă | o copie a aceleiași poze sub alt nume nu mai scapă controlului |
| C6 | Dublurile și lipsa numărului în audit se verifică pe numărul din raport | pluginul amesteca numărul operațional cu cel din raport |
| C7 | Numele folderelor din ZIP fără diacritice | se deschid corect și pe Windows |
| C8 | Exporturile cu date personale cer dreptul de export (medici, administratori); recepția vede raportul, confirmă pozele și repară, dar nu descarcă și nu validează | în plugin orice utilizator cu acces la modul putea descărca tot |

## 6. Programări

| Funcție | Stare | Observații |
|---|---|---|
| Link către Digitail | ✅ | mod implicit |
| Program pe medic, blocări, intervale de 15 minute | ✅ | |
| Fără dublă rezervare | ✅ | garantat de baza de date |
| Cerere publică, confirmare, anulare cu link privat | ✅ | activ doar în modul „intern” |
| Calendar în admin | 🟡 | |
| Emailuri de confirmare | 🟡 | |
| Memento înainte de programare, SMS, WhatsApp | ⬜ | |
| Sincronizare cu Google Calendar sau Digitail | ⬜ | |

Cele două moduri nu funcționează simultan: comutarea se face dintr-o singură setare.

## 7. Site și conținut

| Funcție | Stare | Observații |
|---|---|---|
| Toate paginile, cu aceleași adrese | ✅ | |
| Redirecturi 301 | ✅ | verificate și pe Apache |
| Editare pagini, servicii, echipă, blog, întrebări | 🟡 | ecranele se încarcă pentru fiecare rol (testat); editarea verificată manual |
| Versiuni de pagină și revenire | 🟡 | |
| Bibliotecă media cu optimizare | 🟡 | |
| Formular de contact | ✅ | |
| SEO: meta, canonical, date structurate, sitemap | ✅ | |
| Acord cookie-uri | ✅ | |
| Tur 3D | ⬜ | lipsește adresa de inserare |
| Politica de confidențialitate | ⬜ | lipsește textul |
| Editor vizual de tip Elementor | ⛔ | conținutul se editează pe câmpuri |

## 8. Administrare și operare

| Funcție | Stare | Observații |
|---|---|---|
| 6 roluri, permisiuni pe ecrane și acțiuni | ✅ | |
| Autentificare în doi pași | 🟡 | disponibilă, neimpusă |
| Jurnal de audit | ✅ | |
| Instalator cPanel | ✅ | și instalare reală pe Apache + MariaDB |
| Actualizări ZIP semnate, cu revenire | ✅ | și actualizări reale pe instalarea de test |
| Backup criptat și restaurare | ✅ | doar baza de date |
| Import WordPress | 🟡 | testat pe date sintetice, inclusiv din backup `.wpress`; date reale: neverificat |
| Arhiva brută a bazei WordPress | ✅ | toate tabelele, criptat, consultabilă și exportabilă din admin |
| Șabloane de email editabile | ⬜ | textele sunt în cod |
| Conectare Google prin API | ⬜ | doar ID-uri de măsurare |
| Export / ștergere date la cererea persoanei | ⬜ | |

## Abateri care cer aprobare

| # | Abatere | De ce |
|---|---|---|
| A1 | Reducerea multi-animal doar pentru clientul autentificat | în plugin, oricine știa emailul unui client obținea reducerea și adăuga un animal în contul lui |
| A2 | Abonament per animal | în plugin al doilea contract nu putea fi validat separat (N6) |
| A3 | Reînnoire în ultimele 7 zile, de la data expirării | clientul nu pierde zile plătite |
| A4 | Oferta neplătită expiră automat | contractul spune deja 5 zile |
| A5 | Suspendare și anulare | lipseau |
| A6 | Limită de 70 kg la pisică și pe server | corectează N2 |
| A7 | O singură regulă pentru limitele de greutate | corectează N1 |
| A8 | Cod de tabletă cu expirare și limită de încercări | codul vechi era valabil nelimitat |
| A9 | Consimțământul semnat nu se editează | păstrează valoarea de probă |

Dacă o abatere nu este acceptată, comportamentul vechi poate fi readus; A1 și A8 sunt însă
corecții de securitate.
