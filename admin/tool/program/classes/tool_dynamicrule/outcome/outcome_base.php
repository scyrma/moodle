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
 * This file contains the backend class for program deallocation outcome.
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\tool_dynamicrule\outcome;

use tool_program\api;
use tool_program\persistent\program;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for program_allocation outcome
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis <daniel@moodle.com>
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
            'id' => $data['programid'],
            'tenantid' => tenancy::get_tenant_id(),
            'archived' => 0
        ];
        if (!isset($data['programid']) || !$DB->record_exists(program::TABLE, $params)) {
            $errors['programid'] = get_string('errorinvalidprogram', 'tool_program');
        }
        return $errors;
    }

    /**
     * Return a list of valid attributes for given instance
     *
     * @return array Perhaps something similar to persistent definition, e.g. name, type, description
     */
    protected function get_config_attributes(): array {
        return [];
    }

    /**
     * Check if a given programid exists on database
     *
     * @param int $programid
     * @return bool
     */
    protected function program_exists(int $programid): bool {
        return api::program_exists_in_tenant($programid);
    }

    /**
     * Return the configured programid
     *
     * @return int|null
     */
    protected function get_programid(): ?int {
        return $this->get_configdata()['programid'] ?? null;
    }

    /**
     * Return the program object from configured programid
     *
     * @return program
     */
    protected function get_program(): program {
        return new program($this->get_programid());
    }

    /**
     * Check if program still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;
        if (!$DB->record_exists('tool_program', ['id' => $this->get_programid()])) {
            return false;
        }
        $program = new program($this->get_programid());
        if ($program->is_archived()) {
            return false;
        }

        return true;
    }
}
