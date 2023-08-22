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

namespace tool_reportbuilder\local\entities;

use lang_string;
use tool_datastore\api;
use tool_reportbuilder\constants;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\filter\null_select;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\local\helpers\columns;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;

/**
 * Class containing datastore user entity
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class datastore_user extends entity_base {

    /** @var string */
    protected $userjoin = '';

    /**
     * Which entity class from core reportbuilder should this entity be migrated to
     *
     * @return string name of the class extending {@see \core_reportbuilder\local\entities\entity_base}
     */
    public function convert_get_entity_class(): string {
        return \tool_datastore\reportbuilder\local\entities\user::class;
    }

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['tool_datastore_action' => 'dsa', 'user' => 'u'];
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
     * Entity name
     *
     * @return string
     */
    protected function get_default_entity_name() : string {
        return 'tool_datastore_user';
    }

    /**
     * Entity title
     *
     * @return lang_string
     */
    protected function get_default_entity_title() : lang_string {
        return new lang_string('entityuser', 'tool_datastore');
    }

    /**
     * Specific to this entity the join with user table that is added to some columns/filters only
     *
     * @param string $join
     * @return entity_base
     */
    public function add_user_join(string $join): entity_base {
        $this->userjoin = $join;
        return $this;
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    protected function get_all_columns() : array {
        global $DB;
        $tablealias = $this->get_table_alias('tool_datastore_action');

        // MSSQL can not aggregate columns with sub-query, so we'll disable all aggregation methods for them.
        $ismssql = $DB->get_dbfamily() === 'mssql';

        $fields = ['firstname', 'lastname', 'email', 'idnumber', 'username'];
        foreach ($fields as $field) {
            list($sql, $params) = api::get_datasource_field_sql('user', $field, $tablealias, 'relateduserid',
                \core_reportbuilder\local\report\column::TYPE_TEXT);

            $column = (new report_column(
                $field,
                new lang_string($field),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->add_field($sql, $field, $params)
                ->set_groupby_sql("{$tablealias}.id, {$tablealias}.relateduserid")
                ->set_type(constants::DB_TYPE_TEXT)
                ->add_callback([format::class, 'format_string']);

            if ($ismssql) {
                columns::disable_column_aggregation($column);
            }

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * Return available filters/conditions
     *
     * @param bool $iscondition
     * @return report_filter[]
     */
    protected function get_filters_or_conditions(bool $iscondition) : array {
        $tablealias = $this->get_table_alias('tool_datastore_action');
        $tablealiasuser = $this->get_table_alias('user');
        // First name.
        list($sql, $params) = api::get_datasource_field_sql('user', 'firstname', $tablealias, 'relateduserid',
            \core_reportbuilder\local\report\column::TYPE_TEXT);

        $filters[] = (new report_filter(
            text::class,
            'firstname',
            new lang_string('firstname'),
            $this->get_entity_name(),
            $sql,
            $params
        ))->add_joins($this->get_joins());

        // Last name.
        list($sql, $params) = api::get_datasource_field_sql('user', 'lastname', $tablealias, 'relateduserid',
            \core_reportbuilder\local\report\column::TYPE_TEXT);

        $filters[] = (new report_filter(
            text::class,
            'lastname',
            new lang_string('lastname'),
            $this->get_entity_name(),
            $sql,
            $params
        ))->add_joins($this->get_joins());

        // Status.
        $filters[] = (new report_filter(
            null_select::class,
            'status',
            new lang_string('status'),
            $this->get_entity_name(),
            "{$tablealiasuser}.id"
        ))
            ->add_joins($this->get_joins())
            ->add_join($this->userjoin)
            ->set_options([
                0 => new lang_string('active'),
                1 => new lang_string('deleted'),
            ]);

        return $filters;
    }
}
