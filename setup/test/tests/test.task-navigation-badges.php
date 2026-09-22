<?php

class TaskNavigationBadgesTest extends Test {
    var $name = 'Task navigation open-task badges';

    private function tasksSource() {
        return file_get_contents(get_osticket_root_path().'/scp/tasks.php');
    }

    function testBadgeQueuesUseTheSharedQueueDefinition() {
        $source = $this->tasksSource();

        $this->assert(strpos($source,
            'function iris_apply_task_queue_filters(') !== false);
        $this->assert(strpos($source,
            'iris_apply_task_queue_filters($tasks, $queue_name, $thisstaff, true)') !== false);

        $listing = file_get_contents(
            get_osticket_root_path().'/include/staff/tasks.inc.php'
        );
        $this->assert(strpos($listing,
            'iris_apply_task_queue_filters($tasks, $queue_name, $thisstaff)') !== false);
        $this->assert(strpos($listing,
            'iris_apply_task_visibility($tasks, $thisstaff)') !== false);
    }

    function testBadgeCountsAreOpenAndDistinct() {
        $source = $this->tasksSource();
        $countFunction = substr($source,
            strpos($source, 'function iris_task_queue_count('),
            strpos($source, '// Clear advanced search')
                - strpos($source, 'function iris_task_queue_count(')
        );

        $this->assert(strpos($countFunction,
            "'flags__hasbit' => TaskModel::ISOPEN") !== false);
        $this->assert(strpos($countFunction,
            '$tasks->distinct(\'id\')') !== false);
        $this->assert(strpos($countFunction,
            'array_key_exists($cacheKey, $counts)') !== false);
    }

    function testDepartmentFiltersDoNotNestIdLists() {
        $source = $this->tasksSource();

        $this->assert(strpos($source,
            "getAdminDepartments()->values_flat('id')") !== false);
        $this->assert(strpos($source,
            "'dept_id__in' => [\$adminDeptIds]") === false);
        $this->assert(strpos($source,
            "'dept_id__in' => array(\$adminDeptIds)") === false);
        $this->assert(strpos($source,
            "'dept_id__in' => \$adminDeptIds") !== false);
    }

    function testAllRequestedOpenQueuesReceiveBadges() {
        $source = $this->tasksSource();
        $queues = array(
            'assigned', 'open_me', 'involved', 'thread_me', 'cc',
            'assigned_mteams', 'dept', 'assigned_dept', 'created_dep',
            'requested_dep', 'transferred', 'unassigned_dept',
            'unassigned', 'overdue',
        );

        foreach ($queues as $queue)
            $this->assert(strpos($source,
                "iris_task_queue_count('$queue', \$thisstaff)") !== false,
                "Missing badge count for $queue");

        $this->assert(strpos($source,
            "iris_task_queue_count('closed', \$thisstaff)") === false);
        $this->assert(strpos($source,
            "iris_task_queue_count('closed_mteams', \$thisstaff)") === false);
    }

    function testRendererHidesZeroAndCapsLargeCounts() {
        $template = file_get_contents(
            get_osticket_root_path().'/include/staff/templates/sub-navigation.tmpl.php'
        );

        $this->assert(strpos($template, '$badgeCount > 0') !== false);
        $this->assert(strpos($template,
            "\$badgeCount > 99 ? '99+'") !== false);
        $this->assert(strpos($template,
            "\$item['badge_class'] === 'critical'") !== false);
        $this->assert(strpos($template, 'aria-label=') !== false);
        $this->assert(strpos($template,
            'Format::htmlchars($badgeLabel)') !== false);
    }
}

return 'TaskNavigationBadgesTest';
?>
