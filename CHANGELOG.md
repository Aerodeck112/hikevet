# Jurnal de modificări

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
