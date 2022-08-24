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
 * Plugin upgrade steps are defined here.
 *
 * @package     tool_certification
 * @category    upgrade
 * @author      2018 Workplace team
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Execute tool_certification upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_certification_upgrade($oldversion) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/certification/db/upgradelib.php');

    $dbman = $DB->get_manager();

    if ($oldversion < 2019040500) {

        // Define field allocationstarts to be dropped from tool_certification_users.
        $table = new xmldb_table('tool_certification_users');
        $field = new xmldb_field('allocationstarts');

        // Conditionally launch drop field allocationstarts.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field allocationends to be dropped from tool_certification_users.
        $field = new xmldb_field('allocationends');

        // Conditionally launch drop field allocationends.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019040500, 'tool', 'certification');
    }

    if ($oldversion < 2019041000) {
        // Check if tool_dynamicrule is installed.
        if (class_exists(\tool_dynamicrule\api::class)) {
            // We add dynamic rules to dynamic rules tab inside certifications.
            $certs = $DB->get_records('tool_certification', null, '', 'id, tenantid');
            if (!empty($certs)) {
                foreach ($certs as $cert) {
                    $params = [
                        'component' => 'tool_certification',
                        'componentarea' => 'certification',
                        'itemid' => $cert->id
                    ];
                    $rulesexist = $DB->record_exists('tool_dynamicrule', $params);
                    if (!$rulesexist) {
                        // Create default dynamic rules for dynamic rules tab.
                        \tool_certification\api::add_default_dynamicrule_conditions_to_certification($cert->id, $cert->tenantid);
                    }
                }
            }
        }
        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019041000, 'tool', 'certification');
    }

    if ($oldversion < 2019050600) {

        // Define field timesuspended to be added to tool_certification_users.
        $table = new xmldb_table('tool_certification_users');
        $field = new xmldb_field('timesuspended', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'status');

        // Conditionally launch add field timesuspended.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019050600, 'tool', 'certification');
    }

    if ($oldversion < 2019050701) {
        // Define table tool_certification_badges to be dropped.
        $table = new xmldb_table('tool_certification_badges');
        // Conditionally launch drop table for tool_certification_badges.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        // Define table tool_certification_compets to be dropped.
        $table = new xmldb_table('tool_certification_compets');
        // Conditionally launch drop table for tool_certification_compets.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        // Define table tool_certification_certs to be dropped.
        $table = new xmldb_table('tool_certification_certs');
        // Conditionally launch drop table for tool_certification_certs.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019050701, 'tool', 'certification');
    }

    if ($oldversion < 2019051700) {

        // Define field timerevoked to be added to tool_certification_compltion.
        $table = new xmldb_table('tool_certification_compltion');
        $field = new xmldb_field('timerevoked', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'expirydate');

        // Conditionally launch add field timerevoked.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019051700, 'tool', 'certification');
    }

    if ($oldversion < 2019052103) {
        // Check if tool_dynamicrule is installed.
        if (class_exists(\tool_dynamicrule\api::class)) {
            // We add dynamic rules to dynamic rules tab inside certifications.
            $certifications = $DB->get_records('tool_certification', null, '', 'id, tenantid');
            if (!empty($certifications)) {
                foreach ($certifications as $certification) {
                    $params = [
                        'component' => 'tool_certification',
                        'componentarea' => 'certification',
                        'itemid' => $certification->id
                    ];
                    $rules = $DB->get_records('tool_dynamicrule', $params);

                    // Delete any previous existing rule.
                    foreach ($rules as $rule) {
                        $rule = \tool_dynamicrule\api::get_rule($rule->id, true);
                        $rule->delete();
                    }

                    if (!$certification->tenantid) {
                        continue;
                    }

                    // Apply all default rules.
                    $tenantid = $certification->tenantid;
                    \tool_certification\api::add_default_dynamicrule_conditions_to_certification($certification->id, $tenantid);
                }
            }
        }
        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019052103, 'tool', 'certification');
    }

    if ($oldversion < 2019052702) {
        // Create default role "Certification manager".
        \tool_tenant\manager::create_workplace_role('tool_certification_manager',
                get_string('rolemanager', 'tool_certification'),
                get_string('rolemanagerdescription', 'tool_certification'),
                ['tool/certification:edit', 'tool/certification:allocateuser']);

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019052702, 'tool', 'certification');
    }

    if ($oldversion < 2019073102) {
        // Remove user allocations for deleted users.
        $sql = "SELECT cu.id
        FROM {tool_certification_users} cu
        INNER JOIN {user} u ON u.id = cu.userid
        WHERE u.deleted = 1";
        $ids = $DB->get_fieldset_sql($sql);

        $DB->delete_records_list('tool_certification_users', 'id', $ids);

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019073102, 'tool', 'certification');
    }

    if ($oldversion < 2019091200) {

        // Define index useridcertificationidtimerevoked (not unique) to be added to tool_certification_compltion.
        $table = new xmldb_table('tool_certification_compltion');
        $params = ['userid', 'certificationid', 'timerevoked'];
        $index = new xmldb_index('useridcertificationidtimerevoked', XMLDB_INDEX_NOTUNIQUE, $params);

        // Conditionally launch add index useridcertificationidtimerevoked.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index certificationiduserid (not unique) to be added to tool_certification_users.
        $table = new xmldb_table('tool_certification_users');
        $index = new xmldb_index('certificationiduserid', XMLDB_INDEX_NOTUNIQUE, ['certificationid', 'userid']);

        // Conditionally launch add index certificationiduserid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index certificationidstatusexpirydate (not unique) to be added to tool_certification_users.
        $table = new xmldb_table('tool_certification_users');
        $params = ['certificationid', 'status', 'expirydate'];
        $index = new xmldb_index('certificationidstatusexpirydate', XMLDB_INDEX_NOTUNIQUE, $params);

        // Conditionally launch add index certificationidstatusexpirydate.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index idnumbertenantid (not unique) to be added to tool_certification.
        $table = new xmldb_table('tool_certification');
        $index = new xmldb_index('idnumbertenantid', XMLDB_INDEX_NOTUNIQUE, ['idnumber', 'tenantid']);

        // Conditionally launch add index idnumbertenantid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define key fk_tenantid (foreign) to be added to tool_certification.
        $table = new xmldb_table('tool_certification');
        $key = new xmldb_key('fk_tenantid', XMLDB_KEY_FOREIGN, ['tenantid'], 'tool_tenant', ['id']);

        // Launch add key fk_tenantid.
        $dbman->add_key($table, $key);

        // Define key fk_program (foreign) to be added to tool_certification.
        $table = new xmldb_table('tool_certification');
        $key = new xmldb_key('fk_program', XMLDB_KEY_FOREIGN, ['program'], 'tool_program', ['id']);

        // Launch add key fk_program.
        $dbman->add_key($table, $key);

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019091200, 'tool', 'certification');
    }

    if ($oldversion < 2019091700) {

        // Define field autocreategroups to be added to tool_certification.
        $table = new xmldb_table('tool_certification');
        $field = new xmldb_field('autocreategroups', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '-1', 'allocationenddateabsolute');

        // Conditionally launch add field autocreategroups.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019091700, 'tool', 'certification');
    }

    if ($oldversion < 2019092300) {
        global $DB;

        // Define field timecertified to be added to tool_certification_compltion.
        $table = new xmldb_table('tool_certification_compltion');
        $field = new xmldb_field('timecertified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'expirydate');

        // Conditionally launch add field timecertified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Copy timecreated to timecertified.
        $sql = "UPDATE {tool_certification_compltion} SET timecertified = timecreated";
        $DB->execute($sql);

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019092300, 'tool', 'certification');
    }

    if ($oldversion < 2019102803) {

        // Define field certifiedby to be added to tool_certification_compltion.
        $table = new xmldb_table('tool_certification_compltion');
        $field = new xmldb_field('certifiedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'timecertified');

        // Conditionally launch add field certifiedby.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field revokedby to be added to tool_certification_compltion.
        $field = new xmldb_field('revokedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'timerevoked');

        // Conditionally launch add field revokedby.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define key fk_certifiedby (foreign) to be added to tool_certification_compltion.
        $key = new xmldb_key('fk_certifiedby', XMLDB_KEY_FOREIGN, ['certifiedby'], 'user', ['id']);

        // Launch add key fk_certifiedby.
        $dbman->add_key($table, $key);

        // Define key fk_revokedby (foreign) to be added to tool_certification_compltion.
        $key = new xmldb_key('fk_revokedby', XMLDB_KEY_FOREIGN, ['revokedby'], 'user', ['id']);

        // Launch add key fk_revokedby.
        $dbman->add_key($table, $key);

        // Update existing revoked certifications records with revokedby.
        $revokedcertifications = $DB->get_records_select('tool_certification_compltion', 'timerevoked != 0');
        foreach ($revokedcertifications as $revokedcertification) {
            $DB->update_record('tool_certification_compltion',
                (object)['id' => $revokedcertification->id, 'revokedby' => $CFG->siteguest]);
        }

        // Update existing manual certifications records with certifiedby.
        $sql = "
            SELECT tcc.id FROM {tool_certification_compltion} tcc
            LEFT JOIN {tool_program_users} tpu
            ON tpu.certificationid = tcc.certificationid
            AND tpu.userid = tcc.userid
            LEFT JOIN {tool_program_sets} tps
            ON tps.programid = tpu.programid AND tps.parent = 0
            LEFT JOIN {tool_program_set_completion} tpsc
            ON tpsc.setid = tps.id AND tpsc.userid = tcc.userid
            WHERE tcc.id IS NOT NULL
            AND tpsc.id IS NULL";
        $manualcertifications = $DB->get_records_sql($sql);
        foreach ($manualcertifications as $manualcertification) {
            $DB->update_record('tool_certification_compltion',
                (object)['id' => $manualcertification->id, 'certifiedby' => $CFG->siteguest]);
        }

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019102803, 'tool', 'certification');
    }

    // Recertification upgrade.
    if ($oldversion < 2019111301) {

        // Define field programid to be added to tool_certification_compltion.
        $table = new xmldb_table('tool_certification_compltion');
        $field = new xmldb_field('programid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timerevoked');

        // Conditionally launch add field programid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field islast to be added to tool_certification_compltion.
        $field = new xmldb_field('islast', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'programid');

        // Conditionally launch add field islast.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field currentprogramid to be added to tool_certification_users.
        $table = new xmldb_table('tool_certification_users');
        $field = new xmldb_field('currentprogramid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'timesuspended');

        // Conditionally launch add field currentprogramid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field isrecertification to be added to tool_certification_users.
        $field = new xmldb_field('isrecertification', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'currentprogramid');

        // Conditionally launch add field isrecertification.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field enddate to be dropped from tool_certification_users.
        $field = new xmldb_field('enddate');

        // Conditionally launch drop field duedate.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field enddatelocked to be dropped from tool_certification_users.
        $field = new xmldb_field('enddatelocked');

        // Conditionally launch drop field duedate.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field graceperiodends to be added to tool_certification_users.
        $field = new xmldb_field('graceperiodends', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'isrecertification');

        // Conditionally launch add field graceperiodends.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field nextstartdate to be added to tool_certification_users.
        $field = new xmldb_field('nextstartdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'graceperiodends');

        // Conditionally launch add field nextstartdate.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field graceperiodendslocked to be added to tool_certification_users.
        $field = new xmldb_field('graceperiodendslocked', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'graceperiodends');

        // Conditionally launch add field graceperiodendslocked.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field requirerecertification to be added to tool_certification.
        $table = new xmldb_table('tool_certification');
        $field = new xmldb_field('requirerecertification', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timearchived');

        // Conditionally launch add field requirerecertification.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field recertdifferentprogram to be added to tool_certification.
        $field = new xmldb_field('recertdifferentprogram', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'requirerecertification');

        // Conditionally launch add field recertdifferentprogram.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field recertificationprogram to be added to tool_certification.
        $field = new xmldb_field('recertificationprogram', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0', 'recertdifferentprogram');

        // Conditionally launch add field recertificationprogram.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field recertstartdaterelative to be added to tool_certification.
        $field = new xmldb_field('recertstartdaterelative', XMLDB_TYPE_CHAR, '255', null,
            null, null, null, 'recertificationprogram');

        // Conditionally launch add field recertstartdaterelative.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field recertgraceperiod to be added to tool_certification.
        $field = new xmldb_field('recertgraceperiod', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'recertstartdaterelative');

        // Conditionally launch add field recertgraceperiod.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field recertexpirydatetype to be added to tool_certification.
        $field = new xmldb_field('recertexpirydatetype', XMLDB_TYPE_INTEGER, '2', null,
            XMLDB_NOTNULL, null, '0', 'recertgraceperiod');

        // Conditionally launch add field recertexpirydatetype.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field recertexpirydaterelative to be added to tool_certification.
        $field = new xmldb_field('recertexpirydaterelative', XMLDB_TYPE_CHAR, '255', null,
            null, null, null, 'recertexpirydatetype');

        // Conditionally launch add field recertexpirydaterelative.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field startdate to be dropped from tool_certification_users.
        $table = new xmldb_table('tool_certification_users');
        $field = new xmldb_field('startdate');

        // Conditionally launch drop field startdate.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field startdatelocked to be dropped from tool_certification_users.
        $table = new xmldb_table('tool_certification_users');
        $field = new xmldb_field('startdatelocked');

        // Conditionally launch drop field startdatelocked.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field duedate to be dropped from tool_certification_users.
        $table = new xmldb_table('tool_certification_users');
        $field = new xmldb_field('duedate');

        // Conditionally launch drop field duedate.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field duedatelocked to be dropped from tool_certification_users.
        $table = new xmldb_table('tool_certification_users');
        $field = new xmldb_field('duedatelocked');

        // Conditionally launch drop field duedatelocked.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define index certificationidstatusexpirydate (not unique) to be dropped form tool_certification_users.
        $table = new xmldb_table('tool_certification_users');
        $index = new xmldb_index('certificationidstatusexpirydate', XMLDB_INDEX_NOTUNIQUE,
            ['certificationid', 'status', 'expirydate']);

        // Conditionally launch drop index certificationidstatusexpirydate.
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Define field expirydate to be dropped from tool_certification_users.
        $table = new xmldb_table('tool_certification_users');
        $field = new xmldb_field('expirydate');

        // Conditionally launch drop field expirydate.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field expirydatelocked to be dropped from tool_certification_users.
        $table = new xmldb_table('tool_certification_users');
        $field = new xmldb_field('expirydatelocked');

        // Conditionally launch drop field expirydatelocked.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Set all completion records with timerevoked=0 to islast true.
        $sql = 'UPDATE {tool_certification_compltion} SET islast = 1
                WHERE timerevoked = 0';
        $DB->execute($sql);

        // Set initial program as current program to all not certified existing user allocations.
        $sql = "SELECT tcu.id, tc.program, tcu.certificationid, tcu.userid
                FROM {tool_certification_users} tcu
                INNER JOIN {tool_certification} tc
                ON tc.id = tcu.certificationid
                LEFT JOIN {tool_certification_compltion} tcc
                ON tcc.certificationid = tcu.certificationid AND tcc.userid = tcu.userid AND tcc.timerevoked = 0
                WHERE tcc.id IS NULL";
        $records = $DB->get_records_sql($sql);
        if ($records) {
            foreach ($records as $record) {
                $sqlupdate = "UPDATE {tool_certification_users} SET currentprogramid = :programid WHERE userid = :userid
                        AND certificationid = :certificationid";
                $params = [
                    'programid' => $record->program,
                    'userid' => $record->userid,
                    'certificationid' => $record->certificationid,
                ];
                $DB->execute($sqlupdate, $params);
            }
        }

        // Add the 2 new dynamic rules to existing certifications.
        // Check if tool_dynamicrule is installed.
        if (class_exists(\tool_dynamicrule\api::class)) {
            // We add dynamic rules to dynamic rules tab inside certifications.
            $certifications = $DB->get_records('tool_certification', null, '', 'id, tenantid');
            if (!empty($certifications)) {
                foreach ($certifications as $certification) {

                    $component = 'tool_certification';
                    $componentarea = 'certification';
                    $configdata = ['certificationid' => $certification->id];
                    $name = get_string('certificationrules', 'tool_certification');

                    $conditions = [
                        'recertification_period_started',
                        'recertification_grace_period_ended'
                    ];

                    foreach ($conditions as $condition) {
                        // Create rule.
                        $ruleid = \tool_dynamicrule\api::create_rule_for_component($component, $componentarea,
                            $certification->id, $certification->tenantid, $name);
                        $conditionclass = '\\tool_certification\\tool_dynamicrule\\condition\\' . $condition;
                        // Create condition. No need to verify user tenancy,
                        // we are creating condition for rule that was just created.
                        \tool_dynamicrule\api::create_rule_condition($ruleid, $conditionclass, $configdata, true);
                    }
                }
            }
        }

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019111301, 'tool', 'certification');
    }

    if ($oldversion < 2019111302) {
        // Update programid in compltion table.
        $sql = 'SELECT tcc.id, tc.program
                FROM {tool_certification_compltion} tcc
                JOIN {tool_certification} tc
                ON tc.id = tcc.certificationid
                WHERE tcc.programid = 0';
        $records = $DB->get_records_sql($sql);
        foreach ($records as $record) {
            $params = [
                'id' => $record->id,
                'programid' => $record->program];
            $sql = 'UPDATE {tool_certification_compltion} SET programid = :programid
                WHERE id = :id';
            $DB->execute($sql, $params);
        }

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019111302, 'tool', 'certification');
    }

    if ($oldversion < 2019112601) {
        // Remove dynamic rules left from deleted certifications.
        $sql = "
            SELECT tdr.id
            FROM {tool_dynamicrule} tdr
            WHERE component = 'tool_certification' AND componentarea = 'certification'
            AND itemid NOT IN (
                SELECT id FROM {tool_certification}
            )
        ";
        $rules = $DB->get_records_sql($sql);
        foreach ($rules as $rule) {
            // TODO Change when WP-1293 is implemented.
            (new \tool_dynamicrule\rule($rule->id))->delete();
        }

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019112601, 'tool', 'certification');
    }

    if ($oldversion < 2019122000) {
        // Remove user alocations to non existent certifications.
        $DB->delete_records_select('tool_certification_users', 'certificationid NOT IN (SELECT id FROM {tool_certification})');

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2019122000, 'tool', 'certification');
    }

    if ($oldversion < 2020010700) {
        // Check if tool_dynamicrule is installed.
        if (class_exists(\tool_dynamicrule\api::class)) {
            // Rename all existing certification rules with the correct string.
            $conditionuserallocated = get_string('conditionuserallocated', 'tool_certification');
            $conditioncertificationoverdue = get_string('conditioncertificationoverdue', 'tool_certification');
            $conditioncertificationcertified = get_string('conditioncertificationcertified', 'tool_certification');
            $conditioncertificationnotcertified = get_string('conditioncertificationnotcertified', 'tool_certification');
            $conditioncertificationexpired = get_string('conditioncertificationexpired', 'tool_certification');
            $conditioncertificationsuspended = get_string('conditioncertificationsuspended', 'tool_certification');
            $conditionrecertificationstarted = get_string('conditionrecertificationstarted', 'tool_certification');
            $conditionrecertificationgraceperiod = get_string('conditionrecertificationgraceperiod', 'tool_certification');
            $certificationrules = get_string('certificationrules', 'tool_certification');

            $sql = "SELECT drule.id, drcond.classname
                FROM {tool_dynamicrule} drule
                JOIN {tool_dynamicrule_condition} drcond
                ON drule.id = drcond.ruleid
                WHERE drule.name = :certificationrules AND drule.component = 'tool_certification'
                AND drule.componentarea = 'certification'
            ";
            $records = $DB->get_records_sql($sql, ['certificationrules' => $certificationrules]);
            foreach ($records as $record) {
                switch ($record->classname) {
                    case 'tool_certification\tool_dynamicrule\condition\user_allocated':
                        $DB->set_field('tool_dynamicrule', 'name', $conditionuserallocated, ['id' => $record->id]);
                        break;
                    case 'tool_certification\tool_dynamicrule\condition\certification_overdue':
                        $DB->set_field('tool_dynamicrule', 'name', $conditioncertificationoverdue, ['id' => $record->id]);
                        break;
                    case 'tool_certification\tool_dynamicrule\condition\certification_certified':
                        $DB->set_field('tool_dynamicrule', 'name', $conditioncertificationcertified, ['id' => $record->id]);
                        break;
                    case 'tool_certification\tool_dynamicrule\condition\certification_not_certified':
                        $DB->set_field('tool_dynamicrule', 'name', $conditioncertificationnotcertified, ['id' => $record->id]);
                        break;
                    case 'tool_certification\tool_dynamicrule\condition\certification_expired':
                        $DB->set_field('tool_dynamicrule', 'name', $conditioncertificationexpired, ['id' => $record->id]);
                        break;
                    case 'tool_certification\tool_dynamicrule\condition\certification_suspended':
                        $DB->set_field('tool_dynamicrule', 'name', $conditioncertificationsuspended, ['id' => $record->id]);
                        break;
                    case 'tool_certification\tool_dynamicrule\condition\recertification_period_started':
                        $DB->set_field('tool_dynamicrule', 'name', $conditionrecertificationstarted, ['id' => $record->id]);
                        break;
                    case 'tool_certification\tool_dynamicrule\condition\recertification_grace_period_ended':
                        $DB->set_field('tool_dynamicrule', 'name', $conditionrecertificationgraceperiod, ['id' => $record->id]);
                        break;
                    default:
                        break;
                }
            }
        }

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2020010700, 'tool', 'certification');
    }

    if ($oldversion < 2020030401) {
        // Remove orphaned certifications.
        tool_certification_upgrade_remove_orphaned_certifications();

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2020030401, 'tool', 'certification');
    }

    if ($oldversion < 2020031001) {
        // Find certifications with type 'After start date' and due date null and change type to 'Never'.
        $sql = '
            SELECT *
            FROM {tool_certification}
            WHERE duedatetype = :duedatetype AND duedaterelative IS NULL
        ';
        $params['duedatetype'] = \tool_certification\constants::DATE_AFTER_START_DATE;
        $certifications = $DB->get_records_sql($sql, $params);
        foreach ($certifications as $certification) {
            $certification->duedatetype = \tool_certification\constants::DATE_NEVER;
            $DB->update_record('tool_certification', $certification);
        }

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2020031001, 'tool', 'certification');
    }

    if ($oldversion < 2020061502) {
        // Modify all certification calendar events.
        $sql = "SELECT * FROM {event} WHERE eventtype = 'tool_certification1' OR eventtype = 'tool_certification2'";
        $events = $DB->get_records_sql($sql);
        foreach ($events as $event) {
            $event->type = CALENDAR_EVENT_TYPE_ACTION;
            $event->component = 'tool_certification';
            $event->modulename = '';
            $event->courseid = 0;
            $event->categoryid = 0;
            $DB->update_record('event', $event);
        }

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2020061502, 'tool', 'certification');
    }

    if ($oldversion < 2020071502) {

        tool_certification_upgrade_remove_users_from_orphan_groups();

        // Remove certification component-specific tenant groups that belong to non-existing certification.
        tool_certification_remove_certification_tenant_groups_to_nonexisting_certification();

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2020071502, 'tool', 'certification');
    }

    if ($oldversion < 2020090100) {
        // Schedule ad-hoc task to deallocate users from previous tenant certifications.
        $record = new \stdClass();
        $record->classname = '\tool_certification\task\deallocate_from_previous_tenant_certifications';
        $record->component = 'tool_certification';
        // Next run time based from nextruntime computation in \core\task\manager::queue_adhoc_task().
        $nextruntime = time() - 1;
        $record->nextruntime = $nextruntime;
        $DB->insert_record('task_adhoc', $record);

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2020090100, 'tool', 'certification');
    }

    if ($oldversion < 2020091000) {

        // Define field shared to be added to tool_certification.
        $table = new xmldb_table('tool_certification');
        $field = new xmldb_field('shared', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'recertexpirydaterelative');

        // Conditionally launch add field shared.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2020091000, 'tool', 'certification');
    }

    if ($oldversion < 2021032300) {
        // Check if site ran the wrong upgrade step (MDL-71156).
        if ($DB->record_exists('upgrade_log', ['version' => '2020110901.09', 'plugin' => 'core'])) {
            // Schedule ad-hoc task to refresh all certification calendar events.
            $record = new \stdClass();
            $record->classname = '\tool_certification\task\refresh_certification_calendar_events';
            $record->component = 'tool_certification';

            // Next run time based from nextruntime computation in \core\task\manager::queue_adhoc_task().
            $nextruntime = time() - 1;
            $record->nextruntime = $nextruntime;
            $DB->insert_record('task_adhoc', $record);
        }

        // Program savepoint reached.
        upgrade_plugin_savepoint(true, 2021032300, 'tool', 'certification');
    }

    if ($oldversion < 2021050602) {
        // Find duplicate certification user allocations before converting index to unique.
        $sql = '
            SELECT MIN(id) as minid, tcu.userid, tcu.certificationid
            FROM {tool_certification_users} tcu
            GROUP BY userid,certificationid
            HAVING COUNT(*) > 1
        ';
        $duplicateuserallocations = $DB->get_records_sql($sql);
        foreach ($duplicateuserallocations as $userallocation) {
            // Delete duplicate allocations and keep the ones with smaller ids.
            $params = [
                'id' => $userallocation->minid,
                'userid' => $userallocation->userid,
                'certificationid' => $userallocation->certificationid,
            ];
            $DB->delete_records_select('tool_certification_users',
                'id <> :id AND userid = :userid AND certificationid = :certificationid',
                $params);
        }

        // Define index certificationiduserid (unique) to be dropped form tool_certification_users.
        $table = new xmldb_table('tool_certification_users');
        $index = new xmldb_index('certificationiduserid', XMLDB_INDEX_NOTUNIQUE, ['certificationid', 'userid']);

        // Conditionally launch drop index certificationiduserid.
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Define index certificationiduserid (unique) to be added to tool_certification_users.
        $table = new xmldb_table('tool_certification_users');
        $index = new xmldb_index('certificationiduserid', XMLDB_INDEX_UNIQUE, ['certificationid', 'userid']);

        // Conditionally launch add index certificationiduserid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Certification savepoint reached.
        upgrade_plugin_savepoint(true, 2021050602, 'tool', 'certification');
    }

    return true;
}
