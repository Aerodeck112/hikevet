# Audit pluginuri WordPress HiKeVet

Data auditului: 12.11.2026. Sursa: cele patru arhive ZIP livrate, extrase în `source-wordpress/`
(în afara Git, vezi `source-wordpress/README.md`). Inventarul complet, generat automat cu
fișier și linie pentru fiecare funcție, hook, tabel și cheie meta, este în
[`docs/generated/plugin-inventory.md`](generated/plugin-inventory.md).

Prescurtări fișiere:

| Prescurtare | Fișier | Linii | Versiune în cod |
|---|---|---|---|
| SIM | `hikevet-abonament-simulator/hikevet-abonament-simulator.php` | 1101 | 2.7.0 (README) |
| WAL | `hikevet-client-wallet-core/hikevet-client-wallet-core.php` | 4784 | 3.1.0 (`VER`, linia 12) |
| CON | `hikevet-consent-tablet/hikevet-consent-tablet.php` | 1240 | 1.4.1 (`VER`, linia 12) |
| STE | `hikevet-sterilization-campaign/hikevet-sterilization-campaign.php` | 11412 | 2.7.6 (`VER`, linia 12) |

Toate cele patru fișiere trec `php -l` fără erori (PHP 8.3.6).

## 1. Constatări care contează pentru migrare

### 1.1 Diferențe între ce e livrat și ce rulează în producție

| Modul | ZIP livrat | README servit public de producție | Observație |
|---|---|---|---|
| Consimțământ tabletă | 1.4.1 | descrie până la 1.5.2 | Producția pare mai nouă decât sursa livrată: lipsesc din ZIP „înapoi la dosar”, filtrele avansate și sincronizarea automată a identității. Trebuie cerut ZIP-ul 1.5.2. |
| Simulator | 2.7.0 | 2.7.0 | Identic (`hv-form.js` din ZIP este byte-identic cu cel servit public). |
| Wallet Core | 3.1.0 | 3.1.0 | Identic la nivel de README. |
| Campanie | 2.7.6 | 2.7.6 | Identic la nivel de README. |

### 1.2 Probleme de securitate și confidențialitate în sistemul actual

Acestea nu se portează; noul sistem le corectează.

1. **Contractele PDF sunt în director public.** `ajax_send()` scrie PDF-ul și semnătura JPG în
   `wp-content/uploads/hikevet-contracte/` (SIM:1024–1040) și Wallet Core construiește un URL
   public către ele (`uploads_url_from_path`, WAL:1350; `pdf_url` în contract, WAL:1574, 1636).
   PDF-ul conține CNP, serie și număr CI, adresă și semnătură. Oricine are linkul îl poate deschide.
2. **Reducerea multi-animal se poate obține cu emailul altui client.** Pentru vizitatori
   neautentificați, `existing_pets_for_owner_email()` (SIM:838) caută contul după emailul introdus
   în formular, iar contractul se atașează apoi acelui cont (`ensure_client_user`, WAL:1177).
   Un terț poate obține 30–100% reducere și poate adăuga un animal în contul altcuiva.
3. **Codul de 6 cifre pentru tabletă nu expiră și nu are limită de încercări** (`create_code`,
   CON:272; `find_consent_by_code`, CON:294). Unicitatea codului nu este verificată la generare.
4. **README-urile celor patru pluginuri sunt servite public** la
   `/wp-content/plugins/<plugin>/README.txt` și descriu fluxuri interne, adrese de email și
   numele medicilor.
5. **CNP și CI sunt stocate în clar** în `hv_payload` (post meta, SIM:1055) și în payload-ul
   consimțământului (CON:104–145).
6. **Semnăturile sunt stocate ca data-URL în post meta** (CON:1203–1204) fără hash de integritate.

### 1.3 Neconcordanțe de logică găsite în cod

| # | Neconcordanță | Dovadă | Decizie în HiKeVet Next |
|---|---|---|---|
| N1 | Limitele categoriilor de greutate diferă între module: simulatorul folosește `[min, max)`, Wallet Core folosește `(min, max]`. Un câine de exact 2,5 kg este „2.5-5 kg” în contract și „1.25-2.5 kg” la recalcularea din Wallet. | SIM:814–836 vs WAL:1361–1379 | Se folosește regula simulatorului (cea din contract). Diferența este acoperită de test și listată pentru aprobare. |
| N2 | Greutatea maximă la pisică: 70 kg în browser, 999 kg pe server. | `hv-form.js` (`maxWeight`) vs SIM:829 | Se păstrează validarea de formular 0,5–70 kg; serverul refuză peste 70 kg. De confirmat. |
| N3 | Totalurile „preț normal / preț abonament” din ofertă sunt valori fixe pe categorie, nu suma rândurilor. Ex. câine 1.25–2.5 kg: suma rândurilor „normal” este 4180, totalul afișat este 3810. | SIM:188–219 | Se păstrează valorile fixe exact (apar în contracte). Diferența este semnalată pentru decizie editorială. |
| N4 | Prețul pe 12 luni nu urmează o formulă unică (ex. 229×12×0,8 = 2198,4, în tabel 2200; 231 → 2224). | SIM:817–829 | Tarifele se stochează ca tabel, nu ca formulă. |
| N5 | „Abonament activ” rămâne adevărat permanent după prima validare: `hv_last_validated_subscription_at` nu se șterge la expirare. | WAL:815–820 | Starea se calculează din data de expirare; comportamentul vechi e documentat, nu reprodus. De confirmat. |
| N6 | Validarea plății folosește doar „ultimul contract” al clientului; la mai multe animale, al doilea contract nu poate fi validat separat și expirarea e una singură per client. | WAL:4111–4116, 4275–4345 | Abonamentul devine entitate per animal, cu plată și expirare proprii. |
| N7 | Homepage-ul spune „plan de asigurare medicală sub formă de abonament”, iar contractul și formularul spun „nu o asigurare”. | pagina `/` vs SIM:558, SIM:462 | Text păstrat în audit; pe noul site formularea „asigurare” nu este folosită. Necesită aprobare editorială. |
| N8 | Lista medicilor validatori este scrisă în cod: Dr. Festeu Radu, Dr. Dregics Szidonia, Dr. Anca Ion. | STE:17 și README v1.9.2 | Devine listă administrabilă. |

## 2. Simulator abonamente (SIM)

**Stocare:** CPT `hv_abon_sub` (SIM:77). Meta: `hv_payload` (JSON complet), `hv_pdf` (cale fișier),
`hv_species`, `hv_tier`, `hv_total_normal`, `hv_total_abon`, `hv_submission_token`,
`hv_submission_ready`, `hv_payment_method` (= `clinic`), `hv_payment_status`
(= `awaiting_clinic` la creare, `paid` după validare), `hv_payment_period_months`,
`hv_payment_amount`, `hv_mail_*_status/error` (SIM:1049–1088). Opțiune: `hv_abon_settings` (SIM:11).

**Flux `ajax_send()` (SIM:910–1100):** nonce → token anti-dublare (`existing_submission_by_token`,
SIM:880) → validare câmpuri → categorie din greutate (server) → reducere multi-animal (server) →
suprascrierea prețurilor din payload cu valorile calculate pe server → semnătură PNG→JPG →
PDF → post + meta → `do_action('hv_abon_submission_ready')` → emailuri către clinică și client.
Eșecul emailului nu anulează contractul.

**Tarife** (`tier_from_weight`, SIM:814):

| Specie | Categorie | Interval kg | Lunar | 6 luni | 12 luni |
|---|---|---|---|---|---|
| Câine | 1.25-2.5 kg | [1,25; 2,5) | 229 | 1237 | 2200 |
| Câine | 2.5-5 kg | [2,5; 5) | 231 | 1251 | 2224 |
| Câine | 5-10 kg | [5; 10) | 240 | 1296 | 2304 |
| Câine | 10-20 kg | [10; 20) | 248 | 1335 | 2376 |
| Câine | 20-40 kg | [20; 40) | 257 | 1388 | 2468 |
| Câine | 40-60 kg | [40; 60] | 265 | 1431 | 2544 |
| Pisică | 0.5-2 kg | [0,5; 2) | 216 | 1169 | 2078 |
| Pisică | 2-4 kg | [2; 4) | 225 | 1215 | 2160 |
| Pisică | 4-8 kg | [4; 8) | 233 | 1260 | 2240 |
| Pisică | Peste 8 kg | [8; 999) | 237 | 1278 | 2272 |

**Reducere multi-animal** (`server_discount_info`, SIM:849): ordinal = număr animale existente + 1.
Primul animal: 0%. De la al doilea: 100% dacă un animal existent are aceeași etichetă de categorie
(comparare fără majuscule); altfel 30% (al 2-lea), 40% (al 3-lea), 50% (al 4-lea și următoarele).
Preț final = `round(standard × (100 − p) / 100)`. Animalele arhivate nu se numără
(`get_user_pets` le exclude, WAL:1414).

**Servicii incluse** (`base_services`, SIM:149; `apply_mods`, SIM:188): 15 rânduri pe specie,
cu ajustări pe categorie la câine și totaluri fixe pe categorie. Deparazitările nu sunt incluse (v2.7.0).

**Confirmări legale obligatorii** (SIM:961): `contract`, `annex`, `terms`, `guide`, `privacy`,
`not_insurance`. **Perioade:** 1, 6, 12 luni (SIM:989). **Valabilitate ofertă:** 5 zile
(`contract_valid_days`, SIM:59) — este doar text în contract; nu există anulare automată în cod.

**Contract:** 52 de linii de șablon (`contract_template_lines`, SIM:542), PDF construit manual
(`pdf_build`, SIM:626), text fără diacritice (`ascii`, SIM:13). Prestator: S.C. FEST4PETS S.R.L.

## 3. Wallet Core (WAL)

**Roluri:** `hikevet_client`, `hikevet_doctor`; capabilități `manage_hikevet_wallet`,
`manage_hikevet_sterilization` (WAL:372–393).

**Tabele** (`create_tables`, WAL:405): `hv_wallet_tx`, `hv_treatments`, `hv_notifications`,
`hv_email_log`, `hv_audit_log`, `hv_doctor_notes`. **User meta:** `hv_pets` (listă de animale),
`hv_contracts` (maxim 50, WAL:1554), `hv_subscription_active`, `hv_subscription_expires_at`,
`hv_last_validated_*`, `hv_wallet_promo_migrated_v1`, `hv_expiry_notice_sent_*`.

**Registrul `hv_wallet_tx`** are patru tipuri de sold (`balance_type`):

| `balance_type` | Unitate | Ce reprezintă |
|---|---|---|
| `subscription` | puncte (`points_delta`) | Puncte de abonament; `amount_delta` reține suma plătită la validare |
| `wallet_principal` | lei | Bani depuși de client |
| `wallet_service_bonus` | lei bonus | Bonus utilizabil la servicii |
| `wallet_product_bonus` | lei bonus | Bonus utilizabil la produse |
| `wallet` | (istoric) | Soldul vechi, închis la migrarea v2.8.0 |

**Tipuri de tranzacție:** `subscription_validated`, `treatment_consume`, `wallet_deposit_principal`,
`wallet_bonus_services`, `wallet_bonus_products`, `wallet_purchase_principal`,
`wallet_service_bonus_spend`, `wallet_product_bonus_spend`, `manual_balance_adjust`,
`legacy_wallet_close`, `wallet_migration_*`, plus tranzacțiile de ștergere și restaurare
(WAL:2753–2884).

**Reguli:**

- **Validare abonament** (`handle_validate_subscription`, WAL:4275): suma plătită = prețul
  contractual al perioadei; puncte = `round(preț lunar contractual × luni)` — reducerea de 6/12
  luni nu reduce punctele (WAL:4258). Expirare = acum + perioada. Dublarea aceleiași validări
  este blocată (WAL:4303).
- **Alimentare Wallet** (`handle_wallet_credit`, WAL:4439): doar cu abonament activ. La X lei
  depuși: X principal, X bonus servicii, X/2 bonus produse.
- **Plată din Wallet** (`calculate_wallet_purchase`, WAL:863): bonusul acoperă cel mult 1/2 din
  preț la servicii și 1/3 la produse; restul din principal; principal insuficient = refuz.
  Bonusul e exclus automat pentru denumiri care conțin `deparazit`, `antiparazitar`,
  `medicament`, `laborator extern`, `analiza externa`, `implant`, `ct`, `rmn`, `reactiv`,
  `material chirurgical` (WAL:858), sau manual.
- **Serviciu din abonament** (`handle_add_treatment`, WAL:4453): consumă doar puncte de
  abonament; refuz la puncte insuficiente; deparazitările sunt refuzate.
- **Catalog tratamente** (`treatment_catalog`, WAL:542): pe specie și categorie, cu puncte pe
  utilizare (`subscription_points`) — 10 categorii × 15 servicii.
- **Ajustare manuală** (`handle_manual_points_adjust`, WAL:4132): numele complet al angajatului
  este obligatoriu (minim două cuvinte, 5 caractere); soldul nu poate deveni negativ.
- **Ștergere și restaurare** tratament sau operațiune Wallet: motiv obligatoriu, soldurile se
  restituie; restaurarea cere sold suficient (WAL:2725–2920).
- **Notificare expirare** (`check_subscription_expiry_notifications`, WAL:1980): zilnic, pentru
  abonamente care expiră în 5 zile, o singură dată per contract și dată de expirare.
- **Cerere alimentare** de către client (`handle_front_wallet_request`, WAL:1720): doar notificare
  către clinică; soldul se schimbă numai la confirmarea personalului.

**Sincronizarea cu simulatorul** (`process_submission_post`, WAL:1587): creează sau găsește
contul după email, actualizează telefon și adresă, adaugă sau actualizează animalul (cheie:
microcip, altfel nume + specie; WAL:1478), adaugă contractul, notifică clientul, marchează
`hv_client_sync_done`.

## 4. Consimțământ tabletă (CON)

**Stocare:** CPT `hv_consent`; meta `hv_consent_payload`, `_status` (`pending`, `signed`,
`cancelled`; CON:244), `_token` (32 caractere), `_code` (6 cifre), `_owner_signature`,
`_refusal_signature`, `_doctor_signature`, `_doctor_signature_at/_by`, `_signed_at` (CON:13–23).

**Payload** (`defaults`, CON:104): date proprietar și pacient, procedură, medic, anestezie
(4 opțiuni, CON:146), investigații (8 opțiuni, CON:155), cost estimat, servicii refuzate, patru
acorduri opționale da/nu (`media_*`), declarația privind alimentația, patru confirmări finale
(`owner_final_read`, `_risk`, `_gdpr`, `_lucid`), plus vizita multipacient (`visit_patients`).

**Semnare** (`handle_tablet_submit`, CON:1168): valid prin token sau cod; doar în starea
`pending`; toate cele patru confirmări finale obligatorii, inclusiv GDPR; semnătura de refuz
obligatorie dacă există servicii refuzate; după semnare documentul nu mai poate fi semnat.
Semnătura medicului se poate adăuga ulterior fără a atinge semnătura proprietarului
(`handle_save_doctor_signature`, CON:733).

## 5. Campanie sterilizare (STE)

**Tabele** (STE:173–442): `hv_st_cases` (aproximativ 200 de coloane, cheie unică `cod_caz`),
`hv_st_appointments`, `hv_st_email_log`. Lista completă a coloanelor: `docs/DATA_IMPORT_SPEC.md`.

**Opțiuni:** `hv_st_campaign_db_ver`, `hv_st_reported_through_september_2026` (+ `_updated`,
`_previous`), `hv_st_registry_reconciliation`, trei marcaje de rulare cron.

**Fișiere JSON:** `reported-through-september-2026.json` (347 de coduri de caz cu luna raportării)
și `september-2026-145.json` (145 de coduri cu numărul de pacient). Conțin doar coduri, fără date
personale. Sunt liste de referință, nu dosare.

**Stări întâlnite în cod:** `status_caz` — Programat, Confirmat, Prezentat, Apt interventie,
Amanat medical, In interventie, Operat, Externat, Neprezentat, Validat primarie;
`status_interventie` — Finalizat, Operat (istoric).

**Reguli de raportare:** raportul lunar selectează intervențiile Finalizat/Operat după
`data_interventie` (v2.7.6) și exclude codurile raportate anterior. `numar_pacient` (numărul din
fotografie) este distinct de `cod_caz` (HV-ST-AAAA-NNN).

**Funcții care modifică date istorice și NU se portează în arhivă:** reparare automată a
integrității, renumerotare, validare în masă, sincronizarea consimțămintelor, ștergere dosar.
Arhiva este strict read-only.

## 6. Ce rămâne neverificat

- Datele reale: nu există export SQL și nici `wp-content/uploads`.
- Setările din producție (`hv_abon_settings`, lista de coduri raportate editată din admin).
- Modulul de consimțământ 1.5.x care pare să ruleze în producție.
- Peste 300 de funcții din STE au fost inventariate automat, dar citite integral doar schema,
  stările și regulile de raportare; logica de export PDF/XLSX nu a fost citită linie cu linie.
