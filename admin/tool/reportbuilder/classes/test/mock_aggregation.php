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
 * Class for specific report mock for test aggregations
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\test;

use tool_reportbuilder\constants;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;
use tool_tenant\tenancy;
use tool_reportbuilder\local\entities\user as user_entity;

defined('MOODLE_INTERNAL') || die();

/**
 * Class mock_aggregation
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mock_aggregation extends \tool_reportbuilder\datasource {

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_columns();
        $this->set_main_table('user', 'u');
        list($join, $where, $params) = tenancy::get_users_sql('u');
        $this->add_base_join($join);
        $this->add_base_condition_sql($where, $params);
        $this->add_organisation_condition('u');
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
     * @throws \coding_exception
     */
    protected function set_columns() {
        $this->annotate_entity('user', new \lang_string('entityuser', 'tool_reportbuilder'));
        $this->add_entity(new user_entity('', 'u', ['phone1' , 'phone2']));

        // Add a pseudo-numeric column (field 'picture' has type 'int' in the database).
        $newcolumn = (new report_column(
            'phone1',
            new \lang_string('phone1'),
            'user'))
            ->add_field('picture')
            ->set_is_sortable(true)
            ->set_type(constants::DB_TYPE_NUMBER);

        $this->add_column($newcolumn);

        // Add a boolean column (field 'trackforums' has type 'int(1)' in the database).
        $newcolumn = (new report_column(
            'phone2',
            new \lang_string('phone2'),
            'user'))
            ->add_field('trackforums')
            ->set_is_sortable(true)
            ->set_type(constants::DB_TYPE_BOOLEAN);

        $this->add_column($newcolumn);
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
}