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
 * Class report_programs
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
use tool_reportbuilder\permission;
use tool_reportbuilder\report_column;
use tool_tenant\hierarchy;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Class report_programs
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_programs extends datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_main_table('tool_program', 'tp', false);
        [$sql, $params] = hierarchy::filter_own_or_sub_or_parent_shared_entities_sql('tp.tenantid', 'tp.shared=1');
        $this->add_base_condition_sql($sql, $params);

        $this->set_downloadable(true);
        $this->set_columns();
        $this->set_conditions();
        $this->set_filters();

        // Default columns.
        $defaultcolumns = [
            1 => 'tool_program:fullnamewithimage',
            2 => 'tool_program:associatedcertifications',
            3 => 'tool_program:numbercoursesunique',
            4 => 'tool_program_content:coursesinsetcommaseparatedlinks'
        ];

        foreach ($defaultcolumns as $defaultcolumnorder => $defaultcolumnname) {
            if ($column = $this->get_column($defaultcolumnname)) {
                $column->set_is_default(true, $defaultcolumnorder);

                // Set default sorting.
                if ($column->get_is_sortable()) {
                    $column->set_is_sortable(true, true);
                }
            }
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

        $canshowtenantcolumn = hierarchy::has_subtenants(tenancy::get_tenant_id());
        if ($columntenant = $this->get_column('tool_program:tenant')) {
            $columntenant->set_is_default($canshowtenantcolumn, 6)
                ->set_is_sortable($canshowtenantcolumn, true)
                ->set_is_available($canshowtenantcolumn);
        }
        if ($conditiontenant = $conditions['tool_program:tenant']) {
            $conditiontenant->set_is_available($canshowtenantcolumn);
        }
        if ($filtertenant = $filters['tool_program:tenant']) {
            $filtertenant->set_is_default($canshowtenantcolumn)
                ->set_is_available($canshowtenantcolumn);
        }
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
        $this->add_entity(new program_entity());

        $coursejoins = ['LEFT JOIN {tool_program_sets} tps ON tps.programid = tp.id',
            'LEFT JOIN {tool_program_courses} tpc ON tpc.setid = tps.id',
            'LEFT JOIN {course} c ON c.id = tpc.courseid'];

        $tenantselect = tenancy::get_users_subquery(false, false, 'tpu.userid');
        $userjoins = ['LEFT JOIN {tool_program_users} tpu ON tpu.programid = tp.id AND '.$tenantselect,
            'LEFT JOIN {user} u ON u.id = tpu.userid'];

        $this->add_entity((new programcontent_entity())
            ->add_join($coursejoins[0]));

        $this->add_entity((new course())
            ->add_joins($coursejoins));

        $allowtenant = permission::can_show_tenant_column(tenancy::get_tenant_id());
        $this->add_entity((new user())
            ->set_allow_tenant_columns($allowtenant)
            ->add_joins($userjoins));

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
