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

declare(strict_types=1);

namespace tool_program\reportbuilder\local\formatters;

use core_reportbuilder\local\helpers\format;
use DateTime;
use html_writer;
use moodle_url;
use stdClass;
use tool_certification\certification;
use tool_program\api;
use tool_program\constants;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\program_tree_progress;

/**
 * Formatters for the program user entity
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 David Matamoros <davidmc@moodle.com>
 * @author    2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_user {

    /**
     * Displays column start date.
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function startdate($value, stdClass $row): string {
        $icon = '';

        if (constants::DATE_LOCKED === (int) $row->startdatelocked) {
            global $OUTPUT;
            $icon = ' ' . $OUTPUT->pix_icon('req', get_string('dateoverriden', 'tool_program'));
        }

        if (0 === (int) $row->startdate) {
            return get_string('notset', 'tool_program') . $icon;
        }

        $dateformat = get_string('strftimedatefullshort', 'core_langconfig');
        return format::userdate((int) $row->startdate, $row, $dateformat) . $icon;
    }

    /**
     * Processes output for due date and end date
     *
     * @param int $date
     * @param int $datelocked
     * @return string
     */
    private static function format_due_date_end_date(int $date, int $datelocked): string {
        $icon = '';

        if (constants::DATE_LOCKED === $datelocked) {
            global $OUTPUT;
            $icon = ' ' . $OUTPUT->pix_icon('req', get_string('dateoverriden', 'tool_program'));
        }

        if (0 === $date && constants::DATE_LOCKED === $datelocked) {
            return get_string('never', 'tool_program') . $icon;
        }

        if (0 === $date && constants::DATE_UNLOCKED === $datelocked) {
            return get_string('notset', 'tool_program');
        }

        $dateformat = get_string('strftimedatefullshort', 'core_langconfig');
        return format::userdate($date, (object)[], $dateformat) . $icon;
    }

    /**
     * Displays column due date.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function duedate($value, stdClass $row): string {
        return self::format_due_date_end_date((int) $row->duedate, (int) $row->duedatelocked);
    }

    /**
     * Displays column end date.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function enddate($value, stdClass $row): string {
        return self::format_due_date_end_date((int) $row->enddate, (int) $row->enddatelocked);
    }

    /**
     * Displays column program status.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function programstatus($value, stdClass $row): string {
        if (!$row->programid) {
            return '-';
        }
        return api::get_user_allocation_status_badges((int) $row->status);
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
            return '';
        }
        $userid = (int) $row->userid;
        // Show information only if user can see this user progress.
        if (!permission::can_view_user_programs_progress($userid)) {
            return '';
        }

        $programid = (int) $row->programid;
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
     * Displays allocation source/type.
     *
     * @param int $value
     * @param stdClass $row
     * @return string
     */
    public static function allocationtype(int $value, stdClass $row): string {
        return api::get_user_allocation_name((int) $row->allocationtype);
    }

    /**
     * Returns array with allocation sources/types.
     *
     * @return array
     */
    public static function get_allocation_sources(): array {
        return [
            constants::ALLOCATION_MANUAL => get_string('manual', 'tool_program'),
            constants::ALLOCATION_DYNAMIC => get_string('dynamic', 'tool_program'),
            constants::ALLOCATION_CERTIFICATION => get_string('certification', 'tool_program')
        ];
    }

    /**
     * Displays column program progress.
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function programprogress($value, stdClass $row): string {
        if (!$row->programid && !$row->userid) {
            return '-';
        }
        $userid = (int) $row->userid;
        // Show information only if user can see this user progress.
        if (!permission::can_view_user_programs_progress($userid)) {
            return '';
        }

        $program = new program((int) $row->programid);
        $programtreeprogress = new program_tree_progress($program, (int) $row->userid);
        return $programtreeprogress->get_program_progress_as_percentage();
    }

    /**
     * Column actions
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
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
        $programuser = new \tool_program\persistent\program_user(0, (object)$params);
        if (permission::can_edit_user_allocation($programuser)) {
            $editicon = $OUTPUT->pix_icon('i/settings', get_string('edit'), 'core');
            $editurl = new moodle_url('/admin/tool/program/edit.php', ['id' => $row->programid], 'program_users_tab');
            $output .= html_writer::link($editurl, $editicon);
        }
        return $output;
    }

    /**
     * Returns formatted daystakingprogram
     *
     * @param int $value
     * @param stdClass $row
     * @return string
     */
    public static function daystakingprogram(int $value, stdClass $row): string {
        $firsttimestamp = (int) $row->startdate;
        if ($firsttimestamp === 0) {
            // If there is no start date set use allocation date.
            $firsttimestamp = (int) $row->timecreated;
        }
        $secondtimestamp = (int) $row->completeddate;
        if ($secondtimestamp === 0) {
            // If there is no completion date use current date.
            $secondtimestamp = time();
        }

        return self::get_days_between_timestamps($firsttimestamp, $secondtimestamp);
    }

    /**
     * Returns formatted dayssincelastallocation
     *
     * @param int $value
     * @param stdClass $row
     * @return string
     */
    public static function dayssinceallocation(int $value, stdClass $row): string {
        $firsttimestamp = (int) $row->timecreated;
        if ($firsttimestamp === 0) {
            return '';
        }
        $secondtimestamp = (int) $row->completeddate;
        if ($secondtimestamp === 0) {
            // If there is no completion date use current date.
            $secondtimestamp = time();
        }

        return self::get_days_between_timestamps($firsttimestamp, $secondtimestamp);
    }

    /**
     * Return the number of days between two timestamps
     *
     * @param int $firsttimestamp
     * @param int $secondtimestamp
     * @return string
     */
    private static function get_days_between_timestamps(int $firsttimestamp, int $secondtimestamp): string {
        $firstdate = new DateTime('@' . $firsttimestamp);
        $seconddate = new DateTime('@' . $secondtimestamp);

        $daysint = $seconddate->diff($firstdate)->format("%a");
        return ($daysint > 0) ? $daysint : get_string('lessthanaday', 'tool_program');
    }

    /**
     * Return the associated certification name
     *
     * @param string $certificationid
     * @return string
     */
    public static function associatedcertification(string $certificationid): string {
        if (0 === (int) $certificationid) {
            return '-';
        }
        return (new certification((int) $certificationid))->get_formatted_name();
    }
}
