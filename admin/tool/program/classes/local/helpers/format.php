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
 * @package   tool_program
 * @copyright 2019, Toni Barberá <toni@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\local\helpers;

use coding_exception;
use context_system;
use core\output\inplace_editable;
use core_tag_tag;
use html_writer;
use moodle_exception;
use moodle_url;
use stdClass;
use tool_certification\certification;
use tool_organisation\organisation;
use tool_program\api;
use tool_program\constants;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_set;
use tool_program\program_tree_progress;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class format
 *
 * @package   tool_program
 * @copyright 2019, Toni Barberá <toni@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format {
    /**
     * Returns visible not visible string
     *
     * @param string $value
     * @param stdClass $row
     * @param string $format
     * @return string
     */
    public static function visible($value, stdClass $row, $format = null): string {
        if (1 === (int) $value) {
            return get_string('visible', 'tool_program');
        }
        return get_string('notvisible', 'tool_program');
    }

    /**
     * Returns expired not expired string
     *
     * @param string $value
     * @param stdClass $row
     * @param string $format
     * @return string
     */
    public static function expired($value, stdClass $row, $format = null): string {
        if (1 === (int) $value) {
            return get_string('expired', 'tool_program');
        }
        return get_string('notexpired', 'tool_program');
    }

    /**
     * Returns true false string
     *
     * @param string $value
     * @param stdClass $row
     * @param string $format
     * @return string
     */
    public static function boolean($value, stdClass $row, $format = null): string {
        if (1 === (int) $value) {
            return get_string('true', 'tool_program');
        }
        return get_string('false', 'tool_program');
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
            return get_string('archived', 'tool_program');
        }
        return get_string('notarchived', 'tool_program');
    }

    /**
     * Format set parent as string.
     *
     * @param string $value
     * @param stdClass $row
     *
     * @return string
     */
    public static function set_parent_name($value, stdClass $row): string {
        if (empty($value) || $value === 0) {
            return '';
        }

        // TODO: Add cache here.
        global $DB;
        return $DB->get_record(
            'tool_program_sets',
            ['id' => $value],
            'name'
        )->name;
    }

    /**
     * Column name with inplace editable.
     *
     * @param string $value
     * @param stdClass $row
     *
     * @return string
     */
    public static function inplace_editable(string $value, stdClass $row): string {
        global $OUTPUT;
        $edithint = get_string('editprogramname', 'tool_program');
        $displayvalue = format_string($value);
        $url = new moodle_url('/admin/tool/program/edit.php', ['id' => $row->id]);
        $editlabel = get_string('newvaluefor', 'form', $displayvalue);
        $displayvalue = html_writer::link($url, $displayvalue);
        $editable = permission::can_edit_details(new program($row->id), context_system::instance());
        $inlineeditable = new inplace_editable('tool_program', 'programname', $row->id, $editable,
            $displayvalue, $value, $edithint, $editlabel);

        return $OUTPUT->render($inlineeditable);
    }

    /**
     * Displays column tags.
     *
     * @param string $value
     * @param stdClass $row
     *
     * @return string
     */
    public static function tags(string $value, stdClass $row): string {
        // Get program tags.
        $listoftags = '';
        $programtags = core_tag_tag::get_item_tags_array('tool_program', 'tool_program', $row->id);
        if (!empty($programtags)) {
            $tags = array_values($programtags);
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
     *
     * @return string
     */
    public static function certification(?string $value, stdClass $row): string {
        $certlist = certification::get_records(['program' => $row->id, 'tenantid' => tenancy::get_tenant_id()]);
        if (empty($certlist)) {
            return '-';
        }

        $output = [];
        foreach ($certlist as $cert) {
            $params = ['context' => context_system::instance(), 'escape' => false];
            $str = format_string($cert->get('fullname'), true, $params);

            if (0 === (int) $cert->get('archived')) {
                $certurl = new moodle_url('/admin/tool/certification/edit.php', [
                    'id' => $cert->get('id'),
                ]);
                $output[] = html_writer::link($certurl, $str);
            } else {
                $output[] = html_writer::span($str, 'dimmed_text');
            }
        }
        return implode(', ', $output);
    }

    /**
     * Displays column archivedon.
     *
     * @param string|null $value
     * @param stdClass $row
     *
     * @return string
     */
    public static function archived_on(?string $value, stdClass $row): string {
        $time = userdate($value, get_string('strftimedatetimeshort'));
        return format_string($time);
    }

    /**
     * Displays column duedate.
     *
     * @param string|null $value
     * @param stdClass $row
     *
     * @return string
     */
    public static function enddate(?string $value, stdClass $row): string {
        global $OUTPUT;
        $icon = '';
        if (1 === (int) $row->enddatelocked) {
            $icon = $OUTPUT->pix_icon('req', get_string('dateoverriden', 'tool_program'));
        }
        if (0 === (int) $value) {
            return get_string('notset', 'tool_program');
        }
        return userdate($row->enddate, get_string('strftimedatefullshort')) . ' ' . $icon;
    }

    /**
     * Displays column duedate.
     *
     * @param string|null $value
     * @param stdClass $row
     *
     * @return string
     */
    public static function startdate(?string $value, stdClass $row): string {
        global $OUTPUT;
        $icon = '';
        if (1 === (int) $row->startdatelocked) {
            $icon = $OUTPUT->pix_icon('req', get_string('dateoverriden', 'tool_program'));
        }
        if (0 === (int) $value) {
            return get_string('notset', 'tool_program');
        }
        return userdate($row->startdate, get_string('strftimedatefullshort')) . ' ' . $icon;
    }

    /**
     * Displays column duedate.
     *
     * @param string|null $value
     * @param stdClass $row
     *
     * @return string
     */
    public static function duedate(?string $value, stdClass $row): string {
        global $OUTPUT;
        $icon = '';
        if (1 === (int) $row->duedatelocked) {
            $icon = $OUTPUT->pix_icon('req', get_string('dateoverriden', 'tool_program'));
        }
        if (0 === (int) $row->duedate && 1 === (int) $row->duedatelocked) {
            return get_string('never', 'tool_program') . ' ' . $icon;
        }
        if (0 === (int) $row->duedate && 0 === (int) $row->duedatelocked) {
            return get_string('notset', 'tool_program') . ' ' . $icon;
        }
        return userdate($row->duedate, get_string('strftimedatefullshort')) . ' ' . $icon;
    }

    /**
     * Displays column completiondate.
     *
     * @param string|null $value
     * @param stdClass $row
     *
     * @return string
     */
    public static function completiondate(?string $value, stdClass $row): string {
        if (0 === (int) $row->completeddate) {
            return get_string('notcompleted', 'tool_program');
        }
        return userdate($row->completeddate, get_string('strftimedatefullshort'));
    }

    /**
     * Displays allocation source.
     *
     * @param string|null $value
     * @param stdClass $row
     *
     * @return string
     */
    public static function allocation_source(?string $value, stdClass $row): ?string {
        return api::get_user_allocation_name((int) $row->allocationtype);
    }

    /**
     * Displays allocation date.
     *
     * @param string|null $value
     * @param stdClass $row
     *
     * @return string
     */
    public static function allocation_date(?string $value, stdClass $row): ?string {
        return userdate($row->timecreated, get_string('strftimedatefullshort'));
    }

    /**
     * Displays column certification status.
     *
     * @param string|null $value
     * @param stdClass $row
     *
     * @return string
     */
    public static function certificationstatus(?string $value, stdClass $row): string {
        if (0 === (int) $row->certificationid) {
            return '-';
        }
        $statuses = \tool_certification\api::get_user_allocation_status($row->certificationid, $row->userid);
        $statuseshtml = [];
        foreach ($statuses as $status) {
            $statuseshtml[] = html_writer::span($status['statusstr'], $status['status']);
        }
        return implode(' ', $statuseshtml);
    }

    /**
     * Displays column program status.
     *
     * @param string|null $value
     * @param stdClass $row
     *
     * @return string
     */
    public static function programstatus(?string $value, stdClass $row): string {
        $statuses = api::get_user_allocation_statuses($row->programid, $row->userid, $row->certificationid);
        $statuseshtml = [];
        foreach ($statuses as $status) {
            $statuseshtml[] = html_writer::span($status['statusstr'], $status['status']);
        }
        return implode(' ', $statuseshtml);
    }

    /**
     * Displays column program progress.
     *
     * @param string|null $value
     * @param stdClass $row
     *
     * @return string
     */
    public static function programprogress(?string $value, stdClass $row): string {
        $programtreeprogress = new program_tree_progress(new program($row->programid), $row->userid);
        return $programtreeprogress->get_program_progress_as_percentage();
    }

    /**
     * Displays column program progress.
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function progressoverviewlink(?string $value, stdClass $row): string {
        global $PAGE, $USER, $CFG;
        $userid = (int) $row->userid;
        $viewasmode = $userid !== (int) $USER->id;
        $programid = $row->programid;
        $program = new program($programid);
        $programtreeprogress = new program_tree_progress($program, $userid);
        $progresspercent = $programtreeprogress->get_program_progress_as_percentage();
        $allocationid = (int) $row->id;
        $output = $PAGE->get_renderer('tool_program');
        $systemcontext = context_system::instance();
        $options = ['context' => $systemcontext, 'escape' => false];
        $programname = format_string($program->get('fullname'), true, $options);
        $overviewstr = get_string('progressoverview', 'tool_program');
        $urlparams = ['programid' => $programid, 'userid' => $userid];
        $userprogramurl = new moodle_url("/$CFG->admin/tool/program/programprogress.php", $urlparams);
        $context = (object) [
            'allocationid' => $allocationid,
            'progresspercent' => $progresspercent,
            'title' => $programname . ': ' . $overviewstr,
            'contextid' => $systemcontext->id,
            'viewasmode' => $viewasmode,
            'userprogramurl' => $userprogramurl->out(false),
        ];
        return $output->render_from_template('tool_program/program_progress_overview', $context);
    }

    /**
     * Displays column user certification.
     *
     * @param string $value
     * @param stdClass $row
     *
     * @return string
     */
    public static function certificationuser(?string $value, stdClass $row): string {
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
     * Displays column status.
     *
     * @param string|null $value
     * @param stdClass $row
     *
     * @return string
     */
    public static function certificationname(?string $value, stdClass $row): string {
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
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
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
    public static function userprogramname(?string $value, stdClass $row, $args): string {
        global $USER;
        $userid = ((int) $args['userid'] > 0) ? (int) $args['userid'] : 0;
        $options = ['context' => context_system::instance(), 'escape' => false];
        $programname = format_string($row->fullname, true, $options);

        // If this is the current user show link to program.
        if ($userid === (int) $USER->id) {
            $programurl = new moodle_url('/my');
            return html_writer::link($programurl, $programname);
        }

        // If this is not current user but a user who can allocate, show link to the allocation page for this program.
        $context = context_system::instance();
        if (permission::can_allocate_anybody_as_organisation_manager() || permission::has_allocateuser_capability($context)) {
            $programurl = new moodle_url('/admin/tool/program/edit.php#!program_users_tab', ['id' => $row->programid]);
            return html_writer::link($programurl, $programname);
        }

        // If this report is viewed by a manager who can view reports but not allocate, do not show link.
        return $programname;
    }

    /**
     * Displays column status.
     *
     * @param string|null $value
     * @param stdClass $row
     *
     * @return string
     */
    public static function userstatus(?string $value, stdClass $row): string {
        $statuses = api::get_user_allocation_statuses($row->programid, $row->userid, $row->certid);
        $statuseshtml = [];
        foreach ($statuses as $status) {
            $statuseshtml[] = html_writer::span($status['statusstr'], $status['status']);
        }
        return implode(' ', $statuseshtml);
    }

    /**
     * Displays column program start date.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function programstartdate(?string $value, stdClass $row): ?string {
        switch ($row->startdatetype) {
            case constants::DATE_NONE:
                return get_string('notset', 'tool_program');
                break;
            case constants::DATE_ABSOLUTE:
                return userdate($row->startdateabsolute, get_string('strftimedatefullshort'));
                break;
            case constants::DATE_AFTER_USER_ALLOCATION:
                $str = get_string('afteruserallocationdate', 'tool_program');
                return $row->startdaterelative . ' ' . $str;
                break;
            default:
                throw new coding_exception('errorstartdatetypenotfound');
                break;
        }
    }

    /**
     * Displays column program due date.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function programduedate(?string $value, stdClass $row): ?string {
        switch ($row->duedatetype) {
            case constants::DATE_NONE:
                return get_string('notset', 'tool_program');
                break;
            case constants::DATE_ABSOLUTE:
                return userdate($row->duedateabsolute, get_string('strftimedatefullshort'));
                break;
            case constants::DATE_AFTER_START:
                if (constants::DATE_ABSOLUTE === (int) $row->startdatetype) {
                    $duedate = strtotime('+' . $row->duedaterelative, $row->startdateabsolute);
                    return userdate($duedate, get_string('strftimedatefullshort'));
                }

                $str = get_string('afterstartdate', 'tool_program');
                return $row->duedaterelative . ' ' . $str;
                break;
            case constants::DATE_AFTER_USER_ALLOCATION:
                $str = get_string('afteruserallocationdate', 'tool_program');
                return $row->duedaterelative . ' ' . $str;
                break;
            case constants::DATE_BEFORE_END:
                if (constants::DATE_ABSOLUTE === (int) $row->enddatetype) {
                    $duedate = strtotime('-' . $row->duedaterelative, $row->enddateabsolute);
                    return userdate($duedate, get_string('strftimedatefullshort'));
                }
                $str = get_string('beforeenddate', 'tool_program');
                return $row->duedaterelative . ' ' . $str;
                break;
            default:
                throw new coding_exception('errorexpirydatetypenotfound');
                break;
        }
    }

    /**
     * Displays column program end date.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function programenddate(?string $value, stdClass $row): ?string {
        switch ($row->enddatetype) {
            case constants::DATE_NONE:
                return get_string('notset', 'tool_program');
                break;
            case constants::DATE_ABSOLUTE:
                return userdate($row->enddateabsolute, get_string('strftimedatefullshort'));
                break;
            case constants::DATE_AFTER_START:
                if (constants::DATE_ABSOLUTE === (int) $row->startdatetype) {
                    $enddate = strtotime('+' . $row->enddaterelative, $row->startdateabsolute);
                    return userdate($enddate, get_string('strftimedatefullshort'));
                }

                $str = get_string('afterstartdate', 'tool_program');
                return $row->enddaterelative . ' ' . $str;
                break;
            case constants::DATE_AFTER_DUE:
                if (constants::DATE_ABSOLUTE === (int) $row->duedatetype) {
                    $enddate = strtotime('+' . $row->enddaterelative, $row->duedateabsolute);
                    return userdate($enddate, get_string('strftimedatefullshort'));
                }

                $str = get_string('afterduedate', 'tool_program');
                return $row->duedaterelative . ' ' . $str;
                break;
            case constants::DATE_AFTER_USER_ALLOCATION:
                $str = get_string('afteruserallocationdate', 'tool_program');
                return $row->duedaterelative . ' ' . $str;
                break;
            default:
                throw new coding_exception('errorexpirydatetypenotfound');
                break;
        }
    }

    /**
     * Displays column allow direct allocation.
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function allowdirectallocation(?string $value, stdClass $row): string {
        if (1 === (int) $value) {
            return get_string('yes');
        }
        return get_string('no');
    }

    /**
     * Displays column allocation start date.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function allocationstartdate(?string $value, stdClass $row): string {
        if (1 === (int) $row->allocationstartdatetype) {
            return userdate($row->allocationstartdateabsolute, get_string('strftimedatefullshort'));
        }
        return get_string('notset', 'tool_program');
    }

    /**
     * Displays column allocation end date.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function allocationenddate(?string $value, stdClass $row): string {
        if (constants::DATE_ABSOLUTE === (int) $row->allocationenddatetype) {
            return userdate($row->allocationenddateabsolute, get_string('strftimedatefullshort'));
        }
        if (constants::DATE_AFTER_ALLOCATION_STARTS === (int) $row->allocationenddatetype) {
            if (constants::DATE_ABSOLUTE === (int) $row->allocationstartdatetype) {
                $enddate = strtotime('+' . $row->allocationenddaterelative, $row->allocationstartdateabsolute);
                return userdate($enddate, get_string('strftimedatefullshort'));
            }
            $str = get_string('afterallocationwindowstarts', 'tool_program');
            return $row->allocationenddaterelative . ' ' . $str;
        }
        return get_string('notset', 'tool_program');
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

        $output = $PAGE->get_renderer('tool_program');
        $context = $user->export($output);
        $context->userpicture = strip_links($context->userpicture);
        $context->programuserid = $row->programuserid;

        return $output->render_from_template('tool_program/team_user_info_with_extra_info_row', $context);
    }

    /**
     * Returns program item name
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function programitemname(?string $value, stdClass $row): string {
        $options = ['context' => context_system::instance(), 'escape' => false];
        if ($row->isset && empty($row->name)) {
            $name = format_string($row->fullname, true, $options);
            $name .= ' (' . get_string('baseset', 'tool_program') . ')';
            return $name;
        }
        return format_string($row->name, true, $options);
    }

    /**
     * Returns program item type
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function programitemtype(?string $value, stdClass $row): string {
        return get_string($row->isset ? 'set' : 'course', 'tool_program');
    }

    /**
     * Returns program item parent name
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function programitemparentname(?string $value, stdClass $row): string {
        if (0 === (int) $row->parent) {
            return '-';
        }
        $program = new program($row->programid);
        $progress = new program_tree_progress($program, $row->userid);
        $basesetitem = $progress->get_baseset();
        if ($basesetitem->get_id() === (int) $row->parent) {
            return get_string('baseset', 'tool_program');
        }
        $parentset = new program_set($row->parent);
        $options = ['context' => context_system::instance(), 'escape' => false];
        return format_string($parentset->get('name'), true, $options);
    }

    /**
     * Returns program item progress
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function programitemprogress(?string $value, stdClass $row): string {
        $program = new program($row->programid);
        $progress = new program_tree_progress($program, $row->userid);
        if ($row->isset) {
            $programitem = $progress->get_branch_by_parentsetid($row->id);
        } else {
            $programitem = $progress->get_first_program_course_item_by_courseid($row->courseid);
        }
        return $programitem ? $programitem->progresspercentage . '%' : '-';
    }

    /**
     * Returns program item completion criteria
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function programitemcompletioncriteria(?string $value, stdClass $row): string {
        if (!$row->isset) {
            return '-';
        }
        switch ($row->completioncriteria) {
            case program_set::COMPLETION_ALL_IN_ANY_ORDER:
                return get_string('completeallinanyorder', 'tool_program');
            case program_set::COMPLETION_ALL_IN_ORDER:
                return get_string('completeallinorder', 'tool_program');
            case program_set::COMPLETION_AT_LEAST:
                return get_string('completeatleast', 'tool_program') . ' ' . $row->completionatleast;
        }
        return '-';
    }
}
