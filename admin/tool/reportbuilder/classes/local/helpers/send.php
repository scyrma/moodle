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
 * Class Send
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\helpers;

use tool_reportbuilder\local\models\schedules as schedule;
use tool_reportbuilder\output\report_dataformat_export_format;
use tool_tenant\tenant;

defined('MOODLE_INTERNAL') || die();

/**
 * Class send
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class send extends \tool_reportbuilder\output\report_exporter {

    /** @var schedule $schedule */
    protected $schedule;

    /** @var tenant $tenant */
    protected $tenant;

    /**
     * Constructor
     *
     * @param schedule $schedule
     */
    public function __construct(schedule $schedule) {
        global $PAGE;

        $this->schedule = $schedule;
        $this->tenant = new tenant($this->schedule->get_tenantid());

        $report = $this->schedule->get_report();
        $persistent = new \tool_reportbuilder\reportbuilder($report->get_id());

        parent::__construct($persistent,
            [
                'source'  => $report,
                'page' => 0,
                'editon' => false,
                'tableonly' => true
            ]
        );

        $PAGE->set_url('/');
        $this->prepare_report($PAGE->get_renderer('core'));
    }

    /**
     * Write report content as attachment in temp dir, and return it's path
     *
     * @return string
     */
    public function create_attachment(): string {
        $tabledataformat = new report_dataformat_export_format($this->table, $this->schedule->get('format'));

        $this->table->setup();
        $this->table->query_db(0, false);

        $filepath = $tabledataformat->start_output_to_file($this->schedule->get_filename());
        $tabledataformat->output_headers($this->table->headers);

        foreach ($this->table->rawdata as $row) {
            $tabledataformat->add_data($this->table->format_row($row));
        }
        $tabledataformat->close_output_to_file();

        $this->table->close_recordset();

        return $filepath;
    }

    /**
     * Send the email
     *
     * @return bool
     */
    public function sendemail(): bool {
        // Don't proceed if the tenant is archived.
        if ($this->tenant->get('archived')) {
            return false;
        }

        $filepath = $this->create_attachment();
        $filename = basename($filepath);

        $tousers = $this->get_mails();
        $fromuser = \core_user::get_user($this->schedule->get('usercreated'));

        $subject = $this->schedule->get('subject');
        $message = $this->schedule->get('message');
        $messagetext = html_to_text($message);

        foreach ($tousers as $touser) {
            email_to_user(
                $touser,
                $fromuser,
                $subject,
                $messagetext,
                $message,
                $filepath,
                $filename
            );
        }

        return true;
    }

    /**
     * Based on the schedule recipients, get all valid emails to send
     *
     * @return \stdClass[]
     */
    private function get_mails() {
        global $DB;

        // Keep track of list of email addresses we are going to send to.
        $userstosendemail = [];

        // Load custom user/email recipients.
        $recipients = json_decode($this->schedule->get('recipients'));
        if ($recipients && !empty($recipients->users)) {
            foreach ($recipients->users as $userid) {
                // Make sure user still belongs to the report tenant (they may have been moved).
                $usertenantid = \tool_tenant\tenancy::get_tenant_id($userid);
                if ($usertenantid != $this->schedule->get_tenantid()) {
                    debugging("The user with ID {$userid} no longer belongs to the schedule tenant, and will not be included",
                        DEBUG_DEVELOPER);
                    continue;
                }
                $user = \core_user::get_user($userid);
                if (!array_key_exists($user->email, $userstosendemail)) {
                    $userstosendemail[$user->email] = $user;
                }
            }
        }

        if ($recipients && !empty($recipients->emails)) {
            foreach ($recipients->emails as $email) {
                if (!array_key_exists($email, $userstosendemail)) {
                    $userstosendemail[$email] = $this->make_fake_user($email);
                }
            }
        }

        // Department users (TODO: sub-departments).
        $departmentid = $this->schedule->get('departmentid');
        if ($departmentid > 0) {
            list($where, $params) = \tool_organisation\helper::user_is_in_department_select($departmentid, true);
            $userids = $DB->get_fieldset_sql('SELECT id from {user} u WHERE ' . $where, $params);
            foreach ($userids as $userid) {
                $user = \core_user::get_user($userid);
                if (!array_key_exists($user->email, $userstosendemail)) {
                    $userstosendemail[$user->email] = $user;
                }
            }
        }

        // Position users (TODO: sub-positions).
        $positionid = $this->schedule->get('positionid');
        if ($positionid > 0) {
            list($where, $params) = \tool_organisation\helper::user_has_position_select($positionid, true);
            $userids = $DB->get_fieldset_sql('SELECT id from {user} u WHERE ' . $where, $params);
            foreach ($userids as $userid) {
                $user = \core_user::get_user($userid);
                if (!array_key_exists($user->email, $userstosendemail)) {
                    $userstosendemail[$user->email] = $user;
                }
            }
        }

        return array_values($userstosendemail);
    }

    /**
     * Make a fake user for custom emails
     *
     * @param string $usermail
     * @return \stdClass
     */
    private function make_fake_user($usermail) {
        $userinfo = guest_user();
        $userinfo->email = $usermail;

        return $userinfo;
    }
}
