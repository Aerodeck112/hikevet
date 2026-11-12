# Surse WordPress de referință (read-only)

Aici se extrag cele patru pluginuri HiKeVet livrate ca ZIP:

- `hikevet-abonament-simulator/` (v2.7.0)
- `hikevet-client-wallet-core/` (v3.1.0)
- `hikevet-consent-tablet/` (v1.4.1)
- `hikevet-sterilization-campaign/` (v2.7.6)

**Codul pluginurilor nu este urcat în Git.** Repository-ul `Aerodeck112/hikevet` este public, iar
pluginurile sunt cod privat al clinicii. Directoarele sunt ignorate prin `.gitignore`; rămâne în
Git doar acest fișier. Dacă repository-ul devine privat și vrei sursele versionate, șterge regula
din `.gitignore`.

Sursele sunt material de referință: nimic din HiKeVet Next nu le încarcă la rulare. Sunt citite
doar de scripturile din `tools/audit/`, care regenerează:

- `docs/generated/plugin-inventory.md`
- `docs/generated/wp-table-schemas.md` și `database/import-spec/wp-tables.json`
- `database/seeders/data/treatment-catalog.json`
