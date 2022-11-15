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
 * Upgrade scripts for "Certifications" plugin
 *
 * @package     tool_certification
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

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

/**
 * Remove orphaned data.
 *
 * In some stages during the development we forgot to remove some relevant data when removing entities, so we need to
 * clean it up.
 *
 * Specifically were forgotten (and fixed):
 * - Remove users from certification component-specific tenant groups that are not allocated to related certification
 * - Remove users from certification component-specific tenant groups that are suspended in related certification
 * - Remove users from component-specific tenant groups that belong to archived certification
 */
function tool_certification_upgrade_remove_users_from_orphan_groups() {
    global $DB;

    // Remove users from certification component-specific tenant groups that are suspended in related certification (#2.2b).
    $sql = "
            SELECT gm.userid, gm.groupid
              FROM {groups_members} gm
              JOIN {tool_tenant_group} ttg ON ttg.groupid = gm.groupid
              JOIN {tool_certification} tc ON ttg.itemid = tc.id
              JOIN {tool_certification_users} tcu ON tcu.certificationid = tc.id AND tcu.userid = gm.userid
             WHERE gm.component = 'enrol_program'
               AND ttg.component = 'tool_certification'
               AND ttg.area = 'tool_certification'
               AND tcu.status = :status
        ";
    $params = ['status' => 0];
    $records = $DB->get_recordset_sql($sql, $params);
    foreach ($records as $record) {
        groups_remove_member($record->groupid, $record->userid);
    }
    $records->close();

    // Remove users from certification component-specific tenant groups that are not allocated to related certification (#2.4).
    $sql = "
            SELECT gm.userid, gm.groupid
              FROM {groups_members} gm
              JOIN {tool_tenant_group} ttg ON ttg.groupid = gm.groupid
              JOIN {tool_certification} tc ON ttg.itemid = tc.id
             WHERE gm.component = 'enrol_program'
               AND ttg.component = 'tool_certification'
               AND ttg.area = 'tool_certification'
               AND gm.userid NOT IN (
                   SELECT tcu.userid
                   FROM {tool_certification_users} tcu
                   WHERE tcu.certificationid = tc.id
               )
        ";
    $records = $DB->get_recordset_sql($sql);
    foreach ($records as $record) {
        groups_remove_member($record->groupid, $record->userid);
    }
    $records->close();

    // Remove users from component-specific tenant groups that belong to archived certification (#1.5).
    $sql = "
            SELECT gm.userid, gm.id, gm.groupid
              FROM {groups_members} gm
              JOIN {tool_tenant_group} ttg ON ttg.groupid = gm.groupid
              JOIN {tool_certification} tc ON ttg.itemid = tc.id
              JOIN {tool_certification_users} tcu ON tcu.certificationid = tc.id AND tcu.userid = gm.userid
             WHERE gm.component = 'enrol_program'
               AND ttg.component = 'tool_certification'
               AND ttg.area = 'tool_certification'
               AND tc.archived = 1
        ";
    $records = $DB->get_recordset_sql($sql);
    foreach ($records as $record) {
        groups_remove_member($record->groupid, $record->userid);
    }
    $records->close();
}

/**
 * Remove certification component-specific tenant groups that belong to non-existing certification (#1.3).
 *
 * This function is used in upgrade script and can not use API methods
 */
function tool_certification_remove_certification_tenant_groups_to_nonexisting_certification() {
    global $DB, $CFG;
    require_once($CFG->libdir . '/enrollib.php');

    $sql = "
            SELECT gm.groupid, ttg.itemid
              FROM {groups_members} gm
              JOIN {tool_tenant_group} ttg ON ttg.groupid = gm.groupid
         LEFT JOIN {tool_certification} tc ON ttg.itemid = tc.id
             WHERE gm.component = 'enrol_program'
               AND ttg.component = 'tool_certification'
               AND ttg.area = 'tool_certification'
               AND tc.id IS NULL
        ";
    $records = $DB->get_records_sql($sql);
    foreach ($records as $record) {
        groups_delete_group($record->groupid);

        $tenantgroups = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
            'itemid' => $record->itemid
        ]);
        foreach ($tenantgroups as $tenantgroup) {
            $tenantgroup->delete();
        }
    }
}
