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
 * File for the class program_entity
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Toni Barbera <toni@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\local\helpers;

use lang_string;
use tool_program\api;
use tool_program\tool_reportbuilder\filter\associated_certification;
use tool_program\tool_reportbuilder\filter\contains_course;
use tool_reportbuilder\constants;
use tool_reportbuilder\db;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\aggregate\groupconcat;
use tool_reportbuilder\local\filter\checkbox;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\local\helpers\columns;
use tool_reportbuilder\local\helpers\customfields;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;

defined('MOODLE_INTERNAL') || die();

/**
 * Columns, filters and conditions that defines the program_entity and can be reused in any report datasource
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Toni Barbera <toni@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_entity extends entity_base {
    /** @var customfields */
    protected $customfields;

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['tool_program' => 'tp'];
    }

    /**
     * The default machine-readable name for this entity that will be used in the internal names of the columns/filters
     *
     * @return string
     */
    protected function get_default_entity_name(): string {
        return 'tool_program';
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return \lang_string
     */
    protected function get_default_entity_title(): \lang_string {
        return new \lang_string('entityprogram', 'tool_program');
    }

    /**
     * Executed when entity is added to the datasource or system report
     */
    public function add_to_report() {
        $columns = array_merge($this->get_all_columns(), $this->get_custom_fields()->get_columns());
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $conditions = array_merge($this->get_filters_or_conditions(true), $this->get_custom_fields()->get_conditions());
        foreach ($conditions as $condition) {
            $this->add_condition($condition);
        }

        $filters = array_merge($this->get_filters_or_conditions(false), $this->get_custom_fields()->get_filters());
        foreach ($filters as $filter) {
            $this->add_filter($filter);
        }
    }

    /**
     * Get the custom fields helper
     *
     * @return customfields
     */
    protected function get_custom_fields(): customfields {
        if ($this->customfields === null) {
            $tablealias = $this->get_table_alias('tool_program');
            $this->customfields = new customfields($tablealias . '.id', $this->get_entity_name(),
                'tool_program', 'program', 0);
            $this->customfields->add_joins($this->get_joins());
        }
        return $this->customfields;
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    protected function get_all_columns(): array {
        global $DB;
        $tablealias = $this->get_table_alias('tool_program');

        $columns = [];
        $ismssql = $DB->get_dbfamily() === 'mssql';

        // Column fullname.
        $newcolumn = (new report_column(
            'fullname',
            new lang_string('programname', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.fullname")
            ->add_callback([program_format::class, 'fullname']);
        $columns[] = $newcolumn;

        // Column fullname with image.
        $newcolumn = (new report_column(
            'fullnamewithimage',
            new lang_string('programnamewithimage', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.fullname")
            ->add_field("$tablealias.id")
            ->add_callback([program_format::class, 'fullnamewithimage']);
        $columns[] = $newcolumn;

        // Column program picture.
        $newcolumn = (new report_column(
            'programimage',
            new lang_string('programimage', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.id")
            ->add_callback([program_format::class, 'programimage']);
        $columns[] = $newcolumn;

        // Column idnumber.
        $newcolumn = (new report_column(
            'idnumber',
            new lang_string('idnumber', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.idnumber")
            ->add_callback([program_format::class, 'idnumber']);
        $columns[] = $newcolumn;

        // Column tags.
        list($tagsql, $tagparams) = db::sql_tag_field($tablealias, 'tool_program');

        $newcolumn = (new report_column(
            'tags',
            new lang_string('tags', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field($tagsql, 'tags', $tagparams)
            ->set_groupby_sql($tablealias . '.id')
            ->add_callback([format::class, 'tags_replace_all'])
            ->add_aggregation_callback('groupconcat', [format::class, 'tags_replace_all'])
            ->add_aggregation_callback('groupconcatdistinct', [format::class, 'tags_replace_all'], true)
            ->disable_aggregation('count')
            ->disable_aggregation('countdistinct');
        if ($ismssql) {
            // MsSQL can not aggregate the columns with subquery.
            $newcolumn
                ->disable_aggregation('groupconcat')
                ->disable_aggregation('groupconcatdistinct');
        }
        $columns[] = $newcolumn;

        // Column description.
        $newcolumn = (new report_column(
            'description',
            new lang_string('description', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_LONGTEXT)
            ->add_field("$tablealias.description")
            ->add_field("$tablealias.descriptionformat")
            ->add_field("$tablealias.id")
            ->add_callback([program_format::class, 'description']);
        $columns[] = $newcolumn;

        // Column startdate.
        $newcolumn = (new report_column(
            'startdate',
            new lang_string('startdate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.startdatetype")
            ->add_field("$tablealias.startdateabsolute")
            ->add_field("$tablealias.startdaterelative")
            ->add_callback([program_format::class, 'startdate'])
            ->disable_aggregation('groupconcat')
            ->disable_aggregation('groupconcatdistinct');;
        $columns[] = $newcolumn;

        // Column duedate.
        $newcolumn = (new report_column(
            'duedate',
            new lang_string('duedate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.duedatetype")
            ->add_field("$tablealias.duedateabsolute")
            ->add_field("$tablealias.duedaterelative")
            ->add_callback([program_format::class, 'duedate'])
            ->disable_aggregation('groupconcat')
            ->disable_aggregation('groupconcatdistinct');;
        $columns[] = $newcolumn;

        // Column enddate.
        $newcolumn = (new report_column(
            'enddate',
            new lang_string('enddate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.enddatetype")
            ->add_field("$tablealias.enddateabsolute")
            ->add_field("$tablealias.enddaterelative")
            ->add_callback([program_format::class, 'enddate'])
            ->disable_aggregation('groupconcat')
            ->disable_aggregation('groupconcatdistinct');;
        $columns[] = $newcolumn;

        // Column archived.
        $newcolumn = (new report_column(
            'archived',
            new lang_string('archived', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->add_field("$tablealias.archived")
            ->add_callback([program_format::class, 'archived']);
        $columns[] = $newcolumn;

        // Column timearchived.
        $newcolumn = (new report_column(
            'timearchived',
            new lang_string('archivedon', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.timearchived")
            ->add_callback([program_format::class, 'timearchived']);
        $columns[] = $newcolumn;

        // Column allowdirectallocation.
        $newcolumn = (new report_column(
            'allowdirectallocation',
            new lang_string('allowdirectallocation', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->add_field("$tablealias.allowdirectallocation")
            ->add_callback([program_format::class, 'allowdirectallocation']);
        $columns[] = $newcolumn;

        // Column allocationstartdate.
        $newcolumn = (new report_column(
            'allocationstartdate',
            new lang_string('allocationstartdate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.allocationstartdatetype")
            ->add_field("$tablealias.allocationstartdateabsolute")
            ->add_callback([program_format::class, 'allocationstartdate']);
        $newcolumn->add_aggregation_callback('max', [program_format::class, 'allocationstartdate']);
        $newcolumn->add_aggregation_callback('min', [program_format::class, 'allocationstartdate']);
        $columns[] = $newcolumn;

        // Column allocationenddate.
        $newcolumn = (new report_column(
            'allocationenddate',
            new lang_string('allocationenddate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.allocationenddateabsolute")
            ->add_field("$tablealias.allocationstartdateabsolute")
            ->add_field("$tablealias.allocationstartdatetype")
            ->add_field("$tablealias.allocationenddaterelative")
            ->add_field("$tablealias.allocationenddatetype")
            ->add_callback([program_format::class, 'allocationenddate']);
        $newcolumn->add_aggregation_callback('max', [program_format::class, 'allocationenddate']);
        $newcolumn->add_aggregation_callback('min', [program_format::class, 'allocationenddate']);
        $columns[] = $newcolumn;

        // Column visible.
        $newcolumn = (new report_column(
            'visible',
            new lang_string('visible', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->add_field("$tablealias.visible")
            ->add_callback([program_format::class, 'visible']);
        $columns[] = $newcolumn;

        // Column timemodified.
        $newcolumn = (new report_column(
            'timemodified',
            new lang_string('timemodified', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.timemodified")
            ->add_callback([program_format::class, 'timemodified']);
        $columns[] = $newcolumn;

        // Column timecreated.
        $newcolumn = (new report_column(
            'timecreated',
            new lang_string('timecreated', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.timecreated")
            ->add_callback([program_format::class, 'timecreated']);
        $columns[] = $newcolumn;

        // Column numbercoursesunique.
        $tpc = \tool_wp\db::generate_alias();
        $tps = \tool_wp\db::generate_alias();
        $tp = \tool_wp\db::generate_alias();
        $sql = "(SELECT COUNT(DISTINCT($tpc.courseid))
            FROM {tool_program_courses} $tpc
            LEFT JOIN {tool_program_sets} $tps ON $tps.id = $tpc.setid
            LEFT JOIN {tool_program} $tp ON $tp.id = $tps.programid
            WHERE programid = $tablealias.id)";
        $newcolumn = (new report_column(
            'numbercoursesunique',
            new lang_string('numbercoursesinprogramunique', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_groupby_sql("$tablealias.id")
            ->set_type(constants::DB_TYPE_NUMBER)
            ->add_field($sql, 'numbercoursesunique');
        if ($ismssql) {
            columns::disable_column_aggregation($newcolumn);
        }
        $columns[] = $newcolumn;

        // Column associatedcertifications.
        $c = \tool_wp\db::generate_alias();
        $p = \tool_wp\db::generate_alias();
        $groupconcatsql = db::sql_group_concat("$c.fullname");
        $sql = "(SELECT $groupconcatsql
            FROM {tool_certification} $c
            LEFT JOIN {tool_program} $p
            ON $c.program = $p.id
            WHERE $p.id = $tablealias.id
            GROUP BY $p.id)";

        $newcolumn = (new report_column(
            'associatedcertifications',
            new lang_string('associatedcertifications', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field($sql, "associatedcertifications")
            ->add_callback([format::class, 'format_string'])
            ->set_groupby_sql($tablealias . '.id');
        if ($ismssql) {
            columns::disable_column_aggregation($newcolumn);
        }
        $columns[] = $newcolumn;

        // Column associatedcertificationswithlink.
        $c = \tool_wp\db::generate_alias();
        $p = \tool_wp\db::generate_alias();
        $string = \html_writer::span('{{fullname}}', '', ['data-id' => '{{id}}']);
        $stringparams = ['{{id}}' => $c . '.id', '{{fullname}}' => $c . '.fullname'];
        [$placeholdersql, $placeholderparams] = db::sql_string_with_placeholders($string, $stringparams);
        $groupconcatsql = db::sql_group_concat($placeholdersql);
        $sql = "(SELECT $groupconcatsql
            FROM {tool_certification} $c
            LEFT JOIN {tool_program} $p
            ON $c.program = $p.id
            WHERE $p.id = $tablealias.id
            GROUP BY $p.id)";

        $newcolumn = (new report_column(
            'associatedcertificationswithlink',
            new lang_string('associatedcertificationswithlinks', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field($sql, 'associatedcertifications', $placeholderparams)
            ->add_callback([program_format::class, 'associatedcertificationswithlink'])
            ->set_groupby_sql($tablealias . '.id');
        if ($ismssql) {
            columns::disable_column_aggregation($newcolumn);
        }
        $columns[] = $newcolumn;

         // Column numbercurrentallocatedusers.
        $p = \tool_wp\db::generate_alias();
        $pu = \tool_wp\db::generate_alias();
        $sql = "(SELECT COUNT(DISTINCT($pu.userid))
            FROM {tool_program_users} $pu
            LEFT JOIN {tool_program} $p
            ON $pu.programid = $p.id
            WHERE $p.id = $tablealias.id
            GROUP BY $p.id)";

        $newcolumn = (new report_column(
            'numbercurrentallocatedusers',
            new lang_string('numbercurrentallocatedusers', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field($sql, "numbercurrentallocatedusers")
            ->add_callback([format::class, 'format_string'])
            ->set_groupby_sql($tablealias . '.id');
        if ($ismssql) {
            columns::disable_column_aggregation($newcolumn);
        }
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
        $tablealias = $this->get_table_alias('tool_program');

        // Filter Program selector.
        $filters[] = (new report_filter(
            select::class,
            'programselector',
            new lang_string('programs', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.id")
            ->set_options(api::get_programs_in_tenant_fieldset());

        // Filter fullname.
        $filters[] = (new report_filter(
            text::class,
            'fullname',
            new lang_string('programname', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.fullname");

        // Filter idnumber.
        $filters[] = (new report_filter(
            text::class,
            'idnumber',
            new lang_string('idnumber', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.idnumber");

        // Filter tags.
        list($tagsql, $tagparams) = db::sql_tag_filter($tablealias, 'tool_program');
        $filters[] = (new report_filter(
            tags::class,
            'tags',
            new lang_string('tags', 'tool_program'),
            $this->get_entity_name(),
            $tagsql,
            $tagparams
        ))->add_joins($this->get_joins());

        // Filter archived.
        $filters[] = (new report_filter(
            checkbox::class,
            'archived',
            new lang_string('archived', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.archived");

        // Filter timearchived.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timearchived',
            new lang_string('archivedon', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.timearchived");

        // Filter allowdirectallocation.
        $filters[] = (new report_filter(
            checkbox::class,
            'allowdirectallocation',
            new lang_string('allowdirectallocation', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.allowdirectallocation");

        // Filter visible.
        $filters[] = (new report_filter(
            checkbox::class,
            'visible',
            new lang_string('visible', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.visible");

        // Filter timemodified.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timemodified',
            new lang_string('timemodified', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.timemodified");

        // Filter timecreated.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timecreated',
            new lang_string('timecreated', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.timecreated");

        // Filter associated to a certification.
        $filters[] = (new report_filter(
            associated_certification::class,
            'certification',
            new lang_string('associatedcertification', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins());

        // Filter contains course.
        $filters[] = (new report_filter(
            contains_course::class,
            'course',
            new lang_string('containscourse', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
