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
 * This file contains the class for program completed dynamic rules' condition.
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\tool_dynamicrule\condition;

use MoodleQuickForm;
use tool_program\api;
use tool_program\constants;
use tool_program\local\helpers\dynamic_rules;
use tool_program\local\helpers\dynamic_rules as helper;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_set;
use tool_program\persistent\program_set_completion;
use tool_program\program_tree;
use tool_wp\exporter_base;
use tool_wp\importer_base;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * Class for program completed dynamic rules' condition.
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_completed extends condition_base {

    /**
     * Return the configured criteria
     *
     * @return string
     */
    protected function get_criteria(): string {
        return $this->get_configdata()['criteria'] ?? self::CRITERIA_ALL;
    }

    /**
     * Return the first configured programid
     *
     * @return int
     */
    protected function get_programid(): int {
        $programids = $this->get_multipleprogramid();
        return $programids ? (int)$programids[0] : 0;
    }

    /**
     * Return the configured programid as array
     *
     * @return array
     */
    protected function get_multipleprogramid(): array {
        $programid = !empty($this->get_configdata()) ? $this->get_configdata()['programid'] : [0];
        return (array) $programid;
    }

    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditionprogramcompleted', 'tool_program');
    }

    /**
     * If the current user is able to edit this outcome.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        $programid = (array) $configdata['programid'] ?: [0];
        $validprograms = helper::get_program_if_valid($programid, $this->get_rule());

        if (!$validprograms) {
            return false;
        }

        $programscannotedit = array_filter($validprograms, function($program) {
            return permission::can_edit_dynamicrule_condition($program, $this->get_rule());
        });

        return count($programscannotedit) === count($programid);
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        global $OUTPUT;
        $options = dynamic_rules::get_selector_options();

        if ($this->is_configuration_valid()) {
            $options['valuehtmlcallback'] = dynamic_rules::get_program_fullname_callback();
        }

        // Enable multiple selection (autocomplete) field.
        $options['multiple'] = true;

        // Program select (autocomplete) field.
        $selectprogramstr = get_string('selectprogramcondition', 'tool_program');
        $missingprogramstr = get_string('missingprogram', 'tool_program');
        $mform->addElement('autocomplete', 'programid', $selectprogramstr, [], $options);
        $mform->addRule('programid', $missingprogramstr, 'required', null, 'client');
        $mform->addHelpButton('programid', 'selectprogramcondition', 'tool_program');
        $mform->setType('programid', PARAM_INT);

        $groupcriteria = [];
        $groupcriteria[] = $mform->createElement('radio', 'criteria', get_string('criteriaall', 'tool_program'),
            '', self::CRITERIA_ALL);

        $groupcriteria[] = $mform->createElement('radio', 'criteria', get_string('criteriaany', 'tool_program'),
            $OUTPUT->help_icon('criteriaany', 'tool_program'), self::CRITERIA_ANY);

        $groupcriteria[] = $mform->createElement('radio', 'criteria', get_string('criteriaeach', 'tool_program'),
            $OUTPUT->help_icon('criteriaeach', 'tool_program'). get_string('conditioncriterianotavailableyet', 'tool_dynamicrule'),
            self::CRITERIA_EACH, ['disabled' => 'disabled']);

        $mform->addGroup($groupcriteria, 'criteria_group',
            get_string('conditioncriteria', 'tool_dynamicrule'),
            \html_writer::div('', 'w-100'),
            false);

        $mform->setType('criteria', PARAM_ALPHANUM);
        $mform->setDefault('criteria', self::CRITERIA_ALL);

        // Optional date field.
        $dateisonorafterstr = get_string('completiondateonorafter', 'tool_program');
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
     * Validates the configform of the condition
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        $errors = [];
        $data['programid'] = (array) $data['programid'];

        $validprograms = helper::get_program_if_valid($data['programid'], $this->get_rule());
        if (!$validprograms || (count($validprograms) !== count($data['programid']))) {
            $errors['programid'] = get_string('errorinvalidprogram', 'tool_program');
            return $errors;
        }

        $programscannotedit = array_filter($validprograms, function($program) {
            return !permission::can_edit_dynamicrule_condition($program, $this->get_rule());
        });

        if (!empty($programscannotedit)) {
            // We need to check permission here as listed program might be viewable to user,
            // but user does not have capability to view allocates users.
            $errors['programid'] = get_string('errornopermissionviewallocatedusers', 'tool_program');
        }

        return $errors;
    }

    /**
     * Helps to build SQL to retrieve programs that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {
        $programids = $this->get_multipleprogramid();

        $status = constants::STATUS_COMPLETED;
        $conditiondate = null;
        $programwheres = [];
        $progroamparams = [];

        if ($this->get_conditiondateenabled()) {
            $conditiondate = $this->get_conditiondate();
        }

        foreach ($programids as $programid) {
            [$where, $params] = api::get_programs_with_criteria_conditions($programid, $status, $conditiondate, 'u');

            $programwheres[] = $where;
            $progroamparams = array_merge($progroamparams, $params);

        }

        $separator = ($this->get_criteria() === self::CRITERIA_ALL) ? ' AND ' : ' OR ';
        $programwheres = implode($separator, $programwheres);
        return ['', $programwheres, $progroamparams];
    }

    /**
     * Check if program still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        $programids = $this->get_multipleprogramid();

        if (in_array(0, $programids)) {
            return false;
        }

        $validprograms = helper::get_program_if_valid($programids, $this->get_rule());

        return !is_null($validprograms) && (count($validprograms) === count($programids));
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        global $DB;

        [$whereprogramid, $programids] = $DB->get_in_or_equal($this->get_multipleprogramid(), SQL_PARAMS_NAMED);

        $names = $DB->get_fieldset_sql("SELECT fullname FROM {tool_program} WHERE id " . $whereprogramid . " ORDER BY fullname",
            $programids);

        $programnames = implode("', '", array_map(function(string $name) {
            return format_string($name, true, ['escape' => false]);
        }, $names));

        $options = ['programname' => $programnames];

        $withdatefragment = '';
        if ($this->get_conditiondateenabled()) {
            $options['conditiondate'] = userdate($this->get_conditiondate(), get_string('strftimedatefullshort'));
            $withdatefragment = 'withdate';
        }

        if (count($names) > 1) {
            // Many programs.
            $identifier = 'conditionprogramcompleted' . $this->get_criteria() . 'description' . $withdatefragment;
        } else {
            // One program.
            $options = empty($withdatefragment) ? $programnames : $options;
            $identifier = 'conditionprogramcompleteddescription' . $withdatefragment;
        }

        return get_string($identifier, 'tool_program', $options);
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
     * Add programid condition field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        foreach ($this->get_multipleprogramid() as $programid) {
            $exporter->add_mapping('tool_program', $programid);
        }

    }

    /**
     * Get programid condition field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $configdata = $this->get_configdata();
        $configdata['programid'] = [];
        foreach ($this->get_multipleprogramid() as $programid) {
            $configdata['programid'][] = $importer->get_mapping('tool_program', $programid, IGNORE_MISSING) ?? 0;
        }
        $this->update_configdata($configdata);
    }

    /**
     * Available data for outcomes
     *
     * @param outcome_base $calleroutcome
     * @return array
     */
    public function get_available_data_for_outcome(\tool_dynamicrule\outcome_base $calleroutcome): array {
        return count($this->get_multipleprogramid()) > 1 ? [] : $this->get_placeholders();
    }

    /**
     * Available placeholders for this condition
     *
     * @return \lang_string[]
     */
    public function get_placeholders(): array {
        return [
            'programid' => new \lang_string('displayprogramid', 'tool_program'),
            'programname' => new \lang_string('displayprogramname', 'tool_program'),
            'programcompletedcourses' => new \lang_string('displaycompletedcourses', 'tool_program'),
            'programcompletiondate' => new \lang_string('displaycompletiondate', 'tool_program'),
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

        $programid = $this->get_multipleprogramid();

        if (!$users || count($programid) > 1) {
            return [];
        }

        if (in_array('programname', $keys, true)
            || in_array('programcompletedcourses', $keys, true)
            || in_array('programcompletiondate', $keys, true)) {
            $program = new program($programid[0]);
        }

        foreach ($users as $user) {
            $v = [];
            foreach ($keys as $key) {
                switch ($key) {
                    case 'programid':
                        $v[$key] = $this->get_programid();
                        break;
                    case 'programname':
                        $v[$key] = format_string($program->get('fullname'));
                        break;
                    case 'programcompletedcourses':
                        $v[$key] = $this->get_programcompletedcourses($program, $user->id);
                        break;
                    case 'programcompletiondate':
                        $v[$key] = $this->get_programcompletiondate($program, $user->id);
                        break;
                    default:
                        break;
                }
            }
            $data[$user->id] = $v;
        }
        return $data;
    }

    /**
     * Event subscription.
     *
     * @return string eventname.
     */
    public function get_event_subscription(): string {
        return '\tool_program\event\program_completed';
    }

    /**
     * Returns program completion date for a userid
     *
     * @param program $program
     * @param int $userid
     * @return string
     * @throws \coding_exception
     */
    private function get_programcompletiondate(program $program, int $userid): string {
        /** @var program_set $baseset */
        $baseset = $program->get_base_set();
        $completion = program_set_completion::get_record([
            'setid' => $baseset->get('id'),
            'userid' => $userid
        ]);
        return userdate($completion->get('completeddate'), get_string('strftimedatefullshort', 'langconfig'));
    }

    /**
     * Returns list of the program completed courses for a userid
     *
     * @param program $program
     * @param int $userid
     * @return string
     * @throws \dml_exception
     */
    private function get_programcompletedcourses(program $program, int $userid): string {
        $completedcourses = [];
        $programtree = new program_tree($program);
        $programitems = $programtree->to_list();
        foreach ($programitems as $programitem) {
            if ($programitem->is_course()) {
                $course = $programitem->get_course();
                $courseinfo = new \completion_info($course);
                if ($courseinfo->is_course_complete($userid)) {
                    $completedcourses[] = format_string(get_course_display_name_for_list($course));
                }
            }
        }
        if (!empty($completedcourses)) {
            return \html_writer::alist($completedcourses);
        }
        return get_string('noresults');
    }
}
