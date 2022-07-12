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

use context_system;
use html_writer;
use moodle_url;
use stdClass;
use tool_certification\constants;
use tool_certification\permission;
use tool_organisation\organisation;
use tool_tenant\hierarchy;
use tool_wp\local\helpers\string_helper;

/**
 * Formatters for the certification entity
 *
 * @package   tool_certification
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification {

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
     * Returns formatted fullname with a link
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function fullname_with_link(?string $value, stdClass $row): string {
        $url = new moodle_url('/admin/tool/certification/edit.php', ['id' => (int) $row->id]);

        return html_writer::link($url, self::formatstring($row->fullname));
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
                    'badge badge-secondary');
        }

        return $fullname . $badge;
    }

    /**
     * Displays columns Certification Start Date on report.
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
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
     * @param string|null $value
     * @param stdClass $row
     * @return string
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
     * @param string|null $value
     * @param stdClass $row
     * @return string
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
     * @param int|null $value
     * @param stdClass $row
     * @return string
     */
    public static function actions(?int $value, stdClass $row): string {
        global $OUTPUT;

        $output = '';
        $certification = new \tool_certification\certification((int) $row->id);

        if (permission::can_edit_details($certification)) {
            $editurl = new moodle_url('/admin/tool/certification/edit.php', ['id' => $row->id]);
            $editicon = $OUTPUT->pix_icon('i/settings', get_string('edit'));
            $output .= html_writer::link($editurl, $editicon);
        }
        if (permission::can_view_allocated_users($certification)) {
            $allocationurl = new moodle_url('/admin/tool/certification/edit.php', ['id' => $row->id], 'certification_users_tab');
            $usericon = $OUTPUT->pix_icon('i/enrolusers', get_string('allocateusers', 'tool_program'));
            $output .= html_writer::link($allocationurl, $usericon);
        }
        if (permission::can_view_users_progress($certification)) {
            $reporturl = new moodle_url('/admin/tool/certification/progress.php', ['id' => $row->id]);
            $reporticon = $OUTPUT->pix_icon('bar-chart', get_string('progressreport', 'tool_program'), 'tool_wp');
            $output .= html_writer::link($reporturl, $reporticon);
        }

        return $output;
    }

    /**
     * User details and jobs
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function userinfo(?string $value, stdClass $row): string {
        global $PAGE;
        $user = organisation::get_user_with_jobs((int) $row->id);
        if (!$user) {
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
