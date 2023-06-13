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
 * Class testable_report_exporter
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_reportbuilder\output\report_dataformat_export_format;

/**
 * Class testable_report_exporter
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class testable_report_exporter extends \tool_reportbuilder\output\report_exporter {

    /**
     * testable_report_exporter constructor.
     *
     * @param int $reportid
     * @param bool $editon
     * @param int $page
     * @param array $parameters parameters for system reports
     */
    public function __construct(int $reportid, bool $editon = true, int $page = 0, array $parameters = []) {
        global $PAGE;
        $report = \tool_reportbuilder\manager::get_report($reportid, $parameters);
        $persistent = new \tool_reportbuilder\reportbuilder($report->get_id());
        parent::__construct($persistent,
            [
                'source'  => $report,
                'page' => $page,
                'editon' => $editon,
                'tableonly' => false
            ]
        );
        $PAGE->set_url('/');
        $this->prepare_report($PAGE->get_renderer('core'));
    }

    /**
     * Executes the query and returns the rows
     *
     * @return array
     */
    public function get_table_rows() : array {
        $this->table->setup();
        $this->table->query_db(0, false);

        $rows = [];
        foreach ($this->table->rawdata as $record) {
            $row = $this->table->format_row($record);
            $rows[] = array_values($row);
        }

        $this->table->close_recordset();

        return $rows;
    }

    /**
     * Download the report in the given format
     *
     * TODO: what is the point of creating a "testable" method just to test it? The download testcase needs re-factoring
     * to determine what it should be testing, vs. what it is testing (internals of dataformat export formats)
     *
     * @param string $format Format to export
     * @param bool $ignored
     * @return string File content
     */
    public function download(string $format, bool $ignored = false): string {
        $tabledataformat = new report_dataformat_export_format($this->table, $format);

        $filepath = $tabledataformat->start_output_to_file('test');
        $tabledataformat->output_headers($this->table->headers);

        foreach ($this->get_table_rows() as $row) {
            $tabledataformat->add_data($row);
        }
        $tabledataformat->close_output_to_file();

        return file_get_contents($filepath);
    }
}
