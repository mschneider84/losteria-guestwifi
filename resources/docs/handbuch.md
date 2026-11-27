# Handbuch Gäste-WLAN

## Wie kommt ein Gast ins WLAN?
1. Der Gast verbindet sich mit dem Gäste-WLAN. Das Handy öffnet automatisch die Gästeseite.
2. Er bestätigt die Nutzungsbedingungen und tippt auf „Kostenlos verbinden“. Optional meldet er sich für den Newsletter an.
3. Das Portal schaltet das Gerät an der UDM SE frei – mit dem Zeitlimit und der Bandbreite des Standorts.
4. Danach wird er auf eure Website oder eine Aktionsseite weitergeleitet.

## Übersicht
Kennzahlen je Zeitraum (7, 14, 30, 90 Tage) und Standort: gerade online, Anmeldungen, Wiederkehrer, neue Newsletter-Kontakte. Darunter Anmeldungen pro Tag, Stoßzeiten und Sprachen.

## Gästeseite & Rechtstexte
Unter Einstellungen → „Gästeseite & Rechtstexte“ schreibst du Nutzungsbedingungen, Datenschutzerklärung und Impressum in Deutsch, Englisch und Italienisch (leere Sprachen zeigen den deutschen Text) und lädst das Standard-Logo hoch. Ein Restaurant kann unter Standorte ein eigenes Logo bekommen. Ändern sich Nutzungsbedingungen oder Datenschutz, wird die Version der Einwilligung automatisch erneuert.

Das Newsletter-Modul schaltest du unter Einstellungen → Gästeseite ein oder aus.

## Netzwerk
Was im WLAN los ist, alle 5 Minuten aktualisiert:
- **Jetzt gerade**: Gäste im WLAN je Restaurant und Bereich, Auslastung im Vergleich zum Höchststand der letzten 30 Tage.
- **Wo sitzen die Gäste?**: Innen, Terrasse, Bar … im Tagesverlauf. Den Bereich gibst du jedem Access Point unten in der Tabelle.
- **Auslastung**: Wochentag × Uhrzeit – je dunkler, desto voller.
- **Verweildauer**: wie lange Gäste bleiben, nach Ankunftszeit, Bereich und Wochentag.
- **Stammgäste**: wer wiederkommt und in welchem Abstand; dazu, ob Gäste nach einem Klick auf eine Aktion häufiger wiederkommen.
- **Technik**: Gerätetypen, Datenverbrauch und Access Points mit Problemen (offline, viele Funk-Wiederholungen, überlastet).

Geräte werden nur pseudonym gezählt; Besuche werden nach 90 Tagen gelöscht (einstellbar unter Einstellungen → Datenschutz).

## Standorte
Jedes Restaurant hat eine eigene UniFi-Site, Farbe, Logo, Texte in drei Sprachen, Zeitlimit und Bandbreite. „Vorschau“ zeigt die Gästeseite, ohne etwas freizuschalten.

## Newsletter
Gäste, die den Haken setzen, bekommen eine Bestätigungs-Mail. Erst nach dem Klick sind sie „bestätigt“ und landen im CSV-Export. Unbestätigte werden nach 14 Tagen gelöscht.

## Demo-Modus
Im Benutzermenü. Zeigt erfundene Standorte und Zahlen. An der UDM wird nichts geändert und es werden keine Mails verschickt.

## Einrichtung der UDM SE
Siehe README im Repository: Service-Konto, External Portal, Walled Garden.
