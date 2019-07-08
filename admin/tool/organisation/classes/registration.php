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
 * Class registration
 *
 * @package     tool_organisation
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_organisation;

defined('MOODLE_INTERNAL') || die();

/**
 * Class registration
 *
 * @package     tool_organisation
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registration {

    /**
     * Implementation of callback 'stats' called from tool_wp\\registration::site_info
     *
     * Can be called as:
     * component_class_callback('tool_organisation\\registration', 'stats', [true]);
     *
     * @param bool $usestrings return data in human readable form to be displayed on the "Registration" page
     * @return array
     */
    public static function stats($usestrings = false) {
        global $DB;
        $df = $DB->count_records('tool_organisation_department', ['archived' => 0, 'parentid' => null]);
        $d = $DB->count_records('tool_organisation_department', ['archived' => 0]) - $df;
        $pf = $DB->count_records('tool_organisation_position', ['archived' => 0, 'parentid' => null]);
        $p = $DB->count_records('tool_organisation_position', ['archived' => 0]) - $pf;
        $j = $DB->count_records('tool_organisation_job', []);
        return [
            'wpdepartmentframeworks' => $df,
            'wpdepartments' => $d,
            'wppositionframeworks' => $pf,
            'wppositions' => $p,
            'wpjobs' => $j,
        ];
    }

}
