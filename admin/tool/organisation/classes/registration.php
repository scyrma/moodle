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
 * Class registration
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation;

defined('MOODLE_INTERNAL') || die();

/**
 * Class registration
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
