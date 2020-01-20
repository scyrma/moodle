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
 * Class for certification certified status dynamic rules' condition.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\tool_dynamicrule\condition;

use MoodleQuickForm;
use tool_certification\certification;
use tool_certification\constants;
use tool_certification\local\helpers\dynamic_rules;
use tool_dynamicrule\api as dynamicruleapi;
use tool_dynamicrule\condition_sql;
use tool_certification\api;
use tool_dynamicrule\outcome_base;
use tool_program\persistent\program;
use tool_program\persistent\program_set;
use tool_program\persistent\program_set_completion;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die;

/**
 * Class for certification certified status dynamic rules' condition.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_certified extends condition_sql {
    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditioncertificationcertified', 'tool_certification');
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        $options = dynamic_rules::get_selector_options();
        if ($this->is_configuration_valid()) {
            $options['valuehtmlcallback'] = dynamic_rules::get_certification_fullname_callback();
        }
        $selectstr = get_string('selectcertificationcondition', 'tool_certification');
        $missingcertstr = get_string('missingcertification', 'tool_certification');
        $mform->addElement('autocomplete', 'certificationid', $selectstr, [], $options);
        $mform->addRule('certificationid', $missingcertstr, 'required', null, 'client');
        $mform->addHelpButton('certificationid', 'selectcertificationcondition', 'tool_certification');
        $mform->setType('certificationid', PARAM_INT);

        $datelabelstr = get_string('certifieddateisonorafter', 'tool_certification');
        $enablestr = get_string('enable');
        $group = [];
        $group[] =& $mform->createElement('date_selector', 'conditiondate', '');
        $group[] =& $mform->createElement('advcheckbox', 'conditiondateenabled', null, $enablestr, 1, [0, 1]);
        $mform->addGroup($group, 'dateformgroup', $datelabelstr, ' ', false);
        $mform->disabledIf('conditiondate[day]', 'conditiondateenabled');
        $mform->disabledIf('conditiondate[month]', 'conditiondateenabled');
        $mform->disabledIf('conditiondate[year]', 'conditiondateenabled');
        $mform->setDefault('conditiondateenabled', '1');
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
        $params = [
            'id' => $data['certificationid'],
            'tenantid' => tenancy::get_tenant_id(),
            'archived' => 0
        ];
        if (!isset($data['certificationid']) || !$DB->record_exists(certification::TABLE, $params)) {
            $errors['certificationid'] = get_string('errorinvalidcertification', 'tool_certification');
        }
        return $errors;
    }

    /**
     * Helps to build SQL to retrieve certifications that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {
        $c = dynamicruleapi::generate_alias();
        $cu = dynamicruleapi::generate_alias();
        $cc = dynamicruleapi::generate_alias();
        $pr = dynamicruleapi::generate_alias();
        $cid = dynamicruleapi::generate_param_name();
        $pu = dynamicruleapi::generate_alias();
        $certificationid = $this->get_certificationid();
        $statusid = constants::STATUS_CERTIFIED;

        [$join, $where, $params] = api::get_certification_status_sql_query($certificationid, $statusid, false, 'u',
            $c, $cu, $cc, $pr, $cid, $pu);

        // If enabled check that user is certified on or after chosen date.
        if ($this->get_conditiondateenabled()) {
            $where .= " AND {$cc}.timecertified >= ".$this->get_conditiondate();
        }

        return [$join, $where, $params];
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        $certification = new certification($this->get_certificationid());
        $fullname = format_string($certification->get('fullname'), true, ['escape' => false]);
        $status = get_string('certified', 'tool_certification');
        $stringparams = ['status' => $status, 'fullname' => $fullname];
        $stringid = 'conditioncertificationstatusdescription';

        $description = get_string($stringid, 'tool_certification', $stringparams);

        if ($this->get_conditiondateenabled()) {
            $description .= ' ' . get_string('onorafter', 'tool_certification');
            $description .= ' ' . userdate($this->get_conditiondate(), get_string('strftimedatefullshort'));
        }

        return $description;
    }

    /**
     * Return the configured certificationid
     *
     * @return int|null
     */
    private function get_certificationid(): ?int {
        return $this->get_configdata()['certificationid'] ?? null;
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
     * Return the configured conditionenabled
     *
     * @return bool
     */
    private function get_conditiondateenabled(): bool {
        return $this->get_configdata()['conditiondateenabled'] ?? false;
    }

    /**
     * Return a list of valid attributes for given instance
     *
     * @return array Perhaps something similar to persistent definition, e.g. name, type, description
     */
    protected function get_config_attributes(): array {
        return [];
    }

    /**
     * Check if certification still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;
        if (!$DB->record_exists(certification::TABLE, ['id' => $this->get_certificationid()])) {
            return false;
        }
        $certification = new certification($this->get_certificationid());
        if ($certification->is_archived()) {
            return false;
        }

        return true;
    }

    /**
     * Available data for outcomes
     *
     * @param outcome_base $calleroutcome
     * @return array
     */
    public function get_available_data_for_outcome(outcome_base $calleroutcome): array {
        return [
            'programid' => new \lang_string('displayprogramid', 'tool_program'),
            'programname' => new \lang_string('displayprogramname', 'tool_program'),
            'programcompletedcourses' => new \lang_string('displaycompletedcourses', 'tool_program'),
            'programcompletiondate' => new \lang_string('displaycompletiondate', 'tool_program'),
            'certificationid' => new \lang_string('displaycertificationid', 'tool_certification'),
            'certificationname' => new \lang_string('displaycertificationname', 'tool_certification'),
            'certificationdate' => new \lang_string('displaycertificationdate', 'tool_certification'),
            'certificationexpirydate' => new \lang_string('displayexpirydate', 'tool_certification'),
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
    public function get_data_for_outcome(array $keys, array $users, outcome_base $calleroutcome): array {
        $data = [];
        if (!$users) {
            return [];
        }

        $certification = new certification($this->get_certificationid());
        foreach ($users as $user) {
            $v = [];
            [$program, $timecertified, $expirydate] = $this->get_completion_data($certification->get('id'), $user->id);

            foreach ($keys as $key) {
                switch ($key) {
                    case 'programid':
                        $v[$key] = $program->get('id');
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
                    case 'certificationid':
                        $v[$key] = $this->get_certificationid();
                        break;
                    case 'certificationname':
                        $v[$key] = format_string($certification->get('fullname'));
                        break;
                    case 'certificationdate':
                        $v[$key] = userdate($timecertified, get_string('strftimedatefullshort'));
                        break;
                    case 'certificationexpirydate':
                        $v[$key] = userdate($expirydate, get_string('strftimedatefullshort'));
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
     * Gets completion data for a given certification and user
     *
     * @param int $certificationid
     * @param int $userid
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function get_completion_data(int $certificationid, int $userid): array {
        // This has to be the last program the given user completed.
        $completion = api::get_last_completion_record($userid, $certificationid);
        if (!$completion) {
            // If user has been certified completion record must exist.
            throw new \coding_exception('completion record must exist for certification_certified');
        }
        return [new program($completion->get('programid')), $completion->get('timecertified'), $completion->get('expirydate')];
    }

    /**
     * Event subscription.
     *
     * @return string eventname.
     */
    public function get_event_subscription(): string {
        return '\tool_certification\event\certification_completion_created';
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
        return userdate($completion->get('completeddate'), get_string('strftimedatefullshort'));
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
        global $DB;
        $list = '';
        // TODO WP-1390: Courses should be ordered in the same sequence as in the program contents.
        if ($courseids = $program->get_courses_ids()) {
            $completedcourses = [];
            $courses = $DB->get_records_list('course', 'id', $courseids, 'id');
            foreach ($courses as $course) {
                $courseinfo = new \completion_info($course);
                if ($courseinfo->is_course_complete($userid)) {
                    $completedcourses[] = format_string($course->fullname);
                }
            }
            if (!empty($completedcourses)) {
                $list = \html_writer::alist($completedcourses);
            }
        }
        return $list;
    }
}
