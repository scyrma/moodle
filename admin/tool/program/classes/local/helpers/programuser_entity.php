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
 * File for the class programuser_entity
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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

defined('MOODLE_INTERNAL') || die();

/**
 * Columns, filters and conditions that defines the programuser_entity and can be reused in any report datasource
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * program_fields constructor.
     *
     * @param string $join
     * @param string $tablealias
     * @param array $excludecolumns
     * @param string $tablecompalias
     */
    public function __construct(string $join = '', string $tablealias = 'tpu', array $excludecolumns = [],
                                string $tablecompalias = 'tpsc') {
        $this->join = $join;
        $this->tablealias = $tablealias;
        $this->excludecolumns = array_combine($excludecolumns, $excludecolumns);
        $this->tablecompalias = $tablecompalias;
    }

    /**
     * Entity name
     *
     * @return string
     */
    public function get_entity_name(): string {
        return 'tool_program_users';
    }

    /**
     * Entity title
     *
     * @return lang_string
     */
    public function get_entity_title(): lang_string {
        return new lang_string('entityprogramusers', 'tool_program');
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    public function get_columns(): array {
        $columns = [];

        if (!isset($this->excludecolumns['startdate'])) {
            // Column startdate.
            $newcolumn = (new report_column(
                'startdate',
                new lang_string('startdate', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_TIMESTAMP)
                ->add_field("$this->tablealias.startdate")
                ->add_field("$this->tablealias.startdatelocked")
                ->add_callback([programuser_format::class, 'startdate'])
                ->add_aggregation_callback('min', [format::class, 'userdate'])
                ->add_aggregation_callback('max', [format::class, 'userdate']);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['duedate'])) {
            // Column duedate.
            $newcolumn = (new report_column(
                'duedate',
                new lang_string('duedate', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_TIMESTAMP)
                ->add_field("$this->tablealias.duedate")
                ->add_field("$this->tablealias.duedatelocked")
                ->add_callback([programuser_format::class, 'duedate'])
                ->add_aggregation_callback('min', [format::class, 'userdate'])
                ->add_aggregation_callback('max', [format::class, 'userdate']);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['enddate'])) {
            // Column enddate.
            $newcolumn = (new report_column(
                'enddate',
                new lang_string('enddate', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_TIMESTAMP)
                ->add_field("$this->tablealias.enddate")
                ->add_field("$this->tablealias.enddatelocked")
                ->add_callback([programuser_format::class, 'enddate'])
                ->add_aggregation_callback('min', [format::class, 'userdate'])
                ->add_aggregation_callback('max', [format::class, 'userdate']);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['programstatus'])) {
            // Column programstatus.
            $newcolumn = (new report_column(
                'programstatus',
                new lang_string('programstatus', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_NUMBER)
                ->add_field("$this->tablealias.programid")
                ->add_field("$this->tablealias.certificationid")
                ->add_field("$this->tablealias.userid")
                ->add_callback([programuser_format::class, 'programstatus'])
                ->disable_aggregation('min')
                ->disable_aggregation('max');
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['programprogress'])) {
            // Column programprogress.
            $newcolumn = (new report_column(
                'programprogress',
                new lang_string('programprogress', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_TEXT)
                ->add_field("$this->tablealias.programid")
                ->add_field("$this->tablealias.userid")
                ->add_callback([programuser_format::class, 'programprogress']);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['programprogresswithoverview'])) {
            // Column programprogresswithoverview.
            $newcolumn = (new report_column(
                'programprogresswithoverview',
                new lang_string('programprogress', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_TEXT)
                ->add_field("$this->tablealias.id")
                ->add_field("$this->tablealias.programid")
                ->add_field("$this->tablealias.userid")
                ->add_field("$this->tablealias.certificationid")
                ->add_callback([programuser_format::class, 'programprogressoverviewlink']);
            columns::disable_column_aggregation($newcolumn);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['suspended'])) {
            // Column suspended.
            $newcolumn = (new report_column(
                'suspended',
                new lang_string('suspended', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_BOOLEAN)
                ->add_field("$this->tablealias.status")
                ->add_callback([programuser_format::class, 'suspended']);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['timesuspended'])) {
            // Column timesuspended.
            $newcolumn = (new report_column(
                'timesuspended',
                new lang_string('timesuspended', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_TIMESTAMP)
                ->add_field("$this->tablealias.timesuspended")
                ->add_callback([programuser_format::class, 'timesuspended']);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['allocationtype'])) {
            // Column allocationtype.
            $newcolumn = (new report_column(
                'allocationtype',
                new lang_string('allocationsource', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_NUMBER)
                ->add_field("$this->tablealias.allocationtype")
                ->add_callback([programuser_format::class, 'allocationtype']);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['timecreated'])) {
            // Column timecreated.
            $newcolumn = (new report_column(
                'timecreated',
                new lang_string('allocationdate', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_TIMESTAMP)
                ->add_field("$this->tablealias.timecreated")
                ->add_callback([programuser_format::class, 'timecreated']);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['timemodified'])) {
            // Column timemodified.
            $newcolumn = (new report_column(
                'timemodified',
                new lang_string('timemodified', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_TIMESTAMP)
                ->add_field("$this->tablealias.timemodified")
                ->add_callback([programuser_format::class, 'timemodified']);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['associatedcertification'])) {
            // Column associated certification (if any).
            $newcolumn = (new report_column(
                'associatedcertification',
                new lang_string('associatedcertifications', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_is_sortable(true)
                ->set_type(constants::DB_TYPE_TEXT)
                ->add_field("$this->tablealias.certificationid")
                ->add_callback([programuser_format::class, 'certificationname']);
            $columns[] = $newcolumn;
        }

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
     * Filters/conditions for programs.
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
            new lang_string('allocationsource', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.allocationtype")
            ->set_options(self::get_allocation_sources());

        // Filter startdate.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'startdate',
            new lang_string('startdate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.startdate");

        // Filter duedate.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'duedate',
            new lang_string('duedate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.duedate");

        // Filter enddate.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'enddate',
            new lang_string('enddate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.enddate");

        // Filter suspended.
        $filters[] = (new report_filter(
            checkbox::class,
            'suspended',
            new lang_string('suspended', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.status");

        // Filter timesuspended.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timesuspended',
            new lang_string('timesuspended', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.timesuspended");

        // Filter timecreated.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timecreated',
            new lang_string('allocationdate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.timecreated");

        // Filter timemodified.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timemodified',
            new lang_string('timemodified', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.timemodified");

        // Filter by status.
        $filters[] = (new report_filter(
            select::class,
            'filterablestatus',
            new lang_string('programstatus', 'tool_program'),
            $this->get_entity_name(),
            api::get_status_sql_cases(0, $this->tablealias, $this->tablecompalias)
        ))
            ->add_join($this->join)
            ->set_options(api::get_program_statuses_fieldset());

        // TODO programprogress custom filter field sql needed.

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
            \tool_program\constants::ALLOCATION_MANUAL => get_string('manual', 'tool_program'),
            \tool_program\constants::ALLOCATION_DYNAMIC => get_string('dynamic', 'tool_program'),
            \tool_program\constants::ALLOCATION_CERTIFICATION => get_string('certification', 'tool_program')
        ];
    }
}
