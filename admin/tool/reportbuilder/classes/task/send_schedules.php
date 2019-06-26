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
 * Task for sending schedules emails.
 *
 * @package   tool_reportbuilder
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\task;

use tool_reportbuilder\local\helpers\schedules;

defined('MOODLE_INTERNAL') || die();

/**
 * Task for sending schedules emails.
 *
 * @package   tool_reportbuilder
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_schedules extends \core\task\scheduled_task {

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('send_schedulestask', 'tool_reportbuilder');
    }

    /**
     * Execute the task.
     *
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     * @throws \dml_exception
     */
    public function execute() {
        global $USER;
        $schedules = schedules::get_schedules();
        $originaluser = $USER;
        foreach ($schedules as $schedule) {
            $schedulemanager = new schedules($schedule->get('id'));
            $user = \core_user::get_user($schedule->get('usercreated'));
            cron_setup_user($user);
            $schedulemanager->send();
        }
        cron_setup_user($originaluser);
    }
}