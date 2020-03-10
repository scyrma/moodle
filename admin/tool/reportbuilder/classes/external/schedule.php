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
     * Parameters for the 'delete_schedule' web service
     *
     * @return \external_function_parameters
     */
    public static function delete_schedule_parameters() {
        return new \external_function_parameters([
            'scheduleid' => new \external_value(PARAM_INT, 'Id of the schedule to delete', VALUE_REQUIRED)
        ]);
    }

    /**
     * Delete a schedule
     *
     * @param int $scheduleid
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     * @throws \restricted_context_exception
     * @throws \moodle_exception
     */
    public static function delete_schedule(int $scheduleid) : bool {
        $params = self::validate_parameters(self::delete_schedule_parameters(), [
            'scheduleid' => $scheduleid
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        $schedule = schedules::get_schedule($scheduleid);
        permission::require_can_delete_schedule($schedule);

        return schedules::delete_schedule($params['scheduleid']);
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
     * Parameters for the 'delete_schedule' web service
     *
     * @return \external_function_parameters
     */
    public static function send_schedule_parameters() {
        return new \external_function_parameters([
            'scheduleid' => new \external_value(PARAM_INT, 'Id of the schedule to delete', VALUE_REQUIRED)
        ]);
    }

    /**
     * Send a schedule
     *
     * @param int $scheduleid
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     * @throws \restricted_context_exception
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
        $sendschedule->set_custom_data(array(
            'scheduleid' => $params['scheduleid']
        ));

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