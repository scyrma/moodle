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
 * Class mock_system_report
 *
 * @package   tool_reportbuilder
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Mikel Martín <mikel@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\test;

use tool_reportbuilder\report_column;

defined('MOODLE_INTERNAL') || die();

/**
 * Class mock_system_report
 *
 * @package   tool_reportbuilder
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Mikel Martín <mikel@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class mock_system_report extends \tool_reportbuilder\system_report {

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_columns();
        $this->set_main_table('user', 'u');
        $this->set_downloadable(true);
    }

    /**
     * Get the report name.
     *
     * @return string
     */
    public static function get_name() {
        return "Mock system report for testing purpose";
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
    }

    /**
     * Set the view permissions for the report.
     */
    protected function can_view(): bool {
        return has_capability('tool/reportbuilder:read', \context_system::instance());
    }
}
