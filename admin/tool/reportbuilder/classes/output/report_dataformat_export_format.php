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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

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
        // If the export format doesn't support HTML, then strip all the tags and trim the string.
        if (!$this->dataformat->supports_html()) {
            $row = array_map(function($cell) {
                return trim(strip_tags($cell));
            }, $row);
        }

        return parent::add_data($row);
    }

    /**
     * Start dataformat export to file
     *
     * @param string $filename Intended filename only, excluding path/extension
     * @return string Complete path for the exported file
     */
    public function start_output_to_file(string $filename): string {
        $filepath = make_request_directory() . "/{$filename}" . $this->dataformat->get_extension();

        $this->dataformat->set_filepath($filepath);
        $this->dataformat->start_output_to_file();

        return $filepath;
    }

    /**
     * Finish the export, and write the data to disk
     *
     * @return bool
     */
    public function close_output_to_file(): bool {
        $this->finish_table();

        return $this->dataformat->close_output_to_file();
    }
}
