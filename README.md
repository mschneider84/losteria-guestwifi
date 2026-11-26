# Gäste-WLAN – eigenes Captive Portal für UniFi (UDM SE)

Eigene Gästeseite für das WLAN in den Restaurants. UniFi leitet Gäste auf dieses Portal weiter; nach Klick auf „Kostenlos verbinden“ schaltet das Portal das Gerät über die API der UDM SE frei (mit Zeitlimit und Bandbreite je Standort).

- **Gästeseite**: Deutsch, Englisch, Italienisch (Sprache des Handys wird erkannt), Klick-Login mit Nutzungsbedingungen, freiwillige Newsletter-Anmeldung mit Double-Opt-in, Weiterleitung nach dem Verbinden.
- **Verwaltung** (`/admin`): Übersicht mit Anmeldungen pro Tag und Restaurant, Wiederkehrer, Sprachen, Stoßzeiten · Standorte mit eigenem Branding · Newsletter-Kontakte mit CSV-Export · Einstellungen · Systemstatus · Benutzer & Rollen · Wünsche & Fehler · Demo-Modus.
- **Schnittstelle zum L'Osteria Dashboard** (`/api/v1`): Auswertungen je Restaurant abrufen, Marketingaktionen für die Gästeseite senden. Spezifikation: [`docs/dashboard-schnittstelle.md`](docs/dashboard-schnittstelle.md).
- **Datenschutz**: MAC-Adressen werden nie im Klartext gespeichert (nur HMAC für die Wiederkehrer-Statistik). E-Mail nur bei aktiver Newsletter-Einwilligung, mit Zeitpunkt und Textversion. Automatische Löschfristen (Standard: Anmeldungen 90 Tage, unbestätigte Kontakte 14 Tage, abgemeldete 30 Tage).

Aufbau nach dem AlpinNext Portal-Standard (siehe `CLAUDE.md`).

## Technik

Laravel 13, PHP 8.3, MySQL 8.4 (lokal SQLite), Blade + eigenes CSS ohne Build-Schritt. Deploy auf Laravel Cloud per Push auf `main`, Deploy-Befehl `php artisan migrate --force`.

Umgebungsvariablen: nur die, die Laravel Cloud selbst setzt (`APP_KEY`, Datenbank, Cache, `APP_ENV`, `APP_URL`). Alles andere – UniFi-Zugang, Mail, Löschfristen, Branding – steht in der Datenbank und wird unter **Einstellungen** gepflegt. Geheimnisse sind mit dem `APP_KEY` verschlüsselt: **den `APP_KEY` nie wechseln.**

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve           # dann /setup aufrufen und das erste Admin-Konto anlegen
php artisan portal:demo     # optional: Demo-Daten anlegen
php artisan test
```

### Laravel Cloud

1. **Resources → Database**: eine MySQL-Datenbank anlegen und an die Umgebung *production* hängen. Laravel Cloud setzt `DB_CONNECTION`, `DB_HOST` usw. dann selbst – keine eigenen Variablen nötig.
2. Optional **KV Store** (Valkey) anhängen: Cache und Sitzungen laufen dann automatisch dort.
2. **Object Storage** (Bucket) anhängen: Logos und Aktionsbilder werden dort gespeichert (Laravel Cloud setzt `FILESYSTEM_DISK=s3`). Ohne Bucket gehen hochgeladene Bilder beim nächsten Deploy verloren.
3. **Settings → Deployments → Deploy command**: `php artisan migrate --force`
4. **Scheduler** einschalten (für Heartbeat und Löschfristen).
5. Neu deployen, dann `https://<deine-domain>/setup` aufrufen und das erste Admin-Konto anlegen.

Zeitplan (Laravel Cloud Scheduler): Heartbeat jede Minute, Löschfristen täglich 03:30 (`portal:purge`).

## Einrichtung auf der UDM SE

Die Bezeichnungen gelten für UniFi Network 9.x; je nach Version heißen Menüpunkte leicht anders.

### 1. Verbindung über die UniFi-Cloud (empfohlen)

Das Portal schaltet Gäste über `api.ui.com` frei. Die Anfrage geht über die bestehende Cloud-Verbindung der UDM SE (Site Manager Connector) an die offizielle Network-API – **ohne VPN, Portfreigabe, feste IP oder Zusatzgerät**. So arbeiten auch kommerzielle Hotspot-Anbieter mit der „External Hotspot API“ von Ubiquiti.

Voraussetzungen: UDM SE mit dem UI-Konto verbunden (Fernzugriff an), UniFi OS ≥ 5.0.3, UniFi Network ≥ 9.1.105.

1. [unifi.ui.com](https://unifi.ui.com) → **API** → *Create API Key* (Organisations-Schlüssel, falls mehrere Standorte unter einer Organisation hängen).
2. Im Portal **Einstellungen → UniFi / UDM SE**: „Über die UniFi-Cloud“ wählen, Schlüssel eintragen, **Konsolen laden**, UDM SE auswählen, speichern.
3. **Verbindung testen** – die Sites der Konsole werden angezeigt. Die UniFi-Site eines Standorts ist ihr Kurzname (bei einer Site `default`).

Grenzen des Connectors: 100 Anfragen pro Minute je Konsole (je Gast-Anmeldung 3 Anfragen), 25 Sekunden je Anfrage.

Alternative „Direkt“ (nur mit VPN oder Freigabe): lokaler Benutzer auf der UDM (Admins & Users → *Restrict to Local Access Only*, Network: Site Admin), Adresse/Benutzer/Passwort eintragen.

### 2. Gästenetz auf „External Portal“ stellen

1. **Settings → WiFi**: SSID für Gäste (z. B. `Osteria-Gast`) mit einem eigenen Gast-Netzwerk/VLAN; **Hotspot Portal** aktivieren.
2. **Hotspot / Landing Page → Authentication**: *External Portal Server* wählen und die Adresse des Portals eintragen.
3. **Secure Portal (HTTPS)** und **Redirect using hostname** ausschalten – die Weiterleitung übernimmt das Portal.
4. UniFi leitet Gäste dann an `http://<Portal>/guest/s/<site>/?id=<Gerät>&ap=<AP>&ssid=…&url=…` weiter. Die passende URL je Standort steht im Portal unter **Standorte**.

### 3. Walled Garden (Pre-Authorization Access)

Vor der Freischaltung dürfen Gäste nur das Portal erreichen. Unter **Hotspot → Pre-Authorization Allowances** eintragen:

- Domain des Portals (z. B. `wlan.example.de`) bzw. dessen IP-Adresse
- ggf. Domains für Schriften/Bilder, falls später externe Inhalte eingebunden werden (aktuell nicht nötig – alles wird vom Portal selbst geliefert)

### 4. Standort im Portal anlegen

**Standorte → Standort anlegen**: Name, **UniFi-Site** (Kurzname aus der Adresse `/network/<site>/…`, bei nur einer Site `default`), Zeitlimit, Bandbreite, Farbe, Logo, Weiterleitung. Mit **Vorschau** lässt sich die Gästeseite ohne Freischaltung ansehen.

### Vor dem Pilot zu klären

- **Feste IPv4 für das External Portal**: UniFi erwartet beim *External Portal Server* je nach Version eine IP-Adresse statt eines Domainnamens. Laravel Cloud hat keine feste IP. Lösung, falls nötig: eine kleine Weiterleitung auf einem Server mit fester IP, die `/guest/s/…` samt Parametern an die Portal-Domain weiterreicht.
- **Erster echter Test über die Cloud**: Der Weg über `api.ui.com` ist nach Ubiquitis Dokumentation vorgesehen, aber erst mit eurer UDM SE wirklich bestätigt. Beim Pilot eine Anmeldung durchspielen; schlägt sie fehl, zeigt der Systemstatus die Fehlernummer und den Grund.
- Rechtstexte: Links zu Nutzungsbedingungen, Datenschutzerklärung und Impressum unter **Einstellungen → Gästeseite** eintragen (bis dahin wird ein kurzer eingebauter Text gezeigt).
