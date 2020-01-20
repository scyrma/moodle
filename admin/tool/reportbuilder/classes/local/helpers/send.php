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
 * Class Send
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\helpers;

use tool_reportbuilder\output\report_dataformat_export_format;

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

    /** @var string $format Schedule foormat */
    protected $format;
    /** @var string $subject Email subject */
    protected $subject;
    /** @var string $message Message */
    protected $message;
    /** @var string $name Schedule name */
    protected $name;
    /**
     * @var \tool_reportbuilder\local\models\schedules
     */
    protected $schedule;

    /**
     * Constructor
     *
     * @param int $scheduleid
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function __construct(int $scheduleid) {
        global $PAGE;
        $schedule = new \tool_reportbuilder\local\models\schedules($scheduleid);
        $this->schedule = $schedule;
        $reportid = $schedule->get('reportid');
        $report = \tool_reportbuilder\manager::get_report($reportid);
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
        $this->init($schedule);
    }

    /**
     * Init
     *
     * @param \tool_reportbuilder\local\models\schedules $schedule
     * @throws \coding_exception
     */
    private function init(\tool_reportbuilder\local\models\schedules $schedule) {
        $this->format = $schedule->get('format');
        $this->subject = $schedule->get('subject');
        $this->message = $schedule->get('message');
        $this->name = $schedule->get('name');
    }

    /**
     * Download the report in the given format
     *
     * @return false|string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function get_content() {
        ob_start();

        $tabledataformat = new report_dataformat_export_format($this->table, $this->format);

        $this->table->setup();
        $this->table->pageable(false);
        $this->table->query_db($this->table->count_records(), false);

        $tabledataformat->start_document('test', 'test');
        $tabledataformat->output_headers($this->table->headers);

        foreach ($this->table->rawdata as $row) {
            $tabledataformat->add_data($this->table->format_row($row));
        }
        $tabledataformat->finish_document();
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    /**
     * Write report content as attachment in temp dir, and return it's path
     *
     * @return string
     */
    public function create_attachment(): string {
        $tempdir = make_request_directory();
        $filepath = $tempdir . '/' . $this->get_filename();

        file_put_contents($filepath, $this->get_content());

        return $filepath;
    }

    /**
     * Get filename
     *
     * @return string
     */
    private function get_filename(): string {
        $filename = clean_filename($this->name);
        $extension = $this->get_extension();

        return "{$filename}.{$extension}";
    }

    /**
     * Get extension
     *
     * @return string
     */
    private function get_extension(): string {
        switch ($this->format) {
            case 'excel':
                $extension = 'xlsx';
                break;
            default:
                $extension = $this->format;
                break;
        }

        return $extension;
    }

    /**
     * Send the email
     *
     * @return bool
     * @throws \coding_exception
     * @throws \core\invalid_persistent_exception
     * @throws \dml_exception
     */
    public function sendemail(): bool {
        $filepath = $this->create_attachment();
        $filename = $this->get_filename();

        $tousers = $this->get_mails();
        $fromuser = \core_user::get_user($this->schedule->get('usercreated'));
        mtrace("Sending schedule: " . $this->schedule->get('name'));

        $message = $this->message;
        $messagetext = html_to_text($message);

        foreach ($tousers as $touser) {
            $send = email_to_user(
                $touser,
                $fromuser,
                $this->subject,
                $messagetext,
                $message,
                $filepath,
                $filename
            );

            if ($send) {
                mtrace(sprintf(" * The schedule '%s' has been send to '%s (%s)' on behalf of '%s'",
                    $this->schedule->get('name'), fullname($touser), $touser->email, fullname($fromuser)));
            }
        }

        $this->schedule->set('lastsenton', time());
        $this->schedule->update();

        return true;
    }

    /**
     * Based on the audience, get all valid emails to send.
     *
     * @return \stdClass[]
     */
    private function get_mails() {
        global $DB;

        // Keep track of list of email addresses we are going to send to.
        $userstosendemail = [];

        $audiencejson = $this->schedule->get('audience');
        $audiences = json_decode($audiencejson);

        // Manually added users.
        if (is_object($audiences) && !empty($audiences->users)) {
            foreach ($audiences->users as $userid) {
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

        // Department users (TODO: sub-departments).
        if (is_object($audiences) && !empty($audiences->departmentid)) {
            list($where, $params) = \tool_organisation\helper::user_is_in_department_select($audiences->departmentid, true);
            $userids = $DB->get_fieldset_sql('SELECT id from {user} u WHERE ' . $where, $params);
            foreach ($userids as $userid) {
                $user = \core_user::get_user($userid);
                if (!array_key_exists($user->email, $userstosendemail)) {
                    $userstosendemail[$user->email] = $user;
                }
            }
        }

        // Position users (TODO: sub-positions).
        if (is_object($audiences) && !empty($audiences->positionid)) {
            list($where, $params) = \tool_organisation\helper::user_has_position_select($audiences->positionid, true);
            $userids = $DB->get_fieldset_sql('SELECT id from {user} u WHERE ' . $where, $params);
            foreach ($userids as $userid) {
                $user = \core_user::get_user($userid);
                if (!array_key_exists($user->email, $userstosendemail)) {
                    $userstosendemail[$user->email] = $user;
                }
            }
        }

        // Custom emails.
        if (is_object($audiences) && !empty($audiences->emails)) {
            foreach ($audiences->emails as $email) {
                if (!array_key_exists($email, $userstosendemail)) {
                    $userstosendemail[$email] = $this->make_fake_user($email);
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