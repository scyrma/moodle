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
 * Behat tests.
 *
 * @package   tool_certification
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use Behat\Gherkin\Node\TableNode;

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Steps definitions for tool_certification.
 *
 * @package   tool_certification
 * @category  test
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_certification extends behat_base {

    /**
     * Creates the specified element. More info about available elements in http://docs.moodle.org/dev/Acceptance_testing#Fixtures.
     *
     * @Given /^the following tool certification data "(?P<element_string>(?:[^"]|\\")*)" exist:$/
     *
     * @deprecated since WP-1273 - please do not use this step any more.
     *
     * @param string $elementname The name of the entity to add
     * @param TableNode $data
     */
    public function the_following_tool_certification_data_exist($elementname, TableNode $data): void {
        $this->execute('behat_data_generators::the_following_entities_exist', ['tool_certification > certifications', $data]);
    }

    /**
     * Allocates users to certifications
     *
     * @Given /^the following users allocations to certifications exist:$/
     *
     * TODO fix pending calls on other plugins.
     * @deprecated since WP-1273 - please do not use this step any more.
     *
     * @param TableNode $data
     */
    public function the_following_user_allocations_to_certifications_exist(TableNode $data) {
        $this->execute('behat_data_generators::the_following_entities_exist', ['tool_certification > certification_users', $data]);
    }

    /**
     * Navigate to one more week date in the calendar.
     *
     * @Given /^I view the calendar for "(?P<week>\d+)" more weeks$/
     * @param int $weeks the number of weeks
     */
    public function i_view_the_calendar_for_one_more_week(int $weeks): void {
        $time = strtotime("+$weeks week");
        $this->getSession()->visit($this->locate_path('/calendar/view.php?view=day&course=1&time='.$time));
    }
}
