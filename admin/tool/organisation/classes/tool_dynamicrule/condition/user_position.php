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
 * This file contains the backend class for user_position condition.
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
use tool_wp\exporter_base;
use tool_wp\importer_base;
use tool_organisation\permission;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for user_position condition
 *
 * @package    tool_organisation
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_position extends \tool_dynamicrule\condition_sql {

    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditionuserposition', 'tool_organisation');
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        global $CFG;

        $mform->addElement('selectgroups', 'positionid', get_string('entityposition', 'tool_organisation'),
            organisation::get_all_positions_menu(['' => '']));
        $mform->addRule('positionid', null, 'required', null, 'client');
        $mform->setType('positionid', PARAM_INT);

        $mform->addElement('advcheckbox', 'withsubpositions',
            '', get_string('withsubpositions', 'tool_organisation'));

        $options = ['optional' => true, 'timezone' => $CFG->timezone];
        $mform->addElement('date_selector', 'jobstartdate', get_string('jobstartdateafter', 'tool_organisation'), $options);
        $mform->setDefault('jobstartdate', time());
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        global $DB;
        $name = $DB->get_field('tool_organisation_position', 'name', ['id' => $this->get_positionid()]);
        $options = ['posname' => format_string($name, true, ['escape' => false])];

        $options['subposinclude'] = $this->get_with_subpositions() ? get_string('included') : get_string('notincluded');

        if ($this->get_conditiondateenabled()) {
            $options['conditiondate'] = userdate($this->get_conditiondate(), get_string('strftimedatefullshort'));
            $description = get_string('conditionuserpositiondescriptionwithdate', 'tool_organisation', $options);
        } else {
            $description = get_string('conditionuserpositiondescription', 'tool_organisation', $options);
        }

        return $description;
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
     * Validates the configform of the outcome
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        global $DB;
        $errors = [];
        if (empty($data['positionid']) || !$DB->record_exists('tool_organisation_position', ['id' => $data['positionid']])) {
            $errors['positionid'] = get_string('errorinvalidposition', 'tool_organisation');
        }
        return $errors;
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
        list($where, $params) = helper::user_has_position_select($this->get_positionid(),
            $this->get_with_subpositions(), 'u', $startdate, $this->get_tenantid());

        $join = '';
        return [$join, $where, $params];
    }

    /**
     * Return the configured positionid
     *
     * @return int
     */
    private function get_positionid(): int {
        return $this->get_configdata()['positionid'];
    }

    /**
     * Return the configured departmentid
     *
     * @return bool
     */
    private function get_with_subpositions(): bool {
        return !empty($this->get_configdata()['withsubpositions']);
    }

    /**
     * Check if position still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;
        return $DB->record_exists('tool_organisation_position', ['id' => $this->get_positionid()]);
    }

    /**
     * Event subscription.
     *
     * @return string|array|false event class(es) or false if no subscription.
     */
    public function get_event_subscription() {
        return [job_created::class, job_updated::class];
    }

    /**
     * Add positionid condition field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $exporter->add_mapping('tool_organisation_position', $this->get_positionid());
    }

    /**
     * Get positionid condition field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $configdata = $this->get_configdata();
        $configdata['positionid'] =
            $importer->get_mapping('tool_organisation_position', $this->get_positionid(), IGNORE_MISSING) ?? 0;

        $this->update_configdata($configdata);
    }

    /**
     * If the current user is able to add this condition.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return permission::can_view_jobs();
    }

    /**
     * If the current user is able to edit this condition.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        return permission::can_view_jobs();
    }
}
