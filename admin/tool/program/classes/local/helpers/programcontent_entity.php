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
 * File for program_content_entity class
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\local\helpers;

use lang_string;
use tool_reportbuilder\constants;
use tool_reportbuilder\db;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\helpers\columns;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;

defined('MOODLE_INTERNAL') || die();

/**
 * Columns, filters and conditions that defines the program_content_entity and can be reused in any report datasource
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class programcontent_entity extends entity_base {
    /** @var string */
    protected $join = '';
    /** @var string */
    protected $tablealias = 'tps';
    /** @var array */
    protected $excludecolumns = [];

    /**
     * program_fields constructor.
     *
     * @param string $join
     * @param string $tablealias
     * @param array $excludecolumns
     */
    public function __construct(string $join = '', string $tablealias = 'tps', array $excludecolumns = []) {
        $this->join = $join;
        $this->tablealias = $tablealias;
        $this->excludecolumns = array_combine($excludecolumns, $excludecolumns);
    }

    /**
     * Entity name
     *
     * @return string
     */
    public function get_entity_name(): string {
        return 'tool_program_content';
    }

    /**
     * Entity title
     *
     * @return lang_string
     */
    public function get_entity_title(): lang_string {
        return new lang_string('entityprogramcontent', 'tool_program');
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    public function get_columns(): array {
        global $DB;
        // Column setname.
        $newcolumn = (new report_column(
            'setname',
            new lang_string('programsetname', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$this->tablealias.name")
            ->add_callback([format::class, 'format_string']);
        $columns[] = $newcolumn;

        // Column parentsetname.
        $newcolumn = (new report_column(
            'parentsetname',
            new lang_string('programparentsetname', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("(SELECT name FROM {tool_program_sets} WHERE id = $this->tablealias.parent)", "parentsetname")
            ->set_groupby_sql("$this->tablealias.parent")
            ->add_callback([format::class, 'format_string']);
        if ($DB->get_dbfamily() === 'mssql') {
            columns::disable_column_aggregation($newcolumn);
        }
        $columns[] = $newcolumn;

        // Column completioncriteria.
        $newcolumn = (new report_column(
            'completioncriteria',
            new lang_string('completioncriteria', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$this->tablealias.completioncriteria")
            ->add_field("$this->tablealias.completionatleast")
            ->add_callback([programcontent_format::class, 'completioncriteria'])
            ->add_aggregation_callback('groupconcat', [programcontent_format::class, 'completioncriteria'])
            ->add_aggregation_callback('groupconcatdistinct', [programcontent_format::class, 'completioncriteria']);
        $columns[] = $newcolumn;

        // Column listcoursescommaseparated.
        $c = \tool_wp\db::generate_alias();
        $pc = \tool_wp\db::generate_alias();
        $groupconcatsql = db::sql_group_concat("$c.fullname");
        $sql = "(SELECT $groupconcatsql
        FROM {course} $c
        LEFT JOIN {tool_program_courses} $pc
        ON $c.id = $pc.courseid
        WHERE $pc.setid = $this->tablealias.id
        GROUP BY $pc.setid)";

        $newcolumn = (new report_column(
            'coursesinset',
            new lang_string('coursesinset', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field($sql, 'coursesinset')
            ->set_groupby_sql("$this->tablealias.id")
            ->add_callback([format::class, 'format_string']);
        columns::disable_column_aggregation($newcolumn);
        $columns[] = $newcolumn;

        // Column List of courses (one per line).
        $c2 = \tool_wp\db::generate_alias();
        $pc2 = \tool_wp\db::generate_alias();
        $groupconcatsql = db::sql_group_concat("$c2.fullname", '<br>');
        $sql = "(SELECT $groupconcatsql
        FROM {course} $c2
        LEFT JOIN {tool_program_courses} $pc2
        ON $c2.id = $pc2.courseid
        WHERE $pc2.setid = $this->tablealias.id
        GROUP BY $pc2.setid)";

        $newcolumn = (new report_column(
            'coursesinsetlineseparated',
            new lang_string('coursesinsetlines', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field($sql, 'coursesinset')
            ->set_groupby_sql("$this->tablealias.id")
            ->add_callback([format::class, 'format_string']);
        columns::disable_column_aggregation($newcolumn);
        $columns[] = $newcolumn;

        // List of courses with links (one per line).
        $c3 = \tool_wp\db::generate_alias();
        $pc3 = \tool_wp\db::generate_alias();

        $string = \html_writer::span('{{fullname}}', '', ['data-id' => '{{id}}']);
        $stringparams = ['{{id}}' => $c3 . '.id', '{{fullname}}' => $c3 . '.fullname'];
        [$placeholdersql, $placeholderparams] = db::sql_string_with_placeholders($string, $stringparams);
        $groupconcat = db::sql_group_concat($placeholdersql, '<br>');

        $sql = "(SELECT $groupconcat
        FROM {course} $c3
        LEFT JOIN {tool_program_courses} $pc3
        ON $c3.id = $pc3.courseid
        WHERE $pc3.setid = $this->tablealias.id
        GROUP BY $pc3.setid)";

        $newcolumn = (new report_column(
            'coursesinsetlineseparatedlinks',
            new lang_string('coursesinsetlineslinks', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field($sql, 'coursesinsetlineseparatedlinks', $placeholderparams)
            ->set_groupby_sql($this->tablealias . '.id')
            ->add_callback([programcontent_format::class, 'textwithlink']);
        columns::disable_column_aggregation($newcolumn);
        $columns[] = $newcolumn;

        // List of courses with links (comma separated).
        $c4 = \tool_wp\db::generate_alias();
        $pc4 = \tool_wp\db::generate_alias();

        $string = \html_writer::span('{{fullname}}', '', ['data-id' => '{{id}}']);
        $stringparams = ['{{id}}' => $c4 . '.id', '{{fullname}}' => $c4 . '.fullname'];
        [$placeholdersql, $placeholderparams] = db::sql_string_with_placeholders($string, $stringparams);
        $groupconcat = db::sql_group_concat($placeholdersql);

        $sql = "(SELECT $groupconcat
        FROM {course} $c4
        LEFT JOIN {tool_program_courses} $pc4
        ON $c4.id = $pc4.courseid
        WHERE $pc4.setid = $this->tablealias.id
        GROUP BY $pc4.setid)";

        $newcolumn = (new report_column(
            'coursesinsetcommaseparatedlinks',
            new lang_string('coursesinsetlinks', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field($sql, 'coursesinsetcommaseparatedlinks', $placeholderparams)
            ->set_groupby_sql($this->tablealias . '.id')
            ->add_callback([programcontent_format::class, 'textwithlink']);
        columns::disable_column_aggregation($newcolumn);
        $columns[] = $newcolumn;

        // Column numbercoursesinset.
        $newcolumn = (new report_column(
            'numbercoursesinset',
            new lang_string('numbercoursesinset', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_NUMBER)
            ->add_field("(SELECT COUNT(id) FROM {tool_program_courses} WHERE setid = $this->tablealias.id)", 'numbercoursesinset')
            ->set_groupby_sql("$this->tablealias.id");
        if ($DB->get_dbfamily() === 'mssql') {
            columns::disable_column_aggregation($newcolumn);
        }
        $columns[] = $newcolumn;

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

        return $filters;
    }
}