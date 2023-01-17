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

namespace tool_certification\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\custom_fields;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_tag_tag;
use html_writer;
use lang_string;
use tool_certification\api;
use tool_certification\constants;
use tool_certification\reportbuilder\local\formatters\certification as certification_formatter;
use tool_wp\reportbuilder\local\filters\tags;

/**
 * Certification entity class implementation
 *
 * @package     tool_certification
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification extends base {

    /** @var custom_fields */
    protected $customfields;

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['tool_certification' => 'tc'];
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entitycertification', 'tool_certification');
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
        $tablealias = $this->get_table_alias('tool_certification');
        $dateformat = get_string('strftimedatefullshort', 'core_langconfig');
        $columns = [];

        // Fullname column.
        $columns[] = (new column(
            'fullname',
            new lang_string('certificationname', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.fullname")
            ->set_is_sortable(true)
            ->add_callback([certification_formatter::class, 'formatstring']);

        // Fullname with link column.
        $columns[] = (new column(
            'fullnamewithlink',
            new lang_string('certificationnamewithlink', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$tablealias}.fullname, {$tablealias}.id")
            ->set_is_sortable(true)
            ->add_callback([certification_formatter::class, 'fullname_with_link']);

        // Column idnumber.
        $columns[] = (new column(
            'idnumber',
            new lang_string('idnumber', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.idnumber")
            ->set_is_sortable(true)
            ->add_callback([certification_formatter::class, 'formatstring']);

        // Tags column (pre-aggregated column returning field within a sub-select). TODO: re-factor after MDL-73842.
        $taginstancealias = database::generate_alias();
        $tagalias = database::generate_alias();

        $tagconcatsql = $DB->sql_group_concat(
            $DB->sql_concat_join("'|'", ["{$tagalias}.name", "{$tagalias}.rawname"]), ':', "{$tagalias}.rawname");

        $column = (new column(
            'tags',
            new lang_string('tags', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("(
                SELECT {$tagconcatsql}
                  FROM {tag_instance} {$taginstancealias}
                  JOIN {tag} {$tagalias} ON {$tagalias}.id = {$taginstancealias}.tagid
                 WHERE {$taginstancealias}.component = 'tool_certification'
                   AND {$taginstancealias}.itemtype = 'tool_certification'
                   AND {$taginstancealias}.itemid = {$tablealias}.id
            )", 'tags')
            ->set_groupby_sql("{$tablealias}.id")
            ->set_is_sortable(true)
            // It makes little sense trying to count a pre-aggregated field.
            ->set_disabled_aggregation(['count', 'countdistinct'])
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
        if ($DB->get_dbfamily() === 'mssql') {
            // MsSQL can not aggregate the columns with subquery.
            $column->set_disabled_aggregation_all();
        }
        $columns[] = $column;

        // Column archived.
        $columns[] = (new column(
            'archived',
            new lang_string('archived', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$tablealias}.archived")
            ->set_is_sortable(true)
            ->add_callback([\core_reportbuilder\local\helpers\format::class, 'boolean_as_text']);

        // Column timearchived.
        $columns[] = (new column(
            'timearchived',
            new lang_string('archivedon', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.timearchived")
            ->set_is_sortable(true)
            ->add_callback([\core_reportbuilder\local\helpers\format::class, 'userdate'], $dateformat);

        // Column startdate.
        $columns[] = (new column(
            'startdate',
            new lang_string('startdate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_fields("{$tablealias}.startdatetype, {$tablealias}.startdateabsolute, {$tablealias}.startdaterelative")
            ->set_is_sortable(true, ['startdatetype', 'startdateabsolute', 'startdaterelative'])
            ->add_callback([certification_formatter::class, 'startdate']);

        // Column duedate.
        $columns[] = (new column(
            'duedate',
            new lang_string('duedate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_fields("{$tablealias}.duedatetype, {$tablealias}.duedateabsolute, {$tablealias}.duedaterelative")
            ->add_fields("{$tablealias}.startdatetype, {$tablealias}.startdateabsolute")
            ->set_is_sortable(true, ['duedatetype', 'duedateabsolute', 'duedaterelative',
                'startdatetype', 'startdateabsolute'])
            ->add_callback([certification_formatter::class, 'duedate']);

        // Column expirydate.
        $columns[] = (new column(
            'expirydate',
            new lang_string('expirydate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_fields("{$tablealias}.expirydatetype, {$tablealias}.expirydateabsolute, {$tablealias}.expirydaterelative")
            ->add_fields("{$tablealias}.startdatetype, {$tablealias}.startdateabsolute, {$tablealias}.duedaterelative")
            ->set_is_sortable(true, ['expirydatetype', 'expirydateabsolute', 'expirydaterelative', 'startdatetype',
                'startdateabsolute', 'duedaterelative'])
            ->add_callback([certification_formatter::class, 'expirydate']);

        $dateabsolute = constants::DATE_ABSOLUTE;
        // Column allocationstartdate.
        $columns[] = (new column(
            'allocationstartdate',
            new lang_string('allocationstartdate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("CASE WHEN {$tablealias}.allocationstartdatetype = {$dateabsolute} " .
                "THEN {$tablealias}.allocationstartdateabsolute ELSE 0 END",
                'allocationstartdate')
            ->set_is_sortable(true)
            ->add_callback([\core_reportbuilder\local\helpers\format::class, 'userdate'], $dateformat);

        // Column allocationenddate.
        $columns[] = (new column(
            'allocationenddate',
            new lang_string('allocationenddate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("CASE WHEN {$tablealias}.allocationenddatetype = {$dateabsolute} " .
                "THEN {$tablealias}.allocationenddateabsolute ELSE 0 END",
                'allocationenddate')
            ->set_is_sortable(true)
            ->add_callback([\core_reportbuilder\local\helpers\format::class, 'userdate'], $dateformat);

        // Column timemodified.
        $columns[] = (new column(
            'timemodified',
            new lang_string('timemodified', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.timemodified")
            ->set_is_sortable(true)
            ->add_callback([\core_reportbuilder\local\helpers\format::class, 'userdate'], $dateformat);

        // Column timecreated.
        $columns[] = (new column(
            'timecreated',
            new lang_string('timecreated', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$tablealias}.timecreated")
            ->set_is_sortable(true)
            ->add_callback([\core_reportbuilder\local\helpers\format::class, 'userdate'], $dateformat);

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $filters = [];
        $tablealias = $this->get_table_alias('tool_certification');

        // Fullname filter.
        $filters[] = (new filter(
            text::class,
            'fullname',
            new lang_string('certificationname', 'tool_certification'),
            $this->get_entity_name(),
            "{$tablealias}.fullname"
        ))
            ->add_joins($this->get_joins());

        // IDnumber filter.
        $filters[] = (new filter(
            text::class,
            'idnumber',
            new lang_string('idnumber', 'tool_certification'),
            $this->get_entity_name(),
            "{$tablealias}.idnumber"
        ))
            ->add_joins($this->get_joins());

        // Tags filter.
        $filters[] = (new filter(
            tags::class,
            'tags',
            new lang_string('tags'),
            $this->get_entity_name(),
            "{$tablealias}.id"
        ))
            ->add_joins($this->get_joins())
            ->set_options([
                'component' => 'tool_certification',
                'itemtype' => 'tool_certification',
            ]);

        // Archived filter.
        $filters[] = (new filter(
            boolean_select::class,
            'archived',
            new lang_string('archived', 'tool_certification'),
            $this->get_entity_name(),
            "{$tablealias}.archived"
        ))
            ->add_joins($this->get_joins());

        // Timearchived filter.
        $filters[] = (new filter(
            date::class,
            'timearchived',
            new lang_string('archivedon', 'tool_certification'),
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

        // Timemodified filter.
        $filters[] = (new filter(
            date::class,
            'timemodified',
            new lang_string('timemodified', 'tool_certification'),
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
            new lang_string('timecreated', 'tool_certification'),
            $this->get_entity_name(),
            "{$tablealias}.timecreated"
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

        // Certification selector filter.
        $filters[] = (new filter(
            select::class,
            'certificationselector',
            new lang_string('certifications', 'tool_certification'),
            $this->get_entity_name(),
            "{$tablealias}.archived"
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("{$tablealias}.id")
            ->set_options_callback([api::class, 'get_certifications_in_tenant_fieldset']);

        return $filters;
    }

    /**
     * Get the custom fields helper
     *
     * @return custom_fields
     */
    protected function get_custom_fields(): custom_fields {
        if ($this->customfields === null) {
            $tablealias = $this->get_table_alias('tool_certification');
            $this->customfields = new custom_fields($tablealias . '.id', $this->get_entity_name(),
                'tool_certification', 'certification', 0);
            $this->customfields->add_joins($this->get_joins());
        }
        return $this->customfields;
    }
}
