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
 * Event observer for tool_dynamicrule.
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for tool_dynamicrule.
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class observer {

    /**
     * The observer for monitoring all the events.
     *
     * @param  \core\event\base $event
     * @return void
     */
    public static function process_event(\core\event\base $event) {
        global $DB;
        $cache = \cache::make('tool_dynamicrule', 'eventsubscriptions');
        $eventconditionmap = $cache->get('eventconditionmap');
        if ($eventconditionmap === false) {
            // Build 'event => conditions' map and store in cache.
            $conditions = \tool_dynamicrule\api::get_conditions();
            $eventconditionmap = [];
            foreach ($conditions as $condition) {
                if ($eventname = $condition->get_event_subscription()) {
                    // Strip first slash if any.
                    $eventname = preg_replace('/^\\\\/', '', $eventname);
                    $eventconditionmap[$eventname][] = get_class($condition);
                }
            }
            $cache->set('eventconditionmap', $eventconditionmap);
        }

        // If we have conditions that are subscribed to event, find out which
        // rules they belong to and trigger processing.
        if (!empty($eventconditionmap[get_class($event)]) && count($eventconditionmap[get_class($event)])) {
            // Determine affected userid.
            $conditionclass = $eventconditionmap[get_class($event)][0];
            $userid = $conditionclass::get_event_related_userid($event);

            // Determine rules. We group by ruleid, so we do not trigger same rule
            // more than once if more that one condition in same rule is subscribed
            // to the same event.
            list($sql, $params) = $DB->get_in_or_equal($eventconditionmap[get_class($event)], SQL_PARAMS_NAMED);
            $params['tenantid'] = \tool_tenant\tenancy::get_tenant_id($userid);
            $sql = 'SELECT DISTINCT(drc.ruleid)
                      FROM {tool_dynamicrule_condition} drc
                      JOIN {tool_dynamicrule} dr ON drc.ruleid = dr.id
                     WHERE drc.classname ' . $sql . '
                       AND dr.enabled = 1
                       AND dr.broken = 0
                       AND dr.tenantid = :tenantid
                  ORDER BY drc.ruleid';
            $records = $DB->get_fieldset_sql($sql, $params);
            foreach ($records as $ruleid) {
                // Trigger rule processing for the user.
                // TODO: Ideally we need to verify rule against condition that
                // triggered it first, this might be possible if we make conditions
                // users matching as isolated functions (WP-680).
                // TODO: For better UX (and to avoid potential issue
                // of detached process on backend), we may add user/rule pair
                // to table and process on cron task (WP-681).
                $rule = \tool_dynamicrule\api::get_rule($ruleid, true);
                \tool_dynamicrule\api::process_rule($rule, $userid);
            }
        }
    }
}
