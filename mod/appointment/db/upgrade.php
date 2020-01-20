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

defined('MOODLE_INTERNAL') || die();

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

    return true;
}
