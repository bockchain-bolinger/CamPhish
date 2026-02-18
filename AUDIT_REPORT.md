# Technischer Auditbericht (statischer Check)

## Umfang
Dieser Audit basiert auf **statischer Analyse** und lokalen Syntax-Checks der vorhandenen Bash- und PHP-Dateien.

## Ausgeführte Checks
- `bash -n camphish.sh`
- `bash -n cleanup.sh`
- `php -l debug_log.php`
- `php -l ip.php`
- `php -l location.php`
- `php -l post.php`
- `php -l template.php`

Ergebnis: Keine Syntaxfehler in den geprüften Dateien.

## Zentrale Befunde

### 1) Security / Datenschutz
1. **Unvalidierte und unbereinigte Eingaben** in `post.php`, `location.php`, `debug_log.php` können zu Log-/Datei-Missbrauch führen.
2. **IP-Ermittlung über unzuverlässige Header** in `ip.php` (`HTTP_CLIENT_IP`, `HTTP_X_FORWARDED_FOR`) ohne Proxy-Trust-Modell.
3. **Schwache Dateirechte**: `location.php` setzt `saved.locations.txt` auf `0666` (world-writable).
4. **Keine Request-Authentisierung/CSRF-Schutz**: Endpunkte akzeptieren beliebige POST-Requests.
5. **Fehlende Rate-Limits**: Potenzial für Spam/DoS durch massenhafte Uploads.
6. **Keine Content-Sicherheitsheader** (CSP, X-Content-Type-Options, etc.).
7. **Unsichere Downloads in `camphish.sh`** via `wget --no-check-certificate` (TLS-Verifikation deaktiviert).

### 2) Data Integrity
8. **Keine atomaren Schreibvorgänge/Locks** bei parallelen Requests (Race Conditions bei Dateischreiben).
9. **Kein einheitliches Datenformat** (freie Textdateien statt strukturiertem JSON/CSV mit Schema).
10. **Unklare Fehlerbehandlung**: `post.php` schreibt Dateien auch bei invalidem Payloadpfad weiter.
11. **Fehlende Größenlimits** für Bild-POST (`cat`) – kann Speicherplatz/Memory überlasten.

### 3) Performance
12. **Busy-Wait-Schleife** in `camphish.sh` (`while true` + `sleep 0.5`) erzeugt unnötige Last.
13. **Mehrfaches Öffnen/Schließen von Dateien** pro Request in PHP; kein Puffer-/Batch-Ansatz.
14. **Dateibasierte Persistenz** skaliert schlecht bei vielen gleichzeitigen Requests.
15. **Redundante Kopieroperation** in `location.php` (Datei erzeugen + zusätzlich in `saved_locations` kopieren).

### 4) Wartbarkeit / Qualität
16. **Gemischte Verantwortlichkeiten** (Transport, Persistenz, Logging in einem Skript).
17. **Fehlende gemeinsame Validierungsfunktionen** (DRY-Verletzungen).
18. **Inkonstante Namenskonventionen** (`saved.locations.txt`, `LocationLog.log`, `current_location.txt`).
19. **Kaum automatisierte Tests** über Syntax hinaus.
20. **Fehlende strukturierte Logs** (kein Severity/Context/Request-ID).

## Priorisierte Optimierungen

### P0 (sofort)
1. Serverseitige Input-Validierung + Sanitizing für alle POST-Felder (`filter_var`, Regex, Länge, Typ).
2. Dateirechte härten (`0640`/`0600`), keine `0666`-Dateien.
3. TLS-Prüfung beim Binary-Download aktiv lassen (kein `--no-check-certificate`), optional SHA256-Prüfsummen verifizieren.
4. Request-Limits einführen (Payload-Size, Request-Frequenz pro IP).
5. Schreibzugriffe mit `flock` absichern.

### P1 (kurzfristig)
6. Einheitliches JSON-Logformat und zentrale Logging-Funktion.
7. IP-Handling korrekt hinter Reverse Proxy (explizite Trusted Proxy Liste).
8. Fehlerobjekte standardisieren (`status`, `code`, `message`).
9. Speichergrenzen für Uploads (PHP ini / Anwendungslogik) und harte Abweisung bei Überschreitung.

### P2 (mittelfristig)
10. Dateibasierte Speicherung durch echte Datenbank ersetzen (z. B. Postgres) für Konsistenz + Querybarkeit.
11. Polling durch Event-/Queue-basierten Ablauf ersetzen.
12. Strukturierte Test-Pipeline: Lint + statische Analyse + Integrationstests.

## Vorschlag für Test-Checklist (minimal)
1. Lint/Syntax als CI-Gate.
2. Negative Tests: invalide `lat/lon`, leere Payloads, oversized Base64-Uploads.
3. Concurrency-Test: parallele POSTs auf `location.php`/`post.php`.
4. Permission-Test: Dateirechte nach Schreibzugriff prüfen.
5. Security-Header-Test via `curl -I`.

## Einschränkung
Der Audit ist ein lokaler statischer Check. Kein End-to-End-Test mit realem Tunnel/Browser-Flow durchgeführt.

## Umgesetzte Sofortmaßnahmen (Hardening)
1. Input-Validierung und einheitliche JSON-Fehlerobjekte in `location.php`, `post.php`, `debug_log.php` ergänzt.
2. Dateischreibzugriffe auf `file_put_contents(..., LOCK_EX)` umgestellt (Race-Condition-Risiko reduziert).
3. Unsichere Download-Option `--no-check-certificate` aus `camphish.sh` entfernt.
4. `ip.php` auf sauberere IP-Ermittlung mit optionalem Proxy-Trust (`TRUST_PROXY=1`) umgestellt.
5. Dateirechte für neue `saved.locations.txt` auf `0640` gehärtet.

## Noch offene Optimierungen
- SHA256-Prüfsummen-Validierung der heruntergeladenen Binaries.
- Rate-Limiting pro Quell-IP (z. B. Token-Bucket über temp-Datei/Redis).
- Umstieg von Dateispeicherung auf DB-Backed Persistenz.
- End-to-End- und Negativtests in CI.
