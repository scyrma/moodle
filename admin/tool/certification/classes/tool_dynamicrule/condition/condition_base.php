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
 * This file contains the base class for certification conditions.
 *
 * @package    tool_certification
 * @author     2020 Ruslan Kabalin
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\tool_dynamicrule\condition;

use tool_certification\certification;
use tool_certification\permission;
use tool_certification\local\helpers\dynamic_rules as helper;

defined('MOODLE_INTERNAL') || die;

/**
 * The base class for certification conditions.
 *
 * @package    tool_certification
 * @author     2020 Ruslan Kabalin
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class condition_base extends \tool_dynamicrule\condition_sql {

    /**
     * Validates the configform of the outcome
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        $errors = [];

        if (!$certification = helper::get_certification_if_valid($data['certificationid'], $this->get_rule())) {
            $errors['certificationid'] = get_string('errorinvalidcertification', 'tool_certification');
            return $errors;
        }
        if (!permission::can_edit_dynamicrule_condition($certification, $this->get_rule())) {
            // We need to check permission here as listed certification might be viewable to user,
            // but user does not have capability to view allocated users.
            $errors['certificationid'] = get_string('errornopermissionviewallocatedusers', 'tool_certification');
        }

        return $errors;
    }

    /**
     * Return the configured certificationid
     *
     * @return int|null
     */
    protected function get_certificationid(): ?int {
        return $this->get_configdata()['certificationid'] ?? null;
    }

    /**
     * Return the certification object from configured certificationid
     *
     * @return certification
     */
    protected function get_certification(): certification {
        return new certification($this->get_certificationid());
    }

    /**
     * Check if certification still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        return (bool)helper::get_certification_if_valid($this->get_certificationid(), $this->get_rule());
    }

    /**
     * If the current user is able to add this condition.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return permission::can_add_dynamicrule_condition();
    }

    /**
     * If the current user is able to edit this condition.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        $certification = new certification($configdata['certificationid']);
        return permission::can_edit_dynamicrule_condition($certification, $this->get_rule());
    }
}
