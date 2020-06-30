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
 * This file contains the base class for certification outcomes.
 *
 * @package    tool_certification
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\tool_dynamicrule\outcome;

use tool_certification\certification;
use tool_certification\constants;
use tool_tenant\tenancy;
use tool_certification\permission;

defined('MOODLE_INTERNAL') || die;

/**
 * The base class for certification outcomes.
 *
 * @package    tool_certification
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        global $DB;
        $errors = [];
        $params = [
            'id' => $data['certificationid'],
            'tenantid' => tenancy::get_tenant_id(),
            'archived' => 0
        ];
        if (!isset($data['certificationid']) || !$DB->record_exists(certification::TABLE, $params)) {
            $errors['certificationid'] = get_string('errorinvalidcertification', 'tool_certification');
            return $errors;
        }

        if (!permission::can_edit_dynamicrule_outcome(new certification($data['certificationid']))) {
            // We need to check permission here as listed certification might be viewable to user,
            // but user does not have capability to allocate users.
            $errors['certificationid'] = get_string('errornopermissionallocateusers', 'tool_certification');
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
        return permission::can_edit_dynamicrule_outcome($certification);
    }
}
