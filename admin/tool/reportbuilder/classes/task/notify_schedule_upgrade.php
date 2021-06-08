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

namespace tool_reportbuilder\task;

use core_user;
use moodle_url;
use core\task\adhoc_task;

/**
 * Ad-hoc task for notifying users that their report schedules have been upgraded
 *
 * @package     tool_reportbuilder
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class notify_schedule_upgrade extends adhoc_task {

    /**
     * Execute the task
     */
    public function execute(): void {
        global $DB;

        [
            'reportid' => $reportid,
            'schedulename' => $schedulename,
            'emails' => $emails,
        ] = (array) $this->get_custom_data();

        $userto = core_user::get_user($this->get_userid());
        $userfrom = core_user::get_noreply_user();

        $reportname = $DB->get_field('tool_reportbuilder', 'name', ['id' => $reportid]);
        $reportlink = (new moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => $reportid]))->out();
        $upgradedocslink = get_docs_url('Report_builder#Upgrading_audience_and_schedules_prior_to_3.11');

        $strsubject = get_string('schedulenotifyupgrade', 'tool_reportbuilder');
        $strmessage = get_string('schedulenotifyupgrademessage', 'tool_reportbuilder', (object) [
            'reportname' => format_string($reportname),
            'reportlink' => $reportlink,
            'schedulename' => $schedulename,
            'emails' => implode(', ', $emails),
            'docslink' => $upgradedocslink,
        ]);

        email_to_user($userto, $userfrom, $strsubject, html_to_text($strmessage), $strmessage);
    }
}
