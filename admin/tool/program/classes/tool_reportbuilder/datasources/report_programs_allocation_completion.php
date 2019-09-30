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
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019, Toni Barbera <toni@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\tool_reportbuilder\datasources;

use lang_string;
use moodle_exception;
use tool_program\local\helpers\program_entity;
use tool_program\local\helpers\programcompletion_entity;
use tool_program\local\helpers\programcompletion_format;
use tool_program\local\helpers\programuser_entity;
use tool_program\local\helpers\programuser_format;
use tool_reportbuilder\datasource;
use tool_reportbuilder\local\entities\user as user_entity;
use \tool_organisation\local\entities\jobs as jobs_entity;
use tool_reportbuilder\local\helpers\columns;
use tool_reportbuilder\report_column;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Class report_programs
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019, Toni Barbera <toni@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_programs_allocation_completion extends datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_main_table('tool_program_users', 'tpu');
        $this->add_base_join('INNER JOIN {user} u ON tpu.userid = u.id');
        $this->add_base_join('INNER JOIN {tool_program} tp ON tpu.programid = tp.id');
        $this->add_base_join('INNER JOIN {tool_program_sets} tps ON tps.programid = tpu.programid AND tps.parent = 0');
        $this->add_base_join('LEFT JOIN {tool_program_set_completion} tpsc ON tpsc.setid = tps.id AND tpsc.userid = tpu.userid');

        $this->add_base_condition_simple('u.deleted', 0);
        $this->add_base_condition_simple('tp.tenantid', tenancy::get_tenant_id());

        $this->add_organisation_condition('u');

        $this->set_downloadable(true);

        $this->set_columns();
        $this->set_conditions();
        $this->set_filters();

        // Add default columns.
        if ($column = $this->get_column('tool_program:fullnamewithimage')) {
            $column->set_is_default(true, 1);
            $column->set_is_sortable(true, true);
        }

        if ($column = $this->get_column('user:fullnamewithlink')) {
            $column->set_is_default(true, 2);
            $column->set_is_sortable(true, true);
        }

        if ($column = $this->get_column('user:lastaccess')) {
            $column->set_is_default(true, 3);
            $column->set_is_sortable(true, true);
        }

        if ($column = $this->get_column('tool_program_users:timecreated')) {
            $column->set_is_default(true, 4);
            $column->set_is_sortable(true, true);
        }

        if ($column = $this->get_column('tool_program_users:duedate')) {
            $column->set_is_default(true, 5);
            $column->set_is_sortable(true, true);
        }

        if ($column = $this->get_column('tool_program_users:enddate')) {
            $column->set_is_default(true, 6);
            $column->set_is_sortable(true, true);
        }

        if ($column = $this->get_column('tool_program_users:programstatus')) {
            $column->set_is_default(true, 7);
            $column->set_is_sortable(true, true);
        }

        if ($column = $this->get_column('tool_program_set_completion:completeddate')) {
            $column->set_is_default(true, 8);
            $column->set_is_sortable(true, true);
        }

        if ($column = $this->get_column('tool_program_users:actions')) {
            $column->set_is_default(true, 9);
            $column->set_is_sortable(true, true);
        }

        // Add default conditions.
        $conditions = $this->get_conditions();
        $conditions['tool_program:archived']->set_is_default(true, ['archived_op' => 2, 'archived' => 0]);
        $conditions['tool_program:visible']->set_is_default(true, ['visible_op' => 1, 'visible' => 1]);

        // Add default filters.
        $filters = $this->get_filters();
        $filters['tool_program:programselector']->set_is_default(true, [1]);
        $filters['tool_program_set_completion:programstatus']->set_is_default(true);
        $filters['tool_program_users:timecreated']->set_is_default(true);
        $filters['tool_program_set_completion:completeddate']->set_is_default(true);
        $filters['user:fullname']->set_is_default(true);
        $filters['tool_organisation_jobs:department']->set_is_default(true);
        $filters['tool_organisation_jobs:position']->set_is_default(true);
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
     * Set the columns available for the report and the definition of each.
     */
    protected function set_columns(): void {
        $this->add_entity(new program_entity('', 'tp'));
        $this->add_entity(new programuser_entity('', 'tpu', [], 'tpsc'));
        $this->add_entity(new programcompletion_entity('', 'tpsc', [], 'tpu'));
        $this->add_entity(new user_entity('', 'u'));
        if (class_exists(jobs_entity::class)) {
            $this->add_entity(new jobs_entity('LEFT JOIN {tool_organisation_job} toj ON u.id = toj.userid', 'toj'));
        }

        // Actions column.
        $column = (new report_column(
            'actions',
            new lang_string('actions', 'tool_program'),
            'tool_program_users'
        ))
            ->add_field('u.id', 'userid')
            ->add_field('tpu.certificationid', 'certificationid')
            ->add_field('tp.id', 'programid')
            ->add_callback([programuser_format::class, 'actions']);
        columns::disable_column_aggregation($column);
        $this->add_column($column);

        // Mixed entities columns.
        $column = (new report_column(
            'daystakingprogram',
            new lang_string('daystakingprogram', 'tool_program'),
            'tool_program_set_completion'
        ))
            ->add_field('tpu.startdate')
            ->add_field('tpu.timecreated')
            ->add_field('tpsc.completeddate')
            ->add_callback([programcompletion_format::class, 'daystakingprogram']);
        $this->add_column($column);

        $column = (new report_column(
            'dayssinceallocation',
            new lang_string('dayssinceallocation', 'tool_program'),
            'tool_program_set_completion'
        ))
            ->add_field('tpu.timecreated')
            ->add_field('tpsc.completeddate')
            ->add_callback([programcompletion_format::class, 'dayssinceallocation']);
        $this->add_column($column);
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
