<?php

class TicketFixedListTest extends Test {
    var $name = 'Fixed expediente listing without custom queues';

    function testTicketControllerDoesNotResolveCustomQueues() {
        $source = file_get_contents(get_osticket_root_path().'/scp/tickets.php');
        $listing = file_get_contents(get_osticket_root_path()
            .'/include/staff/templates/queue-tickets.tmpl.php');

        $this->assert(strpos($source,
            'CustomQueue::getHierarchicalQueues') === false);
        $this->assert(strpos($source, 'SavedQueue::lookup') === false);
        $this->assert(strpos($listing, '$queue->') === false);
        $this->assert(strpos($source,
            'function iris_apply_ticket_queue_filters(') !== false);
        $this->assert(strpos($source,
            "\$queue_key = '::IRIS:Q:T'") !== false);
    }

    function testListingUsesFixedFiltersAndVisibility() {
        $source = file_get_contents(get_osticket_root_path()
            .'/include/staff/templates/queue-tickets.tmpl.php');

        $this->assert(strpos($source, '$tickets = Ticket::objects()') !== false);
        $this->assert(strpos($source, "name=\"text_search\"") !== false);
        $this->assert(strpos($source, "'origin_dept' => array(") !== false);
        $this->assert(strpos($source,
            '$thisstaff->getTicketsVisibility()') !== false);
        $this->assert(strpos($source,
            "\$_SESSION[':Q:tickets']") !== false);
    }

    function testCreatorDepartmentUsesIndependentThreadAlias() {
        $model = file_get_contents(get_osticket_root_path()
            .'/include/class.ticket.php');
        $controller = file_get_contents(get_osticket_root_path()
            .'/scp/tickets.php');

        $this->assert(strpos($model, "'creator_thread' => array(") !== false);
        $this->assert(strpos($controller,
            "'creator_thread__events__event__name' => 'created'") !== false);
        $this->assert(strpos($controller,
            "'creator_thread__events__agent__dept__name'") !== false);
    }
}

return 'TicketFixedListTest';
?>
