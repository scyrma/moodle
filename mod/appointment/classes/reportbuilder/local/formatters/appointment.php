<?php
// This file is part of the mod_appointment plugin for Moodle - http://moodle.org/
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

declare(strict_types=1);

namespace mod_appointment\reportbuilder\local\formatters;

use context_module;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot. '/mod/appointment/lib.php');

/**
 * Formatters for the appointment entity
 *
 * @package    mod_appointment
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class appointment {

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

        $contextid = context_module::instance($row->id)->id;
        $description = file_rewrite_pluginfile_urls($row->details, 'pluginfile.php', $contextid,
            'mod_appointment', 'session', $row->appointmentid);
        return format_text($description, $row->detailsformat);
    }

    /**
     * returns session status
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function sessionstatus(?string $value, stdClass $row): string {
        if (!isset($row->sessionid)) {
            return '';
        }
        $session = appointment_get_session($row->sessionid);
        return appointment_get_session_info($session)['status'];
    }

    /**
     * Returns session availability statuses for filter
     *
     * @return array
     */
    public static function get_sessionavailability_statuses(): array {
        return [
            1 => get_string('fullfilter', 'mod_appointment'),
            2 => get_string('empty', 'mod_appointment'),
            3 => get_string('partiallyfull', 'mod_appointment'),
        ];
    }

    /**
     * Returns status of the atendee.
     *
     * @param string|null $value
     * @param stdClass|null $row
     * @return string
     */
    public static function status(?string $value, ?stdClass $row): string {
        $statuses = appointment_statuses();

        // Check code exists.
        if (!isset($statuses[$value])) {
            return '-';
        }
        return get_string('status_' . $statuses[$value], 'appointment');
    }

    /**
     * Returns list of appointment statuses
     *
     * @return array
     */
    public static function get_appointment_statuses(): array {
        $statuslist = [];
        // TODO: WP-1210 Removed excluded statuses when approval functionality is back.
        $excluded = [MOD_APPOINTMENT_STATUS_DECLINED, MOD_APPOINTMENT_STATUS_REQUESTED, MOD_APPOINTMENT_STATUS_APPROVED];
        foreach (appointment_statuses() as $key => $status) {
            if (!in_array($key, $excluded)) {
                $statuslist[$key] = get_string('status_' . $status, 'appointment');
            }
        }
        return $statuslist;
    }
}
