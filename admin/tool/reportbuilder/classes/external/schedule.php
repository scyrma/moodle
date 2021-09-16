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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class containing all services for the schedules.
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\external;

use tool_reportbuilder\local\helpers\schedules;
use tool_reportbuilder\permission;
use tool_reportbuilder\task\send_schedule;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/externallib.php");

/**
 * Class external.
 *
 * The external API for schedules
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class schedule extends \external_api {

    /**
     * Parameters for toggle_schedule external method
     *
     * @return \external_function_parameters
     */
    public static function toggle_schedule_parameters() {
        return new \external_function_parameters([
            'scheduleid' => new \external_value(PARAM_INT, 'Schedule ID', VALUE_REQUIRED),
            'enabled' => new \external_value(PARAM_BOOL, 'Enabled', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute toggle_schedule external method
     *
     * @param int $scheduleid
     * @param bool $enabled
     * @return bool
     */
    public static function toggle_schedule(int $scheduleid, bool $enabled): bool {
        $params = self::validate_parameters(self::toggle_schedule_parameters(), [
            'scheduleid' => $scheduleid,
            'enabled' => $enabled,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $schedule = schedules::get_schedule($params['scheduleid']);
        permission::require_can_edit_schedule($schedule);

        return $schedule->set('enabled', (int) $params['enabled'])->update();
    }

    /**
     * Return value of toggle_schedule external method
     *
     * @return \external_value
     */
    public static function toggle_schedule_returns() {
        return new \external_value(PARAM_BOOL, 'success');
    }

    /**
     * Parameters for the 'delete_schedule' web service
     *
     * @return \external_function_parameters
     */
    public static function delete_schedule_parameters() {
        return new \external_function_parameters([
            'scheduleid' => new \external_value(PARAM_INT, 'Schedule ID', VALUE_REQUIRED),
        ]);
    }

    /**
     * Delete a schedule
     *
     * @param int $scheduleid
     * @return bool
     */
    public static function delete_schedule(int $scheduleid) : bool {
        $params = self::validate_parameters(self::delete_schedule_parameters(), [
            'scheduleid' => $scheduleid
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $schedule = schedules::get_schedule($params['scheduleid']);
        permission::require_can_delete_schedule($schedule);

        return schedules::delete_schedule($schedule->get('id'));
    }

    /**
     * Return for delete_schedule service
     *
     * @return \external_value
     */
    public static function delete_schedule_returns() {
        return new \external_value(PARAM_BOOL, 'success');
    }

    /**
     * Parameters for the send_schedule web service
     *
     * @return \external_function_parameters
     */
    public static function send_schedule_parameters() {
        return new \external_function_parameters([
            'scheduleid' => new \external_value(PARAM_INT, 'Schedule ID', VALUE_REQUIRED),
        ]);
    }

    /**
     * Schedule an ad-hoc task to send the schedule
     *
     * @param int $scheduleid
     * @return bool
     */
    public static function send_schedule(int $scheduleid) : bool {
        $params = self::validate_parameters(self::send_schedule_parameters(), [
            'scheduleid' => $scheduleid
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $schedule = schedules::get_schedule($params['scheduleid']);
        permission::require_can_send_schedule($schedule);

        $sendschedule = new send_schedule();
        $sendschedule->set_userid($schedule->get('usercreated'));
        $sendschedule->set_custom_data([
            'scheduleid' => $schedule->get('id'),
        ]);

        return \core\task\manager::queue_adhoc_task($sendschedule);
    }

    /**
     * Return for send_schedule service
     *
     * @return \external_value
     */
    public static function send_schedule_returns() {
        return new \external_value(PARAM_BOOL, 'success');
    }
}
