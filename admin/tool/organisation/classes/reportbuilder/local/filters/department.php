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

namespace tool_organisation\reportbuilder\local\filters;

use MoodleQuickForm;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\filters\base;
use tool_organisation\organisation;
use tool_tenant\hierarchy;

/**
 * Filter for job department
 *
 * The alias of the {tool_organisation_department} table within the report should be passed as filter SQL
 *
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Paul Holden <paulh@moodle.com>
 * @author    2019 Daniel Neis <daniel@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class department extends base {

    /** @var int Show all */
    public const SHOW_ALL = 0;

    /**
     * Adds controls specific to this filter in the form
     *
     * @param MoodleQuickForm $mform
     */
    public function setup_form(MoodleQuickForm $mform): void {
        $options = organisation::get_all_departments_menu(
            [self::SHOW_ALL => get_string('anydepartment', 'tool_organisation')]);

        $elements[] = $mform->createElement('selectgroups', "{$this->name}_department",
            get_string('department', 'tool_organisation'), $options);

        $elements[] = $mform->createElement('advcheckbox', "{$this->name}_subdepartments", null,
            get_string('withsubdepartments', 'tool_organisation'));
        $mform->disabledIf("{$this->name}_subdepartments", "{$this->name}_department", 'eq', self::SHOW_ALL);

        $mform->addGroup($elements, "{$this->name}_group", '', '', false);
    }

    /**
     * Returns the condition to be used with the SQL where clause
     *
     * @param array $values
     * @return array
     */
    public function get_sql_filter(array $values) : array {
        global $DB;

        $department = (int) ($values["{$this->name}_department"] ?? self::SHOW_ALL);
        $subdepartments = !empty($values["{$this->name}_subdepartments"]);

        if ($department === self::SHOW_ALL) {
            return ['', []];
        }

        // Table alias is provided in the filter SQL.
        $tablealias = $this->filter->get_field_sql();

        // Filter by same or parent tenant.
        [$where, $params] = hierarchy::filter_own_or_parent_shared_entities_sql("{$tablealias}.tenantid", "{$tablealias}.shared=1");

        if (!$subdepartments) {
            $paramdepartment = database::generate_param_name();
            $where .= " AND {$tablealias}.id = :{$paramdepartment}";
            $params[$paramdepartment] = $department;
        } else {
            $paramdepartmentpath = database::generate_param_name();
            $where .= ' AND ' . $DB->sql_like("{$tablealias}.path", ":{$paramdepartmentpath}");

            $departmentpath = $DB->get_field('tool_organisation_department', 'path', ['id' => $department]);
            $params[$paramdepartmentpath] = "{$departmentpath}%";
        }

        return [$where, $params];
    }
}
