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
 * File containing tests for relative_dates class.
 *
 * @package   tool_reportbuilder
 * @category  test
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\local\helpers\relative_dates;

/**
 * Class tool_reportbuilder_helper_relative_dates_testcase
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_helper_relative_dates_testcase extends advanced_testcase {

    /**
     * Basic test get_reports_select method.
     */
    public function test_get_start_and_end_timestamp_for() {

        // Test with empty parameters.
        try {
            relative_dates::get_start_and_end_timestamp_for('', '');
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }

        // Test with empty period.
        try {
            relative_dates::get_start_and_end_timestamp_for('current', '');
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
            $this->assertContains('Invalid period', $e->getMessage());
        }

        // Test with empty time.
        try {
            relative_dates::get_start_and_end_timestamp_for('', 'month');
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
            $this->assertContains('Invalid time', $e->getMessage());
        }

        // Current day.
        $datetime = new DateTime('today');
        $datetime->setTime('00', '00', '00');
        $expectedstart = $datetime->getTimestamp();
        $datetime->setTime('23', '59', '59');
        $expectedend = $datetime->getTimestamp();
        [$starttime, $endtime] = relative_dates::get_start_and_end_timestamp_for('current', 'day');
        $this->assertEquals($expectedstart, $starttime);
        $this->assertEquals($expectedend, $endtime);

        // Previous day.
        $datetime = new DateTime('yesterday');
        $datetime->setTime('00', '00', '00');
        $expectedstart = $datetime->getTimestamp();
        $datetime->setTime('23', '59', '59');
        $expectedend = $datetime->getTimestamp();
        [$starttime, $endtime] = relative_dates::get_start_and_end_timestamp_for('previous', 'day');
        $this->assertEquals($expectedstart, $starttime);
        $this->assertEquals($expectedend, $endtime);

        // Upcoming day.
        $datetime = new DateTime('tomorrow');
        $datetime->setTime('00', '00', '00');
        $expectedstart = $datetime->getTimestamp();
        $datetime->setTime('23', '59', '59');
        $expectedend = $datetime->getTimestamp();
        [$starttime, $endtime] = relative_dates::get_start_and_end_timestamp_for('upcoming', 'day');
        $this->assertEquals($expectedstart, $starttime);
        $this->assertEquals($expectedend, $endtime);

        // Current month.
        $datetime = new DateTime('first day of this month');
        $datetime->setTime('00', '00', '00');
        $expectedstart = $datetime->getTimestamp();
        $datetime = new DateTime('last day of this month');
        $datetime->setTime('23', '59', '59');
        $expectedend = $datetime->getTimestamp();
        [$starttime, $endtime] = relative_dates::get_start_and_end_timestamp_for('current', 'month');
        $this->assertEquals($expectedstart, $starttime);
        $this->assertEquals($expectedend, $endtime);

        // Previous month.
        $datetime = new DateTime('first day of last month');
        $datetime->setTime('00', '00', '00');
        $expectedstart = $datetime->getTimestamp();
        $datetime = new DateTime('last day of last month');
        $datetime->setTime('23', '59', '59');
        $expectedend = $datetime->getTimestamp();
        [$starttime, $endtime] = relative_dates::get_start_and_end_timestamp_for('previous', 'month');
        $this->assertEquals($expectedstart, $starttime);
        $this->assertEquals($expectedend, $endtime);

        // Upcoming month.
        $datetime = new DateTime('first day of next month');
        $datetime->setTime('00', '00', '00');
        $expectedstart = $datetime->getTimestamp();
        $datetime = new DateTime('last day of next month');
        $datetime->setTime('23', '59', '59');
        $expectedend = $datetime->getTimestamp();
        [$starttime, $endtime] = relative_dates::get_start_and_end_timestamp_for('upcoming', 'month');
        $this->assertEquals($expectedstart, $starttime);
        $this->assertEquals($expectedend, $endtime);

        // Current year.
        $datetime = new DateTime('first day of january');
        $datetime->setTime('00', '00', '00');
        $expectedstart = $datetime->getTimestamp();
        $datetime = new DateTime('last day of december');
        $datetime->setTime('23', '59', '59');
        $expectedend = $datetime->getTimestamp();
        [$starttime, $endtime] = relative_dates::get_start_and_end_timestamp_for('current', 'year');
        $this->assertEquals($expectedstart, $starttime);
        $this->assertEquals($expectedend, $endtime);

        // Previous year.
        $datetime = new DateTime();
        $datetime->setTime('00', '00', '00');
        $datetime->setDate(date('Y', strtotime('-1 year')), '1', '1');
        $expectedstart = $datetime->getTimestamp();
        $datetime = new DateTime();
        $datetime->setTime('23', '59', '59');
        $datetime->setDate(date('Y', strtotime('-1 year')), '12', '31');
        $expectedend = $datetime->getTimestamp();
        [$starttime, $endtime] = relative_dates::get_start_and_end_timestamp_for('previous', 'year');
        $this->assertEquals($expectedstart, $starttime);
        $this->assertEquals($expectedend, $endtime);

        // Upcoming year.
        $datetime = new DateTime();
        $datetime->setTime('00', '00', '00');
        $datetime->setDate(date('Y', strtotime('+1 year')), '1', '1');
        $expectedstart = $datetime->getTimestamp();
        $datetime = new DateTime();
        $datetime->setTime('23', '59', '59');
        $datetime->setDate(date('Y', strtotime('+1 year')), '12', '31');
        $expectedend = $datetime->getTimestamp();
        [$starttime, $endtime] = relative_dates::get_start_and_end_timestamp_for('upcoming', 'year');
        $this->assertEquals($expectedstart, $starttime);
        $this->assertEquals($expectedend, $endtime);

        // Current week.
        $datetime = new DateTime('Monday this week');
        $datetime->setTime('00', '00', '00');
        $expectedstart = $datetime->getTimestamp();
        $datetime = new DateTime('Sunday this week');
        $datetime->setTime('23', '59', '59');
        $expectedend = $datetime->getTimestamp();
        [$starttime, $endtime] = relative_dates::get_start_and_end_timestamp_for('current', 'week');
        $this->assertEquals($expectedstart, $starttime);
        $this->assertEquals($expectedend, $endtime);

        // Previous week.
        $datetime = new DateTime('Monday last week');
        $datetime->setTime('00', '00', '00');
        $expectedstart = $datetime->getTimestamp();
        $datetime = new DateTime('Sunday last week');
        $datetime->setTime('23', '59', '59');
        $expectedend = $datetime->getTimestamp();
        [$starttime, $endtime] = relative_dates::get_start_and_end_timestamp_for('previous', 'week');
        $this->assertEquals($expectedstart, $starttime);
        $this->assertEquals($expectedend, $endtime);

        // Upcoming week.
        $datetime = new DateTime('Monday next week');
        $datetime->setTime('00', '00', '00');
        $expectedstart = $datetime->getTimestamp();
        $datetime = new DateTime('Sunday next week');
        $datetime->setTime('23', '59', '59');
        $expectedend = $datetime->getTimestamp();
        [$starttime, $endtime] = relative_dates::get_start_and_end_timestamp_for('upcoming', 'week');
        $this->assertEquals($expectedstart, $starttime);
        $this->assertEquals($expectedend, $endtime);

        // Current quarter.
        $datetime = new DateTime();
        $month = $datetime->format('n');

        if ($month < 4) {
            $expectedcurrentstartdate = new DateTime('first day of january');
            $expectedcurrentenddate = new DateTime('last day of march');
        } else if ($month > 3 && $month < 7) {
            $expectedcurrentstartdate = new DateTime('first day of april');
            $expectedcurrentenddate = new DateTime('last day of june');
        } else if ($month > 6 && $month < 10) {
            $expectedcurrentstartdate = new DateTime('first day of july');
            $expectedcurrentenddate = new DateTime('last day of september');
        } else if ($month > 9) {
            $expectedcurrentstartdate = new DateTime('first day of october');
            $expectedcurrentenddate = new DateTime('last day of december');
        }

        $expectedcurrentstartdate->setTime('00', '00', '00');
        $expectedstart = $expectedcurrentstartdate->getTimestamp();
        $expectedcurrentenddate->setTime('23', '59', '59');
        $expectedend = $expectedcurrentenddate->getTimestamp();
        [$starttime, $endtime] = relative_dates::get_start_and_end_timestamp_for('current', 'quarter');
        $this->assertEquals($expectedstart, $starttime);
        $this->assertEquals($expectedend, $endtime);

        // Previous quarter.
        $datetime = new DateTime('3 months ago');
        $month = (int)$datetime->format('n');

        if ($month < 4) {
            $expectedstartdate = $datetime->modify('first day of january');
            $expectedstartdate->setTime('00', '00', '00');
            $expectedstart = $expectedstartdate->getTimestamp();

            $expectedcurrentenddate = $datetime->modify('last day of march');
            $expectedcurrentenddate->setTime('23', '59', '59');
            $expectedend = $expectedcurrentenddate->getTimestamp();
        } else if ($month > 3 && $month < 7) {
            $expectedstartdate = $datetime->modify('first day of april');
            $expectedstartdate->setTime('00', '00', '00');
            $expectedstart = $expectedstartdate->getTimestamp();

            $expectedcurrentenddate = $datetime->modify('last day of june');
            $expectedcurrentenddate->setTime('23', '59', '59');
            $expectedend = $expectedcurrentenddate->getTimestamp();
        } else if ($month > 6 && $month < 10) {
            $expectedstartdate = $datetime->modify('first day of july');
            $expectedstartdate->setTime('00', '00', '00');
            $expectedstart = $expectedstartdate->getTimestamp();

            $expectedcurrentenddate = $datetime->modify('last day of september');
            $expectedcurrentenddate->setTime('23', '59', '59');
            $expectedend = $expectedcurrentenddate->getTimestamp();
        } else if ($month > 9) {
            $expectedstartdate = $datetime->modify('first day of october');
            $expectedstartdate->setTime('00', '00', '00');
            $expectedstart = $expectedstartdate->getTimestamp();

            $expectedcurrentenddate = $datetime->modify('last day of december');
            $expectedcurrentenddate->setTime('23', '59', '59');
            $expectedend = $expectedcurrentenddate->getTimestamp();
        }

        [$starttime, $endtime] = relative_dates::get_start_and_end_timestamp_for('previous', 'quarter');
        $this->assertEquals($expectedstart, $starttime);
        $this->assertEquals($expectedend, $endtime);

        // Upcoming quarter.
        $datetime = new DateTime('+3 months');
        $month = (int)$datetime->format('n');

        if ($month < 4) {
            $expectedstartdate = $datetime->modify('first day of january');
            $expectedstartdate->setTime('00', '00', '00');
            $expectedstart = $expectedstartdate->getTimestamp();

            $expectedcurrentenddate = $datetime->modify('last day of march');
            $expectedcurrentenddate->setTime('23', '59', '59');
            $expectedend = $expectedcurrentenddate->getTimestamp();
        } else if ($month > 3 && $month < 7) {
            $expectedstartdate = $datetime->modify('first day of april');
            $expectedstartdate->setTime('00', '00', '00');
            $expectedstart = $expectedstartdate->getTimestamp();

            $expectedcurrentenddate = $datetime->modify('last day of june');
            $expectedcurrentenddate->setTime('23', '59', '59');
            $expectedend = $expectedcurrentenddate->getTimestamp();
        } else if ($month > 6 && $month < 10) {
            $expectedstartdate = $datetime->modify('first day of july');
            $expectedstartdate->setTime('00', '00', '00');
            $expectedstart = $expectedstartdate->getTimestamp();

            $expectedcurrentenddate = $datetime->modify('last day of september');
            $expectedcurrentenddate->setTime('23', '59', '59');
            $expectedend = $expectedcurrentenddate->getTimestamp();
        } else if ($month > 9) {
            $expectedstartdate = $datetime->modify('first day of october');
            $expectedstartdate->setTime('00', '00', '00');
            $expectedstart = $expectedstartdate->getTimestamp();

            $expectedcurrentenddate = $datetime->modify('last day of december');
            $expectedcurrentenddate->setTime('23', '59', '59');
            $expectedend = $expectedcurrentenddate->getTimestamp();
        }

        [$starttime, $endtime] = relative_dates::get_start_and_end_timestamp_for('upcoming', 'quarter');
        $this->assertEquals($expectedstart, $starttime);
        $this->assertEquals($expectedend, $endtime);
    }
}