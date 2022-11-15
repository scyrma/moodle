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

/**
 * Permission class for mod_appointment.
 *
 * @package   mod_appointment
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment;

/**
 * Class permission to perform permission checks.
 *
 * @package   mod_appointment
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permission {

    /**
     * If user can configure custom fields for this plugin.
     *
     * @return bool
     */
    public static function can_configure_custom_fields(): bool {
        return has_capability('mod/appointment:managecustomfields', \context_system::instance());
    }

    /**
     * If user can edit custom fields values for given instance.
     *
     * @param int $instanceid
     * @return bool
     *
     * @deprecated at WP-2645
     */
    public static function can_edit_custom_fields($instanceid): bool {
        debug('Appointment: permission::can_edit_custom_fields has been deprecated');
        return true;
    }

    /**
     * If user can view custom fields values for given instance.
     *
     * @param int $instanceid
     * @return bool
     */
    public static function can_view_custom_fields($instanceid): bool {
        return true;
    }

    /**
     * If session can be booked.
     *
     * Note this does not check if user can book a session, see can_signup.
     *
     * @param stdClass $session
     * @param \context_module $contextmodule
     * @return bool
     */
    public static function is_session_bookable(\stdClass $session, \context_module $contextmodule): bool {
        $timenow = time();
        return (appointment_session_has_capacity($session, $contextmodule) || $session->allowwaitlist) &&
               !appointment_has_session_started($session, $timenow);
    }

    /**
     * If user can signup to given session.
     *
     * @param stdClass $session
     * @param \context_module $contextmodule
     * @return bool
     */
    public static function can_signup(\stdClass $session, \context_module $contextmodule): bool {
        return !isguestuser() && has_capability('mod/appointment:signup', $contextmodule) &&
            self::is_session_bookable($session, $contextmodule) &&
            !appointment_check_signup($session->appointment);
    }

    /**
     * If user can cancel the given session.
     *
     * @param stdClass $session
     * @param \context_module $contextmodule
     * @return bool
     */
    public static function can_cancel_signup(\stdClass $session, \context_module $contextmodule): bool {
        return $session->allowcancellations && has_capability('mod/appointment:signup', $contextmodule) &&
            (!empty($session->usersubmission) || appointment_check_signup($session->appointment) === $session->id) &&
            !appointment_has_session_started($session, time());
    }

    /**
     * Make sure user can signup to given session.
     *
     * @param stdClass $session
     * @param \context_module $contextmodule
     */
    public static function require_can_signup(\stdClass $session, \context_module $contextmodule) {
        if (!self::can_signup($session, $contextmodule)) {
            throw new \moodle_exception('error:nopermissiontosignup', 'mod_appointment');
        }
    }

    /**
     * Make sure user can cancel signup in any session on given module context.
     *
     * @param stdClass $session
     * @param \context_module $contextmodule
     */
    public static function require_can_cancel_signup(\stdClass $session, \context_module $contextmodule) {
        if (!self::can_cancel_signup($session, $contextmodule)) {
            throw new \moodle_exception('error:nopermissiontocancelsignup', 'mod_appointment');
        }
    }

    /**
     * User can add appointment instance.
     *
     * @param \context_course $context
     * @return bool
     */
    public static function can_add_instance(\context_course $context): bool {
        return has_capability('mod/appointment:addinstance', $context);
    }

    /**
     * Make sure user can add appointment instance.
     *
     * @param \context_course $context
     */
    public static function require_can_add_instance(\context_course $context) {
        require_capability('mod/appointment:addinstance', $context);
    }

    /**
     * User can view attendees
     *
     * @param \context_module $context
     * @return bool
     */
    public static function can_view_attendees(\context_module $context): bool {
        return has_capability('mod/appointment:viewattendees', $context);
    }

    /**
     * User can view appointment.
     *
     * @param \context_module $context
     * @return bool
     */
    public static function can_view_appointment(\context_module $context): bool {
        return has_capability('mod/appointment:view', $context);
    }

    /**
     * User can edit sessions
     *
     * @param \context_module $context
     * @return bool
     */
    public static function can_edit_sessions(\context_module $context): bool {
        return has_capability('mod/appointment:editsessions', $context);
    }

    /**
     * Require user to be able to view attendees
     *
     * @param \context_module $context
     */
    public static function require_can_view_attendees(\context_module $context) {
        require_capability('mod/appointment:viewattendees', $context,
            null, true, 'errorcannotviewattendees', 'mod_appointment');
    }

    /**
     * Require user to be able to view appointment
     *
     * @param \context_module $context
     */
    public static function require_can_view_appointment(\context_module $context) {
        require_capability('mod/appointment:view', $context,
            null, true, 'errorcannotviewappointment', 'mod_appointment');
    }

    /**
     * Require user to be able to view attendees
     *
     * @param \context_module $context
     */
    public static function require_can_edit_sessions(\context_module $context) {
        require_capability('mod/appointment:editsessions', $context,
            null, true, 'errorcannoteditsessions', 'mod_appointment');
    }

    /**
     * Require user to be able to manage custom fields
     *
     * @param \context_system $context
     */
    public static function require_can_manage_customfields(\context_system $context) {
        require_capability('mod/appointment:managecustomfields', $context,
            null, true, 'errorcannotmanagecustomfields', 'mod_appointment');
    }
}
