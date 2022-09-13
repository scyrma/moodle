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
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\custom_fields;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_tag_tag;
use html_writer;
use lang_string;
use moodle_url;
use stdClass;
use tool_program\api;
use tool_program\reportbuilder\local\filters\associated_certification;
use tool_program\reportbuilder\local\filters\contains_course;
use tool_program\reportbuilder\local\formatters\program as program_formatter;
use tool_tenant\reportbuilder\local\filters\tenant;

/**
 * Program entity class implementation
 *
 * @package     tool_program
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program extends base {

    /** @var custom_fields */
    protected $customfields;

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['tool_program' => 'tp', 'tool_tenant' => 'tt'];
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityprogram', 'tool_program');
    }

    /**
     * Initialise the entity
     *
     * @return base
     */
    public function initialise(): base {
        $columns = array_merge($this->get_all_columns(), $this->get_custom_fields()->get_columns());
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        // All the filters defined by the entity can also be used as conditions.
        $filters = array_merge($this->get_all_filters(), $this->get_custom_fields()->get_filters());
        foreach ($filters as $filter) {
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

        $tablealias = $this->get_table_alias('tool_program');
        $ismssql = $DB->get_dbfamily() === 'mssql';
        $dateformat = get_string('strftimedatefullshort', 'core_langconfig');

        // Fullname column.
        $columns[] = (new column(
            'fullname',
            new lang_string('programname', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.fullname")
            ->set_is_sortable(true)
            ->add_callback([program_formatter::class, 'formatstring']);

        // Fullname with image column.
        $columns[] = (new column(
            'fullnamewithimage',
            new lang_string('programnamewithimage', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$tablealias}.fullname, {$tablealias}.id")
            ->set_is_sortable(true)
            ->add_callback([program_formatter::class, 'fullname_with_image']);

        // Fullname with link column.
        $columns[] = (new column(
            'fullnamewithlink',
            new lang_string('programnamewithlink', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$tablealias}.fullname, {$tablealias}.id")
            ->set_is_sortable(true)
            ->add_callback([program_formatter::class, 'fullname_with_link']);

        // Fullname with image and link column.
        $columns[] = (new column(
            'fullnamewithimageandlink',
            new lang_string('programnamewithimageandlink', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$tablealias}.fullname, {$tablealias}.id")
            ->set_is_sortable(true)
            ->add_callback([program_formatter::class, 'fullname_with_image_and_link']);

        // Program image column.
        $columns[] = (new column(
            'programimage',
            new lang_string('programimage', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.id")
            ->set_is_sortable(true)
            ->add_callback([program_formatter::class, 'program_image']);

        // Tags column (pre-aggregated column returning field within a sub-select). TODO: re-factor after MDL-73842.
        $taginstancealias = database::generate_alias();
        $tagalias = database::generate_alias();

        $tagconcatsql = $DB->sql_group_concat(
            $DB->sql_concat_join("'|'", ["{$tagalias}.name", "{$tagalias}.rawname"]), ':', "{$tagalias}.rawname");

        $column = (new column(
            'tags',
            new lang_string('tags', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("(
                SELECT {$tagconcatsql}
                  FROM {tag_instance} {$taginstancealias}
                  JOIN {tag} {$tagalias} ON {$tagalias}.id = {$taginstancealias}.tagid
                 WHERE {$taginstancealias}.component = 'tool_program'
                   AND {$taginstancealias}.itemtype = 'tool_program'
                   AND {$taginstancealias}.itemid = {$tablealias}.id
            )", 'tags')
            ->set_groupby_sql("{$tablealias}.id")
            ->set_is_sortable(true)
            ->add_callback(static function(?string $value): string {
                $tagsdata = preg_split('/:/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);

                return implode('', array_map(static function(string $tagdata): string {
                    [
                        $tag['name'],
                        $tag['rawname']
                    ] = explode('|', $tagdata);

                    return html_writer::span(core_tag_tag::make_display_name((object) $tag),
                        'border p-1 text-uppercase font-small my-2 mr-2');
                }, $tagsdata));
            });
        if ($ismssql) {
            // MsSQL can not aggregate the columns with subquery.
            $column->set_disabled_aggregation(['groupconcat', 'groupconcatdistinct']);
        }
        $columns[] = $column;

        // IDnumber column.
        $columns[] = (new column(
            'idnumber',
            new lang_string('idnumber', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.idnumber")
            ->set_is_sortable(true)
            ->add_callback([program_formatter::class, 'formatstring']);

        // Description column.
        $columns[] = (new column(
            'description',
            new lang_string('description', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_LONGTEXT)
            ->add_fields("{$tablealias}.description, {$tablealias}.descriptionformat, {$tablealias}.id")
            ->set_is_sortable(true)
            ->add_callback([program_formatter::class, 'description']);

        // Start date column.
        $columns[] = (new column(
            'startdate',
            new lang_string('startdate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$tablealias}.startdatetype, {$tablealias}.startdateabsolute, {$tablealias}.startdaterelative")
            ->set_is_sortable(true, ['startdatetype', 'startdateabsolute', 'startdaterelative'])
            ->set_disabled_aggregation(['groupconcat', 'groupconcatdistinct'])
            ->add_callback([program_formatter::class, 'startdate']);

        // Due date column.
        $columns[] = (new column(
            'duedate',
            new lang_string('duedate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.duedatetype")
            ->add_field("{$tablealias}.duedateabsolute")
            ->add_field("{$tablealias}.duedaterelative")
            ->add_field("{$tablealias}.startdatetype")
            ->add_field("{$tablealias}.startdateabsolute")
            ->add_field("{$tablealias}.enddatetype")
            ->add_field("{$tablealias}.enddateabsolute")
            ->set_is_sortable(true, ['duedatetype', 'duedateabsolute', 'duedaterelative'])
            ->set_disabled_aggregation(['groupconcat', 'groupconcatdistinct'])
            ->add_callback([program_formatter::class, 'duedate']);

        // End date column.
        $columns[] = (new column(
            'enddate',
            new lang_string('enddate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$tablealias}.enddatetype, {$tablealias}.enddateabsolute, {$tablealias}.enddaterelative")
            ->set_is_sortable(true, ['enddatetype', 'enddateabsolute', 'enddaterelative'])
            ->set_disabled_aggregation(['groupconcat', 'groupconcatdistinct'])
            ->add_callback([program_formatter::class, 'enddate']);

        // Archived column.
        $columns[] = (new column(
            'archived',
            new lang_string('archived', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$tablealias}.archived")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'boolean_as_text']);

        // Timearchived column.
        $columns[] = (new column(
            'timearchived',
            new lang_string('archivedon', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.timearchived")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate'], $dateformat);

        // Allowdirectallocation column.
        $columns[] = (new column(
            'allowdirectallocation',
            new lang_string('allowdirectallocation', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$tablealias}.allowdirectallocation")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'boolean_as_text']);

        // Allocationstartdate column.
        $columns[] = (new column(
            'allocationstartdate',
            new lang_string('allocationstartdate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_fields("{$tablealias}.allocationstartdatetype, {$tablealias}.allocationstartdateabsolute")
            ->set_is_sortable(true, ['allocationstartdatetype', 'allocationstartdateabsolute'])
            ->add_callback([program_formatter::class, 'allocation_startdate']);

        // Allocationenddate column.
        $columns[] = (new column(
            'allocationenddate',
            new lang_string('allocationenddate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.allocationenddateabsolute")
            ->add_field("{$tablealias}.allocationstartdateabsolute")
            ->add_field("{$tablealias}.allocationstartdatetype")
            ->add_field("{$tablealias}.allocationenddaterelative")
            ->add_field("{$tablealias}.allocationenddatetype")
            ->set_is_sortable(true, ['allocationenddatetype', 'allocationenddateabsolute', 'allocationenddaterelative'])
            ->add_callback([program_formatter::class, 'allocation_enddate']);

        // Visible column.
        $columns[] = (new column(
            'visible',
            new lang_string('visible', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$tablealias}.visible")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'boolean_as_text']);

        // Timemodified column.
        $columns[] = (new column(
            'timemodified',
            new lang_string('timemodified', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.timemodified")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate'], $dateformat);

        // Timecreated column.
        $columns[] = (new column(
            'timecreated',
            new lang_string('timecreated', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.timecreated")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate'], $dateformat);

        // Numbercoursesunique column.
        $programcourse = database::generate_alias();
        $programset = database::generate_alias();
        $program = database::generate_alias();
        $sql = "
              (SELECT COUNT(DISTINCT({$programcourse}.courseid))
                 FROM {tool_program_courses} {$programcourse}
                 JOIN {tool_program_sets} {$programset}
                   ON {$programset}.id = {$programcourse}.setid
                 JOIN {tool_program} {$program}
                   ON {$program}.id = {$programset}.programid
                WHERE {$programset}.programid = {$tablealias}.id)";

        $column = (new column(
            'numbercoursesunique',
            new lang_string('numbercoursesinprogramunique', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field($sql, 'numbercoursesunique')
            ->set_is_sortable(true)
            ->set_groupby_sql("{$tablealias}.id");
        if ($ismssql) {
            $column->set_disabled_aggregation_all();
        }
        $columns[] = $column;

        // Associatedcertifications column.
        $certification = database::generate_alias();
        $program = database::generate_alias();
        $groupconcatsql = $DB->sql_group_concat("{$certification}.fullname");
        $sql = "
              (SELECT {$groupconcatsql}
                 FROM {tool_certification} {$certification}
                 JOIN {tool_program} {$program}
                   ON {$certification}.program = {$program}.id
                WHERE {$program}.id = {$tablealias}.id
             GROUP BY {$program}.id)
        ";

        $column = (new column(
            'associatedcertifications',
            new lang_string('associatedcertifications', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field($sql, 'associatedcertifications')
            ->set_is_sortable(true)
            ->add_callback([program_formatter::class, 'formatstring'])
            ->set_groupby_sql("{$tablealias}.id");
        if ($ismssql) {
            $column->set_disabled_aggregation_all();
        }
        $columns[] = $column;

        // Associatedcertificationswithlink column.
        $certification = database::generate_alias();
        $program = database::generate_alias();
        $string = html_writer::span('{{fullname}}', '', ['data-id' => '{{id}}']);
        $stringparams = ['{{id}}' => $certification . '.id', '{{fullname}}' => $certification . '.fullname'];
        [$placeholdersql, $placeholderparams] = \tool_reportbuilder\db::sql_string_with_placeholders($string, $stringparams);
        $groupconcatsql = $DB->sql_group_concat($placeholdersql, ', ', "{$certification}.fullname");
        $sql = "(SELECT $groupconcatsql
            FROM {tool_certification} $certification
            LEFT JOIN {tool_program} $program
            ON $certification.program = $program.id
            WHERE $program.id = {$tablealias}.id
            GROUP BY $program.id)";

        $column = (new column(
            'associatedcertificationswithlink',
            new lang_string('associatedcertificationswithlinks', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field($sql, 'associatedcertifications', $placeholderparams)
            ->add_callback(static function(?string $value, stdClass $row) {
                $regex = '#<span data-id="(?<id>[^"]*?)">(?<fullname>[^<]*?)</span>#';

                return preg_replace_callback($regex, function($matches) {
                    $url = new moodle_url('/admin/tool/certification/edit.php', ['id' => $matches['id']]);
                    return html_writer::link($url, format_string($matches['fullname']));
                }, $value);
            })
            ->set_groupby_sql("{$tablealias}.id");
        if ($ismssql) {
            $column->set_disabled_aggregation_all();
        }
        $columns[] = $column;

        // Numbercurrentallocatedusers column.
        $program = database::generate_alias();
        $programuser = database::generate_alias();
        $sql = "
              (SELECT COUNT(DISTINCT({$programuser}.userid))
                 FROM {tool_program_users} {$programuser}
                 JOIN {tool_program} {$program}
                   ON {$programuser}.programid = {$program}.id
                WHERE {$program}.id = {$tablealias}.id
             GROUP BY {$program}.id)
        ";

        $column = (new column(
            'numbercurrentallocatedusers',
            new lang_string('numbercurrentallocatedusers', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field($sql, 'numbercurrentallocatedusers')
            ->set_is_sortable(true)
            ->add_callback([program_formatter::class, 'formatstring'])
            ->set_groupby_sql("{$tablealias}.id");
        if ($ismssql) {
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
        $filters = [];
        $tablealias = $this->get_table_alias('tool_program');

        // Program selector filter.
        $filters[] = (new filter(
            select::class,
            'programselector',
            new lang_string('programs', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.id"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback([api::class, 'get_programs_in_tenant_fieldset']);

        // Fullname filter.
        $filters[] = (new filter(
            text::class,
            'fullname',
            new lang_string('programname', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.fullname"
        ))
            ->add_joins($this->get_joins());

        // IDnumber filter.
        $filters[] = (new filter(
            text::class,
            'idnumber',
            new lang_string('idnumber', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.idnumber"
        ))
            ->add_joins($this->get_joins());

        // TODO WP-3147 WP-3702 Tags filter. Is not currently in core_reportbuilder.

        // Archived filter.
        $filters[] = (new filter(
            boolean_select::class,
            'archived',
            new lang_string('archived', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.archived"
        ))
            ->add_joins($this->get_joins());

        // Timearchived filter.
        $filters[] = (new filter(
            date::class,
            'timearchived',
            new lang_string('archivedon', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.timearchived"
        ))
            ->set_limited_operators([
                date::DATE_ANY,
                date::DATE_NOT_EMPTY,
                date::DATE_EMPTY,
                date::DATE_RANGE,
                date::DATE_LAST,
                date::DATE_CURRENT,
            ])
            ->add_joins($this->get_joins());

        // Allowdirectallocation filter.
        $filters[] = (new filter(
            boolean_select::class,
            'allowdirectallocation',
            new lang_string('allowdirectallocation', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.allowdirectallocation"
        ))
            ->add_joins($this->get_joins());

        // Visible filter.
        $filters[] = (new filter(
            boolean_select::class,
            'visible',
            new lang_string('visible', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.visible"
        ))
            ->add_joins($this->get_joins());

        // Timemodified filter.
        $filters[] = (new filter(
            date::class,
            'timemodified',
            new lang_string('timemodified', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.timemodified"
        ))
            ->set_limited_operators([
                date::DATE_ANY,
                date::DATE_NOT_EMPTY,
                date::DATE_EMPTY,
                date::DATE_RANGE,
                date::DATE_LAST,
                date::DATE_CURRENT,
            ])
            ->add_joins($this->get_joins());

        // Timecreated filter.
        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('timecreated', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.timecreated"
        ))
            ->add_joins($this->get_joins());

        // Associatedcertification filter.
        $filters[] = (new filter(
            associated_certification::class,
            'certification',
            new lang_string('associatedcertification', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.id"
        ))
            ->add_joins($this->get_joins());

        // Contains course filter.
        $filters[] = (new filter(
            contains_course::class,
            'course',
            new lang_string('containscourse', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.id"
        ))
            ->add_joins($this->get_joins());

        // Program tenant filter.
        $filters[] = (new filter(
            tenant::class,
            'tenant',
            new lang_string('programtenant', 'tool_program'),
            $this->get_entity_name(),
            "{$tablealias}.tenantid"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }

    /**
     * Get the custom fields helper
     *
     * @return custom_fields
     */
    protected function get_custom_fields(): custom_fields {
        if ($this->customfields === null) {
            $tablealias = $this->get_table_alias('tool_program');
            $this->customfields = new custom_fields("{$tablealias}.id", $this->get_entity_name(),
                'tool_program', 'program', 0);
            $this->customfields->add_joins($this->get_joins());
        }
        return $this->customfields;
    }
}
