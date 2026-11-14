# Harta migrării WordPress → HiKeVet Next

Prefixul `wp_` este orientativ; importul detectează prefixul real. Detaliile câmp cu câmp sunt în
[`docs/DATA_IMPORT_SPEC.md`](docs/DATA_IMPORT_SPEC.md), iar pașii în [`docs/IMPORT_RUNBOOK.md`](docs/IMPORT_RUNBOOK.md).

## Entități

| Sursă WordPress | Destinație | Cheie de legătură | Importator | Stare |
|---|---|---|---|---|
| `wp_users` + `wp_usermeta` (rol `hikevet_client`) | `users` + rol `client` | `users.wp_user_id` | `WordPressCoreImporter::users` | Testat pe date sintetice |
| `wp_users.user_pass` | `users.wp_password_hash` | – | idem | Verificat la primul login, apoi înlocuit |
| `wp_usermeta.hv_pets` (listă serializată) | `pets` | `(user_id, wp_pet_id)` | `::pets` | Testat sintetic |
| `wp_posts` tip `hv_abon_sub` + meta `hv_*` | `contracts` | `contracts.wp_post_id` | `::contracts` | Testat sintetic |
| fișierul din meta `hv_pdf` | (referință în payload) | – | – | **Copierea PDF-urilor vechi nu este implementată** |
| `wp_hv_wallet_tx` | `wallet_transactions` | `wp_tx_id` | `::wallet` | Testat sintetic |
| `wp_hv_treatments` | `treatments` | `wp_treatment_id` | `::treatments` | Testat sintetic |
| `wp_posts` tip `hv_consent` + meta | `consents` | `consents.wp_post_id` | `::consents` | Testat sintetic |
| `wp_hv_st_cases` | `sterilization_cases` | `source_id` | `SterilizationImporter` | Testat sintetic |
| `wp_hv_st_appointments` | `sterilization_appointments` | `source_id` | idem | Testat sintetic |
| `wp_hv_st_email_log` | `sterilization_email_logs` | `source_id` | idem | Testat sintetic |
| fotografii și acte din dosare | `sterilization_documents` + disc privat | amprentă SHA-256 | idem | Testat sintetic |
| opțiunea `hv_st_reported_through_september_2026` | `sterilization_reference_lists` | `(list_key, cod_caz)` | idem | Testat sintetic |
| `wp_hv_notifications`, `wp_hv_email_log`, `wp_hv_audit_log`, `wp_hv_doctor_notes` | `client_notifications`, `email_logs`, `audit_logs`, `doctor_notes` | coloane `wp_*_id` există | – | **Neimplementat** |
| `wp_usermeta.hv_contracts` | derivat din `contracts` | – | – | Nu se importă separat |
| `wp_usermeta.hv_subscription_*` | derivat din `contracts.status/expires_at` | – | – | Recalculat |
| pagini, articole, media WordPress | `pages`, `posts`, `services`, `media` | slug | `SiteContentSeeder` (din site-ul public) | Făcut |
| opțiunea `hv_abon_settings` | `settings` | – | – | Valori implicite din cod; de comparat cu producția |
| comenzi WooCommerce | – | – | – | Nu se migrează (plata online a fost eliminată în v2.6.2) |

## Stări

| WordPress | HiKeVet Next |
|---|---|
| `hv_payment_status = awaiting_clinic` | `contracts.status = awaiting_clinic` |
| `hv_payment_status = paid`, neexpirat | `active` |
| `hv_payment_status = paid`, expirat | `expired` |
| (inexistent în WordPress) | `offer_lapsed`, `suspended`, `cancelled` |
| `hv_consent_status` pending / signed / cancelled | identic |
| `status_caz`, `status_interventie` din dosare | copiate neschimbat |

## Ce se transformă și ce nu

- **Neschimbat:** fiecare rând din `hv_st_cases` (JSON complet + amprentă), payload-ul consimțămintelor
  semnate, semnăturile, tranzacțiile din registru cu ID-ul și data originală.
- **Transformat:** datele `0000-00-00` devin gol în coloanele de căutare (valoarea originală rămâne
  în rândul sursă); greutatea cu virgulă devine număr; emailurile devin litere mici.
- **Criptat la import:** payload-ul contractelor și consimțămintelor, semnăturile.
- **Neimportat:** semnătura din `hv_payload` al contractelor (rămâne în PDF-ul original), sesiuni,
  cache, comenzi WooCommerce.

## Incertitudini care se lămuresc doar cu exportul real

1. Prefixul și eventualele coloane în plus sau în minus față de codul 2.7.6 / 3.1.0.
2. Formatul exact al căilor din `poza_pacient` și `documente_scanate` (URL complet sau cale relativă).
3. Dacă există solduri negative sau tranzacții fără client (importul le numără și le raportează).
4. Câmpurile adăugate de modulul de consimțământ 1.5.x.
5. Numărul real de dosare pe lună față de rapoartele trimise Primăriei.
