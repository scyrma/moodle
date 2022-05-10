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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Upgrade scripts for "Programs" plugin
 *
 * @package     tool_program
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
 * clean it up.
 *
 * Specifically were forgotten (and fixed):
 * - Deleting programs when tenant is deleted
 * - Deleting records in {tool_program_set_completion} when set or program was deleted
 * - Deleting files when program was deleted
 */
function tool_program_upgrade_remove_orphaned_programs() {
    global $DB;

    // Find programs whose tenant has been deleted. We need to remove those orphan programs.
    $sql = '
            SELECT tp.id
            FROM {tool_program} tp
            LEFT JOIN {tool_tenant} tt
            ON tp.tenantid = tt.id
            WHERE tt.id IS NULL
        ';
    if ($programrecords = $DB->get_records_sql($sql)) {
        // We can not use api method because it may change in the future, instead we delete all
        // data manually here.
        $customfields = \tool_program\customfield\program_handler::create();
        foreach ($programrecords as $programrecord) {
            $customfields->delete_instance($programrecord->id);
            $DB->delete_records('tool_program', ['id' => $programrecord->id]);
        }
    }
    // Find all orphan program sets.
    $sql = '
            SELECT tps.id
            FROM {tool_program_sets} tps
            LEFT JOIN {tool_program} tp
            ON tps.programid = tp.id
            WHERE tp.id IS NULL
        ';
    $programsets = $DB->get_records_sql($sql);
    foreach ($programsets as $programset) {
        $DB->delete_records('tool_program_sets', ['id' => $programset->id]);
    }
    // Find all orphan program courses.
    $sql = '
            SELECT tpc.id
            FROM {tool_program_courses} tpc
            LEFT JOIN {tool_program_sets} tps
            ON tpc.setid = tps.id
            WHERE tps.id IS NULL
        ';
    $programcourses = $DB->get_records_sql($sql);
    foreach ($programcourses as $programcourse) {
        $DB->delete_records('tool_program_courses', ['id' => $programcourse->id]);
    }
    // Find all orphan program users.
    $sql = '
            SELECT tpu.id
            FROM {tool_program_users} tpu
            LEFT JOIN {tool_program} tp
            ON tpu.programid = tp.id
            WHERE tp.id IS NULL
        ';
    $programusers = $DB->get_records_sql($sql);
    foreach ($programusers as $programuser) {
        $DB->delete_records('tool_program_users', ['id' => $programuser->id]);
    }
    // Find orphan program_set_completion records from deleted programs and delete them.
    $sql = '
            SELECT tpsc.id
            FROM {tool_program_set_completion} tpsc
            LEFT JOIN {tool_program_sets} tps
            ON tpsc.setid = tps.id
            WHERE tps.id IS NULL
        ';
    $setcompletions = $DB->get_records_sql($sql);
    foreach ($setcompletions as $setcompletion) {
        $DB->delete_records('tool_program_set_completion', ['id' => $setcompletion->id]);
    }
    // Find all orphan program image and program description files and remove them.
    $sql = "
            SELECT DISTINCT f.contextid, f.component, f.filearea, f.itemid
            FROM {files} f
            LEFT JOIN {tool_program} tp
            ON tp.id = f.itemid
            WHERE f.component = 'tool_program' AND tp.id IS NULL
        ";
    $filerecords = $DB->get_records_sql($sql);
    $fs = get_file_storage();
    foreach ($filerecords as $filerecord) {
        $fs->delete_area_files($filerecord->contextid, $filerecord->component, $filerecord->filearea, $filerecord->itemid);
    }

    // Suspend course enrolments for non-existing programs.
    if ($enrolplugin = enrol_get_plugin('program')) {
        $enrolinstances = $DB->get_records_sql('SELECT e.*
            FROM {enrol} e
            WHERE e.enrol = :program
                AND e.status = :enabled
                AND NOT EXISTS (SELECT 1 FROM {tool_program} p WHERE p.id = e.customint1)',
            ['enabled' => ENROL_INSTANCE_ENABLED, 'program' => 'program']);
        foreach ($enrolinstances as $enrolinstance) {
            $enrolplugin->update_status($enrolinstance, ENROL_INSTANCE_DISABLED);
        }
    }

    // Remove orphaned calendar events.
    $sql = 'SELECT e.id
            FROM {event} e
            WHERE (e.eventtype = ? OR e.eventtype = ?) AND NOT EXISTS
            (SELECT 1 FROM {tool_program} tp WHERE tp.id = e.instance)';
    $eventrecords = $DB->get_records_sql($sql, ['tool_program1', 'tool_program2']);
    foreach ($eventrecords as $eventrecord) {
        $DB->delete_records('event', ['id' => $eventrecord->id]);
    }
}

/**
 * Suspend all user course enrolments from current archived programs.
 */
function tool_program_upgrade_suspend_enrolments_in_archived_programs() {
    global $DB, $CFG;
    require_once($CFG->libdir.'/enrollib.php');

    // Find all programs that are archived or programs inside archived tenants.
    $programs = $DB->get_records_sql('SELECT p.id
        FROM {tool_program} p
        JOIN {tool_tenant} t ON p.tenantid = t.id
        WHERE p.archived = 1 OR t.archived = 1');

    foreach ($programs as $program) {
        tool_program_suspend_enrolments($program->id);
    }
}

/**
 * Suspend enrolments for the given program
 *
 * This function is used in upgrade script and can not use API methods
 *
 * @param int $programid
 */
function tool_program_suspend_enrolments(int $programid) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/enrollib.php');

    if (!$enrolplugin = enrol_get_plugin('program')) {
        return;
    }

    // Find all courses inside this program.
    $sql = 'SELECT DISTINCT pco.courseid
                           FROM {tool_program_courses} pco
                     INNER JOIN {tool_program_sets} s
                             ON s.id = pco.setid
                          WHERE s.programid = :programid ';
    $coursesids = $DB->get_fieldset_sql($sql, ['programid' => $programid]);

    // For each course if the enrolment method exists and enabled - disable it.
    foreach ($coursesids as $courseid) {
        $params = [
            'courseid' => $courseid,
            'enrol' => 'program',
            'customint1' => $programid,
            'status' => ENROL_INSTANCE_ENABLED,
        ];
        if ($enrolinstance = $DB->get_record('enrol', $params)) {
            $enrolplugin->update_status($enrolinstance, ENROL_INSTANCE_DISABLED);
        }
    }
}

/**
 * Remove orphaned data.
 *
 * In some stages during the development we forgot to remove some relevant data when removing entities, so we need to
 * clean it up.
 *
 * Specifically were forgotten (and fixed):
 * - Remove users from program component-specific tenant groups that are not allocated to related program
 * - Remove users from program component-specific tenant groups that are suspended in related program
 * - Remove users from component-specific tenant groups that belong to archived program
 */
function tool_program_upgrade_remove_users_from_orphan_groups() {
    global $DB;

    // Remove users from program component-specific tenant groups that are not allocated to related program (#2.1).
    $sql = "
            SELECT gm.userid, gm.groupid
              FROM {groups_members} gm
              JOIN {tool_tenant_group} ttg ON ttg.groupid = gm.groupid
              JOIN {tool_program} tp
                ON ttg.itemid = tp.id
             WHERE gm.component = 'enrol_program'
               AND ttg.component = 'tool_program'
               AND ttg.area = 'tool_program'
               AND gm.userid NOT IN (
                   SELECT tpu.userid
                   FROM {tool_program_users} tpu
                   WHERE tpu.programid = tp.id
               )
        ";
    $records = $DB->get_recordset_sql($sql);
    foreach ($records as $record) {
        groups_remove_member($record->groupid, $record->userid);
    }
    $records->close();

    // Remove users from program component-specific tenant groups that are suspended in related program (#2.2).
    $sql = "
            SELECT gm.userid, gm.groupid
              FROM {groups_members} gm
              JOIN {tool_tenant_group} ttg ON ttg.groupid = gm.groupid
              JOIN {tool_program} tp ON ttg.itemid = tp.id
              JOIN {tool_program_users} tpu ON tpu.programid = tp.id AND tpu.userid = gm.userid
             WHERE gm.component = 'enrol_program'
               AND ttg.component = 'tool_program'
               AND ttg.area = 'tool_program'
               AND tpu.status = :status
        ";
    $params = ['status' => 0];
    $records = $DB->get_recordset_sql($sql, $params);
    foreach ($records as $record) {
        groups_remove_member($record->groupid, $record->userid);
    }
    $records->close();

    // Remove users from component-specific tenant groups that belong to archived program (#1.4).
    $sql = "
            SELECT gm.userid, gm.groupid
              FROM {groups_members} gm
              JOIN {tool_tenant_group} ttg ON ttg.groupid = gm.groupid
              JOIN {tool_program} tp ON ttg.itemid = tp.id
              JOIN {tool_program_users} tpu ON tpu.programid = tp.id AND tpu.userid = gm.userid
             WHERE gm.component = 'enrol_program'
               AND ttg.component = 'tool_program'
               AND ttg.area = 'tool_program'
               AND tp.archived = 1
        ";
    $records = $DB->get_recordset_sql($sql);
    foreach ($records as $record) {
        groups_remove_member($record->groupid, $record->userid);
    }
    $records->close();
}

/**
 * Remove program component-specific tenant groups that belong to non-existing program (#1.2).
 */
function tool_program_remove_program_tenant_groups_to_nonexisting_program() {
    global $DB, $CFG;
    require_once($CFG->libdir . '/enrollib.php');

    $sql = "
            SELECT gm.groupid, ttg.itemid
              FROM {groups_members} gm
              JOIN {tool_tenant_group} ttg ON ttg.groupid = gm.groupid
         LEFT JOIN {tool_program} tp ON ttg.itemid = tp.id
             WHERE gm.component = 'enrol_program'
               AND ttg.component = 'tool_program'
               AND ttg.area = 'tool_program'
               AND tp.id IS NULL
        ";
    $records = $DB->get_records_sql($sql);
    foreach ($records as $record) {
        groups_delete_group($record->groupid);

        $tenantgroups = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $record->itemid
        ]);
        foreach ($tenantgroups as $tenantgroup) {
            $tenantgroup->delete();
        }
    }
}

/**
 * Remove enrolments in courses that belong to non-existing programs (and remove enrolment method too) (#1.1).
 */
function tool_program_remove_enrolments_to_non_existing_programs() {
    global $DB, $CFG;
    require_once($CFG->libdir . '/enrollib.php');

    if ($enrolplugin = enrol_get_plugin('program')) {
        $sql = "
                SELECT e.*
                FROM {enrol} e
                WHERE e.enrol='program'
                AND e.customint1 NOT IN (
                    SELECT tp.id
                    FROM {tool_program} tp
                )
            ";
        $records = $DB->get_records_sql($sql);
        foreach ($records as $record) {
            $enrolplugin->delete_instance($record);
        }
    }
}
