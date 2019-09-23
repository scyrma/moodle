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
 * @copyright   2019 Toni Barbera <toni@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
 * @copyright   2019 Toni Barbera <toni@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program_entity extends entity_base {
    /** @var string */
    protected $join = '';
    /** @var string */
    protected $tablealias = 'tp';
    /** @var array */
    protected $excludecolumns = [];
    /** @var customfields */
    protected $customfields;

    /**
     * program_fields constructor.
     *
     * @param string $join
     * @param string $tablealias
     * @param array $excludecolumns
     */
    public function __construct(string $join = '', string $tablealias = 'tp', array $excludecolumns = []) {
        $this->join = $join;
        $this->tablealias = $tablealias;
        $this->excludecolumns = array_combine($excludecolumns, $excludecolumns);
        $this->customfields = new customfields($tablealias . '.id', $this->get_entity_name(),
            'tool_program', 'program', 0);
    }

    /**
     * Entity name
     *
     * @return string
     */
    public function get_entity_name(): string {
        return 'tool_program';
    }

    /**
     * Entity title
     *
     * @return lang_string
     */
    public function get_entity_title(): lang_string {
        return new lang_string('entityprogram', 'tool_program');
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    public function get_columns(): array {
        global $DB;

        $columns = [];
        $ismssql = $DB->get_dbfamily() === 'mssql';

        // Column fullname.
        $newcolumn = (new report_column(
            'fullname',
            new lang_string('programname', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$this->tablealias.fullname")
            ->add_callback([program_format::class, 'fullname']);
        $columns[] = $newcolumn;

        // Column fullname with image.
        $newcolumn = (new report_column(
            'fullnamewithimage',
            new lang_string('programnamewithimage', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$this->tablealias.fullname")
            ->add_field("$this->tablealias.id")
            ->add_callback([program_format::class, 'fullnamewithimage']);
        $columns[] = $newcolumn;

        // Column program picture.
        $newcolumn = (new report_column(
            'programimage',
            new lang_string('programimage', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$this->tablealias.id")
            ->add_callback([program_format::class, 'programimage']);
        $columns[] = $newcolumn;

        // Column idnumber.
        $newcolumn = (new report_column(
            'idnumber',
            new lang_string('idnumber', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$this->tablealias.idnumber")
            ->add_callback([program_format::class, 'idnumber']);
        $columns[] = $newcolumn;

        // Column tags.
        list($tagsql, $tagparams) = db::sql_tag_field($this->tablealias, 'tool_program');

        $newcolumn = (new report_column(
            'tags',
            new lang_string('tags', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field($tagsql, 'tags', $tagparams)
            ->set_groupby_sql($this->tablealias . '.id')
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
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_LONGTEXT)
            ->add_field("$this->tablealias.description")
            ->add_field("$this->tablealias.descriptionformat")
            ->add_field("$this->tablealias.id")
            ->add_callback([program_format::class, 'description']);
        $columns[] = $newcolumn;

        // Column startdate.
        $newcolumn = (new report_column(
            'startdate',
            new lang_string('startdate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$this->tablealias.startdatetype")
            ->add_field("$this->tablealias.startdateabsolute")
            ->add_field("$this->tablealias.startdaterelative")
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
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$this->tablealias.duedatetype")
            ->add_field("$this->tablealias.duedateabsolute")
            ->add_field("$this->tablealias.duedaterelative")
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
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$this->tablealias.enddatetype")
            ->add_field("$this->tablealias.enddateabsolute")
            ->add_field("$this->tablealias.enddaterelative")
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
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->add_field("$this->tablealias.archived")
            ->add_callback([program_format::class, 'archived']);
        $columns[] = $newcolumn;

        // Column timearchived.
        $newcolumn = (new report_column(
            'timearchived',
            new lang_string('archivedon', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.timearchived")
            ->add_callback([program_format::class, 'timearchived']);
        $columns[] = $newcolumn;

        // Column allowdirectallocation.
        $newcolumn = (new report_column(
            'allowdirectallocation',
            new lang_string('allowdirectallocation', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->add_field("$this->tablealias.allowdirectallocation")
            ->add_callback([program_format::class, 'allowdirectallocation']);
        $columns[] = $newcolumn;

        // Column allocationstartdate.
        $newcolumn = (new report_column(
            'allocationstartdate',
            new lang_string('allocationstartdate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.allocationstartdatetype")
            ->add_field("$this->tablealias.allocationstartdateabsolute")
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
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.allocationenddateabsolute")
            ->add_field("$this->tablealias.allocationstartdateabsolute")
            ->add_field("$this->tablealias.allocationstartdatetype")
            ->add_field("$this->tablealias.allocationenddaterelative")
            ->add_field("$this->tablealias.allocationenddatetype")
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
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->add_field("$this->tablealias.visible")
            ->add_callback([program_format::class, 'visible']);
        $columns[] = $newcolumn;

        // Column timemodified.
        $newcolumn = (new report_column(
            'timemodified',
            new lang_string('timemodified', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.timemodified")
            ->add_callback([program_format::class, 'timemodified']);
        $columns[] = $newcolumn;

        // Column timecreated.
        $newcolumn = (new report_column(
            'timecreated',
            new lang_string('timecreated', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.timecreated")
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
                WHERE programid = $this->tablealias.id)";
        $newcolumn = (new report_column(
            'numbercoursesunique',
            new lang_string('numbercoursesinprogramunique', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_groupby_sql("$this->tablealias.id")
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
                WHERE $p.id = $this->tablealias.id
                GROUP BY $p.id)";

        $newcolumn = (new report_column(
            'associatedcertifications',
            new lang_string('associatedcertifications', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field($sql, "associatedcertifications")
            ->add_callback([format::class, 'format_string'])
            ->set_groupby_sql($this->tablealias . '.id');
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
                WHERE $p.id = $this->tablealias.id
                GROUP BY $p.id)";

        $newcolumn = (new report_column(
            'numbercurrentallocatedusers',
            new lang_string('numbercurrentallocatedusers', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field($sql, "numbercurrentallocatedusers")
            ->add_callback([format::class, 'format_string'])
            ->set_groupby_sql($this->tablealias . '.id');
        if ($ismssql) {
            columns::disable_column_aggregation($newcolumn);
        }
        $columns[] = $newcolumn;

        // Columns from program custom fields.
        $cfcolumns = $this->customfields->get_columns();
        if (!empty($cfcolumns)) {
            $columns = array_merge($columns, $cfcolumns);
        }

        return $columns;
    }

    /**
     * Returns all available filters.
     *
     * @return report_filter[]
     */
    public function get_filters(): array {
        $filters = $this->get_filters_or_conditions(false);
        // Add program custom fields filters.
        $cffilters = $this->customfields->get_filters();
        if (!empty($cffilters)) {
            $filters = array_merge($filters, $cffilters);
        }
        return $filters;
    }

    /**
     * Returns all available conditions.
     *
     * @return report_filter[]
     */
    public function get_conditions(): array {
        $filters = $this->get_filters_or_conditions(true);
        // Add program custom fields conditions.
        $cffilters = $this->customfields->get_conditions();
        if (!empty($cffilters)) {
            $filters = array_merge($filters, $cffilters);
        }
        return $filters;
    }

    /**
     * Filters/conditions for programs.
     *
     * @param bool $iscondition
     * @return array
     */
    protected function get_filters_or_conditions(bool $iscondition): array {
        $filters = [];

        // Filter Program selector.
        $filters[] = (new report_filter(
            select::class,
            'programselector',
            new lang_string('programs', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.id")
            ->set_options(api::get_programs_in_tenant_fieldset());

        // Filter fullname.
        $filters[] = (new report_filter(
            text::class,
            'fullname',
            new lang_string('programname', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.fullname");

        // Filter idnumber.
        $filters[] = (new report_filter(
            text::class,
            'idnumber',
            new lang_string('idnumber', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.idnumber");

        // Filter tags.
        $groupconcatsql = groupconcat::get_field('tg.name');
        $filters[] = (new report_filter(
            tags::class,
            'tags',
            new lang_string('tags', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("
                (SELECT {$groupconcatsql}
                   FROM {tag_instance} ti
                   JOIN {tag} tg ON tg.id = ti.tagid
                  WHERE ti.itemtype = 'tool_program'
                    AND ti.itemid = {$this->tablealias}.id
                    AND ti.component = 'tool_program') ");

        // Filter archived.
        $filters[] = (new report_filter(
            checkbox::class,
            'archived',
            new lang_string('archived', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.archived");

        // Filter timearchived.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timearchived',
            new lang_string('archivedon', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.timearchived");

        // Filter allowdirectallocation.
        $filters[] = (new report_filter(
            checkbox::class,
            'allowdirectallocation',
            new lang_string('allowdirectallocation', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.allowdirectallocation");

        // Filter visible.
        $filters[] = (new report_filter(
            checkbox::class,
            'visible',
            new lang_string('visible', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.visible");

        // Filter timemodified.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timemodified',
            new lang_string('timemodified', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.timemodified");

        // Filter timecreated.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timecreated',
            new lang_string('timecreated', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.timecreated");

        // Filter associated to a certification.
        $filters[] = (new report_filter(
            associated_certification::class,
            'certification',
            new lang_string('associatedcertification', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join);

        // Filter contains course.
        $filters[] = (new report_filter(
            contains_course::class,
            'course',
            new lang_string('containscourse', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join);

        return $filters;
    }
}
