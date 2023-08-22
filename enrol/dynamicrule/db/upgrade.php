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
 * Dynamicrule enrol plugin upgrade script
 *
 * @package    enrol_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Execute enrol_dynamicrule upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_enrol_dynamicrule_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2019102900) {
        // Enable plugin globally.
        $enabled = enrol_get_plugins(true);

        if (!isset($enabled['dynamicrule'])) {
            $enabled['dynamicrule'] = true;
            $enabled = array_keys($enabled);
            set_config('enrol_plugins_enabled', implode(',', $enabled));
        }

        // Dynamicrule savepoint reached.
        upgrade_plugin_savepoint(true, 2019102900, 'enrol', 'dynamicrule');
    }

    if ($oldversion < 2020110205) {
        // Fetch all non-broken enrolment outcomes.
        $outcomes = $DB->get_records('tool_dynamicrule_outcome', [
            'classname' => 'enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol',
            'broken' => '0'
        ]);

        // Retain only those with enddate defined.
        $outcomes = array_filter($outcomes, function($outcome){
            $configdata = json_decode($outcome->configdata, 1);
            return !empty($configdata['enddate']);
        });

        foreach ($outcomes as $outcome) {
            // For each outcome with enddate, find enrolment instance.
            $configdata = json_decode($outcome->configdata, 1);
            $enrolinstance = $DB->get_record('enrol', [
                'courseid' => $configdata['coursetoenrol'],
                'enrol' => 'dynamicrule',
                'customint1' => $outcome->ruleid,
            ]);
            if ($enrolinstance) {
                // Update all user enrolments in the instance, no need to verify enddate,
                // it will be 0 as enrolments are not editable.
                $DB->set_field('user_enrolments', 'timeend', $configdata['enddate'], ['enrolid' => $enrolinstance->id]);
                $DB->set_field('user_enrolments', 'timemodified', time(), ['enrolid' => $enrolinstance->id]);

                // User enrolments have changed, so mark users as dirty.
                $userenrolments = $DB->get_records('user_enrolments', ['enrolid' => $enrolinstance->id]);
                foreach ($userenrolments as $userenrolment) {
                    mark_user_dirty($userenrolment->userid);
                }
            }
        }

        // Dynamicrule savepoint reached.
        upgrade_plugin_savepoint(true, 2020110205, 'enrol', 'dynamicrule');
    }
    return true;
}
