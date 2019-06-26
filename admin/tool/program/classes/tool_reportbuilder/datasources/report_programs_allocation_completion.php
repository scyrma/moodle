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
 * Class report_programs
 *
 * @package   tool_program
 * @copyright 2019, Toni Barbera <toni@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\tool_reportbuilder\datasources;

use lang_string;
use moodle_exception;
use tool_program\local\helpers\program_fields;
use tool_reportbuilder\datasource;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Class report_programs
 *
 * @package   tool_program
 * @copyright 2019, Toni Barbera <toni@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_programs_allocation_completion extends datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_main_table('tool_program_users', 'tpu');
        $this->add_base_join('INNER JOIN {user} u ON tpu.userid = u.id');
        $this->add_base_join('INNER JOIN {tool_program} tp ON tpu.programid = tp.id');
        $this->add_base_condition_simple('tp.tenantid', tenancy::get_tenant_id());

        $this->add_organisation_condition('u');

        $this->set_downloadable(true);

        $this->set_columns();
        $this->set_conditions();
        $this->set_filters();

        $this->get_column('user:fullname')
            ->set_is_default(true)
            ->set_is_sortable(true, true, 1);

        $this->get_column('tool_program:fullname')
            ->set_is_default(true)
            ->set_is_sortable(true, true, 1);
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('reportprogramsallocationcompletion', 'tool_program');
    }

    /**
     * Gets an instance of program_fields_helper that is used to add typical program columns, filters and conditions
     *
     * @return program_fields
     */
    protected function get_program_fields_helper(): program_fields {
        return new program_fields(
                '',
                'tp',
                [
                    'startdatetype',
                    'startdateabsolute',
                    'startdaterelative',
                    'duedatetype',
                    'duedateabsolute',
                    'duedaterelative',
                    'enddatetype',
                    'enddateabsolute',
                    'enddaterelative',
                    'allocationstartdatetype',
                    'allocationstartdateabsolute',
                    'allocationenddatetype',
                    'allocationenddateabsolute',
                    'allocationenddaterelative',
                    'timearchived',
                    'allowdirectallocation',
                ]
            );
    }

    /**
     * SQL call helper for columns that have sets joins
     *
     * @return string
     */
    private function get_program_sets_joins_helper(): string {
        return 'INNER JOIN {tool_program_sets} tps
                ON tps.programid = tpu.programid AND tps.parent = 0
                LEFT JOIN {tool_program_set_completion} tpsc
                ON tpsc.setid = tps.id  AND tpsc.userid = tpu.userid';
    }

    /**
     * Set the columns available for the report and the definition of each.
     *
     */
    protected function set_columns(): void {
        $this->add_entity($this->get_program_fields_helper());
        $this->annotate_entity('tool_program_users', new lang_string('entityprogramusers', 'tool_program'));
        $this->annotate_entity('tool_program_set_completion', new lang_string('entityprogramcompletion', 'tool_program'));
        $this->annotate_entity('tool_organisation_department', new lang_string('entitydepartment', 'tool_organisation'));
        $this->annotate_entity('tool_organisation_position', new lang_string('entityposition', 'tool_organisation'));
        $this->annotate_entity('tool_organisation_jobs', new lang_string('entityjob', 'tool_organisation'));

        $newcolumn = (new report_column(
            'programcompletion',
            new lang_string('programcompletion', 'tool_program'),
            'tool_program_set_completion'
        ))
            ->add_join($this->get_program_sets_joins_helper())
            ->add_field('tpsc.completeddate');
        $newcolumn->add_callback([format::class, 'userdate']);
        $this->add_column($newcolumn);

        $newcolumn = (new report_column(
            'programstartdate',
            new lang_string('startdate', 'tool_program'),
            'tool_program_users'
        ))
            ->add_join($this->get_program_sets_joins_helper())
            ->add_field('tpu.startdate');
        $newcolumn->add_callback([format::class, 'userdate']);
        $this->add_column($newcolumn);

        $newcolumn = (new report_column(
            'programduedate',
            new lang_string('duedate', 'tool_program'),
            'tool_program_users'
        ))
            ->add_join($this->get_program_sets_joins_helper())
            ->add_field('tpu.duedate');
        $newcolumn->add_callback([format::class, 'userdate']);
        $this->add_column($newcolumn);

        $newcolumn = (new report_column(
            'programenddate',
            new lang_string('enddate', 'tool_program'),
            'tool_program_users'
        ))
            ->add_join($this->get_program_sets_joins_helper())
            ->add_field('tpu.enddate');
        $newcolumn->add_callback([format::class, 'userdate']);
        $this->add_column($newcolumn);

        // User status.
        $newcolumn = (new report_column(
            'userstatus',
            new lang_string('status', 'tool_program'),
            'tool_program_users'
        ))
            ->add_field('tpu.programid')
            ->add_field('tpu.certificationid', 'certid')
            ->add_field('tpu.userid');
        $newcolumn->add_callback([\tool_program\local\helpers\format::class, 'userstatus']);
        $this->add_column($newcolumn);

        $this->add_entity(new user_entity('', 'u', ['idnumber']));
    }

    /**
     * Set the filters of the report
     */
    protected function set_filters(): void {
        $this->add_filters_conditions_helper('add_filter');
    }

    /**
     * Available conditions to be selected in the report.
     */
    protected function set_conditions(): void {
        $this->add_filters_conditions_helper('add_condition');
    }

    /**
     * Helper that add filters or conditions depend how is called
     *
     * @param string $method
     */
    protected function add_filters_conditions_helper(string $method): void {
        if (!in_array($method, ['add_filter', 'add_condition'])) {
            throw new moodle_exception('errorhelperactionnotallowed', 'tool_program');
        }

        $this->$method(
            (new report_filter(
                date_condition::class,
                'timecreated',
                new lang_string('userallocation', 'tool_program'),
                'tool_program_users',
                'tpu.timecreated'
            ))
                ->add_join($this->get_program_sets_joins_helper())
        );

        $this->$method(
            (new report_filter(
                date_condition::class,
                'completeddate',
                new lang_string('programcompletion', 'tool_program'),
                'tool_program_set_completion',
                'tpsc.completeddate'
            ))
                ->add_join($this->get_program_sets_joins_helper())
        );

        $this->$method(
            (new report_filter(
                date_condition::class,
                'startdate',
                new lang_string('programstartdate', 'tool_program'),
                'tool_program_users',
                'tpu.startdate'
            ))
                ->add_join($this->get_program_sets_joins_helper())
        );

        $this->$method(
            (new report_filter(
                date_condition::class,
                'duedate',
                new lang_string('programduedate', 'tool_program'),
                'tool_program_users',
                'tpu.duedate'
            ))
                ->add_join($this->get_program_sets_joins_helper())
        );

        $this->$method(
            (new report_filter(
                date_condition::class,
                'enddate',
                new lang_string('programenddate', 'tool_program'),
                'tool_program_users',
                'tpu.enddate'
            ))
                ->add_join($this->get_program_sets_joins_helper())
        );
    }

    /**
     * This report is available to organisation managers with the permission to view reports
     *
     * Only users who are managed by the current user will be displayed
     *
     * @return bool
     */
    public static function supports_organisation_filter(): bool {
        return true;
    }
}
