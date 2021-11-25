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
 * Event observer for tool_dynamicrule.
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\event;

use tool_dynamicrule\api;
use tool_dynamicrule\rule;
use tool_tenant\hierarchy;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for tool_dynamicrule.
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class observer {

    /**
     * Returns array of events that have separate processors.
     *
     * Those events should not be triggered within process_event called on
     * wildcard event.
     *
     * @see self::process_event()
     * @return array
     */
    private static function get_wildcard_excluded_events() {
        return [
            '\core\event\user_created',
            '\tool_tenant\event\tenant_user_updated',
            '\tool_tenant\event\tenant_updated',
            '\tool_tenant\event\tenant_deleted',
        ];
    }

    /**
     * Get the list of rules and users that need to be checked when the specific event happens
     *
     * @param \core\event\base $event
     * @return array
     */
    protected static function get_rules_and_users_for_event(\core\event\base $event): array {
        $result = [];
        $conditions = api::get_conditions_watching_event(get_class($event));
        foreach ($conditions as $conditionclass) {
            if (api::get_all_rules_with_condition($conditionclass)) {
                // Only retrieve user tenant if there are rules watching for this event. This saves a DB query.
                $userid = component_class_callback($conditionclass, 'get_event_related_userid', [$event]);
                $usertenantid = \tool_tenant\tenancy::get_actual_tenant_id($userid);
                $rules = api::get_all_rules_with_condition($conditionclass, $usertenantid);
                foreach ($rules as $rule) {
                    $result[$rule->id][$userid] = $userid;
                }
            }
        }
        return $result;
    }

    /**
     * The wildcard events callback.
     *
     * This excludes some events we have separate processors for.
     *
     * @see self::get_excluded_events()
     * @param  \core\event\base $event
     * @return void
     */
    public static function process_event(\core\event\base $event) {
        // These events are treated separately.
        if (in_array($event->eventname, self::get_wildcard_excluded_events())) {
            return;
        }

        // If we have conditions that are subscribed to event, find out which
        // rules they belong to and trigger processing.
        $records = self::get_rules_and_users_for_event($event);
        foreach ($records as $ruleid => $userids) {
            // Trigger rule processing for the user.
            // TODO: Ideally we need to verify rule against condition that
            // triggered it first, this might be possible if we make conditions
            // users matching as isolated functions (WP-680).
            // TODO: For better UX (and to avoid potential issue
            // of detached process on backend), we may add user/rule pair
            // to table and process on cron task (WP-681).
            $rule = \tool_dynamicrule\api::get_rule($ruleid, true);
            foreach ($userids as $userid) {
                \tool_dynamicrule\api::process_rule($rule, $userid);
            }
        }
    }

    /**
     * Tenant user updated event callback.
     *
     * @param \tool_tenant\event\tenant_user_updated $event
     * @return void
     */
    public static function tenant_user_updated(\tool_tenant\event\tenant_user_updated $event): void {
        // We process all rules for affected user within tenant.
        $rules = rule::get_records(['tenantid' => $event->other['tenantid'], 'enabled' => 1]);
        foreach ($rules as $rule) {
            \tool_dynamicrule\api::process_rule($rule, $event->relateduserid);
        }
    }

    /**
     * Tenant updated (restored) event callback.
     *
     * @param \tool_tenant\event\tenant_updated $event
     * @return void
     */
    public static function tenant_updated(\tool_tenant\event\tenant_updated $event): void {
        // Check for tenant restored event.
        if (empty($event->other['isrestored'])) {
            return;
        }

        $rules = api::get_rules_for_tenant($event->objectid);
        foreach ($rules as $rule) {
            // This might be bulky, queue each rule processing as adhock task.
            $adhocktask = new \tool_dynamicrule\task\process_rule();
            $adhocktask->set_custom_data($rule->get('id'));
            $adhocktask->set_component('tool_dynamicrule');
            \core\task\manager::queue_adhoc_task($adhocktask);
        }
    }

    /**
     * Tenant deletion event callback.
     *
     * @param  \tool_tenant\event\tenant_deleted $event
     * @return void
     */
    public static function tenant_deleted(\tool_tenant\event\tenant_deleted $event) {
        // Delete all rules that belong to this tenant.
        $rules = rule::get_records(['tenantid' => $event->objectid]);
        foreach ($rules as $rule) {
            $rule->delete();
        }
    }

    /**
     * User creation event callback.
     *
     * @param  \core\event\user_created $event
     * @return void
     */
    public static function user_created(\core\event\user_created $event) {
        $rules = api::get_rules_for_user($event->relateduserid);
        foreach ($rules as $rule) {
            \tool_dynamicrule\api::process_rule($rule, $event->relateduserid);
        }
    }
}
