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
 * This file contains the fixture class for donothing outcome.
 *
 * @package    tool_dynamicrule
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\outcome;

use tool_dynamicrule\rule;

/**
 * The fixture class for donothing outcome
 *
 * @package    tool_dynamicrule
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class testable_outcome_donothing extends \tool_dynamicrule\outcome_base {

    /** @var bool $usercanedit */
    private static $usercanedit = true;
    /** @var bool $usercanadd */
    private static $usercanadd = true;

    /**
     * Sets user_can_add and user_can_edit return to default.
     */
    public static function reset() {
        self::$usercanadd = true;
        self::$usercanedit = true;
    }

    /**
     * Sets user_can_add return.
     *
     * @param bool $return
     */
    public static function set_user_can_add(bool $return) {
        self::$usercanadd = $return;
    }

    /**
     * Sets user_can_edit return.
     *
     * @param bool $return
     */
    public static function set_user_can_edit(bool $return) {
        self::$usercanedit = $return;
    }

    /**
     * If the current user is able to add this outcome.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return self::$usercanadd;
    }

    /**
     * If the current user is able to edit this outcome.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        return self::$usercanedit;
    }

    /**
     * Apply this outcome to user
     *
     * @param stdClass $user The user object to apply the outcome to
     */
    public function apply_to_user(\stdClass $user): void {
        $configdata = $this->get_configdata();
        if ($configdata['error'] === true) {
            throw new \moodle_exception("Do nothing cant be applied to busy user id {$user->id}");
        }
    }

    /**
     * Returns the title of the outcome
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return 'donothing';
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
    }

    /**
     * Validates the configform of the outcome
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        return [];
    }

    /**
     * Return the description for the outcome.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('donothingatall');
    }

    /**
     * This is only used by tests.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return false;
    }

    /**
     * Supports all rule types.
     *
     * @return int Rule types bitwise added.
     */
    public function supports_rule_types(): int {
        return rule::TYPE_NORMAL + rule::TYPE_SHARED;
    }
}
