<?php

require_once INCLUDE_DIR.'class.report.php';

class ReportAuthorizedEntitiesTest extends Test {
    var $name = 'Dashboard authorized agent and team rows';

    function testAgentWithoutEventsRemainsVisible() {
        $rows = array(
            10 => array(
                'staff_id' => 10,
                'Assigned' => 0,
                'Closed' => 0,
                'Open' => 2,
            ),
        );

        $result = OverviewReport::mergeAuthorizedActivity(
            $rows,
            array(),
            'staff'
        );

        $this->assert(isset($result[10]),
            'An authorized agent must remain visible without events');
        $this->assertEqual($result[10]['Assigned'], 0);
        $this->assertEqual($result[10]['Closed'], 0);
        $this->assertEqual($result[10]['Open'], 2);
    }

    function testTeamWithoutEventsRemainsVisible() {
        $rows = array(
            20 => array(
                'team_id' => 20,
                'Assigned' => 0,
                'Closed' => 0,
                'Open' => 3,
            ),
        );

        $result = OverviewReport::mergeAuthorizedActivity(
            $rows,
            array(),
            'team'
        );

        $this->assert(isset($result[20]),
            'An authorized team must remain visible without events');
        $this->assertEqual($result[20]['Assigned'], 0);
        $this->assertEqual($result[20]['Closed'], 0);
        $this->assertEqual($result[20]['Open'], 3);
    }

    function testActivityIsAddedOnlyToAuthorizedRows() {
        $rows = array(
            20 => array(
                'team_id' => 20,
                'Assigned' => 0,
                'Closed' => 0,
                'Open' => 1,
            ),
        );
        $stats = array(
            array('team' => 20, 'Assigned' => 2, 'Closed' => 1),
            array('team' => 99, 'Assigned' => 50, 'Closed' => 50),
        );

        $result = OverviewReport::mergeAuthorizedActivity(
            $rows,
            $stats,
            'team'
        );

        $this->assertEqual(count($result), 1,
            'Events must not add entities outside the authorized catalog');
        $this->assert(!isset($result[99]),
            'An unauthorized team must not be exposed');
        $this->assertEqual($result[20]['Assigned'], 2);
        $this->assertEqual($result[20]['Closed'], 1);
        $this->assertEqual($result[20]['Open'], 1);
    }

    function testUnauthorizedAgentActivityIsIgnored() {
        $rows = array(
            10 => array(
                'staff_id' => 10,
                'Assigned' => 0,
                'Closed' => 0,
                'Open' => 0,
            ),
        );
        $stats = array(
            array('staff_id' => 10, 'Assigned' => 1, 'Closed' => 0),
            array('staff_id' => 77, 'Assigned' => 20, 'Closed' => 20),
        );

        $result = OverviewReport::mergeAuthorizedActivity(
            $rows,
            $stats,
            'staff'
        );

        $this->assertEqual(count($result), 1,
            'Events must not add agents outside the authorized catalog');
        $this->assert(!isset($result[77]),
            'An unauthorized agent must not be exposed');
        $this->assertEqual($result[10]['Assigned'], 1);
        $this->assertEqual($result[10]['Closed'], 0);
    }
}

return 'ReportAuthorizedEntitiesTest';
?>
