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
 * Class helper
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// TODO: move to helpers folder an rename to API.

namespace tool_reportbuilder;

use tool_reportbuilder\local\helpers\audience as audience_helper;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class helper {
    /**
     * Get sources of the report in all plugins.
     *
     * @param bool $default
     *
     * @return array
     * @throws \coding_exception
     */
    public static function get_sources($default = false): array {
        $sources = array();
        if ($default) {
            $sources[''][''] = get_string('selectsource', 'tool_reportbuilder');
        }

        $datasources = \core_component::get_component_classes_in_namespace(null, 'tool_reportbuilder\\datasources');
        foreach ($datasources as $class => $path) {
            if (is_subclass_of($class, datasource::class)) {
                $component = substr($class, 0, strpos($class, '\\'));
                $pluginname = get_string('pluginname', $component);

                $sources[$pluginname][$class] = call_user_func(array($class, 'get_name'));
            }
        }

        return $sources;
    }

    /**
     * Get list separator as defined by current language config, with trailing space
     *
     * @return string
     */
    public static function get_list_separator() : string {
        return rtrim(get_string('listsep', 'langconfig')) . ' ';
    }

    /**
     * Return the visible reports for the current user
     *
     * @return array of report ID => name
     */
    public static function get_reports_select(): array {
        global $DB;

        $select = 'type = :type AND tenantid = :tenant';
        $params = [
            'type' => constants::TYPE_DATASOURCE,
            'tenant' => tenancy::get_tenant_id(),
        ];

        // If user can't view all reports, limit the returned list to those they can see.
        if (!permission::can_view_any()) {
            $reports = audience_helper::user_reports_list();
            if (empty($reports)) {
                return [];
            }

            list($limit, $limitparams) = $DB->get_in_or_equal($reports, SQL_PARAMS_NAMED);
            $select .= " AND id {$limit}";
            $params = array_merge($params, $limitparams);
        }

        $reportsselect = [];
        foreach (reportbuilder::get_records_select($select, $params, 'name') as $report) {
            $reportsselect[$report->get('id')] = format_string($report->get('name'));
        }

        return $reportsselect;
    }
}
