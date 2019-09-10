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
 * Class testable_report_exporter
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class testable_report_exporter
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_report_exporter extends \tool_reportbuilder\output\report_exporter {

    /**
     * testable_report_exporter constructor.
     *
     * @param int $reportid
     * @param bool $editon
     * @param int $page
     */
    public function __construct(int $reportid, bool $editon = true, int $page = 0) {
        global $PAGE;
        $report = \tool_reportbuilder\manager::get_report($reportid);
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
        /** @var \tool_reportbuilder\report_base $source */
        $source = $this->related['source'];
        $this->table->query_db($source->get_pagesize(), false);
        $rows = [];
        foreach ($this->table->rawdata as $record) {
            $rows[] = $this->table->format_row($record);
        }
        return $rows;
    }

    /**
     * Download the table
     *
     * @return \tool_reportbuilder\report_table
     *
     */
    public function get_table() : \tool_reportbuilder\report_table {
        return $this->table;
    }

    /**
     * Download the report in the given format
     *
     * @param string $format Format to export
     * @param bool $withheader If the report have the columns headers
     * @return false|string
     * @throws coding_exception
     * @throws dml_exception
     */
    public function download($format, $withheader = false) {
        ob_start();
        $tabledataformat = new tool_reportbuilder_table_dataformat_export_format($this->table, $format);

        $this->table->query_db($this->related['source']->get_pagesize(), false);
        /** @var table_dataformat_export_format $tabledataformat */
        $tabledataformat->start_document('test', 'test');

        if ($withheader) {
            $tabledataformat->add_data($this->table->headers);
        }
        foreach ($this->table->rawdata as $row) {
            $tabledataformat->add_data($this->table->format_row($row));
        }
        $tabledataformat->finish_document();
        return ob_get_clean();
    }
}