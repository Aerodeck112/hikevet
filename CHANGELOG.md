# Jurnal de modificări

## 0.5.2 – 21.11.2026
- Adminul este grupat ca un singur motor al clinicii: **CRM** (clienți și animale, abonamente, plăți online, mesaje),
  **ERP** (programări, calendar, zile libere, consimțăminte, liste de prețuri, arhiva campaniei de sterilizare),
  **Site** (pagini, servicii, echipă, blog, întrebări, media, traduceri, SEO, redirecturi, asistentul AI) și
  **Sistem** (setări, personal, audit, migrare, arhiva WordPress, actualizări). Campania de sterilizare are un
  singur element în meniu, cu sub-meniu. Modulele ERP noi (fișe medicale, vaccinări, stocuri, facturare) vor intra la ERP.

## 0.5.1 – 21.11.2026
- Toate cererile de programare ajung și pe contact@hikevet.com, pe lângă „Emailuri clinică” (lista se poate
  completa din Setări clinică → Programări). Emailul către clinică are acum și emailul clientului, animalul
  și un link spre programările din admin.
- Când un client își anulează cererea din linkul primit pe email, clinica primește și ea un email.

## 0.5.0 – 21.11.2026

### Site în trei limbi: română (bază), engleză, maghiară
- Româna rămâne la adresele de până acum; engleza la /en/… și maghiara la /hu/… (aceleași pagini, alt prefix).
- Selector RO / EN / HU în meniu; legăturile de pe pagină păstrează limba aleasă.
- SEO: `<html lang>`, adrese canonice pe limbă, legături hreflang (și x-default spre română), sitemap cu toate
  variantele, og:locale.
- Interfața (meniu, formulare, cont client, programări, abonamente, Wallet, erori, replicile lui Hiko și ale Razei,
  scenele de pe servicii, asistentul) este tradusă în `resources/lang/en.json` și `resources/lang/hu.json`.
- Conținutul (23 de servicii, pagini, articole, întrebări frecvente, echipă, documente) este tradus în tabelul
  `translations`; clinica îl poate corecta din Admin → Conținut site → Traduceri (EN, HU).
- Documentele legale în engleză și maghiară sunt informative; pe pagină scrie că textul în română prevalează.
- Asistentul Hiko răspunde în limba paginii (sau în limba în care scrie vizitatorul).
- Contractele PDF și emailurile rămân în română.

### Reparații
- Mesajele de validare din formulare apăreau ca „validation.required”: acum sunt în română (și în engleză, maghiară).

## 0.4.2 – 21.11.2026
- Fiecare pagină de serviciu are scena ei animată, în antet: medicul îi face lui Hiko sau Razei exact serviciul
  respectiv (ecograf cu imagine care se mișcă, radiografie cu bliț și schelet, anestezie cu „zzz”, ghips la ortopedie,
  lupă la dermatologie, oftalmoscop, ciocănel de reflexe, vaccin cu steluță, scaner de microcip, pașaport,
  ambulanța cu girofar etc.), plus replica pacientului. Pentru procedurile de reproducere, scena e de laborator.

## 0.4.1 – 21.11.2026
- Footer: semnătura „Dezvoltat cu grijă (și multe recompense pentru Hiko) de VITIM”, cu legătură spre vitim.ro;
  la trecerea cu mouse-ul, Hiko spune unde a învățat să vorbească.
- Footer: rămâne doar sigla ANPC SAL; sigla SOL (platforma europeană de soluționare online, închisă în 2025) a fost scoasă.
- Butonul asistentului nu mai acoperă siglele din josul paginii.

## 0.4.0 – 21.11.2026

### Programări pe site, ca cereri
- Butoanele „Programează-te” duc la formularul propriu (nu mai la Digitail). Digitail rămâne opțiune în Setări.
- Formular nou, în 4 pași: pacientul, motivul, ziua și intervalul (dimineața, la prânz, după-amiaza, seara),
  datele de contact. Medicul poate fi „oricare”; la medicii cu orar se poate alege și ora exactă.
- Rezumat live al cererii, cu Hiko (sau Raza, pentru pisici) care comentează ce completezi.
- Pagină de confirmare cu „ce urmează”. Emailul spune clar că programarea se fixează după confirmare.
- Admin → Programări: cererile pe interval au butonul „Stabilește ora și confirmă” (medic, oră, durată);
  clientul primește emailul de confirmare. Paginile de servicii pre-selectează serviciul.

### Asistent AI (Hiko)
- Chat pe toate paginile, oprit până se adaugă cheia API Anthropic (Admin → Asistent AI: setări).
- Răspunde din conținutul site-ului (servicii, echipă, întrebări, prețuri abonamente, Wallet, programări)
  și din informațiile adăugate de clinică; din întrebările primite se poate „învăța” răspunsul corect.
- Fără diagnostice și doze, urgențele la telefon, limite pe vizitator, jurnal păstrat 60 de zile, fără IP.

### Design
- Antetul paginilor: panou turcoaz (culoarea site-ului vechi), mai aerisit; fundalul site-ului turcoaz deschis.
- Logo mare, ca ecuson, în meniu; logo alb în footer și în emailuri.
- Episoade: săgeți și tragere cu mouse-ul, player mare cu derulare și trecere la episodul următor.
- Interacțiuni noi: saluturi după ora din zi, căciuli de Moș Crăciun în decembrie, inimioare la mângâiere,
  recompense pentru Hiko pe prima pagină, Hiko care aleargă pe bara de citire a articolelor,
  urme de lăbuțe pe antet, cuvinte secrete („miau”, „ham”).

## 0.3.2 – 20.11.2026

### Personajele au nume: Raza și Hiko
- Pisica cu ochelari se numește Raza, ciobănescul se numește Hiko.
- Fiecare balon de vorbă are o etichetă cu numele (magenta pentru Raza, mandarină pentru Hiko),
  inclusiv lătratul lui Hiko de pe prima pagină.
- Replici noi în care se prezintă („Eu sunt Hiko, ciobănescul de serviciu”, „Sunt Raza, pisica de pază”).
- Numele apar și pentru cititoarele de ecran, pe ecranul de autentificare al personalului și în emailuri.

## 0.3.1 – 20.11.2026

### Pisica și câinele, pe tot site-ul
- Fiecare pagină interioară are personajul ei în antet (câinele la Servicii, Contact, Întrebări, Programare;
  pisica la Echipă, Blog, Abonamente, Cont client), cu replici proprii la atingere și privirea după cursor.
- Pe documente (Termeni, Contract, Anexă, Politica de confidențialitate) pisica doarme; se trezește dacă o atingi.
- În footer, pe fiecare pagină, o pisică adormită pe margine.
- Pagini de eroare noi, cu personaje: 404 (câinele care a căutat peste tot), 419, 429, 500 și 503
  (afișată cât timp se instalează o actualizare).
- Contul clientului: câinele salută pe prenume, iar locurile goale (fără animale, fără contracte) au un personaj.
- Bannerul de cookie-uri, ecranul de autentificare al personalului, emailurile (antet nou, albastru, cu personajele)
  și iconița site-ului (pisica cu ochelari).
- Fără biblioteci noi: animațiile sunt CSS și se opresc pentru cine are „mișcare redusă” activată.

## 0.3.0 – 20.11.2026

### Design nou, după fațada clinicii
- Culorile și fontul de titluri sunt luate de pe muralul clinicii noi (bula albastră, galben, magenta, mandarină).
- Prima pagină: bula „Clinica HiKeVet”, o pisică cu ochelari care urmărește cursorul și un ciobănesc care latră
  la atingere; turul clinicii, cameră cu cameră; galeria de la inaugurare; episoadele video ale pacienților.
- Animațiile se opresc pentru cine are „mișcare redusă” activată în sistem.

### Abonamente și Wallet
- Calculator de abonament cu animal desenat care crește odată cu greutatea, riglă de categorii și economia
  pentru 6 și 12 luni.
- Simulator Wallet: principal, bonus servicii, bonus produse și cum se împarte plata unui serviciu.
- În cont: sume rapide la alimentare, bonusul afișat pe loc și zilele rămase din abonament.

### Altele
- Fotografia 0h7a9586 scoasă de peste tot, inclusiv din conținutul site-ului instalat.
- Fără adrese hikevet.com scrise fix în cod.


## 0.2.0 – 19.11.2026 (pachet de instalare; încă neinstalat în producție)

### Campania de sterilizare (portată din modulul 2.7.6)
- Dosare, file de lucru, programări, emailuri și rapoartele pentru Primărie rulează în platforma nouă.
- Raport Primărie: Excel oficial, desfășurător și centralizator PDF, ZIP general și pe dosar, audit de
  integritate, validare în masă, control numere după fotografie, registru și reconciliere.

### Plăți online cu cardul (Stripe)
- Clienții plătesc din cont alimentarea Wallet-ului (cu același bonus ca la clinică) și abonamentul (plată sau
  reînnoire), pe pagina securizată Stripe. Confirmare la întoarcere, prin webhook semnat și prin verificare la 15 minute.
- *Abonamente → Plăți online*: plățile încasate și facturile de emis manual; plățile cu nepotriviri rămân „de verificat”.
- Cheile Stripe se pun din Setări clinică, criptat. Ghid: `docs/PLATI_ONLINE.md`.

### Campania de sterilizare
- Oprită implicit, păstrată doar ca istoric: niciun email, nicio programare nouă.

### Import
- `hikevet:wpress` declară `utf8mb4` în fișierul SQL extras; importul refuză o copie WordPress cu
  diacritice codate de două ori și raportează consimțămintele care nu pot fi citite, păstrându-le exact.
- Consimțămintele aduc lista pacienților vizitei, legătura cu vizita și lanțul variantelor corectate.

## 0.1.0 – 14.11.2026 (versiune de probă, neinstalată în producție)

Prima versiune completă a platformei.

### Site public
- Toate paginile site-ului actual, cu aceleași adrese și conținutul real; 78 de imagini optimizate.
- Design nou pe identitatea HiKeVet (culori, logo, fotografii), fonturi cu diacritice complete.
- Meta description, canonical, Open Graph, date structurate, sitemap, redirecturi 301.
- Acord pentru cookie-uri înainte de încărcarea etichetelor de măsurare.

### Abonamente
- Configurator în 6 pași, cu prețuri calculate pe server din lista de prețuri 2.7.0.
- Contract PDF cu semnătură, stocat privat; validarea plății la clinică; reînnoire; expirare automată.

### Cont client
- Autentificare, activare cont, animale, contracte, puncte, Wallet, tratamente, notificări.

### Clinică
- Fișa clientului cu toate operațiunile; registru Wallet și puncte care se poate doar completa.
- Consimțământ pe tabletă cu cod temporar, semnături, amprentă de integritate.
- Arhivă de sterilizări doar pentru citire, cu rapoarte lunare și export.
- Programări proprii (dezactivate public; Digitail rămâne activ).

### Administrare
- CMS cu versiuni, bibliotecă media, roluri și permisiuni, autentificare în doi pași, jurnal de audit.
- Instalator pentru cPanel; actualizări ZIP semnate, complete sau incrementale, cu revenire automată; backup criptat.
- Import din WordPress, cu probă înaintea scrierii.

### Corecții făcute în timpul verificărilor
- Regula `.htaccess` care elimina slash-ul final producea buclă de redirecturi pe Apache.
- Extensia `bcmath` era cerută inutil de instalator.
- Asseturile panoului de administrare lipseau din pachet.
- Pe HTTP local, linkurile erau forțate pe HTTPS.
