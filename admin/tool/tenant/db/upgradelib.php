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
 * Upgrade scripts for "Tenant" plugin
 *
 * @package     tool_tenant
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Odei Alba
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Remove orphaned files.
 */
function tool_tenant_upgrade_remove_orphaned_files() {
    global $DB;

    $fileareas = ['headerlogo', 'loginlogo', 'tenantselectorlogo', 'loginbackground', 'favicon'];
    [$select, $params] = $DB->get_in_or_equal($fileareas, SQL_PARAMS_NAMED);

    $params['contextid'] = context_system::instance()->id;
    $params['component'] = 'tool_tenant';

    $sql = 'SELECT f.*
              FROM {files} f
         LEFT JOIN {tool_tenant} t
                ON t.id = f.itemid
             WHERE f.component = :component
               AND f.contextid = :contextid
               AND f.filearea ' . $select . '
               AND t.id IS NULL';

    // Get all tenant files.
    $filerecords = $DB->get_records_sql($sql, $params);

    $fs = get_file_storage();
    foreach ($filerecords as $filerecord) {
        // Delete the file.
        $fs->get_file_instance($filerecord)->delete();
    }
}
