# Jurnal de modificări

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
