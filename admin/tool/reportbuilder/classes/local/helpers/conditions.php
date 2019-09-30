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
 * Class containing the helpers methods for the conditions.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\local\helpers;

use core\output\inplace_editable;
use tool_reportbuilder\event\report_updated;
use tool_reportbuilder\manager;
use tool_reportbuilder\local\models\reportbuilder_conditions;
use tool_reportbuilder\output\reportbuilder_condition_exporter;
use tool_reportbuilder\report_base;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\reportbuilder;

defined('MOODLE_INTERNAL') || die;

/**
 * Class conditions
 *
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   tool_reportbuilder
 */
class conditions {

    /** @var report_base $report */
    protected $report;

    /**
     * conditions constructor.
     * @param report_base $report
     */
    public function __construct(report_base $report) {
        $this->report = $report;
    }

    /**
     * Add a condition.
     *
     * @param int $reportid
     * @param string $conditionkey
     *
     * @return int
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function add_condition($reportid, $conditionkey): int {
        $report = manager::get_report($reportid);

        $conditions = $report->get_conditions();
        if (!array_key_exists($conditionkey, $conditions)) {
            throw new \moodle_exception('Condition not found'); // TODO SP-422 string.
        }
        // TODO SP-422 check this condition was not already added.

        $filter = $conditions[$conditionkey];
        $record = new \stdClass();
        $record->reportid = $reportid;
        $record->entity = $filter->get_entity();
        $record->name = $filter->get_name();
        $record->heading = '';
        $record->operator = null;
        $record->value = null;
        $record->sortorder = 1 + reportbuilder_conditions::get_max_sortorder($reportid);

        $persistent = new reportbuilder_conditions(0, $record);

        $persistent->save();
        return $persistent->get('id');
    }

    /**
     * Get the active conditions of the report.
     *
     * @param int $reportid
     *
     * @return reportbuilder_conditions[]
     */
    public static function get_active_conditions($reportid) {
        $filters = reportbuilder_conditions::get_records(array('reportid' => $reportid), 'sortorder', 'ASC');
        return $filters;
    }

    /**
     * Remove a condition.
     *
     * @param int $conditionid
     *
     * @return reportbuilder_conditions
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     */
    public static function remove_condition(int $conditionid) : reportbuilder_conditions {
        $persistent = new reportbuilder_conditions($conditionid, null);
        $helper = new self(manager::get_report($persistent->get('reportid')));
        $helper->remove_condition_values($persistent->get_unique_identifier());
        $persistent->delete();
        return $persistent;
    }

    /**
     * Delete the condition operator and value.
     *
     * @param string $condition The condition unique identifier
     * @return bool
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     */
    private function remove_condition_values(string $condition): bool {
        $conditionsvalues = $this->get_report_conditions();
        foreach ($conditionsvalues as $conditionkey => $conditionvalue) {
            if (strpos($conditionkey, $condition) === 0 ) {
                unset($conditionsvalues[$conditionkey]);
            }
        }
        return $this->add_report_conditions(json_encode($conditionsvalues));
    }

    /**
     * Get the conditions of the report.
     *
     * @param array $activeconditions
     * @param report_filter[] $conditions
     * @param \renderer_base $output
     * @return array
     * @throws \coding_exception
     */
    public static function get_conditions($activeconditions, $conditions, \renderer_base $output) {
        $conditionsdata = array();
        foreach ($activeconditions as $condition) {
            $conditionexporter = new reportbuilder_condition_exporter(
                $condition,
                array('conditionsdefinition' => $conditions)
            );
            if (!$conditionexporter->is_valid()) {
                continue;
            }
            $conditiondata = $conditionexporter->export($output);
            $conditionsdata[$conditiondata->key] = $conditiondata;
        }
        return $conditionsdata;
    }

    /**
     * Formatted name for condition header
     *
     * @param report_filter $condition condition definition
     * @param string $heading heading as entered by user
     *
     * @return string
     */
    public static function get_formatted_header(report_filter $condition, string $heading) : string {
        // Implementation is identical to filters.
        return filters::get_formatted_header($condition, $heading);
    }

    /**
     * Inplace editable for conditions
     *
     * @param report_filter $condition condition definition
     * @param string $heading heading as entered by user
     * @param int $id
     *
     * @return inplace_editable
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_header_inplace_editable(report_filter $condition, string $heading, int $id) : inplace_editable {
        $displayvalue = self::get_formatted_header($condition, $heading);
        return new inplace_editable('tool_reportbuilder', 'condition', $id,
            true, // This function is only called after we checked that user can edit field.
            $displayvalue, $heading, get_string('customizecondition', 'tool_reportbuilder'),
            get_string('newvaluefor', 'tool_reportbuilder', $displayvalue));
    }

    /**
     * Add the conditions selected to the report as encoded json.
     *
     * @param null|string $encodedjsonform
     * @return bool
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     */
    public function add_report_conditions(?string $encodedjsonform) : bool {
        $persistent = $this->report->get_persistent();
        $persistent->set('conditions', $encodedjsonform);

        if ($persistent->update()) {
            // Trigger report updated event.
            $event = report_updated::create_from_object($persistent);
            $event->trigger();

            return true;
        }
        return false;
    }

    /**
     * Get the report conditions.
     *
     * @return array
     * @throws \coding_exception
     */
    public function get_report_conditions() : ?array {
        $persistent = $this->report->get_persistent();
        $conditions = $persistent->get('conditions');
        if (empty($conditions)) {
            return [];
        }
        return json_decode($conditions, true);
    }

    /**
     * Reset all conditions of the given report.
     *
     * @return bool
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     */
    public function reset_all(): bool {
        $reportpersistent = $this->report->get_persistent();
        $reportpersistent->set('conditions', null);

        if ($reportpersistent->update()) {
            // Trigger report updated event.
            $event = report_updated::create_from_object($reportpersistent);
            $event->trigger();
            return true;
        }
        return false;
    }

    /**
     * Reset a condition.
     *
     * @param int $conditionid Id of the condition to reset
     * @return bool
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     */
    public function reset(int $conditionid): bool {
        $persistent = new reportbuilder_conditions($conditionid, null);
        return $this->remove_condition_values($persistent->get_unique_identifier());
    }
}