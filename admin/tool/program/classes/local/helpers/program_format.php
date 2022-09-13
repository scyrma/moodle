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
use core_text;
use html_writer;
use moodle_url;
use stdClass;
use tool_program\api;
use tool_program\constants;
use tool_program\permission;
use tool_program\persistent\program;
use tool_wp\local\helpers\string_helper;

/**
 * Class program_format
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_format {

    /**
     * Returns formatted fullname
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function fullname(?string $value, stdClass $row): string {
        return format::string($row->fullname);
    }

    /**
     * Returns formatted fullname with a link
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function fullnamewithlink(?string $value, stdClass $row): string {
        return self::add_program_link((int) $row->id, self::fullname($value, $row));
    }

    /**
     * Returns formatted fullname with image
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function fullnamewithimage(?string $value, stdClass $row): string {
        $image = self::programimage($value, $row);
        $fullname = self::fullname($row->fullname, $row);

        return $image . ' ' . $fullname;
    }

    /**
     * Returns formatted fullname with image and link
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function fullnamewithimageandlink(?string $value, stdClass $row): string {
        return self::add_program_link((int) $row->id, self::fullnamewithimage($value, $row));
    }

    /**
     * Adds a program link to the provided text
     *
     * @param int $programid
     * @param string $text
     * @return string
     */
    private static function add_program_link(int $programid, string $text): string {
        $url = new moodle_url('/admin/tool/program/edit.php', ['id' => $programid]);

        return html_writer::link($url, $text);
    }

    /**
     * Returns concatenated values keeping the links
     *
     * @param string|null $value
     * @param stdClass $row
     * @param string $type
     * @return string
     */
    public static function programwithlink_concat(?string $value, stdClass $row, string $type): string {
        return preg_replace_callback('#<span>([^<]*?)</span data-program="(\d*)">#',
            function($matches) use ($type) {
                return self::$type(null, (object) ['id' => $matches[2], 'fullname' => $matches[1]]);
            }, $value);
    }

    /**
     * Returns program image
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function programimage(?string $value, stdClass $row): string {
        global $CFG;
        require_once($CFG->libdir . '/filestorage/file_storage.php');
        if (empty($row->id)) {
            return '';
        }
        $fs = get_file_storage();
        $context = context_system::instance();
        $storedimages = $fs->get_area_files($context->id, 'tool_program', 'program_image', $row->id, 'filename', false);
        $file = reset($storedimages);
        if ($file) {
            $imageuri = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
                $file->get_itemid(), $file->get_filepath(), $file->get_filename());
        } else {
            $imageuri = api::get_program_pattern($row->id);
        }
        $image = html_writer::empty_tag('img', ['src' => $imageuri, 'alt' => '', 'width' => 35, 'height' => 35]);

        return $image;
    }

    /**
     * Returns formatted id number
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function idnumber(?string $value, stdClass $row): string {
        return format::string($row->idnumber);
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
        return format_text($description, $row->descriptionformat);
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
                return format::date((int) $row->startdateabsolute);
            case constants::DATE_AFTER_USER_ALLOCATION:
                $str = string_helper::translate_relativedate_string($row->startdaterelative);
                return get_string('afteruserallocationdatewithrelativedate', 'tool_program', $str);
            case constants::DATE_NONE:
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
                return format::date((int) $row->duedateabsolute);
            case constants::DATE_AFTER_START:
                if (constants::DATE_ABSOLUTE === (int) $row->startdatetype) {
                    $duedate = strtotime('+' . $row->duedaterelative, $row->startdateabsolute);
                    return format::date((int) $duedate);
                }
                $str = string_helper::translate_relativedate_string($row->duedaterelative);
                return get_string('afterstartdatewithrelativedate', 'tool_program', $str);
            case constants::DATE_AFTER_USER_ALLOCATION:
                $str = string_helper::translate_relativedate_string($row->duedaterelative);
                return get_string('afteruserallocationdatewithrelativedate', 'tool_program', $str);
            case constants::DATE_BEFORE_END:
                if (constants::DATE_ABSOLUTE === (int) $row->enddatetype) {
                    $duedate = strtotime('-' . $row->duedaterelative, $row->enddateabsolute);
                    return format::date((int) $duedate);
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
                return format::date((int) $row->enddateabsolute);
            case constants::DATE_AFTER_START:
                if (constants::DATE_ABSOLUTE === (int) $row->startdatetype) {
                    $enddate = strtotime('+' . $row->enddaterelative, $row->startdateabsolute);
                    return format::date((int) $enddate);
                }
                $str = string_helper::translate_relativedate_string($row->enddaterelative);
                return get_string('afterstartdatewithrelativedate', 'tool_program', $str);
            case constants::DATE_AFTER_DUE:
                if (constants::DATE_ABSOLUTE === (int) $row->duedatetype) {
                    $enddate = strtotime('+' . $row->enddaterelative, $row->duedateabsolute);
                    return format::date((int) $enddate);
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
     * Returns formatted time archived
     *
     * TODO WP-3147 WP-3702 check if it can be removed.
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function timearchived(?string $value, stdClass $row): string {
        return format::date((int) $row->timearchived);
    }

    /**
     * Returns formatted allow direct allocation
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function allowdirectallocation(?string $value, stdClass $row): string {
        if (!isset($row->allowdirectallocation)) {
            return '';
        }
        return format::yesno((bool) $row->allowdirectallocation);
    }

    /**
     * Displays column allocation start date.
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function allocationstartdate(?string $value, stdClass $row): string {
        if (!isset($row->allocationstartdatetype)) {
            return '';
        }
        if (!isset($row->allocationstartdateabsolute)) {
            $row->allocationstartdateabsolute = 0;
        }
        if (1 === (int) $row->allocationstartdatetype) {
            return format::date((int) $row->allocationstartdateabsolute);
        }
        return get_string('notset', 'tool_program');
    }

    /**
     * Displays column allocation end date.
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function allocationenddate(?string $value, stdClass $row): string {
        if (!isset($row->allocationenddatetype)) {
            $row->allocationenddatetype = 0;
        }
        if (constants::DATE_ABSOLUTE === (int) $row->allocationenddatetype) {
            return format::date((int) $row->allocationenddateabsolute);
        }
        if (constants::DATE_AFTER_ALLOCATION_STARTS === (int) $row->allocationenddatetype) {
            if (constants::DATE_ABSOLUTE === (int) $row->allocationstartdatetype) {
                $enddate = strtotime('+' . $row->allocationenddaterelative, $row->allocationstartdateabsolute);
                return format::date((int) $enddate);
            }
            $str = string_helper::translate_relativedate_string($row->allocationenddaterelative);
            return get_string('afterallocationwindowstartswithrelativedate', 'tool_program', $str);
        }
        return get_string('notset', 'tool_program');
    }

    /**
     * Returns visible not visible string
     *
     * @param string|null $value
     * @param stdClass $row
     * @param string $format
     * @return string
     */
    public static function visible(?string $value, stdClass $row, $format = null): string {
        if (!isset($value)) {
            return '';
        }
        return format::yesno(
            (bool) $value,
            get_string('visible', 'tool_program'),
            get_string('notvisible', 'tool_program')
        );
    }

    /**
     * Returns archived not archived string
     *
     * @param string|null $value
     * @param stdClass $row
     * @param string $format
     * @return string
     */
    public static function archived(?string $value, stdClass $row, $format = null): string {
        if (!isset($value)) {
            return '';
        }
        return format::yesno(
            (bool) $row->archived,
            get_string('archived', 'tool_program'),
            get_string('notarchived', 'tool_program')
        );
    }

    /**
     * Returns formatted time modified
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function timemodified(?string $value, stdClass $row): string {
        return format::date((int) $row->timemodified);
    }

    /**
     * Returns formatted time created
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function timecreated(?string $value, stdClass $row): string {
        return format::date((int) $row->timecreated);
    }

    /**
     * Column actions
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function actions(?string $value, stdClass $row): string {
        global $OUTPUT;

        $output = '';
        $program = new program($row->id);
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
     * Returns formatted certification names text with link
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function associatedcertificationswithlink(?string $value, stdClass $row): string {
        $regex = '#<span data-id="(?<id>[^"]*?)">(?<fullname>[^<]*?)</span>#';

        return preg_replace_callback($regex, function($matches) {
            $url = new \moodle_url('/admin/tool/certification/edit.php', ['id' => $matches['id']]);
            return \html_writer::link($url, format_string($matches['fullname']));
        }, $value);
    }
}
