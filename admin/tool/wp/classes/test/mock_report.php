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

namespace tool_wp\test;

use core_reportbuilder\datasource;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;

/**
 * Class mock_report
 *
 * @package   tool_wp
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Marina Glancy
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class mock_report extends datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_columns();
        $this->set_filters();
        $this->set_conditions();
        $this->set_main_table('user', 'u');
    }

    /**
     * Get the report name.
     *
     * @return string
     */
    public static function get_name(): string {
        return "Mock report for testing purpose";
    }

    /**
     * Set the columns for the report.
     */
    protected function set_columns() {
        $this->annotate_entity('user', new \lang_string('entityuser', 'tool_reportbuilder'));

        $fields = array('firstname', 'idnumber');

        foreach ($fields as $key => $field) {
            $newcolumn = (new column(
                $field,
                new \lang_string($field),
                'user'))
                ->add_field($field)
                ->set_is_sortable(true);

            $this->add_column($newcolumn);
        }

        // No default column.
        $newcolumn = (new column(
            'lastname',
            new \lang_string('lastname'),
            'user'))
            ->add_field('lastname')
            ->set_is_sortable(true);

        $this->add_column($newcolumn);

        // No default column.
        $newcolumn = (new column(
            'phone1',
            new \lang_string('phone1'),
            'user'))
            ->add_field('phone1')
            ->set_is_sortable(true)
            ->set_type(column::TYPE_INTEGER);

        $this->add_column($newcolumn);
    }

    /**
     * Set filters.
     */
    protected function set_filters() {
        $fields = array('firstname', 'idnumber', 'lastname', 'phone1');
        foreach ($fields as $field) {
            $filter = new filter(
                text::class,
                $field,
                new \lang_string($field),
                'user',
                $field
            );
            $this->add_filter($filter);
        }
    }

    /**
     * Default columns
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'user:firstname',
            'user:idnumber',
        ];
    }

    /**
     * Default filters
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [
            'user:firstname',
            'user:lastname',
            'user:idnumber',
        ];
    }

    /**
     * Default conditions
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [];
    }

    /**
     * Set conditions.
     */
    protected function set_conditions() {
        $fields = array('firstname', 'idnumber', 'lastname', 'phone1');
        foreach ($fields as $field) {
            $filter = new filter(
                text::class,
                $field,
                new \lang_string($field),
                'user',
                $field
            );
            $this->add_condition($filter);
        }
    }
}
