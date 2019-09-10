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
 * File for the class certificationuser_entity
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

defined('MOODLE_INTERNAL') || die();

/**
 * Columns, filters and conditions that defines the certificationuser_entity and can be reused in any report datasource
 *
 * @package     tool_certification
 * @copyright   2019 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificationuser_entity extends entity_base {
    /** @var string */
    protected $join = '';
    /** @var string */
    protected $tablealias = 'tcu';
    /** @var array */
    protected $excludecolumns = [];
    /** @var string */
    protected $tableccalias = 'tcc';

    /**
     * program_fields constructor.
     *
     * @param string $join
     * @param string $tablealias
     * @param array $excludecolumns
     * @param string $tableccalias
     */
    public function __construct(string $join = '', string $tablealias = 'tcu', array $excludecolumns = [],
                                string $tableccalias = 'tcc') {
        $this->join = $join;
        $this->tablealias = $tablealias;
        $this->excludecolumns = array_combine($excludecolumns, $excludecolumns);
        $this->tableccalias = $tableccalias;
    }

    /**
     * Entity name
     *
     * @return string
     */
    public function get_entity_name(): string {
        return 'tool_certification_users';
    }

    /**
     * Entity title
     *
     * @return lang_string
     */
    public function get_entity_title(): lang_string {
        return new lang_string('userallocation', 'tool_certification');
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    public function get_columns(): array {
        $columns = [];

        // Column allocationtype.
        $columns[] = (new report_column(
            'allocationtype',
            new lang_string('allocationsource', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$this->tablealias.allocationtype")
            ->set_is_sortable(true)
            ->add_callback([certificationuser_format::class, 'allocationtype']);

        // Column startdate.
        $columns[] = (new report_column(
            'startdate',
            new lang_string('startdate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.startdate")
            ->add_field("$this->tablealias.startdatelocked")
            ->set_is_sortable(true)
            ->add_callback([certificationuser_format::class, 'startdate'])
            ->add_aggregation_callback('min', [format::class, 'userdate'])
            ->add_aggregation_callback('max', [format::class, 'userdate']);

        // Column duedate.
        $columns[] = (new report_column(
            'duedate',
            new lang_string('duedate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.duedate")
            ->add_field("$this->tablealias.duedatelocked")
            ->set_is_sortable(true)
            ->add_callback([certificationuser_format::class, 'duedate'])
            ->add_aggregation_callback('min', [format::class, 'userdate'])
            ->add_aggregation_callback('max', [format::class, 'userdate']);

        // Column expirydate.
        $columns[] = (new report_column(
            'expirydate',
            new lang_string('expirydate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$this->tablealias.expirydate")
            ->add_field("$this->tablealias.expirydatelocked")
            ->add_field("$this->tablealias.userid")
            ->add_field("$this->tablealias.certificationid")
            ->add_callback([certificationuser_format::class, 'expirydate'])
            ->disable_aggregation('groupconcat')
            ->disable_aggregation('groupconcatdistinct');

        // Column suspended.
        $columns[] = (new report_column(
            'suspended',
            new lang_string('suspended', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->add_field("CASE WHEN $this->tablealias.status = " . \tool_certification\constants::STATUS_OVERRIDE_SUSPENDED .
            " THEN 1 ELSE 0 END", 'suspended')
            ->set_is_sortable(true)
            ->add_callback([format::class, 'checkbox_as_text']);

        // Column timesuspended.
        $columns[] = (new report_column(
            'timesuspended',
            new lang_string('timesuspended', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.timesuspended")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        // Column timecreated.
        $columns[] = (new report_column(
            'timecreated',
            new lang_string('allocationdate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.timecreated")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        // Column timemodified.
        $columns[] = (new report_column(
            'timemodified',
            new lang_string('timemodified', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.timemodified")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        // Column certificationstatus.
        $columns[] = (new report_column(
            'certificationstatus',
            new lang_string('certificationstatus', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field(api::get_status_sql_cases(0, $this->tablealias, $this->tableccalias), 'status')
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
     * Returns all available filters.
     *
     * @return report_filter[]
     */
    public function get_filters(): array {
        return $this->get_filters_or_conditions(false);
    }

    /**
     * Returns all available conditions.
     *
     * @return report_filter[]
     */
    public function get_conditions(): array {
        return $this->get_filters_or_conditions(true);
    }

    /**
     * Filters/conditions for certificationuser.
     *
     * @param bool $iscondition
     * @return array
     */
    protected function get_filters_or_conditions(bool $iscondition): array {
        $filters = [];

        // Filter allocationtype.
        $filters[] = (new report_filter(
            select::class,
            'allocationtype',
            new lang_string('allocationsource', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.allocationtype")
            ->set_options(self::get_allocation_sources());

        // Filter startdate.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'startdate',
            new lang_string('startdate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.startdate");

        // Filter duedate.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'duedate',
            new lang_string('duedate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.duedate");

        // Filter enddate.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'enddate',
            new lang_string('enddate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.enddate");

        // Filter suspended.
        $filters[] = (new report_filter(
            checkbox::class,
            'suspended',
            new lang_string('suspended', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("CASE WHEN $this->tablealias.status = " . \tool_certification\constants::STATUS_OVERRIDE_SUSPENDED .
                " THEN 1 ELSE 0 END");

        // Filter timesuspended.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timesuspended',
            new lang_string('timesuspended', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.timesuspended");

        // Filter timecreated.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timecreated',
            new lang_string('allocationdate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.timecreated");

        // Filter timemodified.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timemodified',
            new lang_string('timemodified', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.timemodified");

        // Filter by status.
        $filters[] = (new report_filter(
            select::class,
            'filterablestatus',
            new lang_string('certificationstatus', 'tool_certification'),
            $this->get_entity_name(),
            api::get_status_sql_cases(0, $this->tablealias, $this->tableccalias, true)
        ))
            ->add_join($this->join)
            ->set_options(api::get_certification_statuses_fieldset());

        // Filter by daystakingcertification.
        $filters[] = (new report_filter(
            number::class,
            'daystakingcertification',
            new lang_string('daystakingcertification', 'tool_certification'),
            $this->get_entity_name(),
            $this->get_daystakingcertification_sql()
        ))
            ->add_join($this->join);

        // Filter by dayssinceallocation.
        $filters[] = (new report_filter(
            number::class,
            'dayssinceallocation',
            new lang_string('dayssinceallocation', 'tool_certification'),
            $this->get_entity_name(),
            $this->get_dayssinceallocation_sql()
        ))
            ->add_join($this->join);

        return $filters;
    }

    /**
     * Returns array with allocation sources/types.
     *
     * @return array
     * @throws \coding_exception
     */
    private static function get_allocation_sources(): array {
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
        return "
                CASE
                WHEN $this->tableccalias.id IS NOT NULL
                AND $this->tableccalias.timerevoked = 0
                AND $this->tablealias.startdate > 0
                AND $this->tableccalias.timecreated > $this->tablealias.startdate
                THEN ($this->tableccalias.timecreated - $this->tablealias.startdate) / (60 * 60 * 24)
                WHEN $this->tableccalias.id IS NOT NULL
                AND $this->tableccalias.timerevoked = 0
                AND $this->tablealias.startdate > 0
                AND $this->tableccalias.timecreated <= $this->tablealias.startdate
                THEN 0
                WHEN ($this->tableccalias.id IS NOT NULL
                AND $this->tableccalias.timerevoked = 0
                AND $this->tablealias.startdate = 0)
                OR $this->tableccalias.id IS NULL
                THEN (".time()." - $this->tablealias.timecreated) / (60 * 60 * 24)
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
        return "
                CASE
                WHEN $this->tableccalias.id IS NOT NULL AND $this->tableccalias.timerevoked = 0
                AND $this->tableccalias.timecreated > $this->tablealias.timecreated
                THEN ($this->tableccalias.timecreated - $this->tablealias.timecreated) / (60 * 60 * 24)
                ELSE (".time()." - $this->tablealias.timecreated) / (60 * 60 * 24)
                END
                ";
    }
}