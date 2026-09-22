<?php

require_once INCLUDE_DIR.'class.task.php';

class TaskDueDateSearchTest extends Test {
    var $name = 'Task due-date search boundaries';

    function testUtcDateRangeIncludesTheCompleteEndDate() {
        $range = TaskModel::getDueDateSearchRange(
            '2026-09-10',
            '2026-09-12',
            'UTC',
            'UTC'
        );

        $this->assertEqual($range['start'], '2026-09-10 00:00:00');
        $this->assertEqual($range['end'], '2026-09-13 00:00:00');
    }

    function testUserTimezoneIsConvertedToDatabaseTimezone() {
        $range = TaskModel::getDueDateSearchRange(
            '2026-09-10',
            '2026-09-10',
            'America/Bogota',
            'UTC'
        );

        $this->assertEqual($range['start'], '2026-09-10 05:00:00');
        $this->assertEqual($range['end'], '2026-09-11 05:00:00');
    }

    function testDaylightSavingUsesCalendarDayBoundaries() {
        $range = TaskModel::getDueDateSearchRange(
            '2026-03-08',
            '2026-03-08',
            'America/New_York',
            'UTC'
        );

        $this->assertEqual($range['start'], '2026-03-08 05:00:00');
        $this->assertEqual($range['end'], '2026-03-09 04:00:00');
    }

    function testDatabaseTimezoneReceivesLocalWallClock() {
        $range = TaskModel::getDueDateSearchRange(
            '2026-09-10',
            '2026-09-10',
            'America/Bogota',
            'America/Bogota'
        );

        $this->assertEqual($range['start'], '2026-09-10 00:00:00');
        $this->assertEqual($range['end'], '2026-09-11 00:00:00');
    }

    function testEmptyAndInvalidDatesDoNotCreateBoundaries() {
        $range = TaskModel::getDueDateSearchRange(
            '',
            'not-a-date',
            'UTC',
            'UTC'
        );

        $this->assertEqual($range['start'], null);
        $this->assertEqual($range['end'], null);
    }

    function testEitherBoundaryCanBeUsedIndependently() {
        $from = TaskModel::getDueDateSearchRange(
            '2026-09-10', '', 'UTC', 'UTC'
        );
        $until = TaskModel::getDueDateSearchRange(
            '', '2026-09-12', 'UTC', 'UTC'
        );

        $this->assertEqual($from['start'], '2026-09-10 00:00:00');
        $this->assertEqual($from['end'], null);
        $this->assertEqual($until['start'], null);
        $this->assertEqual($until['end'], '2026-09-13 00:00:00');
    }

    function testTaskSearchFormAppliesBothDueDateBoundaries() {
        $source = file_get_contents(
            get_osticket_root_path().'/include/staff/tasks.inc.php'
        );

        $this->assert(strpos($source, 'name="due_start"') !== false);
        $this->assert(strpos($source, 'name="due_end"') !== false);
        $this->assert(strpos($source, "'duedate__gte'") !== false);
        $this->assert(strpos($source, "'duedate__lt'") !== false);
    }
}

return 'TaskDueDateSearchTest';
?>
