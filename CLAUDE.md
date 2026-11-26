# Gäste-WLAN (Captive Portal für UniFi)

Gebaut nach dem **AlpinNext Portal-Standard** (Skill `alpinnext-portal-standard`). Bei jeder Änderung: Changelog-Eintrag DE+EN in `config/changelog.php`, Version/Build erhöhen, `php artisan test` grün, dann Push auf `main`. Commits als „Manfred Schneider <ms@schneider-manfred.de>“, ohne KI-Hinweise.

## Besonderheiten
- Einzelkunden-Portal (ein Unternehmen, mehrere Standorte = `locations`). Keine öffentliche Seite außer der Gästeseite.
- Gästeseite: `PortalController`, Texte in `lang/{de,en,it}/portal.php` (Gäste per Sie). Verwaltung per du, DE/EN.
- UniFi: Standard `App\Services\Unifi\UnifiCloudClient` (api.ui.com Connector → Network Integration API, `AUTHORIZE_GUEST_ACCESS`, Site-Manager-Key). Nur der Connector-Pfad `/proxy/network/integration/` ist erlaubt (seit 05.11.2026 sind interne Pfade gesperrt). Alternativ `UnifiOsClient` direkt (Modus „local“). Im Demo-Modus und in der Vorschau `DemoUnifiClient` – dort wird nie etwas an der UDM geändert.
- Datenschutz: MAC nie im Klartext (`GuestSession::hashDevice`). E-Mail nur mit Newsletter-Einwilligung. Löschfristen in `routes/console.php` (`portal:purge`).
- Demo-Daten: `App\Demo\DemoData::ensure()`, Datensätze mit `is_demo = true`, erfundene Namen, Mails auf `.example`.
- Dashboard-Schnittstelle: `routes/api.php`, `Api\DashboardApiController`, Schlüssel `ApiKey` (nur Hash). Spezifikation `docs/dashboard-schnittstelle.md` bei Änderungen mitpflegen. Bilder immer über `App\Support\Media` + `MediaController` (Portal-Domain wegen Walled Garden).
- Einstellungen nur in der DB (`App\Support\Settings::FIELDS`), nie neue `.env`-Zeilen.

## Noch offen gegenüber dem Standard (Ausbau nach dem Pilot)
2FA/Passkeys, Einladungslinks, spatie-Rollen, Post-its, Schulung, Hilfe-Assistent, KI-Aufbereitung von Wünschen + Sofortstart, MCP-Endpunkt, Sicherung, Messung langsamer Seiten, PWA, Saison-Effekte, Handbuch-Suche.
