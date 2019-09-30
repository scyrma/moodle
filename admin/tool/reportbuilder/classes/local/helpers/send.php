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
 */

namespace tool_reportbuilder\local\helpers;

use tool_organisation\department;
use tool_organisation\position;
use tool_reportbuilder\constants;
use tool_reportbuilder\reportbuilder;

defined('MOODLE_INTERNAL') || die();

/**
 * Class send
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * testable_report_exporter constructor.
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
     * Executes the query and returns the rows
     *
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_table_rows() : array {
        /** @var \tool_reportbuilder\report_base $source */
        $source = $this->related['source'];
        $this->table->query_db($source->get_pagesize(), false);
        $rows = [];
        foreach ($this->table->rawdata as $record) {
            $rows[] = $this->table->format_row($record);
        }
        return $rows;
    }

    /**
     * Download the table
     *
     * @return \tool_reportbuilder\report_table
     *
     */
    public function get_table() : \tool_reportbuilder\report_table {
        return $this->table;
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
        $table = $this->get_table();
        /** @var table_dataformat $tabledataformat */
        $tabledataformat = new table_dataformat($table, $this->format);

        $this->table->query_db($this->related['source']->get_pagesize(), false);
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
     * Get filepath for the attachment
     *
     * @return false|string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_filepath(): string {
        $uniquecode = time();
        $filename = $this->get_filename();
        $filedir = make_temp_directory('schedules/' . $uniquecode);
        $filepath = $filedir.'/'.$filename;
        $content = $this->get_content();
        file_put_contents($filepath, $content);
        return $filepath;
    }

    /**
     * Get filename
     *
     * @return mixed
     */
    private function get_filename(): string {
        $schedulename = $this->name;
        $extension = $this->get_extension($this->format);
        return str_replace(' ', '-', $schedulename) .'.'.$extension;
    }

    /**
     * Get extension
     *
     * @param string $format Format
     * @return string
     */
    private function get_extension($format): string {
        switch ($format) {
            case 'excel':
                $extension = 'xlsx';
                break;
            default:
                $extension = $format;
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
        $filepath = $this->get_filepath();
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
                $this->get_filename()
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
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function get_mails() {
        global $DB;

        // Keep track of list of email addresses we are going to send to.
        $emails = [];
        $userstosendemail = [];
        $audiencejson = $this->schedule->get('audience');
        $reportid = $this->schedule->get('reportid');
        $report = new reportbuilder($reportid);
        $reporttenantid = $report->get('tenantid');
        $audiences = json_decode($audiencejson);

        // Get users based on the userid.
        if (is_object($audiences) && object_property_exists($audiences, 'users')) {
            $users = $audiences->users;
            foreach ($users as $userid) {
                $usertenantid = \tool_tenant\tenancy::get_tenant_id($userid);
                if ((int)$usertenantid !== (int)$reporttenantid) {
                    if (debugging()) {
                        mtrace("The user ($userid) does not belong to tenant ($reporttenantid)");
                    }
                    continue;
                }
                $user = \core_user::get_user($userid);
                if (!in_array($user->email, $emails)) {
                    $emails[$user->email] = $user->email;
                    $userstosendemail[] = $user;
                }
            }
        }

        // Get users of the department selected.
        if (is_object($audiences) && object_property_exists($audiences, 'departmentid')) {
            $deparment = new department($audiences->departmentid);
            $deparmenttenantid = $deparment->get('tenantid');
            if ((int)$reporttenantid !== (int)$deparmenttenantid) {
                if (debugging()) {
                    mtrace("The department ($deparmenttenantid) is different of the report tenant ($reporttenantid)");
                }
            } else {
                list($where, $params) = \tool_organisation\helper::user_is_in_department_select($audiences->departmentid, true); // TODO: subdeparment.
                $userids = $DB->get_fieldset_sql('SELECT id from {user} u WHERE ' . $where, $params);
                foreach ($userids as $userid) {
                    $usertenantid = \tool_tenant\tenancy::get_tenant_id($userid);
                    if ((int)$usertenantid !== (int)$reporttenantid) {
                        if (debugging()) {
                            mtrace("The user ($userid) does not belong to tenant ($reporttenantid)");
                        }
                        continue;
                    }
                    $user = \core_user::get_user($userid);
                    if (!in_array($user->email, $emails)) {
                        $emails[$user->email] = $user->email;
                        $userstosendemail[] = $user;
                    }
                }
            }
        }

        // Get users of the department selected.
        if (is_object($audiences) && object_property_exists($audiences, 'positionid')) {
            $position = new position($audiences->positionid);
            $positiontenantid = $position->get('tenantid');
            if ((int)$reporttenantid !== (int)$positiontenantid) {
                if (debugging()) {
                    mtrace("The position ($positiontenantid) is different of the report tenant ($reporttenantid)");
                }
            } else {
                list($where, $params) = \tool_organisation\helper::user_has_position_select($audiences->positionid, true); // TODO: subdeparment.
                $userids = $DB->get_fieldset_sql('SELECT id from {user} u WHERE ' . $where, $params);
                foreach ($userids as $userid) {
                    $usertenantid = \tool_tenant\tenancy::get_tenant_id($userid);
                    if ((int)$usertenantid !== (int)$reporttenantid) {
                        if (debugging()) {
                            mtrace("The user ($userid) does not belong to tenant ($reporttenantid)");
                        }
                        continue;
                    }
                    $user = \core_user::get_user($userid);
                    if (!in_array($user->email, $emails)) {
                        $emails[$user->email] = $user->email;
                        $userstosendemail[] = $user;
                    }
                }
            }
        }

        // Custom emails.
        if (is_object($audiences) && object_property_exists($audiences, 'emails')) {
            foreach ($audiences->emails as $email) {
                if (!in_array($email, $emails)) {
                    $emails[$email] = $email;
                    $userstosendemail[] = $this->make_fake_user($email);
                }
            }
        }

        return $userstosendemail;
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