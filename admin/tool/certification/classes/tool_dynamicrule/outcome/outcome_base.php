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
 * This file contains the backend class for certification_allocation outcome.
 *
 * @package    tool_certification
 * @copyright  2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\tool_dynamicrule\outcome;

use tool_certification\certification;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for certification allocation outcome
 *
 * @package    tool_certification
 * @copyright  2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        global $DB;
        if (!$DB->record_exists(certification::TABLE, ['id' => $this->get_certificationid()])) {
            return false;
        }
        $certification = new certification($this->get_certificationid());
        if ($certification->is_archived()) {
            return false;
        }

        return true;
    }
}
