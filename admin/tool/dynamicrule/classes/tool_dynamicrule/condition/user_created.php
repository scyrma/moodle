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
 * This file contains the backend class for user_created condition.
 *
 * @package    tool_dynamicrule
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Sumit Negi
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

use tool_dynamicrule\api;
use tool_dynamicrule\rule;
use tool_wp\local\helpers\string_helper;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/wp/periodduration.php');

/**
 * The backend class for user_created condition
 *
 * @package    tool_dynamicrule
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Sumit Negi
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_created extends \tool_dynamicrule\condition_sql {

    /** @var \int */
    public const CREATE_TYPE_INLAST = 1;
    /** @var \int */
    public const CREATE_TYPE_PAST = 2;

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
        return get_string('conditionusercreated', 'tool_dynamicrule');
    }

    /**
     * Adds condition's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {

        $usercreateinstr = get_string('creationdate', 'tool_dynamicrule');
        $dateinlaststr = get_string('datetypeinlast', 'tool_dynamicrule');
        $dateneverstr = get_string('datetypepast', 'tool_dynamicrule');
        // User creation date option.
        $usercreateoptions = [
            self::CREATE_TYPE_INLAST => $dateinlaststr,
            self::CREATE_TYPE_PAST => $dateneverstr,
        ];
        $group = [];
        $group[] = $mform->createElement('select', 'usercreatetype', '', $usercreateoptions);
        $group[] = $mform->createElement('periodduration', 'usercreaterelative', '');
        $mform->addGroup($group, 'usercreateformgroup', $usercreateinstr, ' ', false);
        $mform->addHelpButton('usercreateformgroup', 'creationdate', 'tool_dynamicrule');
    }

    /**
     * Validates the configform of the condition
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        return []; // No validation required here.
    }

    /**
     * Check configuration validation check.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        if (!in_array($this->get_usercreatetype(), [self::CREATE_TYPE_INLAST, self::CREATE_TYPE_PAST])) {
            return false;
        }
        if (empty($this->get_configdata()['usercreaterelative'])) {
            return false;
        }
        return parent::is_configuration_valid();
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {
        $createdon = strtotime('-'.$this->get_configdata()['usercreaterelative']);
        $lastcreatedparam = api::generate_param_name();
        $where = '';
        switch ($this->get_usercreatetype()) {
            case self::CREATE_TYPE_PAST:
                $where = 'u.timecreated <= :' . $lastcreatedparam;
                break;

            case self::CREATE_TYPE_INLAST:
                $where = 'u.timecreated >= :' . $lastcreatedparam;
                break;
        }
        return ['', $where, [$lastcreatedparam => $createdon]];
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        $str = '';
        $date = string_helper::translate_relativedate_string($this->get_configdata()['usercreaterelative']);
        switch ($this->get_usercreatetype()) {
            case self::CREATE_TYPE_INLAST:
                $str = get_string('conditionusercreateddescriptionbefore', 'tool_dynamicrule', $date);
                break;
            case self::CREATE_TYPE_PAST:
                $str = get_string('conditionusercreateddescriptionover', 'tool_dynamicrule', $date);
                break;
        }
        return $str;
    }

    /**
     * Return the configured operator
     *
     * @return string
     */
    private function get_usercreatetype(): string {
        return $this->get_configdata()['usercreatetype'] ?? '';
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
