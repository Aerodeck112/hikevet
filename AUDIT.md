# Audit HiKeVet: site public și surse

Data: 12–14.11.2026. Ce este scris aici a fost verificat direct; ce nu a putut fi verificat este
marcat ca atare. Auditul pluginurilor este separat, în [`docs/PLUGIN_AUDIT.md`](docs/PLUGIN_AUDIT.md).

## 1. Ce am avut la dispoziție

| Sursă | Stare |
|---|---|
| Site-ul public `https://hikevet.com` | Accesibil. Descărcate 50 de pagini, 45 de foi de stil Elementor și 80 de imagini. |
| Cele patru pluginuri (ZIP) | Primite și citite. Stau local în `source-wordpress/`, în afara Git (repository-ul este public). |
| Export SQL | **Lipsește.** |
| `wp-content/uploads` (fotografii pacienți, acte, contracte PDF) | **Lipsește.** Am doar imaginile publice folosite în pagini. |
| Acces WordPress Admin, cPanel, conturi Google, Digitail | **Lipsește.** Nimic nu a fost modificat în producție. |

## 2. Platforma actuală (din codul paginilor)

WordPress 7.0.8, Elementor 4.3.2, tema Hello Elementor, Header Footer Elementor, Essential Addons,
WooCommerce 11.0.1 (magazin gol, paginile `/shop/`, `/cart/`, `/checkout/`, `/my-account/` sunt în
sitemap), Site Kit by Google 1.188.0, LiteSpeed Cache (menționat în mesajele pluginurilor).

Integrări găsite:

- **Google Tag Manager** `GTM-T65S32C`, încărcat pe toate paginile fără un mecanism de consimțământ vizibil.
- **Digitail** pentru programări: `https://vet.digitail.io/clinics/clinica-veterinara-hikevet`, 46 de linkuri.
- **Google Maps** (iframe) pe pagina de contact și în subsol.
- **Captur3D** pentru turul 3D (cod de inserare încărcat prin shortcode; adresa nu apare în HTML-ul static).
- Linkuri ANPC SAL și SOL în subsol; link către recenzii Google.

Nu am găsit integrare Twilio, WhatsApp sau SMS în paginile publice. Pluginurile trimit doar email.

## 3. Inventar de pagini

Sitemap-ul WordPress listează 46 de pagini, 4 articole, o categorie și 4 pagini de autor.

| Grup | Adrese | În HiKeVet Next |
|---|---|---|
| Prima pagină | `/` | Păstrată |
| Servicii | `/servicii/` + 23 de pagini de serviciu la prim nivel (`/radiografie/`, `/ecografie/` …) | Toate păstrate cu aceeași adresă |
| Abonamente | `/abonamente/` (conține configuratorul) | Păstrată |
| Echipă, contact, întrebări, blog | `/echipa/`, `/hikevet-contact/`, `/intrebari-frecvente/`, `/blog/`, 4 articole | Păstrate |
| Video Vet, Tur 3D | `/video-vet/`, `/tur-3d/` | Păstrate; turul 3D așteaptă adresa de inserare |
| Documente legale | `/termeni-si-conditii-abonamente/`, `/contract-abonament/`, `/anexa-abonamente/`, `/ghid-proprietar/` | Păstrate, cu versiune de document |
| Politica de confidențialitate | `/politica-de-confidentialitate/` | **Pagina actuală este goală.** Importată ca ciornă, nepublicată |
| Cont | `/acces-cont/`, `/panou-cont/`, `/inregistrare-cont/` | Primele două păstrate; a treia redirecționată |
| Tabletă | `/consimtamant-tableta/` | Păstrată |
| WooCommerce și altele | `/shop/`, `/cart/`, `/checkout/`, `/my-account/`, `/thank-you-page/`, categorie, feed | Redirecturi 301 |

Meniul principal: Servicii, Abonamente, Despre Noi, Contact, Q&A, Panou Cont, Acces cont. Trei
intrări folosesc adrese de forma `/?page_id=N`; acestea sunt redirecționate către adresele curate.

## 4. Identitate vizuală măsurată

Valorile sunt cele calculate de browser pe site-ul actual (capturi desktop 1440 px și mobil 390 px).

| Rol | Valoare |
|---|---|
| Verde principal | `#21B073` |
| Portocaliu accent | `#FF5B2E` |
| Galben | `#FAC44D` |
| Verde pal (fundal) | `#ECF2E3` |
| Albastru deschis | `#BEDCF5` |
| Text titluri | `#353535` |
| Text curent | `#777777` |

Fonturi: Bowlby One (titluri), Jost (text), Nothing You Could Do (accent de mână), Boogaloo
(izolat). Formă recurentă: fotografii decupate în capsulă pe fundal colorat.

**Problemă găsită:** Bowlby One și Nothing You Could Do nu conțin literele românești ă, ș, ț.
Pe site-ul actual acestea sunt desenate cu un font de rezervă, deci titlurile amestecă două fonturi.
Vezi [`docs/DESIGN_SYSTEM.md`](docs/DESIGN_SYSTEM.md) pentru soluția aleasă.

## 5. Conținut și imagini

- Textele tuturor paginilor au fost extrase automat și stau în `database/seeders/data/site-content.json`.
- Echipa numită pe site: Dr. Radu Feșteu (cu biografie) și Dregics Szidonia (doar nume și funcție).
  O a treia fotografie din zona „Personalul HiKeVet” este o imagine de stoc fără nume; nu a fost
  tratată ca membru al echipei.
- 19 logouri de parteneri, 6 fotografii din cabinet (legate de Instagram), 2 portrete de studio.
- Multe fotografii sunt de stoc (nume de fișier Pexels, Envato). Sunt marcate în biblioteca media
  cu nota „licența trebuie confirmată”.
- Cifrele „20+ experiență” și „1000+ clienți mulțumiți” sunt preluate de pe site, neverificate.
- Programul clinicii nu apare nicăieri pe site; nu a fost inventat și nu apare în datele structurate.

## 6. Probleme găsite pe site-ul actual

| # | Problemă | Dovadă | Ce am făcut |
|---|---|---|---|
| 1 | Abonamentul este numit „asigurare” în 61 de locuri, deși contractul spune că nu este asigurare | [`docs/generated/flagged-texts.md`](docs/generated/flagged-texts.md) | Formulările nu au fost preluate. Lista așteaptă aprobare editorială și juridică. |
| 2 | Pagina de abonamente promite „deparazitări” la prevenție, dar din v2.7.0 ele sunt excluse | pagina `/abonamente/` vs README simulator | Noua pagină afișează serviciile reale din lista de prețuri. |
| 3 | Politica de confidențialitate este goală, deși formularul de abonament cere acordul pentru ea | pagina `/politica-de-confidentialitate/` | Ciornă nepublicată. **Clinica trebuie să furnizeze textul.** |
| 4 | Paginile de servicii trimit la `/asigurari/`, care nu există | 23 de pagini | Redirect 301 către `/abonamente/`. |
| 5 | Fără meta description, fără date structurate | toate paginile | Adăugate. |
| 6 | README-urile pluginurilor private sunt publice | `/wp-content/plugins/<plugin>/README.txt` | Semnalat; dispare odată cu WordPress. |
| 7 | Contractele PDF cu CNP și CI au adrese publice | `docs/PLUGIN_AUDIT.md` §1.2 | În platforma nouă stau pe disc privat. **Pe site-ul actual riscul rămâne până la oprire.** |
| 8 | GTM se încarcă fără acord | sursa paginilor | Încărcare doar după accept. |

## 7. Existent / de portat / necesită sursa / propunere nouă

| Funcție | Existent în WordPress | Stare în HiKeVet Next |
|---|---|---|
| Pagini publice, servicii, echipă, blog, întrebări | Da | **Portat**, cu conținutul real |
| Design | Elementor | **Refăcut** pe identitatea existentă (decizia clientului din 12.11) |
| Configurator abonamente în 6 pași | Da (simulator 2.7.0) | **Portat și testat** față de codul original |
| Contract PDF cu semnătură | Da | **Portat**; stocare privată, amprentă SHA-256 |
| Cont client, animale, contracte | Da (Wallet Core) | **Portat** |
| Puncte, Wallet promoțional, tratamente | Da (Wallet Core 3.1.0) | **Portat și testat** |
| Panou medici | Da | **Portat** ca fișă de client în admin; graficele din v3.1 nu sunt portate |
| Consimțământ pe tabletă | Da (1.4.1 livrat; 1.5.2 pare în producție) | **Portat** din 1.4.1; diferențele față de 1.5.2 **necesită sursa** |
| Campanie sterilizare (flux activ) | Da (2.7.6) | **Neportat intenționat**: doar arhivă istorică |
| Arhivă sterilizări | Date în `hv_st_*` | **Pregătit**: schemă, import, ecrane; **necesită exportul SQL** |
| Rapoarte Primărie PDF/XLSX | Da | Parțial: centralizator și desfășurător pe ecran și CSV. PDF/XLSX identice **neportate** |
| Programări | Digitail (extern) | Digitail păstrat. Sistem propriu **nou**, dezactivat public până la comutare |
| Emailuri automate | `wp_mail` | **Portat** cu jurnal de livrare; trimiterea reală cere SMTP |
| SMS / WhatsApp | Nu există | Neimplementat |
| Administrare conținut | Elementor | **Nou**: CMS cu versiuni |
| Roluri și 2FA | 2 roluri WordPress | **Nou**: 6 roluri, permisiuni, 2FA pentru personal |
| Jurnal de audit | Parțial (Wallet) | **Nou**, pe toată platforma |
| Google (GA4, GTM, Search Console) | Site Kit | ID-uri configurabile și acord; **fără conectare prin API** |
| Instalator cPanel | – | **Nou**, testat pe MariaDB |
| Actualizări ZIP | – | **Nou**, semnate, cu revenire |
| Backup | – | **Nou**, criptat, restaurare testată |
| Import WordPress | – | **Nou**, testat doar pe date sintetice |

## 8. Riscuri majore

1. **Importul nu a fost rulat pe date reale.** Schemele vin din cod, dar baza reală poate avea
   coloane sau valori neașteptate. Proba (dry-run) le raportează fără să scrie nimic.
2. **Modulul de consimțământ din producție pare mai nou decât sursa primită.** Datele create de
   1.5.x pot avea câmpuri pe care importul le păstrează, dar ecranele nu le afișează separat.
3. **Fluxul activ al campaniei de sterilizare nu există în platforma nouă.** Dacă mai este
   folosit zilnic, WordPress trebuie păstrat pentru el până la o decizie separată.
4. **Repository-ul este public.** Codul platformei, inclusiv tarifele, este vizibil. Pluginurile
   private nu au fost urcate.
5. **Găzduirea nu a fost verificată.** Laravel 13 cere PHP 8.3+. Instalatorul verifică și blochează
   dacă versiunea este mai veche; alternativa ar fi Laravel 12 (PHP 8.2), cu adaptări.
6. **Formulări juridice.** Textele „asigurare” și politica de confidențialitate goală cer decizia clinicii.
