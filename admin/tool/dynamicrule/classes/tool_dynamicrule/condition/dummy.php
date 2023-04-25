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
 * This is service instance designed for deletion of conditions that have missing class.
 *
 * @package    tool_dynamicrule
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

/**
 * Dummy condition.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
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
        return [];
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
     * Return the description for the condition when current user does not have permission to edit it
     *
     * @return string
     */
    public function get_uneditable_description(): string {
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
