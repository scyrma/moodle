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

namespace tool_wp;

use advanced_testcase;
use core_reportbuilder\local\helpers\database;

/**
 * Tests for the tool_wp\db class methods.
 *
 * @package    tool_wp
 * @covers     \tool_wp\db
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class db_test extends advanced_testcase {

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
        $this->assertDebuggingCalledCount(5);

        $this->assertNotEquals($a1, $a2);
        $this->assertNotEquals($p1, $p2);

        $sql = "SELECT {$a1}.id AS {$a3}
            FROM {user} $a1
            JOIN {user} $a2 ON {$a2}.id = {$a1}.id
            WHERE {$a1}.id = :{$p1} AND {$a1}.deleted = :{$p2}";
        $params = [$p1 => $userid, $p2 => 0];

        \tool_wp\db::validate_sql($sql);
        \tool_wp\db::validate_params($params);
        $this->assertDebuggingCalledCount(2);

        $record = $DB->get_record_sql($sql, $params);
        $this->assertEquals($userid, $record->{$a3});
    }

    /**
     * Test for function validate_params()
     */
    public function test_validate_params() {
        \tool_wp\db::validate_params(
            [\tool_wp\db::generate_param_name() => 1, 'param' => 1, 'id' => 3]);
        $this->assertDebuggingCalledCount(3, [
            'Function \tool_wp\db::generate_param_name() is deprecated. '.
                'Please use \core_reportbuilder\local\helpers\database::generate_param_name()',
            'Function \tool_wp\db::validate_params() is deprecated. '.
                'Please use \core_reportbuilder\local\helpers\database::validate_params()',
            'Coding error detected, it must be fixed by a programmer: Invalid parameter names (param, id)',
        ]);
        $this->resetDebugging();
    }

    /**
     * Test params validation with a custom message
     */
    public function test_validate_params_custom_message(): void {
        db::validate_params(['invalid' => 1], 'Pardon me');
        $this->assertDebuggingCalledCount(2, [
            'Function \tool_wp\db::validate_params() is deprecated. '.
                'Please use \core_reportbuilder\local\helpers\database::validate_params()',
            'Pardon me',
        ]);
        $this->resetDebugging();
    }
}
