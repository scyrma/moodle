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
 * Class permission
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule;

use tool_tenant\tenancy;
use tool_tenant\hierarchy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class permission
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class permission {

    /**
     * User can manage dynamic rules
     *
     * @return bool
     */
    public static function can_manage_rules(): bool {
        return has_capability('tool/dynamicrule:manage', \context_system::instance());
    }

    /**
     * Require user to be able to manage dynamic rules
     */
    public static function require_can_manage_rules() {
        require_capability('tool/dynamicrule:manage', \context_system::instance(),
            null, true, 'errorcannotmanage', 'tool_dynamicrule');
    }

    /**
     * Checks if rule is 'visible' to the current user based on the rule tenant
     *
     * This function does not check if rule is archived or not
     *
     * @param rule $rule
     * @return bool
     */
    protected static function is_rule_visible_in_list(rule $rule): bool {
        return hierarchy::is_own_or_parent_shared_entity($rule->get('tenantid'), $rule->is_shared());
    }

    /**
     * User can delete condition.
     *
     * @param condition_base $condition
     */
    public static function can_delete_condition(condition_base $condition) {
        return $condition->is_dummy() || self::can_edit_condition($condition);
    }

    /**
     * User can edit condition.
     *
     * We allow user to edit broken condition irrespective to condition editing capability
     * (assuming user has adding capability for it).
     *
     * @param condition_base $condition
     * @param array $configdata alternative config data to validate.
     */
    public static function can_edit_condition(condition_base $condition, array $configdata = []) {
        if (empty($configdata)) {
            $configdata = $condition->get_configdata();
        }
        if ($condition->is_dummy()) {
            // No editing of dummy conditions.
            return false;
        }
        if ($condition->is_broken()) {
            return self::can_add_condition($condition, $condition->get_rule());
        }
        return $condition->user_can_edit($configdata);
    }

    /**
     * User can delete outcome.
     *
     * @param outcome_base $outcome
     */
    public static function can_delete_outcome(outcome_base $outcome) {
        return $outcome->is_dummy() || self::can_edit_outcome($outcome);
    }

    /**
     * User can edit outcome.
     *
     * We allow user to edit broken outcome irrespective to outcome editing capability
     * (assuming user has adding capability for it).
     *
     * @param outcome_base $outcome
     * @param array $configdata alternative config data to validate.
     * @throws \moodle_exception
     */
    public static function can_edit_outcome(outcome_base $outcome, array $configdata = []) {
        if (empty($configdata)) {
            $configdata = $outcome->get_configdata();
        }
        if ($outcome->is_dummy()) {
            // No editing of dummy outcomes.
            return false;
        }
        if ($outcome->is_broken()) {
            return self::can_add_outcome($outcome, $outcome->get_rule());
        }
        return $outcome->user_can_edit($configdata);
    }

    /**
     * User can edit a rule
     *
     * @param rule $rule
     * @return bool
     */
    public static function can_edit_rule(rule $rule): bool {
        return $rule->get('id') &&
            self::can_manage_rules() &&
            tenancy::get_tenant_id() == $rule->get('tenantid') &&
            !$rule->is_archived() &&
            self::can_edit_all_rule_conditions($rule) &&
            self::can_edit_all_rule_outcomes($rule);
    }

    /**
     * User can edit a rule in rule tenant.
     *
     * This is different to {@see self::can_edit_rule} as it checks access to rule
     * tenant (rather than if rule is in current tenant).
     *
     * @param rule $rule
     * @return bool
     */
    public static function can_edit_rule_in_tenant(rule $rule): bool {
        $tenantpermitted = self::is_rule_visible_in_list($rule) &&
            \tool_tenant\permission::can_access_tenant($rule->get('tenantid'));

        return $rule->get('id') &&
            self::can_manage_rules() &&
            $tenantpermitted &&
            !$rule->is_archived() &&
            self::can_edit_all_rule_conditions($rule) &&
            self::can_edit_all_rule_outcomes($rule);
    }

    /**
     * Require user to be able to edit a rule
     *
     * @param rule $rule
     * @throws \moodle_exception
     */
    public static function require_can_edit_rule(rule $rule) {
        if (!self::can_edit_rule($rule)) {
            throw new \moodle_exception('errorcannotmanage', 'tool_dynamicrule');
        }
    }

    /**
     * User is be able to add a condition to the rule
     *
     * @param condition_base $condition
     * @param rule|null $rule
     * @return bool
     */
    public static function can_add_condition(condition_base $condition, ?rule $rule = null): bool {
        if (!$rule) {
            debugging('Parameter rule is now mandatory', DEBUG_DEVELOPER);
        }
        $conditionruletypes = $condition->supports_rule_types();
        if ($rule && $rule->is_shared()) {
            return ($conditionruletypes & rule::TYPE_SHARED) && $condition->user_can_add();
        }
        return ($conditionruletypes & rule::TYPE_NORMAL) && $condition->user_can_add();
    }

    /**
     * Require user to be able to add a condition to the rule
     *
     * @param condition_base $condition
     * @param rule|null $rule
     * @throws \moodle_exception
     */
    public static function require_can_add_condition(condition_base $condition, ?rule $rule = null) {
        if (!self::can_add_condition($condition, $rule)) {
            throw new \moodle_exception('errorcannotmanagecondition', 'tool_dynamicrule');
        }
    }

    /**
     * User is be able to add an outcome to the rule
     *
     * @param outcome_base $outcome
     * @param rule|null $rule
     * @return bool
     */
    public static function can_add_outcome(outcome_base $outcome, ?rule $rule = null): bool {
        if (!$rule) {
            debugging('Parameter rule is now mandatory', DEBUG_DEVELOPER);
        }
        $outcomeruletypes = $outcome->supports_rule_types();
        if ($rule && $rule->is_shared()) {
            return ($outcomeruletypes & rule::TYPE_SHARED) && $outcome->user_can_add();
        }
        return ($outcomeruletypes & rule::TYPE_NORMAL) && $outcome->user_can_add();
    }

    /**
     * Require user to be able to add an outcome
     *
     * @param outcome_base $outcome
     * @param rule|null $rule
     * @throws \moodle_exception
     */
    public static function require_can_add_outcome(outcome_base $outcome, ?rule $rule = null) {
        if (!self::can_add_outcome($outcome, $rule)) {
            throw new \moodle_exception('errorcannotmanageoutcome', 'tool_dynamicrule');
        }
    }

    /**
     * Require user to be able to edit a condition.
     *
     * We allow user to edit broken condition irrespective to condition editing capability
     * (assuming user has adding capability for it).
     *
     * @param condition_base $condition
     * @param array $configdata alternative config data to validate.
     * @throws \moodle_exception
     */
    public static function require_can_edit_condition(condition_base $condition, array $configdata = []) {
        if (!self::can_edit_condition($condition, $configdata)) {
            throw new \moodle_exception('errorcannotmanagecondition', 'tool_dynamicrule');
        }
    }

    /**
     * Require user to be able to edit an outcome.
     *
     * We allow user to edit broken outcome irrespective to outcome editing capability
     * (assuming user has adding capability for it).
     *
     * @param outcome_base $outcome
     * @param array $configdata alternative config data to validate.
     * @throws \moodle_exception
     */
    public static function require_can_edit_outcome(outcome_base $outcome, array $configdata = []) {
        if (!self::can_edit_outcome($outcome, $configdata)) {
            throw new \moodle_exception('errorcannotmanageoutcome', 'tool_dynamicrule');
        }
    }

    /**
     * User can create dynamic rule. Observes configured site/tenant limit.
     *
     * @param  bool $ignorelimit Ignore limit, only check capability
     * @return bool
     */
    public static function can_create_rule(bool $ignorelimit = false): bool {
        // Capability check goes first.
        if (!self::can_manage_rules()) {
            return false;
        }

        if (!$ignorelimit && (self::is_site_limit_reached() || self::is_tenant_limit_reached())) {
            // Limit reached.
            return false;
        }

        return true;
    }

    /**
     * Returns true if site limit of dynamic rules has been reached.
     *
     * @return bool
     */
    public static function is_site_limit_reached(): bool {
        global $CFG;

        if (!empty($CFG->tool_dynamicrule_limitsenabled) && isset($CFG->tool_dynamicrule_sitelimit)) {
            // Check if we reached site limit of number of rules.
            return (int) $CFG->tool_dynamicrule_sitelimit <= rule::count_records(['component' => null]);
        }
        return false;
    }

    /**
     * Returns true if per tenant limit of dynamic rules has been reached.
     *
     * @return bool
     */
    public static function is_tenant_limit_reached(): bool {
        global $CFG;

        if (!empty($CFG->tool_dynamicrule_limitsenabled) && isset($CFG->tool_dynamicrule_tenantlimit)) {
            // Check if we reached per tenant limit of number of rules.
            $tenantid = tenancy::get_tenant_id();
            return (int) $CFG->tool_dynamicrule_tenantlimit <= rule::count_records(['tenantid' => $tenantid, 'component' => null]);
        }
        return false;
    }

    /**
     * Require user to be able to create a rule
     */
    public static function require_can_create_rule() {
        if (!self::can_create_rule()) {
            throw new \moodle_exception('errorcannotcreate', 'tool_dynamicrule');
        }
    }

    /**
     * User can enable rule
     *
     * @param rule $rule
     * @return bool
     */
    public static function can_enable_rule(rule $rule): bool {
        return !$rule->is_archived() &&
            $rule->has_conditions() &&
            $rule->has_outcomes() &&
            api::is_rule_configuration_valid($rule) &&
            self::can_edit_rule($rule);
    }

    /**
     * Require user to be able to enable a rule
     *
     * @param rule $rule
     * @throws \moodle_exception
     */
    public static function require_can_enable_rule(rule $rule) {
        if (!self::can_enable_rule($rule)) {
            throw new \moodle_exception('errorcannotmanage', 'tool_dynamicrule');
        }
    }

    /**
     * User can view rules list
     *
     * @return bool
     */
    public static function can_view_rules_list() {
        return self::can_manage_rules();
    }

    /**
     * User can view archived rules list
     *
     * @return bool
     */
    public static function can_view_archived_rules_list() {
        return self::can_manage_rules();
    }

    /**
     * User can archive a rule
     *
     * @param rule $rule
     * @return bool
     */
    public static function can_archive_rule(rule $rule): bool {
        return !$rule->get('component') && self::can_edit_rule($rule);
    }

    /**
     * Require user to be able to archive a rule
     *
     * @param rule $rule
     * @throws \moodle_exception
     */
    public static function require_can_archive_rule(rule $rule) {
        if (!self::can_archive_rule($rule)) {
            throw new \moodle_exception('errorcannotmanage', 'tool_dynamicrule');
        }
    }

    /**
     * User can restore a rule
     *
     * @param rule $rule
     * @return bool
     */
    public static function can_restore_rule(rule $rule): bool {
        return $rule->get('id') &&
            self::can_manage_rules() &&
            tenancy::get_tenant_id() == $rule->get('tenantid') &&
            $rule->is_archived() &&
            !$rule->get('component') &&
            self::can_edit_all_rule_conditions($rule) &&
            self::can_edit_all_rule_outcomes($rule);
    }

    /**
     * Require user to be able to restore a rule
     *
     * @param rule $rule
     * @throws \moodle_exception
     */
    public static function require_can_restore_rule(rule $rule) {
        if (!self::can_restore_rule($rule)) {
            throw new \moodle_exception('errorcannotmanage', 'tool_dynamicrule');
        }
    }

    /**
     * User can delete a rule
     *
     * @param rule $rule
     * @return bool
     */
    public static function can_delete_rule(rule $rule): bool {
        return $rule->get('id') &&
            self::can_manage_rules() &&
            tenancy::get_tenant_id() == $rule->get('tenantid') &&
            $rule->is_archived() &&
            !$rule->get('component') &&
            self::can_edit_all_rule_conditions($rule) &&
            self::can_edit_all_rule_outcomes($rule);
    }

    /**
     * Require user to be able to delete a rule
     *
     * @param rule $rule
     * @throws \moodle_exception
     */
    public static function require_can_delete_rule(rule $rule) {
        if (!self::can_delete_rule($rule)) {
            throw new \moodle_exception('errorcannotmanage', 'tool_dynamicrule');
        }
    }

    /**
     * User can duplicate a rule
     *
     * @param  rule $rule
     * @param  bool $ignorelimit Ignore limit, only check capability
     * @return bool
     */
    public static function can_duplicate_rule(rule $rule, bool $ignorelimit = false): bool {
        return !$rule->get('component') &&
            self::can_edit_rule_in_tenant($rule) &&
            self::can_create_rule($ignorelimit);
    }

    /**
     * Require user to be able to duplicate a rule
     *
     * @param rule $rule
     * @throws \moodle_exception
     */
    public static function require_can_duplicate_rule(rule $rule) {
        if (!self::can_duplicate_rule($rule)) {
            throw new \moodle_exception('errorcannotmanage', 'tool_dynamicrule');
        }
    }

    /**
     * User can view matching users
     *
     * @param rule $rule
     * @return bool
     */
    public static function can_view_matching_users(rule $rule): bool {
        return $rule->get('id') &&
            self::can_manage_rules() &&
            tenancy::get_tenant_id() == $rule->get('tenantid');
    }

    /**
     * Require user to be able to view matching users
     *
     * @param rule $rule
     * @throws \moodle_exception
     */
    public static function require_can_view_matching_users(rule $rule) {
        if (!self::can_view_matching_users($rule)) {
            throw new \moodle_exception('errorcannotmanage', 'tool_dynamicrule');
        }
    }

    /**
     * User can view matched users report
     *
     * @param rule $rule
     * @return bool
     */
    public static function can_view_matched_users_report(rule $rule): bool {
        return $rule->get('id') &&
            self::can_manage_rules() &&
            self::is_rule_visible_in_list($rule) &&
            self::can_edit_all_rule_conditions($rule);
    }

    /**
     * Require user to be able to view matched users report
     *
     * @param rule $rule
     * @throws \moodle_exception
     */
    public static function require_can_view_matched_users_report(rule $rule) {
        if (!self::can_view_matched_users_report($rule)) {
            throw new \moodle_exception('errorcannotmanage', 'tool_dynamicrule');
        }
    }

    /**
     * User can edit rule outcomes
     *
     * @param rule $rule
     * @return bool
     */
    public static function can_edit_rule_outcomes(rule $rule) {
        return self::can_edit_rule($rule);
    }

    /**
     * Require user to be able to edit rule outcomes
     *
     * @param rule $rule
     * @throws \moodle_exception
     */
    public static function require_can_edit_rule_outcomes(rule $rule) {
        if (!self::can_edit_rule_outcomes($rule)) {
            throw new \moodle_exception('errorcannotmanage', 'tool_dynamicrule');
        }
    }

    /**
     * User can edit rule conditions
     *
     * @param rule $rule
     * @return bool
     */
    public static function can_edit_rule_conditions(rule $rule): bool {
        return self::can_edit_rule($rule) &&
            !$rule->get('component') &&
            (api::count_matched_users($rule->get('id')) === 0);
    }

    /**
     * Require user to be able to edit rule conditions
     *
     * @param rule $rule
     * @throws \moodle_exception
     */
    public static function require_can_edit_rule_conditions(rule $rule): void {
        if (!self::can_edit_rule_conditions($rule)) {
            throw new \moodle_exception('errorcannotmanage', 'tool_dynamicrule');
        }
    }

    /**
     * User can delete condition in given rule.
     *
     * This function combines rule level permissions check with actual condition.
     *
     * @param rule $rule
     * @param condition_base $condition
     * @return bool
     */
    public static function can_delete_rule_condition(rule $rule, condition_base $condition): bool {
        return self::can_edit_rule_conditions($rule) && self::can_delete_condition($condition);
    }

    /**
     * Require user to be able to delete rule condition
     *
     * @param rule $rule
     * @param condition_base $condition
     * @throws \moodle_exception
     */
    public static function require_can_delete_rule_condition(rule $rule, condition_base $condition): void {
        if (!self::can_delete_rule_condition($rule, $condition)) {
            throw new \moodle_exception('errorcannotmanage', 'tool_dynamicrule');
        }
    }

    /**
     * User can delete outcome at given rule.
     *
     * This function combines rule level permissions check with actual condition.
     *
     * @param rule $rule
     * @param outcome_base $outcome
     * @return bool
     */
    public static function can_delete_rule_outcome(rule $rule, outcome_base $outcome): bool {
        return self::can_edit_rule_outcomes($rule) && self::can_delete_outcome($outcome);
    }

    /**
     * Require user to be able to delete rule outcome
     *
     * @param rule $rule
     * @param outcome_base $outcome
     * @throws \moodle_exception
     */
    public static function require_can_delete_rule_outcome(rule $rule, outcome_base $outcome): void {
        if (!self::can_delete_rule_outcome($rule, $outcome)) {
            throw new \moodle_exception('errorcannotmanage', 'tool_dynamicrule');
        }
    }

    /**
     * Require user to be able to edit a rule and that is not a component rule
     *
     * @param rule $rule
     * @throws \moodle_exception
     */
    public static function require_can_use_edit_rule_interface(rule $rule): void {
        if (!self::can_edit_rule($rule) || $rule->get('component')) {
            throw new \moodle_exception('errorcannotmanage', 'tool_dynamicrule');
        }
    }

    /**
     * User can edit cohort condition.
     *
     * Used for shared permissions checks, e.g. in condition and import/export mapping.
     *
     * @deprecated in WP-1459
     *
     * @param int $cohortid
     * @param int|null $tenantid
     * @return bool
     */
    public static function can_edit_cohort_condition(int $cohortid, ?int $tenantid = null) : bool {
        debugging('Function permission::can_edit_cohort_condition() should not be used', DEBUG_DEVELOPER);
        global $DB;
        $cohort = $DB->get_record('cohort', ['id' => $cohortid], '*', MUST_EXIST);
        $context = \context::instance_by_id($cohort->contextid, MUST_EXIST);
        return has_any_capability(['moodle/cohort:manage', 'moodle/cohort:view'], $context);
    }

    /**
     * Checks if user can edit all conditions in this rule
     *
     * @param rule $rule
     * @return bool
     * @throws \coding_exception
     */
    public static function can_edit_all_rule_conditions(rule $rule): bool {
        $conditions = api::get_rule_conditions($rule->get('id'));
        foreach ($conditions as $condition) {
            if (!$condition->is_dummy() && !self::can_edit_condition($condition)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Checks if user can edit all outcomes in this rule
     *
     * @param rule $rule
     * @return bool
     * @throws \coding_exception
     */
    public static function can_edit_all_rule_outcomes(rule $rule): bool {
        $outcomes = api::get_rule_outcomes($rule->get('id'));
        foreach ($outcomes as $outcome) {
            if (!$outcome->is_dummy() && !self::can_edit_outcome($outcome)) {
                return false;
            }
        }
        return true;
    }
}
