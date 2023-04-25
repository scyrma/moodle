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

namespace tool_certification\external;

use external_api;
use context_system;
use external_function_parameters;
use external_multiple_structure;
use external_value;
use tool_certification\certification;
use tool_certification\certification_user;
use tool_certification\permission as certificationpermission;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/externallib.php');

/**
 * Class to get all allocations for given certification.
 *
 * @package    tool_certification
 * @author     2022 Odei Alba <odei.alba@moodle.com>
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_certification_allocations extends external_api {
    /**
     * Parameters for execute
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'certificationid' => new external_value(PARAM_INT, 'ID of the certification', VALUE_REQUIRED),
        ]);
    }

    /**
     * Get list of allocated users into a certification
     * @param int $certificationid
     * @return array
     */
    public static function execute(int $certificationid): array {
        global $PAGE;
        // Parameter validation.
        [
            'certificationid' => $certificationid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'certificationid' => $certificationid,
        ]);

        // We always must call validate_context in a webservice.
        $context = context_system::instance();
        self::validate_context($context);

        $certification = new certification($certificationid);

        certificationpermission::require_can_view_allocated_users($certification);

        $renderer = $PAGE->get_renderer('core');
        $tenantselect = tenancy::get_users_subquery(false, true, "userid");
        $select = $tenantselect . 'certificationid = :certificationid';
        $certificationusers = certification_user::get_records_select($select, ['certificationid' => $certificationid]);

        $result = array_map(function($certificationuser) use ($context, $renderer) {
            return (new certification_user_exporter($certificationuser, ['context' => $context]))->export($renderer);
        }, $certificationusers);

        return $result;
    }

    /**
     * Return for execute
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            certification_user_exporter::get_read_structure()
        );
    }
}
