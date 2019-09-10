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
 * File containing tests for helper filter class.
 *
 * @package   tool_reportbuilder
 * @category  test
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\local\helpers\filters as filters_helper;

/**
 * Class tool_reportbuilder_helper_filters_testcase
 *
 * @package   tool_reportbuilder
 * @covers    \tool_reportbuilder\local\helpers\filters
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_helper_filters_testcase extends advanced_testcase {

    /**
     * Test get_filters method.
     *
     * @throws coding_exception
     */
    public function test_get_filters() {
        $filter = json_encode(array('text-filter' => 'text value'));
        set_user_preference('filters_report_1', $filter);
        $filtershelper = new filters_helper(1);
        $result = $filtershelper->get_report_filters();
        $this->assertEquals(json_decode($filter, true), $result);
    }

    /**
     * Test get_filers method without value.
     *
     * @throws coding_exception
     */
    public function test_get_filters_without_value() {
        $filter = json_encode([]);
        set_user_preference('filters_report_1', $filter);
        $filtershelper = new filters_helper(1);
        $result = $filtershelper->get_report_filters();
        $this->assertEquals(json_decode($filter, true), $result);
    }
}