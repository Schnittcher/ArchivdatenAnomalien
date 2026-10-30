# Changelog

## 06.10.2026 - Version 2.0 (Beta Version)

### Neu
- Schwellwert einstellbar, absolut oder relativ in Prozent (Standard unverändert 0,1)
- Rückfrage vor dem Löschen
- Löschbericht wird vor dem Löschen erstellt und direkt als CSV heruntergeladen
- Fortschrittsbalken während der Prüfung
- Umstellung auf `IPSModuleStrict`

### Fixes
- Zeitraum wurde falsch berechnet (Datumsfehler), Start- und Enddatum werden jetzt geprüft
- Beim Löschen wurde nur die letzte Variable neu aggregiert, jetzt alle betroffenen
- Treffer verschiedener Variablen mit gleichem Zeitstempel gingen verloren
- Falsche Zeitpunkte beim Löschen rund um die Zeitumstellung, es wird jetzt der echte Zeitstempel verwendet
- Entfernen einer nicht vorhandenen Variable aus der Prüfliste löschte den ersten Eintrag
- Neuprüfung nach dem Löschen verwendete immer aggregierte Daten
- Speicherfehler bei sehr vielen Rohwerten (ca. 250.000 und mehr), die Daten werden jetzt blockweise geprüft
- Abgeschnittene Archivabfragen: ältere Anomalien wurden nicht gefunden
- Aggregierter Modus prüft Rohdaten nur noch am auffälligen Tag sowie Vor- und Folgetag (deutlich schneller)
- Tippfehler und fehlende Übersetzungen korrigiert

## 07.07.2023 - Version 1.0 (Stable)

### Neu
- Erste stabile Version.
