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

/**
 * Factory for system reports.
 *
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

use tool_tenant\tenancy;

/**
 * Class system_report_factory
 *
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 * @package   tool_reportbuilder
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

        $persistent = static::get_report_instance($reportclass);
        /** @var system_report $report */
        $report = manager::get_report_from_persistent($persistent, $parameters);

        manager::check_columns($report);
        manager::check_filters($report);

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
     * Given a report source, this method will return a reportbuilder persistent instance for the current tenant. A new instance
     * will be created if one doesn't already exist
     *
     * @param string $reportclass
     * @return reportbuilder
     */
    protected static function get_report_instance(string $reportclass): reportbuilder {
        if ($persistent = reportbuilder::get_record(['source' => $reportclass, 'tenantid' => tenancy::get_tenant_id()])) {
            return $persistent;
        }

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

        return manager::save_report($datareport);
    }
}
