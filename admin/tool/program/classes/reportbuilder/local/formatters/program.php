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

use context_system;
use core_reportbuilder\local\helpers\format;
use html_writer;
use moodle_url;
use stdClass;
use tool_program\api;
use tool_program\constants;
use tool_program\permission;
use tool_tenant\hierarchy;
use tool_wp\local\helpers\string_helper;

/**
 * Formatters for the program entity
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 David Matamoros <davidmc@moodle.com>
 * @author    2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program {

    /**
     * Returns formatted string
     *
     * @param string|null $value
     * @return string
     */
    public static function formatstring(?string $value): string {
        return format_string($value, true, ['context' => context_system::instance(), 'escape' => false]);
    }

    /**
     * Returns fullname with image
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function fullname_with_image(?string $value, stdClass $row): string {
        $image = self::program_image($value, $row);
        $fullname = self::formatstring($row->fullname);

        return $image . ' ' . $fullname;
    }

    /**
     * Returns program image
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function program_image(?string $value, stdClass $row): string {
        global $CFG;

        if (empty($row->id)) {
            return '';
        }

        require_once($CFG->libdir . '/filestorage/file_storage.php');

        $fs = get_file_storage();
        $context = context_system::instance();
        $storedimages = $fs->get_area_files($context->id, 'tool_program', 'program_image', $row->id, 'filename', false);
        $file = reset($storedimages);
        if ($file) {
            $imageuri = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
                $file->get_itemid(), $file->get_filepath(), $file->get_filename());
        } else {
            $imageuri = api::get_program_pattern((int)$row->id);
        }
        return html_writer::img($imageuri, '', ['width' => 35, 'height' => 35]);
    }

    /**
     * Returns formatted fullname with a link
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function fullname_with_link(?string $value, stdClass $row): string {
        return self::program_link((int) $row->id, self::formatstring($value));
    }

    /**
     * Return the program link to the provided text
     *
     * @param int $programid
     * @param string $text
     * @return string
     */
    private static function program_link(int $programid, string $text): string {
        $url = new moodle_url('/admin/tool/program/edit.php', ['id' => $programid]);

        return html_writer::link($url, $text);
    }

    /**
     * Returns formatted fullname with image and link
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function fullname_with_image_and_link(?string $value, stdClass $row): string {
        return self::program_link((int) $row->id, self::fullname_with_image($value, $row));
    }

    /**
     * Returns formatted description
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function description(?string $value, stdClass $row): string {
        if (empty($row->id)) {
            return '';
        }
        $contextid = context_system::instance()->id;
        $description = file_rewrite_pluginfile_urls($row->description, 'pluginfile.php', $contextid,
            'tool_program', 'program_description', $row->id);
        return format_text($description, $row->descriptionformat, ['context' => $contextid]);
    }

    /**
     * Displays column program start date.
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function startdate(?string $value, stdClass $row): string {
        if (!isset($row->startdatetype)) {
            return '';
        }

        switch ($row->startdatetype) {
            case constants::DATE_ABSOLUTE:
                return format::userdate((int) $row->startdateabsolute, (object)[],
                    get_string('strftimedatefullshort', 'core_langconfig'));
            case constants::DATE_AFTER_USER_ALLOCATION:
                $str = string_helper::translate_relativedate_string($row->startdaterelative);
                return get_string('afteruserallocationdatewithrelativedate', 'tool_program', $str);
            default:
                return get_string('notset', 'tool_program');
        }
    }

    /**
     * Displays column program due date.
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function duedate(?string $value, stdClass $row): string {
        if (!isset($row->duedatetype)) {
            return '';
        }

        switch ($row->duedatetype) {
            case constants::DATE_ABSOLUTE:
                return format::userdate((int) $row->duedateabsolute, (object)[],
                    get_string('strftimedatefullshort', 'core_langconfig'));
            case constants::DATE_AFTER_START:
                if (constants::DATE_ABSOLUTE === (int) $row->startdatetype) {
                    $duedate = strtotime('+' . $row->duedaterelative, (int)$row->startdateabsolute);
                    return format::userdate((int) $duedate, (object)[], get_string('strftimedatefullshort', 'core_langconfig'));
                }
                $str = string_helper::translate_relativedate_string($row->duedaterelative);
                return get_string('afterstartdatewithrelativedate', 'tool_program', $str);
            case constants::DATE_AFTER_USER_ALLOCATION:
                $str = string_helper::translate_relativedate_string($row->duedaterelative);
                return get_string('afteruserallocationdatewithrelativedate', 'tool_program', $str);
            case constants::DATE_BEFORE_END:
                if (constants::DATE_ABSOLUTE === (int) $row->enddatetype) {
                    $duedate = strtotime('-' . $row->duedaterelative, (int)$row->enddateabsolute);
                    return format::userdate((int) $duedate, (object)[], get_string('strftimedatefullshort', 'core_langconfig'));
                }
                $str = string_helper::translate_relativedate_string($row->duedaterelative);
                return get_string('beforeenddatewithrelativedate', 'tool_program', $str);
            case constants::DATE_NONE:
            default:
                return get_string('notset', 'tool_program');
        }
    }

    /**
     * Displays column program end date.
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function enddate(?string $value, stdClass $row): string {
        if (!isset($row->enddatetype)) {
            return '';
        }
        switch ($row->enddatetype) {
            case constants::DATE_ABSOLUTE:
                return format::userdate((int) $row->enddateabsolute, (object)[],
                    get_string('strftimedatefullshort', 'core_langconfig'));
            case constants::DATE_AFTER_START:
                if (constants::DATE_ABSOLUTE === (int) $row->startdatetype) {
                    $enddate = strtotime('+' . $row->enddaterelative, (int)$row->startdateabsolute);
                    return format::userdate((int) $enddate, (object)[], get_string('strftimedatefullshort', 'core_langconfig'));
                }
                $str = string_helper::translate_relativedate_string($row->enddaterelative);
                return get_string('afterstartdatewithrelativedate', 'tool_program', $str);
            case constants::DATE_AFTER_DUE:
                if (constants::DATE_ABSOLUTE === (int) $row->duedatetype) {
                    $enddate = strtotime('+' . $row->enddaterelative, (int)$row->duedateabsolute);
                    return format::userdate((int) $enddate, (object)[], get_string('strftimedatefullshort', 'core_langconfig'));
                }
                $str = string_helper::translate_relativedate_string($row->enddaterelative);
                return get_string('afterduedatewithrelativedate', 'tool_program', $str);
            case constants::DATE_AFTER_USER_ALLOCATION:
                $str = string_helper::translate_relativedate_string($row->enddaterelative);
                return get_string('afteruserallocationdatewithrelativedate', 'tool_program', $str);
            case constants::DATE_NONE:
            default:
                return get_string('notset', 'tool_program');
        }
    }

    /**
     * Displays column allocation start date.
     *
     * @param int|null $value
     * @param stdClass $row
     * @return string
     */
    public static function allocation_startdate(?int $value, stdClass $row): string {
        if (!isset($row->allocationstartdatetype)) {
            return '';
        }
        if (!isset($row->allocationstartdateabsolute)) {
            $row->allocationstartdateabsolute = 0;
        }
        if (constants::DATE_ABSOLUTE === (int) $row->allocationstartdatetype) {
            return format::userdate((int) $row->allocationstartdateabsolute, (object)[],
                get_string('strftimedatefullshort', 'core_langconfig'));
        }
        return get_string('notset', 'tool_program');
    }

    /**
     * Displays column allocation end date.
     *
     * @param int|null $value
     * @param stdClass $row
     * @return string
     */
    public static function allocation_enddate(?int $value, stdClass $row): string {
        if (!isset($row->allocationenddatetype)) {
            $row->allocationenddatetype = 0;
        }

        if (constants::DATE_ABSOLUTE === (int) $row->allocationenddatetype) {
            return format::userdate((int) $row->allocationenddateabsolute, (object)[],
                get_string('strftimedatefullshort', 'core_langconfig'));
        }
        if (constants::DATE_AFTER_ALLOCATION_STARTS === (int) $row->allocationenddatetype) {
            if (constants::DATE_ABSOLUTE === (int) $row->allocationstartdatetype) {
                $enddate = strtotime('+' . $row->allocationenddaterelative, (int)$row->allocationstartdateabsolute);
                return format::userdate((int) $enddate, (object)[], get_string('strftimedatefullshort', 'core_langconfig'));
            }
            $str = string_helper::translate_relativedate_string($row->allocationenddaterelative);
            return get_string('afterallocationwindowstartswithrelativedate', 'tool_program', $str);
        }
        return get_string('notset', 'tool_program');
    }

    /**
     * Displays column actions.
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function actions(?string $value, stdClass $row): string {
        global $OUTPUT;

        $output = '';
        $program = new \tool_program\persistent\program($row->id);
        if (permission::can_edit_details($program)) {
            $editurl = new moodle_url('/admin/tool/program/edit.php', ['id' => $row->id]);
            $editicon = $OUTPUT->pix_icon('i/settings', get_string('edit'));
            $output .= html_writer::link($editurl, $editicon);
        }
        if (permission::can_view_allocated_users($program)) {
            $str = get_string('allocateusers', 'tool_program');
            $usericon = $OUTPUT->pix_icon('i/enrolusers', $str);
            $allocationurl = new moodle_url('/admin/tool/program/edit.php#program_users_tab', ['id' => $row->id]);
            $output .= html_writer::link($allocationurl, $usericon);
        }
        if (permission::can_view_users_progress($program)) {
            $reporturl = new moodle_url('/admin/tool/program/usersprogress.php', ['id' => $row->id]);
            $reporticon = $OUTPUT->pix_icon('bar-chart', get_string('progressreport', 'tool_program'), 'tool_wp');
            $output .= html_writer::link($reporturl, $reporticon);
        }
        return $output;
    }

    /**
     * Appends shared badge if needed
     *
     * Note that this method needs 'fullname' and 'tenantid' to be passed in order to work.
     *
     * @param string $fullname
     * @param stdClass $row
     * @return string
     */
    public static function append_shared_badge(string $fullname, stdClass $row): string {
        $badge = '';

        if (in_array($row->tenantid, hierarchy::get_parent_tenants_ids())) {
            $badge = ' ' . html_writer::span(get_string('sharedspace', 'tool_tenant'),
                'badge badge-pill badge-secondary');
        }

        return $fullname . $badge;
    }

    /**
     * Displays column program name on user-program report
     *
     * Depending on the permission that current user has the link onthe program name will direct to programs editor
     * or to "My page".
     *
     * @param string $programname
     * @param stdClass $row
     * @param array $args
     * @return string
     */
    public static function userprogramname(string $programname, stdClass $row, array $args): string {
        global $USER;

        $userid = ((int) $args['userid'] > 0) ? (int) $args['userid'] : 0;

        // If this user is the current user show link to dashboard.
        if ($userid === (int) $USER->id) {
            $programurl = new moodle_url('/my');
            return html_writer::link($programurl, $programname);
        }

        // If this user is not current user but a user who can allocate, show link to the allocation page for this program.
        $context = context_system::instance();
        if (permission::can_allocate_anybody_as_organisation_manager() || permission::has_allocateuser_capability($context)) {
            $programurl = new moodle_url('/admin/tool/program/edit.php', ['id' => $row->programid], 'program_users_tab');
            return html_writer::link($programurl, $programname);
        }

        // If this report is viewed by a manager who can view reports but not allocate, do not show link.
        return $programname;
    }
}
