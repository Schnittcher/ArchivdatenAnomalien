<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace ArchivdatenAnomalien {?>' . file_get_contents(__DIR__ . '/../libs/vendor/SymconModulHelper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace ArchivdatenAnomalien {?>' . file_get_contents(__DIR__ . '/../libs/vendor/SymconModulHelper/WebhookHelper.php') . '}');

class ArchivdatenAnomalien extends IPSModule
{
    use \ArchivdatenAnomalien\DebugHelper;
    use \ArchivdatenAnomalien\WebhookHelper;

    private const ARCHIVE_CONTROL_GUID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
    private const EMPTY_DATE = '{"year":0,"month":0,"day":0}';
    //Ab dieser Anzahl Werte pro AC_GetLoggedValues-Abfrage kann das Ergebnis abgeschnitten sein
    private const LOGGED_VALUES_LIMIT = 10000;
    //Stufe für AC_GetAggregatedValues: 1 = täglich
    private const AGGREGATION_LEVEL_DAILY = 1;
    //Schwellwert-Arten
    private const THRESHOLD_ABSOLUTE = 0;
    private const THRESHOLD_PERCENT = 1;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString('CheckedVariables', '[]');
        $this->RegisterPropertyString('StartDate', self::EMPTY_DATE);
        $this->RegisterPropertyString('EndDate', self::EMPTY_DATE);
        $this->RegisterPropertyFloat('Threshold', 0.1);
        $this->RegisterPropertyInteger('ThresholdType', self::THRESHOLD_ABSOLUTE);
        $this->RegisterAttributeString('lastDeletedValues', '');

        $this->SetBuffer('CheckedVariables', '[]');
        $this->SetBuffer('LastCheck', '');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $checkedVariables = $this->ReadPropertyString('CheckedVariables');
        $this->SetBuffer('CheckedVariables', $checkedVariables);

        //Der Webhook für den Löschbericht wird nicht mehr benötigt, alten Eintrag entfernen
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->UnregisterHook('/hook/DeletionReport/' . $this->InstanceID);
        }
    }

    public function GetConfigurationForm()
    {
        //Reset Liste CheckedVariables, falls nicht gespeichert wurde
        $checkedVariables = $this->ReadPropertyString('CheckedVariables');
        $this->SetBuffer('CheckedVariables', $checkedVariables);

        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        //Es werden nur Zählervariablen (AggregationType 1) angeboten
        $listValues = [];
        $archiveID = $this->getArchiveID();
        if ($archiveID > 0) {
            foreach (AC_GetAggregationVariables($archiveID, false) as $variable) {
                if ($variable['AggregationType'] == 1) {
                    $listValues[] = [
                        'VariableID'        => $variable['VariableID'],
                        'editable'          => false
                    ];
                }
            }
        }
        $this->setListValues($Form['elements'], 'allVariables', $listValues);
        return json_encode($Form);
    }

    public function deleteAnomalies($resultList)
    {
        $archiveID = $this->getArchiveID();
        if ($archiveID == 0) {
            $this->showPopup($this->Translate('No archive control instance found.'));
            return;
        }

        $toDelete = [];
        foreach ($this->extractRows($resultList) as $row) {
            if (empty($row['Delete'])) {
                continue;
            }
            $timeStamp = $this->getRowTimeStamp($row);
            if ($timeStamp !== null) {
                $toDelete[] = ['row' => $row, 'timeStamp' => $timeStamp];
            }
        }

        $deleted = count($toDelete);
        if ($deleted == 0) {
            $this->showPopup($this->Translate('No anomalies selected.'));
            return;
        }

        //Bericht zuerst schreiben, damit er auch bei einem Abbruch während des Löschens vorhanden ist
        $this->arrayToCSV(array_column($toDelete, 'row'));
        $this->SendDebug('Delete', $deleted . ' values', 0);

        $affectedVariables = [];
        foreach ($toDelete as $entry) {
            $variableID = (int) $entry['row']['VariableID'];
            AC_DeleteVariableData($archiveID, $variableID, $entry['timeStamp'], $entry['timeStamp']);
            $affectedVariables[$variableID] = true;
        }
        foreach (array_keys($affectedVariables) as $variableID) {
            AC_ReAggregateVariable($archiveID, $variableID);
        }

        if ($deleted == 1) {
            $this->showPopup($deleted . ' ' . $this->Translate('anomaly deleted.'));
        } else {
            $this->showPopup($deleted . ' ' . $this->Translate('anomalies have been deleted.'));
        }

        //Liste mit den gleichen Parametern wie bei der letzten Prüfung neu laden
        $lastCheck = json_decode($this->GetBuffer('LastCheck'), true);
        if (is_array($lastCheck)) {
            $resultListValues = $this->collectAnomalies(
                (bool) $lastCheck['rawData'],
                (int) $lastCheck['startDate'],
                (int) $lastCheck['endDate'],
                (array) $lastCheck['variableIDs'],
                (float) $lastCheck['threshold'],
                (int) $lastCheck['thresholdType']
            );
            $this->UpdateFormField('resultList', 'values', json_encode($resultListValues));
        }
    }

    public function setAllListEntriesActive($resultList)
    {
        $listValues = [];
        foreach ($this->extractRows($resultList) as $row) {
            $row['Delete'] = true;
            $listValues[] = $row;
        }
        $this->UpdateFormField('resultList', 'values', json_encode($listValues));
    }

    public function checkAnomalies(bool $rawData = false, $startDate = null, $endDate = null)
    {
        if ($this->getArchiveID() == 0) {
            $this->showPopup($this->Translate('No archive control instance found.'));
            return [];
        }

        //Datum aus dem Formular verwenden, sonst die gespeicherte Konfiguration
        $start = $this->parseDate($startDate ?? $this->ReadPropertyString('StartDate'), false);
        $end = $this->parseDate($endDate ?? $this->ReadPropertyString('EndDate'), true);

        if ($start === null || $end === null) {
            $this->showPopup($this->Translate('Please select a valid date range.'));
            return [];
        }
        if ($start > $end) {
            $this->showPopup($this->Translate('Start date must not be after end date.'));
            return [];
        }

        //Der Buffer entspricht dem aktuellen Stand der Liste im Formular (auch ungespeichert)
        $listVariableIDs = json_decode($this->GetBuffer('CheckedVariables'), true);
        if (!is_array($listVariableIDs)) {
            $listVariableIDs = [];
        }
        $variableIDs = array_map('intval', array_column($listVariableIDs, 'VariableID'));

        $threshold = max(0.0, $this->ReadPropertyFloat('Threshold'));
        $thresholdType = $this->ReadPropertyInteger('ThresholdType');

        $startDate = $start - 86400;
        $endDate = $end + 86400;

        $this->SetBuffer('LastCheck', json_encode([
            'rawData'       => $rawData,
            'startDate'     => $startDate,
            'endDate'       => $endDate,
            'variableIDs'   => $variableIDs,
            'threshold'     => $threshold,
            'thresholdType' => $thresholdType
        ]));

        $resultListValues = $this->collectAnomalies($rawData, $startDate, $endDate, $variableIDs, $threshold, $thresholdType);
        $this->UpdateFormField('resultList', 'values', json_encode($resultListValues));

        return $resultListValues;
    }

    public function addCheckedVariables($variableID)
    {
        if ($variableID > 0) {
            $values = json_decode($this->GetBuffer('CheckedVariables'), true);
            if (!is_array($values)) {
                $values = [];
            }

            if (array_search((int) $variableID, array_map('intval', array_column($values, 'VariableID')), true) === false) {
                $values[] = [
                    'VariableID'        => $variableID,
                    'editable'          => false
                ];
            }
            $this->SetBuffer('CheckedVariables', json_encode($values));
            $this->UpdateFormField('CheckedVariables', 'values', json_encode($values));
        }
    }

    public function deleteCheckedVariables($variableID)
    {
        $values = json_decode($this->GetBuffer('CheckedVariables'), true);
        if (!is_array($values)) {
            return;
        }
        $values = array_values($values);

        $key = array_search((int) $variableID, array_map('intval', array_column($values, 'VariableID')), true);
        if ($key === false) {
            return;
        }
        unset($values[$key]);
        $values = array_values($values);
        $this->SetBuffer('CheckedVariables', json_encode($values));
        $this->UpdateFormField('CheckedVariables', 'values', json_encode($values));
    }

    public function DownloadDeletionReport()
    {
        //Der Button hat das Attribut "download": bei einer Data-URL speichert die Konsole sie als Datei.
        //Nur zurückgeben, das echo steht im onClick (Ausgaben aus Modulfunktionen werden als Warning umhüllt).
        $csv = $this->buildDeletionReport();
        if ($csv == '') {
            return $this->Translate('No deletion report available.');
        }
        return 'data:text/csv;base64,' . base64_encode($csv);
    }

    private function getArchiveID()
    {
        $ids = IPS_GetInstanceListByModuleID(self::ARCHIVE_CONTROL_GUID);
        if (count($ids) == 0) {
            return 0;
        }
        return $ids[0];
    }

    /**
     * Setzt die Werte einer Liste im Formular anhand ihres Namens, auch in verschachtelten Elementen.
     */
    private function setListValues(array &$elements, string $name, array $values)
    {
        foreach ($elements as &$element) {
            if (($element['name'] ?? '') === $name) {
                $element['values'] = $values;
                return true;
            }
            if (isset($element['items']) && $this->setListValues($element['items'], $name, $values)) {
                return true;
            }
        }
        return false;
    }

    private function buildDeletionReport()
    {
        $report = $this->ReadAttributeString('lastDeletedValues');
        if ($report == '') {
            return '';
        }
        $csv = $this->Translate('Date') . ';' . $this->Translate('VariableID') . ';' . $this->Translate('Value before anomaly') . ';' . $this->Translate('Value') . ';' . $this->Translate('Value after anomaly') . PHP_EOL;
        return $csv . $report;
    }

    private function collectAnomalies(bool $rawData, int $startDate, int $endDate, array $variableIDs, float $threshold, int $thresholdType)
    {
        $archiveID = $this->getArchiveID();
        if ($archiveID == 0) {
            return [];
        }

        //Schlüssel VariableID|TimeStamp verhindert Doppelte, ohne Treffer anderer Variablen zu verlieren
        $resultListValues = [];
        $total = count($variableIDs);
        $done = 0;
        $this->setProgress(0, true);
        foreach ($variableIDs as $variableID) {
            if ($rawData) {
                $windows = [[$startDate, $endDate]];
            } else {
                //Auffällige Tage über die täglichen Mittelwerte finden und nur dort die Rohwerte prüfen
                $values = AC_GetAggregatedValues($archiveID, $variableID, self::AGGREGATION_LEVEL_DAILY, $startDate, $endDate, 0);
                $windows = [];
                foreach ($this->filterVariable($values, false, $variableID, $threshold, $thresholdType) as $Value) {
                    //Vorheriger, betroffener und folgender Tag
                    $windows[] = [
                        max($startDate, $Value['TimeStamp'] - 86400),
                        min($endDate, $Value['TimeStamp'] + 2 * 86400 - 1)
                    ];
                }
                $windows = $this->mergeWindows($windows);
            }

            foreach ($windows as $window) {
                $rawValues = $this->getAllLoggedValues($archiveID, $variableID, $window[0], $window[1]);
                foreach ($this->filterVariable($rawValues, true, $variableID, $threshold, $thresholdType) as $rawValue) {
                    $resultListValues[$variableID . '|' . $rawValue['TimeStamp']] = $rawValue;
                }
            }

            $done++;
            $this->setProgress((int) round($done / $total * 100), true);
        }
        $this->setProgress(100, false);
        $this->SendDebug('Check', count($resultListValues) . ' anomalies in ' . $total . ' variables', 0);

        return array_values($resultListValues);
    }

    /**
     * Holt alle Rohwerte eines Zeitraums. AC_GetLoggedValues begrenzt die Anzahl (laut Dokumentation 10000,
     * auf Kernel 9.1 wurden 50000 beobachtet) und liefert die neuesten zuerst. Ab 10000 Werten wird deshalb
     * ab dem ältesten erhaltenen Wert weitergelesen, bis eine Abfrage weniger Werte liefert.
     */
    private function getAllLoggedValues(int $archiveID, int $variableID, int $startDate, int $endDate)
    {
        $allValues = [];
        while ($endDate >= $startDate) {
            $chunk = AC_GetLoggedValues($archiveID, $variableID, $startDate, $endDate, 0);
            $allValues = array_merge($allValues, $chunk);
            if (count($chunk) < self::LOGGED_VALUES_LIMIT) {
                break;
            }
            $endDate = $chunk[count($chunk) - 1]['TimeStamp'] - 1;
        }
        return $allValues;
    }

    /**
     * Fasst sich überlappende oder direkt aufeinanderfolgende Zeitfenster zusammen.
     */
    private function mergeWindows(array $windows)
    {
        usort($windows, function ($a, $b)
        {
            return $a[0] <=> $b[0];
        });
        $merged = [];
        foreach ($windows as $window) {
            $last = count($merged) - 1;
            if ($last >= 0 && $window[0] <= $merged[$last][1] + 1) {
                $merged[$last][1] = max($merged[$last][1], $window[1]);
            } else {
                $merged[] = $window;
            }
        }
        return $merged;
    }

    private function setProgress(int $percent, bool $visible)
    {
        $this->UpdateFormField('Progress', 'current', $percent);
        $this->UpdateFormField('Progress', 'visible', $visible);
    }

    /**
     * Wandelt einen SelectDate-Wert (JSON-String, Array oder Objekt) in einen Timestamp.
     * Gibt null zurück, wenn kein gültiges Datum gewählt ist.
     */
    private function parseDate($value, bool $endOfDay)
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        $value = (array) $value;
        if (!isset($value['day'], $value['month'], $value['year'])) {
            return null;
        }
        $day = (int) $value['day'];
        $month = (int) $value['month'];
        $year = (int) $value['year'];
        if (!checkdate($month, $day, $year)) {
            return null;
        }
        if ($endOfDay) {
            return mktime(23, 59, 59, $month, $day, $year);
        }
        return mktime(0, 0, 0, $month, $day, $year);
    }

    /**
     * Die Liste wird je nach Aufrufweg unterschiedlich verschachtelt übergeben.
     * Gibt alle Zeilen (Arrays mit VariableID) als flache Liste zurück.
     */
    private function extractRows($data)
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        if (is_object($data)) {
            $data = (array) $data;
        }
        if (!is_array($data)) {
            return [];
        }
        if (isset($data['VariableID'])) {
            return [$data];
        }
        $rows = [];
        foreach ($data as $entry) {
            $rows = array_merge($rows, $this->extractRows($entry));
        }
        return $rows;
    }

    private function getRowTimeStamp($row)
    {
        if (isset($row['TimeStamp'])) {
            return (int) $row['TimeStamp'];
        }
        //Fallback für Zeilen ohne TimeStamp
        $timeStamp = strtotime((string) ($row['Date'] ?? ''));
        return $timeStamp === false ? null : $timeStamp;
    }

    private function showPopup(string $text)
    {
        $this->UpdateFormField('PopupInfoLabel', 'caption', $text);
        $this->UpdateFormField('PopupInfo', 'visible', false);
        $this->UpdateFormField('PopupInfo', 'visible', true);
    }

    private function arrayToCSV($values)
    {
        $csv = '';
        foreach ($values as $value) {
            //Nur die Berichtsspalten, ohne Lösch-Flag und TimeStamp
            $csv .= implode(';', [
                $value['Date'],
                $value['VariableID'],
                $value['ValueBefore'],
                $value['Value'],
                $value['ValueAfter']
            ]) . PHP_EOL;
        }
        $this->WriteAttributeString('lastDeletedValues', $csv);
    }

    /**
     * Sucht Werte, die zwischen ihren beiden Nachbarn eine Spitze bilden (Richtungswechsel in beide
     * Richtungen größer als der Schwellwert). Der Schwellwert ist absolut oder in Prozent des größten
     * Betrags der drei Werte.
     */
    private function filterVariable(array $logData, bool $rawData, int $variableID, float $threshold, int $thresholdType)
    {
        $keyValue = 'Avg';
        if ($rawData) {
            $keyValue = 'Value';
        }
        $failedValues = [];

        //Neueste zuerst, wie vom Archiv geliefert (ValueBefore/ValueAfter hängen davon ab)
        usort($logData, function ($a, $b)
        {
            return $b['TimeStamp'] <=> $a['TimeStamp'];
        });

        // Macht erst ab 3 Werten Sinn
        $entries = count($logData);
        if ($entries < 3) {
            return $failedValues;
        }
        for ($i = 2; $i < $entries; $i++) {
            $newer = $logData[$i - 2][$keyValue];
            $middle = $logData[$i - 1][$keyValue];
            $older = $logData[$i][$keyValue];

            $limit = $threshold;
            if ($thresholdType == self::THRESHOLD_PERCENT) {
                $limit = $threshold / 100 * max(abs($newer), abs($middle), abs($older));
            }

            // Differenz mittlerer - neuerer Wert und älterer - mittlerer Wert
            $diff1 = $middle - $newer;
            $diff2 = $older - $middle;
            // Wenn der mittlere Wert entweder der größte oder kleinste Wert ist stimmt was nicht
            if ((($diff1 < -$limit) && ($diff2 > $limit)) ||
                (($diff1 > $limit) && ($diff2 < -$limit))) {
                $failedValues[] = [
                    'Date'        => date('d.m.Y H:i:s', $logData[$i - 1]['TimeStamp']),
                    'TimeStamp'   => $logData[$i - 1]['TimeStamp'],
                    'VariableID'  => $variableID,
                    'ValueBefore' => $older,
                    'Value'       => $middle,
                    'ValueAfter'  => $newer
                ];
            }
        }
        return $failedValues;
    }
}
