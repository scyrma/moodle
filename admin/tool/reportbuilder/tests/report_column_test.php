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
 * Class tool_reportbuilder_report_column_testcase
 *
 * @package   tool_reportbuilder
 * @category  test
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class tool_reportbuilder_report_column_testcase
 *
 * @package   tool_reportbuilder
 * @covers    \tool_reportbuilder\report_column
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_report_column_testcase extends advanced_testcase{

    /**
     * Creates tenant and assigns user.
     *
     * @return void
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function test_set_visiblename() {
        $reportcolumn = new \tool_reportbuilder\report_column('test', new lang_string('teststring1', 'tool_reportbuilder'), 'test');
        $reportcolumn->set_visiblename(new lang_string('teststring2', 'tool_reportbuilder'));
        $string = $reportcolumn->get_visiblename();
        $this->assertEquals(new lang_string('teststring2', 'tool_reportbuilder'), $string);
    }

}