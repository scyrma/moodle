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
 * Class mock_report_nodefault
 *
 * @package    tool_reportbuilder
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019, Ruslan Kabalin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\test;

use tool_reportbuilder\constants;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;

defined('MOODLE_INTERNAL') || die();

/**
 * Class mock_report_nodefault
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Ruslan Kabalin
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class mock_report_nodefault extends mock_report {

    /**
     * Set the columns for the report.
     */
    protected function set_columns() {
        $this->annotate_entity('user', new \lang_string('entityuser', 'tool_reportbuilder'));

        $fields = array('firstname', 'idnumber', 'lastname', 'phone1');

        foreach ($fields as $field) {
            $newcolumn = (new report_column(
                $field,
                new \lang_string($field),
                'user'))
                ->add_field($field)
                ->set_is_sortable(true);

            $this->add_column($newcolumn);
        }
    }
}
