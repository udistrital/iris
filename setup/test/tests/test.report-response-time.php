<?php

require_once INCLUDE_DIR.'class.report.php';

class ReportResponseTimeTest extends Test {
    var $name = 'Dashboard task response-time calculations';

    private function calculate($created, $assigned, $closed=array(),
            $reopened=array(), $start=1000, $stop=5000,
            $authorized=array(20, 30)) {
        return OverviewReport::calculateResponseTimeIntervals(
            $created,
            $assigned,
            $closed,
            $reopened,
            $start,
            $stop,
            $authorized
        );
    }

    function testFirstAssignmentBelongsToItsRecipient() {
        $result = $this->calculate(
            array(array('id' => 1, 'thread_id' => 100, 'timestamp' => 100)),
            array(array(
                'id' => 2,
                'thread_id' => 100,
                'staff_id' => 20,
                'timestamp' => 1100,
            ))
        );

        $this->assertEqual($result['created_to_assigned'][20], array(1000));
        $this->assert(!isset($result['created_to_assigned'][10]),
            'The task creator must not receive the assignment interval');
    }

    function testFirstAndLastAssignmentsAreUsedForTheirOwnMetrics() {
        $result = $this->calculate(
            array(array('id' => 10, 'thread_id' => 101, 'timestamp' => 100)),
            array(
                array('id' => 12, 'thread_id' => 101, 'staff_id' => 30,
                    'timestamp' => 2000),
                array('id' => 11, 'thread_id' => 101, 'staff_id' => 20,
                    'timestamp' => 1100),
            ),
            array(array('id' => 13, 'thread_id' => 101, 'staff_id' => 30,
                'timestamp' => 3000))
        );

        $this->assertEqual($result['created_to_assigned'][20], array(1000));
        $this->assertEqual($result['assigned_to_closed'][30], array(1000));
        $this->assert(!isset($result['assigned_to_closed'][20]),
            'A reassigned task must be credited to the closing assignee');
    }

    function testStartingEventMayPredateSelectedPeriod() {
        $result = $this->calculate(
            array(array('id' => 20, 'thread_id' => 102, 'timestamp' => 100)),
            array(array('id' => 21, 'thread_id' => 102, 'staff_id' => 20,
                'timestamp' => 500)),
            array(array('id' => 22, 'thread_id' => 102, 'staff_id' => 20,
                'timestamp' => 1500))
        );

        $this->assert(!isset($result['created_to_assigned'][20]),
            'An assignment completed before the period must not be counted');
        $this->assertEqual($result['assigned_to_closed'][20], array(1000),
            'A closure in the period must use its earlier assignment');
    }

    function testReopenStartsANewCloseCycle() {
        $result = $this->calculate(
            array(
                array('id' => 30, 'thread_id' => 103, 'timestamp' => 100),
                array('id' => 40, 'thread_id' => 104, 'timestamp' => 100),
            ),
            array(
                array('id' => 31, 'thread_id' => 103, 'staff_id' => 20,
                    'timestamp' => 1100),
                array('id' => 41, 'thread_id' => 104, 'staff_id' => 30,
                    'timestamp' => 1100),
                array('id' => 43, 'thread_id' => 104, 'staff_id' => 30,
                    'timestamp' => 2500),
            ),
            array(
                array('id' => 33, 'thread_id' => 103, 'staff_id' => 20,
                    'timestamp' => 3000),
                array('id' => 44, 'thread_id' => 104, 'staff_id' => 30,
                    'timestamp' => 3500),
            ),
            array(
                array('id' => 32, 'thread_id' => 103, 'timestamp' => 2000),
                array('id' => 42, 'thread_id' => 104, 'timestamp' => 2000),
            )
        );

        $this->assert(!isset($result['assigned_to_closed'][20]),
            'An assignment before reopening must not be paired with the new close');
        $this->assertEqual($result['assigned_to_closed'][30], array(1000));
    }

    function testTeamAssignmentsAndInvalidChronologyAreIgnored() {
        $result = $this->calculate(
            array(
                array('id' => 50, 'thread_id' => 105, 'timestamp' => 2000),
                array('id' => 60, 'thread_id' => 106, 'timestamp' => 100),
            ),
            array(
                array('id' => 51, 'thread_id' => 105, 'staff_id' => 20,
                    'timestamp' => 1500),
                array('id' => 61, 'thread_id' => 106, 'staff_id' => 20,
                    'data' => '{"team":4}', 'timestamp' => 1100),
                array('id' => 62, 'thread_id' => 106, 'staff_id' => 30,
                    'data' => '{"staff":[30,"Agent"]}', 'timestamp' => 1200),
            )
        );

        $this->assert(!isset($result['created_to_assigned'][20]));
        $this->assertEqual($result['created_to_assigned'][30], array(1100));
    }

    function testUnauthorizedAgentsAndDuplicateEventsAreIgnored() {
        $result = $this->calculate(
            array(array('id' => 70, 'thread_id' => 107, 'timestamp' => 100)),
            array(
                array('id' => 71, 'thread_id' => 107, 'staff_id' => 99,
                    'timestamp' => 1100),
                array('id' => 71, 'thread_id' => 107, 'staff_id' => 99,
                    'timestamp' => 1100),
            ),
            array(),
            array(),
            1000,
            5000,
            array(20, 30)
        );

        $this->assertEqual($result['created_to_assigned'], array());
        $this->assertEqual(OverviewReport::averageResponseHours(array(3600, 7200)), 1.5);
        $this->assertEqual(OverviewReport::averageResponseHours(array()), null);
    }
}

return 'ReportResponseTimeTest';
?>
