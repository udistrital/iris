<?php

require_once INCLUDE_DIR.'class.report.php';

class ReportPlotDataTest extends Test {
    var $name = 'Dashboard plot data contract';

    function testEmptyTableProducesValidEmptySeries() {
        $plot = OverviewReport::formatPlotData(array(
            'columns' => array('Dependencia', 'Created', 'Assigned'),
            'data' => array(),
        ));

        $this->assertEqual($plot['labels'], array());
        $this->assertEqual($plot['times'], array());
        $this->assertEqual($plot['events'], array('created', 'assigned'));
        $this->assertEqual($plot['plots']['created'], array());
        $this->assertEqual($plot['plots']['assigned'], array());
    }

    function testSingleRowKeepsZeroAndExcludesTotal() {
        $plot = OverviewReport::formatPlotData(array(
            'columns' => array('Agente', 'Created', 'Assigned', 'Closed', 'Abiertos'),
            'data' => array(
                array('Ada', 1, 0, 2, 0),
                array('TOTAL', 1, 0, 2, 0),
            ),
        ));

        $this->assertEqual($plot['labels'], array('Ada'));
        $this->assertEqual($plot['times'], array(0));
        $this->assertEqual(
            $plot['events'],
            array('created', 'assigned', 'closed', 'abiertos')
        );
        $this->assertEqual($plot['plots']['created'], array(1));
        $this->assertEqual($plot['plots']['assigned'], array(0));
        $this->assertEqual($plot['plots']['closed'], array(2));
        $this->assertEqual($plot['plots']['abiertos'], array(0));
    }

    function testMultipleTeamRowsAdaptToTheirColumns() {
        $plot = OverviewReport::formatPlotData(array(
            'columns' => array('Team', 'Assigned', 'Closed', 'Abiertos'),
            'data' => array(
                array('Equipo A', 3, 1, 4),
                array('Equipo B', 0, 2, 0),
                array('TOTAL', 3, 3, 4),
            ),
        ));

        $this->assertEqual($plot['labels'], array('Equipo A', 'Equipo B'));
        $this->assertEqual($plot['times'], array(0, 1));
        $this->assertEqual(
            $plot['events'],
            array('assigned', 'closed', 'abiertos')
        );
        $this->assertEqual($plot['plots']['assigned'], array(3, 0));
        $this->assertEqual($plot['plots']['closed'], array(1, 2));
        $this->assertEqual($plot['plots']['abiertos'], array(4, 0));
    }

    function testReportWithoutTotalRetainsItsLastRowAndDecimals() {
        $plot = OverviewReport::formatPlotData(array(
            'columns' => array('Agente', 'Promedio Horas'),
            'data' => array(
                array('Ada', 1.5),
                array('Linus', 0),
            ),
        ));

        $this->assertEqual($plot['labels'], array('Ada', 'Linus'));
        $this->assertEqual($plot['times'], array(0, 1));
        $this->assertEqual($plot['events'], array('promedio_horas'));
        $this->assertEqual($plot['plots']['promedio_horas'], array(1.5, 0));
    }
}

return 'ReportPlotDataTest';
?>
