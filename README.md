# Gäste-WLAN – eigenes Captive Portal für UniFi (UDM SE)

Eigene Gästeseite für das WLAN in den Restaurants. UniFi leitet Gäste auf dieses Portal weiter; nach Klick auf „Kostenlos verbinden“ schaltet das Portal das Gerät über die API der UDM SE frei (mit Zeitlimit und Bandbreite je Standort).

- **Gästeseite**: Deutsch, Englisch, Italienisch (Sprache des Handys wird erkannt), Klick-Login mit Nutzungsbedingungen, freiwillige Newsletter-Anmeldung mit Double-Opt-in, Weiterleitung nach dem Verbinden.
- **Verwaltung** (`/admin`): Übersicht mit Anmeldungen pro Tag und Restaurant, Wiederkehrer, Sprachen, Stoßzeiten · Standorte mit eigenem Branding · Newsletter-Kontakte mit CSV-Export · Einstellungen · Systemstatus · Benutzer & Rollen · Wünsche & Fehler · Demo-Modus.
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

Zeitplan (Laravel Cloud Scheduler): Heartbeat jede Minute, Löschfristen täglich 03:30 (`portal:purge`).

## Einrichtung auf der UDM SE

Die Bezeichnungen gelten für UniFi Network 9.x; je nach Version heißen Menüpunkte leicht anders.

### 1. Service-Konto für das Portal

1. UniFi OS → **Admins & Users** → **Add Admin**.
2. **Restrict to Local Access Only** einschalten (kein UI-Konto, kein Fernzugriff).
3. Benutzername z. B. `gaeste-portal`, langes Zufallspasswort.
4. Rolle für **Network**: *Site Admin* (bzw. eine eigene Rolle mit Gast-Verwaltung), alle anderen Apps: *None*.
5. Im Portal unter **Einstellungen → UniFi / UDM SE** Adresse (`https://<IP-der-UDM>`), Benutzer und Passwort eintragen und **Verbindung testen** – die gefundenen Sites werden angezeigt.

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
- **Weg vom Portal zur UDM-API**: Das Portal muss die UDM SE per HTTPS erreichen – über VPN oder eine Port-Freigabe, die nur die Adresse des Portals zulässt. Ohne feste Absender-IP ist eine VPN-Lösung bzw. ein Relay-Server sauberer.
- Rechtstexte: Links zu Nutzungsbedingungen, Datenschutzerklärung und Impressum unter **Einstellungen → Gästeseite** eintragen (bis dahin wird ein kurzer eingebauter Text gezeigt).
