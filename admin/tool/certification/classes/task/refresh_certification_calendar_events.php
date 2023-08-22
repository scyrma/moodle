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
 * Certification ad-hoc task for refreshing certification calendar events.
 *
 * This is triggered by upgrade script only. Upgrade step from MDL-67494 corrupted calendar events
 * changing all userid values to zero. This scripts remove those even entries and recerates them again.
 *
 * @package     tool_certification
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\task;

use core\task\adhoc_task;
use tool_certification\api;
use tool_certification\certification;
use tool_certification\constants;
use tool_program\persistent\program_user;

/**
 * Ad-hoc task refresh_certification_calendar_events
 *
 * @package     tool_certification
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class refresh_certification_calendar_events extends adhoc_task {

    /**
     * Execute the task
     *
     * @return void
     */
    public function execute() {
        global $DB;

        // Remove wrong records from component tool_certification that have userid=0.
        $DB->delete_records('event', ['component' => 'tool_certification', 'userid' => 0]);

        // Create missing calendar events or update existing correct ones for all certifications where
        // the certification is not archived and, the tenant the certification belongs to, is also not archived.
        $sql = '
            SELECT tc.*
            FROM {tool_certification} tc
            JOIN {tool_tenant} tt
            ON tt.id = tc.tenantid
            WHERE tc.archived = :carchived AND tt.archived = :tarchived
        ';
        $certifications = $DB->get_records_sql($sql, ['carchived' => 0, 'tarchived' => 0]);
        foreach ($certifications as $certificationrecord) {
            $certification = new certification(0, $certificationrecord);
            $certificationusers = $certification->get_certification_users();
            foreach ($certificationusers as $certificationuser) {
                $certificationid = $certificationuser->get('certificationid');
                $userid = $certificationuser->get('userid');
                /** @var program_user $programuser */
                $programuser = program_user::get_record(['certificationid' => $certificationid, 'userid' => $userid]);

                $userallocdate = (int) $certificationuser->get('timecreated');
                $userduedate = (int) $programuser->get('duedate');
                $userexpirydate = api::recalculate_user_expiry_date($certification, $certificationuser,
                    $userallocdate, $userduedate);

                $iscertified = api::is_user_certified($userid, $certificationid);

                // Check if due date is set and needs a calendar event.
                if (!$iscertified && $userduedate !== constants::DATE_NONE) {
                    // Create or update due date calendar event.
                    $data = (object) [
                        'userid' => $userid,
                        'name' => $certification->get_formatted_name(),
                        'certificationid' => $certificationid,
                        'timestart' => $userduedate,
                        'certificationdatetype' => constants::CALENDAR_EVENT_DUE_DATE
                    ];
                    api::update_calendar_event($data);
                }

                // Check if expiry date is set and needs a calendar event.
                if ($iscertified && $userexpirydate !== constants::DATE_NONE) {
                    // Create or update due date calendar event.
                    $data = (object) [
                        'userid' => $userid,
                        'name' => $certification->get_formatted_name(),
                        'certificationid' => $certificationid,
                        'timestart' => $userexpirydate,
                        'certificationdatetype' => constants::CALENDAR_EVENT_EXPIRY_DATE
                    ];
                    api::update_calendar_event($data);
                }
            }
        }
    }
}
