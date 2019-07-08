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
 * Tests for the tool_organisation external class.
 *
 * @package   tool_organisation
 * @copyright 2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the tool_organisation external class.
 *
 * @package    tool_organisation
 * @copyright  2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_organisation_external_testcase extends advanced_testcase {
    /**
     * Test for funciton create_departments()
     */
    public function test_create_departments() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $records = [
            ['name' => 'Department 01', 'idnumber' => 'dep1'],
            ['name' => 'Department 02', 'idnumber' => 'dep2'],
            ['name' => 'Department 12', 'parent' => 'dep1'],
            ['name' => 'Department 22', 'parent' => 'dep2'],
            ['name' => 'Department 31', 'parent' => 'dep3'],
        ];
        $departments = tool_organisation_external::create_departments($records);
        $departments = external_api::clean_returnvalue(tool_organisation_external::create_departments_returns(), $departments);
        $this->assertEquals('Department 01', $departments['result'][0]['name']);
        $this->assertEquals('Department 02', $departments['result'][1]['name']);
        $this->assertEquals('Department 12', $departments['result'][2]['name']);
        $this->assertEquals('Department 22', $departments['result'][3]['name']);
        $this->assertEquals('Department 31', $departments['warnings'][0]['item']);
    }

    /**
     * Test for funciton create_positions()
     */
    public function test_create_positions() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $records = [
            ['name' => 'Position 00', 'idnumber' => 'pos0', 'globalmanager' => true],

            ['name' => 'Position 01', 'idnumber' => 'pos1',
             'globalpermissions' => ['allocateprograms' => true, 'viewreports' => true, 'receivenotifications' => true]],

            ['name' => 'Position 02', 'idnumber' => 'pos2', 'departmentmanager' => true],

            ['name' => 'Position 12', 'parent' => 'pos1', 'idnumber' => 'pos12',
             'departmentpermissions' => ['allocateprograms' => true, 'viewreports' => false, 'receivenotifications' => true]],

            ['name' => 'Position 22', 'parent' => 'pos2', 'idnumber' => 'pos22',
             'departmentpermissions' => ['allocateprograms' => true, 'viewreports' => true, 'receivenotifications' => false]],

            ['name' => 'Position 31', 'parent' => 'pos3'],
        ];

        $positions = tool_organisation_external::create_positions($records);
        $positions = external_api::clean_returnvalue(tool_organisation_external::create_positions_returns(), $positions);

        $this->assertEquals('Position 00', $positions['result'][0]['name']);
        $this->assertEquals('Position 01', $positions['result'][1]['name']);
        $this->assertEquals('Position 02', $positions['result'][2]['name']);
        $this->assertEquals('Position 12', $positions['result'][3]['name']);
        $this->assertEquals('Position 22', $positions['result'][4]['name']);
        $this->assertEquals('Position 31', $positions['warnings'][0]['item']);

        $pos0 = \tool_organisation\position::get_record(['idnumber' => 'pos0']);
        $this->assertEquals(1, $pos0->get('globalmanager'));
        $this->assertEquals(0, $pos0->get('departmentmanager'));

        $pos2 = \tool_organisation\position::get_record(['idnumber' => 'pos2']);
        $this->assertEquals(0, $pos2->get('globalmanager'));
        $this->assertEquals(1, $pos2->get('departmentmanager'));

        $this->assertEquals(7, \tool_organisation\position::get_record(['idnumber' => 'pos1'])->get('globalpermissions'));

        $this->assertEquals(5, \tool_organisation\position::get_record(['idnumber' => 'pos12'])->get('departmentpermissions'));

        $this->assertEquals(3, \tool_organisation\position::get_record(['idnumber' => 'pos22'])->get('departmentpermissions'));
    }
}
