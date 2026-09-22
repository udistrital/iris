<?php

require_once INCLUDE_DIR.'class.report.php';

class ReportCreatedByAgentTest extends Test {
    var $name = 'Dashboard tasks created by agent';

    function testCreationIsAttributedToTheEventActor() {
        $events = array(
            array(
                'id' => 101,
                'uid' => 10,
                'uid_type' => 'S',
                'staff_id' => 20,
            ),
        );

        $counts = OverviewReport::countCreatedEventsByAgent($events);

        $this->assertEqual($counts[10], 1,
            'The creation must be attributed to the staff actor');
        $this->assert(!isset($counts[20]),
            'The assignee must not receive credit for creating the task');
    }

    function testUnassignedTaskStillCreditsItsCreator() {
        $events = array(
            array(
                'id' => 104,
                'uid' => 10,
                'uid_type' => 'S',
                'staff_id' => 0,
            ),
        );

        $created = OverviewReport::countCreatedEventsByAgent($events);
        $rows = array(
            10 => array(
                'staff_id' => 10,
                'Created' => $created[10] ?? 0,
                'Assigned' => 0,
                'Closed' => 0,
                'Open' => 0,
            ),
        );
        $result = OverviewReport::mergeAuthorizedActivity(
            $rows,
            array(),
            'staff'
        );

        $this->assert(isset($result[10]),
            'The authorized creator must remain in the agent report');
        $this->assertEqual($result[10]['Created'], 1);
        $this->assertEqual($result[10]['Assigned'], 0);
        $this->assertEqual($result[10]['Closed'], 0);
    }

    function testOnlyStaffActorsAreCounted() {
        $events = array(
            array('id' => 101, 'uid' => 10, 'uid_type' => 'S'),
            array('id' => 102, 'uid' => 10, 'uid_type' => 'U'),
            array('id' => 103, 'uid' => 0, 'uid_type' => 'S'),
        );

        $counts = OverviewReport::countCreatedEventsByAgent($events);

        $this->assertEqual(count($counts), 1);
        $this->assertEqual($counts[10], 1);
    }

    function testEachCreationEventIsCountedOnce() {
        $events = array(
            array('id' => 101, 'uid' => 10, 'uid_type' => 'S'),
            array('id' => 101, 'uid' => 10, 'uid_type' => 'S'),
            array('id' => 102, 'uid' => 10, 'uid_type' => 'S'),
            array('id' => 103, 'uid' => 11, 'uid_type' => 'S'),
        );

        $counts = OverviewReport::countCreatedEventsByAgent($events);

        $this->assertEqual($counts[10], 2,
            'Duplicate rows for one event must not increase the count');
        $this->assertEqual($counts[11], 1);
        $this->assertEqual(array_sum($counts), 3,
            'The total must equal the number of unique valid creation events');
    }
}

return 'ReportCreatedByAgentTest';
?>
