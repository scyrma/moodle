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
 * This file contains the webservices for Dynamic rules tool.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule;

defined('MOODLE_INTERNAL') || die;

/**
 * The external API for the Dynamic rules tool.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class external extends \external_api {

    /**
     * Returns the structure of parameters for enable_rule function.
     *
     * @return \external_function_parameters
     */
    protected static function enable_rule_parameters() {
        $params = ['id' => new \external_value(PARAM_INT, 'The ID of the rule to be enabled', VALUE_REQUIRED)];
        return new \external_function_parameters($params);
    }

    /**
     * Enables the given rule
     *
     * @param int $ruleid The ID of the rule
     * @return bool
     */
    public static function enable_rule($ruleid) {
        $params = self::validate_parameters(self::enable_rule_parameters(), ['id' => $ruleid]);
        $rule = \tool_dynamicrule\api::get_rule($params['id']);
        permission::require_can_enable_rule($rule);
        return \tool_dynamicrule\api::enable_rule($rule->get('id'));
    }

    /**
     * Describes the return function of enable_rule
     *
     * @return \external_value
     */
    public static function enable_rule_returns() {
        return new \external_value(PARAM_BOOL, 'True if successfully updated.');
    }

    /**
     * Returns the structure of parameters for can_enable_rule function.
     *
     * @return \external_function_parameters
     */
    protected static function can_enable_rule_parameters() {
        $params = ['id' => new \external_value(PARAM_INT, 'The ID of the rule that is being checked', VALUE_REQUIRED)];
        return new \external_function_parameters($params);
    }

    /**
     * Return true if the rule can be enabled.
     *
     * @param int $ruleid The ID of the rule
     * @return bool
     */
    public static function can_enable_rule($ruleid) {
        $params = self::validate_parameters(self::can_enable_rule_parameters(), ['id' => $ruleid]);
        $rule = \tool_dynamicrule\api::get_rule($params['id']);
        // We require rule edit permission.
        permission::require_can_edit_rule($rule);
        return permission::can_enable_rule($rule);
    }

    /**
     * Return true if the rule can be enabled.
     *
     * @return \external_value
     */
    public static function can_enable_rule_returns() {
        return new \external_value(PARAM_BOOL, 'True if successfully updated.');
    }

    /**
     * Returns the structure of parameters for disable_rule function.
     *
     * @return \external_function_parameters
     */
    protected static function disable_rule_parameters() {
        $params = ['id' => new \external_value(PARAM_INT, 'The ID of the rule to be disabled', VALUE_REQUIRED)];
        return new \external_function_parameters($params);
    }

    /**
     * Disables the given rule
     *
     * @param int $ruleid The ID of the rule
     * @return bool
     */
    public static function disable_rule($ruleid) {
        $params = self::validate_parameters(self::disable_rule_parameters(), ['id' => $ruleid]);
        $rule = \tool_dynamicrule\api::get_rule($params['id']);
        permission::require_can_edit_rule($rule);
        return \tool_dynamicrule\api::disable_rule($rule->get('id'));
    }

    /**
     * Describes the return function of enable_rule
     *
     * @return \external_value
     */
    public static function disable_rule_returns() {
        return new \external_value(PARAM_BOOL, 'True if successfully updated.');
    }

    /**
     * Returns the structure of parameters for archive_rule function.
     *
     * @return \external_function_parameters
     */
    protected static function archive_rule_parameters() {
        $params = ['id' => new \external_value(PARAM_INT, 'The ID of the rule to be archived', VALUE_REQUIRED)];
        return new \external_function_parameters($params);
    }

    /**
     * Archives the given rule
     *
     * @param int $ruleid The ID of the rule
     * @return bool
     */
    public static function archive_rule($ruleid) {
        $params = self::validate_parameters(self::archive_rule_parameters(), ['id' => $ruleid]);
        $rule = \tool_dynamicrule\api::get_rule($params['id']);
        permission::require_can_archive_rule($rule);
        return \tool_dynamicrule\api::archive_rule($rule->get('id'));
    }

    /**
     * Describes the return function of archive_rule
     *
     * @return \external_value
     */
    public static function archive_rule_returns() {
        return new \external_value(PARAM_BOOL, 'True if successfully updated.');
    }

    /**
     * Returns the structure of parameters for unarchive_rule function.
     *
     * @return \external_function_parameters
     */
    protected static function unarchive_rule_parameters() {
        $params = ['id' => new \external_value(PARAM_INT, 'The ID of the rule to be unarchived', VALUE_REQUIRED)];
        return new \external_function_parameters($params);
    }

    /**
     * Unarchives the given rule
     *
     * @param int $ruleid The ID of the rule
     * @return bool
     */
    public static function unarchive_rule($ruleid) {
        $params = self::validate_parameters(self::unarchive_rule_parameters(), ['id' => $ruleid]);
        $rule = \tool_dynamicrule\api::get_rule($params['id']);
        permission::require_can_restore_rule($rule);
        return \tool_dynamicrule\api::unarchive_rule($rule->get('id'));
    }

    /**
     * Describes the return function of unarchive_rule
     *
     * @return \external_value
     */
    public static function unarchive_rule_returns() {
        return new \external_value(PARAM_BOOL, 'True if successfully updated.');
    }

    /**
     * Returns the structure of parameters for delete_rule function.
     *
     * @return \external_function_parameters
     */
    protected static function delete_rule_parameters() {
        $params = ['id' => new \external_value(PARAM_INT, 'The ID of the rule to be deleted', VALUE_REQUIRED)];
        return new \external_function_parameters($params);
    }

    /**
     * Deletes the given rule
     *
     * @param int $ruleid The ID of the rule
     * @return bool
     */
    public static function delete_rule($ruleid) {
        $params = self::validate_parameters(self::delete_rule_parameters(), ['id' => $ruleid]);
        $rule = \tool_dynamicrule\api::get_rule($params['id']);
        permission::require_can_delete_rule($rule);
        return \tool_dynamicrule\api::delete_rule($rule->get('id'));
    }

    /**
     * Describes the return function of delete_rule
     *
     * @return \external_value
     */
    public static function delete_rule_returns() {
        return new \external_value(PARAM_BOOL, 'True if successfully deleted.');
    }

    /**
     * Returns the structure of parameters for duplicate_rule function.
     *
     * @return \external_function_parameters
     */
    protected static function duplicate_rule_parameters() {
        $params = ['id' => new \external_value(PARAM_INT, 'The ID of the rule to be deleted', VALUE_REQUIRED)];
        return new \external_function_parameters($params);
    }

    /**
     * Duplicates the given rule
     *
     * @param int $ruleid The ID of the rule
     * @return int The ID of the created rule
     */
    public static function duplicate_rule($ruleid) {
        $params = self::validate_parameters(self::duplicate_rule_parameters(), ['id' => $ruleid]);
        $rule = \tool_dynamicrule\api::get_rule($params['id']);
        permission::require_can_duplicate_rule($rule);
        return \tool_dynamicrule\api::duplicate_rule($rule->get('id'));
    }

    /**
     * Describes the return function of duplicate_rule
     *
     * @return \external_value
     */
    public static function duplicate_rule_returns() {
        return new \external_value(PARAM_INT, 'The ID of created rule.');
    }


    /**
     * Returns the structure of parameters for count_matching_users function.
     * @return \external_function_parameters
     */
    protected static function count_matching_users_parameters() {
        $params = ['id' => new \external_value(PARAM_INT, 'The ID of the rule to count matching users', VALUE_REQUIRED)];
        return new \external_function_parameters($params);
    }

    /**
     * Count users matching the given rule
     *
     * @param int $ruleid The ID of the rule
     * @return bool
     */
    public static function count_matching_users($ruleid) {
        $params = self::validate_parameters(self::archive_rule_parameters(), ['id' => $ruleid]);
        $rule = \tool_dynamicrule\api::get_rule($params['id']);
        permission::require_can_view_matching_users($rule);

        return \tool_dynamicrule\api::count_matching_users($params['id']);
    }

    /**
     * Describes the return function of count_matching_users
     * @return \external_value
     */
    public static function count_matching_users_returns() {
        return new \external_value(PARAM_INT, 'The number of users matching the rule.');
    }

    /**
     * Returns the structure of parameters for delete_condition function.
     * @return \external_function_parameters
     */
    protected static function delete_condition_parameters() {
        $params = ['instanceid' => new \external_value(PARAM_INT, 'The ID of the condition to be deleted', VALUE_REQUIRED)];
        return new \external_function_parameters($params);
    }

    /**
     * Delete the given condition.
     *
     * @param int $instanceid The ID of the condition
     * @return bool
     */
    public static function delete_condition($instanceid) {
        $params = self::validate_parameters(self::delete_condition_parameters(), ['instanceid' => $instanceid]);
        $condition = new condition($params['instanceid']);
        $rule = \tool_dynamicrule\api::get_rule($condition->get('ruleid'));
        permission::require_can_edit_rule($rule);

        return $condition->delete();
    }

    /**
     * Describes the return function of delete_condition
     * @return \external_value
     */
    public static function delete_condition_returns() {
        return new \external_value(PARAM_BOOL, 'True if successfully deleted.');
    }

    /**
     * Returns the structure of parameters for delete_outcome function.
     * @return \external_function_parameters
     */
    protected static function delete_outcome_parameters() {
        $params = ['instanceid' => new \external_value(PARAM_INT, 'The ID of the outcome to be deleted', VALUE_REQUIRED)];
        return new \external_function_parameters($params);
    }

    /**
     * Delete the given outcome.
     *
     * @param int $instanceid The ID of the outcome
     * @return bool
     */
    public static function delete_outcome($instanceid) {
        $params = self::validate_parameters(self::delete_outcome_parameters(), ['instanceid' => $instanceid]);
        $outcome = new outcome($params['instanceid']);
        $rule = \tool_dynamicrule\api::get_rule($outcome->get('ruleid'));
        permission::require_can_edit_rule($rule);

        return $outcome->delete();
    }

    /**
     * Describes the return function of delete_outcome
     * @return \external_value
     */
    public static function delete_outcome_returns() {
        return new \external_value(PARAM_BOOL, 'True if successfully deleted.');
    }

    /**
     * Parameters for the badge selector WS.
     *
     * @return \external_function_parameters
     */
    public static function potential_badge_selector_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'search' => new \external_value(PARAM_NOTAGS, 'Search string', VALUE_REQUIRED),
        ]);
    }

    /**
     * Badge selector.
     *
     * @param string $search
     * @return array
     */
    public static function potential_badge_selector(string $search): array {
        $params = self::validate_parameters(self::potential_badge_selector_parameters(), ['search' => $search]);
        self::validate_context(\context_system::instance());
        // No permission check here, the api will return [] if user can not manage rules.
        return \tool_dynamicrule\api::get_potential_badges($params['search']);
    }

    /**
     * Return for badge selector.
     *
     * @return \external_multiple_structure
     */
    public static function potential_badge_selector_returns(): \external_multiple_structure {
        return new \external_multiple_structure(new \external_single_structure([
            'id' => new \external_value(PARAM_INT, 'ID of the user'),
            'name' => new \external_value(PARAM_NOTAGS, 'The name of the badge'),
        ]));
    }

    /**
     * Parameters for the competency selector WS.
     *
     * @return \external_multiple_structure
     */
    public static function potential_competency_selector_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'search' => new \external_value(PARAM_NOTAGS, 'Search string', VALUE_REQUIRED),
        ]);
    }

    /**
     * Competency selector.
     *
     * @param string $search
     * @return array
     */
    public static function potential_competency_selector(string $search): array {
        $params = self::validate_parameters(self::potential_competency_selector_parameters(), ['search' => $search]);
        self::validate_context(\context_system::instance());
        // No permission check here, the api will return [] if user can not manage rules.
        return \tool_dynamicrule\api::get_potential_competencies($params['search']);
    }

    /**
     * Return for competency selector.
     *
     * @return \external_multiple_structure
     */
    public static function potential_competency_selector_returns(): \external_multiple_structure {
        return new \external_multiple_structure(new \external_single_structure([
            'id' => new \external_value(PARAM_INT, 'ID of the user'),
            'shortname' => new \external_value(PARAM_NOTAGS, 'The name of the competency'),
        ]));
    }
}
