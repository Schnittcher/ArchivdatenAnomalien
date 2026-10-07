<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ArchivdatenAnomalienTest extends TestCase
{
    private const ARCHIVE_GUID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
    private const MODULE_GUID = '{C495123A-0A9F-6162-24EF-E856475BB790}';

    //Beobachtungszeitraum der Testdaten: 01.10.2026 bis 03.10.2026
    private const DAY = 86400;

    private string $timezone;
    private int $archiveID;
    private int $instanceID;
    private int $variableID;
    private int $dayStart;

    protected function setUp(): void
    {
        //Die Zeitraumberechnung nutzt die lokale Zeitzone, deshalb für reproduzierbare Tests festlegen
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
        $this->dayStart = mktime(0, 0, 0, 10, 1, 2026);

        IPS\Kernel::reset();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/CoreStubs/library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');

        $this->archiveID = IPS_CreateInstance(self::ARCHIVE_GUID);
        $this->instanceID = IPS_CreateInstance(self::MODULE_GUID);
        $this->variableID = $this->createLoggedVariable();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }

    public function testValidConfigurationIsActiveAndRepeatable(): void
    {
        IPS_ApplyChanges($this->instanceID);
        $this->assertSame(102, IPS_GetInstance($this->instanceID)['InstanceStatus']);

        //Wiederholtes Übernehmen ändert den Zustand nicht
        IPS_ApplyChanges($this->instanceID);
        $this->assertSame(102, IPS_GetInstance($this->instanceID)['InstanceStatus']);
    }

    public function testInvalidConfigurationSetsErrorStatus(): void
    {
        IPS_SetProperty($this->instanceID, 'Threshold', -1.0);
        IPS_ApplyChanges($this->instanceID);
        $this->assertSame(201, IPS_GetInstance($this->instanceID)['InstanceStatus']);

        IPS_SetProperty($this->instanceID, 'Threshold', 0.1);
        IPS_SetProperty($this->instanceID, 'ThresholdType', 7);
        IPS_ApplyChanges($this->instanceID);
        $this->assertSame(202, IPS_GetInstance($this->instanceID)['InstanceStatus']);

        IPS_SetProperty($this->instanceID, 'ThresholdType', 0);
        IPS_SetProperty($this->instanceID, 'StartDate', json_encode(['year' => 2026, 'month' => 10, 'day' => 5]));
        IPS_SetProperty($this->instanceID, 'EndDate', json_encode(['year' => 2026, 'month' => 10, 'day' => 1]));
        IPS_ApplyChanges($this->instanceID);
        $this->assertSame(203, IPS_GetInstance($this->instanceID)['InstanceStatus']);
    }

    public function testRawModeFindsSingleValueSpikes(): void
    {
        $spikeUp = $this->dayStart + 40 * 1800;
        $spikeDown = $this->dayStart + 100 * 1800;
        $this->addSeries(144, 1800, [40 => 5.0, 100 => -5.0]);
        $this->configure(1, 8);

        $rows = AA_checkAnomalies($this->instanceID, true);

        $timeStamps = array_column($rows, 'TimeStamp');
        sort($timeStamps);
        $this->assertSame([$spikeUp, $spikeDown], $timeStamps);
        foreach ($rows as $row) {
            $this->assertSame($this->variableID, $row['VariableID']);
            //Der auffällige Wert weicht deutlich von seinen Nachbarn ab
            $this->assertGreaterThan(4.0, abs($row['Value'] - $row['ValueBefore']));
            $this->assertGreaterThan(4.0, abs($row['Value'] - $row['ValueAfter']));
        }
    }

    public function testThresholdDecidesWhichValuesAreAnomalies(): void
    {
        $this->addSeries(144, 1800, [40 => 5.0, 100 => 3.0]);

        $this->configure(1, 8, 4.0, 0);
        $this->assertCount(1, AA_checkAnomalies($this->instanceID, true), 'absolut 4: nur der Wert +5');

        $this->configure(1, 8, 6.0, 0);
        $this->assertCount(0, AA_checkAnomalies($this->instanceID, true), 'absolut 6: keine Anomalie');

        //Relativ: 1 % von etwa 100 liegt unter beiden Abweichungen, 4 % nur unter +5
        $this->configure(1, 8, 1.0, 1);
        $this->assertCount(2, AA_checkAnomalies($this->instanceID, true), 'relativ 1 %');

        $this->configure(1, 8, 4.0, 1);
        $this->assertCount(1, AA_checkAnomalies($this->instanceID, true), 'relativ 4 %');
    }

    public function testInvalidDateRangeReturnsNoResults(): void
    {
        $this->addSeries(144, 1800, [40 => 5.0]);

        $this->configure(1, 8);

        $this->setDates(['year' => 0, 'month' => 0, 'day' => 0], ['year' => 0, 'month' => 0, 'day' => 0]);
        $this->assertSame([], AA_checkAnomalies($this->instanceID, true), 'leerer Zeitraum');

        $this->setDates(['year' => 2026, 'month' => 10, 'day' => 3], ['year' => 2026, 'month' => 10, 'day' => 1]);
        $this->assertSame([], AA_checkAnomalies($this->instanceID, true), 'Start nach Ende');

        $this->setDates(['year' => 2026, 'month' => 2, 'day' => 31], ['year' => 2026, 'month' => 10, 'day' => 8]);
        $this->assertSame([], AA_checkAnomalies($this->instanceID, true), 'ungültiges Datum');
    }

    public function testDatesAreReadFromSavedConfiguration(): void
    {
        $this->addSeries(144, 1800, [40 => 5.0]);

        //Ein Zeitraum, der den Ausreißer nicht enthält
        $this->configure(20, 20);
        $this->assertCount(0, AA_checkAnomalies($this->instanceID, true));

        $this->configure(1, 1);
        $this->assertCount(1, AA_checkAnomalies($this->instanceID, true));
    }

    public function testDeleteRemovesOnlySelectedRowsAndWritesReport(): void
    {
        $firstSpike = $this->dayStart + 40 * 1800;
        $secondSpike = $this->dayStart + 100 * 1800;
        $this->addSeries(144, 1800, [40 => 5.0, 100 => -5.0]);
        $this->configure(1, 8);

        $rows = AA_checkAnomalies($this->instanceID, true);
        foreach ($rows as $index => $row) {
            $rows[$index]['Delete'] = ($row['TimeStamp'] === $firstSpike);
        }
        AA_deleteAnomalies($this->instanceID, $rows);

        $this->assertCount(0, AC_GetLoggedValues($this->archiveID, $this->variableID, $firstSpike, $firstSpike, 0));
        $this->assertCount(1, AC_GetLoggedValues($this->archiveID, $this->variableID, $secondSpike, $secondSpike, 0));

        $remaining = AA_checkAnomalies($this->instanceID, true);
        $this->assertSame([$secondSpike], array_column($remaining, 'TimeStamp'));

        $report = $this->decodeReport(AA_DownloadDeletionReport($this->instanceID));
        $this->assertStringContainsString(date('d.m.Y H:i:s', $firstSpike) . ';' . $this->variableID . ';', $report);
        $this->assertStringNotContainsString(date('d.m.Y H:i:s', $secondSpike), $report);
    }

    public function testDeleteWithoutSelectionChangesNothing(): void
    {
        $this->addSeries(144, 1800, [40 => 5.0]);
        $this->configure(1, 8);
        $before = count(AC_GetLoggedValues($this->archiveID, $this->variableID, 0, PHP_INT_MAX, 0));

        $rows = AA_checkAnomalies($this->instanceID, true);
        foreach ($rows as $index => $row) {
            $rows[$index]['Delete'] = false;
        }
        AA_deleteAnomalies($this->instanceID, $rows);
        AA_deleteAnomalies($this->instanceID, []);

        $this->assertSame($before, count(AC_GetLoggedValues($this->archiveID, $this->variableID, 0, PHP_INT_MAX, 0)));
        //Ohne Löschung gibt es keinen Bericht
        $this->assertStringNotContainsString('data:text/csv', AA_DownloadDeletionReport($this->instanceID));
    }

    public function testRemovingUnknownVariableKeepsCheckedList(): void
    {
        $this->addSeries(144, 1800, [40 => 5.0]);
        $this->configure(1, 8);

        AA_addCheckedVariables($this->instanceID, $this->variableID);
        AA_deleteCheckedVariables($this->instanceID, 99999);

        $this->assertCount(1, AA_checkAnomalies($this->instanceID, true));
    }

    public function testAggregatedModeOnlyChecksRawValuesAroundConspicuousDays(): void
    {
        //Tagesmittel: Tag 3 weicht nach oben ab, die übrigen Tage sind gleich
        $dailyAverages = [100.0, 100.0, 101.0, 100.0, 100.0];
        $aggregated = [];
        foreach ($dailyAverages as $index => $average) {
            $aggregated[] = [
                'Avg'       => $average,
                'Duration'  => self::DAY,
                'Max'       => $average,
                'MaxTime'   => 0,
                'Min'       => $average,
                'MinTime'   => 0,
                'TimeStamp' => $this->dayStart + $index * self::DAY
            ];
        }
        AC_StubsAddAggregatedValues($this->archiveID, $this->variableID, 1, $aggregated);

        //Rohwerte stündlich über fünf Tage mit Spitzen an Tag 2, 3 und 5 (Index 30, 54, 102)
        $this->addSeries(120, 3600, [30 => 5.0, 54 => 5.0, 102 => 5.0]);
        $this->configure(1, 5);

        $found = array_column(AA_checkAnomalies($this->instanceID, false), 'TimeStamp');
        sort($found);

        //Gesucht wird am auffälligen Tag 3 sowie am Vor- und Folgetag (Tag 2 bis 4); Tag 5 liegt außerhalb
        $this->assertSame([$this->dayStart + 30 * 3600, $this->dayStart + 54 * 3600], $found);
    }

    #[DataProvider('blockBoundaryProvider')]
    public function testSpikesOnBlockBoundaryAreFound(int $spikeIndex): void
    {
        //10010 Werte im Minutentakt: der erste Block hat 10000 Werte, der zweite 10
        $this->addSeries(10010, 60, [$spikeIndex => 5.0]);
        $this->configure(1, 8);

        $rows = AA_checkAnomalies($this->instanceID, true);

        $this->assertSame([$this->dayStart + $spikeIndex * 60], array_column($rows, 'TimeStamp'));
    }

    public static function blockBoundaryProvider(): array
    {
        return [
            'ältester Wert des ersten Blocks'  => [10],
            'neuester Wert des zweiten Blocks' => [9],
            'in der Mitte des zweiten Blocks'  => [5]
        ];
    }

    public function testResultsAreLimitedForVeryNoisyData(): void
    {
        //Jeder Wert weicht von beiden Nachbarn ab: 1500 Werte ergeben 1498 Spitzen (Index 1 bis 1498)
        $this->addAlternatingSeries(1500, 60);
        $this->configure(1, 8);

        $rows = AA_checkAnomalies($this->instanceID, true);

        //Höchstens 1000 Zeilen, und es sind die neuesten (Index 499 bis 1498)
        $this->assertCount(1000, $rows);
        $timeStamps = array_column($rows, 'TimeStamp');
        $this->assertSame($this->dayStart + 1498 * 60, max($timeStamps));
        $this->assertSame($this->dayStart + 499 * 60, min($timeStamps));
    }

    public function testExactlyAtTheLimitAllResultsAreReturned(): void
    {
        //1002 Werte ergeben genau 1000 Spitzen (Index 1 bis 1000)
        $this->addAlternatingSeries(1002, 60);
        $this->configure(1, 8);

        $timeStamps = array_column(AA_checkAnomalies($this->instanceID, true), 'TimeStamp');

        $this->assertCount(1000, $timeStamps);
        $this->assertSame($this->dayStart + 1000 * 60, max($timeStamps));
        $this->assertSame($this->dayStart + 60, min($timeStamps));
    }

    public function testConfigurationFormListsOnlyCounterVariables(): void
    {
        $standardVariable = IPS_CreateVariable(2);
        AC_SetLoggingStatus($this->archiveID, $standardVariable, true);

        //Der Archiv-Stub gibt bei dieser Abfrage einen Hinweis aus
        ob_start();
        $form = json_decode(IPS_GetConfigurationForm($this->instanceID), true);
        ob_end_clean();

        $this->assertIsArray($form);
        $list = $this->findElement($form['elements'], 'allVariables');
        $this->assertNotNull($list);
        $variableIDs = array_column($list['values'], 'VariableID');
        $this->assertContains($this->variableID, $variableIDs);
        $this->assertNotContains($standardVariable, $variableIDs);
    }

    public function testFormElementsMatchRegisteredProperties(): void
    {
        ob_start();
        $form = json_decode(IPS_GetConfigurationForm($this->instanceID), true);
        ob_end_clean();
        $configuration = json_decode(IPS_GetConfiguration($this->instanceID), true);

        foreach (['CheckedVariables', 'StartDate', 'EndDate', 'Threshold', 'ThresholdType'] as $property) {
            $this->assertArrayHasKey($property, $configuration, $property . ' ist nicht registriert');
            $this->assertNotNull($this->findElement($form['elements'], $property), $property . ' fehlt im Formular');
        }
    }

    private function createLoggedVariable(): int
    {
        $variableID = IPS_CreateVariable(2);
        AC_SetLoggingStatus($this->archiveID, $variableID, true);
        AC_SetAggregationType($this->archiveID, $variableID, 1);
        IPS_ApplyChanges($this->archiveID);
        return $variableID;
    }

    /**
     * Legt eine Reihe um 100 an. $spikes ordnet einem Index einen Aufschlag zu.
     */
    private function addSeries(int $count, int $interval, array $spikes): void
    {
        $values = [];
        for ($index = 0; $index < $count; $index++) {
            $values[] = [
                'TimeStamp' => $this->dayStart + $index * $interval,
                'Value'     => 100.0 + 0.01 * sin($index / 5) + ($spikes[$index] ?? 0.0)
            ];
        }
        AC_AddLoggedValues($this->archiveID, $this->variableID, $values);
    }

    /**
     * Legt eine Reihe an, in der jeder Wert gegenüber seinen Nachbarn eine Spitze ist (100 und 102 im Wechsel).
     */
    private function addAlternatingSeries(int $count, int $interval): void
    {
        $values = [];
        for ($index = 0; $index < $count; $index++) {
            $values[] = [
                'TimeStamp' => $this->dayStart + $index * $interval,
                'Value'     => ($index % 2 == 0) ? 100.0 : 102.0
            ];
        }
        AC_AddLoggedValues($this->archiveID, $this->variableID, $values);
    }

    private function configure(int $startDay, int $endDay, float $threshold = 0.1, int $thresholdType = 0): void
    {
        IPS_SetProperty($this->instanceID, 'CheckedVariables', json_encode([['VariableID' => $this->variableID, 'editable' => false]]));
        IPS_SetProperty($this->instanceID, 'StartDate', json_encode(['year' => 2026, 'month' => 10, 'day' => $startDay]));
        IPS_SetProperty($this->instanceID, 'EndDate', json_encode(['year' => 2026, 'month' => 10, 'day' => $endDay]));
        IPS_SetProperty($this->instanceID, 'Threshold', $threshold);
        IPS_SetProperty($this->instanceID, 'ThresholdType', $thresholdType);
        IPS_ApplyChanges($this->instanceID);
    }

    private function setDates(array $start, array $end): void
    {
        IPS_SetProperty($this->instanceID, 'StartDate', json_encode($start));
        IPS_SetProperty($this->instanceID, 'EndDate', json_encode($end));
        IPS_ApplyChanges($this->instanceID);
    }

    private function decodeReport(string $dataUrl): string
    {
        $prefix = 'data:text/csv;base64,';
        $this->assertStringStartsWith($prefix, $dataUrl);
        return base64_decode(substr($dataUrl, strlen($prefix)), true);
    }

    private function findElement(array $elements, string $name): ?array
    {
        foreach ($elements as $element) {
            if (($element['name'] ?? '') === $name) {
                return $element;
            }
            if (isset($element['items'])) {
                $found = $this->findElement($element['items'], $name);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }
}
