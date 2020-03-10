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
 * Upgrade scripts for "Certifications" plugin
 *
 * @package     tool_certification
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Remove orphaned data.
 *
 * In some stages during the development we forgot to remove some relevant data when removing entities, so we need to
 * clean it up. For example, deleting certifications when tenant is deleted.
 */
function tool_certification_upgrade_remove_orphaned_certifications() {
    global $DB;
    // Find certifications whose tenant has been deleted. We need to remove those orphan certifications.
    $sql = 'SELECT tc.id
            FROM {tool_certification} tc
            LEFT JOIN {tool_tenant} tt
            ON tc.tenantid = tt.id
            WHERE tt.id IS NULL';
    if ($certificationrecords = $DB->get_records_sql($sql)) {
        // We can not use api method because it may change in the future, instead we delete all
        // data manually here.
        $customfields = \tool_certification\customfield\certification_handler::create();
        foreach ($certificationrecords as $certificationrecord) {
            $customfields->delete_instance($certificationrecord->id);
            $DB->delete_records('tool_certification', ['id' => $certificationrecord->id]);
        }
    }

    // Find all allocations in programs that link to non-existing certifications.
    $sql = 'SELECT pu.id FROM {tool_program_users} pu
            WHERE pu.certificationid > 0
            AND NOT EXISTS (SELECT 1 FROM {tool_certification} tc WHERE tc.id = pu.certificationid)';
    $programuserrecords = $DB->get_records_sql($sql);
    foreach ($programuserrecords as $programuserrecord) {
        $DB->delete_records('tool_program_users', ['id' => $programuserrecord->id]);
    }

    // Find all allocations in certifications that link to non-existing certifications.
    $sql = 'SELECT tcu.id FROM {tool_certification_users} tcu
            LEFT JOIN {tool_certification} tc ON tcu.certificationid = tc.id
            WHERE tc.id IS NULL';
    $certificationuserrecords = $DB->get_records_sql($sql);
    foreach ($certificationuserrecords as $certificationuserrecord) {
        $DB->delete_records('tool_certification_users', ['id' => $certificationuserrecord->id]);
    }

    // Find all orphan certification completions.
    $sql = 'SELECT tcc.id
            FROM {tool_certification_compltion} tcc
            LEFT JOIN {tool_certification} tc ON tcc.certificationid = tc.id
            WHERE tc.id IS NULL';
    $certificationcompletionrecords = $DB->get_records_sql($sql);
    foreach ($certificationcompletionrecords as $certificationcompletionrecord) {
        $DB->delete_records('tool_certification_compltion', ['id' => $certificationcompletionrecord->id]);
    }

    // Remove orphaned calendar events.
    $sql = 'SELECT e.id
            FROM {event} e
            WHERE (e.eventtype = ? OR e.eventtype = ?) AND NOT EXISTS
            (SELECT 1 FROM {tool_certification} tc WHERE tc.id = e.instance)';
    $eventrecords = $DB->get_records_sql($sql, ['tool_certification1', 'tool_certification2']);
    foreach ($eventrecords as $eventrecord) {
        $DB->delete_records('event', ['id' => $eventrecord->id]);
    }
}
