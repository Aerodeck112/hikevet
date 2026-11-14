# Lista de paritate funcțională

Starea fiecărei funcții față de sistemul WordPress, la 14.11.2026.

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
| Plată: bonus cel mult 1/2 la servicii, 1/3 la produse | ✅ | |
| Excluderea automată și manuală a bonusului | ✅ | aceeași listă de cuvinte |
| Serviciu din abonament, pe puncte | ✅ | refuz la puncte insuficiente și la deparazitări |
| Ajustare manuală cu numele angajatului | ✅ | soldul nu devine negativ |
| Ștergere și restaurare cu motiv | ✅ | prin tranzacții inverse |
| Registru care nu poate fi modificat | ✅ | |
| Note ale medicilor | 🟡 | |
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

## 5. Sterilizări

| Funcție | Stare | Observații |
|---|---|---|
| Arhivă: listă, căutare, filtre, fișă completă | ✅ | pe date sintetice |
| Marcaj „Lipsă în sursă” | ✅ | |
| Fotografii și acte, cu amprentă | ✅ | pe fișiere sintetice |
| Raport lunar: centralizator și desfășurător | 🟡 | pe ecran și CSV |
| Export dosar (ZIP), listă (CSV), cu jurnal | ✅ | |
| Rapoarte PDF / XLSX în formatul Primăriei | ⬜ | logica de export din plugin nu a fost portată |
| Fluxul activ al campaniei | ⛔ | arhiva este istorică |
| Reparare, renumerotare, validare în masă, ștergere | ⛔ | ar modifica dovezi |

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
| Import WordPress | 🟡 | testat pe date sintetice; date reale: neverificat |
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
