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

declare(strict_types=1);

namespace tool_tenant\reportbuilder\local;

use stdClass;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\models\report;
use tool_tenant\permission;
use tool_tenant\tenancy;

/**
 * Tenant callbacks for core reportbuilder hacks
 *
 * @package    tool_tenant
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Paul Holden <paulh@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
final class callbacks {

    /**
     * Ensure when new reports are created, we attach tenant details to them
     *
     * @param stdClass $report
     * @return stdClass
     */
    public static function set_report_tenant(stdClass $report): stdClass {
        $report->component = 'tool_tenant';
        $report->itemid = tenancy::get_tenant_id();

        return $report;
    }

    /**
     * Return extra fields for report listing to ensure they are passed to the permission methods
     *
     * @param string $reportalias
     * @return string
     */
    public static function get_reports_list_tenant_fields(string $reportalias): string {
        return "{$reportalias}.component, {$reportalias}.itemid";
    }

    /**
     * Return SQL clause to restrict report listing to those in the given tenant
     *
     * @param string $reportalias
     * @return array
     */
    public static function get_reports_list_tenant_clause(string $reportalias): array {
        $paramcomponent = database::generate_param_name();
        $paramitemid = database::generate_param_name();

        return ["{$reportalias}.component = :{$paramcomponent} AND {$reportalias}.itemid = :{$paramitemid}", [
            $paramcomponent => 'tool_tenant',
            $paramitemid => tenancy::get_tenant_id(),
        ]];
    }

    /**
     * Whether current user can additionally access report according to it's tenant
     *
     * @param report $report
     * @return bool
     */
    public static function can_view_report_in_tenant(report $report): bool {
        return $report->get('component') !== 'tool_tenant' || permission::can_access_tenant($report->get('itemid'));
    }

    /**
     * Whether current user can additionally edit report according to it's tenant
     *
     * @param report $report
     * @return bool
     */
    public static function can_edit_report_in_tenant(report $report): bool {
        return $report->get('component') !== 'tool_tenant' || permission::can_access_tenant($report->get('itemid'));
    }
}
