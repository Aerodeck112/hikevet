# Matrice: sursă WordPress → HiKeVet Next

Fiecare funcție importantă din pluginuri, cu locul unde a ajuns și testul care o acoperă.
Prescurtări ca în `PLUGIN_AUDIT.md`: SIM, WAL, CON, STE, urmate de linia din fișierul sursă.

## Simulator abonamente

| Sursă | În Laravel | Test |
|---|---|---|
| `tier_from_weight` SIM:814 | `PricingEngine::tierFor`, tabelele `price_lists`, `subscription_tiers` | `PricingEngineTest`, `SimulatorParityTest::test_every_weight_step_matches_the_plugin` |
| `server_discount_info` SIM:849 | `PricingEngine::offer` | `SimulatorParityTest::test_multi_pet_discount_matches_the_plugin_for_all_combinations` |
| `base_services`, `apply_mods` SIM:149–219 | `subscription_tier_services`, `PriceListSeeder` | `PricingEngineTest::test_tier_specific_service_adjustments`, `…no_deworming_service_is_included` |
| `contract_template_lines` SIM:542 | `contract_templates`, `contract-template-2.7.0.json` | `SimulatorParityTest::test_contract_template_is_identical_to_the_plugin` |
| `ajax_send` SIM:910 | `ConfiguratorController::submit`, `SubmitContractRequest`, `ContractService::submit` | `ContractFlowTest` (13 teste) |
| token anti-dublare SIM:880 | `contracts.submission_token` (unic) | `…same_submission_token_does_not_create_a_second_contract` |
| suprascrierea prețurilor din browser SIM:1000+ | prețurile vin doar din `PricingEngine` | `…prices_sent_by_the_browser_are_ignored` |
| `pdf_build` SIM:626 | `ContractPdf` + `resources/views/pdf/contract.blade.php` (dompdf) | `…guest_submits_contract_and_gets_account_pet_pdf…` |
| PDF în `uploads/hikevet-contracte/` | disc privat, descărcare cu verificare | `…only_the_owner_can_download_the_contract_pdf` |
| `existing_pets_for_owner_email` SIM:838 | reducerea se aplică doar clientului autentificat | `…guest_cannot_attach_a_contract_or_get_a_discount_using_someone_elses_email` |
| emailuri către clinică și client | `Notifier`, `email_logs` | `ContractFlowTest` (Mail fake) |
| `hv-form.js` | `resources/js/configurator.js`, `signature.js`, `price-calc.js` | verificat manual în browser |

## Wallet Core

| Sursă | În Laravel | Test |
|---|---|---|
| `ensure_client_user` WAL:1177, `process_submission_post` WAL:1587 | `ClientAccounts`, apelat din `ContractService` | `ContractFlowTest` |
| `handle_validate_subscription` WAL:4275 | `SubscriptionActivation::validatePayment` | `…clinic_validates_payment_points_ignore_period_discount…`, `…payment_cannot_be_validated_twice…` |
| `check_subscription_expiry_notifications` WAL:1980 | `SubscriptionActivation::runDailyMaintenance`, comanda `hikevet:daily` | `…daily_maintenance_expires_subscriptions_and_lapses_unpaid_offers` |
| `handle_wallet_credit` WAL:4439 | `WalletLedger::deposit` | `WalletLedgerTest::…deposit_of_100_gives_100_principal_100_service_bonus_50_product_bonus` |
| `calculate_wallet_purchase` WAL:863 | `WalletLedger::calculatePurchase`, `purchase` | `…service_purchase_uses_bonus_for_at_most_half_and_product_for_at_most_a_third` |
| cuvinte care exclud bonusul WAL:858 | `WalletLedger` | `…bonus_is_excluded_for_medication_and_external_cost_items…` |
| `handle_add_treatment` WAL:4453 | `WalletLedger::consumeSubscriptionService` | `…subscription_service_consumes_points_only_and_refuses_overdraft_and_deworming` |
| `treatment_catalog` WAL:542 | `treatment-catalog.json` | același test |
| `handle_manual_points_adjust` WAL:4132 | `WalletLedger::manualAdjust` | `…manual_adjust_requires_full_employee_name_and_never_goes_negative` |
| ștergere și restaurare WAL:2725–2920 | `WalletLedger::deleteTreatment`, `restoreTreatment` | `…deleting_a_treatment_refunds_and_restoring_reapplies_it` |
| `handle_front_wallet_request` WAL:1720 | `DashboardController::requestTopup`, `wallet_topup_requests` | `…client_topup_request_does_not_change_the_balance` |
| panoul clientului (shortcode) | `DashboardController`, `account/dashboard.blade.php` | `ContractFlowTest`, verificare manuală |
| panoul medicilor | `ClientResource` + `ViewClient` (Filament) | `AdminPanelTest::…client_record_page_renders_with_contract_and_balances` |
| `hv_audit_log` | `audit_logs`, `App\Support\Audit` | `ContractFlowTest::…cnp_is_encrypted_at_rest_and_never_reaches_the_audit_log` |
| `hv_notifications` | `client_notifications` | – |
| `hv_doctor_notes` | `doctor_notes`, acțiune în fișa clientului | – |
| graficele din v3.1.0 | **neportat** | – |

## Consimțământ pe tabletă

| Sursă | În Laravel | Test |
|---|---|---|
| `defaults` CON:104 | `ConsentService`, formularul din `ConsentResource` | `ConsentTest::…code_default_anesthesia_and_default_date` |
| `create_code` CON:272, `find_consent_by_code` CON:294 | `ConsentService::create`, `findByCode` (HMAC, expirare, limită de încercări) | `…wrong_expired_and_unknown_codes_are_rejected_with_the_same_message`, `…code_entry_is_rate_limited` |
| textul documentului | `resources/views/consent/document.blade.php` (neschimbat) | `…tablet_flow_code_opens_document_and_signature_locks_it` |
| `handle_tablet_submit` CON:1168 | `ConsentTabletController::sign`, `ConsentService::sign` | `…gdpr_and_every_final_confirmation_are_mandatory`, `…refused_services_require_a_separate_refusal_signature` |
| `handle_save_doctor_signature` CON:733 | `ConsentService::doctorSign` | `…doctor_signature_can_be_added_after_owner_signed_without_touching_the_document` |
| vizita cu mai mulți pacienți | `visit_patients` în payload | `…shared_visit_consent_lists_all_patients_and_correction_creates_a_new_document` |
| (inexistent) amprentă de integritate | `ConsentService::verifyIntegrity` | `…tampering_with_a_signed_payload_is_detected` |
| tipărire | `consent/print.blade.php`, ruta `admin.consents.print` | `…admin_screens_and_print_view_respect_permissions` |
| funcțiile 1.5.x (filtre avansate, „înapoi la dosar”, sincronizarea identității) | **necesită sursa** | – |

## Campanie sterilizare

| Sursă | În Laravel | Test |
|---|---|---|
| `hv_st_cases` STE:173 | `sterilization_cases` (doar citire) | `WordPressImportTest::…real_import_keeps_every_source_row_identical_and_flags_what_is_missing` |
| `hv_st_appointments`, `hv_st_email_log` | `sterilization_appointments`, `sterilization_email_logs` | același test |
| fotografii și acte | `sterilization_documents`, disc privat | același test |
| listele de coduri raportate | `sterilization_reference_lists` | `…import_is_idempotent_and_can_be_rolled_back` |
| lista și fișa dosarului | `SterilizationCaseResource` | `…archive_admin_is_read_only_filtered_and_exports_are_logged` |
| raport lunar (centralizator, desfășurător) | `ArchiveExporter::monthlyReport`, pagina `SterilizationReports` | același test |
| export | `ArchiveExporter::csv`, `caseZip` | același test |
| export PDF / XLSX în formatul Primăriei | **neportat** | – |
| programare, prezentare, intervenție, externare, validare, emailuri, cron | **neportat intenționat** (arhivă istorică) | – |
| reparare automată, renumerotare, validare în masă, ștergere | **neportat intenționat** (ar modifica dovezi) | `…archive_rows_are_read_only_through_the_model` |

## Site și platformă

| Sursă | În Laravel | Test |
|---|---|---|
| pagini Elementor | `pages`, `services`, `posts`, `faqs`, `team_members` + `resources/views/site` | `PublicSiteTest::…every_public_wordpress_url_still_answers` |
| adrese WordPress | `PublicRedirects`, `redirects`, `site_url()` | `…retired_wordpress_urls_redirect_permanently`, `…content_pages_get_a_canonical_trailing_slash` |
| Site Kit (GTM) | setări Marketing + acord | `…tracking_scripts_load_only_when_configured_and_wait_for_consent` |
| linkuri Digitail | `App\Support\Booking` | `BookingTest::…public_page_redirects_to_digitail_until_internal_mode_is_enabled` |
| roluri WordPress | `App\Enums\Role`, `Permission`, spatie/permission | `AdminPanelTest` |
| parole WordPress | `WordPressPasswordVerifier` | `WordPressImportTest::…migrated_client_logs_in_with_the_wordpress_password_and_is_rehashed` |
