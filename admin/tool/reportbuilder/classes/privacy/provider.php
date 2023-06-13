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
 * Privacy Subsystem implementation for tool_reportbuilder.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
namespace tool_reportbuilder\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use tool_reportbuilder\audience_base;
use tool_reportbuilder\reportbuilder;
use tool_reportbuilder\local\models\audiences as reportbuilder_audience;
use tool_reportbuilder\local\models\schedule as reportbuilder_schedule;

/**
 * Privacy Subsystem for tool_reportbuilder
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\user_preference_provider {

    /**
     * Returns metadata about the plugin
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(reportbuilder::TABLE, [
            'name' => 'privacy:metadata:reportbuilder:name',
            'source' => 'privacy:metadata:reportbuilder:source',
            'tenantid' => 'privacy:metadata:reportbuilder:tenantid',
            'usercreated' => 'privacy:metadata:reportbuilder:usercreated',
            'usermodified' => 'privacy:metadata:reportbuilder:usermodified',
            'timecreated' => 'privacy:metadata:reportbuilder:timecreated',
            'timemodified' => 'privacy:metadata:reportbuilder:timemodified',
        ], 'privacy:metadata:reportbuilder');

        $collection->add_database_table(reportbuilder_audience::TABLE, [
            'reportid' => 'privacy:metadata:reportbuilder_audience:reportid',
            'classname' => 'privacy:metadata:reportbuilder_audience:classname',
            'configdata' => 'privacy:metadata:reportbuilder_audience:configdata',
            'usercreated' => 'privacy:metadata:reportbuilder_audience:usercreated',
            'usermodified' => 'privacy:metadata:reportbuilder_audience:usermodified',
            'timecreated' => 'privacy:metadata:reportbuilder_audience:timecreated',
            'timemodified' => 'privacy:metadata:reportbuilder_audience:timemodified',
        ], 'privacy:metadata:reportbuilder_audience');

        $collection->add_database_table(reportbuilder_schedule::TABLE, [
            'name' => 'privacy:metadata:reportbuilder_schedule:name',
            'reportid' => 'privacy:metadata:reportbuilder_schedule:reportid',
            'scheduled' => 'privacy:metadata:reportbuilder_schedule:scheduled',
            'recurrence' => 'privacy:metadata:reportbuilder_schedule:recurrence',
            'lastsenton' => 'privacy:metadata:reportbuilder_schedule:lastsenton',
            'format' => 'privacy:metadata:reportbuilder_schedule:format',
            'subject' => 'privacy:metadata:reportbuilder_schedule:subject',
            'message' => 'privacy:metadata:reportbuilder_schedule:message',
            'usercreated' => 'privacy:metadata:reportbuilder_schedule:usercreated',
            'usermodified' => 'privacy:metadata:reportbuilder_schedule:usermodified',
            'timecreated' => 'privacy:metadata:reportbuilder_schedule:timecreated',
            'timemodified' => 'privacy:metadata:reportbuilder_schedule:timemodified',
        ], 'privacy:metadata:reportbuilder_schedule');

        $collection->add_user_preference('filters_report', 'privacy:metadata:preference:filters_report');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $select = 'usercreated = ? OR usermodified = ?';
        $params = [$userid, $userid];

        // We add the system context if the specified user has created/modified any reports or schedules.
        if (reportbuilder::record_exists_select($select . ' AND type = 0', $params) ||
                reportbuilder_audience::record_exists_select($select, $params) ||
                reportbuilder_schedule::record_exists_select($select, $params)) {

            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }

        // Users who have created reports.
        $sql = 'SELECT usercreated, usermodified
                  FROM {' . reportbuilder::TABLE . '}
                 WHERE type = ?';
        $userlist->add_from_sql('usercreated', $sql, [0]);
        $userlist->add_from_sql('usermodified', $sql, [0]);

        // Users who have created audiences.
        $sql = 'SELECT usercreated, usermodified
                  FROM {' .  reportbuilder_audience::TABLE . '}';
        $userlist->add_from_sql('usercreated', $sql, []);
        $userlist->add_from_sql('usermodified', $sql, []);

        // Users who have created schedules.
        $sql = 'SELECT usercreated, usermodified
                  FROM {' . reportbuilder_schedule::TABLE . '}';
        $userlist->add_from_sql('usercreated', $sql, []);
        $userlist->add_from_sql('usermodified', $sql, []);
    }

    /**
     * Export all user data for the specified user in the specified contexts
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        // We're only interested in the system context.
        $contexts = array_filter($contextlist->get_contexts(), function($context) {
            return $context instanceof context_system;
        });

        if (empty($contexts)) {
            return;
        }

        $context = reset($contexts);
        $user = $contextlist->get_user();

        // We need to get all reports that the user has created, or reports they have created audience/schedules for.
        $select = 'type = 0 AND (usercreated = ? OR usermodified = ? OR id IN (
            SELECT a.reportid
              FROM {' . reportbuilder_audience::TABLE . '} a
             WHERE a.usercreated = ? OR a.usermodified = ?
             UNION
            SELECT s.reportid
              FROM {' . reportbuilder_schedule::TABLE . '} s
             WHERE s.usercreated = ? OR s.usermodified = ?
        ))';
        $params = array_fill(0, 6, $user->id);

        foreach (reportbuilder::get_records_select($select, $params) as $report) {
            $contextpath = static::get_export_path($report);

            static::export_report($context, $contextpath, $report);

            $select = 'reportid = ? AND (usercreated = ? OR usermodified = ?)';
            $params = [$report->get('id'), $user->id, $user->id];

            // Audiences.
            if ($audiences = reportbuilder_audience::get_records_select($select, $params)) {
                static::export_audiences($context, $contextpath, $audiences);
            }

            // Schedules.
            if ($schedules = reportbuilder_schedule::get_records_select($select, $params)) {
                static::export_schedules($context, $contextpath, $schedules);
            }
        }
    }

    /**
     * Export all user preferences for the plugin
     *
     * @param int $userid
     * @return void
     */
    public static function export_user_preferences(int $userid) {
        global $DB;

        $select = 'userid = :userid AND ' . $DB->sql_like('name', ':namelike');
        $params = [
            'userid' => $userid,
            'namelike' => 'filters_report_%',
        ];

        $preferences = $DB->get_fieldset_select('user_preferences', 'name', $select, $params);
        foreach ($preferences as $preference) {
            $value = get_user_preferences($preference, null, $userid);

            writer::export_user_preference('tool_reportbuilder', $preference, $value,
                get_string('privacy:metadata:preference:filters_report', 'tool_reportbuilder'));
        }
    }

    /**
     * Delete all user data in the specified context
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        // We don't perform any deletion of user data.
    }

    /**
     * Delete all user data for the specified user in the specified contexts
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // We don't perform any deletion of user data.
    }

    /**
     * Delete data for multiple users within a single context
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        // We don't perform any deletion of user data.
    }

    /**
     * Export given report in context
     *
     * @param context_system $context
     * @param array $contextpath
     * @param reportbuilder $report
     * @return void
     */
    protected static function export_report(context_system $context, array $contextpath, reportbuilder $report) {
        $reportdata = (object) [
            'name' => format_string($report->get('name')),
            'source' => $report->get('source'),
            'tenantid' => $report->get('tenantid'),
            'usercreated' => transform::user($report->get('usercreated')),
            'usermodified' => transform::user($report->get('usermodified')),
            'timecreated' => transform::datetime($report->get('timecreated')),
            'timemodified' => transform::datetime($report->get('timemodified')),
        ];

        writer::with_context($context)->export_data($contextpath, $reportdata);
    }

    /**
     * Export given audiences in context
     *
     * @param context_system $context
     * @param array $contextpath
     * @param reportbuilder_audience[] $audiences
     */
    protected static function export_audiences(context_system $context, array $contextpath, array $audiences): void {
        $audiencedata = [];

        foreach ($audiences as $audience) {
            $instance = audience_base::instance(0, $audience->to_record());

            if (!$instance) {
                continue;
            }

            $audiencedata[] = (object) [
                'description' => $instance->get_description(),
                'usercreated' => transform::user($audience->get('usercreated')),
                'usermodified' => transform::user($audience->get('usermodified')),
                'timecreated' => transform::datetime($audience->get('timecreated')),
                'timemodified' => transform::datetime($audience->get('timemodified')),
            ];
        }

        writer::with_context($context)->export_related_data($contextpath, 'audiences', (object) ['data' => $audiencedata]);
    }

    /**
     * Export given schedules in context
     *
     * @param context_system $context
     * @param array $contextpath
     * @param reportbuilder_schedule[] $schedules
     */
    protected static function export_schedules(context_system $context, array $contextpath, array $schedules): void {
        $scheduledata = [];

        foreach ($schedules as $schedule) {
            $scheduledata[] = (object) [
                'name' => format_string($schedule->get('name')),
                'scheduled' => transform::datetime($schedule->get('scheduled')),
                'recurrence' => $schedule->get('recurrence'),
                'lastsenton' => transform::datetime($schedule->get('lastsenton')),
                'format' => $schedule->get('format'),
                'subject' => format_string($schedule->get('subject')),
                'message' => format_text($schedule->get('message'), FORMAT_HTML, ['context' => $context]),
                'usercreated' => transform::user($schedule->get('usercreated')),
                'usermodified' => transform::user($schedule->get('usermodified')),
                'timecreated' => transform::datetime($schedule->get('timecreated')),
                'timemodified' => transform::datetime($schedule->get('timemodified')),
            ];
        }

        writer::with_context($context)->export_related_data($contextpath, 'schedules', (object) ['data' => $scheduledata]);
    }

    /**
     * Get context export path for a report
     *
     * @param reportbuilder $report
     * @return array
     */
    public static function get_export_path(reportbuilder $report) {
        $rootnode = get_string('pluginname', 'tool_reportbuilder');

        $reportnode = implode('-', [
            $report->get('id'),
            clean_filename($report->get('name')),
        ]);

        return [$rootnode, $reportnode];
    }
}
