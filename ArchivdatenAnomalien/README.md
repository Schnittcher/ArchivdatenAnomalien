# ArchivdatenAnomalien
Das Modul kann Anomalien im Archiv erkennen.
Die Anomalien werden in einer Liste angezeigt und können direkt entfernt werden.
Zusätzlich erstellt das Modul eine Bericht mit allen Werten welche gelöscht worden sind.

### Inhaltsverzeichnis

- [ArchivdatenAnomalien](#archivdatenanomalien)
    - [Inhaltsverzeichnis](#inhaltsverzeichnis)
    - [1. Funktionsumfang](#1-funktionsumfang)
    - [2. Voraussetzungen](#2-voraussetzungen)
    - [3. Software-Installation](#3-software-installation)
    - [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
    - [5. Statusvariablen und Profile](#5-statusvariablen-und-profile)
    - [6. WebFront](#6-webfront)
    - [7. PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Aufspüren von Anomalien im Archibv
* Entfernen der Anomalien
* Anzeige eines Löschungsbericht

### 2. Voraussetzungen

- IP-Symcon ab Version 6.0

### 3. Software-Installation

* Über den Module Store das 'ArchivdatenAnomalien'-Modul installieren.

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'ArchivdatenAnomalien'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
Alle geloggten Variablen         | In dieser Liste werden alle geloggten Variablen angezeigt, welche mit dem Button ">>" in die Liste der zu prüfenden Variablen aufgenommen werden können.
Überprüfung der Variablen auf Anomalien | In dieserListe werden alle Variablen aufgelistet, welche zur Überprüfung ausgewählt wurden. Mit dem Button "<<" können diese aus der Liste wieder entfernt werden.
Startdatum | Das Startdatum, ab welchem Tag auf Anomalien geprüft werden soll.
Enddatum | Das Enddatum, bis welchem Tag auf Anomalien geprüft werden soll.
Rohdaten | Ob die Rohdaten oder aggregierte Daten geprüft werden sollen.
Überprüfung auf Anomalien | Mit klick auf diesen Button wird die Überprüfung gestartet.
Liste mit Anomalien | In dieser Liste werden die Anomalien aufgezeigt, es wird jeweils der Wert vor der erkannten Anomalie, sowie der Wert der Anomalie und der Wert nach der Anomalie angezeigt. In der Liste können die Werte, welche gelöscht werden sollen ausgewählt werden und mit ienem klick auf den Button "Ausgewählte Anomalien löschen" gelöscht werden.
Letzten Löschungsbericht herunterladen | Mit einem Klick auf diesen Button, wir ein Bericht der letzten Löschung generiert.


### 5. Statusvariablen und Profile

Es werden keine Variablen oder Profile angelegt.

### 6. WebFront

Es gibt eine Funktionalität im Webfront.

### 7. PHP-Befehlsreferenz

Keine Funktionen vorhanden.