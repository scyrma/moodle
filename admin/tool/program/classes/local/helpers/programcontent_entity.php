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

use core_reportbuilder\local\helpers\database;
use lang_string;
use tool_reportbuilder\constants;
use tool_reportbuilder\db;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\helpers\columns;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;

/**
 * Columns, filters and conditions that defines the program_content_entity and can be reused in any report datasource
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class programcontent_entity extends entity_base {

    /**
     * Which entity class from core reportbuilder should this entity be migrated to
     *
     * @return string name of the class extending {@see \core_reportbuilder\local\entities\entity_base}
     */
    public function convert_get_entity_class(): string {
        return \tool_program\reportbuilder\local\entities\program_content::class;
    }

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['tool_program_set' => 'tps'];
    }

    /**
     * The default machine-readable name for this entity that will be used in the internal names of the columns/filters
     *
     * @return string
     */
    protected function get_default_entity_name(): string {
        return 'tool_program_content';
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return \lang_string
     */
    protected function get_default_entity_title(): \lang_string {
        return new \lang_string('entityprogramcontent', 'tool_program');
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
        global $DB;
        $columns = [];
        $tablealias = $this->get_table_alias('tool_program_set');

        if (!isset($this->excludecolumns['setname'])) {
            // Column setname.
            $newcolumn = (new report_column(
                'setname',
                new lang_string('programsetname', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->set_type(constants::DB_TYPE_TEXT)
                ->add_field("$tablealias.name")
                ->add_callback([format::class, 'format_string']);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['parentsetname'])) {
            // Column parentsetname.
            $newcolumn = (new report_column(
                'parentsetname',
                new lang_string('programparentsetname', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->set_type(constants::DB_TYPE_TEXT)
                ->add_field("(SELECT name FROM {tool_program_sets} WHERE id = $tablealias.parent)", 'parentsetname')
                ->set_groupby_sql("$tablealias.parent")
                ->add_callback([format::class, 'format_string']);
            if ($DB->get_dbfamily() === 'mssql') {
                columns::disable_column_aggregation($newcolumn);
            }
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['completioncriteria'])) {
            // Column completioncriteria.
            $newcolumn = (new report_column(
                'completioncriteria',
                new lang_string('completioncriteria', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->set_type(constants::DB_TYPE_TEXT)
                ->add_field("$tablealias.completioncriteria")
                ->add_field("$tablealias.completionatleast")
                ->add_callback([programcontent_format::class, 'completioncriteria'])
                ->add_aggregation_callback('groupconcat', [programcontent_format::class, 'completioncriteria'])
                ->add_aggregation_callback('groupconcatdistinct', [programcontent_format::class, 'completioncriteria']);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['coursesinset'])) {
            // Column listcoursescommaseparated.
            $c = database::generate_alias();
            $pc = database::generate_alias();
            $groupconcatsql = db::sql_group_concat("$c.fullname");
            $sql = "(SELECT $groupconcatsql
            FROM {course} $c
            LEFT JOIN {tool_program_courses} $pc
            ON $c.id = $pc.courseid
            WHERE $pc.setid = $tablealias.id
            GROUP BY $pc.setid)";

            $newcolumn = (new report_column(
                'coursesinset',
                new lang_string('coursesinset', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->set_type(constants::DB_TYPE_TEXT)
                ->add_field($sql, 'coursesinset')
                ->set_groupby_sql("$tablealias.id")
                ->add_callback([format::class, 'format_string']);
            columns::disable_column_aggregation($newcolumn);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['coursesinsetlineseparated'])) {
            // Column List of courses (one per line).
            $c2 = database::generate_alias();
            $pc2 = database::generate_alias();
            $groupconcatsql = db::sql_group_concat("$c2.fullname", '<br>');
            $sql = "(SELECT $groupconcatsql
            FROM {course} $c2
            LEFT JOIN {tool_program_courses} $pc2
            ON $c2.id = $pc2.courseid
            WHERE $pc2.setid = $tablealias.id
            GROUP BY $pc2.setid)";

            $newcolumn = (new report_column(
                'coursesinsetlineseparated',
                new lang_string('coursesinsetlines', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->set_type(constants::DB_TYPE_TEXT)
                ->add_field($sql, 'coursesinset')
                ->set_groupby_sql("$tablealias.id")
                ->add_callback([format::class, 'format_string']);
            columns::disable_column_aggregation($newcolumn);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['coursesinsetlineseparatedlinks'])) {
            // List of courses with links (one per line).
            $c3 = database::generate_alias();
            $pc3 = database::generate_alias();

            $string = \html_writer::span('{{fullname}}', '', ['data-id' => '{{id}}']);
            $stringparams = ['{{id}}' => $c3 . '.id', '{{fullname}}' => $c3 . '.fullname'];
            [$placeholdersql, $placeholderparams] = db::sql_string_with_placeholders($string, $stringparams);
            $groupconcat = db::sql_group_concat($placeholdersql, '<br>', "{$c3}.fullname");

            $sql = "(SELECT $groupconcat
            FROM {course} $c3
            LEFT JOIN {tool_program_courses} $pc3
            ON $c3.id = $pc3.courseid
            WHERE $pc3.setid = $tablealias.id
            GROUP BY $pc3.setid)";

            $newcolumn = (new report_column(
                'coursesinsetlineseparatedlinks',
                new lang_string('coursesinsetlineslinks', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->set_type(constants::DB_TYPE_TEXT)
                ->add_field($sql, 'coursesinsetlineseparatedlinks', $placeholderparams)
                ->set_groupby_sql($tablealias . '.id')
                ->add_callback([programcontent_format::class, 'textwithlink']);
            columns::disable_column_aggregation($newcolumn);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['coursesinsetcommaseparatedlinks'])) {
            // List of courses with links (comma separated).
            $c4 = database::generate_alias();
            $pc4 = database::generate_alias();

            $string = \html_writer::span('{{fullname}}', '', ['data-id' => '{{id}}']);
            $stringparams = ['{{id}}' => $c4 . '.id', '{{fullname}}' => $c4 . '.fullname'];
            [$placeholdersql, $placeholderparams] = db::sql_string_with_placeholders($string, $stringparams);
            $groupconcat = db::sql_group_concat($placeholdersql, null, "{$c4}.fullname");

            $sql = "(SELECT $groupconcat
            FROM {course} $c4
            LEFT JOIN {tool_program_courses} $pc4
            ON $c4.id = $pc4.courseid
            WHERE $pc4.setid = $tablealias.id
            GROUP BY $pc4.setid)";

            $newcolumn = (new report_column(
                'coursesinsetcommaseparatedlinks',
                new lang_string('coursesinsetlinks', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->set_type(constants::DB_TYPE_TEXT)
                ->add_field($sql, 'coursesinsetcommaseparatedlinks', $placeholderparams)
                ->set_groupby_sql($tablealias . '.id')
                ->add_callback([programcontent_format::class, 'textwithlink']);
            columns::disable_column_aggregation($newcolumn);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['numbercoursesinset'])) {
            // Column numbercoursesinset.
            $sql = "(SELECT COUNT(id) FROM {tool_program_courses} WHERE setid = $tablealias.id)";
            $newcolumn = (new report_column(
                'numbercoursesinset',
                new lang_string('numbercoursesinset', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->set_type(constants::DB_TYPE_NUMBER)
                ->add_field($sql, 'numbercoursesinset')
                ->set_groupby_sql("$tablealias.id");
            if ($DB->get_dbfamily() === 'mssql') {
                columns::disable_column_aggregation($newcolumn);
            }
            $columns[] = $newcolumn;
        }

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

        return $filters;
    }
}
