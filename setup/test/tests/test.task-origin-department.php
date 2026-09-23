<?php

class TaskOriginDepartmentTest extends Test {
    var $name = 'Task creator department in task queues';

    function testCreatorDepartmentIsAggregatedFromTheCreationActor() {
        $source = file_get_contents(get_osticket_root_path().'/scp/tasks.php');

        $start = strpos($source,
            'function iris_task_creator_department_expression()');
        $end = strpos($source,
            'function iris_apply_task_queue_filters(', $start);
        $expression = substr($source, $start, $end - $start);

        $this->assert(strpos($expression, 'SqlAggregate::MAX(') !== false);
        $this->assert(strpos($expression,
            "'thread__events__event__name' => 'created'") !== false);
        $this->assert(strpos($expression,
            "'thread__events__uid_type' => 'S'") !== false);
        $this->assert(strpos($expression,
            "'thread__events__agent__dept__name'") !== false);
    }

    function testDisplayAndSortingReuseTheSameExpression() {
        $source = file_get_contents(
            get_osticket_root_path().'/include/staff/tasks.inc.php'
        );

        $this->assert(substr_count($source,
            'iris_task_creator_department_expression()') === 2);
    }

    function testSearchUsesOnlyStaffCreationEvents() {
        $source = file_get_contents(
            get_osticket_root_path().'/include/staff/tasks.inc.php'
        );
        $filter = substr($source,
            strpos($source, "if (\$_REQUEST['origin_dept'])"),
            strpos($source, "if (\$_REQUEST['assignee'])")
                - strpos($source, "if (\$_REQUEST['origin_dept'])")
        );

        $this->assert(strpos($filter,
            "'thread__events__event__name' => 'created'") !== false);
        $this->assert(strpos($filter,
            "'thread__events__uid_type' => 'S'") !== false);
        $this->assert(strpos($filter, "new Q(array('id__in' => \$originTasks))") !== false);
    }
}

return 'TaskOriginDepartmentTest';
?>
