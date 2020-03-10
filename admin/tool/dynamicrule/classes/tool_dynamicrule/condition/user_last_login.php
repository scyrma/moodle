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
 * This file contains the backend class for user_last_login condition.
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

use tool_dynamicrule\api;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot. '/admin/tool/wp/periodduration.php');

/**
 * The backend class for user_last_login condition
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        $mform->hideIf('lastloginrelative', 'lastlogintype', 'eq', self::LAST_LOGIN_TYPE_NONE);
        $mform->disabledIf('lastloginrelative', 'lastlogintype', 'eq', self::LAST_LOGIN_TYPE_NONE);
        $mform->hideIf('lastloginrelative', 'lastlogintype', 'eq', self::LAST_LOGIN_TYPE_EVER);
        $mform->disabledIf('lastloginrelative', 'lastlogintype', 'eq', self::LAST_LOGIN_TYPE_EVER);
        $mform->hideIf('lastloginrelative', 'lastlogintype', 'eq', self::LAST_LOGIN_TYPE_NEVER);
        $mform->disabledIf('lastloginrelative', 'lastlogintype', 'eq', self::LAST_LOGIN_TYPE_NEVER);
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
            $errors['lastlogintype'] = get_string('errorinvaliduserlastlogintype', 'tool_dynamicrule');
        }
        $lastlogintype = $this->get_lastlogintype();
        if ((($lastlogintype !== self::LAST_LOGIN_TYPE_EVER) && ($lastlogintype !== self::LAST_LOGIN_TYPE_NEVER)) &&
                empty($data['lastloginrelative'])) {
            $errors['lastloginrelative'] = get_string('errorinvaliduserlastlogin', 'tool_dynamicrule');
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
                $lastaccessparam = api::generate_param_name();
                $where = 'u.lastaccess > 0 AND u.lastaccess <= :' . $lastaccessparam;
                $sql = ['', $where, [$lastaccessparam => $this->get_lastlogin()]];
              break;

            case self::LAST_LOGIN_TYPE_INLAST:
                $lastaccessparam = api::generate_param_name();
                $where = 'u.lastaccess > 0 AND u.lastaccess >= :' . $lastaccessparam;
                $sql = ['', $where, [$lastaccessparam => $this->get_lastlogin()]];
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
        switch ($this->get_lastlogintype()) {
            case self::LAST_LOGIN_TYPE_PAST:
                // TODO: translate lastlogin value (1 week, 2 days, etc).
                $date = $this->get_configdata()['lastloginrelative'];
                $str = get_string('conditionuserlastlogindescriptionbefore', 'tool_dynamicrule', $date);
                break;
            case self::LAST_LOGIN_TYPE_INLAST:
                // TODO: translate lastlogin value (1 week, 2 days, etc).
                $date = $this->get_configdata()['lastloginrelative'];
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
        if (empty($this->get_configdata()['lastlogintype'])) {
            $type = '';
        } else {
            $type = $this->get_configdata()['lastlogintype'];
        }
        return $type;
    }

    /**
     * Return the configured lastlogin time
     *
     * @return string
     */
    private function get_lastlogin(): int {
        switch ($this->get_lastlogintype()) {
            case self::LAST_LOGIN_TYPE_PAST:
            case self::LAST_LOGIN_TYPE_INLAST:
                $lastlogin = strtotime('-'.$this->get_configdata()['lastloginrelative']);
                break;
            default:
                $lastlogin = '';
                break;
        }
        return $lastlogin;
    }
}
