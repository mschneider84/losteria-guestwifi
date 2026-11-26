# Schnittstelle Gäste-WLAN ↔ L'Osteria Dashboard

Version 1 · Stand 27.11.2026

Das Gäste-WLAN-Portal stellt eine REST-Schnittstelle bereit. Das L'Osteria Dashboard nutzt sie in zwei Richtungen:

1. **Auswertungen abholen**: Anmeldungen, Geräte, Wiederkehrer, Stoßzeiten, Sprachen, Newsletter und Aktions-Klicks je Restaurant.
2. **Marketingaktionen senden**: Titel, Text, Bild und Button werden im Dashboard gepflegt und auf der Gästeseite angezeigt.

Das Dashboard ist immer der Aufrufer, das Portal braucht keinen Zugang zum Dashboard.

## Zugang

- Basis-URL: `https://<portal-domain>/api/v1`
- Schlüssel im Portal unter **Einstellungen → Dashboard-Schnittstelle** anlegen. Er wird nur einmal angezeigt; im Dashboard als Einstellung (verschlüsselt) speichern.
- Senden im Header, nie in der URL:
  - `Authorization: Bearer gw_…` oder `X-Api-Key: gw_…`
- Schlüssel „nur lesen“ dürfen Auswertungen abrufen; Schlüssel mit „darf Marketingaktionen senden“ zusätzlich `PUT`/`DELETE` auf Aktionen.
- Grenzen: 120 Anfragen pro Minute je Schlüssel, 20 Fehlversuche je IP in 10 Minuten → `429`.
- Fehler kommen als JSON `{"error": "…"}` bzw. bei Prüffehlern im Laravel-Format `{"message": "…", "errors": {"feld": ["…"]}}`.
- Alle Zeiten Europe/Berlin. Datumsangaben `YYYY-MM-DD`, Zeitpunkte ISO 8601.

| Status | Bedeutung |
|---|---|
| 200 / 201 / 204 | OK / angelegt / gelöscht |
| 401 | Schlüssel fehlt, falsch oder abgelaufen |
| 403 | Schlüssel darf nur lesen |
| 404 | Aktion oder Standort unbekannt |
| 422 | Eingaben ungültig (Details in `errors`) oder Bild nicht ladbar |
| 429 | Zu viele Anfragen |

## Endpunkte

### `GET /status`
Verbindungstest.
```json
{ "portal": "L'Osteria Gäste-WLAN", "version": "1.1.0", "build": 3, "standorte": 12 }
```

### `GET /standorte`
Alle Restaurants im Portal. Der `slug` ist der Schlüssel für Auswertungen und Aktionen – im Dashboard einmal je Restaurant zuordnen.
```json
{ "daten": [ { "slug": "muenchen-leopoldstrasse", "name": "…", "stadt": "München", "unifi_site": "default",
  "aktiv": true, "zeitlimit_minuten": 240, "download_kbps": 20000, "upload_kbps": 5000 } ] }
```

### `GET /auswertung?von=2026-11-01&bis=2026-11-30&standort=<slug>`
Alle Parameter optional. Standard: die letzten 30 Tage, alle Standorte. Höchstens 366 Tage. Antworten werden 1–10 Minuten zwischengespeichert; für das Dashboard reicht ein Abruf pro Stunde (bzw. nachts für den Vortag).
```json
{
  "zeitraum": { "von": "2026-11-01", "bis": "2026-11-30", "zeitzone": "Europe/Berlin" },
  "gesamt": { "anmeldungen": 6210, "geraete": 5480, "wiederkehrer_anmeldungen": 1720, "newsletter_bestaetigt": 140 },
  "standorte": [ {
    "standort": { "slug": "muenchen-leopoldstrasse", "name": "…", "stadt": "München" },
    "anmeldungen": 2110, "geraete": 1890, "wiederkehrer_anmeldungen": 590, "wiederkehrer_quote": 0.28,
    "newsletter_bestaetigt": 51,
    "pro_tag":    { "2026-11-01": 64, "2026-11-02": 81 },
    "pro_stunde": { "0": 0, "12": 260, "19": 310 },
    "sprachen":   { "de": 1500, "en": 400, "it": 210 },
    "aktionen":   [ { "id": "pizza-2026-12", "aufrufe": 1830, "klicks": 112 } ]
  } ]
}
```
- `anmeldungen`: erfolgreiche Freischaltungen. `geraete`: verschiedene Geräte (verschlüsselte Kennung, keine MAC).
- `wiederkehrer_anmeldungen`: Anmeldungen von Geräten, die am selben Standort schon einmal online waren.
- `aktionen`: Aufrufe (Aktion auf der Gästeseite angezeigt) und Klicks auf den Button, je Aktions-ID.

### `GET /aktionen`
Alle Aktionen, die das Portal aktuell kennt (zum Abgleich).

### `PUT /aktionen/{id}` – Aktion anlegen oder ändern
`{id}` ist die ID der Aktion im Dashboard (`A–Z a–z 0–9 . _ -`, max. 100 Zeichen). Gleiche ID = Aktualisierung (idempotent, beliebig oft sendbar). Immer den **vollständigen** Stand senden; weggelassene Felder werden geleert.

```json
{
  "titel":       { "de": "Pizza des Monats: Tartufo e Funghi", "en": "Pizza of the month", "it": "Pizza del mese" },
  "text":        { "de": "Mit schwarzem Trüffel – nur im Dezember." },
  "button_text": { "de": "Zur Speisekarte" },
  "button_url":  "https://www.losteria.net/…",
  "bild_url":    "https://dashboard.…/storage/aktionen/pizza.jpg",
  "standorte":   ["muenchen-leopoldstrasse", "nuernberg"],
  "start":       "2026-12-01T00:00:00+01:00",
  "ende":        "2026-12-31T23:59:59+01:00",
  "wochentage":  [1, 2, 3, 4, 5],
  "uhrzeit_von": "17:00",
  "uhrzeit_bis": "19:00",
  "platzierung": "both",
  "prioritaet":  10,
  "aktiv":       true
}
```

| Feld | Pflicht | Regeln |
|---|---|---|
| `titel.de` | ja | max. 120 Zeichen; `en`/`it` optional, sonst wird Deutsch gezeigt |
| `text.*` | nein | max. 400 Zeichen je Sprache |
| `button_text.*`, `button_url` | nein | Button erscheint nur **nach** dem Verbinden (vorher gibt es kein Internet); URL nur `https` |
| `bild_url` | nein | `https`, JPEG/PNG/WebP, max. 3 MB, ideal 1200×675 (16:9). Das Portal lädt das Bild einmal herunter und liefert es selbst aus – Gäste sehen es so schon vor der Freischaltung. Neues Bild = neue URL senden. |
| `standorte` | nein | Liste von `slug`s; leer/fehlend = alle Restaurants |
| `start`, `ende` | nein | Zeitraum; fehlend = unbegrenzt |
| `wochentage` | nein | 1 = Montag … 7 = Sonntag |
| `uhrzeit_von`, `uhrzeit_bis` | nein | `HH:MM`, z. B. für Aperitivo-Zeiten |
| `platzierung` | nein | `start` (vor dem Verbinden), `online` (nach dem Verbinden), `both` (Standard) |
| `prioritaet` | nein | −100…100; passen mehrere Aktionen, gewinnt die höchste |
| `aktiv` | nein | `false` = pausiert, bleibt aber gespeichert |

Antwort `201` (neu) bzw. `200` (geändert) mit `{"daten": {…}}`, darin `bild_im_portal` = URL, unter der das Portal das Bild ausliefert.

### `DELETE /aktionen/{id}`
Entfernt Aktion und Bild. Antwort `204`. Die bisherigen Aufrufe/Klicks verschwinden mit – vorher abholen, falls sie im Dashboard bleiben sollen.

### `GET /auslastung?standort=<slug>` – Live-Auslastung
Letzte Momentaufnahme (alle 5 Minuten, höchstens 15 Minuten alt; sonst `stand: null`). `standort` optional.

```json
{"daten": [{
  "standort": {"slug": "muenchen", "name": "Osteria Centrale", "stadt": "München"},
  "stand": "2026-11-27T19:35:00+01:00",
  "geraete": 61, "gaeste": 57,
  "auslastung_quote": 0.68, "hoechststand_30_tage": 84,
  "bereiche": [{"bereich": "Innen", "geraete": 30, "gaeste": 30}, {"bereich": "Terrasse", "geraete": 15, "gaeste": 15}, {"bereich": "Bar", "geraete": 16, "gaeste": 12}]
}]}
```
- `gaeste` = Geräte im Gäste-WLAN, `geraete` = alle Geräte (inkl. Personal). `auslastung_quote` = Gäste ÷ höchster Stand der letzten 30 Tage.

### `GET /netzwerk?von=2026-11-01&bis=2026-11-30&standort=<slug>` – Netzwerk-Auswertung
Zeitraum höchstens 92 Tage (Standard: letzte 30 Tage). Je Standort:

| Block | Inhalt |
|---|---|
| `bereiche[]` | `bereich`, `anteil` (0–1), `hoechststand`, `pro_stunde` {0–23: Ø anwesende Gastgeräte} |
| `auslastung` | `heatmap` {1=Mo…7=So: {0–23: Ø Gäste}}, `spitzenzeiten[]` (wochentag, stunde, gaeste), `hoechststand` (gaeste, zeitpunkt) |
| `verweildauer` | `besuche`, `durchschnitt_minuten`, `median_minuten`, `nach_ankunftsstunde`, `nach_wochentag`, `nach_bereich`, `verteilung` (Besuche ab 5 Minuten) |
| `stammgaeste` | `geraete`, `wiederkehrer`, `wiederkehrer_quote`, `abstand_tage_durchschnitt`, `abstand_verteilung`, `besuche_je_geraet` |
| `aktionen_wirkung` | `vergleich_rueckkehr_quote_ohne_klick`, `zeitfenster_tage` (30), `aktionen[]` (id, titel, geraete_mit_klick, wiedergekommen, rueckkehr_quote, unterschied_prozentpunkte) |
| `technik` | `geraetetypen` {iPhone: n, Android: n, …}, `datenverbrauch` (null, wenn die UDM keinen Verbrauch je Gerät meldet), `access_points[]` (name, bereich, modell, zustand, geraete, hoechststand, funk_wiederholungen_prozent, hinweise[]) |

Datenschutz: Die Schnittstelle liefert nur Anzahlen und Durchschnitte, nie einzelne Geräte oder Kennungen.

## Empfohlene Umsetzung im Dashboard

1. **Einstellungen**: Feld „Gäste-WLAN: Adresse“ und „Gäste-WLAN: Schlüssel“ (verschlüsselt, write-only) – keine `.env`-Variablen.
2. **Restaurant-Zuordnung**: einmal `GET /standorte` und je Restaurant im Dashboard den `slug` speichern.
3. **Auswertung**: Zeitplan stündlich `GET /auswertung?von=<heute>&bis=<heute>` und nachts den Vortag; Werte in einer eigenen Tabelle (`wlan_tageswerte`: restaurant, datum, anmeldungen, geraete, wiederkehrer, newsletter) ablegen. Seiten im Dashboard lesen nur diese Tabelle.
4. **Marketingaktionen**: Eingabemaske im Dashboard (Felder wie oben, Bild-Upload). Beim Speichern `PUT /aktionen/{id}`, beim Löschen `DELETE`. Schlägt der Aufruf fehl, Hinweis zeigen und im Zeitplan erneut versuchen.
5. **Auslastung**: `GET /auslastung` alle 5 Minuten für eine Live-Kachel je Restaurant; `GET /netzwerk` nachts für den Vortag bzw. die letzten 30 Tage, Ergebnis als JSON je Restaurant ablegen.
6. **Systemstatus**: `GET /status` im Selbsttest des Dashboards.

```php
// Beispiel Laravel (Dashboard-Seite)
$wlan = Http::withToken(Settings::get('wlan.api_key'))
    ->baseUrl(rtrim(Settings::get('wlan.url'), '/').'/api/v1')
    ->acceptJson()->timeout(10);

$daten = $wlan->get('/auswertung', ['von' => today()->toDateString(), 'bis' => today()->toDateString()])->throw()->json();
$wlan->put('/aktionen/'.$aktion->id, $aktion->toWlanPayload())->throw();
```
