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
 * Class permission
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule;

use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class permission
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * User can edit a rule
     *
     * @param rule $rule
     * @return bool
     */
    public static function can_edit_rule(rule $rule): bool {
        return $rule->get('id') &&
            self::can_manage_rules() &&
            tenancy::get_tenant_id() == $rule->get('tenantid') &&
            !$rule->is_archived();
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
        return self::can_edit_rule($rule) &&
            !$rule->is_broken() &&
            !$rule->is_archived() &&
            $rule->has_conditions() &&
            $rule->has_outcomes();
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
            $rule->is_archived();
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
            $rule->is_archived();
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
        return !$rule->get('component') && self::can_edit_rule($rule) && self::can_create_rule($ignorelimit);
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
    public static function can_edit_rule_conditions(rule $rule) {
        return self::can_edit_rule($rule) && !$rule->get('component');
    }

    /**
     * Require user to be able to edit rule conditions
     *
     * @param rule $rule
     * @throws \moodle_exception
     */
    public static function require_can_edit_rule_conditions(rule $rule) {
        if (!self::can_edit_rule_conditions($rule)) {
            throw new \moodle_exception('errorcannotmanage', 'tool_dynamicrule');
        }
    }
}
