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

function xmldb_fileconverter_dummy_upgrade($oldversion) {
    global $DB;

    // Moodle v3.3.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2017051500) {
        $existingrecord = $DB->get_record('config', ['name' => 'converter_plugins_sortorder']);

        if($existingrecord) {
            $existingrecord->value = join(',', (array_merge(explode(',', $existingrecord->value), ['dummy'])));
            $DB->update_record('config', $existingrecord);
        } else {
            $DB->insert_record(
                'config',
                (object)[
                    'name' => 'converter_plugins_sortorder',
                    'value' => 'dummy'
                ]
            );
        }

        upgrade_plugin_savepoint(true, 2017051500, 'fileconverter', 'dummy');
    }

    return true;
}
