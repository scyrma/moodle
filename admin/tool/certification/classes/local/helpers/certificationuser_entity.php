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
 * File for the class certificationuser_entity
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\local\helpers;

use lang_string;
use tool_certification\api;
use tool_reportbuilder\constants;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\filter\checkbox;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\filter\number;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;
use \tool_reportbuilder\local\helpers\format;

/**
 * Columns, filters and conditions that defines the certificationuser_entity and can be reused in any report datasource
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certificationuser_entity extends entity_base {

    /**
     * Which entity class from core reportbuilder should this entity be migrated to
     *
     * @return string name of the class extending {@see \core_reportbuilder\local\entities\entity_base}
     */
    public function convert_get_entity_class(): string {
        return \tool_certification\reportbuilder\local\entities\certification_user::class;
    }

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['tool_certification_users' => 'tcu', 'tool_certification_compltion' => 'tcc', 'tool_program_users' => 'tpu'];
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
     * Entity name
     *
     * @return string
     */
    protected function get_default_entity_name(): string {
        return 'tool_certification_users';
    }

    /**
     * Entity title
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('userallocation', 'tool_certification');
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    protected function get_all_columns(): array {
        $columns = [];
        $tablealias = $this->get_table_alias('tool_certification_users');
        $tableccalias = $this->get_table_alias('tool_certification_compltion');
        $tablepualias = $this->get_table_alias('tool_program_users');

        // Column allocationtype.
        $columns[] = (new report_column(
            'allocationtype',
            new lang_string('allocationsource', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.allocationtype")
            ->set_is_sortable(true)
            ->add_callback([certificationuser_format::class, 'allocationtype']);

        // Column expirydate.
        $columns[] = (new report_column(
            'expirydate',
            new lang_string('expirydate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tableccalias.expirydate")
            ->add_field("$tablealias.userid")
            ->add_field("$tablealias.certificationid")
            ->add_field("$tableccalias.id", "completionid")
            ->add_callback([certificationuser_format::class, 'expirydate'])
            ->disable_aggregation('groupconcat')
            ->disable_aggregation('groupconcatdistinct');

        // Column suspended.
        $columns[] = (new report_column(
            'suspended',
            new lang_string('suspended', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->add_field("CASE WHEN $tablealias.status = " . \tool_certification\constants::STATUS_OVERRIDE_SUSPENDED .
                " THEN 1 ELSE 0 END", 'suspended')
            ->set_is_sortable(true)
            ->add_callback([format::class, 'checkbox_as_text']);

        // Column timesuspended.
        $columns[] = (new report_column(
            'timesuspended',
            new lang_string('timesuspended', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.timesuspended")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        // Column timecreated.
        $columns[] = (new report_column(
            'timecreated',
            new lang_string('allocationdate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.timecreated")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        // Column timemodified.
        $columns[] = (new report_column(
            'timemodified',
            new lang_string('timemodified', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.timemodified")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        // Column certificationstatus.
        $columns[] = (new report_column(
            'certificationstatus',
            new lang_string('certificationstatus', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field(api::get_status_sql_cases(0, $tablealias, $tableccalias, $tablepualias), 'status')
            ->add_field("$tablepualias.certificationid")
            ->add_callback([certificationuser_format::class, 'status'])
            ->disable_aggregation('groupconcat')
            ->disable_aggregation('groupconcatdistinct');

        // Column daystakingcertification.
        $columns[] = (new report_column(
            'daystakingcertification',
            new lang_string('daystakingcertification', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->set_type(constants::DB_TYPE_NUMBER)
            ->set_is_sortable(true)
            ->add_field($this->get_daystakingcertification_sql(), 'daystakingcertification')
            ->add_callback([certificationuser_format::class, 'dayround'])
            ->add_aggregation_callback('avg', [certificationuser_format::class, 'dayround'])
            ->disable_aggregation('groupconcat')
            ->disable_aggregation('groupconcatdistinct');

        // Column dayssinceallocation.
        $columns[] = (new report_column(
            'dayssinceallocation',
            new lang_string('dayssinceallocation', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->set_type(constants::DB_TYPE_NUMBER)
            ->set_is_sortable(true)
            ->add_field($this->get_dayssinceallocation_sql(), 'dayssinceallocation')
            ->add_callback([certificationuser_format::class, 'dayround'])
            ->add_aggregation_callback('avg', [certificationuser_format::class, 'dayround'])
            ->disable_aggregation('groupconcat')
            ->disable_aggregation('groupconcatdistinct');

        return $columns;
    }

    /**
     * Filters/conditions for certificationuser.
     *
     * @param bool $iscondition
     * @return array
     */
    protected function get_filters_or_conditions(bool $iscondition): array {
        $filters = [];
        $tablealias = $this->get_table_alias('tool_certification_users');
        $tableccalias = $this->get_table_alias('tool_certification_compltion');
        $tablepualias = $this->get_table_alias('tool_program_users');

        // Filter allocationtype.
        $filters[] = (new report_filter(
            select::class,
            'allocationtype',
            new lang_string('allocationsource', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.allocationtype")
            ->set_options_callback([static::class, 'get_allocation_sources']);

        // Filter suspended.
        $filters[] = (new report_filter(
            checkbox::class,
            'suspended',
            new lang_string('suspended', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("CASE WHEN $tablealias.status = " . \tool_certification\constants::STATUS_OVERRIDE_SUSPENDED .
                " THEN 1 ELSE 0 END");

        // Filter timesuspended.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timesuspended',
            new lang_string('timesuspended', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.timesuspended");

        // Filter timecreated.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timecreated',
            new lang_string('allocationdate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.timecreated");

        // Filter timemodified.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timemodified',
            new lang_string('timemodified', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.timemodified");

        // Filter by status.
        $filters[] = (new report_filter(
            select::class,
            'filterablestatus',
            new lang_string('certificationstatus', 'tool_certification'),
            $this->get_entity_name(),
            api::get_status_sql_cases(0, $tablealias, $tableccalias, $tablepualias, true)
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback([api::class, 'get_certification_statuses_fieldset']);

        // Filter by daystakingcertification.
        $filters[] = (new report_filter(
            number::class,
            'daystakingcertification',
            new lang_string('daystakingcertification', 'tool_certification'),
            $this->get_entity_name(),
            $this->get_daystakingcertification_sql()
        ))
            ->add_joins($this->get_joins());

        // Filter by dayssinceallocation.
        $filters[] = (new report_filter(
            number::class,
            'dayssinceallocation',
            new lang_string('dayssinceallocation', 'tool_certification'),
            $this->get_entity_name(),
            $this->get_dayssinceallocation_sql()
        ))
            ->add_joins($this->get_joins());

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
            \tool_certification\constants::ALLOCATION_MANUAL => get_string('manual', 'tool_certification'),
            \tool_certification\constants::ALLOCATION_DYNAMIC => get_string('dynamic', 'tool_certification'),
        ];
    }

    /**
     * Returns daystakingcertification sql
     *
     * @return string
     */
    private function get_daystakingcertification_sql(): string {
        $tablealias = $this->get_table_alias('tool_certification_users');
        $tableccalias = $this->get_table_alias('tool_certification_compltion');
        $tablepualias = $this->get_table_alias('tool_program_users');
        return "
                CASE
                WHEN $tableccalias.id IS NOT NULL
                AND $tableccalias.timerevoked = 0
                AND $tableccalias.islast = 1
                AND $tablepualias.startdate > 0
                AND $tableccalias.timecertified > $tablepualias.startdate
                THEN ($tableccalias.timecertified - $tablepualias.startdate) / ".DAYSECS."
                WHEN $tableccalias.id IS NOT NULL
                AND $tableccalias.timerevoked = 0
                AND $tablepualias.startdate > 0
                AND $tableccalias.timecreated <= $tablepualias.startdate
                THEN 0
                WHEN ($tableccalias.id IS NOT NULL
                AND $tableccalias.timerevoked = 0
                AND $tableccalias.islast = 1
                AND $tablepualias.startdate = 0)
                OR $tableccalias.id IS NULL
                THEN (".time()." - $tablealias.timecreated) / ".DAYSECS."
                ELSE NULL
                END
                ";
    }

    /**
     * Returns dayssinceallocation sql
     *
     * @return string
     */
    private function get_dayssinceallocation_sql(): string {
        $tablealias = $this->get_table_alias('tool_certification_users');
        $tableccalias = $this->get_table_alias('tool_certification_compltion');
        $tablepualias = $this->get_table_alias('tool_program_users');
        return "
                CASE
                WHEN $tableccalias.id IS NOT NULL AND $tableccalias.timerevoked = 0 AND $tableccalias.islast = 1
                AND $tableccalias.timecreated > $tablealias.timecreated
                THEN ($tableccalias.timecreated - $tablealias.timecreated) / ".DAYSECS."
                ELSE (".time()." - $tablealias.timecreated) / ".DAYSECS."
                END
                ";
    }
}
