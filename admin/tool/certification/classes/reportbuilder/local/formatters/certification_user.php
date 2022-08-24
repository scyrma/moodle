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

namespace tool_certification\reportbuilder\local\formatters;

use html_writer;
use moodle_exception;
use moodle_url;
use stdClass;
use tool_certification\certification;
use tool_certification\constants;
use tool_certification\permission;

/**
 * Formatters for the certification user entity
 *
 * @package   tool_certification
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_user {

    /**
     * Displays allocation source.
     *
     * @param string|null $value
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
                throw new moodle_exception('errorallocationsourcenotfound', 'tool_certification');
                break;
        }
    }

    /**
     * Displays column expirydate.
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function expirydate(?string $value, stdClass $row): string {
        if ((!isset($row->userid) && !isset($row->certificationid)) || !isset($row->completionid)) {
            return '';
        }
        if (0 === (int) $row->expirydate) {
            return get_string('never', 'tool_certification');
        }
        return userdate((int) $row->expirydate, get_string('strftimedatefullshort', 'core_langconfig'));
    }

    /**
     * Displays column status.
     *
     * @param string|null $value
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
                return html_writer::span($str, 'tool_certification_cert_user_status_futureallocation');
                break;
            case constants::STATUS_EXPIRED:
                return html_writer::span(
                    get_string('expired', 'tool_certification'),
                    'tool_certification_cert_user_status_expired'
                );
                break;
            case constants::STATUS_CERTIFIED:
                return html_writer::span(
                    get_string('certified', 'tool_certification'),
                    'tool_certification_cert_user_status_certified'
                );
                break;
            case constants::STATUS_OPEN:
                return html_writer::span(
                    get_string('open', 'tool_certification'),
                    'tool_certification_cert_user_status_open'
                );
                break;
            case constants::STATUS_OVERDUE:
                return html_writer::span(
                    get_string('overdue', 'tool_certification'),
                    'tool_certification_cert_user_status_overdue'
                );
                break;
            case constants::STATUS_SUSPENDED:
                return html_writer::span(
                    get_string('suspended', 'tool_certification'),
                    'tool_certification_cert_user_status_suspended'
                );
                break;
            case constants::STATUS_CERTIFIED_AND_SUSPENDED:
                return html_writer::span(
                        get_string('suspended', 'tool_certification'),
                        'tool_certification_cert_user_status_suspended'
                    ) . html_writer::span(
                        get_string('certified', 'tool_certification'),
                        'tool_certification_cert_user_status_certified'
                    );
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
     * Returns array with allocation sources/types.
     *
     * @return array
     */
    public static function get_allocation_sources(): array {
        return [
            constants::ALLOCATION_MANUAL => get_string('manual', 'tool_certification'),
            constants::ALLOCATION_DYNAMIC => get_string('dynamic', 'tool_certification'),
        ];
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
        $profileurl = new moodle_url('/user/profile.php', ['id' => (int) $row->userid]);
        $messageurl = new moodle_url('/message/index.php', ['id' => (int) $row->userid]);
        $output = html_writer::link($profileurl, $profileicon) . ' ' . html_writer::link($messageurl, $messageicon);

        $certification = new certification((int) $row->certificationid);
        if (permission::can_allocate_anybody($certification)) {
            $str = get_string('allocateusers', 'tool_certification');
            $usericon = $OUTPUT->pix_icon('i/enrolusers', $str);
            $params = ['id' => (int) $row->certificationid];
            $editurl = new moodle_url('/admin/tool/certification/edit.php', $params, 'certification_users_tab');
            $output .= html_writer::link($editurl, $usericon);
        }
        return $output;
    }
}
