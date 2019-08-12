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
 * Factory for system reports.
 *
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder;

use core\session\exception;
use tool_reportbuilder\local\helpers\filters as filters_helper;
use tool_reportbuilder\local\helpers\columns as columns_helper;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die;

/**
 * Class system_report_factory
 *
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_reportbuilder
 */
class system_report_factory {
    /**
     * Create the system report.
     *
     * @param string $reportclass
     * @param array $parameters additional parameters, simple keys with simple values.
     *     They can be accessed inside report using function get_parameter()
     *
     * @return system_report
     */
    public static function create(string $reportclass, array $parameters = []) : system_report {
        if (!static::is_system_report($reportclass)) {
            throw new \coding_exception("This class is not a system report");
        }

        $persistent = static::generate_reportid($reportclass);
        /** @var system_report $report */
        $report = manager::get_report_from_persistent($persistent, $parameters);

        // TODO SP-422 there are a lot of DB queries here, much more than necessary,
        // especially when there are no changes to the structure.
        $columns = $report->get_columns();
        $filters = $report->get_filters();
        manager::check_columns($report->get_id(), $columns);
        manager::check_filters($report->get_id(), $filters);
        $currentcolumns = columns_helper::get_active_columns($report);
        $currentfilters = filters_helper::get_active_filters($report->get_id());
        manager::delete_old_columns($currentcolumns, $columns);
        manager::delete_old_filters($currentfilters, $filters);

        return $report;
    }

    /**
     * Check if the given class is a system report.
     *
     * @param string $classname
     *
     * @return bool
     * @throws \ReflectionException
     */
    protected static function is_system_report(string $classname) : bool {
        if (class_exists($classname) && is_subclass_of($classname, system_report::class)) {
            return (new \ReflectionClass($classname))->isInstantiable();
        }

        return false;
    }

    /**
     * Create the report id for the customizations in the report builder.
     *
     * @param string $reportclass
     *
     * @return int
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     */
    protected static function generate_reportid(string $reportclass) : reportbuilder {
        /** @var reportbuilder $persistent */
        $persistent = reportbuilder::get_record(['source' => $reportclass, 'tenantid' => tenancy::get_tenant_id()]);
        if ($persistent) {
            return $persistent;
        }

        $manager = new manager();
        $datareport = new \stdClass();
        $datareport->source = $reportclass;
        $datareport->description = '';
        $datareport->type = constants::TYPE_SYSTEM;
        // TODO SP-469 For system reports we do call to manager::check_columns().
        $datareport->adddefault = 0;

        // Tricking IDE for static methods calls.
        /** @var system_report $reportclassname */
        $reportclassname = $reportclass;
        $datareport->name = $reportclassname::get_name();

        return $manager->save_report($datareport);
    }
}