# Specificația importului de date

Descrie exact ce citește importul din baza WordPress și unde scrie. Schemele sursă provin din
codul pluginurilor (`docs/generated/wp-table-schemas.md`, `database/import-spec/wp-tables.json`),
nu dintr-un export real. **Importul a fost rulat doar pe o bază sintetică** construită după
aceste scheme.

## 1. Principii

1. **Sursa nu este modificată.** Conexiunea `wp` este folosită numai pentru citire.
2. **Probă înainte de scriere.** Fără `--execute`, comanda numără, verifică și raportează, fără să scrie.
3. **Repetabil.** Fiecare rând are o cheie de legătură (`wp_user_id`, `wp_post_id`, `wp_tx_id`,
   `source_id`); a doua rulare actualizează, nu dublează.
4. **Nimic inventat.** Valorile lipsă rămân goale și sunt listate în raport ca „Lipsă în sursă”.
5. **Dovezile nu se rescriu.** Dosarele de sterilizare, consimțămintele semnate și semnăturile se
   copiază neschimbate, cu amprentă SHA-256.
6. **Doar baza indicată.** Importul citește numai din baza conexiunii `wp`, chiar dacă utilizatorul
   MySQL vede și alte baze.

## 2. Detectarea sursei

Prefixul tabelelor este dedus din tabela care se termină în `usermeta` sau `hv_st_cases`. Dacă o
tabelă așteptată lipsește, secțiunea respectivă este sărită și raportată.

## 3. Conturi și animale

| Sursă | Destinație | Regulă |
|---|---|---|
| `users.ID` | `users.wp_user_id` | cheie de legătură |
| `users.user_email` | `users.email` | litere mici; email invalid → cont neimportat, raportat |
| `users.user_pass` | `users.wp_password_hash` | neschimbat; verificat la prima autentificare (phpass `$P$`, bcrypt, `$wp$2y$`), apoi înlocuit |
| `users.display_name` | `users.name` | dacă lipsește: partea dinaintea `@` |
| `users.user_registered` | `users.created_at` | |
| `usermeta` `billing_phone`/`phone` | `users.phone` | doar dacă nu există deja |
| `usermeta` `billing_address_1`/`address`, `billing_city` | `users.address`, `users.city` | doar dacă nu există deja |

Se importă utilizatorii care au `hv_pets`, `hv_contracts` sau rolul `hikevet_client`. Personalul
(medici, administratori) **nu se importă**; conturile lor se creează din admin. Un cont cu același
email existent deja este legat, nu dublat.

`usermeta.hv_pets` (listă serializată PHP) → `pets`:

| Câmp sursă | Destinație | Regulă |
|---|---|---|
| `id` | `pets.wp_pet_id` | cheie, împreună cu proprietarul |
| `pet_name`, `species`, `sex`, `breed`, `microchip`, `sterilized`, `tier_label` | coloanele cu același rol | text gol → gol |
| `dob` | `date_of_birth` | dată invalidă → gol |
| `weight` | `weight_kg` | virgula devine punct |
| `archived_at`, `archive_reason` | identic | animalele arhivate rămân arhivate |

## 4. Contracte

Postări de tip `hv_abon_sub` cu meta `hv_*` → `contracts`.

| Sursă | Destinație | Regulă |
|---|---|---|
| `posts.ID` | `wp_post_id`; `number = WP-<ID>` | numerele noi `HV-AB-…` nu se suprapun |
| `hv_payload` (JSON) | `payload` (criptat) | fără câmpul `signature`; se adaugă `source_pdf` |
| `hv_payload.price_*`, `standard_price_*`, `discount_*`, `pet_ordinal` | coloanele de preț | valorile din contract, nerecalculate |
| `hv_total_normal`, `hv_total_abon` | `total_normal`, `total_subscription` | |
| `hv_payment_status` | `status` | `paid` + neexpirat → `active`; `paid` + expirat → `expired`; altfel `awaiting_clinic` |
| `hv_payment_period_months`, `hv_payment_amount`, `hv_payment_paid_at` | `period_months`, `payment_amount`, `paid_at`, `starts_at` | |
| (calculat) | `expires_at` | `paid_at` + perioada |
| `hv_client_user_id` sau `owner_email` | `user_id` | client negăsit → contract neimportat, raportat |
| `post_date` | `signed_at`, `created_at` | |

Contract fără `hv_payload` valid → neimportat, raportat. Raportul numără și contractele fără `hv_pdf`.

**Neimplementat:** copierea fișierelor PDF vechi din `uploads/hikevet-contracte/` pe discul privat.
Calea originală este păstrată în payload (`source_pdf`).

**De verificat pe date reale:** în WordPress expirarea este una per client (N5, N6 din audit). La
import fiecare contract plătit primește propria expirare, calculată din data plății. Pentru
clienții cu mai multe contracte, rezultatul trebuie comparat cu `hv_subscription_expires_at`.

## 5. Registru Wallet și tratamente

`hv_wallet_tx` → `wallet_transactions`, rând cu rând: `id` → `wp_tx_id`; `type`, `balance_type`,
`amount_delta`, `points_delta`, `related_type`, `related_id`, `created_at` neschimbate; `note`
fără etichete HTML; `actor_user_id` → `actor_name = "WordPress #<id>"`.

- Tranzacțiile fără client importat sau cu tip de sold necunoscut nu se importă și apar în raport.
- Soldurile nu se copiază: rezultă din suma tranzacțiilor. Proba numără combinațiile client/tip de
  sold cu sumă negativă în sursă; acestea se raportează și **nu se corectează**.

`hv_treatments` → `treatments`: `id` → `wp_treatment_id`; denumire, preț, puncte, categorie de
plată, sume din principal și bonus, blocarea bonusului, note, ștergere și restaurare cu motiv și dată.

## 6. Consimțăminte

Postări de tip `hv_consent` → `consents`.

| Sursă | Destinație | Regulă |
|---|---|---|
| `hv_consent_payload` | `payload` (criptat) | **integral, fără normalizare** |
| `hv_consent_status` | `status` | `pending`, `signed`, `cancelled`; altceva → `pending` |
| `hv_consent_owner_signature`, `_refusal_signature`, `_doctor_signature` | coloanele de semnătură (criptate) | neschimbate |
| `hv_consent_signed_at`, `_doctor_signature_at` | `signed_at`, `doctor_signed_at` | |
| `hv_st_case_id` | `sterilization_case_id`, `origin = sterilization` | |
| `hv_consent_code`, `hv_consent_token` | – | **nu se importă**: codurile vechi nu expirau |

Raportul numără consimțămintele marcate „semnat” fără semnătură de proprietar. Câmpurile adăugate
de versiunea 1.5.x rămân în payload, dar nu au fost văzute în sursă.

## 7. Arhiva de sterilizări

`hv_st_cases` (193 de coloane în codul 2.7.6) → `sterilization_cases`.

- **Rândul complet** se salvează ca JSON în `source_row`, cu amprenta SHA-256 în `row_sha256`.
- 20 de coloane se copiază și separat, pentru căutare: `cod_caz`, `numar_pacient`,
  `numar_pacient_raport`, `ora_programare`, `nume_proprietar`, `telefon`, `email`, `localitate`,
  `specie`, `sex`, `nume_pacient`, `rasa`, `microcip`, `procedura_efectuata`, `tip_anestezie`,
  `medic_operator`, `status_caz`, `status_interventie`, `validat_pentru_primarie`, `validat_de`.
- 6 date se convertesc pentru filtrare: `data_programare`, `data_prezentare`, `data_interventie`,
  `data_raportare_primarie`, `data_externare`, `data_validare`. `0000-00-00` devine gol; valoarea
  originală rămâne în `source_row`.
- `cod_caz` și `numar_pacient` sunt câmpuri distincte și rămân distincte.

Semnalări în raport (`missing_in_source`), fără a bloca importul:

- la un dosar finalizat (`status_interventie` = Finalizat sau Operat) lipsește unul dintre:
  proprietar, pacient, specie, sex, data intervenției, procedură, medic, număr pacient, fotografie;
- o dată are o valoare invalidă.

Erori care exclud rândul: `cod_caz` gol; `cod_caz` duplicat (al doilea rând este respins și raportat).

**Documente** (`poza_pacient`, `documente_scanate`): cu `--uploads=<cale>` fiecare fișier este
căutat în copia `wp-content/uploads`, copiat bit cu bit pe discul privat, iar amprenta este
verificată după copiere. Fișierul negăsit se înregistrează ca „Lipsă în sursă”. Căile care ies din
directorul indicat sunt ignorate.

Tabele asociate: `hv_st_appointments` → `sterilization_appointments`, `hv_st_email_log` →
`sterilization_email_logs`. Listele de coduri raportate (JSON din plugin, ținute local, în afara
Git) → `sterilization_reference_lists`.

**Reconciliere:** raportul dă numărul dosarelor finalizate pe luni (după `data_interventie`),
pentru comparație cu rapoartele trimise Primăriei și cu cele două liste de referință.

**Anulare:** `--rollback=<ID lot>` șterge exact rândurile aduse de acel lot.

## 8. Ce nu se importă încă

| Sursă | Motiv |
|---|---|
| `hv_notifications`, `hv_email_log`, `hv_audit_log`, `hv_doctor_notes` | importator nescris; tabelele și coloanele de legătură există |
| PDF-urile contractelor | vezi §4 |
| Opțiunea `hv_abon_settings` | valorile implicite din cod sunt deja încărcate; de comparat manual |
| Pagini, articole, media WordPress | conținutul a fost preluat din site-ul public |
| Comenzi WooCommerce | plata online a fost eliminată din simulator |
