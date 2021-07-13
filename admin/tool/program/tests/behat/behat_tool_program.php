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
 * tool_tenant steps definitions.
 *
 * @package    tool_program
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use Behat\Gherkin\Node\TableNode;

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Steps definitions for tool_tenant.
 *
 * @package    tool_program
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_program extends behat_base {
    /**
     * Creates the specified element. More info about available elements in http://docs.moodle.org/dev/Acceptance_testing#Fixtures.
     *
     * @Given /^the following tool program data "(?P<element_string>(?:[^"]|\\")*)" exist:$/
     *
     * @deprecated since WP-1273 - please do not use this step any more.
     *
     * @param string $elementname The name of the entity to add
     * @param TableNode $data
     */
    public function the_following_tool_program_data_exist($elementname, TableNode $data): void {
        $this->execute('behat_data_generators::the_following_entities_exist', ['tool_program > programs', $data]);
    }

    /**
     * Allocates users to programs
     *
     * @Given /^the following users allocations to programs exist:$/
     *
     * @deprecated since WP-1273 - please do not use this step any more.
     *
     * @param TableNode $data
     */
    public function the_following_user_allocations_to_programs_exist(TableNode $data): void {
        $this->execute('behat_data_generators::the_following_entities_exist', ['tool_program > program_users', $data]);
    }

    /**
     * Completes program allocations
     *
     * @Given /^the following tool program user allocations are completed:$/
     *
     * @deprecated since WP-1273 - please do not use this step any more.
     *
     * @param TableNode $data
     */
    public function the_following_tool_program_user_allocations_are_completed(TableNode $data): void {
        $this->execute('behat_data_generators::the_following_entities_exist', ['tool_program > program_completions', $data]);
    }

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
     * Adds courses to a program
     *
     * @Given /^the following program courses exist:$/
     *
     * @deprecated since WP-1273 - please do not use this step any more.
     *
     * @param TableNode $data
     */
    public function the_following_program_courses_exist(TableNode $data): void {
        $this->execute('behat_data_generators::the_following_entities_exist', ['tool_program > program_courses', $data]);
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
