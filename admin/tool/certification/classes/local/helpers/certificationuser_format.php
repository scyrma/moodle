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
 * File for class certificationuser_format
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\local\helpers;

use coding_exception;
use html_writer;
use moodle_exception;
use moodle_url;
use stdClass;
use tool_certification\certification;
use tool_certification\constants;
use tool_certification\permission;

defined('MOODLE_INTERNAL') || die();

/**
 * Class certificationuser_format
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certificationuser_format {
    /**
     * Displays allocation source.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function allocationtype(?string $value, stdClass $row): ?string {
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
     * Displays column expirydate.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     * @throws coding_exception
     */
    public static function expirydate(?string $value, stdClass $row): string {
        if (!isset($row->userid) && !isset($row->certificationid)) {
            return '';
        }
        if (0 === (int)$row->expirydate) {
            return get_string('never', 'tool_certification');
        }
        return userdate($row->expirydate, get_string('strftimedatefullshort'));
    }

    /**
     * Displays column status.
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function status(?string $value, stdClass $row): string {
        if (isset($row->certificationid) && (int)$row->certificationid === 0) {
            return '';
        }
        switch ((int)$row->status) {
            case constants::STATUS_FUTUREALLOCATION:
                $str = get_string('futureallocation', 'tool_certification');
                return html_writer::span($str, 'cert_user_status_futureallocation');
                break;
            case constants::STATUS_EXPIRED:
                return html_writer::span(get_string('expired', 'tool_certification'), 'cert_user_status_expired');
                break;
            case constants::STATUS_CERTIFIED:
                return html_writer::span(get_string('certified', 'tool_certification'), 'cert_user_status_certified');
                break;
            case constants::STATUS_OPEN:
                return html_writer::span(get_string('open', 'tool_certification'), 'cert_user_status_open');
                break;
            case constants::STATUS_OVERDUE:
                return html_writer::span(get_string('overdue', 'tool_certification'), 'cert_user_status_overdue');
                break;
            case constants::STATUS_SUSPENDED:
                return html_writer::span(get_string('suspended', 'tool_certification'), 'cert_user_status_suspended');
                break;
            case constants::STATUS_CERTIFIED_AND_SUSPENDED:
                return html_writer::span(get_string('suspended', 'tool_certification'), 'cert_user_status_suspended') .
                    html_writer::span(get_string('certified', 'tool_certification'), 'cert_user_status_certified');
            default:
                return '';
                break;
        }
    }

    /**
     * Round day value down to nearest whole number
     *
     * @param mixed $value
     * @param stdClass $row
     * @return string
     */
    public static function dayround($value, stdClass $row) : string {
        $daysint = floor($value);
        return ($daysint > 0) ? (string)$daysint : get_string('lessthanaday', 'tool_certification');
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

        $certification = new certification($row->certificationid);
        if (permission::can_allocate_anybody($certification)) {
            $str = get_string('allocateusers', 'tool_certification');
            $usericon = $OUTPUT->pix_icon('i/enrolusers', $str);
            $params = ['id' => $row->certificationid];
            $editurl = new moodle_url('/admin/tool/certification/edit.php#certification_users_tab', $params);
            $output .= html_writer::link($editurl, $usericon);
        }
        return $output;
    }
}