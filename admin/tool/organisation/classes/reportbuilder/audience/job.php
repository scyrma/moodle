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

namespace tool_organisation\reportbuilder\audience;

use MoodleQuickForm;
use tool_organisation\department;
use tool_organisation\helper;
use tool_organisation\organisation;
use tool_organisation\permission;
use tool_organisation\position;
use core_reportbuilder\local\audiences\base;
use tool_wp\exporter_base;
use tool_wp\importer_base;
use tool_wp\reportbuilder\local\exportimport\audience_with_mapping;

/**
 * Report audience type based on a users jobs within the organisation structure
 *
 * @package   tool_organisation
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 Paul Holden <paulh@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class job extends base implements audience_with_mapping {

    /** @var int Indicate "Any" department. */
    private const DEPARTMENT_ANY = 0;

    /** @var int Indicate "Any" position. */
    private const POSITION_ANY = 0;

    /**
     * If the current user is able to add this audience type
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return permission::has_assign_jobs_capability();
    }

    /**
     * If the current user is able to edit this audience type
     *
     * @return bool
     */
    public function user_can_edit(): bool {
        return permission::has_assign_jobs_capability();
    }

    /**
     * Returns the title of the audience
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('jobs', 'tool_organisation');
    }

    /**
     * Return the description for the audience.
     *
     * @return string
     */
    public function get_description(): string {
        $configdata = $this->get_configdata();

        // Department name, or "Any".
        $departmentconfig = $configdata['department'];
        $departmentid = (int) ($departmentconfig['id'] ?? -1);
        if ($departmentid !== self::DEPARTMENT_ANY) {
            $strdepartment = (new department($departmentid))->get_formatted_name();

            // Append sub-department info.
            if (!empty($departmentconfig['withsubdepartments'])) {
                $strdepartment .= ' (' . get_string('withsubdepartments', 'tool_organisation') . ')';
            }
        } else {
            $strdepartment = get_string('anydepartment', 'tool_organisation');
        }

        // Position name, or "Any".
        $positionconfig = $configdata['position'];
        $positionid = (int) ($positionconfig['id'] ?? -1);
        if ($positionid !== self::POSITION_ANY) {
            $strposition = (new position($positionid))->get_formatted_name();

            // Append sub-position info.
            if (!empty($positionconfig['withsubpositions'])) {
                $strposition .= ' (' . get_string('withsubpositions', 'tool_organisation') . ')';
            }
        } else {
            $strposition = get_string('anyposition', 'tool_organisation');
        }

        return get_string('audiencejobdescription', 'tool_organisation', (object) [
            'department' => $strdepartment,
            'position' => $strposition,
        ]);
    }

    /**
     * Add form elements for the audience selection
     *
     * @param MoodleQuickForm $mform
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        $strs = get_strings([
            'anydepartment',
            'department',
            'withsubdepartments',
            'anyposition',
            'position',
            'withsubpositions',
        ], 'tool_organisation');

        // Department select.
        $departments = organisation::get_all_departments_menu([self::DEPARTMENT_ANY => $strs->anydepartment]);
        $department = [
            $mform->createElement('selectgroups', 'id', $strs->department, $departments),
            $mform->createElement('checkbox', 'withsubdepartments', $strs->withsubdepartments),
        ];
        $mform->addElement('group', 'department', $strs->department, $department);
        $mform->disabledIf('department[withsubdepartments]', 'department[id]', 'eq', self::DEPARTMENT_ANY);

        // Position select.
        $positions = organisation::get_all_positions_menu([self::POSITION_ANY => $strs->anyposition]);
        $position = [
            $mform->createElement('selectgroups', 'id', $strs->position, $positions),
            $mform->createElement('checkbox', 'withsubpositions', $strs->withsubpositions),
        ];
        $mform->addElement('group', 'position', $strs->position, $position);
        $mform->disabledIf('position[withsubpositions]', 'position[id]', 'eq', self::POSITION_ANY);
    }

    /**
     * Helps to build SQL to retrieve users that match the current audience
     *
     * @param string $usertablealias
     * @return array [$join, $where, [$params]]
     */
    public function get_sql(string $usertablealias): array {
        $configdata = $this->get_configdata();

        $jobtablewheres = $jobtableparams = [];

        // Limit departments if greater than zero (= 'Any').
        $departmentconfig = $configdata['department'];
        $departmentid = (int) ($departmentconfig['id'] ?? -1);
        if ($departmentid !== self::DEPARTMENT_ANY) {
            $withsubdepartments = !empty($departmentconfig['withsubdepartments']);

            [$departmentwhere, $departmentparams] = helper::user_is_in_department_select($departmentid,
                $withsubdepartments, $usertablealias);

            $jobtablewheres[] = $departmentwhere;
            $jobtableparams = array_merge($jobtableparams, $departmentparams);
        }

        // Limit positions if greater than zero (= 'Any').
        $positionconfig = $configdata['position'];
        $positionid = (int) ($positionconfig['id'] ?? -1);
        if ($positionid !== self::POSITION_ANY) {
            $withsubpositions = !empty($positionconfig['withsubpositions']);

            [$positionwhere, $positionparams] = helper::user_has_position_select($positionid,
                $withsubpositions, $usertablealias);

            $jobtablewheres[] = $positionwhere;
            $jobtableparams = array_merge($jobtableparams, $positionparams);
        }

        // If we have some where clauses, use them, otherwise user has selected Any/Any so select users with current jobs.
        if (count($jobtablewheres) > 0) {
            $jobtablewhere = implode(' AND ', $jobtablewheres);
        } else {
            [$jobtablewhere, $jobparams] = helper::users_with_jobs_sql($usertablealias);

            $jobtableparams = array_merge($jobtableparams, $jobparams);
        }

        return ['', $jobtablewhere, $jobtableparams];
    }

    /**
     * Add department/position field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $configdata = $this->get_configdata();

        // Map department if greater than zero (= 'Any').
        $departmentconfig = $configdata['department'];
        $departmentid = (int) ($departmentconfig['id'] ?? -1);
        if ($departmentid !== self::DEPARTMENT_ANY) {
            $exporter->add_mapping('tool_organisation_department', $departmentid);
        }

        // Map position if greater than zero (= 'Any').
        $positionconfig = $configdata['position'];
        $positionid = (int) ($positionconfig['id'] ?? -1);
        if ($positionid !== self::POSITION_ANY) {
            $exporter->add_mapping('tool_organisation_position', $positionid);
        }
    }

    /**
     * Get department/position field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $configdata = $this->get_configdata();

        // Get department mapping if greater than zero (= 'Any'), default to -1 if missing.
        $departmentconfig = $configdata['department'];
        $departmentid = (int) ($departmentconfig['id'] ?? -1);
        if ($departmentid !== self::DEPARTMENT_ANY) {
            $configdata['department']['id'] = $importer->get_mapping('tool_organisation_department', $departmentid,
                IGNORE_MISSING) ?? -1;
        }

        // Get position mapping if greater than zero (= 'Any'), default to -1 if missing.
        $positionconfig = $configdata['position'];
        $positionid = (int) ($positionconfig['id'] ?? -1);
        if ($positionid !== self::POSITION_ANY) {
            $configdata['position']['id'] = $importer->get_mapping('tool_organisation_position', $positionid,
                IGNORE_MISSING) ?? -1;
        }

        $this->update_configdata($configdata);
    }
}
