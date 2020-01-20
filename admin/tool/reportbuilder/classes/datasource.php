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
 * Class datasource
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

use tool_organisation\organisation;

defined('MOODLE_INTERNAL') || die();

/**
 * Class datasource. Must be used as a base class for all datasources
 *
 * Datasources should be located in plugindir/classes/tool_reportbuilder/datasources/classname.php
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class datasource extends report_base {

    /**
     * Get the report conditions definition.
     *
     * @return report_filter[]
     */
    public final function get_conditions() {
        $conditions = parent::get_conditions();
        return $conditions;
    }


    /**
     * Define is the report support organization filter.
     *
     * If report supports organisation filter, it will be available to the users who have manager
     * position with permission to view reports.
     *
     * If datasource returns true here, it should implement the base condition that only limits
     * managed users by calling $this->add_organisation_condition()
     *
     * @return bool
     * @deprecated
     */
    public static function supports_organisation_filter() : bool {
        return false;
    }

    /**
     * For users who can view reports on their teams but can not view all reports add a team condition
     *
     * This method must be called from initialise() if supports_organisation_filter() returnts true
     *
     * @param string $usertablealias
     * @return bool
     * @deprecated
     */
    protected function add_organisation_condition(string $usertablealias) {
        return false;
    }
}
