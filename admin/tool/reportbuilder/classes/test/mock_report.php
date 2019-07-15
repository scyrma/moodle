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
 * Class mock_report
 *
 * @package tool_reportbuilder
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\test;

use tool_reportbuilder\constants;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;

defined('MOODLE_INTERNAL') || die();

/**
 * Class mock_report
 *
 * @package tool_reportbuilder
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mock_report extends \tool_reportbuilder\datasource {

    /**
     * Initialise report
     */
    protected function initialise() {
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
    public static function get_name() {
        return "Mock report for testing purpose";
    }

    /**
     * Set the columns for the report.
     */
    protected function set_columns() {
        $this->annotate_entity('user', new \lang_string('entityuser', 'tool_reportbuilder'));

        $fields = array('firstname', 'idnumber');

        foreach ($fields as $key => $field) {
            $newcolumn = (new report_column(
                $field,
                new \lang_string($field),
                'user'))
                ->add_field($field)
                ->set_is_default(true, $key)
                ->set_is_sortable(true);

            $this->add_column($newcolumn);
        }

        // No default column.
        $newcolumn = (new report_column(
            'lastname',
            new \lang_string('lastname'),
            'user'))
            ->add_field('lastname')
            ->set_is_sortable(true);

        $this->add_column($newcolumn);

        // No default column.
        $newcolumn = (new report_column(
            'phone1',
            new \lang_string('phone1'),
            'user'))
            ->add_field('phone1')
            ->set_is_sortable(true)
            ->set_type(constants::DB_TYPE_NUMBER);

        $this->add_column($newcolumn);
    }

    /**
     * Set filters.
     */
    protected function set_filters() {
        $fields = array('firstname', 'idnumber', 'lastname', 'phone1');
        foreach ($fields as $field) {
            $filter = new report_filter(
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
     * Set default filters.
     */
    protected function set_default_filters() {
        $this->defaultfilters = array(
            array(
                'entity' => 'user',
                'source' => 'user',
                'field' => 'firstname',
            ),
            array(
                'entity' => 'user',
                'source' => 'user',
                'field' => 'lastname',
            ),
            array(
                'entity' => 'user',
                'source' => 'user',
                'field' => 'idnumber',
            )
        );
    }

    /**
     * Set conditions.
     */
    protected function set_conditions() {
        $fields = array('firstname', 'idnumber', 'lastname', 'phone1');
        foreach ($fields as $field) {
            $filter = new report_filter(
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