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
 * This file contains the backend class for user_last_login condition.
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

use core_reportbuilder\local\helpers\database;
use tool_dynamicrule\rule;
use tool_wp\local\helpers\string_helper;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot. '/' . $CFG->admin . '/tool/wp/periodduration.php');

/**
 * The backend class for user_last_login condition
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_last_login extends \tool_dynamicrule\condition_sql {

    /** @var \int */
    public const LAST_LOGIN_TYPE_NONE = 0;
    /** @var \int */
    public const LAST_LOGIN_TYPE_PAST = 1;
    /** @var \int */
    public const LAST_LOGIN_TYPE_INLAST = 2;
    /** @var \int */
    public const LAST_LOGIN_TYPE_NEVER = 3;
    /** @var \int */
    public const LAST_LOGIN_TYPE_EVER = 4;

    /**
     * Which rule types this condition supports.
     *
     * @return int Rule types bitwise added.
     */
    public function supports_rule_types(): int {
        return rule::TYPE_NORMAL + rule::TYPE_SHARED;
    }

    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditionuserlastlogin', 'tool_dynamicrule');
    }

    /**
     * Adds condition's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {

        $lastloginstr = get_string('lastlogin', 'tool_dynamicrule');
        $datenonestr = get_string('datetypenone', 'tool_dynamicrule');
        $datepaststr = get_string('datetypepast', 'tool_dynamicrule');
        $dateinlaststr = get_string('datetypeinlast', 'tool_dynamicrule');
        $dateeverstr = get_string('datetypeever', 'tool_dynamicrule');
        $dateneverstr = get_string('datetypenever', 'tool_dynamicrule');

        // Last login.
        $lastloginoptions = [
            self::LAST_LOGIN_TYPE_NONE => $datenonestr,
            self::LAST_LOGIN_TYPE_PAST => $datepaststr,
            self::LAST_LOGIN_TYPE_INLAST => $dateinlaststr,
            self::LAST_LOGIN_TYPE_EVER => $dateeverstr,
            self::LAST_LOGIN_TYPE_NEVER => $dateneverstr,
        ];
        $group = [];
        $group[] = $mform->createElement('select', 'lastlogintype', '', $lastloginoptions);
        $group[] = $mform->createElement('periodduration', 'lastloginrelative', '');
        $mform->addGroup($group, 'lastloginformgroup', $lastloginstr, ' ', false);
        $mform->hideIf('lastloginrelative', 'lastlogintype', 'in',
            [self::LAST_LOGIN_TYPE_NONE, self::LAST_LOGIN_TYPE_EVER, self::LAST_LOGIN_TYPE_NEVER]);
        $mform->disabledIf('lastloginrelative[timeunit]', 'lastlogintype', 'in',
            [self::LAST_LOGIN_TYPE_NONE, self::LAST_LOGIN_TYPE_EVER, self::LAST_LOGIN_TYPE_NEVER]);
        $mform->disabledIf('lastloginrelative[number]', 'lastlogintype', 'in',
            [self::LAST_LOGIN_TYPE_NONE, self::LAST_LOGIN_TYPE_EVER, self::LAST_LOGIN_TYPE_NEVER]);
        $mform->addHelpButton('lastloginformgroup', 'lastlogin', 'tool_dynamicrule');
    }

    /**
     * Validates the configform of the condition
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        $errors = [];
        if (empty($data['lastlogintype'])) {
            $errors['lastloginformgroup'] = get_string('errorinvaliduserlastlogintype', 'tool_dynamicrule');
        }

        if (in_array($data['lastlogintype'], [self::LAST_LOGIN_TYPE_INLAST, self::LAST_LOGIN_TYPE_PAST])) {
            // Avoid 0 hours/days/weeks.
            $time = time();
            if (strtotime('-' . $data['lastloginrelative'], $time) === $time) {
                $errors['lastloginformgroup'] = get_string('errorinvaliduserlastlogin', 'tool_dynamicrule');
            }
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
        switch ($this->get_lastlogintype()) {
            case self::LAST_LOGIN_TYPE_PAST:
                $lastaccessparam = database::generate_param_name();
                $where = 'u.lastaccess > 0 AND u.lastaccess <= :' . $lastaccessparam;
                $sql = ['', $where, [$lastaccessparam => strtotime('-'.$this->get_lastlogin())]];
              break;

            case self::LAST_LOGIN_TYPE_INLAST:
                $lastaccessparam = database::generate_param_name();
                $where = 'u.lastaccess > 0 AND u.lastaccess >= :' . $lastaccessparam;
                $sql = ['', $where, [$lastaccessparam => strtotime('-'.$this->get_lastlogin())]];
              break;

            case self::LAST_LOGIN_TYPE_EVER:
                $sql = ['', 'u.lastaccess > 0', []];
                break;

            case self::LAST_LOGIN_TYPE_NEVER:
                $sql = ['', 'u.lastaccess = 0', []];
                break;
        }

        return $sql;
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        $lastloginrelative = $this->get_lastlogin();
        switch ($this->get_lastlogintype()) {
            case self::LAST_LOGIN_TYPE_PAST:
                $date = string_helper::translate_relativedate_string($lastloginrelative);
                $str = get_string('conditionuserlastlogindescriptionbefore', 'tool_dynamicrule', $date);
                break;
            case self::LAST_LOGIN_TYPE_INLAST:
                $date = string_helper::translate_relativedate_string($lastloginrelative);
                $str = get_string('conditionuserlastlogindescriptioninlast', 'tool_dynamicrule', $date);
                break;
            case self::LAST_LOGIN_TYPE_EVER:
                $str = get_string('conditionuserlastlogindescriptionever', 'tool_dynamicrule');
                break;
            case self::LAST_LOGIN_TYPE_NEVER:
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
    private function get_lastlogintype(): string {
        return $this->get_configdata()['lastlogintype'] ?? '';
    }

    /**
     * Return the configured lastlogin time
     *
     * @return string
     */
    private function get_lastlogin(): string {
        return $this->get_configdata()['lastloginrelative'] ?? '';
    }

    /**
     * If the current user is able to add this condition.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return \tool_tenant\permission::can_browse_users_anywhere() &&
            has_capability('moodle/user:viewalldetails', \context_system::instance());
    }

    /**
     * If the current user is able to edit this condition.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        return \tool_tenant\permission::can_browse_users_anywhere() &&
            has_capability('moodle/user:viewalldetails', \context_system::instance());
    }
}
