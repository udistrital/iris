<?php

require_once INCLUDE_DIR.'class.report.php';

class ReportDateRangeTest extends Test {
    var $name = 'Dashboard report date ranges';

    private function timestamp($date) {
        return strtotime($date.' UTC');
    }

    function testUpToTodayEndsAtCurrentInstant() {
        $start = $this->timestamp('2026-01-15 00:00:00');
        $now = $this->timestamp('2026-02-20 14:35:00');

        $end = OverviewReport::calculateEndTimestamp($start, 'now', $now);

        $this->assertEqual($end, $now,
            'Up to today must not add 24 hours to the current instant');
    }

    function testOneWeekEndsSevenDaysAfterStart() {
        $start = $this->timestamp('2026-01-15 00:00:00');
        $end = OverviewReport::calculateEndTimestamp($start, '+7 days', 0);

        $this->assertEqual($end, $this->timestamp('2026-01-22 00:00:00'));
    }

    function testTwoWeeksEndFourteenDaysAfterStart() {
        $start = $this->timestamp('2026-01-15 00:00:00');
        $end = OverviewReport::calculateEndTimestamp($start, '+14 days', 0);

        $this->assertEqual($end, $this->timestamp('2026-01-29 00:00:00'));
    }

    function testOneMonthIsCalculatedFromStartDate() {
        $start = $this->timestamp('2026-01-15 00:00:00');
        $end = OverviewReport::calculateEndTimestamp($start, '+1 month', 0);

        $this->assertEqual($end, $this->timestamp('2026-02-15 00:00:00'));
    }

    function testOneQuarterIsCalculatedFromStartDate() {
        $start = $this->timestamp('2026-01-15 00:00:00');
        $end = OverviewReport::calculateEndTimestamp($start, '+3 months', 0);

        $this->assertEqual($end, $this->timestamp('2026-04-15 00:00:00'));
    }

    function testQueryAndDisplayUseTheSameBoundaries() {
        $range = array(
            $this->timestamp('2026-01-15 00:00:00'),
            $this->timestamp('2026-01-22 00:00:00'),
        );

        $query = OverviewReport::formatDateRangeForQuery($range);
        $display = OverviewReport::formatDateRangeForDisplay(
            $range,
            'UTC',
            'Y-m-d H:i:s'
        );

        $this->assertEqual(
            $query,
            array(
                'FROM_UNIXTIME('.$range[0].')',
                'FROM_UNIXTIME('.$range[1].')',
            )
        );
        $this->assertEqual(
            $display,
            array('2026-01-15 00:00:00', '2026-01-22 00:00:00')
        );
    }

    function testActivePeriodCanBeReadByTheForm() {
        $reflection = new ReflectionClass('OverviewReport');
        $report = $reflection->newInstanceWithoutConstructor();
        $report->end = '+14 days';

        $this->assertEqual($report->getPeriod(), '+14 days');
    }
}

return 'ReportDateRangeTest';
?>
