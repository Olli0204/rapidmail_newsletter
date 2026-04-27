# Rapidmail Newsletter

JTL-Shop 5 Plugin, das eine vollständige Zwei-Wege-Synchronisierung zwischen dem JTL-Shop-Newsletter und Rapidmail bereitstellt.

## Funktionsweise

- **Anmeldung** — Bestätigt ein Kunde den Double-Opt-In, wird er automatisch in die passende Rapidmail-Empfängerliste übertragen
- **Abmeldung** — Meldet sich ein Kunde über das Shop-Formular ab, wird er gleichzeitig aus Rapidmail entfernt
- **Cronjob** — Läuft täglich und gleicht Abmeldungen aus Rapidmail mit der Shop-Datenbank ab, um veraltete Einträge zu bereinigen
- **Import** — Bestehende Empfänger können per Datumsfilter einmalig aus dem Shop nach Rapidmail importiert werden

Die Sprache des Empfängers (Deutsch/Englisch) bestimmt automatisch, in welche der zwei konfigurierten Empfängerlisten er eingetragen wird.

## Einrichtung

Das Plugin führt über einen vierstufigen Setup-Assistenten im Backend:

### Schritt 1 — Zugangsdaten
API-Benutzername und Passwort aus dem Rapidmail-Konto eintragen. Das Plugin prüft die Verbindung sofort nach dem Absenden.

Eine Anleitung zum Anlegen von API-Zugangsdaten findet sich in der Rapidmail-Dokumentation unter **Einstellungen → API-Zugang**.

### Schritt 2 — Empfängerlisten
Je eine Empfängerliste für Deutsch und Englisch auswählen. Alle vorhandenen Listen aus dem Rapidmail-Konto werden automatisch zur Auswahl geladen.

Neue Listen können direkt aus dem Backend erstellt werden, indem ein Name eingetragen und auf **Neue Liste erstellen** geklickt wird.

### Schritt 3 — Datenimport
Bestehende Shop-Abonnenten nach Rapidmail übertragen. Dazu ein Anmeldedatum eintragen — alle aktiven Empfänger, die sich ab diesem Datum angemeldet haben und noch nicht synchronisiert wurden, werden importiert.

Nach dem Import wird die Anzahl der erfolgreich übertragenen Empfänger angezeigt.

### Schritt 4 — Laufende Einstellungen
Automatische An- und Abmeldung aktivieren oder deaktivieren:

| Einstellung             | Beschreibung                                                                 |
|-------------------------|------------------------------------------------------------------------------|
| AutoRegistration Aktiv  | Neue Anmeldungen werden automatisch an Rapidmail übertragen                  |
| AutoDeregistration Aktiv| Abmeldungen im Shop entfernen den Empfänger gleichzeitig aus Rapidmail       |

## Admin-Widget

Das Dashboard-Widget zeigt auf einen Blick den Status der Konfiguration:

- Zugangsdaten verbunden
- Empfängerlisten verknüpft
- Automatische Anmeldung aktiv
- Automatische Abmeldung aktiv

## Kompatibilität

| Plugin-Version | JTL-Shop      |
|----------------|---------------|
| 1.1.0          | 5.2.4 – 5.7.0 |
| 1.0.3          | 5.2.4 – 5.5.3 |

## Changelog

### 1.1.0
- Sicherheitslücke geschlossen: Datumseingabe im Import-Schritt wird vor SQL-Verwendung validiert
- Gebrochene DOWN-Migration behoben (`WHERE 'kPlugin'` → `WHERE kPlugin`)
- Vergleiche auf `loginValid`-Setting korrigiert (fehlender `->cWert`-Zugriff)
- Widget-Variable `list_valid` → `lists_valid` korrigiert (Statusanzeige war immer inaktiv)
- Tippfehler "Inatkiv" und "Gepeichert!" behoben
- Bootstrap-Klassen `col-sm6` → `col-sm-6` korrigiert
- Ungültiges HTML `</br>` → `<br>` behoben
- Alle snake_case-Methoden in camelCase umbenannt
- Unbenutzte Imports entfernt, `@package` in allen Klassen korrigiert
- `declare(strict_types=1)` ergänzt, Typdeklarationen und Casts vervollständigt
- Migration auf InnoDB und utf8mb4 umgestellt
- MaxShopVersion auf 5.7.0 erhöht

### 1.0.3
- Initiales Release
