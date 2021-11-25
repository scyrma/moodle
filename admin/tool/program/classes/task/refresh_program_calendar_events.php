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
 * Program ad-hoc task for refreshing program calendar events.
 *
 * This is triggered by upgrade script only. Upgrade step from MDL-67494 corrupted calendar events
 * changing all userid values to zero. This scripts remove those even entries and recerates them again.
 *
 * @package     tool_program
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\task;

use core\task\adhoc_task;
use tool_program\api;
use tool_program\constants;
use tool_program\persistent\program;

defined('MOODLE_INTERNAL') || die;

/**
 * Ad-hoc task refresh_program_calendar_events
 *
 * @package     tool_program
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class refresh_program_calendar_events extends adhoc_task {

    /**
     * Execute the task
     *
     * @return void
     */
    public function execute() {
        global $DB;

        // Remove wrong records from component tool_program that have userid=0.
        $DB->delete_records('event', ['component' => 'tool_program', 'userid' => 0]);

        // Create missing calendar events or update existing correct ones for all programs direct allocations where
        // the program is not archived and, the tenant the program belongs to, is also not archived.
        $sql = '
            SELECT tp.*
            FROM {tool_program} tp
            JOIN {tool_tenant} tt
            ON tt.id = tp.tenantid
            WHERE tp.archived = :parchived AND tt.archived = :tarchived
        ';
        $programs = $DB->get_records_sql($sql, ['parchived' => 0, 'tarchived' => 0]);
        foreach ($programs as $programrecord) {
            $program = new program(0, $programrecord);
            $programusers = $program->get_program_users();
            foreach ($programusers as $programuser) {
                if (!$programuser->is_certification_allocation()) {
                    $userduedate = (int) $programuser->get('duedate');
                    $userenddate = (int) $programuser->get('enddate');
                    $programid = $programuser->get('programid');
                    $userid = $programuser->get('userid');

                    // Check if due date is set and needs a calendar event.
                    if ($userduedate !== constants::DATE_NONE) {
                        // Create or update due date calendar event.
                        $data = (object) [
                            'userid' => $userid,
                            'name' => $program->get_formatted_name(),
                            'programid' => $programid,
                            'timestart' => $userduedate,
                            'programdatetype' => constants::CALENDAR_EVENT_DUE_DATE
                        ];
                        api::update_calendar_event($data);
                    }

                    // Check if end date is set and needs a calendar event.
                    if ($userenddate !== constants::DATE_NONE) {
                        // Create or update end date calendar event.
                        $data = (object) [
                            'userid' => $userid,
                            'name' => $program->get_formatted_name(),
                            'programid' => $programid,
                            'timestart' => $userenddate,
                            'programdatetype' => constants::CALENDAR_EVENT_END_DATE
                        ];
                        api::update_calendar_event($data);
                    }
                }
            }
        }
    }
}
