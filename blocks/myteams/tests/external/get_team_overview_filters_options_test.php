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

use external_api;
use externallib_advanced_testcase;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the block_myteams get_team_overview_filters_options external class.
 *
 * @covers     \block_myteams\external\get_team_overview_filters_options
 * @package    block_myteams
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_team_overview_filters_options_test extends externallib_advanced_testcase {

    /**
     * Test execute
     */
    public function test_execute(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        $pos1 = $generator->create_position([
            'name' => 'Pos1',
            'globalmanager' => 1,
            'globalpermissions' => \tool_organisation\organisation::PERM_VIEW_REPORTS,
        ]);
        $pos2 = $generator->create_position(['name' => 'Pos2']);
        $dep1 = $generator->create_department(['name' => 'Dep1']);
        $dep2 = $generator->create_department(['name' => 'Dep2']);

        $user1 = $this->getDataGenerator()->create_user();
        $data = [
            'positionid' => $pos1->id,
            'departmentid' => $dep1->id,
            'userid' => $user1->id,
        ];
        $generator->assign_job((object) $data);
        $this->setUser($user1);

        $result = get_team_overview_filters_options::execute();
        $cleanresult = external_api::clean_returnvalue(get_team_overview_filters_options::execute_returns(), $result);
        $this->assertDebuggingNotCalled();
        $this->assertCount(count($cleanresult), $result);

        $this->assertArrayHasKey('departments', $cleanresult);
        $this->assertArrayHasKey('positions', $cleanresult);
        $this->assertArrayHasKey('fullname_filter', $cleanresult);
        $this->assertArrayHasKey('orgstructuretype_filter', $cleanresult);

        $this->assertEqualsCanonicalizing([$pos1->id, $pos2->id], array_column($cleanresult['positions'], 'id'));
        $this->assertEqualsCanonicalizing([$pos1->name, $pos2->name], array_column($cleanresult['positions'], 'name'));
        $this->assertEqualsCanonicalizing([$pos1->parentid, $pos2->parentid],
            array_column($cleanresult['positions'], 'parentid'));

        $this->assertEqualsCanonicalizing([$dep1->id, $dep2->id], array_column($cleanresult['departments'], 'id'));
        $this->assertEqualsCanonicalizing([$dep1->name, $dep2->name], array_column($cleanresult['departments'], 'name'));
        $this->assertEqualsCanonicalizing([$dep1->parentid, $dep2->parentid],
            array_column($cleanresult['departments'], 'parentid'));

        $this->assertCount(9, $cleanresult['fullname_filter']);
        $this->assertCount(3, $cleanresult['orgstructuretype_filter']);
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
