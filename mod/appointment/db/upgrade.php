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
 * Upgrade scripts
 *
 * Copyright (C) 2007-2011 Catalyst IT (http://www.catalyst.net.nz)
 * Copyright (C) 2011-2013 Totara LMS (http://www.totaralms.com)
 * Copyright (C) 2014 onwards Catalyst IT (http://www.catalyst-eu.net)
 *
 * @package    mod_appointment
 * @copyright  2014 onwards Catalyst IT <http://www.catalyst-eu.net>
 * @author     Stacey Walker <stacey@catalyst-eu.net>
 * @author     Alastair Munro <alastair.munro@totaralms.com>
 * @author     Aaron Barnes <aaron.barnes@totaralms.com>
 * @author     Francois Marier <francois@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute mod_appointment upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_appointment_upgrade($oldversion=0) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2019102300) {

        // Define field datetimeknown to be dropped from appointment_sessions.
        $table = new xmldb_table('appointment_sessions');

        $fields = [
            new xmldb_field('datetimeknown'),
            new xmldb_field('normalcost'),
            new xmldb_field('discountcost')
        ];

        foreach ($fields as $field) {
            // Conditionally launch drop field datetimeknown.
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }
        $field = new xmldb_field('location', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'allowwaitlist');

        // Conditionally launch add field location.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Appointment savepoint reached.
        upgrade_mod_savepoint(true, 2019102300, 'appointment');
    }

    if ($oldversion < 2019110400) {

        // Define field detailsformat to be added to appointment_sessions.
        $table = new xmldb_table('appointment_sessions');
        $field = new xmldb_field('detailsformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'details');

        // Conditionally launch add field detailsformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Appointment savepoint reached.
        upgrade_mod_savepoint(true, 2019110400, 'appointment');
    }
    if ($oldversion < 2019112700) {

        // Define field location to be dropped from appointment_sessions.
        $table = new xmldb_table('appointment_sessions');
        $field = new xmldb_field('location');

        // Conditionally launch drop field location.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Appointment savepoint reached.
        upgrade_mod_savepoint(true, 2019112700, 'appointment');
    }

    if ($oldversion < 2020012100) {

        // Define field sessionid to be dropped from appointment_signups.
        $table = new xmldb_table('appointment_signups');
        $field = new xmldb_field('discountcode');

        // Conditionally launch drop field discountcode.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2020012100, 'appointment');
    }

    if ($oldversion < 2020012200) {

        // Define field duration to be dropped from appointment_sessions.
        $table = new xmldb_table('appointment_sessions');
        $field = new xmldb_field('duration');

        // Conditionally launch drop field duration.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Appointment savepoint reached.
        upgrade_mod_savepoint(true, 2020012200, 'appointment');
    }

    if ($oldversion < 2020030200) {
        // Unset manageremail related settings.
        unset_config('appointment_addchangemanageremail');
        unset_config('appointment_manageraddressformat');
        unset_config('appointment_manageraddressformatreadable');

        upgrade_mod_savepoint(true, 2020030200, 'appointment');
    }

    if ($oldversion < 2020091800) {
        // Schedule ad-hoc task to renew sessions calendar entries.
        $record = new \stdClass();
        $record->classname = '\mod_appointment\task\update_calendar_entries';
        $record->component = 'mod_appointment';
        // Next run time based from nextruntime computation in \core\task\manager::queue_adhoc_task().
        $nextruntime = time() - 1;
        $record->nextruntime = $nextruntime;
        $DB->insert_record('task_adhoc', $record);

        upgrade_mod_savepoint(true, 2020091800, 'appointment');
    }

    if ($oldversion < 2020112500) {
        // Set default message settings for appointments affected by WP-2365.
        $defaults = [
            'confirmationsubject' => get_string('setting:defaultconfirmationsubjectdefault', 'appointment'),
            'confirmationmessage' => get_string('setting:defaultconfirmationmessagedefault', 'appointment'),
            'remindersubject'     => get_string('setting:defaultremindersubjectdefault', 'appointment'),
            'remindermessage'     => get_string('setting:defaultremindermessagedefault', 'appointment'),
            'reminderperiod'      => 2,
            'waitlistedsubject'   => get_string('setting:defaultwaitlistedsubjectdefault', 'appointment'),
            'waitlistedmessage'   => get_string('setting:defaultwaitlistedmessagedefault', 'appointment'),
            'cancellationsubject' => get_string('setting:defaultcancellationsubjectdefault', 'appointment'),
            'cancellationmessage' => get_string('setting:defaultcancellationmessagedefault', 'appointment'),
        ];

        // Fetch appointments that have 'confirmationsubject' set to NULL, this indicates
        // that messaging settings has never been added or modified.
        $appointments = $DB->get_records_sql('SELECT * FROM {appointment} WHERE confirmationsubject IS NULL');
        foreach ($appointments as $appointment) {
            $appointment = (object) array_merge((array) $appointment, $defaults);
            $DB->update_record('appointment', $appointment);
        }

        upgrade_mod_savepoint(true, 2020112500, 'appointment');
    }

    if ($oldversion < 2021021200) {

        // Define field confirmationmessageformat to be added to appointment.
        $table = new xmldb_table('appointment');
        $field = new xmldb_field('confirmationmessageformat', XMLDB_TYPE_INTEGER, '2', null,
            XMLDB_NOTNULL, null, '0', 'confirmationmessage');

        // Conditionally launch add field confirmationmessageformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field waitlistedmessageformat to be added to appointment.
        $table = new xmldb_table('appointment');
        $field = new xmldb_field('waitlistedmessageformat', XMLDB_TYPE_INTEGER, '2', null,
            XMLDB_NOTNULL, null, '0', 'waitlistedmessage');

        // Conditionally launch add field waitlistedmessageformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field cancellationmessageformat to be added to appointment.
        $table = new xmldb_table('appointment');
        $field = new xmldb_field('cancellationmessageformat', XMLDB_TYPE_INTEGER, '2', null,
            XMLDB_NOTNULL, null, '0', 'cancellationmessage');

        // Conditionally launch add field cancellationmessageformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field remindermessageformat to be added to appointment.
        $table = new xmldb_table('appointment');
        $field = new xmldb_field('remindermessageformat', XMLDB_TYPE_INTEGER, '2', null,
            XMLDB_NOTNULL, null, '0', 'remindermessage');

        // Conditionally launch add field remindermessageformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Appointment savepoint reached.
        upgrade_mod_savepoint(true, 2021021200, 'appointment');
    }

    if ($oldversion < 2021040600) {
        // Fixing user events malformed in MDL-71156.
        // Delete user events first (like appointment_delete_user_calendar_events does).
        [$insql, $params] = $DB->get_in_or_equal(['appointmentbooking', 'appointmentsession']);
        $whereclause = "modulename = '0' AND eventtype $insql";
        $DB->delete_records_select('event', $whereclause, $params);

        // Schedule ad-hoc task to renew sessions calendar entries.
        $record = new \stdClass();
        $record->classname = '\mod_appointment\task\update_calendar_entries';
        $record->component = 'mod_appointment';
        // Next run time based from nextruntime computation in \core\task\manager::queue_adhoc_task().
        $nextruntime = time() - 1;
        $record->nextruntime = $nextruntime;
        $DB->insert_record('task_adhoc', $record);

        upgrade_mod_savepoint(true, 2021040600, 'appointment');
    }

    if ($oldversion < 2021042600) {
        // Drop appointment_session_data table no longer in use.
        $table = new xmldb_table('appointment_session_data');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Drop appointment_session_field table no longer in use.
        $table = new xmldb_table('appointment_session_field');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Appointment savepoint reached.
        upgrade_mod_savepoint(true, 2021042600, 'appointment');
    }

    if ($oldversion < 2021050500) {
        // Custom from address is no longer needed.
        unset_config('appointment_fromaddress');
        // Appointment savepoint reached.
        upgrade_mod_savepoint(true, 2021050500, 'appointment');
    }

    if ($oldversion < 2021050700) {

        // Define field completionbooked to be added to appointment.
        $table = new xmldb_table('appointment');
        $field = new xmldb_field('completionbooked', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'allowcancellationsdefault');

        // Conditionally launch add field completionbooked.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Appointment savepoint reached.
        upgrade_mod_savepoint(true, 2021050700, 'appointment');
    }

    if ($oldversion < 2021092901) {

        // Define session update message fields to be added to appointment.
        $table = new xmldb_table('appointment');

        // Conditionally launch add field updatesubject.
        $field = new xmldb_field('updatesubject', XMLDB_TYPE_TEXT, null, null,
            null, null, null, 'confirmationmessageformat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Conditionally launch add field updatemessage.
        $field = new xmldb_field('updatemessage', XMLDB_TYPE_TEXT, null, null,
            null, null, null, 'updatesubject');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Conditionally launch add field updatemessageformat.
        $field = new xmldb_field('updatemessageformat', XMLDB_TYPE_INTEGER, '2', null,
            XMLDB_NOTNULL, null, '0', 'updatemessage');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Set default update message settings for existing appointments.
        $defaults = [
            'updatesubject' => get_string('setting:defaultupdatesubjectdefault', 'appointment'),
            'updatemessage' => get_string('setting:defaultupdatemessagedefault', 'appointment'),
            'updatemessageformat' => FORMAT_HTML,
        ];
        $appointments = $DB->get_records('appointment');
        foreach ($appointments as $appointment) {
            $appointment = (object) array_merge((array) $appointment, $defaults);
            $DB->update_record('appointment', $appointment);
        }

        // Define field countmodified to be added to appointment_sessions.
        $table = new xmldb_table('appointment_sessions');
        $field = new xmldb_field('countmodified', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, '0', 'timemodified');

        // Conditionally launch add field countmodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Appointment savepoint reached.
        upgrade_mod_savepoint(true, 2021092901, 'appointment');
    }

    if ($oldversion < 2021100400) {
        // Define field notificationtype to be dropped from appointment_signups.
        $table = new xmldb_table('appointment_signups');
        $field = new xmldb_field('notificationtype');

        // Conditionally launch drop field notificationtype.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Remove MOD_APPOINTMENT_STATUS_SESSION_CANCELLED constant.
        $updatesql = 'UPDATE {appointment_signups_status} SET statuscode = ? WHERE statuscode = ?';
        $DB->execute($updatesql, [MOD_APPOINTMENT_STATUS_USER_CANCELLED, 20]);

        // Remove configuration for sending ical attachment.
        unset_config('appointment_disableicalcancel');

        // Appointment savepoint reached.
        upgrade_mod_savepoint(true, 2021100400, 'appointment');
    }

    if ($oldversion < 2021121400) {
        // Schedule ad-hoc task to create missing custom fields.
        $record = new \stdClass();
        $record->classname = '\mod_appointment\task\update_custom_fields';
        $record->component = 'mod_appointment';
        // Next run time based from nextruntime computation in \core\task\manager::queue_adhoc_task().
        $nextruntime = time() - 1;
        $record->nextruntime = $nextruntime;
        $DB->insert_record('task_adhoc', $record);

        upgrade_mod_savepoint(true, 2021121400, 'appointment');
    }

    if ($oldversion < 2021123000) {
        // Update scheduled task only if it has default configuration.
        $oldconfig = [
            'component' => 'mod_appointment',
            'classname' => '\\mod_appointment\\task\\cron_task',
            'minute'    => '*',
            'hour'      => '1',
            'day'       => '*',
            'month'     => '*',
            'dayofweek' => '*'
        ];
        if ($recordid = $DB->get_field('task_scheduled', 'id', $oldconfig)) {
            $newconfig = [
                'id' => $recordid,
                'minute'    => '0',
                'hour'      => '*',
                'nextruntime' => null,
            ];
            $DB->update_record('task_scheduled', $newconfig);
        }
        upgrade_mod_savepoint(true, 2021123000, 'appointment');
    }

    if ($oldversion < 2022031651) {
        // Schedule ad-hoc task to renew sessions calendar entries following WP-3894.
        $record = new \stdClass();
        $record->classname = '\mod_appointment\task\update_calendar_entries';
        $record->component = 'mod_appointment';
        // Next run time based from nextruntime computation in \core\task\manager::queue_adhoc_task().
        $nextruntime = time() - 1;
        $record->nextruntime = $nextruntime;
        $DB->insert_record('task_adhoc', $record);

        upgrade_mod_savepoint(true, 2022031651, 'appointment');
    }

    return true;
}
