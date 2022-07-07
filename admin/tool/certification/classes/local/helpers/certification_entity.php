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

/**
 * Class certification_entity
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
use tool_reportbuilder\local\filter\tags;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\local\helpers\columns;
use tool_reportbuilder\local\helpers\customfields;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;
use \tool_reportbuilder\local\helpers\format;
use lang_string;
use tool_tenant\tool_reportbuilder\filter\tenant_filter;

/**
 * Typical fields from the certification table that can be added
 *
 * @package     tool_certification
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_entity extends entity_base {
    /** @var customfields */
    protected $customfields;

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['tool_certification' => 'tc', 'tool_tenant' => 'tttc'];
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
            $tablealias = $this->get_table_alias('tool_certification');
            $this->customfields = new customfields($tablealias . '.id', $this->get_entity_name(),
                'tool_certification', 'certification', 0);
            $this->customfields->add_joins($this->get_joins());
        }
        return $this->customfields;
    }

    /**
     * Entity name
     * @return string
     */
    protected function get_default_entity_name(): string {
        return 'tool_certification';
    }

    /**
     * Entity title
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entitycertification', 'tool_certification');
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    protected function get_all_columns() : array {
        global $DB;
        $tablealias = $this->get_table_alias('tool_certification');

        $columns = [];
        $ismssql = $DB->get_dbfamily() === 'mssql';

        // Column fullname.
        $newcolumn = (new report_column(
            'fullname',
            new lang_string('certificationname', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.fullname")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'format_string']);
        $columns[] = $newcolumn;

        $c = \tool_wp\db::generate_alias();
        $string = \html_writer::span('{{fullname}}', '', ['data-id' => '{{id}}']);
        $stringparams = ['{{id}}' => $c . '.id', '{{fullname}}' => $c . '.fullname'];
        [$placeholdersql, $placeholderparams] = db::sql_string_with_placeholders($string, $stringparams);

        $sql = "(SELECT $placeholdersql
        FROM {tool_certification} $c
        WHERE $c.id = $tablealias.id)";
        // Column fullnamewithlink.
        $newcolumn = (new report_column(
            'fullnamewithlink',
            new lang_string('certificationnamewithlink', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field($sql, 'fullname', $placeholderparams)
            ->set_is_sortable(true)
            ->set_groupby_sql("$tablealias.id")
            ->add_callback([certification_format::class, 'textwithlink'])
            ->add_aggregation_callback('groupconcat', [certification_format::class, 'textwithlink'])
            ->add_aggregation_callback('groupconcatdistinct', [certification_format::class, 'textwithlink'], true);
        if ($DB->get_dbfamily() === 'mssql') {
            columns::disable_column_aggregation($newcolumn);
        }
        $columns[] = $newcolumn;

        // Column idnumber.
        $newcolumn = (new report_column(
            'idnumber',
            new lang_string('idnumber'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.idnumber")
            ->set_is_sortable(true)
            ->add_callback('s');
        $columns[] = $newcolumn;

        // Column tags.
        list($tagsql, $tagparams) = db::sql_tag_field($tablealias, 'tool_certification');

        $newcolumn = (new report_column(
            'tags',
            new lang_string('tags', 'tool_certification'),
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

        // Column archived.
        $newcolumn = (new report_column(
            'archived',
            new lang_string('archived', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->add_field("$tablealias.archived")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'checkbox_as_text']);
        $columns[] = $newcolumn;

        // Column timearchived.
        $newcolumn = (new report_column(
            'timearchived',
            new lang_string('archivedon', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.timearchived")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);
        $columns[] = $newcolumn;

        // Column startdate.
        $newcolumn = (new report_column(
            'startdate',
            new lang_string('startdate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.startdatetype")
            ->add_field("$tablealias.startdateabsolute")
            ->add_field("$tablealias.startdaterelative")
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
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.duedatetype")
            ->add_field("$tablealias.duedateabsolute")
            ->add_field("$tablealias.duedaterelative")
            ->add_field("$tablealias.startdatetype")
            ->add_field("$tablealias.startdateabsolute")
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
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.expirydatetype")
            ->add_field("$tablealias.expirydateabsolute")
            ->add_field("$tablealias.expirydaterelative")
            ->add_field("$tablealias.startdatetype")
            ->add_field("$tablealias.startdateabsolute")
            ->add_field("$tablealias.duedaterelative")
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
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("CASE WHEN $tablealias.allocationstartdatetype = 1 " .
                "THEN $tablealias.allocationstartdateabsolute ELSE NULL END",
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
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("CASE WHEN $tablealias.allocationenddatetype = 1 " .
                "THEN $tablealias.allocationenddateabsolute ELSE NULL END",
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
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.timemodified")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);
        $columns[] = $newcolumn;

        // Column timecreated.
        $newcolumn = (new report_column(
            'timecreated',
            new lang_string('timecreated', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.timecreated")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);
        $columns[] = $newcolumn;

        $columns[] = (new report_column(
            'tenant',
            new lang_string('certificationtenant', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($this->get_tenant_join())
            ->add_field($this->get_table_alias('tool_tenant').'.name', 'tenant')
            ->set_is_sortable(true)
            ->add_callback([format::class, 'format_string']);

        return $columns;
    }

    /**
     * SQL join to add for the tenant column.
     *
     * @return string
     */
    protected function get_tenant_join() {
        $tablealias = $this->get_table_alias('tool_certification');
        $tt = $this->get_table_alias('tool_tenant');
        return "LEFT JOIN {tool_tenant} $tt ON {$tt}.id = {$tablealias}.tenantid";
    }

    /**
     * Filters/conditions for certifications.
     *
     * @param bool $iscondition
     * @return array
     */
    protected function get_filters_or_conditions(bool $iscondition): array {
        $filters = [];
        $tablealias = $this->get_table_alias('tool_certification');

        // Filter fullname.
        $filters[] = (new report_filter(
            text::class,
            'fullname',
            new lang_string('certificationname', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("COALESCE({$tablealias}.fullname, '')");

        // Filter idnumber.
        $filters[] = (new report_filter(
            text::class,
            'idnumber',
            new lang_string('idnumber', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.idnumber");

        // Filter tags.
        $filters[] = (new report_filter(
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

        // Filter archived.
        $filters[] = (new report_filter(
            checkbox::class,
            'archived',
            new lang_string('archived', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.archived");

        // Filter timearchived.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timearchived',
            new lang_string('archivedon', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.timearchived");

        // Filter timemodified.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timemodified',
            new lang_string('timemodified', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.timemodified");

        // Filter timecreated.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timecreated',
            new lang_string('timecreated', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.timecreated");

        // Filter certification selector.
        $filters[] = (new report_filter(
            select::class,
            'certificationselector',
            new lang_string('certifications', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("$tablealias.id")
            ->set_options_callback([api::class, 'get_certifications_in_tenant_fieldset']);

        // Filter certification tenant.
        $filters[] = (new report_filter(
            tenant_filter::class,
            'tenant',
            new lang_string('certificationtenant', 'tool_certification'),
            $this->get_entity_name(),
            "$tablealias.tenantid"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
