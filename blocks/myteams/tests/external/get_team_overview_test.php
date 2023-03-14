<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
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
//
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

namespace block_myteams\external;

use context_system;
use core_reportbuilder\local\filters\text;
use external_api;
use externallib_advanced_testcase;
use moodle_exception;
use tool_organisation\reportbuilder\local\filters\org_structure;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the block_myteams get_team_overview external class.
 *
 * @covers     \block_myteams\external\get_team_overview
 * @package    block_myteams
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_team_overview_test extends externallib_advanced_testcase {

    /**
     * Test execute
     */
    public function test_execute(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        $tenant = $tenantgenerator->create_tenant();

        $pos1 = $generator->create_position([
            'name' => 'Pos1',
            'tenantid' => $tenant->id,
            'globalmanager' => 1,
            'globalpermissions' => \tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS |
                \tool_organisation\organisation::PERM_VIEW_REPORTS,
        ]);
        $pos2 = $generator->create_position(['name' => 'Pos2', 'tenantid' => $tenant->id, 'parentid' => $pos1->id]);
        $pos3 = $generator->create_position(['name' => 'Pos3', 'tenantid' => $tenant->id, 'parentid' => $pos2->id]);
        $dep1 = $generator->create_department(['name' => 'Dep1', 'tenantid' => $tenant->id]);
        $dep1b = $generator->create_department(['name' => 'Dep1b', 'tenantid' => $tenant->id, 'parentid' => $dep1->id]);
        $dep2 = $generator->create_department(['name' => 'Dep2', 'tenantid' => $tenant->id]);

        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => '1']);
        $tenantgenerator->allocate_user($user1->id, $tenant->id);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Lionel', 'lastname' => 'Richie']);
        $tenantgenerator->allocate_user($user2->id, $tenant->id);
        $user3 = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => '3']);
        $tenantgenerator->allocate_user($user3->id, $tenant->id);
        $user4 = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => '4']);
        $tenantgenerator->allocate_user($user4->id, $tenant->id);
        $user5 = $this->getDataGenerator()->create_user(['firstname' => 'Amy', 'lastname' => 'Winehouse']);
        $tenantgenerator->allocate_user($user5->id, $tenant->id);

        $data = [
            'tenantid' => $tenant->id,
            'positionid' => $pos1->id,
            'departmentid' => $dep1->id,
            'userid' => $user1->id,
        ];
        $generator->assign_job((object) $data);

        $data = [
            'tenantid' => $tenant->id,
            'positionid' => $pos2->id,
            'departmentid' => $dep1->id,
            'userid' => $user2->id,
        ];
        $generator->assign_job((object) $data);

        $data = [
            'tenantid' => $tenant->id,
            'positionid' => $pos2->id,
            'departmentid' => $dep1->id,
            'userid' => $user3->id,
        ];
        $generator->assign_job((object) $data);

        $data = [
            'tenantid' => $tenant->id,
            'positionid' => $pos3->id,
            'departmentid' => $dep1->id,
            'userid' => $user4->id,
        ];
        $generator->assign_job((object) $data);

        $data = [
            'tenantid' => $tenant->id,
            'positionid' => $pos3->id,
            'departmentid' => $dep1b->id,
            'userid' => $user5->id,
        ];
        $generator->assign_job((object) $data);

        $generator->assign_capability('tool/organisation:assignjobs', $user1->id, context_system::instance());
        self::setUser($user1->id);

        $result = get_team_overview::execute();
        $cleanresult = external_api::clean_returnvalue(get_team_overview::execute_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertNotEmpty($cleanresult);
        $this->assertArrayHasKey('managedusers', $cleanresult);
        $this->assertArrayHasKey('totalcount', $cleanresult);

        $usersfullnames = array_map(static function($user) {
            return $user['user']['fullname'];
        }, $cleanresult['managedusers']);
        // Check default result order by user's fullname ASC.
        $this->assertEquals([fullname($user5), fullname($user2), fullname($user3), fullname($user4)],
            $usersfullnames);

        $datauser2 = array_filter($cleanresult['managedusers'], static function($user) use ($user2) {
            return $user['user']['id'] == $user2->id;
        });
        $datauser2 = (reset($datauser2));
        $this->assertEquals(fullname($user2), $datauser2['user']['fullname']);
        $this->assertEquals(0, $datauser2['lastaccess']);
        $this->assertFalse($datauser2['isoverdue']);
        $this->assertCount(1, $datauser2['sections']);
        $this->assertEquals('Jobs', $datauser2['sections'][0]['name']);
        $this->assertEquals('', $datauser2['sections'][0]['link']);
        $this->assertEquals('Pos2', $datauser2['sections'][0]['items'][0]['title']);
        $this->assertEquals('Dep1', $datauser2['sections'][0]['items'][0]['subtitle']);
        $this->assertEmpty($datauser2['sections'][0]['items'][0]['badges']);
        $this->assertEquals(4, $cleanresult['totalcount']);

        // Check searching in a given department.
        $result = get_team_overview::execute(0, '', 3, $dep2->id);
        $cleanresult = external_api::clean_returnvalue(get_team_overview::execute_returns(), $result);
        $this->assertEmpty($cleanresult['managedusers']);

        // Check searching in a given position.
        $result = get_team_overview::execute(0, '', 3, 0, false, $pos2->id, false);
        $cleanresult = external_api::clean_returnvalue(get_team_overview::execute_returns(), $result);
        $this->assertEquals(2, $cleanresult['totalcount']);

        // Check searching in a given department and position.
        $result = get_team_overview::execute(0, '', 3, $dep1->id, false, $pos2->id, false);
        $cleanresult = external_api::clean_returnvalue(get_team_overview::execute_returns(), $result);
        $this->assertEquals(2, $cleanresult['totalcount']);

        // Check searching for a specific user in a given department and position.
        $result = get_team_overview::execute(text::IS_EQUAL_TO, fullname($user2), 3, $dep1->id, false, $pos2->id, true);
        $cleanresult = external_api::clean_returnvalue(get_team_overview::execute_returns(), $result);
        $this->assertEquals(1, $cleanresult['totalcount']);

        // Check limit from.
        $result = get_team_overview::execute(0, '', org_structure::OPERATOR_EVERYONE, 0, false, 0, false, 1);
        $cleanresult = external_api::clean_returnvalue(get_team_overview::execute_returns(), $result);
        $this->assertCount(3, $cleanresult['managedusers']);
        $this->assertEquals(4, $cleanresult['totalcount']);

        // Check limit from and limit number.
        $result = get_team_overview::execute(0, '', org_structure::OPERATOR_EVERYONE, 0, false, 0, false, 1, 1);
        $cleanresult = external_api::clean_returnvalue(get_team_overview::execute_returns(), $result);
        $this->assertCount(1, $cleanresult['managedusers']);
        $this->assertEquals(4, $cleanresult['totalcount']);

        // Search for a specific user.
        $result = get_team_overview::execute(text::CONTAINS, 'Lionel', org_structure::OPERATOR_EVERYONE);
        $cleanresult = external_api::clean_returnvalue(get_team_overview::execute_returns(), $result);
        $this->assertCount(1, $cleanresult['managedusers']);
        $this->assertEquals('Lionel Richie', $cleanresult['managedusers'][0]['user']['fullname']);
        $this->assertEquals(1, $cleanresult['totalcount']);

        // Check searching for a position without subpositions.
        $result = get_team_overview::execute(0, '', org_structure::OPERATOR_CUSTOM, $dep1->id, false, $pos2->id, false);
        $cleanresult = external_api::clean_returnvalue(get_team_overview::execute_returns(), $result);
        $this->assertEquals(2, $cleanresult['totalcount']);
        $usersfullnames = array_map(static function($user) {
            return $user['user']['fullname'];
        }, $cleanresult['managedusers']);
        $this->assertEqualsCanonicalizing([fullname($user2), fullname($user3)], $usersfullnames);

        // Check searching for a position with subpositions.
        $result = get_team_overview::execute(0, '', org_structure::OPERATOR_CUSTOM, $dep1->id, false, $pos2->id, true);
        $cleanresult = external_api::clean_returnvalue(get_team_overview::execute_returns(), $result);
        $this->assertEquals(3, $cleanresult['totalcount']);
        $usersfullnames = array_map(static function($user) {
            return $user['user']['fullname'];
        }, $cleanresult['managedusers']);
        $this->assertEqualsCanonicalizing([fullname($user2), fullname($user3), fullname($user4)], $usersfullnames);

        // Check searching for a department without subdepartments.
        $result = get_team_overview::execute(0, '', org_structure::OPERATOR_CUSTOM, $dep1->id, false, $pos3->id, false);
        $cleanresult = external_api::clean_returnvalue(get_team_overview::execute_returns(), $result);
        $this->assertEquals(1, $cleanresult['totalcount']);
        $usersfullnames = array_map(static function($user) {
            return $user['user']['fullname'];
        }, $cleanresult['managedusers']);
        $this->assertEquals([fullname($user4)], $usersfullnames);

        // Check searching for a department with subdepartments.
        $result = get_team_overview::execute(0, '', org_structure::OPERATOR_CUSTOM, $dep1->id, true, $pos3->id, false);
        $cleanresult = external_api::clean_returnvalue(get_team_overview::execute_returns(), $result);
        $this->assertEquals(2, $cleanresult['totalcount']);
        $usersfullnames = array_map(static function($user) {
            return $user['user']['fullname'];
        }, $cleanresult['managedusers']);
        $this->assertEqualsCanonicalizing([fullname($user4), fullname($user5)], $usersfullnames);
    }

    /**
     * Test execute without permission
     */
    public function test_execute_no_permission(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(moodle_exception::class);
        get_team_overview_filters_options::execute();
    }
}
