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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * File containing tests for tool_wp\db class.
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the tool_wp\db class methods.
 *
 * @package    tool_wp
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_wp_db_testcase extends advanced_testcase {

    /**
     * Test for funciton generate_alias() and generate_param_name()
     */
    public function test_generate_alias() {
        global $DB;
        $userid = 2; // Admin user.

        $a1 = \tool_wp\db::generate_alias();
        $a2 = \tool_wp\db::generate_alias();
        $a3 = \tool_wp\db::generate_alias();
        $p1 = \tool_wp\db::generate_param_name();
        $p2 = \tool_wp\db::generate_param_name();

        $this->assertNotEquals($a1, $a2);
        $this->assertNotEquals($p1, $p2);

        $sql = "SELECT {$a1}.id AS {$a3}
            FROM {user} $a1
            JOIN {user} $a2 ON {$a2}.id = {$a1}.id
            WHERE {$a1}.id = :{$p1} AND {$a1}.deleted = :{$p2}";
        $params = [$p1 => $userid, $p2 => 0];

        \tool_wp\db::validate_sql($sql);
        \tool_wp\db::validate_params($params);

        $record = $DB->get_record_sql($sql, $params);
        $this->assertEquals($userid, $record->{$a3});
    }

    /**
     * Test for function validate_params()
     */
    public function test_validate_params() {
        \tool_wp\db::validate_params(
            [\tool_wp\db::generate_param_name() => 1, 'param' => 1, 'id' => 3]);
        $this->assertDebuggingCalled('SQL uses parameters that were not generated with ' .
            'tool_wp\db::generate_param_name(): param, id');
        $this->resetDebugging();
    }

    /**
     * Test for function validate_sql()
     */
    public function test_validate_sql() {
        // TODO: complete the test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );

        \tool_wp\db::validate_sql('SELECT * FROM {users} u');
    }
}
