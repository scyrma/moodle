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

namespace tool_organisation\tool_dynamicrule\condition;

use tool_dynamicrule\rule;
use tool_wp\exporter_base;
use tool_wp\importer_base;
use tool_organisation\permission;

defined('MOODLE_INTERNAL') || die;

/**
 * The base class for user department conditions.
 *
 * @package    tool_organisation
 * @author     2021 Ruslan Kabalin
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class condition_department_base extends \tool_dynamicrule\condition_sql {
    /**
     * Return the configured criteria
     *
     * @return string
     */
    protected function get_criteria(): string {
        return $this->get_configdata()['criteria'] ?? self::CRITERIA_ALL;
    }

    /**
     * Validates the configform
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        global $DB;
        $errors = [];

        if (empty($data['departmentid'])) {
            $errors['departmentid'] = get_string('required');
            return $errors;
        }

        [$list, $params] = $DB->get_in_or_equal($data['departmentid']);
        $sql = "SELECT COUNT(1) FROM {tool_organisation_department} WHERE archived = 0 AND id " . $list;
        if ($DB->count_records_sql($sql, $params) !== count($data['departmentid'])) {
            $errors['departmentid'] = get_string('errorinvaliddepartment', 'tool_organisation');
        }
        return $errors;
    }

    /**
     * Return the configured departmentids
     *
     * @return array
     */
    protected function get_departmentid(): array {
        return (array) $this->get_configdata()['departmentid'];
    }

    /**
     * Return the configured subdepartments flag
     *
     * @return bool
     */
    protected function get_with_subdepartments(): bool {
        return !empty($this->get_configdata()['withsubdepartments']);
    }

    /**
     * Check if department still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;
        $departmentids = $this->get_departmentid();
        [$list, $params] = $DB->get_in_or_equal($departmentids);
        $sql = "SELECT COUNT(1) FROM {tool_organisation_department} WHERE archived = 0 AND id " . $list;
        return $DB->count_records_sql($sql, $params) === count($departmentids);
    }

    /**
     * If the current user is able to add this condition.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return permission::has_assign_jobs_capability();
    }

    /**
     * If the current user is able to edit this condition.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        return permission::has_assign_jobs_capability();
    }

    /**
     * Add departmentids condition field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        foreach ($this->get_departmentid() as $departmentid) {
            $exporter->add_mapping('tool_organisation_department', $departmentid);
        }
    }

    /**
     * Get departmentid condition field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $configdata = $this->get_configdata();
        $configdata['departmentid'] = [];
        foreach ($this->get_departmentid() as $departmentid) {
            $configdata['departmentid'][] = $importer->get_mapping('tool_organisation_department',
                $departmentid, IGNORE_MISSING) ?? 0;
        }
        $this->update_configdata($configdata);
    }

    /**
     * Which rule types this condition supports.
     *
     * @return int Rule types bitwise added.
     */
    public function supports_rule_types(): int {
        return rule::TYPE_NORMAL + rule::TYPE_SHARED;
    }
}
