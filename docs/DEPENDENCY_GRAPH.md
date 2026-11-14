# Graful dependențelor

## 1. Între pluginurile WordPress

```
                      ┌────────────────────────────┐
                      │  Simulator abonamente 2.7.0 │
                      │  CPT hv_abon_sub + PDF      │
                      └──────────────┬─────────────┘
              do_action('hv_abon_submission_ready')
                                     ▼
┌──────────────────────────────────────────────────────────────┐
│  Wallet Core 3.1.0                                            │
│  conturi (wp_users + usermeta hv_pets, hv_contracts)          │
│  hv_wallet_tx · hv_treatments · hv_notifications ·            │
│  hv_email_log · hv_audit_log · hv_doctor_notes                │
│  roluri hikevet_client / hikevet_doctor                       │
└───────▲──────────────────────────────────────▲───────────────┘
        │ citește animalele clientului          │ capabilitatea
        │ (reducere multi-animal)               │ manage_hikevet_sterilization
        │                                       │
┌───────┴───────────────┐            ┌─────────┴─────────────────┐
│ Simulator (formular)  │            │ Campanie sterilizare 2.7.6 │
└───────────────────────┘            │ hv_st_cases · _appointments│
                                     │ · _email_log               │
┌───────────────────────┐  consent_id│                            │
│ Consimțământ tabletă  │◄───────────┤ shared_consent_id          │
│ CPT hv_consent        │────────────► hv_st_case_id (meta)       │
└───────────────────────┘            └────────────────────────────┘
```

| De la | Către | Legătură | Dovadă |
|---|---|---|---|
| Simulator | Wallet Core | acțiunea `hv_abon_submission_ready` după salvarea contractului | SIM:1061, WAL:1587 |
| Simulator | Wallet Core | citește animalele existente pentru reducere | SIM:838, WAL:1414 |
| Wallet Core | Simulator | validarea plății scrie `hv_payment_*` pe contract | WAL:4275 |
| Campanie | Wallet Core | folosește rolul de medic și capabilitatea de sterilizare | WAL:372–393 |
| Campanie | Consimțământ | dosarul reține `consent_id` / `shared_consent_id` | schema `hv_st_cases` |
| Campanie | Consimțământ | campania scrie pe consimțământ meta `hv_st_case_id` | STE:4381 |
| Toate | WordPress | `wp_mail`, cron, roluri, uploads, nonce | – |
| Simulator | WooCommerce | **nicio dependență** din v2.6.2 (plata online eliminată) | README SIM |

Consecință pentru import: ordinea este conturi → animale → contracte → registru → tratamente →
consimțăminte → dosare de sterilizare.

## 2. În HiKeVet Next

```
Site public ──► Configurator ──► PricingEngine ──► price_lists / subscription_tiers
                     │
                     ▼
              ContractService ──► ContractPdf (disc privat)
                     │      └───► ClientAccounts ──► users / pets
                     │      └───► Notifier ──► email_logs
                     ▼
        SubscriptionActivation ──► WalletLedger ──► wallet_transactions (doar adăugare)
                     ▲                    ▲
                     │                    │
        Admin: fișa clientului ───────────┘──► treatments

Tabletă ──► ConsentService ──► consents (criptat, amprentă) ◄── sterilization_cases (doar citire)
                                                                        ▲
Import WordPress (WordPressCoreImporter, SterilizationImporter) ────────┘

Programări: BookingService ──► appointments / appointment_slots   (sau redirect Digitail)

Transversal: Audit ──► audit_logs · Settings ──► settings · roluri și permisiuni
Operare: Installer · UpdateService ──► ReleasePackage, BackupService · cron (schedule:run)
```

## 3. Dependențe externe

| Pachet | Rol | Observație |
|---|---|---|
| laravel/framework 13 | aplicația | cere PHP 8.3+ |
| filament/filament 5 | panoul de administrare | include Livewire 4 |
| spatie/laravel-permission | roluri și permisiuni | |
| dompdf/dompdf | contracte PDF | fără programe externe; potrivit pentru shared hosting |
| Alpine.js, Vite, Tailwind (doar admin) | interfață | compilate dinainte; pe server nu este nevoie de Node |
| Fontsource (Dela Gothic One, Jost, Caveat) | fonturi | servite local, fără cereri către Google |

Servicii din afară: Digitail (link de programare), Google Maps (hartă pe pagina de contact),
GTM / GA4 (numai după acord), SMTP-ul găzduirii. Nu există integrare SMS sau WhatsApp și nicio
legătură prin API cu Google.
