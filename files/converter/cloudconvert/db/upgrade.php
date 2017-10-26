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

defined('MOODLE_INTERNAL') || die();

function xmldb_fileconverter_cloudconvert_upgrade($oldversion) {
    global $DB;

    // Moodle v3.3.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion <= 2017051501) {
        $existingrecord = $DB->get_record('config', ['name' => 'converter_plugins_sortorder']);

        if($existingrecord) {
            $existingconverters = explode(',', $existingrecord->value);

            // Remove the dummy plugin from the list.
            $existingconverters = join(
                ',',
                array_merge(array_diff($existingconverters, ['dummy']))
            );

            if(!in_array('cloudconvert', $existingconverters)) {
                $existingrecord->value = join(',', (array_merge($existingconverters, ['cloudconvert'])));
                $DB->update_record('config', $existingrecord);
            }
        } else {
            $DB->insert_record(
                'config',
                (object)[
                    'name' => 'converter_plugins_sortorder',
                    'value' => 'cloudconvert'
                ]
            );
        }

        upgrade_plugin_savepoint(true, 2017051501, 'fileconverter', 'cloudconvert');
    }

    return true;
}
