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
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// TODO: move to helpers folder an rename to API.

namespace tool_reportbuilder;

use tool_reportbuilder\event\report_deleted;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class.
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * Returns list of datasources that support organisation filters
     *
     * @return array list of classnames
     */
    public static function get_sources_with_organisation_support(): array {
        $sources = array();
        $plugins = \core_component::get_component_names();

        foreach ($plugins as $plugin) {
            $datasources = \core_component::get_component_classes_in_namespace($plugin, 'tool_reportbuilder\\datasources');
            foreach ($datasources as $class => $path) {
                if (is_subclass_of($class, datasource::class) && $class::supports_organisation_filter()) {
                    $sources[] = $class;
                }
            }
        }

        return $sources;
    }

    /**
     * Return the visible reports for the current user
     */
    public static function get_reports_select() {
        $filters = [
            'type' => constants::TYPE_DATASOURCE,
            'tenantid' => \tool_tenant\tenancy::get_tenant_id()
        ];

        $reports = reportbuilder::get_records($filters);
        $reportsselect = [];
        foreach ($reports as $report) {
            $reportsselect[$report->get('id')] = format_string($report->get('name'));
        }
        asort($reportsselect);
        return $reportsselect;
    }

    /**
     * Function for delete reports.
     *
     * @param report_base $report
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function delete_report(report_base $report) {
        global $PAGE;
        $PAGE->set_context(\context_system::instance());

        $persistent = $report->get_persistent();
        $event = report_deleted::create_from_object($persistent);
        if ($persistent->delete()) {
            $event->trigger();
            return true;
        }
        return false;
    }
}
