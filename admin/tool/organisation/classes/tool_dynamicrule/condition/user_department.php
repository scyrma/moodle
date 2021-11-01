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
 * This file contains the backend class for user_department condition.
 *
 * @package    tool_organisation
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\tool_dynamicrule\condition;

use tool_organisation\event\job_created;
use tool_organisation\event\job_updated;
use tool_organisation\helper;
use tool_organisation\organisation;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for user_department condition
 *
 * @package    tool_organisation
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_department extends condition_department_base {

    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditionuserdepartment', 'tool_organisation');
    }

    /**
     * Return the configured conditiondate
     *
     * @return int
     */
    private function get_conditiondate(): int {
        return $this->get_configdata()['jobstartdate'];
    }

    /**
     * Return the configured conditionenabled
     *
     * @return bool
     */
    private function get_conditiondateenabled(): bool {
        return $this->get_configdata()['jobstartdate'] ?? false;
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        global $DB;
        [$list, $params] = $DB->get_in_or_equal($this->get_departmentid());
        $names = $DB->get_fieldset_sql("SELECT name FROM {tool_organisation_department} WHERE id "
            . $list . " ORDER BY name", $params);

        $deptnames = implode("', '", array_map(function(string $name) {
            return format_string($name, true, ['escape' => false]);
        }, $names));

        $options = [
            'deptname' => $deptnames,
            'subdeptsinclude' => $this->get_with_subdepartments() ? get_string('included') : get_string('notincluded'),
        ];

        $withdatefragment = '';
        if ($this->get_conditiondateenabled()) {
            $options['conditiondate'] = userdate($this->get_conditiondate(), get_string('strftimedatefullshort'));
            $withdatefragment = 'withdate';
        }

        if (count($names) > 1) {
            // Many departments.
            $identifier = 'conditionuserdepartments' . $this->get_criteria() . 'description' . $withdatefragment;
        } else {
            // One department.
            $identifier = 'conditionuserdepartmentdescription' . $withdatefragment;
        }

        return get_string($identifier, 'tool_organisation', $options);
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        global $CFG, $OUTPUT;

        $mform->addElement('selectgroups', 'departmentid', get_string('entitydepartment', 'tool_organisation'),
            organisation::get_all_departments_menu(), ['multiple' => true]);

        $mform->addElement('advcheckbox', 'withsubdepartments',
            '', get_string('withsubdepartments', 'tool_organisation'));

        $group = [];
        $group[] = $mform->createElement('radio', 'criteria',
            get_string('conditionuserdepartmentsallcriteria', 'tool_organisation'),
            '',
            self::CRITERIA_ALL);
        $group[] = $mform->createElement('radio', 'criteria',
            get_string('conditionuserdepartmentsanycriteria', 'tool_organisation'),
            $OUTPUT->help_icon('conditionuserdepartmentsanycriteria', 'tool_organisation'),
            self::CRITERIA_ANY);
        $group[] = $mform->createElement('radio', 'criteria',
            get_string('conditionuserdepartmentseachcriteria', 'tool_organisation'),
            $OUTPUT->help_icon('conditionuserdepartmentseachcriteria', 'tool_organisation') .
            get_string('conditioncriterianotavailableyet', 'tool_dynamicrule'),
            self::CRITERIA_EACH, ['disabled' => true]);
        $mform->addGroup($group, 'criteria_group',
            get_string('conditioncriteria', 'tool_dynamicrule'),
            \html_writer::div('', 'w-100'),
            false);
        $mform->setType('criteria', PARAM_ALPHANUM);
        $mform->setDefault('criteria', self::CRITERIA_ANY);

        $options = ['optional' => true, 'timezone' => $CFG->timezone];
        $mform->addElement('date_selector', 'jobstartdate', get_string('jobstartdateafter', 'tool_organisation'), $options);
        $mform->setDefault('jobstartdate', time());
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {
        $startdate = null;
        if ($this->get_conditiondateenabled()) {
            $startdate = $this->get_conditiondate();
        }
        $withsubdepartments = $this->get_with_subdepartments();
        $tenantid = $this->get_tenantid();

        $departmentwheres = [];
        $departmentparams = [];
        foreach ($this->get_departmentid() as $departmentid) {
            [$where, $params] = helper::user_is_in_department_select($departmentid,
                $withsubdepartments, 'u', $startdate, $tenantid);

            $departmentwheres[] = $where;
            $departmentparams = array_merge($departmentparams, $params);
        }

        if (count($departmentwheres) > 1) {
            $separator = ($this->get_criteria() === self::CRITERIA_ALL) ? ' AND ' : ' OR ';
            $departmentwheres = implode($separator, $departmentwheres);
        } else {
            $departmentwheres = $departmentwheres[0];
        }

        return ['', $departmentwheres, $departmentparams];
    }

    /**
     * Event subscription.
     *
     * @return string|array|false event class(es) or false if no subscription.
     */
    public function get_event_subscription() {
        return [job_created::class, job_updated::class];
    }
}
