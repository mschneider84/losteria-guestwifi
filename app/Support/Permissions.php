<?php

namespace App\Support;

/** Rechte-Katalog. Geprüft wird nur über hasPerm()/@can, nie über Rollennamen. */
class Permissions
{
    public const ALL = [
        'dashboard.view' => ['Übersicht', 'Übersicht ansehen', 'Kennzahlen und Statistik der Standorte.'],
        'locations.manage' => ['Standorte', 'Standorte bearbeiten', 'Branding, Zeitlimit, Bandbreite und Weiterleitung je Standort.'],
        'contacts.view' => ['Kontakte', 'Newsletter-Kontakte ansehen', 'Liste der Anmeldungen mit Status.'],
        'contacts.export' => ['Kontakte', 'Kontakte exportieren', 'CSV-Export der bestätigten Newsletter-Kontakte.'],
        'settings.manage' => ['System', 'Einstellungen ändern', 'UniFi-Zugang, Mail, Löschfristen, Branding.'],
        'users.manage' => ['System', 'Benutzer verwalten', 'Benutzer anlegen, Rollen vergeben.'],
        'status.view' => ['System', 'Systemstatus ansehen', 'Selbsttest, UniFi-Verbindung, Version.'],
        'meldungen.manage' => ['System', 'Wünsche & Fehler bearbeiten', 'Status setzen, antworten, an KI übergeben.'],
        'demo' => ['System', 'Demo-Modus', 'Mit erfundenen Daten vorführen, ohne echte UDM.'],
    ];

    public const ROLES = [
        'superadmin' => ['Superadmin', '*'],
        'admin' => ['Admin', ['dashboard.view', 'locations.manage', 'contacts.view', 'contacts.export', 'settings.manage', 'status.view', 'meldungen.manage', 'demo']],
        'manager' => ['Restaurantleitung', ['dashboard.view', 'contacts.view', 'demo']],
        'viewer' => ['Leser', ['dashboard.view']],
    ];

    public static function forRole(string $role): array
    {
        $perms = self::ROLES[$role][1] ?? [];

        return $perms === '*' ? array_keys(self::ALL) : $perms;
    }
}
