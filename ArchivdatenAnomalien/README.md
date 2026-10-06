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

* Aufspüren von Anomalien im Archiv
* Entfernen der Anomalien
* Anzeige eines Löschungsbericht

### 2. Voraussetzungen

- IP-Symcon ab Version 7.0

### 3. Software-Installation

* Über den Module Store das 'ArchivdatenAnomalien'-Modul installieren.

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'ArchivdatenAnomalien'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
Alle geloggten Zählervariablen         | In dieser Liste werden alle geloggten Variablen angezeigt, die im Archiv als Zähler (Aggregationstyp "Zähler") konfiguriert sind. Sie können mit dem Button ">>" in die Liste der zu prüfenden Variablen aufgenommen werden.
Überprüfung der Variablen auf Anomalien | In dieser Liste werden alle Variablen aufgelistet, welche zur Überprüfung ausgewählt wurden. Mit dem Button "<<" können diese aus der Liste wieder entfernt werden.
Startdatum | Das Startdatum, ab welchem Tag auf Anomalien geprüft werden soll.
Enddatum | Das Enddatum, bis welchem Tag auf Anomalien geprüft werden soll. Start- und Enddatum werden aus der gespeicherten Konfiguration gelesen, Änderungen müssen daher vor der Überprüfung mit "Änderungen übernehmen" gespeichert werden.
Schwellwert | Ab welcher Abweichung ein Wert als Anomalie gilt. Ein Wert wird erkannt, wenn er sich von beiden Nachbarwerten in entgegengesetzter Richtung um mehr als den Schwellwert unterscheidet (Spitze nach oben oder unten). Standard: 0,1.
Art des Schwellwerts | "Absolut": Der Schwellwert gilt als Wert der Variable. "Relativ": Der Schwellwert gilt in Prozent des größten Betrags der drei betrachteten Werte.
Rohdaten | Ob die Rohdaten oder aggregierte Daten geprüft werden sollen. Bei aggregierten Daten werden zunächst die Tagesmittelwerte geprüft und nur an auffälligen Tagen (sowie dem Vor- und Folgetag) die Rohdaten untersucht.
Überprüfung auf Anomalien | Mit klick auf diesen Button wird die Überprüfung gestartet.
Liste mit Anomalien | In dieser Liste werden die Anomalien aufgezeigt, es wird jeweils der Wert vor der erkannten Anomalie, sowie der Wert der Anomalie und der Wert nach der Anomalie angezeigt. In der Liste können die Werte, welche gelöscht werden sollen ausgewählt werden und mit einem Klick auf den Button "Ausgewählte Anomalien löschen" gelöscht werden. Vor dem Löschen erscheint eine Rückfrage.
Letzten Löschungsbericht herunterladen | Mit einem Klick auf diesen Button wird ein Bericht der letzten Löschung als CSV-Datei heruntergeladen. Der Bericht wird vor dem eigentlichen Löschen erstellt.


### 5. Statusvariablen und Profile

Es werden keine Variablen oder Profile angelegt.

### 6. WebFront

Es gibt eine Funktionalität im Webfront.

### 7. PHP-Befehlsreferenz

Die Funktionen werden vom Konfigurationsformular genutzt und können auch in Skripten aufgerufen werden.

```php
AA_checkAnomalies(int $InstanzID, bool $Rohdaten, $Startdatum, $Enddatum): array
```
Prüft die ausgewählten Variablen und gibt die gefundenen Anomalien zurück (Date, TimeStamp, VariableID, ValueBefore, Value, ValueAfter). `$Startdatum` und `$Enddatum` sind SelectDate-Werte als JSON (`{"year":2026,"month":10,"day":1}`), bei `null` wird die gespeicherte Konfiguration verwendet. Alle Parameter müssen angegeben werden.

```php
AA_deleteAnomalies(int $InstanzID, $Liste)
```
Löscht die Einträge der übergebenen Liste, bei denen `Delete` gesetzt ist, aus dem Archiv und aggregiert die betroffenen Variablen neu.

```php
AA_setAllListEntriesActive(int $InstanzID, $Liste)
```
Markiert alle Einträge der Liste zum Löschen.

```php
AA_addCheckedVariables(int $InstanzID, int $VariablenID)
AA_deleteCheckedVariables(int $InstanzID, int $VariablenID)
```
Fügt eine Variable zur Liste der zu prüfenden Variablen hinzu bzw. entfernt sie.

```php
AA_DownloadDeletionReport(int $InstanzID): string
```
Gibt den letzten Löschungsbericht als Data-URL (`data:text/csv;base64,…`) zurück.