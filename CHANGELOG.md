# 07.10.2026 - Version 2.0
## Neu
- Schwellwert einstellbar, absolut oder relativ in Prozent (Standard unverändert 0,1).
- Angezeigt werden höchstens 1000 Anomalien, die neuesten zuerst. Bei mehr Treffern, zum Beispiel bei stark schwankenden Werten und zu kleinem Schwellwert, erscheint ein Hinweis, Schwellwert oder Zeitraum anzupassen.
- Rückfrage vor dem Löschen.
- Der Löschungsbericht wird vor dem Löschen erstellt und direkt als CSV-Datei heruntergeladen.
- Fortschrittsbalken bei der Überprüfung und Statusanzeige bei ungültigem Schwellwert oder Zeitraum.
- Achtung: Das Modul benötigt jetzt IP-Symcon 8.1 oder neuer.
- Achtung: Der Webhook für den Löschungsbericht entfällt, ein alter Eintrag im WebHook-Control kann gelöscht werden.

## Fixes
- Der Zeitraum wurde falsch berechnet, Start- und Enddatum werden jetzt geprüft.
- Nach dem Löschen werden alle betroffenen Variablen neu aggregiert, nicht nur die zuletzt gelöschte.
- Anomalien verschiedener Variablen mit gleichem Zeitstempel gingen verloren.
- Löschen rund um die Zeitumstellung traf falsche Zeitpunkte.
- Das Entfernen einer nicht vorhandenen Variable aus der Prüfliste löschte den ersten Eintrag.
- Die Neuprüfung nach dem Löschen nutzte immer aggregierte Daten, auch bei Auswahl von Rohdaten.
- Bei großen Archiven wurden ältere Anomalien nicht gefunden.
- Der aggregierte Modus ist deutlich schneller.
