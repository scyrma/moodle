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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class course_reset_entity
 *
 * @package   tool_wp
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\helpers;

use lang_string;
use tool_certification\api;
use tool_reportbuilder\constants;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\filter\checkbox;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\local\helpers\columns as column_helper;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;
use tool_wp\db;

defined('MOODLE_INTERNAL') || die();

/**
 * Columns, filters and conditions that defines the course_reset_entity and can be reused in any report datasource
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_reset_entity extends entity_base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['tool_wp_course_reset' => 'twpcr'];
    }

    /**
     * The default machine-readable name for this entity that will be used in the internal names of the columns/filters
     *
     * @return string
     */
    protected function get_default_entity_name(): string {
        return 'tool_wp_course_reset';
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return \lang_string
     */
    protected function get_default_entity_title(): \lang_string {
        return new lang_string('entitycoursereset', 'tool_wp');
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
        $tablealias = $this->get_table_alias('tool_wp_course_reset');

        // Column reason for course reset.
        $newcolumn = (new report_column(
            'reason',
            new lang_string('reason', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.reason", 'reason')
            ->add_callback([format::class, 'format_string']);
        $columns[] = $newcolumn;

        // Column time requested.
        $newcolumn = (new report_column(
            'timerequested',
            new lang_string('timerequested', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.timerequested", 'timerequested')
            ->add_callback([format::class, 'userdate']);
        $columns[] = $newcolumn;

        // Column user requested.
        $viewfullnames = has_capability('moodle/site:viewfullnames', \context_system::instance());
        $ureqtable = db::generate_alias();
        $userrequestedjoin = "LEFT JOIN {user} {$ureqtable} " .
            "ON {$tablealias}.userid = {$ureqtable}.id AND {$ureqtable}.deleted = 0";
        [$sql, $params] = \tool_reportbuilder\db::sql_fullname($ureqtable, $viewfullnames);
        $newcolumn = (new report_column(
            'userrequested',
            new lang_string('userrequested', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($userrequestedjoin)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field($sql, 'fullname', $params)
            ->add_aggregation_fields('count', $tablealias . '.userrequested')
            ->set_groupby_sql(\tool_reportbuilder\db::sql_fullname($ureqtable, $viewfullnames, true));
        $columns[] = $newcolumn;

        // Column was completed.
        $newcolumn = (new report_column(
            'wascompleted',
            new lang_string('wascompleted', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->add_field("$tablealias.wascompleted", 'wascompleted')
            ->add_callback([format::class, 'checkbox_as_text']);
        $columns[] = $newcolumn;

        // Column grade.
        $newcolumn = (new report_column(
            'grade',
            new lang_string('grade', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_NUMBER)
            ->add_field("$tablealias.grade", 'grade')
            ->add_callback([format::class, 'format_string']);
        $columns[] = $newcolumn;

        // Column resetstatus.
        $newcolumn = (new report_column(
            'resetstatus',
            new lang_string('resetstatus', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_NUMBER)
            ->add_field("$tablealias.resetstatus", 'resetstatus')
            ->add_callback([course_reset_format::class, 'reset_status']);
        column_helper::disable_column_aggregation($newcolumn);
        $columns[] = $newcolumn;

        // Column resetinfo.
        $newcolumn = (new report_column(
            'resetinfo',
            new lang_string('resetinfo', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_LONGTEXT)
            ->add_field("$tablealias.resetinfo", 'resetinfo')
            ->add_callback([course_reset_format::class, 'reset_info'])
            ->disable_aggregation('count')
            ->disable_aggregation('countdistinct')
            ->disable_aggregation('groupconcat')
            ->disable_aggregation('groupconcatdistinct');
        $columns[] = $newcolumn;

        // Column time reset.
        $newcolumn = (new report_column(
            'timereseted',
            new lang_string('timereseted', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.timecreated", 'timereseted')
            ->add_callback([format::class, 'userdate']);
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
        $tablealias = $this->get_table_alias('tool_wp_course_reset');

        // Filter for timereseted.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timereseted',
            new lang_string('timereseted', 'tool_wp'),
            $this->get_entity_name(),
            "$tablealias.timecreated"
        ))
            ->add_joins($this->get_joins());

        // Filter Was completed.
        $filters[] = (new report_filter(
            checkbox::class,
            'wascompleted',
            new lang_string('wascompleted', 'tool_wp'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("(CASE WHEN ({$tablealias}.wascompleted > 0) THEN 1 ELSE 0 END)");

        return $filters;
    }
}
