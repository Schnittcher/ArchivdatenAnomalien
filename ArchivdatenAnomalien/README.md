# ArchivdatenAnomalien
Das Modul spürt Anomalien (einzelne Ausreißer) im Archiv geloggter Zählervariablen auf und zeigt sie in einer Liste an. Ausgewählte Werte können direkt aus dem Archiv gelöscht werden. Über jede Löschung erstellt das Modul einen Bericht, der als CSV-Datei heruntergeladen werden kann. Die Instanz wird manuell über "Instanz hinzufügen" angelegt und bedient sich über ihr Konfigurationsformular. Eine Archiv-Instanz (Archive Control) wird vorausgesetzt.

## Inhaltsverzeichnis
- [ArchivdatenAnomalien](#archivdatenanomalien)
  - [Inhaltsverzeichnis](#inhaltsverzeichnis)
  - [1. Konfiguration](#1-konfiguration)
  - [2. Variablen](#2-variablen)
  - [3. Funktionen](#3-funktionen)
  - [4. Spenden](#4-spenden)
  - [5. Lizenz](#5-lizenz)

## 1. Konfiguration

Feld | Beschreibung
------------ | ----------------
Alle geloggten Zählervariablen | Alle geloggten Variablen, die im Archiv als Zähler (Aggregationstyp "Zähler") konfiguriert sind. Mit dem Button ">>" werden sie in die Liste der zu prüfenden Variablen übernommen.
Überprüfung der Variablen auf Anomalien | Die zur Überprüfung ausgewählten Variablen. Mit dem Button "<<" werden sie wieder entfernt. Die Liste wirkt sofort auf die Überprüfung, dauerhaft gespeichert wird sie mit "Änderungen übernehmen".
Startdatum | Erster Tag, ab dem auf Anomalien geprüft wird. Standard: leer, vor der Überprüfung muss ein Zeitraum ausgewählt werden. Das Datum wird aus der gespeicherten Konfiguration gelesen, Änderungen müssen daher vor der Überprüfung mit "Änderungen übernehmen" gespeichert werden.
Enddatum | Letzter Tag der Überprüfung, gleiche Regeln wie beim Startdatum. Liegt das Startdatum nach dem Enddatum, wechselt die Instanz in den Status 203.
Schwellwert | Ab welcher Abweichung ein Wert als Anomalie gilt. Erkannt wird ein Wert, der sich von seinen beiden Nachbarwerten in entgegengesetzter Richtung um mehr als den Schwellwert unterscheidet (Spitze nach oben oder unten). Standard: 0,1. Ein negativer Wert setzt die Instanz in den Status 201.
Art des Schwellwerts | "Absolut" (Standard): Der Schwellwert gilt als Wert der Variable. "Relativ": Der Schwellwert gilt in Prozent des größten Betrags der drei betrachteten Werte. Ein unbekannter Wert setzt die Instanz in den Status 202.
Rohdaten | Ob die Rohdaten oder aggregierte Daten geprüft werden. Bei aggregierten Daten werden zunächst die Tagesmittelwerte geprüft und nur an auffälligen Tagen (sowie am Vor- und Folgetag) die Rohdaten untersucht.
Überprüfe auf Anomalien | Startet die Überprüfung. Ein Fortschrittsbalken zeigt den Stand je Variable. Angezeigt werden höchstens 1000 Anomalien, die neuesten zuerst. Werden mehr gefunden, erscheint ein Hinweis, den Schwellwert zu erhöhen oder den Zeitraum zu verkleinern.
Liste mit Anomalien | Zeigt je Anomalie den Wert vor und nach dem auffälligen Wert sowie den Wert selbst. In der Spalte "Löschen" werden die Einträge ausgewählt, die gelöscht werden sollen.
Alle zu löschenden Einträge auswählen | Markiert alle Einträge der Liste zum Löschen.
Ausgewählte Anomalien löschen | Löscht die markierten Werte nach einer Rückfrage unwiderruflich aus dem Archiv und aggregiert die betroffenen Variablen neu. Danach wird die Liste mit den Einstellungen der letzten Überprüfung neu geladen. Ohne Auswahl wird nichts gelöscht.
Letzten Löschungsbericht herunterladen | Lädt den Bericht der letzten Löschung als CSV-Datei (Datum, Variablen-ID, Wert vor der Anomalie, Wert, Wert nach der Anomalie). Der Bericht wird vor dem Löschen erstellt.

## 2. Variablen
Das Modul legt keine Variablen und keine Profile an.

## 3. Funktionen
Die Funktionen werden vom Konfigurationsformular genutzt und können auch in Skripten aufgerufen werden.

`array AA_checkAnomalies(integer $InstanzID, boolean $Rohdaten);`
Prüft die ausgewählten Variablen im gespeicherten Zeitraum mit dem gespeicherten Schwellwert und gibt die gefundenen Anomalien zurück (Date, TimeStamp, VariableID, ValueBefore, Value, ValueAfter), höchstens 1000 Einträge, die neuesten zuerst. Bei ungültigem Zeitraum wird eine leere Liste zurückgegeben.

`void AA_deleteAnomalies(integer $InstanzID, mixed $Liste);`
Löscht die Einträge der übergebenen Liste, bei denen `Delete` gesetzt ist, aus dem Archiv und aggregiert die betroffenen Variablen neu.

`void AA_setAllListEntriesActive(integer $InstanzID, mixed $Liste);`
Markiert alle Einträge der übergebenen Liste zum Löschen und aktualisiert die Liste im geöffneten Formular.

`void AA_addCheckedVariables(integer $InstanzID, integer $VariablenID);`
Fügt eine Variable zur Liste der zu prüfenden Variablen hinzu.

`void AA_deleteCheckedVariables(integer $InstanzID, integer $VariablenID);`
Entfernt eine Variable aus der Liste der zu prüfenden Variablen.

`string AA_DownloadDeletionReport(integer $InstanzID);`
Gibt den letzten Löschungsbericht als Data-URL (`data:text/csv;base64,…`) zurück. Ist noch kein Bericht vorhanden, wird ein Hinweistext zurückgegeben.

## 4. Spenden
Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EK4JRP87XLSHW" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a> <a href="https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share" target="_blank">Amazon Wunschzettel</a>

## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
