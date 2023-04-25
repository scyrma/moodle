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

/**
 * File containing tests for relative_dates class.
 *
 * @package   tool_reportbuilder
 * @category  test
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\helpers;

use advanced_testcase;

/**
 * Class tool_reportbuilder_helper_relative_dates_testcase
 *
 * @package   tool_reportbuilder
 * @group     tool_reportbuilder
 * @covers    \tool_reportbuilder\local\helpers\relative_dates
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class relative_dates_test extends advanced_testcase {

    /**
     * Data provider for test_get_start_and_end_timestamp_for_variable_date
     *
     * @return array
     */
    public function get_start_and_end_timestamp_for_variable_date_provider() : array {
        return [
            // Current/previous/upcoming day.
            ['current', 'day', '01-10-2020 23:50', '01-10-2020', '01-10-2020'],
            ['previous', 'day', '01-10-2020', '30-09-2020', '30-09-2020'],
            ['upcoming', 'day', '01-10-2020', '02-10-2020', '02-10-2020'],
            // Current/previous/upcoming week.
            ['current', 'week', '29-02-2020', '24-02-2020', '01-03-2020'],
            ['previous', 'week', '29-02-2020', '17-02-2020', '23-02-2020'],
            ['upcoming', 'week', '29-02-2020', '02-03-2020', '08-03-2020'],
            // Current month.
            ['current', 'month', '01-01-2020 00:01', '01-01-2020', '31-01-2020'],
            ['current', 'month', '31-01-2020', '01-01-2020', '31-01-2020'],
            ['current', 'month', '01-02-2020', '01-02-2020', '29-02-2020'],
            ['current', 'month', '29-02-2020', '01-02-2020', '29-02-2020'],
            // Previous month.
            ['previous', 'month', '01-02-2020 23:59', '01-01-2020', '31-01-2020'],
            ['previous', 'month', '29-02-2020', '01-01-2020', '31-01-2020'],
            ['previous', 'month', '01-03-2020', '01-02-2020', '29-02-2020'],
            ['previous', 'month', '31-03-2020', '01-02-2020', '29-02-2020'],
            // Upcoming month.
            ['upcoming', 'month', '01-01-2020', '01-02-2020', '29-02-2020'],
            ['upcoming', 'month', '31-01-2020', '01-02-2020', '29-02-2020'],
            ['upcoming', 'month', '01-02-2020', '01-03-2020', '31-03-2020'],
            ['upcoming', 'month', '29-02-2020', '01-03-2020', '31-03-2020'],
            // Current/previous/upcoming quarter.
            ['current', 'quarter', '29-02-2020', '01-01-2020', '31-03-2020'],
            ['previous', 'quarter', '29-02-2020', '01-10-2019', '31-12-2019'],
            ['upcoming', 'quarter', '29-02-2020', '01-04-2020', '30-06-2020'],
            ['current', 'quarter', '31-03-2020', '01-01-2020', '31-03-2020'],
            ['previous', 'quarter', '31-03-2020', '01-10-2019', '31-12-2019'],
            ['upcoming', 'quarter', '31-03-2020 23:59', '01-04-2020', '30-06-2020'],
            ['current', 'quarter', '01-01-2020', '01-01-2020', '31-03-2020'],
            ['previous', 'quarter', '01-01-2020', '01-10-2019', '31-12-2019'],
            ['upcoming', 'quarter', '01-01-2020', '01-04-2020', '30-06-2020'],
            ['current', 'quarter', '15-04-2020', '01-04-2020', '30-06-2020'],
            ['current', 'quarter', '15-08-2020', '01-07-2020', '30-09-2020'],
            ['current', 'quarter', '15-12-2020', '01-10-2020', '31-12-2020'],
            // Current/previous/upcoming year.
            ['current', 'year', '29-02-2020', '01-01-2020', '31-12-2020'],
            ['previous', 'year', '29-02-2020', '01-01-2019', '31-12-2019'],
            ['upcoming', 'year', '29-02-2020', '01-01-2021', '31-12-2021'],
            ['current', 'year', '01-01-2020', '01-01-2020', '31-12-2020'],
            ['previous', 'year', '01-01-2020', '01-01-2019', '31-12-2019'],
            ['upcoming', 'year', '01-01-2020', '01-01-2021', '31-12-2021'],
            ['current', 'year', '31-12-2020', '01-01-2020', '31-12-2020'],
            ['previous', 'year', '31-12-2020', '01-01-2019', '31-12-2019'],
            ['upcoming', 'year', '31-12-2020', '01-01-2021', '31-12-2021'],
            ['upcoming', 'year', '31-12-2019', '01-01-2020', '31-12-2020'],
            ['upcoming', 'year', '01-01-2019', '01-01-2020', '31-12-2020'],
            ['previous', 'year', '31-12-2021', '01-01-2020', '31-12-2020'],
            ['previous', 'year', '01-01-2021', '01-01-2020', '31-12-2020'],
        ];
    }

    /**
     * Test getting start and end timestamp for variable current date
     *
     * @param string $time
     * @param string $period
     * @param string $timenow
     * @param string $expectstart
     * @param string $expectend
     * @return void
     *
     * @dataProvider get_start_and_end_timestamp_for_variable_date_provider
     */
    public function test_get_start_and_end_timestamp_for_variable_date(string $time, string $period, string $timenow,
            string $expectstart, string $expectend) : void {

        list($start, $end) = relative_dates::get_start_and_end_timestamp_for($time, $period, strtotime($timenow));
        $this->assertEquals(strtotime($expectstart), $start);
        $this->assertEquals(strtotime($expectend . ' 23:59:59'), $end);
        $this->assertEquals($expectstart . ' 00:00:00', userdate($start, '%d-%m-%Y %H:%M:%S', 99, false, false));
        $this->assertEquals($expectend . ' 23:59:59', userdate($end, '%d-%m-%Y %H:%M:%S', 99, false, false));
    }
}
