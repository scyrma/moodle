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
 * Class certification_entity
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\local\helpers;

use tool_certification\api;
use tool_reportbuilder\constants;
use tool_reportbuilder\db;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\filter\checkbox;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;
use \tool_reportbuilder\local\helpers\format;
use lang_string;

defined('MOODLE_INTERNAL') || die();

/**
 * Typical fields from the certification table that can be added
 *
 * @package     tool_certification
 * @copyright   2019 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certification_entity extends entity_base {
    /** @var string */
    protected $join = '';
    /** @var string */
    protected $tablealias = 'tc';
    /** @var array */
    protected $excludefields = [];

    /**
     * certification_fields constructor.
     *
     * @param string      $join
     * @param string      $tablealias
     * @param array       $excludefields
     */
    public function __construct(string $join = '', string $tablealias = 'tc', array $excludefields = []) {
        $this->join          = $join;
        $this->tablealias    = $tablealias;
        $this->excludefields = array_combine($excludefields, $excludefields);
    }

    /**
     * Entity name
     * @return string
     */
    public function get_entity_name(): string {
        return 'tool_certification';
    }

    /**
     * Entity title
     * @return lang_string
     */
    public function get_entity_title(): lang_string {
        return new lang_string('entitycertification', 'tool_certification');
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    public function get_columns() : array {
        global $DB;

        $columns = [];
        $ismssql = $DB->get_dbfamily() === 'mssql';

        // Column fullname.
        $newcolumn = (new report_column(
            'fullname',
            new lang_string('certificationname', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$this->tablealias.fullname")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'format_string']);
        $columns[] = $newcolumn;

        // Column idnumber.
        $newcolumn = (new report_column(
            'idnumber',
            new lang_string('idnumber'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$this->tablealias.idnumber")
            ->set_is_sortable(true)
            ->add_callback('s');
        $columns[] = $newcolumn;

        // Column tags.
        list($tagsql, $tagparams) = db::sql_tag_field($this->tablealias, 'tool_certification');

        $newcolumn = (new report_column(
            'tags',
            new lang_string('tags', 'tool_certification'),
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

        // Column archived.
        $newcolumn = (new report_column(
            'archived',
            new lang_string('archived', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->add_field("$this->tablealias.archived")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'checkbox_as_text']);
        $columns[] = $newcolumn;

        // Column timearchived.
        $newcolumn = (new report_column(
            'timearchived',
            new lang_string('archivedon', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.timearchived")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);
        $columns[] = $newcolumn;

        // Column startdate.
        $newcolumn = (new report_column(
            'startdate',
            new lang_string('startdate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$this->tablealias.startdatetype")
            ->add_field("$this->tablealias.startdateabsolute")
            ->add_field("$this->tablealias.startdaterelative")
            ->add_callback([certification_format::class, 'startdate'])
            ->disable_aggregation('groupconcat')
            ->disable_aggregation('groupconcatdistinct');
        $columns[] = $newcolumn;

        // Column duedate.
        $newcolumn = (new report_column(
            'duedate',
            new lang_string('duedate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$this->tablealias.duedatetype")
            ->add_field("$this->tablealias.duedateabsolute")
            ->add_field("$this->tablealias.duedaterelative")
            ->add_field("$this->tablealias.startdatetype")
            ->add_field("$this->tablealias.startdateabsolute")
            ->add_callback([certification_format::class, 'duedate'])
            ->disable_aggregation('groupconcat')
            ->disable_aggregation('groupconcatdistinct');
        $columns[] = $newcolumn;

        // Column expirydate.
        $newcolumn = (new report_column(
            'expirydate',
            new lang_string('expirydate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$this->tablealias.expirydatetype")
            ->add_field("$this->tablealias.expirydateabsolute")
            ->add_field("$this->tablealias.expirydaterelative")
            ->add_callback([certification_format::class, 'expirydate'])
            ->disable_aggregation('groupconcat')
            ->disable_aggregation('groupconcatdistinct');
        $columns[] = $newcolumn;

        // Column allocationstartdate.
        $newcolumn = (new report_column(
            'allocationstartdate',
            new lang_string('allocationstartdate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("CASE WHEN $this->tablealias.allocationstartdatetype = 1 " .
                "THEN $this->tablealias.allocationstartdateabsolute ELSE NULL END",
                'allocationstartdate')
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);
        $columns[] = $newcolumn;

        // Column allocationenddate.
        $newcolumn = (new report_column(
            'allocationenddate',
            new lang_string('allocationenddate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("CASE WHEN $this->tablealias.allocationenddatetype = 1 " .
                "THEN $this->tablealias.allocationenddateabsolute ELSE NULL END",
                'allocationenddate')
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);
        $columns[] = $newcolumn;

        // Column timemodified.
        $newcolumn = (new report_column(
            'timemodified',
            new lang_string('timemodified', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.timemodified")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);
        $columns[] = $newcolumn;

        // Column timecreated.
        $newcolumn = (new report_column(
            'timecreated',
            new lang_string('timecreated', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.timecreated")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);
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
     * Filters/conditions for certifications.
     *
     * @param bool $iscondition
     * @return array
     */
    protected function get_filters_or_conditions(bool $iscondition): array {
        $filters = [];

        // Filter fullname.
        $filters[] = (new report_filter(
            text::class,
            'fullname',
            new lang_string('certificationname', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.fullname");

        // Filter idnumber.
        $filters[] = (new report_filter(
            text::class,
            'idnumber',
            new lang_string('idnumber', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.idnumber");

        // Filter tags.
        $groupconcatsql = db::sql_group_concat('tg.name');
        $filters[] = (new report_filter(
            tags::class,
            'tags',
            new lang_string('tags', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("
                (SELECT {$groupconcatsql}
                   FROM {tag_instance} ti
                   JOIN {tag} tg ON tg.id = ti.tagid
                  WHERE ti.itemtype = 'tool_certification'
                    AND ti.itemid = {$this->tablealias}.id
                    AND ti.component = 'tool_certification') ");

        // Filter archived.
        $filters[] = (new report_filter(
            checkbox::class,
            'archived',
            new lang_string('archived', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.archived");

        // Filter timearchived.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timearchived',
            new lang_string('archivedon', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.timearchived");

        // Filter timemodified.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timemodified',
            new lang_string('timemodified', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.timemodified");

        // Filter timecreated.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timecreated',
            new lang_string('timecreated', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.timecreated");

        // Filter certification selector.
        $filters[] = (new report_filter(
            select::class,
            'certificationselector',
            new lang_string('certifications', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.id")
            ->set_options(api::get_certifications_in_tenant_fieldset());

        return $filters;
    }
}