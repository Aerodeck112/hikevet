# Scheme tabele custom WordPress

Generat de `tools/audit/table_schemas.py`. Prefixul real (`wp_`) este variabil.

## `hv_wallet_tx` (11 coloane) — hikevet-client-wallet-core

| Coloana | Tip | NULL |
|---|---|---|
| `id` | BIGINT UNSIGNED | nu |
| `user_id` | BIGINT UNSIGNED | nu |
| `type` | VARCHAR(30) | nu |
| `balance_type` | VARCHAR(30) | nu |
| `amount_delta` | DECIMAL(12,2) | nu |
| `points_delta` | INT | nu |
| `note` | TEXT | da |
| `related_type` | VARCHAR(50) | da |
| `related_id` | BIGINT UNSIGNED | da |
| `actor_user_id` | BIGINT UNSIGNED | da |
| `created_at` | DATETIME | nu |

Chei: `PRIMARY KEY (id)`; `KEY user_id (user_id)`; `KEY type (type)`; `KEY balance_type (balance_type)`

## `hv_treatments` (22 coloane) — hikevet-client-wallet-core

| Coloana | Tip | NULL |
|---|---|---|
| `id` | BIGINT UNSIGNED | nu |
| `user_id` | BIGINT UNSIGNED | nu |
| `pet_name` | VARCHAR(191) | da |
| `treatment_name` | VARCHAR(191) | nu |
| `price_standard` | DECIMAL(12,2) | nu |
| `wallet_used` | DECIMAL(12,2) | nu |
| `points_used` | INT | nu |
| `point_mode` | VARCHAR(30) | nu |
| `auto_points_per_unit` | INT | nu |
| `payment_category` | VARCHAR(30) | nu |
| `principal_used` | DECIMAL(12,2) | nu |
| `bonus_used` | DECIMAL(12,2) | nu |
| `bonus_balance_type` | VARCHAR(30) | da |
| `bonus_blocked` | TINYINT(1) | nu |
| `notes` | TEXT | da |
| `actor_user_id` | BIGINT UNSIGNED | da |
| `deleted_at` | DATETIME | da |
| `deleted_by` | BIGINT UNSIGNED | da |
| `delete_reason` | TEXT | da |
| `restored_at` | DATETIME | da |
| `restored_by` | BIGINT UNSIGNED | da |
| `created_at` | DATETIME | nu |

Chei: `PRIMARY KEY (id)`; `KEY user_id (user_id)`; `KEY treatment_name (treatment_name)`

## `hv_notifications` (8 coloane) — hikevet-client-wallet-core

| Coloana | Tip | NULL |
|---|---|---|
| `id` | BIGINT UNSIGNED | nu |
| `user_id` | BIGINT UNSIGNED | nu |
| `subject` | VARCHAR(191) | nu |
| `message` | LONGTEXT | nu |
| `channel` | VARCHAR(30) | nu |
| `related_type` | VARCHAR(50) | da |
| `related_id` | BIGINT UNSIGNED | da |
| `created_at` | DATETIME | nu |

Chei: `PRIMARY KEY (id)`; `KEY user_id (user_id)`

## `hv_email_log` (10 coloane) — hikevet-client-wallet-core

| Coloana | Tip | NULL |
|---|---|---|
| `id` | BIGINT UNSIGNED | nu |
| `user_id` | BIGINT UNSIGNED | da |
| `to_email` | TEXT | nu |
| `subject` | VARCHAR(191) | nu |
| `context` | VARCHAR(80) | da |
| `status` | VARCHAR(20) | nu |
| `error_message` | TEXT | da |
| `related_type` | VARCHAR(50) | da |
| `related_id` | BIGINT UNSIGNED | da |
| `created_at` | DATETIME | nu |

Chei: `PRIMARY KEY (id)`; `KEY user_id (user_id)`; `KEY context (context)`; `KEY status (status)`; `KEY created_at (created_at)`

## `hv_audit_log` (10 coloane) — hikevet-client-wallet-core

| Coloana | Tip | NULL |
|---|---|---|
| `id` | BIGINT UNSIGNED | nu |
| `user_id` | BIGINT UNSIGNED | nu |
| `entity_type` | VARCHAR(50) | nu |
| `entity_id` | BIGINT UNSIGNED | nu |
| `action` | VARCHAR(50) | nu |
| `summary` | TEXT | da |
| `before_value` | LONGTEXT | da |
| `after_value` | LONGTEXT | da |
| `actor_user_id` | BIGINT UNSIGNED | nu |
| `created_at` | DATETIME | nu |

Chei: `PRIMARY KEY (id)`; `KEY user_id (user_id)`; `KEY entity_type (entity_type)`; `KEY action (action)`; `KEY created_at (created_at)`

## `hv_doctor_notes` (11 coloane) — hikevet-client-wallet-core

| Coloana | Tip | NULL |
|---|---|---|
| `id` | BIGINT UNSIGNED | nu |
| `user_id` | BIGINT UNSIGNED | nu |
| `pet_id` | BIGINT UNSIGNED | nu |
| `note_text` | LONGTEXT | nu |
| `priority` | VARCHAR(20) | nu |
| `note_status` | VARCHAR(20) | nu |
| `actor_user_id` | BIGINT UNSIGNED | nu |
| `created_at` | DATETIME | nu |
| `updated_at` | DATETIME | da |
| `deleted_at` | DATETIME | da |
| `deleted_by` | BIGINT UNSIGNED | nu |

Chei: `PRIMARY KEY (id)`; `KEY user_id (user_id)`; `KEY pet_id (pet_id)`; `KEY note_status (note_status)`; `KEY deleted_at (deleted_at)`

## `hv_st_cases` (193 coloane) — hikevet-sterilization-campaign

| Coloana | Tip | NULL |
|---|---|---|
| `id` | BIGINT UNSIGNED | nu |
| `cod_caz` | VARCHAR(50) | nu |
| `data_programare` | DATE | da |
| `ora_programare` | VARCHAR(10) | da |
| `initial_appointment_id` | BIGINT UNSIGNED | da |
| `initial_appointment_synced_at` | DATETIME | da |
| `visit_group_id` | BIGINT UNSIGNED | da |
| `shared_consent_id` | BIGINT UNSIGNED | da |
| `data_prezentare` | DATE | da |
| `nume_proprietar` | VARCHAR(191) | da |
| `telefon` | VARCHAR(60) | da |
| `email` | VARCHAR(191) | da |
| `adresa_ci` | TEXT | da |
| `serie_numar_ci` | VARCHAR(100) | da |
| `localitate` | VARCHAR(120) | da |
| `specie` | VARCHAR(50) | da |
| `sex` | VARCHAR(50) | da |
| `nume_pacient` | VARCHAR(191) | da |
| `rasa` | VARCHAR(191) | da |
| `culoare_semnalmente` | TEXT | da |
| `varsta_estimata` | VARCHAR(80) | da |
| `greutate_estimativa` | DECIMAL(8,2) | da |
| `animal_cu_stapan` | VARCHAR(50) | da |
| `microcip` | VARCHAR(191) | da |
| `carnet_sanatate` | VARCHAR(50) | da |
| `vaccinat` | VARCHAR(20) | da |
| `data_vaccinare` | DATE | da |
| `observatii_initiale` | LONGTEXT | da |
| `status_caz` | VARCHAR(80) | nu |
| `procedura_programata` | VARCHAR(191) | da |
| `greutate_reala` | DECIMAL(8,2) | da |
| `temperatura` | DECIMAL(5,2) | da |
| `pacient_apt` | VARCHAR(80) | da |
| `motiv_amanare` | TEXT | da |
| `consimtamant_semnat` | VARCHAR(20) | da |
| `gdpr_semnat` | VARCHAR(20) | da |
| `analize_acceptate` | VARCHAR(20) | da |
| `analize_efectuate` | VARCHAR(20) | da |
| `inhalatorie_acceptata` | VARCHAR(20) | da |
| `risc_anestezic` | VARCHAR(50) | da |
| `observatii_preoperatorii` | LONGTEXT | da |
| `comportament_neobisnuit` | VARCHAR(20) | da |
| `comportament_detalii` | TEXT | da |
| `deparazitat` | VARCHAR(20) | da |
| `deparazitare_detalii` | TEXT | da |
| `diaree_voma` | VARCHAR(20) | da |
| `diaree_voma_detalii` | TEXT | da |
| `tip_hrana_select` | VARCHAR(60) | da |
| `tip_hrana_detalii` | TEXT | da |
| `frecventa_respiratorie` | VARCHAR(100) | da |
| `frecventa_cardiaca` | VARCHAR(100) | da |
| `culoare_mucoase` | TEXT | da |
| `istoric_medical` | LONGTEXT | da |
| `mentiuni_consult_preoperatorii` | LONGTEXT | da |
| `completat_preoperator_de` | VARCHAR(191) | da |
| `timestamp_preoperator` | DATETIME | da |
| `status_preoperator` | VARCHAR(80) | da |
| `data_interventie` | DATE | da |
| `data_raportare_primarie` | DATE | da |
| `ora_start` | VARCHAR(10) | da |
| `ora_final` | VARCHAR(10) | da |
| `procedura_efectuata` | VARCHAR(191) | da |
| `tip_anestezie` | VARCHAR(191) | da |
| `protocol_anestezic` | TEXT | da |
| `substanta1` | VARCHAR(191) | da |
| `doza1` | VARCHAR(100) | da |
| `substanta2` | VARCHAR(191) | da |
| `doza2` | VARCHAR(100) | da |
| `substanta3` | VARCHAR(191) | da |
| `doza3` | VARCHAR(100) | da |
| `alte_substante` | TEXT | da |
| `complicatii` | TEXT | da |
| `mentiuni_medicale` | LONGTEXT | da |
| `mentiuni_intra_postoperatorii` | LONGTEXT | da |
| `medic_operator` | VARCHAR(191) | da |
| `tehnician_asistent` | VARCHAR(191) | da |
| `status_postoperator` | VARCHAR(80) | da |
| `poza_pacient` | TEXT | da |
| `numar_pacient` | VARCHAR(80) | da |
| `numar_pacient_vechi` | VARCHAR(80) | da |
| `numar_pacient_auto_at` | DATETIME | da |
| `numar_pacient_raport` | VARCHAR(80) | da |
| `numar_pacient_raport_sursa` | VARCHAR(80) | da |
| `numar_pacient_raport_confirmat_at` | DATETIME | da |
| `numar_pacient_foto_confirmat` | VARCHAR(80) | da |
| `numar_pacient_foto_verificat_de` | VARCHAR(191) | da |
| `numar_pacient_foto_verificat_at` | DATETIME | da |
| `photo_upload_token` | VARCHAR(80) | da |
| `photo_uploaded_at` | DATETIME | da |
| `photo_upload_source` | VARCHAR(50) | da |
| `completat_interventie_de` | VARCHAR(191) | da |
| `timestamp_interventie` | DATETIME | da |
| `status_interventie` | VARCHAR(80) | da |
| `consimtamant_acceptare_proceduri` | VARCHAR(20) | da |
| `document_gdpr` | VARCHAR(20) | da |
| `ci_verificat` | VARCHAR(20) | da |
| `carnet_sau_fisa` | VARCHAR(20) | da |
| `documente_scanate` | TEXT | da |
| `observatii_documente` | LONGTEXT | da |
| `completat_documente_de` | VARCHAR(191) | da |
| `timestamp_documente` | DATETIME | da |
| `status_documente` | VARCHAR(80) | da |
| `data_externare` | DATE | da |
| `ora_externare` | VARCHAR(10) | da |
| `recomandari_postoperatorii` | VARCHAR(20) | da |
| `pachet_postoperator` | VARCHAR(20) | da |
| `control_recomandat` | VARCHAR(20) | da |
| `data_control` | DATE | da |
| `followup_efectuat` | VARCHAR(20) | da |
| `data_followup` | DATE | da |
| `evolutie_comunicat` | LONGTEXT | da |
| `observatii_externare` | LONGTEXT | da |
| `recomandari_postoperatorii_text` | LONGTEXT | da |
| `completat_externare_de` | VARCHAR(191) | da |
| `timestamp_externare` | DATETIME | da |
| `status_externare` | VARCHAR(80) | da |
| `postop_email_due_at` | DATETIME | da |
| `postop_email_sent_at` | DATETIME | da |
| `postop_email_status` | VARCHAR(20) | da |
| `postop_email_error` | TEXT | da |
| `postop_email_manual_at` | DATETIME | da |
| `final_summary_email_sent_at` | DATETIME | da |
| `final_summary_email_status` | VARCHAR(20) | da |
| `final_summary_email_error` | TEXT | da |
| `consent_id` | BIGINT UNSIGNED | da |
| `consent_synced_at` | DATETIME | da |
| `date_proprietar_complete` | VARCHAR(20) | da |
| `date_pacient_complete` | VARCHAR(20) | da |
| `consimtamant_complet` | VARCHAR(20) | da |
| `gdpr_complet` | VARCHAR(20) | da |
| `anestezie_completata` | VARCHAR(20) | da |
| `substante_completate` | VARCHAR(20) | da |
| `medic_operator_completat` | VARCHAR(20) | da |
| `poza_incarcata` | VARCHAR(20) | da |
| `complicatii_mentiuni_completate` | VARCHAR(20) | da |
| `validat_pentru_primarie` | VARCHAR(20) | nu |
| `validat_de` | VARCHAR(191) | da |
| `data_validare` | DATE | da |
| `observatii_validare` | LONGTEXT | da |
| `nr_fisa` | VARCHAR(80) | da |
| `data_fisa` | DATE | da |
| `cod_identificare` | VARCHAR(191) | da |
| `sterilizat` | VARCHAR(20) | da |
| `contact_persoana` | VARCHAR(191) | da |
| `contact_telefon` | VARCHAR(60) | da |
| `contact_email` | VARCHAR(191) | da |
| `clinica_curenta` | VARCHAR(191) | da |
| `circuit_doctor` | VARCHAR(191) | da |
| `circuit_doctor_tel` | VARCHAR(60) | da |
| `preluare_triaj` | VARCHAR(20) | da |
| `triaj_doctor` | VARCHAR(191) | da |
| `triaj_data` | DATE | da |
| `triaj_ora` | VARCHAR(10) | da |
| `trimitere_programare` | TEXT | da |
| `trimitere_doctor` | VARCHAR(191) | da |
| `trimitere_data` | DATE | da |
| `trimitere_ora` | VARCHAR(10) | da |
| `provenienta_anul` | TEXT | da |
| `alte_animale_acasa` | TEXT | da |
| `habitat` | VARCHAR(40) | da |
| `calatorii` | VARCHAR(20) | da |
| `calatorii_data` | DATE | da |
| `capuse` | VARCHAR(20) | da |
| `capuse_data` | DATE | da |
| `dieta_tip` | TEXT | da |
| `dieta_cantitate` | TEXT | da |
| `dieta_administrare` | TEXT | da |
| `vaccinari_la_zi` | VARCHAR(20) | da |
| `vaccinari_data` | DATE | da |
| `deparazitari_la_zi` | VARCHAR(20) | da |
| `deparazitari_data` | DATE | da |
| `boli_anterioare` | LONGTEXT | da |
| `operatii_anterioare` | LONGTEXT | da |
| `medicatie_curenta` | LONGTEXT | da |
| `suplimente` | VARCHAR(20) | da |
| `note_fisa` | LONGTEXT | da |
| `atentionari` | LONGTEXT | da |
| `responsabil_caz` | VARCHAR(191) | da |
| `dr_intern` | VARCHAR(191) | da |
| `student` | VARCHAR(191) | da |
| `motiv_prezentarii` | LONGTEXT | da |
| `debut_primele_semne` | LONGTEXT | da |
| `frecventa_durata` | LONGTEXT | da |
| `tratament_consult` | LONGTEXT | da |
| `evolutie_raspuns_med` | LONGTEXT | da |
| `note_examinare` | LONGTEXT | da |
| `status_fisa` | VARCHAR(80) | da |
| `completat_fisa_de` | VARCHAR(191) | da |
| `timestamp_fisa` | DATETIME | da |
| `creat_de` | VARCHAR(191) | da |
| `timestamp_creat` | DATETIME | nu |
| `actualizat_de` | VARCHAR(191) | da |
| `timestamp_actualizat` | DATETIME | nu |

Chei: `PRIMARY KEY (id)`; `UNIQUE KEY cod_caz (cod_caz)`; `KEY status_caz (status_caz)`; `KEY data_interventie (data_interventie)`; `KEY validat_pentru_primarie (validat_pentru_primarie)`; `KEY postop_email_due_at (postop_email_due_at)`; `KEY postop_email_sent_at (postop_email_sent_at)`; `KEY consent_id (consent_id)`; `KEY initial_appointment_id (initial_appointment_id)`; `KEY visit_group_id (visit_group_id)`; `KEY shared_consent_id (shared_consent_id)`

## `hv_st_appointments` (26 coloane) — hikevet-sterilization-campaign

| Coloana | Tip | NULL |
|---|---|---|
| `id` | BIGINT UNSIGNED | nu |
| `appointment_code` | VARCHAR(50) | nu |
| `case_id` | BIGINT UNSIGNED | da |
| `appointment_date` | DATE | nu |
| `start_time` | VARCHAR(10) | nu |
| `end_time` | VARCHAR(10) | da |
| `appointment_type` | VARCHAR(191) | nu |
| `title` | VARCHAR(191) | nu |
| `status` | VARCHAR(50) | nu |
| `owner_name` | VARCHAR(191) | da |
| `client_email` | VARCHAR(191) | da |
| `client_phone` | VARCHAR(60) | da |
| `patient_name` | VARCHAR(191) | da |
| `source` | VARCHAR(100) | da |
| `notes` | LONGTEXT | da |
| `reminder_enabled` | VARCHAR(20) | nu |
| `reminder_days` | INT | nu |
| `reminder_sent_at` | DATETIME | da |
| `reminder_manual_sent_at` | DATETIME | da |
| `email_last_status` | VARCHAR(20) | da |
| `email_last_at` | DATETIME | da |
| `email_last_error` | TEXT | da |
| `created_by` | VARCHAR(191) | da |
| `created_at` | DATETIME | nu |
| `updated_by` | VARCHAR(191) | da |
| `updated_at` | DATETIME | nu |

Chei: `PRIMARY KEY (id)`; `UNIQUE KEY appointment_code (appointment_code)`; `KEY case_id (case_id)`; `KEY appointment_date (appointment_date)`; `KEY status (status)`; `KEY reminder_sent_at (reminder_sent_at)`

## `hv_st_email_log` (10 coloane) — hikevet-sterilization-campaign

| Coloana | Tip | NULL |
|---|---|---|
| `id` | BIGINT UNSIGNED | nu |
| `appointment_id` | BIGINT UNSIGNED | da |
| `case_id` | BIGINT UNSIGNED | da |
| `to_email` | TEXT | nu |
| `cc_email` | TEXT | da |
| `subject` | VARCHAR(191) | nu |
| `email_type` | VARCHAR(80) | da |
| `status` | VARCHAR(20) | nu |
| `error_message` | TEXT | da |
| `created_at` | DATETIME | nu |

Chei: `PRIMARY KEY (id)`; `KEY appointment_id (appointment_id)`; `KEY case_id (case_id)`; `KEY status (status)`; `KEY created_at (created_at)`
