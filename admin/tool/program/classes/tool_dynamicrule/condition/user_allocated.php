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
 * This file contains the backend class for user_allocated condition.
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\tool_dynamicrule\condition;

use MoodleQuickForm;
use tool_dynamicrule\api as dynamicruleapi;
use tool_program\api;
use tool_program\local\helpers\dynamic_rules;
use tool_program\persistent\program;
use tool_program\persistent\program_user;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for user_allocated condition
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_allocated extends condition_base {

    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditionuserallocated', 'tool_program');
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        // Program select (autocomplete) field.
        $selectprogramstr = get_string('selectprogramcondition', 'tool_program');
        $missingprogramstr = get_string('missingprogram', 'tool_program');
        $options = dynamic_rules::get_selector_options();
        if ($this->is_configuration_valid()) {
            $options['valuehtmlcallback'] = dynamic_rules::get_program_fullname_callback();
        }
        $mform->addElement('autocomplete', 'programid', $selectprogramstr, [], $options);
        $mform->addRule('programid', $missingprogramstr, 'required', null, 'client');
        $mform->addHelpButton('programid', 'selectprogramcondition', 'tool_program');
        $mform->setType('programid', PARAM_INT);

        // Optional date field.
        $dateisonorafterstr = get_string('allocationdateonorafter', 'tool_program');
        $enablestr = get_string('enable');
        $group = [];
        $group[] =& $mform->createElement('date_time_selector', 'conditiondate', '');
        $group[] =& $mform->createElement('advcheckbox', 'conditiondateenabled', $enablestr, '', 1, [0, 1]);
        $mform->addGroup($group, 'dateformgroup', $dateisonorafterstr, ' ', false);
        $mform->disabledIf('conditiondate[day]', 'conditiondateenabled');
        $mform->disabledIf('conditiondate[month]', 'conditiondateenabled');
        $mform->disabledIf('conditiondate[year]', 'conditiondateenabled');
        $mform->setDefault('conditiondateenabled', '1');
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {
        $pu = dynamicruleapi::generate_alias();
        $programid = dynamicruleapi::generate_param_name();
        $join = "JOIN {tool_program_users} {$pu} ON ({$pu}.userid = u.id)";
        $where = "{$pu}.programid = :{$programid}";
        $params = [$programid => $this->get_programid()];

        // If date enabled, check that user was allocated on or after the chosen date.
        if ($this->get_conditiondateenabled()) {
            $where .= " AND {$pu}.timecreated >= " . $this->get_conditiondate();
        }

        return [$join, $where, $params];
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        $program = new program($this->get_programid());
        $fullname = format_string($program->get('fullname'), true, ['escape' => false]);

        if ($this->get_conditiondateenabled()) {
            $conditiondate = userdate($this->get_conditiondate(), get_string('strftimedatetimeshort'));
            $options = ['programname' => $fullname, 'conditiondate' => $conditiondate];
            $description = get_string('conditionuserallocateddescriptionwithdate', 'tool_program', $options);
        } else {
            $description = get_string('conditionuserallocateddescription', 'tool_program', $fullname);
        }

        return $description;
    }

    /**
     * Return the configured conditiondate
     *
     * @return int
     */
    private function get_conditiondate(): int {
        return $this->get_configdata()['conditiondate'];
    }

    /**
     * Wether configured date condition is enabled
     *
     * @return int|null
     */
    private function get_conditiondateenabled(): ?int {
        return $this->get_configdata()['conditiondateenabled'] ?? null;
    }

    /**
     * Available data for outcomes
     *
     * @param outcome_base $calleroutcome
     * @return array
     */
    public function get_available_data_for_outcome(\tool_dynamicrule\outcome_base $calleroutcome): array {
        return [
            'programid' => new \lang_string('displayprogramid', 'tool_program'),
            'programname' => new \lang_string('displayprogramname', 'tool_program'),
            'programduedate' => new \lang_string('displayprogramduedate', 'tool_program'),
        ];
    }

    /**
     * Data for the outcomes
     *
     * @param array $keys
     * @param array $users array of user objects
     * @param outcome_base $calleroutcome
     * @return array
     */
    public function get_data_for_outcome(array $keys, array $users, \tool_dynamicrule\outcome_base $calleroutcome): array {
        $data = [];
        if (!$users) {
            return [];
        }

        if (in_array('programname', $keys, true) ||
            in_array('programduedate', $keys, true)) {
            $program = new program($this->get_programid());
        }

        foreach ($users as $user) {
            $v = [];
            foreach ($keys as $key) {
                switch ($key) {
                    case 'programid':
                        $v[$key] = $this->get_programid();
                        break;
                    case 'programname':
                        $v[$key] = $program->get_formatted_name();
                        break;
                    case 'programduedate':
                        $v[$key] = $this->get_program_user_duedate($program, $user->id);
                        break;
                    default:
                        break;
                }
            }
            $data[$user->id] = $v;
        }
        return $data;
    }
}
