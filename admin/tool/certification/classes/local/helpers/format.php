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
 * Class containing helper methods for format columns data as callbacks.
 *
 * @package   tool_certification
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\local\helpers;

use core\output\inplace_editable;
use tool_certification\api;
use tool_certification\certification;
use tool_certification\constants;
use tool_certification\permission;
use html_writer;
use stdClass;
use context_system;
use coding_exception;
use moodle_url;
use tool_organisation\organisation;

defined('MOODLE_INTERNAL') || die();

/**
 * Class format
 *
 * @package tool_certification
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format {

    /**
     * Column name with inplace editable.
     *
     * @param string    $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     */
    public static function inplace_editable(string $value, stdClass $row) : string {
        global $OUTPUT;

        $edithint = get_string('editcertificationname', 'tool_certification');
        $displayvalue = format_string($value);
        $url = new moodle_url('/admin/tool/certification/edit.php', ['id' => $row->id]);
        $editlabel = get_string('newvaluefor', 'form', $displayvalue);
        $displayvalue = html_writer::link($url, $displayvalue);

        $certification = new certification($row->id);
        $canedit = permission::can_edit_details($certification, context_system::instance());
        $inlineeditable = new inplace_editable('tool_certification', 'certificationname',
            $row->id, $canedit, $displayvalue, $value, $edithint, $editlabel);

        return $OUTPUT->render($inlineeditable);
    }

    /**
     * Displays column tags.
     *
     * @param string    $value
     * @param stdClass $row
     * @return string
     */
    public static function tags(string $value, stdClass $row): string {
        // Get certification tags.
        $listoftags = '';
        $certificationtags = \core_tag_tag::get_item_tags_array('tool_certification', 'tool_certification', $row->id);
        if (!empty($certificationtags)) {
            $tags = array_values($certificationtags);
            foreach ($tags as $tag) {
                $listoftags .= html_writer::span($tag, 'border p-1 text-uppercase font-small my-2 mr-2') . ' ';
            }
        }
        return $listoftags;
    }

    /**
     * Displays column associated programs.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     * @throws \moodle_exception
     */
    public static function program(?string $value, stdClass $row) : string {
        if (!isset($row->program)) {
            // Value of the field {tool_certification}.program .
            throw new \moodle_exception('errormissingassociatedprogram', 'tool_certification');
        }

        if (!$row->programid) {
            // Value of the field {tool_program}.id .
            // This means that field 'program' in certification table is not empty but there is no record
            // in the program table with this id.
            $deletedstr = get_string('deleted', 'tool_certification');
            return html_writer::tag('p', $deletedstr, ['class' => 'cert_program_deleted']);
        }

        if ($row->programarchived) {
            $archivedstr = get_string('archived', 'tool_certification');
            return html_writer::tag('p', $archivedstr, ['class' => 'cert_program_archived']);
        }
        $programurl = new moodle_url('/admin/tool/program/edit.php', [
            'id' => $row->programid,
        ]);
        return html_writer::link($programurl, format_string($row->programfullname));
    }

    /**
     * Displays column archivedon.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function archived_on(?string $value, stdClass $row): string {
        $time = userdate($value, get_string('strftimedatetimeshort'));
        return format_string($time, true);
    }

    /**
     * Displays column duedate.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     */
    public static function duedate(?string $value, stdClass $row): string {
        global $OUTPUT;
        $icon = '';
        if (1 === (int)$row->duedatelocked) {
            $icon = $OUTPUT->pix_icon('req', get_string('dateoverrided', 'tool_certification'));
        }
        if (0 === (int)$value) {
            return get_string('notset', 'tool_certification');
        }
        return userdate($row->duedate, get_string('strftimedatefullshort')) . ' ' . $icon;
    }

    /**
     * Displays column startdate.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     */
    public static function startdate(?string $value, stdClass $row): string {
        global $OUTPUT;
        $icon = '';
        if (1 === (int)$row->startdatelocked) {
            $icon = $OUTPUT->pix_icon('req', get_string('dateoverrided', 'tool_certification'));
        }
        if (0 === (int)$value) {
            return get_string('notset', 'tool_certification');
        }
        return userdate($row->startdate, get_string('strftimedatefullshort')) . ' ' . $icon;
    }

    /**
     * Displays column expirydate.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     */
    public static function userexpirydate(?string $value, stdClass $row): string {
        global $OUTPUT;
        if (api::is_user_certified($row->userid, $row->certificationid)) {
            $icon = '';
            if (1 === (int)$row->expirydatelocked) {
                $icon = $OUTPUT->pix_icon('req', get_string('dateoverrided', 'tool_certification'));
            }
            if (0 === (int)$row->expirydate) {
                return get_string('never', 'tool_certification') . $icon;
            }
            return userdate($row->expirydate, get_string('strftimedatefullshort')) . $icon;
        }
        return '';
    }

    /**
     * Displays allocation source.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function allocation_source(?string $value, stdClass $row): ?string {
        switch ((int)$row->allocationtype) {
            case constants::ALLOCATION_MANUAL:
                return get_string('manual', 'tool_certification');
                break;
            case constants::ALLOCATION_DYNAMIC:
                return get_string('dynamic', 'tool_certification');
                break;
            default:
                throw new \moodle_exception('errorallocationsourcenotfound', 'tool_certification');
                break;
        }
    }

    /**
     * Displays column status.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function status(?string $value, stdClass $row): string {
        $statuses = api::get_user_allocation_status($row->certificationid, $row->userid);
        $statuseshtml = [];
        foreach ($statuses as $status) {
            $statuseshtml[] = html_writer::span($status['statusstr'], $status['status']);
        }
        return implode(' ', $statuseshtml);
    }

    /**
     * Displays column programstatus.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function programstatus(?string $value, stdClass $row): string {
        $statuses = \tool_program\api::get_user_allocation_statuses($row->program, $row->userid, $row->certificationid);
        $statuseshtml = [];
        foreach ($statuses as $status) {
            $statuseshtml[] = html_writer::span($status['statusstr'], $status['status']);
        }
        return implode(' ', $statuseshtml);
    }

    /**
     * Displays column program name on userprogram report.
     *
     * @param string $value
     * @param stdClass $row
     * @param array $args
     * @return string
     */
    public static function usercertificationname(?string $value, stdClass $row, $args): string {
        global $USER;
        $userid = ((int) $args['userid'] > 0) ? (int) $args['userid'] : 0;

        // If this is the current user show link to program.
        if ((int) $USER->id === $userid) {
            $certurl = new moodle_url('/my');
            return html_writer::link($certurl, format_string($row->fullname));
        }

        // If this is not current user but a user who can allocate.
        // Then show link to the allocation page for this certification.
        $canallocate = permission::can_manage_user_allocation(context_system::instance());
        if (((int) $USER->id !== $userid) && $canallocate) {
            $certurl = new moodle_url('/admin/tool/certification/edit.php#!certification_users_tab', [
                'id' => $row->certificationid,
            ]);
            return html_writer::link($certurl, format_string($row->fullname));
        }

        // If this report is viewed by a manager who can view reports but not allocate - no link.
        return format_string($row->fullname);
    }

    /**
     * Displays column program name on userprogram report.
     *
     * @param string $value
     * @param stdClass $row
     * @param array $args
     * @return string
     */
    public static function userprogramname(?string $value, stdClass $row, $args): string {
        global $USER;
        $userid = ((int) $args['userid'] > 0) ? (int) $args['userid'] : 0;

        // If this is the current user show link to program.
        if ((int) $USER->id === $userid) {
            $certurl = new moodle_url('/my');
            return html_writer::link($certurl, format_string($row->programname));
        }

        // If this is not current user but a user who can allocate.
        // Then show link to the allocation page for this program.
        $canallocate = permission::can_manage_user_allocation(context_system::instance());
        if (((int) $USER->id !== $userid) && $canallocate) {
            $certurl = new moodle_url('/admin/tool/program/edit.php#!program_users_tab', [
                'id' => $row->programid,
            ]);
            return html_writer::link($certurl, format_string($row->programname));
        }

        // If this report is viewed by a manager who can view reports but not allocate - no link.
        return format_string($row->programname);
    }

    /**
     * Returns archived not archived string
     *
     * @param string $value
     * @param stdClass $row
     * @param string $format
     * @return string
     */
    public static function archived($value, stdClass $row, $format = null): string {
        if (1 === (int) $value) {
            return get_string('archived', 'tool_certification');
        }
        return get_string('notarchived', 'tool_certification');
    }

    /**
     * Displays column program name on report.
     *
     * @param string $value
     * @param stdClass $row
     * @param array $args
     * @return string
     */
    public static function programname(?string $value, stdClass $row, $args): string {
        return format_string($row->fullname);
    }

    /**
     * Displays columns allocation dates on report.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     */
    public static function allocationdate(?string $value, stdClass $row): string {
        if (1 === (int)$row->allocationstartdatetype) {
            return userdate($row->allocationstartdateabsolute, get_string('strftimedatefullshort'));
        }
        if (1 === (int)$row->allocationenddatetype) {
            return userdate($row->allocationenddateabsolute, get_string('strftimedatefullshort'));
        }
        return '';
    }

    /**
     * Displays columns Certification Start Date on report.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     * @throws \moodle_exception
     */
    public static function certificationstartdate(?string $value, stdClass $row): ?string {
        switch ($row->startdatetype) {
            case constants::DATE_ABSOLUTE:
                return userdate($row->startdateabsolute, get_string('strftimedatefullshort'));
                break;
            case constants::DATE_USER_ALLOCATION_DATE:
                return get_string('allocationdate', 'tool_certification');
                break;
            case constants::DATE_RELATIVE_TO_ALLOCATION_DATE:
                $str = get_string('afterallocationdate', 'tool_certification');
                return $row->startdaterelative . ' ' . $str;
                break;
            default:
                throw new coding_exception('errorstartdatetypenotfound');
                break;
        }
    }

    /**
     * Displays columns Certification Due Date on report.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     */
    public static function certificationduedate(?string $value, stdClass $row): string {
        if ((int) $row->startdatetype === constants::DATE_ABSOLUTE) {
            $duedate = strtotime('+' . $row->duedaterelative, $row->startdateabsolute);
            return userdate($duedate, get_string('strftimedatefullshort'));
        }
        return $row->duedaterelative . ' ' . get_string('afterstartdate', 'tool_certification');
    }

    /**
     * Displays columns Certification Expiry Date on report.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     * @throws \moodle_exception
     */
    public static function certificationexpirydate(?string $value, stdClass $row): ?string {
        switch ($row->expirydatetype) {
            case constants::DATE_NEVER:
                return get_string('never', 'tool_certification');
                break;
            case constants::DATE_ABSOLUTE:
                return userdate($row->expirydateabsolute, get_string('strftimedatefullshort'));
                break;
            case constants::DATE_AFTER_COMPLETION:
                $str = get_string('aftercompletion', 'tool_certification');
                return $row->duedaterelative . ' ' . $str;
                break;
            case constants::DATE_AFTER_ALLOCATION_DATE:
                $str = get_string('afterallocationdate', 'tool_certification');
                return $row->duedaterelative . ' ' . $str;
                break;
            case constants::DATE_AFTER_DUE_DATE:
                if ((int) $row->startdatetype === constants::DATE_ABSOLUTE) {
                    $duedate = strtotime('+' . $row->duedaterelative, $row->startdateabsolute);
                    $expirydate = strtotime('+' . $row->expirydaterelative, $duedate);
                    return userdate($expirydate, get_string('strftimedatefullshort'));
                }

                $str = get_string('afterduedate', 'tool_certification');
                return $row->duedaterelative . ' ' . $str;
                break;
            default:
                throw new coding_exception('errorexpirydatetypenotfound');
                break;
        }
    }

    /**
     * User details and jobs
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function userinfo(?string $value, stdClass $row): string {
        global $PAGE;
        $user = organisation::get_user_with_jobs($row->id);
        if (!$user) {
            // Error may occur here when user who has jobs was moved to another tenant.
            return '';
        }
        $user->set_full_user_record($row);
        // TODO SP-141 remove jobs irrelevant to the $user.

        $output = $PAGE->get_renderer('tool_certification');
        $context = $user->export($output);
        $context->userpicture = strip_links($context->userpicture);
        $context->certificationuserid = $row->certificationuserid;

        return $output->render_from_template('tool_certification/team_user_info_with_extra_info_row', $context);
    }
}
