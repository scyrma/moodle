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
 * Class containing helper methods for format columns data as callbacks.
 *
 * @package    tool_certification
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\local\helpers;

use core\output\inplace_editable;
use tool_certification\certification;
use tool_certification\permission;
use html_writer;
use stdClass;
use context_system;
use coding_exception;
use moodle_url;
use tool_organisation\organisation;
use tool_program\constants;
use tool_program\persistent\program;
use tool_tenant\hierarchy;

defined('MOODLE_INTERNAL') || die();

/**
 * Class format
 *
 * @package    tool_certification
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class format {

    /**
     * Column name with inplace editable.
     *
     * @param string    $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception|\moodle_exception
     */
    public static function inplace_editable(string $value, stdClass $row) : string {
        global $OUTPUT;

        $edithint = get_string('editcertificationname', 'tool_certification');
        $displayvalue = format_string($value, true, ['escape' => false]);
        $url = new moodle_url('/admin/tool/certification/edit.php', ['id' => $row->id]);
        $editlabel = get_string('newvaluefor', 'form', $displayvalue);
        $displayvalue = html_writer::link($url, $displayvalue);

        $certification = new certification($row->id);
        $canedit = permission::can_edit_details($certification);
        $inlineeditable = new inplace_editable('tool_certification', 'certificationname',
            $row->id, $canedit, $displayvalue, $value, $edithint, $editlabel);

        $badge = '';
        if (in_array($certification->get('tenantid'), hierarchy::get_parent_tenants_ids())) {
            $badge = ' ' . \html_writer::span(get_string('sharedspace', 'tool_tenant'),
                    'badge badge-secondary');
        }

        return $OUTPUT->render($inlineeditable) . $badge;
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
        if (!isset($row->program, $row->programid)) {
            return '';
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
            $statuseshtml[] = html_writer::span($status['statusstr'], 'tool_program_'.$status['status']);
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
        $certification = new certification(0, $row);
        $certificationname = format_string($certification->get('fullname'));
        $canallocate = permission::can_view_allocated_users($certification);
        if ($USER->id != $userid && $canallocate) {
            $certurl = new moodle_url('/admin/tool/certification/edit.php#certification_users_tab', [
                'id' => $certification->get('id'),
            ]);
            return html_writer::link($certurl, $certificationname);
        }

        // If this report is viewed by a manager who can view reports but not allocate - no link.
        return $certificationname;
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
        $program = new program(0, $row);
        $programname = format_string($program->get('fullname'));

        // If this is the current user show link to program.
        if ((int) $USER->id === $userid) {
            $certurl = new moodle_url('/my');
            return html_writer::link($certurl, $programname);
        }

        // If this is not current user but a user who can allocate.
        // Then show link to the allocation page for this program.
        $canallocate = \tool_program\permission::can_view_allocated_users($program);
        if ($USER->id != $userid && $canallocate) {
            $certurl = new moodle_url('/admin/tool/program/edit.php#program_users_tab', [
                'id' => $program->get('id'),
            ]);
            return html_writer::link($certurl, $programname);
        }

        // If this report is viewed by a manager who can view reports but not allocate - no link.
        return $programname;
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
            // TODO WP-1662 this also can happen when viewing users on shared certifications bc their jobs are in another tenant.
            return fullname($row);
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
