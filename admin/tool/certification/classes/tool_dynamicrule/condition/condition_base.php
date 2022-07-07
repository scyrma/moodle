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
 * This file contains the base class for certification conditions.
 *
 * @package    tool_certification
 * @author     2020 Ruslan Kabalin
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\tool_dynamicrule\condition;

use tool_certification\api;
use tool_certification\certification;
use tool_certification\certification_user;
use tool_certification\constants;
use tool_certification\permission;
use tool_certification\local\helpers\dynamic_rules as helper;
use tool_dynamicrule\rule;
use tool_program\persistent\program;
use tool_program\persistent\program_set;
use tool_program\persistent\program_set_completion;
use tool_program\persistent\program_user;
use tool_program\program_tree;

/**
 * The base class for certification conditions.
 *
 * @package    tool_certification
 * @author     2020 Ruslan Kabalin
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class condition_base extends \tool_dynamicrule\condition_sql {

    /**
     * Validates the configform of the outcome
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        $errors = [];

        if (!$certification = helper::get_certification_if_valid($data['certificationid'], $this->get_rule())) {
            $errors['certificationid'] = get_string('errorinvalidcertification', 'tool_certification');
            return $errors;
        }
        if (!permission::can_edit_dynamicrule_condition($certification, $this->get_rule())) {
            // We need to check permission here as listed certification might be viewable to user,
            // but user does not have capability to view allocated users.
            $errors['certificationid'] = get_string('errornopermissionviewallocatedusers', 'tool_certification');
        }

        return $errors;
    }

    /**
     * Return the configured certificationid
     *
     * @return int
     */
    protected function get_certificationid(): int {
        return $this->get_configdata()['certificationid'] ?? 0;
    }

    /**
     * Return the certification object from configured certificationid
     *
     * @return certification
     */
    protected function get_certification(): certification {
        return new certification($this->get_certificationid());
    }

    /**
     * Check if certification still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        return (bool)helper::get_certification_if_valid($this->get_certificationid(), $this->get_rule());
    }

    /**
     * If the current user is able to add this condition.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return permission::can_add_dynamicrule_condition();
    }

    /**
     * If the current user is able to edit this condition.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        $certification = new certification($configdata['certificationid']);
        return permission::can_edit_dynamicrule_condition($certification, $this->get_rule());
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
    protected function get_completion_data(int $certificationid, int $userid): array {
        // This has to be the last program the given user completed.
        $completion = api::get_last_completion_record($userid, $certificationid);
        if (!$completion) {
            // If user has been certified completion record must exist.
            throw new \coding_exception('completion record must exist for certification_certified');
        }
        return [new program($completion->get('programid')), $completion->get('timecertified'), $completion->get('expirydate')];
    }

    /**
     * Returns program completion date for a userid
     *
     * @param program $program
     * @param int $userid
     * @return string
     * @throws \coding_exception
     */
    protected function get_programcompletiondate(program $program, int $userid): string {
        /** @var program_set $baseset */
        $baseset = $program->get_base_set();
        $completion = program_set_completion::get_record([
            'setid' => $baseset->get('id'),
            'userid' => $userid
        ]);
        if ($completion) {
            return userdate($completion->get('completeddate'), get_string('strftimedatefullshort'));
        }

        return get_string('notavailable', 'tool_certification');
    }

    /**
     * Returns list of the program completed courses for a userid
     *
     * @param program $program
     * @param int $userid
     * @return string
     */
    protected function get_programcompletedcourses(program $program, int $userid): string {
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

    /**
     * Returns user allocation due date
     *
     * @param certification_user $certificationuser
     * @return int
     * @throws \coding_exception
     */
    protected function get_allocation_duedate(certification_user $certificationuser): int {
        $userid = $certificationuser->get('userid');

        if ($certificationuser->get('isrecertification')) {
            // If is recertification, due date should be the previous expiry date.
            $lastcompletion = self::get_last_completion_record($userid, $certificationuser->get('certificationid'));
            $duedate = (int)$lastcompletion->get('expirydate');
        } else {
            // If is the first round, due date is in program user allocation.
            $certification = $certificationuser->get_certification();
            $programuser = program_user::get_record([
                'userid' => $userid,
                'certificationid' => $certification->get('id'),
            ]);
            $duedate = $programuser->get('duedate');
        }
        return $duedate;
    }

    /**
     * Returns data for each value requested
     *
     * @param array $keys
     * @param certification $certification
     * @param int $userid
     * @param bool $needscompletion
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function get_key_values(array $keys, certification $certification, int $userid,
                                      bool $needscompletion): array {
        $v = [];
        $timecertified = null;
        $expirydate = null;

        $certificationuser = certification_user::get_record([
            'certificationid' => $certification->get('id'),
            'userid' => $userid,
        ]);
        if ($needscompletion) {
            [$program, $timecertified, $expirydate] = $this->get_completion_data($certification->get('id'), $userid);
        } else if ($certificationuser->get('currentprogramid')) {
            $program = new program($certificationuser->get('currentprogramid'));
        } else {
            $program = $certification->get_certification_program();
        }

        foreach ($keys as $key) {
            switch ($key) {
                case 'programid':
                    $v[$key] = $program->get('id');
                    break;
                case 'programname':
                    $v[$key] = $program->get_formatted_name();
                    break;
                case 'programcompletedcourses':
                    $v[$key] = $this->get_programcompletedcourses($program, $userid);
                    break;
                case 'programcompletiondate':
                    $v[$key] = $this->get_programcompletiondate($program, $userid);
                    break;
                case 'certificationid':
                    $v[$key] = $this->get_certificationid();
                    break;
                case 'certificationname':
                    $v[$key] = $certification->get_formatted_name();
                    break;
                case 'certificationdate':
                    $v[$key] = userdate($timecertified, get_string('strftimedatefullshort'));
                    break;
                case 'certificationexpirydate':
                    if ($expirydate > 0) {
                        $v[$key] = userdate($expirydate, get_string('strftimedatefullshort'));
                    } else {
                        $v[$key] = get_string('never');
                    }
                    break;
                case 'expirydatetimestamp':
                    $v[$key] = $expirydate;
                    break;
                case 'certificationreopen':
                    $v[$key] = userdate($certificationuser->get('nextstartdate'), get_string('strftimedatefullshort'));
                    break;
                case 'certificationprogramname':
                    $initialprogram = $certification->get_certification_program();
                    $v[$key] = $initialprogram->get_formatted_name();
                    break;
                case 'recertificationprogramname':
                    $initialprogram = new program($certification->get('recertificationprogram'));
                    $v[$key] = $initialprogram->get_formatted_name();
                    break;
                case 'certificationgraceperiodend':
                    if ($certificationuser->get('graceperiodends') == 0) {
                        $v[$key] = get_string('notset', 'tool_certification');
                    } else {
                        $v[$key] = userdate($certificationuser->get('graceperiodends'), get_string('strftimedatefullshort'));
                    }
                    break;
                case 'certificationduedate':
                    $v[$key] = $this->get_certification_user_duedate($certificationuser, $userid);
                    break;
                default:
                    break;
            }
        }
        return $v;
    }

    /**
     * Returns certification due date for this user
     *
     * @param certification_user $certificationuser
     * @param int $userid
     * @return string
     * @throws \coding_exception
     */
    public function get_certification_user_duedate(certification_user $certificationuser, int $userid): string {
        $programuser = program_user::get_record([
            'userid' => $userid,
            'programid' => $certificationuser->get('currentprogramid'),
            'certificationid' => $certificationuser->get('certificationid'),
        ]);
        if ((int)$programuser->get('duedatelocked') === constants::DATE_LOCKED &&
            $programuser->get('duedate') == constants::DATE_NONE) {
            $duedate = get_string('never', 'tool_certification');
        } else if ((int)$programuser->get('duedatelocked') === constants::DATE_LOCKED && $programuser->get('duedate') > 0) {
            $duedate = userdate($programuser->get('duedate'), get_string('strftimedatefullshort'));
        } else {
            $defaultdates = api::get_default_certification_dates($certificationuser->get_certification());
            $duedate = $defaultdates->duedate;
        }
        return $duedate;
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
