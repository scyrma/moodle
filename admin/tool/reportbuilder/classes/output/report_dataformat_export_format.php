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
 * Class defining dataformat export format for reports
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\output;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/lib/tablelib.php');

/**
 * Class report_dataformat_export_format
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_dataformat_export_format extends \table_dataformat_export_format {

    /**
     * Add a row of data
     *
     * @param array $row
     * @return bool
     */
    public function add_data($row) {
        // If we are exporting to something other than HTML, then strip all the tags (TODO: support PDF - see MDL-67547).
        if (strcasecmp($this->dataformat->get_extension(), '.html') !== 0) {
            $row = array_map('strip_tags', $row);
        }

        $this->dataformat->write_record($row, $this->rownum++);
        return true;
    }

    /**
     * Finish document
     *
     * @return void
     */
    public function finish_document() {
        $this->dataformat->close_output();

        // If we are not running in the CLI (i.e. from a browser), we can exit at this point.
        if (!CLI_SCRIPT) {
            exit();
        }
    }
}