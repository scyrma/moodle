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
 * string_helper
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Sumit Negi
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
use tool_wp\local\helpers\string_helper;
/**
 * Test class
 *
 * @package     tool_wp
 * @group       tool_wp
 * @category    test
 * @covers      tool_wp\local\helpers\string_helper
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Sumit Negi
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class string_helper_test extends \advanced_testcase {

    /**
     * Test string manipulation for week e.g 1/2 week(s), 1/2 day(s), 1/2 hour(s) etc.
     *
     * @param string $value
     * @param string $expected
     * @dataProvider relativedates_provider
     */
    public function test_relativedate_strings_helper($value, $expected): void {
        $translatedstring = string_helper::translate_relativedate_string($value);
        $this->assertEquals($expected, $translatedstring);
    }

    /**
     * Data provider for test_relativedate_strings_helper.
     *
     * @return array
     */
    public function relativedates_provider(): array {
        return [
            'hour' => ['1 hour', '1 hour'],
            'hours' => ['2 hour', '2 hours'],
            'day' => ['1 day', '1 day'],
            'days' => ['2 day', '2 days'],
            'week' => ['1 week', '1 week'],
            'weeks' => ['2 week', '2 weeks'],
            'year' => ['1 year', '1 year'],
            'years' => ['2 year', '2 years']
        ];
    }


}
