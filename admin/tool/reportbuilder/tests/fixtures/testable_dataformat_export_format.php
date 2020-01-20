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
 * Class fixture defining testable dataformat exporter
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_reportbuilder\output\report_dataformat_export_format;

defined('MOODLE_INTERNAL') || die();

/**
 * Class testable_dataformat_export_format
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class testable_dataformat_export_format extends report_dataformat_export_format {

    /**
     * Constructor
     *
     * @param string $table An sql table
     * @param string $dataformat type of dataformat for export
     */
    public function __construct(&$table, $dataformat) {
        global $CFG;

        parent::__construct($table, $dataformat);

        // If we are using PDF dataformat, replace the class with our own that outputs the file as a string.
        if ($dataformat == 'pdf') {
            require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_pdf_writer.php');

            $this->dataformat = new testable_pdf_writer;
        }
    }
}