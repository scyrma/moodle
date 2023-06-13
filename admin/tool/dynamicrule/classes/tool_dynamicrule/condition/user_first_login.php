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

namespace tool_dynamicrule\tool_dynamicrule\condition;

use core_reportbuilder\local\helpers\database;
use tool_dynamicrule\condition_sql;
use tool_tenant\permission;
use tool_wp\local\helpers\string_helper;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot. '/' . $CFG->admin . '/tool/wp/periodduration.php');
require_once($CFG->libdir.'/completionlib.php');

/**
 * The user_first_login condition class.
 *
 * @package    tool_dynamicrule
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Kateryna Martynenko <kateryna.martynenko@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_first_login extends condition_sql {

    /** @var int */
    public const FIRST_LOGIN_TYPE_NONE = 0;
    /** @var int */
    public const FIRST_LOGIN_TYPE_RANGE = 1;
    /** @var int */
    public const FIRST_LOGIN_TYPE_INLAST = 2;
    /** @var int */
    public const FIRST_LOGIN_TYPE_NEVER = 3;
    /** @var int */
    public const FIRST_LOGIN_TYPE_EVER = 4;

    /**
     * Can the current user add this condition.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return permission::can_browse_users() && has_capability('moodle/user:viewalldetails', \context_system::instance());
    }

    /**
     * Can the user edit this particular instance of this condition.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        return permission::can_browse_users() && has_capability('moodle/user:viewalldetails', \context_system::instance());
    }

    /**
     * Get the title of this condition.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('userfirstlogin', 'tool_dynamicrule');
    }

    /**
     * Get the description of this condition.
     *
     * @return string
     */
    public function get_description(): string {
        $firstloginrelative = $this->get_firstlogin();
        switch ($this->get_firstlogintype()) {
            case self::FIRST_LOGIN_TYPE_RANGE:
                $dates = [];
                $dates['startdate'] = userdate($this->get_conditionfirstdate(), get_string('strftimedatetimeshort'));
                $dates['enddate'] = userdate($this->get_conditionenddate(), get_string('strftimedatetimeshort'));
                $str = get_string('conditionuserfirstlogindescriptionover', 'tool_dynamicrule', $dates);
                break;
            case self::FIRST_LOGIN_TYPE_INLAST:
                $date = string_helper::translate_relativedate_string($firstloginrelative);
                $str = get_string('conditionuserfirstlogindescriptioninlast', 'tool_dynamicrule', $date);
                break;
            case self::FIRST_LOGIN_TYPE_EVER:
                $str = get_string('conditionuserfirstlogindescriptionever', 'tool_dynamicrule');
                break;
            case self::FIRST_LOGIN_TYPE_NEVER:
                $str = get_string('conditionuserlastlogindescriptionnever', 'tool_dynamicrule');
                break;
        }
        return $str;
    }

    /**
     * Return the configured operator
     *
     * @return string
     */
    private function get_firstlogintype(): string {
        return $this->get_configdata()['firstlogintype'] ?? '';
    }

    /**
     * Return the configured first login time
     *
     * @return string
     */
    private function get_firstlogin(): string {
        return $this->get_configdata()['firstloginrelative'] ?? '';
    }

    /**
     * Setup the form elements.
     *
     * @param \MoodleQuickForm $mform
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        // First login.
        $firstloginoptions = [
            self::FIRST_LOGIN_TYPE_NONE => get_string('datetypenone', 'tool_dynamicrule'),
            self::FIRST_LOGIN_TYPE_RANGE => get_string('datetyperange', 'tool_dynamicrule'),
            self::FIRST_LOGIN_TYPE_INLAST => get_string('datetypeinlast', 'tool_dynamicrule'),
            self::FIRST_LOGIN_TYPE_EVER => get_string('datetypeever', 'tool_dynamicrule'),
            self::FIRST_LOGIN_TYPE_NEVER => get_string('datetypenever', 'tool_dynamicrule'),
        ];
        $group = [];
        $group[] = $mform->createElement('select', 'firstlogintype', '', $firstloginoptions);
        $group[] = $mform->createElement('periodduration', 'firstloginrelative', '');
        $mform->addGroup($group, 'firstloginformgroup', get_string('firstlogin', 'tool_dynamicrule'), ' ', false);
        $mform->hideIf('firstloginrelative', 'firstlogintype', 'in', [
            self::FIRST_LOGIN_TYPE_NONE,
            self::FIRST_LOGIN_TYPE_EVER,
            self::FIRST_LOGIN_TYPE_NEVER,
            self::FIRST_LOGIN_TYPE_RANGE,
        ]);
        $mform->disabledIf('firstloginrelative[timeunit]', 'firstlogintype', 'in', [
            self::FIRST_LOGIN_TYPE_NONE,
            self::FIRST_LOGIN_TYPE_EVER,
            self::FIRST_LOGIN_TYPE_NEVER,
            self::FIRST_LOGIN_TYPE_RANGE,
        ]);
        $mform->disabledIf('firstloginrelative[number]', 'firstlogintype', 'in', [
            self::FIRST_LOGIN_TYPE_NONE,
            self::FIRST_LOGIN_TYPE_EVER,
            self::FIRST_LOGIN_TYPE_NEVER,
            self::FIRST_LOGIN_TYPE_RANGE,
        ]);
        $mform->addHelpButton('firstloginformgroup', 'firstlogin', 'tool_dynamicrule');

        $mform->addElement('date_time_selector', 'first_login_startdate', get_string('startdate', 'tool_dynamicrule'));
        $mform->hideIf('first_login_startdate', 'firstlogintype', 'in', [
            self::FIRST_LOGIN_TYPE_NONE,
            self::FIRST_LOGIN_TYPE_INLAST,
            self::FIRST_LOGIN_TYPE_EVER,
            self::FIRST_LOGIN_TYPE_NEVER,
        ]);
        $mform->setDefault('first_login_startdate', usergetmidnight(time()));

        $mform->addElement('date_time_selector', 'first_login_enddate', get_string('enddate', 'tool_dynamicrule'));
        $mform->hideIf('first_login_enddate', 'firstlogintype', 'in', [
            self::FIRST_LOGIN_TYPE_NONE,
            self::FIRST_LOGIN_TYPE_INLAST,
            self::FIRST_LOGIN_TYPE_EVER,
            self::FIRST_LOGIN_TYPE_NEVER,
        ]);
        $mform->setDefault('first_login_enddate', usergetmidnight(time()));
    }

    /**
     * Return the configured condition first date
     *
     * @return int
     */
    private function get_conditionfirstdate(): int {
        return $this->get_configdata()['first_login_startdate'] ?? 0;
    }

    /**
     * Return the configured condition end date
     *
     * @return int
     */
    private function get_conditionenddate(): int {
        return $this->get_configdata()['first_login_enddate'] ?? 0;
    }

    /**
     * Validates the configform of the condition
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        $errors = [];

        // No type selected.
        if (empty($data['firstlogintype'])) {
            $errors['firstloginformgroup'] = get_string('errorinvaliduserfirstlogintype', 'tool_dynamicrule');
        }

        // Avoid 0 hours/days/weeks.
        if (in_array($data['firstlogintype'], [self::FIRST_LOGIN_TYPE_INLAST])) {
            $time = time();
            if (strtotime('-' . $data['firstloginrelative'], $time) === $time) {
                $errors['firstloginformgroup'] = get_string('errorinvaliduserfirstlogin', 'tool_dynamicrule');
            }
        }

        // Selected dates are within range.
        if (self::FIRST_LOGIN_TYPE_RANGE == $data['firstlogintype'] &&
            $data['first_login_enddate'] <= $data['first_login_startdate']) {
            $errors['first_login_enddate'] = get_string('errorinvaliddates', 'tool_dynamicrule');
        }

        return $errors;
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {
        $sql = [];
        switch ($this->get_firstlogintype()) {
            case self::FIRST_LOGIN_TYPE_RANGE:
                $startdate = $this->get_conditionfirstdate();
                $enddate = $this->get_conditionenddate();
                $startdateparam = database::generate_param_name();
                $enddateparam = database::generate_param_name();

                $where = "u.firstaccess BETWEEN :{$startdateparam} AND :{$enddateparam}";
                $sql = ['', $where, [$startdateparam => $startdate, $enddateparam => $enddate]];
                break;
            case self::FIRST_LOGIN_TYPE_INLAST:
                $firstaccessparam = database::generate_param_name();
                $where = "u.firstaccess > 0 AND u.firstaccess >= :{$firstaccessparam}";
                $sql = ['', $where, [$firstaccessparam => strtotime('-' . $this->get_firstlogin())]];
                break;
            case self::FIRST_LOGIN_TYPE_EVER:
                $sql = ['', 'u.firstaccess > 0', []];
                break;
            case self::FIRST_LOGIN_TYPE_NEVER:
                $sql = ['', 'u.firstaccess = 0', []];
                break;
        }

        return $sql;
    }

    /**
     * Event subscription.
     *
     * @return array list of event classes to listen to
     */
    public function get_event_subscription() {
        return [\core\event\user_loggedin::class];
    }

    /**
     * Should this rule be processed in scheduled tasks
     *
     * @return bool
     */
    public function is_scheduled_task(): bool {
        return true;
    }

    /**
     * Which rule types this condition supports.
     *
     * @return int Rule types bitwise added.
     */
    public function supports_rule_types(): int {
        return \tool_dynamicrule\rule::TYPE_NORMAL + \tool_dynamicrule\rule::TYPE_SHARED;
    }
}
