# ArchivdatenAnomalien
Findet einzelne Ausreißer im Archiv geloggter Zählervariablen, zeigt sie an und löscht ausgewählte Werte auf Wunsch aus dem Archiv. Über jede Löschung wird ein Bericht erstellt.

## Inhaltsverzeichnis
- [ArchivdatenAnomalien](#archivdatenanomalien)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Voraussetzungen](#1-voraussetzungen)
  - [2. Funktionsumfang](#2-funktionsumfang)
  - [3. Enthaltene Module](#3-enthaltene-module)
  - [4. Installation](#4-installation)
  - [5. Konfiguration in IP-Symcon](#5-konfiguration-in-ip-symcon)
  - [6. Spenden](#6-spenden)
  - [7. Lizenz](#7-lizenz)

## 1. Voraussetzungen

* mindestens IPS Version 8.1
* eine Archiv-Instanz (Archive Control) mit geloggten Variablen, die im Archiv als Zähler konfiguriert sind

## 2. Funktionsumfang
* Aufspüren einzelner Ausreißer (Spitzen nach oben oder unten) in den Rohdaten oder in den aggregierten Daten des Archivs
* Einstellbarer Schwellwert, absolut oder relativ in Prozent
* Gezieltes Löschen ausgewählter Anomalien aus dem Archiv, mit Rückfrage
* Löschungsbericht als CSV-Datei
* Funktionen für Skripte: Überprüfung, Löschen und Bericht

## 3. Enthaltene Module

* [ArchivdatenAnomalien](ArchivdatenAnomalien/README.md)

## 4. Installation
Installation über den IP-Symcon Module Store.

## 5. Konfiguration in IP-Symcon
Nach der Installation wird unter "Instanz hinzufügen" eine Instanz "ArchivdatenAnomalien" angelegt. In der Konfiguration werden die zu prüfenden Variablen und der Zeitraum ausgewählt, danach wird die Überprüfung gestartet. Die gefundenen Anomalien erscheinen in einer Liste und können dort zum Löschen ausgewählt werden.

## 6. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EK4JRP87XLSHW" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a> <a href="https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share" target="_blank">Amazon Wunschzettel</a>

## 7. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
