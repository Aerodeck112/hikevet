# Migrarea SEO

Scopul: la comutarea de pe WordPress, nicio adresă indexată să nu se piardă.

## 1. Adrese

Toate adresele publice din sitemap-ul WordPress își păstrează forma exactă, inclusiv slash-ul final.

| Tip | Exemplu | În HiKeVet Next |
|---|---|---|
| Prima pagină | `/` | identic |
| Servicii (23) | `/radiografie/`, `/ecografie/` | identic, la primul nivel |
| Pagini | `/servicii/`, `/abonamente/`, `/echipa/`, `/hikevet-contact/`, `/intrebari-frecvente/`, `/video-vet/`, `/tur-3d/` | identic |
| Blog | `/blog/` și 4 articole la primul nivel | identic |
| Documente | `/termeni-si-conditii-abonamente/`, `/contract-abonament/`, `/anexa-abonamente/`, `/ghid-proprietar/` | identic |
| Cont | `/acces-cont/`, `/panou-cont/` | identic (neindexate) |

Varianta fără slash (`/servicii`) redirecționează 301 către cea cu slash, ca în WordPress.

**Atenție la server:** `public/.htaccess` nu conține regula obișnuită Laravel care elimină slash-ul
final. Împreună cu regula aplicației ar produce o buclă de redirecturi. Există un test automat
care împiedică reintroducerea ei.

## 2. Redirecturi 301

Încărcate la instalare și administrabile din *Conținut site → Redirecturi*.

| De la | Către | Motiv |
|---|---|---|
| `/asigurari/` | `/abonamente/` | link stricat din cele 23 de pagini de servicii |
| `/inregistrare-cont/` | `/acces-cont/` | pagină comasată |
| `/my-account/`, `/thank-you-page/` | `/acces-cont/` | WooCommerce |
| `/shop/`, `/cart/`, `/checkout/` | `/` | WooCommerce, magazin gol |
| `/category/uncategorized/`, `/feed/` | `/blog/` | arhive WordPress |
| `/?page_id=2903`, `2386`, `2892` | `/servicii/`, `/echipa/`, `/hikevet-contact/` | linkuri din meniul vechi |

Adresele `/author/…` și `/wp-content/…` răspund cu 404. Dacă Search Console arată trafic pe
alte adrese vechi, se adaugă din admin, fără modificări de cod.

## 3. Ce este nou față de site-ul actual

| Element | WordPress acum | HiKeVet Next |
|---|---|---|
| Meta description | lipsește | pe fiecare pagină, editabilă |
| Canonical | prezent | prezent pe fiecare pagină |
| Open Graph / Twitter | lipsește | titlu, descriere, imagine |
| Date structurate | lipsesc | `VeterinaryCare` (clinica), `WebSite`, `BreadcrumbList`, `MedicalProcedure` (servicii), `Article` (blog), `FAQPage` (întrebări) |
| Sitemap | `wp-sitemap.xml`, include paginile WooCommerce goale | `/sitemap.xml`, doar pagini publice reale |
| `robots.txt` | implicit | exclude admin, cont, tabletă, instalator |
| Imagini | JPG/PNG originale | WebP, două dimensiuni, `width`/`height`, încărcare întârziată |
| Titluri | unele pagini au două H1 (de exemplu `/abonamente/`) | un singur H1 pe pagină |

`VeterinaryCare` conține numele, adresa, telefonul și coordonatele de pe site. **Programul nu
este inclus**, pentru că nu apare nicăieri pe site-ul actual; se adaugă după ce este completat în setări.

Pe orice mediu care nu este producție, `robots.txt` interzice tot, ca site-ul de probă să nu fie indexat.

## 4. Măsurători

Lighthouse 13.6, rulat local în mod producție (date de laborator, nu de la vizitatori reali):

| Pagină | Performanță | Accesibilitate | Bune practici | SEO | LCP | CLS |
|---|---|---|---|---|---|---|
| Prima pagină, desktop | 100 | 100 | 100 | 100 | 0,3 s | 0,008 |
| Prima pagină, mobil | 87 | 100 | 100 | 100 | 3,9 s | 0,018 |
| Abonamente, mobil | 96 | 100 | 100 | 100 | 2,4 s | 0,037 |
| Pagină de serviciu, mobil | 100 | 100 | 100 | 100 | 0,6 s | 0,032 |
| Contact, mobil | 100 | 100 | 100 | 100 | 0,8 s | 0,022 |
| Întrebări frecvente, mobil | 100 | 100 | 100 | 100 | 0,8 s | 0,059 |
| Articol de blog, mobil | 87 | 100 | 100 | 100 | 4,1 s | 0,026 |

Pe mobil, prima pagină și articolele au LCP peste pragul de 2,5 s în simularea de rețea lentă
(imaginea principală). Rezultatele pe găzduirea reală vor diferi și trebuie măsurate din nou
după instalare; nu am comparat cu site-ul actual în aceleași condiții.

## 5. Google

Platforma **nu este conectată** la niciun cont Google. Din *Marketing → Setări* se completează
ID-urile (GTM, GA4, verificarea Search Console); etichetele se încarcă doar după acordul
vizitatorului. Rapoarte din Search Console sau Analytics în admin nu există.

## 6. Lista pentru ziua comutării

1. Înainte: export din Search Console al paginilor cu trafic; verificarea fiecărei adrese pe site-ul de probă.
2. În `.env`: `APP_ENV=production` și `APP_URL=https://hikevet.com`.
3. După comutare: `https://hikevet.com/robots.txt` nu mai conține `Disallow: /`.
4. Search Console: trimite `/sitemap.xml`, urmărește „Pagini” o săptămână pentru erori 404.
5. Verifică rezultatele îmbogățite cu instrumentul Google pentru prima pagină, un serviciu și întrebări.
6. Păstrează același ID GTM (`GTM-T65S32C`), ca istoricul Analytics să continue.
