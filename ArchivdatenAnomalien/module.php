<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace ArchivdatenAnomalien {?>' . file_get_contents(__DIR__ . '/../libs/vendor/SymconModulHelper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace ArchivdatenAnomalien {?>' . file_get_contents(__DIR__ . '/../libs/vendor/SymconModulHelper/WebhookHelper.php') . '}');

class ArchivdatenAnomalien extends IPSModule
{
    use \ArchivdatenAnomalien\DebugHelper;
    use \ArchivdatenAnomalien\WebhookHelper;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('LoggedVariable', 0);
        $this->RegisterPropertyString('CheckedVariables', '[]');
        $this->RegisterPropertyString('StartDate', '{"year":0,"month":0,"day":0}');
        $this->RegisterPropertyString('EndDate', '{"year":0,"month":0,"day":0}');
        $this->RegisterPropertyBoolean('rawData', false);
        $this->RegisterAttributeString('lastDeletedValues', '');

        $this->SetBuffer('CheckedVariables', '[]');
        $this->SetBuffer('LastCheck', '');

        $this->RegisterHook('/hook/DeletionReport/' . $this->InstanceID);
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $checkedVariables = $this->ReadPropertyString('CheckedVariables');
        $this->SetBuffer('CheckedVariables', $checkedVariables);
    }

    public function GetConfigurationForm()
    {
        //Reset Liste CheckedVariables, falls nicht gespeichert wurde
        $checkedVariables = $this->ReadPropertyString('CheckedVariables');
        $this->SetBuffer('CheckedVariables', $checkedVariables);

        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        $archiveID = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];
        $loggedVariables = AC_GetAggregationVariables($archiveID, false);

        $listValues = [];

        foreach ($loggedVariables as $variable) {
            if ($variable['AggregationType'] == 1) {
                $listValues[] = [
                    'VariableID'        => $variable['VariableID'],
                    'editable'          => false
                ];
            }
        }
        $Form['elements'][0]['items'][0]['values'] = $listValues;
        return json_encode($Form);
    }

    public function deleteAnomalies($resultList)
    {
        $archiveID = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];

        $deletedValues = [];
        $affectedVariables = [];
        foreach ($this->extractRows($resultList) as $row) {
            if (empty($row['Delete'])) {
                continue;
            }
            $timeStamp = $this->getRowTimeStamp($row);
            if ($timeStamp === null) {
                continue;
            }
            AC_DeleteVariableData($archiveID, (int) $row['VariableID'], $timeStamp, $timeStamp);
            $deletedValues[] = $row;
            $affectedVariables[(int) $row['VariableID']] = true;
        }

        $deleted = count($deletedValues);
        if ($deleted == 0) {
            $this->showPopup($this->Translate('No anomalies selected.'));
            return;
        }

        foreach (array_keys($affectedVariables) as $variableID) {
            AC_ReAggregateVariable($archiveID, $variableID);
        }
        $this->arrayToCSV($deletedValues);

        if ($deleted == 1) {
            $this->showPopup($deleted . ' ' . $this->Translate('anomalie deleted.'));
        } else {
            $this->showPopup($deleted . ' ' . $this->Translate('anomalies have been deleted.'));
        }

        //Liste mit den gleichen Parametern wie bei der letzten Prüfung neu laden
        $lastCheck = json_decode($this->GetBuffer('LastCheck'), true);
        if (is_array($lastCheck)) {
            $resultListValues = $this->collectAnomalies((bool) $lastCheck['rawData'], (int) $lastCheck['startDate'], (int) $lastCheck['endDate'], (array) $lastCheck['variableIDs']);
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

        $startDate = $start - 86400;
        $endDate = $end + 86400;

        $this->SetBuffer('LastCheck', json_encode([
            'rawData'     => $rawData,
            'startDate'   => $startDate,
            'endDate'     => $endDate,
            'variableIDs' => $variableIDs
        ]));

        $resultListValues = $this->collectAnomalies($rawData, $startDate, $endDate, $variableIDs);
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

    protected function ProcessHookData()
    {
        $csv = $this->buildDeletionReport();
        if ($csv == '') {
            http_response_code(404);
            echo $this->Translate('No deletion report available.');
            return;
        }

        header('Content-Type: text/csv;charset=utf-8');
        header('Content-Length: ' . strlen($csv));
        header('Content-Disposition: attachment; filename="' . $this->Translate('Deletion report') . '.csv"');
        echo $csv;
    }

    private function buildDeletionReport()
    {
        $report = $this->ReadAttributeString('lastDeletedValues');
        if ($report == '') {
            return '';
        }
        $csv = $this->Translate('Date') . ';' . $this->Translate('VariableID') . ';' . $this->Translate('Value before anomalie') . ';' . $this->Translate('Value') . ';' . $this->Translate('Value after anomalie') . PHP_EOL;
        return $csv . $report;
    }

    private function collectAnomalies(bool $rawData, int $startDate, int $endDate, array $variableIDs)
    {
        $archiveID = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];
        $aggregationType = 1;

        //Schlüssel VariableID|TimeStamp verhindert Doppelte, ohne Treffer anderer Variablen zu verlieren
        $resultListValues = [];
        foreach ($variableIDs as $variableID) {
            if (!$rawData) {
                $values = AC_GetAggregatedValues($archiveID, $variableID, $aggregationType, $startDate, $endDate, 0);

                $filteredValues = $this->filter_variable($values, $rawData, $variableID);

                foreach ($filteredValues as $Value) {
                    $valueEndDate = $Value['TimeStamp'];

                    $rawValues = AC_GetLoggedValues($archiveID, $variableID, $valueEndDate, $endDate, 0);
                    $filteredRawValues = $this->filter_variable($rawValues, true, $variableID);
                    foreach ($filteredRawValues as $rawValue) {
                        $resultListValues[$variableID . '|' . $rawValue['TimeStamp']] = $rawValue;
                    }
                }
            } else {
                $values = AC_GetLoggedValues($archiveID, $variableID, $startDate, $endDate, 0);
                $filteredRawValues = $this->filter_variable($values, true, $variableID);
                foreach ($filteredRawValues as $rawValue) {
                    $resultListValues[$variableID . '|' . $rawValue['TimeStamp']] = $rawValue;
                }
            }
        }
        return array_values($resultListValues);
    }

    /**
     * Wandelt ein SelectDate-Wert (JSON-String, Array oder Objekt) in einen Timestamp.
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

    private function filter_variable($logData, $rawData, $variableID)
    {
        $keyValue = 'Avg';
        if ($rawData) {
            $keyValue = 'Value';
        }
        $failedValues = [];
        // Anzahl der Werte
        $entries = count($logData);
        // Macht erst ab 3 Werten Sinn
        if ($entries < 2) {
            return $failedValues;
        }
        // Anzahl der Fehler protokolieren
        $changes = 0;
        for ($i = 2; $i < $entries; $i++) {
            // Differenz Wert2-Wert1
            $diff1 = $logData[$i - 1][$keyValue] - $logData[$i - 2][$keyValue];
            // Differenz Wert3-Wert2
            $diff2 = $logData[$i][$keyValue] - $logData[$i - 1][$keyValue];
            // Wenn der mittlere Wert entweder der größte oder kleinste Wert ist stimmt was nicht
            if ((($diff1 < -0.1) && ($diff2 > 0.1)) ||
                            (($diff1 > 0.1) && ($diff2 < -0.1))) {
                // lösche mittleren Wert
                $failedValues[] = [
                    'Date'        => date('d.m.Y H:i:s', $logData[$i - 1]['TimeStamp']),
                    'TimeStamp'   => $logData[$i - 1]['TimeStamp'],
                    'VariableID'  => $variableID,
                    'ValueBefore' => $logData[$i][$keyValue],
                    'Value'       => $logData[$i - 1][$keyValue],
                    'ValueAfter'  => $logData[$i - 2][$keyValue]
                ];
                $changes++;
            }
        }
        return $failedValues;
    }
}
