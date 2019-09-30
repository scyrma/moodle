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
 * Class containing the logic for the filter position_permissions.
 *
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_organisation\tool_reportbuilder\filter;

use tool_organisation\organisation;
use tool_reportbuilder\local\filter\checkbox;
use tool_wp\db;

defined('MOODLE_INTERNAL') || die();

/**
 * Class position_permissions
 *
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class position_permissions extends checkbox {
    /**
     * Returns the condition to be used with SQL where
     *
     * @param array|null $values
     * @return array array of two elements - SQL query and named parameters
     */
    public function get_sql_filter(?array $values) : array {
        global $DB;
        if (!$values) {
            return ['', []];
        }

        $operator = array_key_exists("{$this->name}_op", $values) ? $values["{$this->name}_op"] : null;

        if ((int)$operator === 0) {
            return ['', []];
        }

        $pos = $this->reportfilter->get_field_sql();

        switch($this->name) {
            case 'tool_organisation_jobs:canviewreports':
                $withpermission = organisation::PERM_VIEW_REPORTS;
                break;
            case 'tool_organisation_jobs:canreceivenotifications':
                $withpermission = organisation::PERM_RECEIVE_NOTIFICATIONS;
                break;
            case 'tool_organisation_jobs:canallocateprograms':
                $withpermission = organisation::PERM_ALLOCATE_PROGRAMS;
                break;
            default:
                $withpermission = 0;
                break;
        }

        $globperm = $DB->sql_bitand("{$pos}.globalpermissions", $withpermission);
        $deptperm = $DB->sql_bitand("{$pos}.departmentpermissions", $withpermission);
        $withperm1 = db::generate_param_name();
        $withperm2 = db::generate_param_name();

        if ((int)$operator === 1) {
            $where = "(({$pos}.departmentmanager = 1 AND $deptperm = :{$withperm1})
            OR ({$pos}.globalmanager = 1 AND $globperm = :{$withperm2}))";
        } else {
            $where = "NOT (({$pos}.departmentmanager = 1 AND $deptperm = :{$withperm1})
            OR ({$pos}.globalmanager = 1 AND $globperm = :{$withperm2}))";
        }

        $params = [$withperm1 => $withpermission, $withperm2 => $withpermission];

        return [$where, $params];
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    public function get_label(array $data) : string {
        return 'position_permissions';
    }

    /**
     * Check if the current filter is active in order to show the reset button.
     *
     * Must set the variable "isactive" to true or false.
     *
     * Each filter type must have the own logic to determinate if the filter is active or not.
     *
     * @param array $values
     * @return mixed
     */
    public function is_active(?array $values) : void {
        $this->isactive = !empty($values);
    }

    /**
     * Define the type of the filter.
     *
     * @return string
     */
    protected function filter_type() : string {
        return 'filter-position_permissions';
    }

    /**
     * Returns sample values that can be used in the tests
     *
     * If $extended is not set, return 1-2 sets of values, they will be massively used in tests for all filters in all datasources
     * If $extended is set, return as many sets of values as possible, for extended test of this specific filter
     *
     * @param bool $extended
     * @return array
     */
    public function get_test_values(bool $extended = false) {
        return [
            [$this->name => 0],
            [$this->name => 1],
            [$this->name => 2]
        ];
    }
}