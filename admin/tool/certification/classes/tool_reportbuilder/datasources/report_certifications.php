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
 * Class report_certifications
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\tool_reportbuilder\datasources;

use tool_certification\local\helpers\certification_entity;
use tool_certification\local\helpers\certification_format;
use tool_certification\local\helpers\certificationuser_entity;
use tool_program\local\helpers\program_entity;
use tool_program\local\helpers\programcontent_entity;
use tool_reportbuilder\convert_not_possible;
use tool_reportbuilder\datasource;
use tool_reportbuilder\local\entities\course;
use tool_reportbuilder\local\entities\user;
use lang_string;
use tool_reportbuilder\local\helpers\columns;
use tool_reportbuilder\permission;
use tool_reportbuilder\report_column;
use tool_tenant\hierarchy;
use tool_tenant\tenancy;

/**
 * Class report_certifications
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_certifications extends datasource {

    /** @var string */
    protected $tenantselect;

    /**
     * When converting custom report to core reportbuilder which class corresponds to this datasource
     *
     * @return string name of the class extending {@see \core_reportbuilder\datasource}
     */
    public function convert_get_datasource_class(): string {
        return \tool_certification\reportbuilder\datasource\certifications::class;
    }

    /**
     * When converting this report to core_reportbuilder which column corresponds to the given column
     *
     * @param report_column $oldcolumn the column in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string the full name (unique identifier) of the corresponding column in the converted datasource
     */
    public function convert_get_column_unique_identifier(report_column $oldcolumn,
                                                         \core_reportbuilder\datasource $newsource): string {
        if ($oldcolumn->get_unique_identifier() === 'course:category') {
            return 'course_category:name';
        } else if ($oldcolumn->get_unique_identifier() === 'tool_certification:tenant') {
            return 'tenant:name';
        } else if ($oldcolumn->get_unique_identifier() === 'tool_program:tenant') {
            // TODO WP-3702 implement program tenant column.
            throw new convert_not_possible('Program tenant column can not be converted');
        }
        return parent::convert_get_column_unique_identifier($oldcolumn, $newsource);
    }

    /**
     * When converting this report to core_reportbuilder which filter corresponds to the given filter
     *
     * @param \tool_reportbuilder\report_filter $oldfilter the filter in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string the full name (unique identifier) of the corresponding filter in the converted datasource
     */
    public function convert_get_filter_unique_identifier(\tool_reportbuilder\report_filter $oldfilter,
                                                         \core_reportbuilder\datasource $newsource): string {
        if ($oldfilter->get_unique_identifier() === 'course:category') {
            return 'course_category:name';
        } else if ($oldfilter->get_unique_identifier() === 'tool_certification:tenant') {
            return 'tenant:name';
        }
        return parent::convert_get_filter_unique_identifier($oldfilter, $newsource);
    }

    /**
     * When converting this report to core_reportbuilder which condition corresponds to the given condition
     *
     * @param \tool_reportbuilder\report_filter $oldcondition the condition in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string the full name (unique identifier) of the corresponding condition in the converted datasource
     */
    public function convert_get_condition_unique_identifier(\tool_reportbuilder\report_filter $oldcondition,
                                                            \core_reportbuilder\datasource $newsource): string {
        if ($oldcondition->get_unique_identifier() === 'course:category') {
            return 'course_category:name';
        } else if ($oldcondition->get_unique_identifier() === 'tool_certification:tenant') {
            return 'tenant:name';
        }
        return parent::convert_get_condition_unique_identifier($oldcondition, $newsource);
    }

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->tenantselect = tenancy::get_users_subquery(false, false, 'tcu.userid');

        $this->set_main_table('tool_certification', 'tc', false);
        $this->add_base_join('INNER JOIN {tool_program} tp ON tp.id = tc.program');

        [$sql, $params] = hierarchy::filter_own_or_sub_or_parent_shared_entities_sql('tc.tenantid', 'tc.shared=1');
        $this->add_base_condition_sql($sql, $params);

        $this->set_downloadable(true);
        $this->set_columns();

        // Default columns.
        if ($column = $this->get_column('tool_certification:fullname')) {
            $column->set_is_default(true, 1);
            $column->set_is_sortable(true, true);
        }
        if ($column = $this->get_column('tool_program:fullnamewithimage')) {
            $column->set_is_default(true, 2);
            $column->set_is_sortable(true, true);
        }
        if ($column = $this->get_column('tool_program:numbercoursesunique')) {
            $column->set_is_default(true, 3);
            $column->set_is_sortable(true, true);
        }
        if ($column = $this->get_column('tool_program_content:coursesinsetcommaseparatedlinks')) {
            $column->set_is_default(true, 4);
            $column->set_is_sortable(true, true);
        }

        // Add default conditions.
        $conditions = $this->get_conditions();
        $conditions['tool_certification:archived']->set_is_default(true, ['archived_op' => 2, 'archived' => 0]);
        $conditions['tool_program:archived']->set_is_default(true, ['archived_op' => 2, 'archived' => 0]);
        $conditions['tool_program:visible']->set_is_default(true, ['visible_op' => 1, 'visible' => 1]);

        // Add default filters.
        $filters = $this->get_filters();
        $filters['tool_certification:fullname']->set_is_default(true);
        $filters['tool_program:programselector']->set_is_default(true);
        $filters['tool_certification:archived']->set_is_default(true);
        $filters['tool_program:course']->set_is_default(true);
        $filters['tool_certification:timecreated']->set_is_default(true);
        $filters['tool_certification:timemodified']->set_is_default(true);

        $canshowtenantcolumn = hierarchy::has_subtenants(tenancy::get_tenant_id());
        if ($columntenant = $this->get_column('tool_certification:tenant')) {
            $columntenant->set_is_default($canshowtenantcolumn, 10)
                ->set_is_sortable($canshowtenantcolumn, true)
                ->set_is_available($canshowtenantcolumn);
        }
        if ($conditiontenant = $conditions['tool_certification:tenant']) {
            $conditiontenant->set_is_available($canshowtenantcolumn);
        }
        if ($filtertenant = $filters['tool_certification:tenant']) {
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
        return get_string('certifications', 'tool_certification');
    }

    /**
     * Returns the certification_user/user join.
     *
     * @return array
     */
    private function get_users_joins(): array {
        // Added tcc join here because status in certification user uses completion table.
        return [
            'LEFT JOIN {tool_certification_users} tcu ON tcu.certificationid = tc.id AND ' . $this->tenantselect,
            'LEFT JOIN {user} u ON u.id = tcu.userid',
            'LEFT JOIN {tool_certification_compltion} tcc '.
            'ON tcc.certificationid = tcu.certificationid AND tcc.userid = tcu.userid AND tcc.timerevoked = 0 AND tcc.islast = 1',
            'LEFT JOIN {tool_program_users} tpu '.
            'ON tpu.programid = tcc.programid AND tcu.certificationid = tpu.certificationid AND tcu.userid = tpu.userid'
        ];
    }

    /**
     * Set the columns available for the report and the definition of each.
     *
     */
    protected function set_columns(): void {
        $setsjoin = 'LEFT JOIN {tool_program_sets} tps ON tps.programid = tp.id';

        $coursesjoins = ['LEFT JOIN {tool_program_courses} tpc ON tpc.setid = tps.id',
            'LEFT JOIN {course} c ON c.id = tpc.courseid'];

        $this->add_entity(new certification_entity());

        $this->add_entity((new certificationuser_entity())
            ->add_joins($this->get_users_joins()));

        $this->add_entity(new program_entity());

        $this->add_entity((new programcontent_entity())
            ->add_join($setsjoin));

        $allowtenant = permission::can_show_tenant_column(tenancy::get_tenant_id());
        $this->add_entity((new user())
            ->set_allow_tenant_columns($allowtenant)
            ->add_join($this->get_users_joins()[0])
            ->add_join($this->get_users_joins()[1]));

        $this->add_entity((new course())
            ->add_join($setsjoin)
            ->add_joins($coursesjoins)
            ->set_entity_title(new lang_string('programcourse', 'tool_certification')));

        // Actions column.
        $column = (new report_column(
            'actions',
            new \lang_string('actions', 'tool_certification'),
            'tool_certification'
        ))
            ->add_fields('tc.id')
            ->add_callback([certification_format::class, 'actions']);
        columns::disable_column_aggregation($column);
        $this->add_column($column);
    }
}
