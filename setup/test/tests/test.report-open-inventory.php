<?php

class ReportOpenInventoryTest extends Test {
    var $name = 'Dashboard open-task inventory regression tests';

    private function getReportSource() {
        return file_get_contents(
            get_osticket_root_path().'/include/class.report.php'
        );
    }

    private function getOpenTasksQuerySource() {
        $source = $this->getReportSource();
        $matches = array();
        if (!preg_match(
            '/\$openTasks\s*=\s*TaskModel::objects\(\)(.*?)\$openTasksCount\s*=/s',
            $source,
            $matches
        )) {
            return false;
        }

        return $matches[1];
    }

    function testOldOpenTaskIsNotFilteredByUpdatedDate() {
        $query = $this->getOpenTasksQuerySource();

        $this->assert($query !== false,
            'Unable to locate the open-task inventory query');
        $this->assert(strpos($query, "'updated__range'") === false,
            'An old open task must not be excluded by its updated date');
        $this->assert(
            strpos($query, "'flags__hasbit' => TaskModel::ISOPEN") !== false,
            'The inventory must include every task with the ISOPEN bit set'
        );
    }

    function testOpenInventorySupportsEveryDashboardGroup() {
        $query = $this->getOpenTasksQuerySource();

        foreach (array('dept_id', 'team_id', 'staff_id') as $field) {
            $this->assert($query !== false && strpos($query, "'$field'") !== false,
                "Open-task inventory must support grouping by $field");
        }
    }

    function testEventMetricsRemainPeriodBound() {
        $source = $this->getReportSource();
        $start = strpos($source, '$base_stats = ThreadEvent::objects()');
        $stop = strpos($source, '$times = ThreadEvent::objects()', $start);
        $eventQuery = ($start !== false && $stop !== false)
            ? substr($source, $start, $stop - $start)
            : false;

        $this->assert($eventQuery !== false,
            'Unable to locate the historical event query');
        $this->assert(
            $eventQuery !== false
                && strpos($eventQuery, "'timestamp__range'") !== false,
            'Historical event metrics must remain restricted to the selected period'
        );
    }
}

return 'ReportOpenInventoryTest';
?>
