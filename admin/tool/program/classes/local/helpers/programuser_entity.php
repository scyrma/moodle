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

namespace tool_program\local\helpers;

use lang_string;
use tool_program\api;
use tool_reportbuilder\constants;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\filter\checkbox;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\local\helpers\columns;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;

/**
 * Columns, filters and conditions that defines the programuser_entity and can be reused in any report datasource
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class programuser_entity extends entity_base {
    /** @var string */
    protected $join = '';
    /** @var string */
    protected $tablealias = 'tpu';
    /** @var array */
    protected $excludecolumns = [];
    /** @var string */
    protected $tablecompalias = 'tpsc';

    /**
     * Which entity class from core reportbuilder should this entity be migrated to
     *
     * @return string name of the class extending {@see \core_reportbuilder\local\entities\entity_base}
     */
    public function convert_get_entity_class(): string {
        return \tool_program\reportbuilder\local\entities\program_user::class;
    }

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['tool_program_users' => 'tpu', 'tool_program_set_completion' => 'tpsc'];
    }

    /**
     * The default machine-readable name for this entity that will be used in the internal names of the columns/filters
     *
     * @return string
     */
    protected function get_default_entity_name(): string {
        return 'tool_program_users';
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return \lang_string
     */
    protected function get_default_entity_title(): \lang_string {
        return new \lang_string('entityprogramusers', 'tool_program');
    }

    /**
     * Executed when entity is added to the datasource or system report
     */
    public function add_to_report() {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $conditions = $this->get_filters_or_conditions(true);
        foreach ($conditions as $condition) {
            $this->add_condition($condition);
        }

        $filters = $this->get_filters_or_conditions(false);
        foreach ($filters as $filter) {
            $this->add_filter($filter);
        }
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    protected function get_all_columns(): array {
        $columns = [];
        $tablealias = $this->get_table_alias('tool_program_users');
        $tablecompalias = $this->get_table_alias('tool_program_set_completion');

        // Column startdate.
        $newcolumn = (new report_column(
            'startdate',
            new lang_string('startdate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.startdate")
            ->add_field("$tablealias.startdatelocked")
            ->add_callback([programuser_format::class, 'startdate'])
            ->add_aggregation_callback('min', [format::class, 'userdate'])
            ->add_aggregation_callback('max', [format::class, 'userdate']);
        $columns[] = $newcolumn;

        // Column duedate.
        $newcolumn = (new report_column(
            'duedate',
            new lang_string('duedate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.duedate")
            ->add_field("$tablealias.duedatelocked")
            ->add_callback([programuser_format::class, 'duedate'])
            ->add_aggregation_callback('min', [format::class, 'userdate'])
            ->add_aggregation_callback('max', [format::class, 'userdate']);
        $columns[] = $newcolumn;

        // Column enddate.
        $newcolumn = (new report_column(
            'enddate',
            new lang_string('enddate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.enddate")
            ->add_field("$tablealias.enddatelocked")
            ->add_callback([programuser_format::class, 'enddate'])
            ->add_aggregation_callback('min', [format::class, 'userdate'])
            ->add_aggregation_callback('max', [format::class, 'userdate']);
        $columns[] = $newcolumn;

        // Column programstatus.
        $newcolumn = (new report_column(
            'programstatus',
            new lang_string('programstatus', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_NUMBER)
            ->add_field("$tablealias.programid")
            ->add_field("$tablealias.certificationid")
            ->add_field("$tablealias.userid")
            ->add_callback([programuser_format::class, 'programstatus'])
            ->disable_aggregation('min')
            ->disable_aggregation('max');
        $columns[] = $newcolumn;

        // Column programprogress.
        $newcolumn = (new report_column(
            'programprogress',
            new lang_string('programprogress', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.programid")
            ->add_field("$tablealias.userid")
            ->add_callback([programuser_format::class, 'programprogress']);
        $columns[] = $newcolumn;

        // Column programprogresswithoverview.
        $newcolumn = (new report_column(
            'programprogresswithoverview',
            new lang_string('programprogresswithreportlinks', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.id")
            ->add_field("$tablealias.programid")
            ->add_field("$tablealias.userid")
            ->add_field("$tablealias.certificationid")
            ->add_callback([programuser_format::class, 'programprogressoverviewlink']);
        columns::disable_column_aggregation($newcolumn);
        $columns[] = $newcolumn;

        // Column suspended.
        $newcolumn = (new report_column(
            'suspended',
            new lang_string('suspended', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->add_field("$tablealias.status")
            ->add_callback([programuser_format::class, 'suspended']);
        $columns[] = $newcolumn;

        // Column timesuspended.
        $newcolumn = (new report_column(
            'timesuspended',
            new lang_string('timesuspended', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.timesuspended")
            ->add_callback([programuser_format::class, 'timesuspended']);
        $columns[] = $newcolumn;

        // Column allocationtype.
        $newcolumn = (new report_column(
            'allocationtype',
            new lang_string('allocationsource', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_NUMBER)
            ->add_field("$tablealias.allocationtype")
            ->add_callback([programuser_format::class, 'allocationtype']);
        $columns[] = $newcolumn;

        // Column timecreated.
        $newcolumn = (new report_column(
            'timecreated',
            new lang_string('allocationdate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.timecreated")
            ->add_callback([programuser_format::class, 'timecreated']);
        $columns[] = $newcolumn;

        // Column timemodified.
        $newcolumn = (new report_column(
            'timemodified',
            new lang_string('timemodified', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.timemodified")
            ->add_callback([programuser_format::class, 'timemodified']);
        $columns[] = $newcolumn;

        // Column associated certification (if any).
        $newcolumn = (new report_column(
            'associatedcertification',
            new lang_string('associatedcertifications', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_is_sortable(true)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.certificationid")
            ->add_callback([programuser_format::class, 'certificationname']);
        $columns[] = $newcolumn;

        return $columns;
    }

    /**
     * Filters/conditions for programs.
     *
     * @param bool $iscondition
     * @return array
     */
    protected function get_filters_or_conditions(bool $iscondition): array {
        $filters = [];
        $tablealias = $this->get_table_alias('tool_program_users');
        $tablecompalias = $this->get_table_alias('tool_program_set_completion');

        // Filter allocationtype.
        $filters[] = (new report_filter(
            select::class,
            'allocationtype',
            new lang_string('allocationsource', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.allocationtype")
            ->set_options_callback([static::class, 'get_allocation_sources']);

        // Filter startdate.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'startdate',
            new lang_string('startdate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.startdate");

        // Filter duedate.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'duedate',
            new lang_string('duedate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.duedate");

        // Filter enddate.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'enddate',
            new lang_string('enddate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.enddate");

        // Filter suspended.
        $filters[] = (new report_filter(
            checkbox::class,
            'suspended',
            new lang_string('suspended', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("CASE WHEN $tablealias.status = " . \tool_program\constants::STATUS_OVERRIDE_SUSPENDED .
                " THEN 1 ELSE 0 END");

        // Filter timesuspended.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timesuspended',
            new lang_string('timesuspended', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.timesuspended");

        // Filter timecreated.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timecreated',
            new lang_string('allocationdate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.timecreated");

        // Filter timemodified.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timemodified',
            new lang_string('timemodified', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.timemodified");

        // Filter by status.
        $filters[] = (new report_filter(
            select::class,
            'filterablestatus',
            new lang_string('programstatus', 'tool_program'),
            $this->get_entity_name(),
            api::get_status_sql_cases(0, $tablealias, $tablecompalias)
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback([api::class, 'get_program_statuses_fieldset']);

        // TODO programprogress custom filter field sql needed.

        return $filters;
    }

    /**
     * Returns array with allocation sources/types.
     *
     * @return array
     * @throws \coding_exception
     */
    public static function get_allocation_sources(): array {
        return [
            \tool_program\constants::ALLOCATION_MANUAL => get_string('manual', 'tool_program'),
            \tool_program\constants::ALLOCATION_DYNAMIC => get_string('dynamic', 'tool_program'),
            \tool_program\constants::ALLOCATION_CERTIFICATION => get_string('certification', 'tool_program')
        ];
    }
}
