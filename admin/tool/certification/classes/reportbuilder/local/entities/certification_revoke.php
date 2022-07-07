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
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_user\fields;
use lang_string;
use stdClass;

/**
 * Certification revoke entity class implementation
 *
 * A separate entity is needed for revoke because usually we join tables using timerevoked = 0 and islast = 1.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_revoke extends base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['tool_certification_compltion' => 'tccr'];
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('revoked', 'tool_certification');
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
        $tablealias = $this->get_table_alias('tool_certification_compltion');
        $dateformat = get_string('strftimedatefullshort', 'core_langconfig');

        // Revoked column.
        $columns[] = (new column(
            'revoked',
            new lang_string('revoked', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->set_is_sortable(true)
            ->add_field("CASE WHEN {$tablealias}.id IS NOT NULL THEN 1 ELSE 0 END", 'revoked')
            ->add_callback([format::class, 'boolean_as_text']);

        // Time revoked column.
        $columns[] = (new column(
            'timerevoked',
            new lang_string('revokedon', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->set_is_sortable(true)
            ->add_field("{$tablealias}.timerevoked")
            ->add_callback([format::class, 'userdate'], $dateformat);

        // Revoked by column.
        $useralias = database::generate_alias();
        $columns[] = (new column(
            'revokedby',
            new lang_string('revokedby', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {user} {$useralias} ON {$useralias}.id = {$tablealias}.revokedby")
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_fields("{$tablealias}.revokedby " . fields::for_name()->get_sql($useralias)->selects)
            ->add_callback(static function(?string $value, stdClass $row): string {
                return fullname($row);
            });

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $filters = [];
        $tablealias = $this->get_table_alias('tool_certification_compltion');

        // Revoked filter.
        $filters[] = (new filter(
            boolean_select::class,
            'revoked',
            new lang_string('revoked', 'tool_certification'),
            $this->get_entity_name(),
            "CASE WHEN {$tablealias}.id IS NOT NULL THEN 1 ELSE 0 END"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
