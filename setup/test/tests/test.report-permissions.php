<?php

require_once INCLUDE_DIR.'class.report.php';

class ReportPermissionRoleStub {
    private $canViewStats;

    function __construct($canViewStats) {
        $this->canViewStats = $canViewStats;
    }

    function hasPerm($permission) {
        return $permission === ReportModel::PERM_AGENTS
            && $this->canViewStats;
    }
}

class ReportPermissionStaffStub {
    private $depts;
    private $managed;
    private $roles;
    private $admin;

    function __construct($depts, $managed=array(), $roles=array(),
            $admin=false) {
        $this->depts = $depts;
        $this->managed = $managed;
        $this->roles = $roles;
        $this->admin = $admin;
    }

    function getDepts() {
        return $this->depts;
    }

    function getManagedDepartments() {
        return $this->managed;
    }

    function getRole($deptId) {
        return $this->roles[$deptId] ?? null;
    }

    function isAdmin() {
        return $this->admin;
    }
}

class ReportPermissionsTest extends Test {
    var $name = 'Dashboard role and department permissions';

    function testSystemAdministratorReceivesAccessibleDepartments() {
        $staff = new ReportPermissionStaffStub(array(10, 20), array(), array(), true);

        $this->assertEqual(
            OverviewReport::getAuthorizedDepartmentIds($staff),
            array(10, 20)
        );
    }

    function testDepartmentHeadReceivesManagedDepartmentOnly() {
        $staff = new ReportPermissionStaffStub(
            array(10, 20),
            array(20)
        );

        $this->assertEqual(
            OverviewReport::getAuthorizedDepartmentIds($staff),
            array(20)
        );
    }

    function testStatsPermissionAppliesPerDepartmentRole() {
        $staff = new ReportPermissionStaffStub(
            array(10, 20),
            array(),
            array(
                10 => new ReportPermissionRoleStub(true),
                20 => new ReportPermissionRoleStub(false),
            )
        );

        $this->assertEqual(
            OverviewReport::getAuthorizedDepartmentIds($staff),
            array(10)
        );
    }

    function testOrdinaryAgentDoesNotReceiveDepartmentStatistics() {
        $staff = new ReportPermissionStaffStub(
            array(10),
            array(),
            array(10 => new ReportPermissionRoleStub(false))
        );

        $this->assertEqual(
            OverviewReport::getAuthorizedDepartmentIds($staff),
            array()
        );
    }

    function testReportDoesNotDependOnFixedRoleIds() {
        $source = file_get_contents(
            get_osticket_root_path().'/include/class.report.php'
        );

        $this->assert(strpos($source, "getRole()->getId() !== 1") === false);
        $this->assert(strpos($source,
            'in_array($role->getId(), [1, 2])') === false);
    }

    function testLockedAgentsAreExcludedFromAgentCatalogs() {
        $source = file_get_contents(
            get_osticket_root_path().'/include/class.report.php'
        );

        $this->assert(substr_count($source, "'isactive' => 1") >= 2,
            'Agent and response-time catalogs must only include active staff');
    }
}

return 'ReportPermissionsTest';
?>
