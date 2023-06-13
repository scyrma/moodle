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
 * Class Send
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Alberto Lara Hernández <albertolara@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\helpers;

use tool_reportbuilder\audience_base;
use tool_reportbuilder\local\models\audiences;
use tool_reportbuilder\local\models\schedule;
use tool_reportbuilder\output\report_dataformat_export_format;
use tool_tenant\tenancy;
use tool_tenant\tenant;

/**
 * Class send
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Alberto Lara Hernández <albertolara@moodle.com>
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
     * @return \stdClass[] An array of user objects matching the schedule audiences
     */
    private function get_mails() {
        global $DB;

        // Keep track of list of email addresses we are going to send to.
        $userstosendemail = [];

        $audienceids = json_decode($this->schedule->get('audiences'));
        if (!empty($audienceids)) {
            [$audienceselect, $audienceparams] = $DB->get_in_or_equal($audienceids, SQL_PARAMS_NAMED);

            $audienceselect = "reportid = :reportid AND id {$audienceselect}";
            $audienceparams['reportid'] = $this->schedule->get_report()->get_id();

            // Request selected audience records, return early if they no longer exist.
            $audiences = audiences::get_records_select($audienceselect, $audienceparams);
            if (empty($audiences)) {
                return [];
            }

            [$wheres, $params] = audience::user_audience_sql($audiences);

            $tenancywhere = tenancy::get_users_subquery(false, false, 'u.id', 0, true);
            $allwheres = '(' . implode(') OR (', $wheres) . ')';

            $sql = "SELECT u.*
                      FROM {user} u
                     WHERE {$tenancywhere} AND ($allwheres)";

            $userstosendemail = $DB->get_records_sql($sql, $params);
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
