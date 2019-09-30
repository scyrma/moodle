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
 */
abstract class datasource extends report_base {
    /** @var bool $orgconditionadded */
    protected $orgconditionadded = false;

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
     * Helps to validate the report.
     */
    protected final function validate() {
        parent::validate();
        if (static::supports_organisation_filter() && !$this->orgconditionadded) {
            throw new \coding_exception('Missing call to add_organisation_condition() during initialising');
        }
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
     *
     * @return bool
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
     */
    protected function add_organisation_condition(string $usertablealias) {
        if (!permission::can_view_any()) {
            // This report supports "organisation-only" view mode. Add managed users condition.
            if ($manager = \tool_organisation\organisation::get_user_with_jobs()) {
                list($where, $params) = $manager->get_managed_users_select($usertablealias,
                    organisation::PERM_VIEW_REPORTS);
                $this->add_base_condition_sql($where, $params);
            } else {
                // This user is not a manager and can not see contents of the report.
                $this->add_base_condition_sql('1=0');
            }
        }
        $this->orgconditionadded = true;
    }
}
