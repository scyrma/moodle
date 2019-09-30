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
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\tool_reportbuilder\datasources;

use moodle_exception;
use tool_program\local\helpers\program_format;
use tool_program\local\helpers\programcontent_entity;
use tool_program\local\helpers\program_entity;
use tool_reportbuilder\datasource;
use tool_reportbuilder\local\entities\course;
use tool_reportbuilder\local\entities\user;
use tool_reportbuilder\local\helpers\columns;
use tool_reportbuilder\report_column;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Class report_programs
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_programs extends datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_main_table('tool_program', 'tp');
        $this->add_base_join('LEFT JOIN {context} ctx ON ctx.instanceid = tp.id AND ctx.contextlevel = 10');

        $this->set_downloadable(true);
        $this->set_columns();
        $this->set_conditions();
        $this->set_filters();

        // Default columns.
        if ($column = $this->get_column('tool_program:programimage')) {
            $column->set_is_default(true, 1);
            $column->set_is_sortable(true, true);
        }

        if ($column = $this->get_column('tool_program:fullname')) {
            $column->set_is_default(true, 2);
            $column->set_is_sortable(true, true);
        }

        if ($column = $this->get_column('tool_program:associatedcertifications')) {
            $column->set_is_default(true, 3);
            $column->set_is_sortable(true, true);
        }

        if ($column = $this->get_column('tool_program:numbercoursesunique')) {
            $column->set_is_default(true, 4);
            $column->set_is_sortable(true, true);
        }

        if ($column = $this->get_column('tool_program_content:coursesinsetcommaseparatedlinks')) {
            $column->set_is_default(true, 5);
            $column->set_is_sortable(true, true);
        }

        // Add default conditions.
        $conditions = $this->get_conditions();
        $conditions['tool_program:archived']->set_is_default(true, ['archived_op' => 2, 'archived' => 0]);
        $conditions['tool_program:visible']->set_is_default(true, ['visible_op' => 1, 'visible' => 1]);

        // Add default filters.
        $filters = $this->get_filters();
        $filters['tool_program:programselector']->set_is_default(true, [1]);
        $filters['tool_program:archived']->set_is_default(true, ['archived_op' => 2, 'archived' => 0]);
        $filters['tool_program:certification']->set_is_default(true);
        $filters['tool_program:course']->set_is_default(true);
        $filters['tool_program:timecreated']->set_is_default(true);
        $filters['tool_program:timemodified']->set_is_default(true);
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('programs', 'tool_program');
    }

    /**
     * Set the columns available for the report and the definition of each.
     *
     */
    protected function set_columns(): void {
        $this->add_entity(new program_entity('', 'tp'));

        $coursejoin = 'LEFT JOIN {tool_program_sets} tps ON tps.programid = tp.id
            LEFT JOIN {tool_program_courses} tpc ON tpc.setid = tps.id
            LEFT JOIN {course} c ON c.id = tpc.courseid';
        $userjoin = '
            LEFT JOIN {tool_program_users} tpu ON tpu.programid = tp.id
            LEFT JOIN {user} u ON u.id = tpu.userid
        ';

        // TODO remove second and third extra joins on programcontent entity after WP-1088.
        $this->add_entity(new programcontent_entity($coursejoin, 'tps'));
        $this->add_entity(new course($coursejoin, 'c'));
        $this->add_entity(new user($userjoin, 'u'));

        // Actions column.
        $column = (new report_column(
            'actions',
            new \lang_string('actions', 'tool_program'),
            'tool_program'
        ))
            ->add_fields('tp.id')
            ->add_callback([program_format::class, 'actions']);
        columns::disable_column_aggregation($column);
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
            throw new moodle_exception('Helper action not allowed.');
        }
    }
}