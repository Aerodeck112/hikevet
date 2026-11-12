# Inventar automat pluginuri WordPress

Generat de `tools/audit/plugin_inventory.py` din `source-wordpress/`. Nu edita manual.

## hikevet-abonament-simulator

- Fisier: `source-wordpress/hikevet-abonament-simulator/hikevet-abonament-simulator.php` (1102 linii)
- Versiune in cod: `2.7.0`

### Constante

| Linie | Constanta | Valoare |
|---|---|---|
| 11 | `OPT_KEY` | `'hv_abon_settings'` |

### Shortcode-uri

| Cheie | Linii |
|---|---|
| `hikevet_abonament_form` | 46 |

### Actiuni admin-post / AJAX

| Cheie | Linii |
|---|---|
| `wp_ajax_hv_abon_send` | 47 |
| `wp_ajax_nopriv_hv_abon_send` | 48 |
| `wp_ajax_hv_abon_get_nonce` | 49 |
| `wp_ajax_nopriv_hv_abon_get_nonce` | 50 |

### Hook-uri custom si cron

| Cheie | Linii |
|---|---|
| `hv_abon_submission_ready` | 1061 |

### Tipuri de post (CPT)

| Cheie | Linii |
|---|---|
| `hv_abon_sub` | 77 |

### Roluri si capabilitati

| Cheie | Linii |
|---|---|
| `manage_options` | 132 |

### Options

| Cheie | Linii |
|---|---|
| `admin_email` | 57 |

### Post meta

| Cheie | Linii |
|---|---|
| `hv_pdf` | 1049 |
| `hv_species` | 1050 |
| `hv_tier` | 1051 |
| `hv_total_normal` | 1052 |
| `hv_total_abon` | 1053 |
| `hv_submission_token` | 1054 |
| `hv_payload` | 1055 |
| `hv_submission_ready` | 1056 |
| `hv_payment_method` | 1057 |
| `hv_payment_status` | 1058 |
| `hv_payment_period_months` | 1059 |
| `hv_payment_amount` | 1060 |
| `hv_mail_clinic_status` | 1085 |
| `hv_mail_clinic_error` | 1086 |
| `hv_mail_client_status` | 1087 |
| `hv_mail_client_error` | 1088 |

### User meta

| Cheie | Linii |
|---|---|
| `hv_pets` | 227, 845 |
| `first_name` | 250 |
| `last_name` | 250 |
| `billing_phone` | 252 |
| `phone` | 253 |
| `billing_address_1` | 255 |
| `address` | 256 |

### Functii (33)

| Linie | Functie | Vizibilitate |
|---|---|---|
| 13 | `ascii()` | private |
| 27 | `mail_failed()` | public |
| 34 | `ajax_get_nonce()` | public |
| 41 | `__construct()` | public |
| 54 | `defaults()` | public static |
| 69 | `get_settings()` | public static |
| 76 | `register_cpt()` | public |
| 83 | `admin_menu()` | public |
| 87 | `admin_settings()` | public |
| 103 | `add_field()` | private |
| 116 | `sanitize_settings()` | public |
| 131 | `render_settings()` | public |
| 140 | `enqueue()` | public |
| 149 | `base_services()` | private |
| 188 | `apply_mods()` | private |
| 222 | `get_logged_in_client_pets_for_configurator()` | private |
| 245 | `get_prefill_owner_for_logged_user()` | private |
| 266 | `absolute_site_url()` | private |
| 274 | `legal_urls()` | private |
| 289 | `shortcode()` | public |
| 542 | `contract_template_lines()` | private |
| 598 | `pdf_escape()` | private |
| 603 | `pdf_text()` | private |
| 607 | `wrap_lines()` | private |
| 618 | `pdf_header()` | private |
| 626 | `pdf_build()` | private |
| 814 | `tier_from_weight()` | private |
| 838 | `existing_pets_for_owner_email()` | private |
| 849 | `server_discount_info()` | private |
| 880 | `existing_submission_by_token()` | private |
| 889 | `signature_png_to_jpg()` | private |
| 903 | `payment_amount_for_period()` | private |
| 910 | `ajax_send()` | public |

## hikevet-client-wallet-core

- Fisier: `source-wordpress/hikevet-client-wallet-core/hikevet-client-wallet-core.php` (4785 linii)
- Versiune in cod: `3.1.0`

### Constante

| Linie | Constanta | Valoare |
|---|---|---|
| 12 | `VER` | `'3.1.0'` |
| 13 | `ROLE_CLIENT` | `'hikevet_client'` |
| 14 | `ROLE_DOCTOR` | `'hikevet_doctor'` |
| 15 | `TABLE_WALLET` | `'hv_wallet_tx'` |
| 16 | `TABLE_TREAT` | `'hv_treatments'` |
| 17 | `TABLE_NOTIF` | `'hv_notifications'` |
| 18 | `TABLE_EMAIL_LOG` | `'hv_email_log'` |
| 19 | `TABLE_AUDIT` | `'hv_audit_log'` |
| 20 | `TABLE_NOTES` | `'hv_doctor_notes'` |
| 21 | `META_SUB_ACTIVE` | `'hv_subscription_active'` |
| 2149 | `escapeHtml` | `(value)=>String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp` |
| 2153 | `modal` | `document.getElementById(button.dataset.hvOpenModal)` |
| 2159 | `modal` | `button.closest('.hv-modal')` |
| 2166 | `box` | `input.parentElement.querySelector('.hv-live-results')` |
| 2169 | `q` | `input.value.trim()` |
| 2173 | `url` | `ajaxurl+'?action=hv_doctor_live_search&nonce='+encodeURIComponent(input.dataset.` |
| 2174 | `response` | `await fetch(url,{credentials:'same-origin'})` |
| 2175 | `json` | `await response.json()` |
| 2176 | `items` | `json?.data?.items||[]` |
| 2190 | `form` | `button.closest('.postbox')?.querySelector('.hv-v3-collapsible')` |
| 2196 | `row` | `document.getElementById(button.dataset.target)` |
| 2202 | `form` | `document.getElementById(button.dataset.target)` |
| 2208 | `wrap` | `document.querySelector('.wrap')` |
| 2213 | `title` | `wrap.querySelector(':scope > h1')` |
| 2214 | `intro` | `title?title.nextElementSibling:null` |
| 2215 | `hero` | `document.createElement('section')` |
| 2222 | `modal` | `document.getElementById('hv-create-client-modal')` |
| 2225 | `grid` | `Array.from(wrap.querySelectorAll('div[style*="display:grid"]')).find(element=>el` |
| 2228 | `firstCard` | `grid.querySelector('.postbox')` |
| 2229 | `patientText` | `firstCard?.innerText||''` |
| 2230 | `nameMatch` | `patientText.match(/Nume:\s*([^\n]+)/i)` |
| 2231 | `emailMatch` | `patientText.match(/Email:\s*([^\n]+)/i)` |
| 2232 | `patientName` | `(nameMatch?.[1]||'Pacient').trim()` |
| 2233 | `patientEmail` | `(emailMatch?.[1]||'').trim()` |
| 2234 | `active` | `/Abonament activ:\s*Da/i.test(patientText)` |
| 2236 | `header` | `document.createElement('section')` |
| 2238 | `identity` | `document.createElement('div')` |
| 2240 | `avatar` | `document.createElement('div')` |
| 2243 | `identityText` | `document.createElement('div')` |
| 2244 | `strong` | `document.createElement('strong')` |
| 2246 | `small` | `document.createElement('span')` |
| 2250 | `status` | `document.createElement('span')` |
| 2256 | `cards` | `Array.from(grid.querySelectorAll('.postbox'))` |
| 2258 | `heading` | `(card.querySelector('h2,h3')?.textContent||'').toLowerCase()` |
| 2282 | `tabsBar` | `document.createElement('nav')` |
| 2292 | `button` | `document.createElement('button')` |
| 2302 | `fab` | `document.createElement('button')` |
| 2306 | `menu` | `document.createElement('div')` |
| 2310 | `button` | `document.createElement('button')` |
| 2323 | `allParagraphs` | `Array.from(form.querySelectorAll(':scope > p'))` |
| 2325 | `field` | `form.querySelector('[name="'+name+'"]')` |
| 2330 | `nodes` | `[]` |
| 2332 | `parent` | `fieldParent(name)` |
| 2338 | `submitParent` | `Array.from(allParagraphs).find(p=>p.querySelector('button[type="submit"],button:` |
| 2341 | `used` | `new Set(stepNodes.flat())` |
| 2346 | `head` | `document.createElement('div')` |
| 2349 | `button` | `document.createElement('button')` |
| 2358 | `footer` | `document.createElement('div')` |
| 2360 | `back` | `document.createElement('button')` |
| 2362 | `next` | `document.createElement('button')` |
| 2369 | `fields` | `stepNodes[index].flatMap(node=>Array.from(node.querySelectorAll('input,select,te` |
| 2389 | `treatmentForm` | `document.querySelector('form input[name="action"][value="hv_add_treatment"]')?.c` |
| 2391 | `summary` | `document.createElement('div')` |
| 2396 | `pet` | `treatmentForm.querySelector('[name="pet_id"]')` |
| 2397 | `treatment` | `treatmentForm.querySelector('[name="treatment_key"]')` |
| 2398 | `manual` | `treatmentForm.querySelector('[name="treatment_name"]')` |
| 2416 | `walletForm` | `document.querySelector('form input[name="action"][value="hv_wallet_purchase"]')?` |
| 2418 | `nameField` | `walletForm.querySelector('[name="purchase_name"]')` |
| 2420 | `chips` | `document.createElement('div')` |
| 2423 | `chip` | `document.createElement('button')` |
| 2431 | `summary` | `document.createElement('div')` |
| 2437 | `amount` | `Math.max(0,parseFloat(walletForm.querySelector('[name="sale_amount"]')?.value||0` |
| 2438 | `category` | `walletForm.querySelector('[name="purchase_category"]')?.value||'service'` |
| 2439 | `blocked` | `walletForm.querySelector('[name="bonus_blocked"]')?.checked` |
| 2440 | `availablePrincipal` | `Math.max(0,parseFloat(walletForm.dataset.principal||0))` |
| 2441 | `availableBonus` | `Math.max(0,parseFloat(category==='product'?walletForm.dataset.productBonus:walle` |
| 2442 | `rate` | `category==='product'?1/3:1/2` |
| 2443 | `bonus` | `blocked?0:Math.min(amount*rate,availableBonus)` |
| 2444 | `principal` | `Math.max(0,amount-bonus)` |
| 2464 | `petForm` | `document.querySelector('form input[name="action"][value="hv_admin_add_pet"]')?.c` |
| 2466 | `tier` | `petForm.querySelector('[name="tier_label"]')` |
| 2468 | `help` | `document.createElement('small')` |
| 2484 | `headers` | `Array.from(table.querySelectorAll('thead th')).map(th=>th.textContent.trim())` |
| 3948 | `petSel` | `document.getElementById("hv-treatment-pet-select")` |
| 3949 | `trSel` | `document.getElementById("hv-treatment-select")` |
| 3952 | `placeholder` | `{value:"",text:"Selectează tratamentul",disabled:false}` |
| 3963 | `pet` | `String(petSel.value||"")` |
| 3965 | `first` | `document.createElement("option")` |
| 3972 | `option` | `document.createElement("option")` |
| 4608 | `app` | `document.getElementById('hv-access-app')` |
| 4609 | `ajax` | `app.dataset.ajax` |
| 4610 | `nonce` | `app.dataset.nonce||''` |
| 4612 | `box` | `app.querySelector('.hv-auth-msg[data-for="'+type+'"]')` |
| 4620 | `fd` | `new FormData(form)` |
| 4621 | `r` | `await fetch(ajax,{method:'POST',credentials:'same-origin',body:fd,cache:'no-stor` |
| 4624 | `login` | `document.getElementById('hv-ajax-login-form')` |
| 4628 | `btn` | `login.querySelector('button')` |
| 4630 | `j` | `await post('hv_auth_login', login)` |
| 4637 | `reg` | `document.getElementById('hv-ajax-register-form')` |
| 4641 | `btn` | `reg.querySelector('button')` |
| 4643 | `j` | `await post('hv_auth_register', reg)` |

### Tabele create (dbDelta)

| Linie | Variabila tabel |
|---|---|
| 417 | `$wallet` |
| 435 | `$treat` |
| 463 | `$notif` |
| 480 | `$email_log` |
| 500 | `$audit` |
| 518 | `$notes` |

### Shortcode-uri

| Cheie | Linii |
|---|---|
| `hikevet_client_dashboard` | 69 |
| `hikevet_client_register` | 70 |
| `hikevet_client_auth_links` | 71 |
| `hikevet_client_login` | 72 |
| `hikevet_client_account_box` | 73 |
| `hikevet_client_access` | 74 |

### Actiuni admin-post / AJAX

| Cheie | Linii |
|---|---|
| `admin_post_hv_wallet_credit` | 34 |
| `admin_post_hv_add_treatment` | 35 |
| `admin_post_hv_wallet_purchase` | 36 |
| `admin_post_hv_validate_subscription` | 37 |
| `admin_post_hv_manual_points_adjust` | 38 |
| `admin_post_hv_send_subscription_payment_notice` | 39 |
| `admin_post_hv_run_expiry_notifications` | 40 |
| `admin_post_hv_run_system_health_check` | 41 |
| `admin_post_hv_admin_add_pet` | 42 |
| `admin_post_hv_admin_update_pet` | 43 |
| `admin_post_hv_admin_delete_pet` | 44 |
| `admin_post_hv_admin_restore_pet` | 45 |
| `admin_post_hv_admin_update_owner` | 46 |
| `admin_post_hv_admin_create_client` | 47 |
| `admin_post_hv_admin_delete_treatment` | 48 |
| `admin_post_hv_admin_restore_treatment` | 49 |
| `admin_post_hv_admin_add_note` | 50 |
| `admin_post_hv_admin_delete_note` | 51 |
| `admin_post_hv_admin_toggle_note` | 52 |
| `wp_ajax_hv_doctor_live_search` | 53 |
| `admin_post_hv_resend_activation` | 58 |
| `admin_post_hv_resend_activation_submission` | 59 |
| `wp_ajax_nopriv_hv_auth_login` | 82 |
| `wp_ajax_nopriv_hv_auth_register` | 83 |
| `wp_ajax_hv_auth_logout` | 84 |
| `wp_ajax_hv_auth_status` | 85 |
| `wp_ajax_nopriv_hv_auth_status` | 86 |

### Hook-uri custom si cron

| Cheie | Linii |
|---|---|
| `hv_daily_subscription_expiry_check` | 54, 361 |
| `hv_daily_system_health_check` | 55, 368 |
| `hv_abon_submission_ready` | 66 |

### Roluri si capabilitati

| Cheie | Linii |
|---|---|
| `manage_hikevet_sterilization` | 189, 387 |
| `manage_hikevet_wallet` | 190, 386, 392, 397, 1676, 1702 |
| `manage_options` | 191, 397, 1676, 1702 |
| `read` | 384 |
| `edit_posts` | 385 |

### Options

| Cheie | Linii |
|---|---|
| `hv_client_wallet_db_ver` | 347, 398, 401 |
| `hv_last_system_health_check` | 1037, 3803 |
| `hv_abon_settings` | 1740, 1902 |
| `admin_email` | 1909 |

### Post meta

| Cheie | Linii |
|---|---|
| `hv_client_user_id` | 1559, 1614, 1780 |
| `hv_pdf` | 1562, 1600, 1783 |
| `hv_payload` | 1566, 1597, 1769, 1844, 1850, 1870 |
| `hv_payment_method` | 1577, 1637, 4327 |
| `hv_payment_status` | 1578, 1638, 4328 |
| `hv_payment_period_months` | 1579, 1639, 4330 |
| `hv_wc_order_id` | 1580, 1640 |
| `hv_client_sync_done` | 1591, 1663 |
| `hv_submission_ready` | 1601 |
| `hv_last_activation_manual_resend` | 1813, 1875 |
| `hv_payment_paid_at` | 4329 |
| `hv_payment_amount` | 4331 |

### User meta

| Cheie | Linii |
|---|---|
| `hv_wallet_promo_migrated_v1` | 785, 797 |
| `hv_last_validated_subscription_at` | 817, 4324 |
| `hv_subscription_expires_at` | 818, 1917, 3107, 3442, 3704, 3762, 4325 |
| `hv_last_activation_sent` | 1166, 3834 |
| `hv_pets` | 1415, 1467 |
| `hv_contracts` | 1536, 1555, 3761 |
| `billing_phone` | 1617, 2588, 2627, 2644, 3063, 3482, 3679, 3763 |
| `billing_address_1` | 1620, 2628, 2645, 3483, 3681 |
| `hv_last_payment_notice_sent` | 1975 |
| `billing_city` | 2629, 2646, 3484, 3682 |
| `hv_owner_internal_notes` | 2630, 2647, 3485 |
| `billing_first_name` | 2643 |
| `hv_last_validated_subscription_amount` | 4304, 4321 |
| `hv_last_validated_submission_id` | 4306, 4322 |
| `hv_last_validated_period_months` | 4308, 4323 |

### Tipuri tranzactie wallet (wallet_tx)

| Cheie | Linii |
|---|---|
| `legacy_wallet_close` | 792 |
| `wallet_migration_principal` | 793 |
| `wallet_migration_service_bonus` | 794 |
| `wallet_migration_product_bonus` | 795 |
| `subscription_validated` | 4317 |
| `wallet_deposit_principal` | 4445 |
| `wallet_bonus_services` | 4446 |
| `wallet_bonus_products` | 4447 |
| `treatment_consume` | 4462 |
| `wallet_purchase_principal` | 4476 |

### Functii (144)

| Linie | Functie | Vizibilitate |
|---|---|---|
| 23 | `__construct()` | public |
| 95 | `is_doctor_user()` | private |
| 119 | `ensure_doctor_admin_access()` | public |
| 147 | `allow_doctor_woocommerce_admin()` | public |
| 151 | `allow_doctor_admin_bar()` | public |
| 155 | `doctor_login_redirect()` | public |
| 173 | `doctor_woocommerce_login_redirect()` | public |
| 180 | `is_client_only_user()` | private |
| 196 | `hide_admin_bar_for_clients()` | public |
| 205 | `block_wp_admin_for_clients()` | public |
| 215 | `no_cache_front()` | private |
| 222 | `ajax_auth_login()` | public |
| 260 | `ajax_auth_register()` | public |
| 320 | `ajax_auth_logout()` | public |
| 326 | `ajax_auth_status()` | public |
| 339 | `db_table()` | private |
| 344 | `activate()` | public |
| 352 | `deactivate()` | public static |
| 359 | `ensure_expiry_cron_scheduled()` | public |
| 365 | `ensure_health_cron_scheduled()` | public |
| 372 | `create_roles()` | private |
| 396 | `maybe_update_tables()` | public |
| 405 | `create_tables()` | public |
| 542 | `treatment_catalog()` | private |
| 721 | `normalize_treatment_species()` | private |
| 728 | `resolve_treatment_tier()` | private |
| 768 | `get_treatment_items()` | private |
| 775 | `get_balance()` | private |
| 783 | `ensure_wallet_promo_migrated()` | private |
| 800 | `get_wallet_promo_balances()` | private |
| 808 | `get_full_balances()` | private |
| 815 | `user_has_active_subscription()` | private |
| 822 | `set_subscription_active()` | private |
| 826 | `current_time_mysql()` | private |
| 830 | `sanitize_amount()` | private |
| 834 | `get_wallet_balance()` | private |
| 839 | `wallet_tx()` | private |
| 858 | `wallet_bonus_exclusion_reason()` | private |
| 863 | `calculate_wallet_purchase()` | private |
| 870 | `log_email()` | private |
| 887 | `send_logged_mail()` | private |
| 904 | `unique_email_list()` | private |
| 915 | `get_client_email_copy_recipients()` | private |
| 923 | `get_client_mail_confirmation_recipients()` | private |
| 929 | `send_client_mail_with_clinic_copies()` | private |
| 994 | `run_daily_system_health_check()` | public |
| 1046 | `handle_run_system_health_check()` | public |
| 1056 | `render_email_log_page()` | public |
| 1114 | `add_notification()` | private |
| 1144 | `username_from_email()` | private |
| 1157 | `send_activation_email()` | private |
| 1170 | `get_set_password_link()` | private |
| 1177 | `ensure_client_user()` | private |
| 1213 | `next_pet_discount_info_for_user()` | private |
| 1238 | `calculate_discounted_prices_from_payload()` | private |
| 1265 | `handle_front_add_pet()` | public |
| 1327 | `pet_form_shortcode_html()` | private |
| 1350 | `uploads_url_from_path()` | private |
| 1361 | `derive_tier_label()` | private |
| 1381 | `upload_pet_photo()` | private |
| 1403 | `pet_avatar_html()` | private |
| 1414 | `get_user_pets()` | private |
| 1442 | `save_user_pets()` | private |
| 1470 | `get_pet_by_id()` | private |
| 1478 | `upsert_pet_for_user()` | private |
| 1535 | `get_user_contracts()` | private |
| 1540 | `add_contract_to_user()` | private |
| 1558 | `sync_contract_pdf_for_submission()` | private |
| 1587 | `process_submission_post()` | private |
| 1666 | `watch_submission_meta()` | public |
| 1671 | `handle_submission_ready()` | public |
| 1675 | `maybe_repair_subscription_links()` | public |
| 1695 | `on_submission_saved()` | public |
| 1701 | `can_manage()` | private |
| 1706 | `handle_resend_activation_admin()` | public |
| 1720 | `handle_front_wallet_request()` | public |
| 1755 | `handle_front_resend_activation()` | public |
| 1768 | `ensure_user_from_submission()` | private |
| 1803 | `handle_resend_activation_submission()` | public |
| 1830 | `submission_columns()` | public |
| 1842 | `submission_custom_column()` | public |
| 1865 | `add_submission_metabox()` | public |
| 1869 | `render_submission_metabox()` | public |
| 1901 | `get_clinic_emails()` | private |
| 1915 | `get_contract_expiry_date()` | private |
| 1931 | `handle_send_subscription_payment_notice()` | public |
| 1980 | `check_subscription_expiry_notifications()` | public |
| 2042 | `handle_run_expiry_notifications()` | public |
| 2052 | `doctor_admin_modern_styles()` | public |
| 2142 | `doctor_admin_modern_scripts()` | public |
| 2497 | `audit_log()` | private |
| 2514 | `get_audit_rows()` | private |
| 2526 | `get_doctor_notes()` | private |
| 2539 | `admin_redirect_patient()` | private |
| 2545 | `handle_admin_create_client()` | public |
| 2600 | `handle_admin_update_owner()` | public |
| 2654 | `handle_admin_delete_pet()` | public |
| 2692 | `handle_admin_restore_pet()` | public |
| 2725 | `handle_admin_delete_treatment()` | public |
| 2817 | `handle_admin_restore_treatment()` | public |
| 2922 | `handle_admin_add_note()` | public |
| 2965 | `handle_admin_delete_note()` | public |
| 2995 | `handle_admin_toggle_note()` | public |
| 3038 | `ajax_doctor_live_search()` | public |
| 3073 | `render_create_client_modal()` | private |
| 3090 | `dashboard_client_stats()` | private |
| 3141 | `doctor_dashboard_analytics()` | private |
| 3214 | `render_donut_visual()` | private |
| 3250 | `render_activity_chart()` | private |
| 3265 | `patient_financial_analytics()` | private |
| 3302 | `render_patient_visual_summary()` | private |
| 3349 | `render_doctor_dashboard()` | private |
| 3440 | `render_patient_alerts()` | private |
| 3481 | `render_owner_editor()` | private |
| 3515 | `render_management_extras()` | private |
| 3657 | `admin_menu()` | public |
| 3664 | `find_clients()` | private |
| 3724 | `render_clients_page()` | public |
| 3795 | `render_wallet_page()` | public |
| 3962 | `rebuildTreatments()` | - |
| 4106 | `has_contract_subscription()` | private |
| 4111 | `get_latest_contract_for_user()` | private |
| 4119 | `valid_employee_full_name()` | private |
| 4132 | `handle_manual_points_adjust()` | public |
| 4226 | `detect_contract_period_months()` | private |
| 4250 | `contract_amount_by_period()` | private |
| 4258 | `contract_points_by_period()` | private |
| 4268 | `period_label()` | private |
| 4275 | `handle_validate_subscription()` | public |
| 4347 | `handle_admin_update_pet()` | public |
| 4397 | `handle_admin_add_pet()` | public |
| 4439 | `handle_wallet_credit()` | public |
| 4453 | `handle_add_treatment()` | public |
| 4467 | `handle_wallet_purchase()` | public |
| 4481 | `handle_front_login()` | public |
| 4526 | `multi_pet_rules_html()` | private |
| 4552 | `shortcode_access_page()` | public |
| 4611 | `msg()` | - |
| 4662 | `shortcode_login()` | public |
| 4688 | `shortcode_account_box()` | public |
| 4713 | `shortcode_auth_links()` | public |
| 4718 | `shortcode_register()` | public |
| 4731 | `handle_front_register()` | public |
| 4741 | `shortcode_dashboard()` | public |

## hikevet-consent-tablet

- Fisier: `source-wordpress/hikevet-consent-tablet/hikevet-consent-tablet.php` (1241 linii)
- Versiune in cod: `1.4.1`

### Constante

| Linie | Constanta | Valoare |
|---|---|---|
| 12 | `VER` | `'1.4.1'` |
| 13 | `CPT` | `'hv_consent'` |
| 14 | `META_PAYLOAD` | `'hv_consent_payload'` |
| 15 | `META_STATUS` | `'hv_consent_status'` |
| 16 | `META_TOKEN` | `'hv_consent_token'` |
| 17 | `META_CODE` | `'hv_consent_code'` |
| 18 | `META_OWNER_SIG` | `'hv_consent_owner_signature'` |
| 19 | `META_REFUSAL_SIG` | `'hv_consent_refusal_signature'` |
| 20 | `META_DOCTOR_SIG` | `'hv_consent_doctor_signature'` |
| 21 | `META_DOCTOR_SIG_AT` | `'hv_consent_doctor_signature_at'` |
| 22 | `META_DOCTOR_SIG_BY` | `'hv_consent_doctor_signature_by'` |
| 23 | `META_SIGNED_AT` | `'hv_consent_signed_at'` |
| 630 | `client` | `document.getElementById("hv-consent-client"), petSel=document.getElementById("hv` |
| 631 | `nonce` | `' . wp_json_encode($nonce) . '` |
| 640 | `species` | `(p.species==="dog"?"Caine":(p.species==="cat"?"Pisica":p.species||""))` |
| 641 | `sex` | `(p.sex==="male"?"mascul":(p.sex==="female"?"femela":p.sex||""))` |
| 650 | `fd` | `new FormData()` |
| 652 | `r` | `await fetch(ajaxurl,{method:"POST",credentials:"same-origin",body:fd})` |
| 653 | `j` | `await r.json()` |
| 664 | `c` | `document.getElementById(canvasId), h=document.getElementById(inputId), clear=doc` |
| 665 | `ctx` | `c.getContext("2d")` |
| 672 | `existing` | `c.dataset.existing||""` |
| 789 | `canvas` | `document.getElementById(' . wp_json_encode($canvas_id) . ')` |
| 790 | `input` | `document.getElementById(' . wp_json_encode($input_id) . ')` |
| 791 | `clearButton` | `document.getElementById(' . wp_json_encode($clear_id) . ')` |
| 792 | `form` | `document.getElementById(' . wp_json_encode($form_id) . ')` |
| 795 | `ctx` | `canvas.getContext("2d")` |
| 806 | `rect` | `canvas.getBoundingClientRect()` |
| 807 | `touch` | `event.touches ? event.touches[0] : event` |
| 830 | `point` | `getPoint(event)` |
| 868 | `existing` | `canvas.dataset.existing || ""` |
| 870 | `image` | `new Image()` |
| 1141 | `c` | `document.getElementById(canvasId), h=document.getElementById(inputId), clear=doc` |
| 1142 | `ctx` | `c.getContext("2d")` |
| 1151 | `ownerDirty` | `initCanvas("hv-owner-signature-canvas","hv-owner-signature-data","hv-owner-signa` |
| 1152 | `refusalDirty` | `initCanvas("hv-refusal-signature-canvas","hv-refusal-signature-data","hv-refusal` |
| 1153 | `form` | `document.getElementById("hv-consent-tablet-form")` |
| 1155 | `owner` | `document.getElementById("hv-owner-signature-data")` |
| 1156 | `refusal` | `document.getElementById("hv-refusal-signature-data")` |
| 1159 | `btn` | `form.querySelector("button[type=submit]")` |

### Shortcode-uri

| Cheie | Linii |
|---|---|
| `hikevet_consent_tablet` | 35 |

### Actiuni admin-post / AJAX

| Cheie | Linii |
|---|---|
| `admin_post_hv_consent_save` | 29 |
| `admin_post_hv_consent_save_doctor_signature` | 30 |
| `admin_post_hv_consent_tablet_submit` | 31 |
| `admin_post_nopriv_hv_consent_tablet_submit` | 32 |
| `wp_ajax_hv_consent_client_data` | 34 |

### Tipuri de post (CPT)

| Cheie | Linii |
|---|---|
| `self::CPT` | 39 |

### Roluri si capabilitati

| Cheie | Linii |
|---|---|
| `manage_hikevet_wallet` | 60 |
| `manage_options` | 60 |

### Post meta

| Cheie | Linii |
|---|---|
| `hv_consent_visit_patients` | 172 |
| `hv_consent_visit_group_id` | 176 |
| `hv_consent_visit_patients_updated_at` | 177 |

### User meta

| Cheie | Linii |
|---|---|
| `hv_pets` | 369 |
| `billing_phone` | 391 |
| `hv_phone` | 391 |
| `billing_address_1` | 393 |
| `billing_city` | 394 |

### Functii (58)

| Linie | Functie | Vizibilitate |
|---|---|---|
| 25 | `__construct()` | public |
| 38 | `register_post_type()` | public |
| 50 | `activate()` | public |
| 55 | `deactivate()` | public |
| 59 | `can_manage()` | private |
| 63 | `no_cache()` | private |
| 70 | `tablet_success_cookie_name()` | private |
| 74 | `mark_tablet_success()` | private |
| 82 | `consume_tablet_success()` | private |
| 92 | `admin_menu()` | public |
| 104 | `defaults()` | private |
| 146 | `anesthesia_options()` | private |
| 155 | `investigation_options()` | private |
| 167 | `get_payload()` | private |
| 182 | `sanitize_text()` | private |
| 186 | `sanitize_payload()` | private |
| 244 | `get_status()` | private |
| 249 | `status_label()` | private |
| 258 | `status_badge()` | private |
| 268 | `create_token()` | private |
| 272 | `create_code()` | private |
| 280 | `find_consent_by_token()` | private |
| 294 | `find_consent_by_code()` | private |
| 308 | `tablet_page_url()` | private |
| 320 | `tablet_url()` | private |
| 325 | `value()` | private |
| 329 | `checked()` | private |
| 333 | `sig_image()` | private |
| 340 | `is_signature_data()` | private |
| 344 | `clean_signature()` | private |
| 351 | `client_users()` | private |
| 361 | `ajax_client_data()` | public |
| 401 | `render_admin_page()` | public |
| 418 | `render_list()` | private |
| 467 | `field()` | private |
| 478 | `editor_checks()` | private |
| 489 | `render_editor()` | private |
| 637 | `val()` | - |
| 638 | `fillPet()` | - |
| 663 | `initCanvas()` | - |
| 667 | `point()` | - |
| 668 | `draw()` | - |
| 682 | `handle_admin_save()` | public |
| 727 | `current_doctor_name()` | private |
| 733 | `handle_save_doctor_signature()` | public |
| 767 | `render_doctor_signature_pad()` | private |
| 805 | `getPoint()` | - |
| 814 | `saveCanvas()` | - |
| 818 | `start()` | - |
| 827 | `move()` | - |
| 840 | `end()` | - |
| 890 | `static_document_html()` | private |
| 1048 | `shortcode_tablet()` | public |
| 1140 | `initCanvas()` | - |
| 1144 | `point()` | - |
| 1145 | `draw()` | - |
| 1168 | `handle_tablet_submit()` | public |
| 1217 | `render_view()` | private |

## hikevet-sterilization-campaign

- Fisier: `source-wordpress/hikevet-sterilization-campaign/hikevet-sterilization-campaign.php` (11413 linii)
- Versiune in cod: `2.7.6`

### Constante

| Linie | Constanta | Valoare |
|---|---|---|
| 12 | `VER` | `'2.7.6'` |
| 13 | `CAP` | `'manage_hikevet_sterilization'` |
| 14 | `TABLE` | `'hv_st_cases'` |
| 15 | `TABLE_APPOINTMENTS` | `'hv_st_appointments'` |
| 16 | `TABLE_EMAIL_LOG` | `'hv_st_email_log'` |
| 17 | `CITY_BULK_VALIDATOR` | `'Dr. Festeu Radu'` |

### Tabele create (dbDelta)

| Linie | Variabila tabel |
|---|---|
| 173 | `$table` |
| 389 | `$appointments` |
| 425 | `$email_log` |

### Actiuni admin-post / AJAX

| Cheie | Linii |
|---|---|
| `admin_post_hv_st_save_prior_reports` | 28 |
| `admin_post_hv_st_save_case` | 29 |
| `admin_post_hv_st_delete_case` | 30 |
| `admin_post_hv_st_export_report` | 31 |
| `admin_post_hv_st_export_report_xlsx` | 32 |
| `admin_post_hv_st_export_report_pdf` | 33 |
| `admin_post_hv_st_export_general_summary_pdf` | 34 |
| `admin_post_hv_st_export_city_package` | 35 |
| `admin_post_hv_st_mark_photo_number_checked` | 36 |
| `admin_post_hv_st_bulk_mark_photo_numbers_checked` | 37 |
| `admin_post_hv_st_align_numbers_from_photo` | 38 |
| `admin_post_hv_st_export_number_registry_csv` | 39 |
| `admin_post_hv_st_save_registry_reconciliation` | 40 |
| `admin_post_hv_st_export_reconciliation_csv` | 41 |
| `admin_post_hv_st_export_reconciliation_month_pdf` | 42 |
| `admin_post_hv_st_export_single_case_package` | 43 |
| `admin_post_hv_st_scan_city_cases` | 44 |
| `admin_post_hv_st_bulk_validate_complete_city_cases` | 45 |
| `admin_post_hv_st_reissue_corrected_consent` | 46 |
| `admin_post_hv_st_auto_repair_integrity` | 47 |
| `admin_post_hv_st_phone_photo_upload` | 48 |
| `admin_post_nopriv_hv_st_phone_photo_upload` | 49 |
| `admin_post_hv_st_save_appointment` | 52 |
| `wp_ajax_hv_st_appointment_availability` | 53 |
| `admin_post_hv_st_cancel_appointment` | 54 |
| `admin_post_hv_st_delete_appointment` | 55 |
| `admin_post_hv_st_send_appointment_email` | 56 |
| `admin_post_hv_st_send_postop_email` | 57 |
| `admin_post_hv_st_send_final_summary_email` | 58 |
| `admin_post_hv_st_run_postop_email_check` | 59 |
| `admin_post_hv_st_start_consultation` | 60 |
| `admin_post_hv_st_add_visit_pet` | 61 |
| `admin_post_hv_st_delete_visit_pet` | 62 |
| `admin_post_hv_st_sync_visit_owner` | 63 |
| `wp_ajax_hv_st_prefill_lookup` | 64 |
| `wp_ajax_hv_st_photo_status` | 65 |

### Hook-uri custom si cron

| Cheie | Linii |
|---|---|
| `hv_st_hourly_appointment_reminders` | 66, 90 |
| `hv_st_hourly_postop_emails` | 67, 93 |
| `hv_st_visit_pet_deleted` | 3389 |
| `hv_st_case_deleted` | 7937 |

### Roluri si capabilitati

| Cheie | Linii |
|---|---|
| `hikevet_doctor` | 120 |
| `read` | 130 |
| `manage_options` | 158, 461, 8128 |
| `manage_hikevet_wallet` | 158, 461 |

### Options

| Cheie | Linii |
|---|---|
| `hv_st_campaign_db_ver` | 76, 159, 161 |
| `hv_st_last_appointment_reminder_fallback` | 99, 101 |
| `hv_st_last_postop_fallback_check` | 107, 109 |
| `hv_st_registry_reconciliation` | 1761, 1843 |
| `hv_st_last_postop_engine_run` | 4937, 5144 |
| `hv_st_reported_through_september_2026` | 8608, 8649 |
| `hv_st_reported_through_september_2026_previous` | 8648 |
| `hv_st_reported_through_september_2026_updated` | 8650 |

### Post meta

| Cheie | Linii |
|---|---|
| `hv_consent_status` | 792, 2693, 3141, 3160, 4347, 4380, 4385, 4428 … |
| `hv_consent_payload` | 799, 2695, 3143, 3150, 4383, 4390, 8225, 9866 |
| `hv_consent_visit_patients` | 2707, 3051, 9868 |
| `hv_consent_visit_patients_updated_at` | 3052 |
| `hv_consent_visit_group_id` | 3055 |
| `hv_consent_token` | 4378, 8220 |
| `hv_consent_code` | 4379, 4429, 8221, 8320 |
| `hv_st_case_id` | 4381, 4391, 8223 |
| `hv_st_case_code` | 4382, 4392, 8224 |
| `hv_consent_correction_reason` | 8226 |
| `hv_consent_created_as_correction_at` | 8227 |
| `hv_consent_supersedes` | 8230 |
| `hv_consent_superseded_by` | 8231 |
| `hv_consent_superseded_at` | 8232 |
| `hv_consent_owner_signature` | 9834, 9879 |
| `hv_consent_signed_at` | 9835, 9873 |
| `hv_consent_doctor_signature` | 9880 |
| `hv_consent_refusal_signature` | 9881 |

### User meta

| Cheie | Linii |
|---|---|
| `billing_address_1` | 6885 |
| `billing_address_2` | 6886 |
| `billing_city` | 6888 |
| `billing_phone` | 6889 |
| `hv_phone` | 6890 |
| `hv_pets` | 6947, 7039 |

### Functii (339)

| Linie | Functie | Vizibilitate |
|---|---|---|
| 19 | `__construct()` | public |
| 73 | `activate()` | public |
| 80 | `deactivate()` | public |
| 88 | `ensure_appointment_cron()` | public |
| 97 | `maybe_run_appointment_reminder_fallback()` | public |
| 105 | `maybe_run_postop_email_fallback()` | public |
| 113 | `doctor_role_slugs()` | private |
| 117 | `add_caps()` | private |
| 135 | `ensure_doctor_role_access()` | public |
| 145 | `grant_doctor_campaign_access()` | public |
| 154 | `maybe_update_tables()` | public |
| 165 | `create_tables()` | public |
| 445 | `table()` | private |
| 450 | `table_appointments()` | private |
| 455 | `table_email_log()` | private |
| 460 | `can_manage()` | private |
| 464 | `current_name()` | private |
| 469 | `now()` | private |
| 473 | `defaults()` | private |
| 678 | `choices()` | private |
| 701 | `next_code()` | private |
| 712 | `scheduled_procedure_from_case()` | private |
| 723 | `appointment_type_from_procedure()` | private |
| 732 | `appointment_case_fields()` | private |
| 743 | `medical_procedure_from_appointment_type()` | private |
| 753 | `medical_procedure_from_case()` | private |
| 758 | `appointment_minutes()` | private |
| 765 | `appointment_time_from_minutes()` | private |
| 770 | `default_appointment_end()` | private |
| 775 | `appointment_end_time()` | private |
| 782 | `appointment_case_url()` | private |
| 789 | `consent_is_signed()` | private |
| 795 | `consent_gdpr_accepted()` | private |
| 804 | `apply_consent_status()` | private |
| 816 | `documents_status_live()` | private |
| 830 | `postop_recommendations_default()` | private |
| 835 | `postop_recommendations_value()` | private |
| 840 | `appointment_calendar_label()` | private |
| 848 | `sync_initial_appointment_for_case()` | private |
| 893 | `case_ready_for_consent()` | private |
| 902 | `normalize_case_row()` | private |
| 910 | `get_case()` | private |
| 916 | `get_case_by_code()` | private |
| 922 | `all_cases()` | private |
| 1116 | `sanitize_date()` | private |
| 1124 | `sanitize_time()` | private |
| 1129 | `sanitize_amount()` | private |
| 1144 | `text_fields()` | private |
| 1161 | `textarea_fields()` | private |
| 1173 | `date_fields()` | private |
| 1177 | `time_fields()` | private |
| 1181 | `decimal_fields()` | private |
| 1185 | `sanitize_update()` | private |
| 1207 | `upload_file()` | private |
| 1227 | `integrity_identity_key()` | private |
| 1232 | `city_address_is_valid()` | private |
| 1240 | `patient_number_year_for_row()` | private |
| 1248 | `next_patient_number_for_year()` | private |
| 1282 | `patient_number_conflicts_for_row()` | private |
| 1307 | `patient_number_conflicting_rows()` | private |
| 1333 | `resolve_manual_patient_number_conflicts()` | private |
| 1387 | `ensure_unique_patient_number()` | private |
| 1435 | `verification_date_for_row()` | private |
| 1477 | `verification_rows()` | private |
| 1569 | `verification_filtered_rows()` | private |
| 1603 | `verification_summary()` | private |
| 1631 | `handle_mark_photo_number_checked()` | public |
| 1672 | `handle_bulk_mark_photo_numbers_checked()` | public |
| 1760 | `registry_reconciliation_settings()` | private |
| 1774 | `parse_number_expression()` | private |
| 1806 | `registry_void_numbers()` | private |
| 1811 | `reconciliation_months()` | private |
| 1819 | `reconciliation_submitted_numbers()` | private |
| 1825 | `handle_save_registry_reconciliation()` | public |
| 1853 | `reconciliation_three_months()` | private |
| 1903 | `build_reconciliation_month_pdf()` | private |
| 2038 | `handle_export_reconciliation_month_pdf()` | public |
| 2072 | `handle_export_reconciliation_csv()` | public |
| 2117 | `verified_photo_registry_rows()` | private |
| 2169 | `number_registry_matrix()` | private |
| 2239 | `handle_align_numbers_from_photo()` | public |
| 2313 | `handle_export_number_registry_csv()` | public |
| 2384 | `report_patient_number()` | private |
| 2390 | `report_patient_number_year_for_row()` | private |
| 2394 | `report_patient_number_conflicts()` | private |
| 2420 | `freeze_report_patient_number()` | private |
| 2433 | `historical_report_number_candidate_is_safe()` | private |
| 2461 | `backfill_report_patient_numbers()` | private |
| 2553 | `report_number_sequence_audit()` | private |
| 2595 | `compact_number_ranges()` | private |
| 2618 | `patient_number_unique_for_case()` | private |
| 2643 | `patient_photo_unique_for_case()` | private |
| 2663 | `ci_document_consistent_for_owner()` | private |
| 2681 | `consent_identity_check()` | private |
| 2726 | `city_integrity_issues_for_row()` | private |
| 2773 | `city_integrity_audit()` | private |
| 2808 | `city_integrity_error_message()` | private |
| 2813 | `auto_validation()` | private |
| 2850 | `is_ready_for_city()` | private |
| 2862 | `table_columns()` | private |
| 2878 | `ensure_case_table_schema_for_save()` | private |
| 2894 | `db_row_only()` | private |
| 2907 | `save_row()` | private |
| 2975 | `admin_url_for()` | private |
| 2982 | `visit_group_id_for_row()` | private |
| 2988 | `ensure_visit_group()` | private |
| 3005 | `visit_group_cases()` | private |
| 3026 | `visit_patients_payload()` | private |
| 3047 | `sync_visit_patients_meta()` | private |
| 3059 | `assign_consent_to_visit_group()` | private |
| 3084 | `sync_visit_owner_from_case()` | private |
| 3156 | `render_visit_group_card()` | private |
| 3209 | `input_raw()` | private |
| 3213 | `select_raw()` | private |
| 3219 | `local_upload_is_referenced_elsewhere()` | private |
| 3239 | `handle_delete_visit_pet()` | public |
| 3416 | `handle_add_visit_pet()` | public |
| 3506 | `handle_sync_visit_owner()` | public |
| 3517 | `section_corectare()` | private |
| 3588 | `visit_group_summary_map()` | private |
| 3601 | `admin_menu()` | public |
| 3611 | `render_page()` | public |
| 3629 | `render_styles()` | private |
| 4169 | `render_header()` | private |
| 4189 | `consent_plugin_available()` | private |
| 4193 | `consent_procedure_from_case()` | private |
| 4198 | `valid_consent_date_value()` | private |
| 4205 | `consent_date_from_case()` | private |
| 4213 | `consent_anesthesia_from_case()` | private |
| 4224 | `consent_payload_from_case()` | private |
| 4286 | `create_consent_token()` | private |
| 4290 | `create_consent_code()` | private |
| 4311 | `sync_consent_for_case()` | private |
| 4406 | `consent_admin_url()` | private |
| 4416 | `render_case_consent_card()` | private |
| 4447 | `appointment_defaults()` | private |
| 4450 | `next_appointment_code()` | private |
| 4455 | `get_appointment()` | private |
| 4458 | `get_appointments_in_range()` | private |
| 4461 | `get_case_options()` | private |
| 4464 | `app_status_class()` | private |
| 4467 | `appointment_badge()` | private |
| 4470 | `normalize_time()` | private |
| 4471 | `sanitize_appointment()` | private |
| 4501 | `populate_appointment_from_case()` | private |
| 4522 | `save_appointment_row()` | private |
| 4542 | `create_case_from_appointment()` | private |
| 4574 | `link_appointment_to_new_case()` | private |
| 4584 | `appointment_datetime_ts()` | private |
| 4585 | `log_appointment_email()` | private |
| 4588 | `clinic_copy_addresses()` | private |
| 4593 | `appointment_email_body()` | private |
| 4654 | `send_appointment_email()` | private |
| 4701 | `final_summary_email_subject()` | private |
| 4708 | `send_final_summary_email()` | private |
| 4726 | `postop_email_due_at()` | private |
| 4741 | `postop_email_subject()` | private |
| 4745 | `consultation_summary_details()` | private |
| 4771 | `consultation_email_table()` | private |
| 4780 | `intervention_summary_details()` | private |
| 4794 | `intervention_email_table()` | private |
| 4803 | `postoperative_summary_email_body()` | private |
| 4843 | `followup_email_body()` | private |
| 4884 | `send_postop_email()` | private |
| 4935 | `run_postop_emails()` | private |
| 4976 | `handle_send_final_summary_email()` | public |
| 4998 | `handle_send_postop_email()` | public |
| 5011 | `handle_run_postop_email_check()` | public |
| 5024 | `postop_email_logs()` | private |
| 5037 | `instant_postop_email_logs()` | private |
| 5050 | `render_postop_emails()` | private |
| 5119 | `render_followup_emails()` | private |
| 5209 | `run_appointment_reminders()` | public |
| 5246 | `sync_case_from_appointment()` | private |
| 5268 | `save_multi_patient_appointments()` | private |
| 5364 | `handle_save_appointment()` | public |
| 5408 | `handle_cancel_appointment()` | public |
| 5430 | `handle_delete_appointment()` | public |
| 5455 | `handle_send_appointment_email()` | public |
| 5458 | `appointment_email_history()` | private |
| 5459 | `handle_start_consultation()` | public |
| 5495 | `appointment_input()` | private |
| 5500 | `appointment_select_field()` | private |
| 5504 | `appointment_textarea()` | private |
| 5508 | `appointment_day_availability()` | private |
| 5587 | `appointment_availability_markup()` | private |
| 5631 | `ajax_appointment_availability()` | public |
| 5650 | `render_week_day_cards()` | private |
| 5681 | `render_multi_patient_appointment_form()` | private |
| 5801 | `normalize()` | - |
| 5809 | `optionSource()` | - |
| 5815 | `resultMarkup()` | - |
| 5851 | `escapeHtml()` | - |
| 5857 | `render()` | - |
| 5974 | `pad()` | - |
| 5978 | `timeToMinutes()` | - |
| 5983 | `minutesToTime()` | - |
| 5988 | `replaceAllText()` | - |
| 5995 | `nextPatientTimes()` | - |
| 6008 | `renumberPatients()` | - |
| 6053 | `bindSlotButtons()` | - |
| 6080 | `loadAvailability()` | - |
| 6136 | `render_appointment_editor()` | private |
| 6198 | `render_appointments()` | private |
| 6412 | `render_dashboard()` | private |
| 6464 | `badge()` | private |
| 6468 | `yes_badge()` | private |
| 6472 | `case_completion_checklist()` | private |
| 6520 | `checklist_target_url()` | private |
| 6529 | `render_case_missing_checklist()` | private |
| 6579 | `case_filter_url()` | private |
| 6584 | `render_cases()` | private |
| 6850 | `normalize_wallet_species()` | private |
| 6857 | `normalize_wallet_sex()` | private |
| 6864 | `age_from_dob()` | private |
| 6880 | `wallet_client_payload()` | private |
| 6901 | `get_prefill_sources()` | private |
| 6962 | `owner_payload_from_source()` | private |
| 6982 | `case_client_payload()` | private |
| 6993 | `case_patient_payload()` | private |
| 7010 | `ajax_prefill_lookup()` | public |
| 7074 | `render_prefill_options()` | private |
| 7080 | `render_appointment_owner_options()` | private |
| 7095 | `render_reception_prefill()` | private |
| 7133 | `input()` | private |
| 7138 | `textarea()` | private |
| 7142 | `fisa_examinare_default_text()` | private |
| 7154 | `fisa_examinare_value()` | private |
| 7159 | `select()` | private |
| 7163 | `options()` | private |
| 7169 | `choice_radio()` | private |
| 7177 | `checkbox_mark()` | private |
| 7181 | `no_cache()` | private |
| 7188 | `get_or_create_photo_token()` | private |
| 7200 | `find_case_by_photo_token()` | private |
| 7208 | `phone_photo_url()` | private |
| 7213 | `qr_image_url()` | private |
| 7218 | `render_phone_qr()` | private |
| 7235 | `maybe_render_phone_photo_upload()` | public |
| 7293 | `handle_phone_photo_upload()` | public |
| 7327 | `ajax_photo_status()` | public |
| 7342 | `render_case_editor()` | private |
| 7499 | `section_receptie()` | private |
| 7553 | `section_preoperator()` | private |
| 7601 | `intervention_date_value()` | private |
| 7607 | `section_interventie()` | private |
| 7658 | `section_documente()` | private |
| 7679 | `section_externare()` | private |
| 7696 | `section_fisa()` | private |
| 7774 | `printable_value()` | private |
| 7779 | `render_patient_sheet_print()` | private |
| 7799 | `section_validare()` | private |
| 7827 | `image_or_link()` | private |
| 7833 | `render_summary()` | private |
| 7856 | `substances()` | private |
| 7865 | `delete_local_upload_url()` | private |
| 7885 | `handle_delete_case()` | public |
| 7957 | `handle_save_case()` | public |
| 8200 | `create_corrected_consent_for_case()` | private |
| 8248 | `handle_reissue_corrected_consent()` | public |
| 8269 | `auto_repair_integrity()` | private |
| 8342 | `handle_auto_repair_integrity()` | public |
| 8364 | `bulk_validate_complete_city_cases()` | private |
| 8415 | `handle_bulk_validate_complete_city_cases()` | public |
| 8431 | `scan_city_cases()` | private |
| 8480 | `handle_scan_city_cases()` | public |
| 8501 | `reporting_date_sql()` | private |
| 8515 | `backfill_reporting_dates()` | private |
| 8530 | `september_145_rows()` | private |
| 8570 | `fixed_september_requested()` | private |
| 8579 | `require_september_145_rows()` | private |
| 8587 | `render_september_145_exports()` | private |
| 8607 | `prior_report_codes()` | private |
| 8622 | `exclude_prior_reports()` | private |
| 8631 | `handle_save_prior_reports()` | public |
| 8655 | `render_prior_reports()` | private |
| 8679 | `finalized_intervention_rows()` | private |
| 8737 | `report_rows()` | private |
| 8747 | `report_map()` | private |
| 8794 | `report_summary()` | private |
| 8829 | `general_animal_category()` | private |
| 8843 | `general_animal_summary()` | private |
| 8859 | `build_general_summary_pdf()` | private |
| 8878 | `report_month_label()` | private |
| 8885 | `report_xlsx_columns()` | private |
| 8920 | `xlsx_escape()` | private |
| 8926 | `xlsx_column_name()` | private |
| 8936 | `xlsx_inline_cell()` | private |
| 8942 | `xlsx_number_cell()` | private |
| 8946 | `xlsx_row_xml()` | private |
| 8952 | `zip_dos_datetime()` | private |
| 8960 | `build_zip_binary()` | private |
| 9033 | `report_xlsx_styles_xml()` | private |
| 9080 | `report_xlsx_summary_sheet()` | private |
| 9156 | `report_xlsx_data_sheet()` | private |
| 9232 | `build_report_xlsx_file()` | private |
| 9301 | `package_scan_rows()` | private |
| 9372 | `package_scan_summary()` | private |
| 9416 | `package_recursive_delete()` | private |
| 9429 | `package_create_temp_dir()` | private |
| 9437 | `package_url_to_local_path()` | private |
| 9449 | `package_fetch_url()` | private |
| 9465 | `package_copy_attachment()` | private |
| 9477 | `package_image_bytes_to_jpeg()` | private |
| 9507 | `package_prepare_jpeg()` | private |
| 9517 | `package_signature_to_jpeg()` | private |
| 9528 | `pdf_ascii()` | private |
| 9537 | `pdf_escape()` | private |
| 9541 | `pdf_color()` | private |
| 9546 | `pdf_new_document()` | private |
| 9551 | `pdf_add_page()` | private |
| 9556 | `pdf_cmd()` | private |
| 9560 | `pdf_text()` | private |
| 9566 | `pdf_line()` | private |
| 9571 | `pdf_rect()` | private |
| 9582 | `pdf_wrap_lines()` | private |
| 9594 | `pdf_wrapped_text()` | private |
| 9604 | `pdf_register_jpeg()` | private |
| 9617 | `pdf_image()` | private |
| 9626 | `pdf_binary()` | private |
| 9671 | `pdf_write_file()` | private |
| 9675 | `pdf_add_brand_header()` | private |
| 9682 | `build_package_report_pdf()` | private |
| 9754 | `build_case_sheet_pdf()` | private |
| 9814 | `consent_selected_labels()` | private |
| 9820 | `consent_gdpr_export_state()` | private |
| 9851 | `consent_gdpr_pdf_label()` | private |
| 9863 | `build_consent_pdf()` | private |
| 10012 | `build_missing_documents_xlsx()` | private |
| 10058 | `package_zip_directory()` | private |
| 10122 | `render_reports()` | private |
| 10790 | `makeAccordion()` | - |
| 10862 | `handle_export_report_xlsx()` | public |
| 10887 | `handle_export_report_pdf()` | public |
| 10993 | `build_single_case_package()` | private |
| 11105 | `handle_export_single_case_package()` | public |
| 11158 | `handle_export_general_summary_pdf()` | public |
| 11180 | `handle_export_city_package()` | public |
| 11386 | `handle_export_report()` | public |
