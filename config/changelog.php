<?php

// Oberster Eintrag = aktuelle Version (App\Support\Version). Neueste zuerst.
return [
    ['version' => '1.1.0', 'build' => 3, 'date' => '2026-11-27 00:12', 'type' => 'new', 'area' => 'Schnittstelle', 'audience' => 'admin', 'source' => null, 'module' => 'api',
        'text' => 'Schnittstelle zum L\'Osteria Dashboard: Das Dashboard holt die WLAN-Auswertungen je Restaurant ab und schickt Marketingaktionen. Schlüssel legst du unter Einstellungen → Dashboard-Schnittstelle an.',
        'text_en' => 'Interface to the L\'Osteria dashboard: the dashboard fetches Wi-Fi statistics per restaurant and sends marketing campaigns. Create keys under Settings → Dashboard interface.'],
    ['version' => '1.1.0', 'build' => 3, 'date' => '2026-11-27 00:12', 'type' => 'new', 'area' => 'Gästeseite', 'audience' => 'all', 'source' => null, 'module' => 'portal',
        'text' => 'Marketingaktionen aus dem Dashboard erscheinen auf der Gästeseite – je Restaurant, Zeitraum, Wochentag und Uhrzeit, mit Bild und Button. Aufrufe und Klicks werden gezählt; Übersicht unter „Aktionen“.',
        'text_en' => 'Marketing campaigns from the dashboard appear on the guest page – per restaurant, period, weekday and time, with image and button. Views and clicks are counted; overview under “Campaigns”.'],
    ['version' => '1.1.0', 'build' => 3, 'date' => '2026-11-27 00:12', 'type' => 'fix', 'area' => 'Standorte', 'audience' => 'admin', 'source' => null, 'module' => 'system',
        'text' => 'Logos werden im Speicher von Laravel Cloud abgelegt und über das Portal ausgeliefert, damit sie auch nach einem Deploy erhalten bleiben.',
        'text_en' => 'Logos are stored in the Laravel Cloud bucket and served through the portal so they survive deploys.'],
    ['version' => '1.0.1', 'build' => 2, 'date' => '2026-11-26 23:54', 'type' => 'fix', 'area' => 'System', 'audience' => 'admin', 'source' => null, 'module' => 'system',
        'text' => 'Läuft jetzt mit der MySQL-Datenbank von Laravel Cloud statt einer lokalen Datei. Ist ein KV-Speicher angehängt, werden Cache und Sitzungen dort abgelegt.',
        'text_en' => 'Now runs on the Laravel Cloud MySQL database instead of a local file. If a KV store is attached, cache and sessions use it.'],
    ['version' => '1.0.0', 'build' => 1, 'date' => '2026-11-26 23:39', 'type' => 'new', 'area' => 'Gästeseite', 'audience' => 'all', 'source' => null, 'module' => 'portal',
        'text' => 'Eigene Gästeseite fürs WLAN: Deutsch, Englisch und Italienisch, Anmeldung per Klick mit Nutzungsbedingungen und freiwilliger Newsletter-Anmeldung mit Bestätigungs-Mail.',
        'text_en' => 'Own guest Wi-Fi page: German, English and Italian, one-click login with terms of use and optional newsletter sign-up with confirmation e-mail.'],
    ['version' => '1.0.0', 'build' => 1, 'date' => '2026-11-26 23:39', 'type' => 'new', 'area' => 'UniFi', 'audience' => 'admin', 'source' => null, 'module' => 'unifi',
        'text' => 'Gäste werden direkt an der UDM SE freigeschaltet, mit Zeitlimit und Bandbreite je Standort. Verbindung lässt sich in den Einstellungen testen.',
        'text_en' => 'Guests are authorized directly on the UDM SE, with time and bandwidth limits per location. Connection can be tested in the settings.'],
    ['version' => '1.0.0', 'build' => 1, 'date' => '2026-11-26 23:39', 'type' => 'new', 'area' => 'Übersicht', 'audience' => 'admin', 'source' => null, 'module' => 'dashboard',
        'text' => 'Übersicht mit Anmeldungen pro Tag und Restaurant, Wiederkehrern, Sprachen und Stoßzeiten. Newsletter-Kontakte als CSV exportieren.',
        'text_en' => 'Overview with logins per day and restaurant, returning guests, languages and peak hours. Export newsletter contacts as CSV.'],
    ['version' => '1.0.0', 'build' => 1, 'date' => '2026-11-26 23:39', 'type' => 'new', 'area' => 'System', 'audience' => 'admin', 'source' => null, 'module' => 'system',
        'text' => 'Demo-Modus zum Vorführen ohne echte UDM, Systemstatus, Wünsche & Fehler, Benutzer mit Rollen und automatische Löschfristen.',
        'text_en' => 'Demo mode for presenting without a real UDM, system status, wishes & bugs, users with roles and automatic deletion periods.'],
];
