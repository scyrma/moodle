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

declare(strict_types=1);

namespace tool_program\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use html_writer;
use lang_string;
use moodle_url;
use stdClass;
use tool_program\reportbuilder\local\formatters\program as program_formatter;
use tool_program\reportbuilder\local\formatters\program_content as program_content_formatter;

/**
 * Program content entity class implementation
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_content extends base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['tool_program_set' => 'tps', 'tool_program_course' => 'tpc'];
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityprogramcontent', 'tool_program');
    }

    /**
     * Initialise the entity
     *
     * @return base
     */
    public function initialise(): base {

        foreach ($this->get_all_columns() as $column) {
            $this->add_column($column);
        }

        foreach ($this->get_all_filters() as $filter) {
            $this
                ->add_filter($filter)
                ->add_condition($filter);
        }

        return $this;
    }

    /**
     * Returns list of all available columns
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        global $DB;
        $columns = [];
        $tablealias = $this->get_table_alias('tool_program_set');

        // Set name column.
        $columns[] = (new column(
            'setname',
            new lang_string('programsetname', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$tablealias}.name, {$tablealias}.parent")
            ->set_is_sortable(true)
            ->add_callback([program_formatter::class, 'formatstring'])
            ->add_callback(static function(string $value, stdClass $row) {
                if (isset($row->parent) && (int)$row->parent === 0) {
                    return get_string('baseset', 'tool_program');
                }
                return $value;
            });

        // Parent set name column.
        $programset = database::generate_alias();
        $columns[] = (new column(
            'parentsetname',
            new lang_string('programparentsetname', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_join("LEFT JOIN {tool_program_sets} {$programset} ON {$programset}.id = {$tablealias}.parent")
            ->add_field("{$programset}.name", 'parentsetname')
            ->add_field("{$programset}.parent", 'parent')
            ->set_is_sortable(true)
            ->add_callback([program_formatter::class, 'formatstring'])
            ->add_callback(static function(string $value, stdClass $row) {
                // Show 'Base set' when is the base set (Base set don't have names).
                // This entity can be left joined in the datasource so we need to check if parent is set. In the case it would be
                // null it would be casted to 0 and show a wrong value.
                if (isset($row->parent) && (int)$row->parent === 0) {
                    return get_string('baseset', 'tool_program');
                }
                return $value;
            });

        // Completion criteria column.
        $columns[] = (new column(
            'completioncriteria',
            new lang_string('completioncriteria', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$tablealias}.completioncriteria, {$tablealias}.completionatleast")
            ->set_is_sortable(true)
            ->add_callback([program_content_formatter::class, 'completion_criteria']);

        // List courses comma separated column.
        $course = database::generate_alias();
        $programcourse = database::generate_alias();
        $groupconcatsql = $DB->sql_group_concat("{$course}.fullname");
        $sql = "
              (SELECT {$groupconcatsql}
                 FROM {course} {$course}
                 JOIN {tool_program_courses} {$programcourse}
                   ON {$course}.id = {$programcourse}.courseid
                WHERE {$programcourse}.setid = {$tablealias}.id
             GROUP BY {$programcourse}.setid)
        ";

        $columns[] = (new column(
            'coursesinset',
            new lang_string('coursesinset', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field($sql, 'coursesinset')
            ->set_groupby_sql("{$tablealias}.id")
            ->set_is_sortable(true)
            ->add_callback([program_formatter::class, 'formatstring'])
            ->set_disabled_aggregation_all();

        // List courses (one per line) column.
        $course = database::generate_alias();
        $programcourse = database::generate_alias();
        $groupconcatsql = $DB->sql_group_concat("{$course}.fullname", '<br>');
        $sql = "
              (SELECT {$groupconcatsql}
                 FROM {course} {$course}
            LEFT JOIN {tool_program_courses} {$programcourse}
                   ON {$course}.id = {$programcourse}.courseid
                WHERE {$programcourse}.setid = {$tablealias}.id
             GROUP BY {$programcourse}.setid)
        ";

        $columns[] = (new column(
            'coursesinsetlineseparated',
            new lang_string('coursesinsetlines', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field($sql, 'coursesinset')
            ->set_groupby_sql("{$tablealias}.id")
            ->set_is_sortable(true)
            ->add_callback([program_formatter::class, 'formatstring'])
            ->set_disabled_aggregation_all();

        $datacolumns = [
            // Coursesinsetcommaseparatedlinks (comma separated) column.
            ['name' => 'coursesinsetcommaseparatedlinks', 'string' => 'coursesinsetlinks', 'separator' => ', '],
            // Coursesinsetlineseparatedlinks (one per line) column.
            ['name' => 'coursesinsetlineseparatedlinks', 'string' => 'coursesinsetlineslinks', 'separator' => '<br>'],
        ];
        foreach ($datacolumns as $datacolumn) {
            $course = database::generate_alias();
            $programcourse = database::generate_alias();
            $string = html_writer::span('{{fullname}}', '', ['data-id' => '{{id}}']);
            $stringparams = ['{{id}}' => $course . '.id', '{{fullname}}' => $course . '.fullname'];
            [$placeholdersql, $placeholderparams] = \tool_reportbuilder\db::sql_string_with_placeholders($string, $stringparams);
            $groupconcatsql = $DB->sql_group_concat($placeholdersql, $datacolumn['separator'], "{$course}.fullname");
            $sql = "(SELECT $groupconcatsql
            FROM {course} $course
            LEFT JOIN {tool_program_courses} $programcourse
            ON $course.id = $programcourse.courseid
            WHERE $programcourse.setid = {$tablealias}.id
            GROUP BY $programcourse.setid)";

            $column = (new column(
                $datacolumn['name'],
                new lang_string($datacolumn['string'], 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->set_type(column::TYPE_TEXT)
                ->add_field($sql, 'coursesinsetlineseparatedlinks', $placeholderparams)
                ->add_callback(static function(?string $value, stdClass $row) {
                    $regex = '#<span data-id="(?<id>[^"]*?)">(?<fullname>[^<]*?)</span>#';

                    return preg_replace_callback($regex, function($matches) {
                        $url = new moodle_url('/course/view.php', ['id' => $matches['id']]);
                        return html_writer::link($url, format_string($matches['fullname']));
                    }, (string) $value);
                })
                ->set_disabled_aggregation_all()
                ->set_groupby_sql("{$tablealias}.id");
            $columns[] = $column;
        }

        // Number courses in set column.
        $sql = "(SELECT COUNT(id) FROM {tool_program_courses} WHERE setid = {$tablealias}.id)";
        $column = (new column(
            'numbercoursesinset',
            new lang_string('numbercoursesinset', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field($sql, 'numbercoursesinset')
            ->set_groupby_sql("{$tablealias}.id")
            ->set_is_sortable(true);
        if ($DB->get_dbfamily() === 'mssql') {
            $column->set_disabled_aggregation_all();
        }
        $columns[] = $column;

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        return [];
    }
}
