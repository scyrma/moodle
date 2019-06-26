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

use Closure;
use context_system;
use tool_certification\api;
use tool_certification\certification;

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
        $errors = [];
        if (!isset($data['certificationid']) || !$this->certification_exists($data['certificationid'])) {
            $errors['certificationid'] = get_string('errorinvalidcertification', 'tool_certification');
        }
        return $errors;
    }

    /**
     * Check if a given certificationid exists on database
     *
     * @param int $certificationid
     * @return bool
     */
    protected function certification_exists(int $certificationid): bool {
        return api::certification_exists_in_tenant($certificationid);
    }

    /**
     * Return the configured certificationid
     *
     * @return int
     */
    protected function get_certificationid(): int {
        return (int) $this->get_configdata()['certificationid'];
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
     * Return certifications field set: certification ids => certification names.
     *
     * @return array
     */
    protected function get_certifications(): array {
        return api::get_certifications_in_tenant_fieldset();
    }

    /**
     * Check if certification still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;
        return $DB->record_exists('tool_certification', ['id' => $this->get_certificationid()]);
    }

    /**
     * Returns format fullname callback given a program id.
     *
     * @return Closure
     */
    protected function get_format_certification_fullname_callback(): Closure {
        return static function($certificationid) {
            $certification = new certification($certificationid);
            $formatparams = ['context' => context_system::instance(), 'escape' => false];
            return format_string($certification->get('fullname'), true, $formatparams);
        };
    }
}
