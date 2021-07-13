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
 * This is service instance designed for deletion of conditions that have missing class.
 *
 * @package    tool_dynamicrule
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Ruslan Kabalin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

defined('MOODLE_INTERNAL') || die;

/**
 * Dummy condition.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class dummy extends \tool_dynamicrule\condition_sql {

    /**
     * Returns the title of the condition.
     *
     * @return string The title as formatted string
     */
    public function get_title(): string {
        return get_string('missingcondition', 'tool_dynamicrule');
    }

    /**
     * Adds condition's elements to the given mform.
     *
     * Not in use, but needs to be defined.
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
    }

    /**
     * Validates the configform of the condition.
     *
     * Not in use, but needs to be defined.
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
    }

    /**
     * Return the description for the condition.
     *
     * Not in use, but needs to be defined.
     *
     * @return string
     */
    public function get_description(): string {
        return '';
    }


    /**
     * We need adding permission to allow user see delete icon.
     * In reality this condition is never listed in the menu.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return true;
    }

    /**
     * No need to edit this condition.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        return false;
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {
        return ['', '1=1', []];
    }

    /**
     * Configuration is not valid.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        return false;
    }

    /**
     * Condition broken label.
     *
     * @return string
     */
    public function get_broken_description(): string {
        $configdata = $this->get_configdata();
        $explodedclass = explode(':', $configdata['instanceclass']);
        $descr = (object) ['plugin' => $explodedclass[0], 'condition' => $explodedclass[1]];
        return get_string('missingconditiondescr', 'tool_dynamicrule', $descr);
    }
}
