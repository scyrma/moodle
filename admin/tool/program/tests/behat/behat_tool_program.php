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
 * tool_program steps definitions.
 *
 * @package    tool_program
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Steps definitions for tool_program.
 *
 * @package    tool_program
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_program extends behat_base {

    /**
     * Allocates users to programs
     *
     * @Given /^I press "(?P<button_string>(?:[^"]|\\")*)" for the "(?P<course_string>(?:[^"]|\\")*)" program course$/
     *
     * @param string $buttonname
     * @param string $coursename
     */
    public function i_press_for_the_program_course($buttonname, $coursename): void {
        $xpath = "//div[@data-region='programs-overview-course-view' and contains(.,'" .
            $this->escape($coursename) . "')]//div[@data-region='course-call-to-action']";

        $this->execute('behat_general::i_click_on_in_the',
            [$buttonname, 'button', $xpath, 'xpath_element']);
    }

    /**
     * Return the list of partial named selectors.
     *
     * Those selectors can be used to capture dashboard elements. Examples:
     *    And I click on "Expand" "link" in the "ProgramName" "tool_program > Dashboard item"
     *
     * @return array
     */
    public static function get_partial_named_selectors(): array {
        return [
            new behat_component_named_selector('Dashboard item', [
                <<<XPATH
    .//div[contains(concat(' ', normalize-space(@class), ' '), ' dashboard-item ')
            and
            normalize-space(descendant::*[contains(concat(' ', normalize-space(@class), ' '), ' element-name ')]) = %locator%
            ]
XPATH
            ], true),
        ];
    }
}
