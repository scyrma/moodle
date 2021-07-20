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
 * Class for build a system report.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\report_filter;
use tool_tenant\tenancy;

/**
 * Class system_report
 *
 * Base class for all system reports
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class system_report extends report_base {

    /** @var string */
    private $basefields = '';

    /**
     * Return list of column names that will be excluded when table is downloading.
     *
     * @return array
     */
    public function get_exclude_columns_for_download(): array {
        return ['actions'];
    }

    /**
     * System report can never have conditions
     *
     * @return report_filter[]
     */
    final public function get_conditions() {
        return [];
    }

    /**
     * Output the report.
     *
     * @return string
     */
    final public function output() {
        global $PAGE;
        if (!$this->can_view()) {
            throw new \moodle_exception('nopermissions'); // TODO SP-422 better error message.
        }
        $outputpage = new \tool_reportbuilder\output\system_report($this);
        return $PAGE->get_renderer('tool_reportbuilder')->render($outputpage);
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * This is necessary to implement here and not on the page that embeds the system report
     * because second and consequtive pages of the report are rendered via web services.
     *
     * To retrieve parameters values call $this->get_parameter()
     */
    abstract protected function can_view(): bool;

    /**
     * Validates access to download this report.
     *
     * @return bool
     */
    final public function can_download(): bool {
        return \tool_tenant\permission::can_access_tenant($this->get_tenant_id()) && $this->is_downloadable() && $this->can_view();
    }

    /**
     * Add list of fields that have to be always included in SQL query for actions and row classes
     *
     * Base fields are only available in system reports because they are not compatible with aggregation
     *
     * @param string $sql SQL clause for the list of fields that only uses main table or base joins
     *     Fields may or may not have aliases. Example: "u.id, p.name AS programname"
     */
    protected function add_base_fields(string $sql) {
        if (strlen($this->basefields)) {
            $this->basefields .= ',';
        }
        $this->basefields .= $sql;
    }

    /**
     * Return list of fields that have to be always included in SQL query for actions and row classes
     *
     * @return string
     */
    public function get_base_fields() : string {
        return $this->basefields;
    }

    /**
     * Adds an action to be displayed in the 'actions' column in each row
     *
     * @param report_action $action
     */
    public function add_action(report_action $action) {
        parent::add_action($action);

        // Verify placeholders.
        if ($placeholders = $action->get_all_placeholders()) {
            preg_match_all('/[ \\.]([\w]+)\s*\\,/', ' ' . $this->get_base_fields() . ',', $matches);
            if ($missing = array_diff($placeholders, $matches[1])) {
                $missing = join(', ', $missing);
                throw new \coding_exception("Fields $missing should be added as base fields ' .
                    'because they are used in actions placeholders");
            }
        }
    }

    /**
     * CSS classes to add to the row (override if necessary)
     *
     * @param \stdClass $row
     * @return string
     */
    public function get_row_class(\stdClass $row) : string {
        return '';
    }

    /**
     * Called before rendering each row (override if necessary)
     *
     * Can be used to pre-fetch or create some objects and store them in this class.
     * They can later be used in the callbacks for the actions and columns.
     *
     * @param \stdClass $row
     */
    public function row_callback(\stdClass $row): void {
        return;
    }
}
