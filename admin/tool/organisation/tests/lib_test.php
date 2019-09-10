<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * File containing tests for functions in lib.php
 *
 * @package     tool_organisation
 * @category    test
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for functions in lib.php
 *
 * @package    tool_organisation
 * @copyright  2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_organisation_lib_testcase extends advanced_testcase {

    /**
     * Test for callback 'wp_registration_stats'
     */
    public function test_registration_get_site_info() {
        global $CFG;
        $this->resetAfterTest(true);
        $origsiteinfo = $siteinfo = ['moodlerelease' => $CFG->release, 'url' => $CFG->wwwroot];
        component_class_callback('tool_wp\\registration', 'site_info', [&$siteinfo, false]);
        $appended = array_diff_key($siteinfo, $origsiteinfo);

        $this->assertNotNull($appended['wpdepartmentframeworks']);
        $this->assertNotNull($appended['wpdepartments']);
        $this->assertNotNull($appended['wppositionframeworks']);
        $this->assertNotNull($appended['wppositions']);
        $this->assertNotNull($appended['wpjobs']);

        $siteinfo = $origsiteinfo;
        component_class_callback('tool_wp\\registration', 'site_info', [&$siteinfo, true]);
    }

    /**
     * Tenant generator
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Get organisation generator
     *
     * @return tool_organisation_generator
     */
    protected function get_org_generator(): tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }

    /**
     * Creates a team with one manager and $numberofusers users
     *
     * @param int $tenantid
     * @param int $numberofusers
     * @param int|null $permission
     * @param string $usernameprefix
     * @return array
     */
    protected function create_team(int $tenantid, int $numberofusers = 2, int $permission = null, string $usernameprefix = 'user') {
        if ($permission === null) {
            $permission = \tool_organisation\organisation::PERM_VIEW_REPORTS |
                \tool_organisation\organisation::PERM_RECEIVE_NOTIFICATIONS |
                \tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS;
        }

        $generator = $this->get_org_generator();
        $depframework = $generator->create_department(['tenantid' => $tenantid]);
        $dep = $generator->create_department(['parentid' => $depframework->id]);
        $posframework = $generator->create_position(['tenantid' => $tenantid]);
        $pos1 = $generator->create_position(['parentid' => $posframework->id, 'globalmanager' => 1,
            'globalpermissions' => $permission]);
        $pos2 = $generator->create_position(['parentid' => $pos1->id]);

        $users = [];
        for ($i = 0; $i <= $numberofusers; $i++) {
            $user = $this->getDataGenerator()->create_user(['username' => $usernameprefix . $i]);
            $this->get_tenant_generator()->allocate_user($user->id, $tenantid);
            $position = $i ? $pos2->id : $pos1->id;
            $generator->assign_job(['userid' => $user->id, 'departmentid' => $dep->id, 'positionid' => $position]);
            $users[] = $user;
        }
        return $users;
    }

    /**
     * Test callback tool_organisation_control_view_profile()
     */
    public function test_can_view_profile() {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        $this->resetAfterTest();
        $tenant = $this->get_tenant_generator()->create_tenant();
        $users = $this->create_team($tenant->id, 5);

        $otheruser1 = $this->getDataGenerator()->create_user();
        $otheruser2 = $this->getDataGenerator()->create_user();
        $this->get_tenant_generator()->allocate_user($otheruser2->id, $tenant->id);

        // Users can not see each others profiles.
        $this->setUser($users[1]);
        $this->assertFalse(user_can_view_profile($users[2]));

        // User who has a manager position can see users in his team.
        $this->setUser($users[0]);
        $this->assertTrue(user_can_view_profile($users[2]));
        $this->assertFalse(user_can_view_profile($otheruser2));
        $this->assertFalse(user_can_view_profile($otheruser1));
    }
}
