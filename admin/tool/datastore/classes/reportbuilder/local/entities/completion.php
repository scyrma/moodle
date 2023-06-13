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

namespace tool_datastore\reportbuilder\local\entities;

use lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use tool_datastore\api;
use tool_datastore\action_factory;
use tool_datastore\local\action\course_completed;

/**
 * Entity representing course completion from the datastore
 *
 * @package     tool_datastore
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class completion extends base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
            'tool_datastore_action' => 'dsa',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title() : lang_string {
        return new lang_string('entitycompletion', 'tool_datastore');
    }

    /**
     * Initialise the entity
     *
     * @return base
     */
    public function initialise(): base {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        return $this;
    }

    /**
     * Return list of all available columns
     *
     * @return column[]
     */
    protected function get_all_columns() : array {
        global $DB;

        $tablealias = $this->get_table_alias('tool_datastore_action');

        // MSSQL can not aggregate columns with sub-query, so we'll disable all aggregation methods for them.
        $ismssql = $DB->get_dbfamily() === 'mssql';

        /** @var course_completed $actionclass */
        $actionclass = action_factory::get_action_class('course_completed');

        $completionfields = $actionclass::get_fields_to_index()['course_completion'];
        foreach ($completionfields as $field) {
            [$sql, $params] = api::get_datasource_field_sql('course_completion', $field, $tablealias, null, column::TYPE_TIMESTAMP);

            $column = (new column(
                $field,
                new lang_string("columncompletion{$field}", 'tool_datastore'),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->add_field($sql, $field, $params)
                ->set_is_sortable(true)
                ->set_groupby_sql("{$tablealias}.id")
                ->set_type(column::TYPE_TIMESTAMP)
                ->add_callback([format::class, 'userdate']);

            if ($ismssql) {
                $column->set_disabled_aggregation_all();
            }

            $columns[] = $column;
        }

        return $columns;
    }
}
