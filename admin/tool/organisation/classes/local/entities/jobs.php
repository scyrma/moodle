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
 * File for class jobs.
 *
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_organisation\local\entities;

use lang_string;
use tool_organisation\organisation;
use tool_organisation\tool_reportbuilder\filter\job_department;
use tool_organisation\tool_reportbuilder\filter\job_position;
use tool_organisation\tool_reportbuilder\filter\job_time;
use tool_organisation\tool_reportbuilder\filter\position_permissions;
use tool_reportbuilder\constants;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\filter\checkbox;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\db;

defined('MOODLE_INTERNAL') || die();

/**
 * Columns, filters and conditions that defines the jobs entity and can be reused in any report datasource.
 *
 * @package   tool_organisation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jobs extends entity_base {

    /** @var string */
    protected $join = '';
    /** @var string */
    protected $tablealias = 'toj';
    /** @var array */
    protected $excludecolumns = [];

    /**
     * certification_entity constructor.
     *
     * @param string $join
     * @param string $tablealias
     * @param array $excludecolumns
     */
    public function __construct(string $join = '', string $tablealias = 'toj', array $excludecolumns = []) {
        $this->tablealias = $tablealias;
        $this->join = $join . $this->get_jobs_join();
        $this->excludecolumns = $excludecolumns;
    }

    /**
     * Additional join for jobs
     *
     * @return string
     */
    protected function get_jobs_join(): string {
        return " LEFT JOIN {tool_organisation_position} topos ON {$this->tablealias}.positionid = topos.id " .
            "LEFT JOIN {tool_organisation_department} tod ON {$this->tablealias}.departmentid = tod.id ";
    }

    /**
     * Entity name
     * @return string
     */
    public function get_entity_name(): string {
        return 'tool_organisation_jobs';
    }

    /**
     * Entity title
     * @return lang_string
     */
    public function get_entity_title(): lang_string {
        return new lang_string('jobs', 'tool_organisation');
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    public function get_columns(): array {
        global $CFG;

        $columns = [];

        // Column position.
        $newcolumn = (new report_column(
            'position',
            new lang_string('position', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_field('topos.name')
            ->add_callback([format::class, 'format_string']);
        $columns[] = $newcolumn;

        // Column department.
        $newcolumn = (new report_column(
            'department',
            new lang_string('department', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_field('tod.name')
            ->add_callback([format::class, 'format_string']);
        $columns[] = $newcolumn;

        // Column position department.
        [$sqlposdept, $paramsposdept] = db::sql_get_string('positionanddepartmentdisplay', 'tool_organisation',
            ['position' => 'topos.name', 'department' => 'tod.name']);
        $sqlposdeptgrp = db::sql_get_string('positionanddepartmentdisplay', 'tool_organisation',
            ['position' => 'topos.name', 'department' => 'tod.name'], true);

        $newcolumn = (new report_column(
            'positiondepartment',
            new lang_string('jobpositiondepartment', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_field($sqlposdept, 'positiondepartment', $paramsposdept)
            ->add_callback([format::class, 'format_string'])
            ->set_groupby_sql($sqlposdeptgrp);

        $columns[] = $newcolumn;

        // Column startdate.
        $newcolumn = (new report_column(
            'startdate',
            new lang_string('startdate', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->set_is_sortable(true)
            ->add_field("$this->tablealias.startdate")
            ->add_callback([\tool_organisation\local\helpers\format::class, 'jobdate']);
        $columns[] = $newcolumn;

        // Column enddate.
        $newcolumn = (new report_column(
            'enddate',
            new lang_string('enddate', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->set_is_sortable(true)
            ->add_field("$this->tablealias.enddate")
            ->add_callback([\tool_organisation\local\helpers\format::class, 'jobdate']);
        $columns[] = $newcolumn;

        // Column globalmanagementicons.
        $newcolumn = (new report_column(
            'globalmanagementicons',
            new lang_string('globalmanagementicons', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field('CASE WHEN topos.globalmanager = 1 THEN topos.globalpermissions ELSE NULL END', 'globalpermissions')
            ->add_callback([\tool_organisation\local\helpers\format::class, 'managementicons'], 'globalpermissions')
            ->add_aggregation_callback('groupconcat',
                [\tool_organisation\local\helpers\format::class, 'managementicons_group'], 'globalpermissions')
            ->add_aggregation_callback('groupconcatdistinct',
                [\tool_organisation\local\helpers\format::class, 'managementicons_group'], 'globalpermissions');
        $columns[] = $newcolumn;

        // Column departmentmanagementicons.
        $newcolumn = (new report_column(
            'departmentmanagementicons',
            new lang_string('departmentmanagementicons', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field('CASE WHEN topos.departmentmanager = 1 THEN topos.departmentpermissions ELSE NULL END',
                'departmentpermissions')
            ->add_callback([\tool_organisation\local\helpers\format::class, 'managementicons'], 'departmentpermissions')
            ->add_aggregation_callback('groupconcat',
                [\tool_organisation\local\helpers\format::class, 'managementicons_group'], 'departmentpermissions')
            ->add_aggregation_callback('groupconcatdistinct',
                [\tool_organisation\local\helpers\format::class, 'managementicons_group'], 'departmentpermissions');
        $columns[] = $newcolumn;

        // Column positionframework.
        $sql = self::generate_framework_query('topos');
        $newcolumn = (new report_column(
            'positionframework',
            new lang_string('positionframework', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->add_join(' LEFT JOIN {tool_organisation_position} topfram ON topfram.id = ' . $sql . ' ')
            ->add_field('topfram.name')
            ->add_callback([format::class, 'format_string'])
            ->set_type(constants::DB_TYPE_TEXT)
            ->set_is_sortable(true);
        $columns[] = $newcolumn;

        // Column departmentframework.
        $sql = self::generate_framework_query('tod');
        $newcolumn = (new report_column(
            'departmentframework',
            new lang_string('departmentframework', 'tool_organisation'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->add_join(' LEFT JOIN {tool_organisation_department} todfram ON todfram.id = ' . $sql . ' ')
            ->add_field('todfram.name')
            ->add_callback([format::class, 'format_string'])
            ->set_type(constants::DB_TYPE_TEXT)
            ->set_is_sortable(true);
        $columns[] = $newcolumn;

        return array_diff_key($columns, $this->excludecolumns);
    }

    /**
     * Returns all available conditions on jobs entity.
     *
     * @return report_filter[]
     */
    public function get_filters(): array {
        return $this->get_filters_or_conditions(false);
    }

    /**
     * Returns all available conditions on jobs entity.
     *
     * @return report_filter[]
     */
    public function get_conditions(): array {
        return $this->get_filters_or_conditions(true);
    }

    /**
     * Filters/conditions for jobs
     *
     * @param bool $iscondition
     * @return array
     */
    protected function get_filters_or_conditions(bool $iscondition): array {
        $filters = [];

        // Position select filter.
        $filters[] = (new report_filter(
            job_position::class,
            'position',
            new lang_string('position', 'tool_organisation'),
            'tool_organisation_jobs'
        ))
            ->add_join($this->join)
            ->set_field_sql('topos')
            ->set_options(organisation::get_all_positions_menu(
                ['' => get_string('anyposition', 'tool_organisation')]));

        // Department select filter.
        $filters[] = (new report_filter(
            job_department::class,
            'department',
            new lang_string('department', 'tool_organisation'),
            'tool_organisation_jobs'
        ))
            ->add_join($this->join)
            ->set_field_sql('tod')
            ->set_options(organisation::get_all_departments_menu(
                ['' => get_string('anydepartment', 'tool_organisation')]));

        // Start date filter.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'startdate',
            new lang_string('startdate', 'tool_organisation'),
            'tool_organisation_jobs'
        ))
            ->add_join($this->join)
            ->set_field_sql('toj.startdate');

        // End date filter.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'enddate',
            new lang_string('enddate', 'tool_organisation'),
            'tool_organisation_jobs'
        ))
            ->add_join($this->join)
            ->set_field_sql('toj.enddate');

        // Is manager filter.
        $filters[] = (new report_filter(
            checkbox::class,
            'manager',
            new lang_string('manager', 'role'),
            'tool_organisation_jobs'
        ))
            ->add_join($this->join)
            ->set_field_sql('(CASE WHEN (topos.globalmanager = 1 OR topos.departmentmanager = 1) THEN 1 ELSE 0 END)');

        // Is global manager filter.
        $filters[] = (new report_filter(
            checkbox::class,
            'globalmanager',
            new lang_string('globalmanager', 'tool_organisation'),
            'tool_organisation_jobs'
        ))
            ->add_join($this->join)
            ->set_field_sql('topos.globalmanager');

        // Is department manager filter.
        $filters[] = (new report_filter(
            checkbox::class,
            'departmentmanager',
            new lang_string('departmentmanager', 'tool_organisation'),
            'tool_organisation_jobs'
        ))
            ->add_join($this->join)
            ->set_field_sql('topos.departmentmanager');

        // Can view reports filter.
        $filters[] = (new report_filter(
            position_permissions::class,
            'canviewreports',
            new lang_string('conditioncanviewreports', 'tool_organisation'),
            'tool_organisation_jobs'
        ))
            ->add_join($this->join)
            ->set_field_sql('topos');

        // Can receive notifications filter.
        $filters[] = (new report_filter(
            position_permissions::class,
            'canreceivenotifications',
            new lang_string('conditioncanreceivenotifications', 'tool_organisation'),
            'tool_organisation_jobs'
        ))
            ->add_join($this->join)
            ->set_field_sql('topos');

        // Can allocate to programs filter.
        $filters[] = (new report_filter(
            position_permissions::class,
            'canallocateprograms',
            new lang_string('conditioncanallocateprograms', 'tool_organisation'),
            'tool_organisation_jobs'
        ))
            ->add_join($this->join)
            ->set_field_sql('topos');

        // Show jobs filter.
        $filters[] = (new report_filter(
            job_time::class,
            'showjobs',
            new lang_string('showjobs', 'tool_organisation'),
            'tool_organisation_jobs'
        ))
            ->add_join($this->join)
            ->set_field_sql('toj');

        return $filters;
    }

    /**
     * Generates query to get the position/department framework id from the path.
     *
     * Performs a substring from the PATH field. The start position on the substring is 2 to remove the first /
     * To calculate the end position it calculates the length of the substring starting on position 2 to the position of
     * The second / removing 3 characters from the beginning to avoid the incompatibility problems of using substract.
     * Eg. If path is /11/23/26 returns id 11.
     *
     * @param string $tablealias
     * @return string
     * @throws \coding_exception
     */
    private static function generate_framework_query(string $tablealias): string {
        global $DB;

        $endposition = $DB->sql_position("'/'", $DB->sql_substr("$tablealias.path", 3));
        $topid = $DB->sql_substr("$tablealias.path", 2, $endposition);
        $cast = $DB->sql_cast_char2int($topid);
        return "CASE WHEN $tablealias.id IS NULL THEN NULL ELSE $cast END";
    }
}
