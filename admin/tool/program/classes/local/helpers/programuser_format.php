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

namespace tool_program\local\helpers;

use context_system;
use html_writer;
use moodle_exception;
use moodle_url;
use stdClass;
use tool_certification\certification;
use tool_program\api;
use tool_program\constants;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use tool_program\program_tree_progress;

/**
 * Class programuser_format
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class programuser_format {

    /**
     * Displays column duedate.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function startdate(?string $value, stdClass $row): string {
        $icon = '';
        if (constants::DATE_LOCKED === (int) $row->startdatelocked) {
            global $OUTPUT;
            $icon = ' ' . $OUTPUT->pix_icon('req', get_string('dateoverriden', 'tool_program'));
        }
        if (0 === (int) $row->startdate) {
            return get_string('notset', 'tool_program') . $icon;
        }
        return format::date((int) $row->startdate) . $icon;
    }

    /**
     * Displays column duedate.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function duedate(?string $value, stdClass $row): string {
        $icon = '';
        if (constants::DATE_LOCKED === (int) $row->duedatelocked) {
            global $OUTPUT;
            $icon = ' ' . $OUTPUT->pix_icon('req', get_string('dateoverriden', 'tool_program'));
        }
        if (0 === (int) $row->duedate && constants::DATE_LOCKED === (int) $row->duedatelocked) {
            return get_string('never', 'tool_program') . $icon;
        }
        if (0 === (int) $row->duedate && constants::DATE_UNLOCKED === (int) $row->duedatelocked) {
            return get_string('notset', 'tool_program');
        }
        return format::date((int) $row->duedate) . $icon;
    }

    /**
     * Displays column enddate.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function enddate(?string $value, stdClass $row): string {
        $icon = '';
        if (constants::DATE_LOCKED === (int) $row->enddatelocked) {
            global $OUTPUT;
            $icon = ' ' . $OUTPUT->pix_icon('req', get_string('dateoverriden', 'tool_program'));
        }
        if (0 === (int) $row->enddate && constants::DATE_LOCKED === (int) $row->enddatelocked) {
            return get_string('never', 'tool_program') . $icon;
        }
        if (0 === (int) $row->enddate && constants::DATE_UNLOCKED === (int) $row->enddatelocked) {
            return get_string('notset', 'tool_program');
        }
        return format::date((int) $row->enddate) . $icon;
    }

    /**
     * Formats suspended
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function suspended(string $value, stdClass $row): string {
        $issuspended = constants::STATUS_OVERRIDE_SUSPENDED === (int) $row->status;
        return format::yesno(
            $issuspended,
            get_string('suspended', 'tool_program'),
            get_string('notsuspended', 'tool_program')
        );
    }

    /**
     * Displays allocation source.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function allocationtype(string $value, stdClass $row): string {
        return api::get_user_allocation_name((int) $row->allocationtype);
    }

    /**
     * Returns formatted time modified
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function timesuspended(string $value, stdClass $row): string {
        return format::date((int) $row->timesuspended);
    }

    /**
     * Returns formatted time modified
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function timemodified(string $value, stdClass $row): string {
        return format::date((int) $row->timemodified);
    }

    /**
     * Returns formatted time created
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function timecreated(string $value, stdClass $row): string {
        return format::date((int) $row->timecreated);
    }

    /**
     * Displays column user certification.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function certificationuser(string $value, stdClass $row): string {
        if (0 === (int) $row->certificationuser) {
            return '-';
        }
        if (certification::record_exists($row->certificationuser)) {
            $certurl = new moodle_url('/admin/tool/certification/edit.php', ['id' => $row->certificationuser]);
            $certification = new certification($row->certificationuser);
            $options = ['context' => context_system::instance(), 'escape' => false];
            $certificationname = format_string($certification->get('fullname'), true, $options);

            return html_writer::link($certurl, $certificationname);
        }
        throw new moodle_exception('errorcertificationnotfound', 'tool_program');
    }

    /**
     * Displays column program status.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function programstatus(?string $value, ?stdClass $row): string {
        // Recertification: If programid is null because user is certified and currentprogramid is set to null.
        // Then get programid/userid/certificationid from the certifications record.
        if (!$row->programid && !$row->userid && !$row->certificationid) {
            return '-';
        }
        $statuses = api::get_user_allocation_statuses($row->programid, $row->userid, $row->certificationid);
        $statuseshtml = [];
        foreach ($statuses as $status) {
            $statuseshtml[] = api::get_user_allocation_status_badges($status['status']);
        }
        return implode(' ', $statuseshtml);
    }

    /**
     * Displays column program progress.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function programprogress(?string $value, stdClass $row): string {
        if (!$row->programid && !$row->userid) {
            return '-';
        }

        $userid = (int) $row->userid;
        // Show information only if user can see this user progress.
        if (!permission::can_view_user_programs_progress($userid)) {
            return '-';
        }

        $programtreeprogress = new program_tree_progress(new program($row->programid), $userid);
        return $programtreeprogress->get_program_progress_as_percentage();
    }

    /**
     * Displays column program progress with overview modal and report link.
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function programprogressoverviewlink(?string $value, stdClass $row): string {
        global $PAGE;

        // Sometimes certification users does not have a current program if they are between certification rounds.
        if (!$row->programid) {
            return '-';
        }
        $userid = (int) $row->userid;
        // Show information only if user can see this user progress.
        if (!permission::can_view_user_programs_progress($userid)) {
            return '-';
        }

        $programid = $row->programid;
        $program = new program($programid);
        $programtreeprogress = new program_tree_progress($program, $userid);
        $progresspercent = $programtreeprogress->get_program_progress_as_percentage();
        $allocationid = (int) $row->id;
        $output = $PAGE->get_renderer('tool_program');

        $overviewstr = get_string('progressoverview', 'tool_program');

        // Url needs program path because it's called from programs and certifications plugins.
        $userprogramurl = new moodle_url('/admin/tool/program/programsprogress.php', [
            'programid' => $programid,
            'userid' => $userid,
        ]);

        $context = (object) [
            'allocationid' => $allocationid,
            'progresspercent' => $progresspercent,
            'title' => $program->get_formatted_name() . ': ' . $overviewstr,
            'contextid' => $program->get_context()->id,
            'userprogramurl' => $userprogramurl->out(false),
        ];
        return $output->render_from_template('tool_program/program_progress_overview', $context);
    }

    /**
     * Displays column status.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function certificationname(string $value, stdClass $row): string {
        if (0 === (int) $row->certificationid) {
            return '-';
        }
        $certification = new certification($row->certificationid);
        $options = ['context' => context_system::instance(), 'escape' => false];
        return format_string($certification->get('fullname'), true, $options);
    }

    /**
     * Displays column expirydate.
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function userexpirydate(?string $value, stdClass $row): string {
        if (empty($row->certificationid) || empty($row->userid)) {
            return '-';
        }
        global $OUTPUT;
        if (\tool_certification\api::is_user_certified($row->userid, $row->certificationid)) {
            $icon = '';
            if (1 === (int) $row->expirydatelocked) {
                $icon = $OUTPUT->pix_icon('req', get_string('dateoverrided', 'tool_certification'));
            }
            if (0 === (int) $row->expirydate) {
                return get_string('never', 'tool_certification') . $icon;
            }
            return userdate($row->expirydate, get_string('strftimedatefullshort')) . $icon;
        }
        return '-';
    }

    /**
     * Displays column program name on userprogram report.
     *
     * @param string $value
     * @param stdClass $row
     * @param array $args
     * @return string
     */
    public static function userprogramname(string $value, stdClass $row, $args): string {
        global $USER;
        $userid = ((int) $args['userid'] > 0) ? (int) $args['userid'] : 0;
        $options = ['context' => context_system::instance(), 'escape' => false];
        $programname = format_string($row->fullname, true, $options);

        // If this user is the current user show link to dashboard.
        if ($userid === (int) $USER->id) {
            $programurl = new moodle_url('/my');
            return html_writer::link($programurl, $programname);
        }

        // If this user is not current user but a user who can allocate, show link to the allocation page for this program.
        $context = context_system::instance();
        if (permission::can_allocate_anybody_as_organisation_manager() || permission::has_allocateuser_capability($context)) {
            $programurl = new moodle_url('/admin/tool/program/edit.php#program_users_tab', ['id' => $row->programid]);
            return html_writer::link($programurl, $programname);
        }

        // If this report is viewed by a manager who can view reports but not allocate, do not show link.
        return $programname;
    }

    /**
     * Column actions
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function actions(?string $value, stdClass $row): string {
        global $OUTPUT;
        // View user profile, send message, edit user allocation (if has permission).
        $profileicon = $OUTPUT->pix_icon('i/user', get_string('profile'));
        $messageicon = $OUTPUT->pix_icon('t/messages', get_string('sendmessage', 'core_message'));
        $profileurl = new moodle_url('/user/profile.php', ['id' => $row->userid]);
        $messageurl = new moodle_url('/message/index.php', ['id' => $row->userid]);
        $output = html_writer::link($profileurl, $profileicon) . ' ' . html_writer::link($messageurl, $messageicon);
        $params = ['programid' => $row->programid, 'certificationid' => $row->certificationid, 'userid' => $row->userid];
        $programuser = new program_user(0, (object)$params);
        if (permission::can_edit_user_allocation($programuser)) {
            $editicon = $OUTPUT->pix_icon('i/settings', get_string('edit'), 'core');
            $editurl = new moodle_url('/admin/tool/program/edit.php#program_users_tab', ['id' => $row->programid]);
            $output .= html_writer::link($editurl, $editicon);
        }
        return $output;
    }
}
