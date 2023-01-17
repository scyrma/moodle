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
 * This file contains the base class for certification outcomes.
 *
 * @package    tool_certification
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\tool_dynamicrule\outcome;

use tool_certification\certification;
use tool_certification\constants;
use tool_certification\permission;
use tool_certification\local\helpers\dynamic_rules as helper;
use tool_dynamicrule\rule;

/**
 * The base class for certification outcomes.
 *
 * @package    tool_certification
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class outcome_base extends \tool_dynamicrule\outcome_base {

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
        if (!permission::can_edit_dynamicrule_outcome($certification, $this->get_rule())) {
            // We need to check permission here as listed certification might be viewable to user,
            // but user does not have capability to allocate users.
            $errors['certificationid'] = get_string('errornopermissionallocateusers', 'tool_certification');
        }

        return $errors;
    }

    /**
     * Return the configured certificationid
     *
     * @return int
     */
    protected function get_certificationid(): int {
        return $this->get_configdata()['certificationid'] ?? 0;
    }

    /**
     * Return the configured start date
     *
     * @return int|null
     */
    protected function get_startdate(): ?int {
        $configdata = $this->get_configdata();
        if (!isset($configdata['startdatetype']) || (int)$configdata['startdatetype'] === constants::DATE_NONE) {
            return null;
        }

        return $configdata['startdateabsolute'];
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
        return certification::record_exists($this->get_certificationid()) &&
            !$this->get_certification()->is_archived();
    }

    /**
     * If the current user is able to add this outcome.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return permission::can_add_dynamicrule_outcome();
    }

    /**
     * If the current user is able to edit this outcome.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        $certification = new certification($configdata['certificationid']);
        return permission::can_edit_dynamicrule_outcome($certification, $this->get_rule());
    }

    /**
     * Return the description for the outcome when current user does not have permission to edit it
     *
     * @return string
     */
    public function get_uneditable_description(): string {
        $certification = new certification($this->get_configdata()['certificationid']);
        if (permission::can_view_details($certification)) {
            return $this->get_description();
        }
        return parent::get_uneditable_description();
    }

    /**
     * Which rule types this outcome supports.
     *
     * @return int Rule types bitwise added.
     */
    public function supports_rule_types(): int {
        return rule::TYPE_NORMAL + rule::TYPE_SHARED;
    }
}
