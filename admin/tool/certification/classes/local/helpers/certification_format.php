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
 * File for class certification_format
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\local\helpers;

use coding_exception;
use core_text;
use html_writer;
use moodle_url;
use stdClass;
use tool_certification\api;
use tool_certification\certification;
use tool_certification\constants;
use tool_wp\local\helpers\string_helper;

/**
 * Class certification_format
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_format {

    /**
     * Displays columns Certification Start Date on report.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     * @throws \moodle_exception
     */
    public static function startdate(?string $value, stdClass $row): ?string {
        if (!$row->startdatetype) {
            return '';
        }
        switch ($row->startdatetype) {
            case constants::DATE_ABSOLUTE:
                return userdate($row->startdateabsolute, get_string('strftimedatefullshort'));
                break;
            case constants::DATE_USER_ALLOCATION_DATE:
                return get_string('allocationdate', 'tool_certification');
                break;
            case constants::DATE_RELATIVE_TO_ALLOCATION_DATE:
                $str = string_helper::translate_relativedate_string($row->startdaterelative);
                return get_string('afterallocationdatewithrelativedate', 'tool_certification', $str);
                break;
            default:
                return get_string('errorinvaliddate', 'tool_certification');
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
    public static function duedate(?string $value, stdClass $row): string {
        if (!$row->duedatetype) {
            return '';
        }
        if ((int)$row->duedatetype === constants::DATE_NEVER) {
            return get_string('never');
        }
        if (isset($row->startdatetype) && (int) $row->startdatetype === constants::DATE_ABSOLUTE) {
            $duedate = strtotime('+' . $row->duedaterelative, $row->startdateabsolute);
            return userdate($duedate, get_string('strftimedatefullshort'));
        }
        if (isset($row->duedaterelative)) {
            $str = string_helper::translate_relativedate_string($row->duedaterelative);
            return get_string('afterstartdatewithrelativedate', 'tool_certification', $str);
        }
        return get_string('errorinvaliddate', 'tool_certification');
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
    public static function expirydate(?string $value, stdClass $row): ?string {
        if (!$row->expirydatetype) {
            return '';
        }
        switch ($row->expirydatetype) {
            case constants::DATE_NEVER:
                return get_string('never', 'tool_certification');
                break;
            case constants::DATE_ABSOLUTE:
                return userdate($row->expirydateabsolute, get_string('strftimedatefullshort'));
                break;
            case constants::DATE_AFTER_COMPLETION:
                $str = string_helper::translate_relativedate_string($row->expirydaterelative);
                return get_string('aftercompletionwithrelativedate', 'tool_certification', $str);
                break;
            case constants::DATE_AFTER_ALLOCATION_DATE:
                $str = string_helper::translate_relativedate_string($row->expirydaterelative);
                return get_string('afterallocationdatewithrelativedate', 'tool_certification', $str);
                break;
            case constants::DATE_AFTER_DUE_DATE:
                if ((int) $row->startdatetype === constants::DATE_ABSOLUTE) {
                    $duedate = strtotime('+' . $row->duedaterelative, $row->startdateabsolute);
                    $expirydate = strtotime('+' . $row->expirydaterelative, $duedate);
                    return userdate($expirydate, get_string('strftimedatefullshort'));
                }

                $str = string_helper::translate_relativedate_string($row->expirydaterelative);
                return get_string('afterduedatewithrelativedate', 'tool_certification', $str);
                break;
            default:
                return get_string('errorinvaliddate', 'tool_certification');
                break;
        }
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

        $output = '';
        $certification = new certification($row->id);
        if (\tool_certification\permission::can_edit_details($certification)) {
            $editurl = new moodle_url('/admin/tool/certification/edit.php', ['id' => $row->id]);
            $editicon = $OUTPUT->pix_icon('i/settings', get_string('edit'));
            $output .= html_writer::link($editurl, $editicon);
        }
        if (\tool_certification\permission::can_view_allocated_users($certification)) {
            $str = get_string('allocateusers', 'tool_program');
            $usericon = $OUTPUT->pix_icon('i/enrolusers', $str);
            $allocationurl = new moodle_url('/admin/tool/certification/edit.php#certification_users_tab', ['id' => $row->id]);
            $output .= html_writer::link($allocationurl, $usericon);
        }
        if (\tool_certification\permission::can_view_users_progress($certification)) {
            $reporturl = new moodle_url('/admin/tool/certification/progress.php', ['id' => $row->id]);
            $reporticon = $OUTPUT->pix_icon('bar-chart', get_string('progressreport', 'tool_program'), 'tool_wp');
            $output .= html_writer::link($reporturl, $reporticon);
        }
        return $output;
    }

    /**
     * Returns formatted text with link
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function textwithlink(?string $value, stdClass $row): string {
        $regex = '#<span data-id="(?<id>[^"]*?)">(?<fullname>[^<]*?)</span>#';

        return preg_replace_callback($regex, function($matches) {
            $url = new \moodle_url('/admin/tool/certification/edit.php', ['id' => $matches['id']]);
            return \html_writer::link($url, format_string($matches['fullname']));
        }, $value);
    }
}
