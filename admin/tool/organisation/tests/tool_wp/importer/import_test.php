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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_organisation\tool_wp\importer;

use advanced_testcase;
use stdClass;
use tool_organisation_generator;
use tool_tenant_generator;
use tool_organisation\organisation;
use tool_wp\local\exportimport\helper;
use tool_organisation\tool_wp\exporter\jobs as jobs_exporter;
use tool_organisation\tool_wp\exporter\orgstructure as orgstructure_exporter;
use tool_wp_generator;

/**
 * Tests for the import API.
 *
 * @package    tool_organisation
 * @covers     \tool_organisation\tool_wp\importer\orgstructure
 * @covers     \tool_organisation\tool_wp\importer\jobs
 * @covers     \tool_organisation\tool_wp\importer\departments_csv
 * @covers     \tool_organisation\tool_wp\importer\positions_csv
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class import_test extends advanced_testcase {

    /** @var stdClass */
    protected $pf;
    /** @var stdClass */
    protected $pfother;
    /** @var stdClass */
    protected $pftenantother;
    /** @var stdClass */
    protected $pa;
    /** @var stdClass */
    protected $pb;
    /** @var stdClass */
    protected $pa1;
    /** @var stdClass */
    protected $pa2;
    /** @var stdClass */
    protected $pb1;


    /** @var stdClass */
    protected $df;
    /** @var stdClass */
    protected $dfother;
    /** @var stdClass */
    protected $dftenantother;
    /** @var stdClass */
    protected $da;
    /** @var stdClass */
    protected $db;
    /** @var stdClass */
    protected $da1;
    /** @var stdClass */
    protected $da2;
    /** @var stdClass */
    protected $db1;

    /** @var array */
    protected $users = [];

    /** @var stdClass */
    protected $tenant;
    /** @var stdClass */
    protected $tenantother;

    /**
     * Tenant generator
     *
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Organisation generator
     *
     * @return tool_organisation_generator
     */
    protected function get_generator() : tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }

    /**
     * WP generator
     *
     * @return tool_wp_generator
     */
    protected function get_workplace_generator() : tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }

    /**
     * Sets the user as a tenant admin for his tenant
     *
     * @param int $userid
     */
    protected function make_user_tenant_admin(int $userid) {
        (new \tool_tenant\manager())->assign_tenant_admin_roles([$userid], \tool_tenant\tenancy::get_tenant_id($userid));
    }

    /**
     * Generates a user, allocates to the tenant and gives a job
     *
     * @param string $username
     * @param string|null $position
     * @param string|null $department
     * @return stdClass
     */
    protected function generate_user(string $username, string $position = null, string $department = null) : stdClass {
        if (!array_key_exists($username, $this->users)) {
            $user = $this->getDataGenerator()->create_user(['username' => $username]);
            $this->get_tenant_generator()->allocate_user($user->id, $this->tenant->id);
            $this->users[$username] = $user;
        }
        if ($position && $department) {
            $this->get_generator()->assign_job((object)['userid' => $this->users[$username]->id,
                'positionid' => $this->{$position}->id,
                'departmentid' => $this->{$department}->id, ]);
        }

        return $this->users[$username];
    }

    /**
     * Generate test structure
     */
    protected function generate_structure() {
        $this->resetAfterTest();
        $this->tenant = $this->get_tenant_generator()->create_tenant();
        $this->tenantother = $this->get_tenant_generator()->create_tenant();

        $generator = $this->get_generator();

        $this->pf = $generator->create_position(['tenantid' => $this->tenant->id]);
        $this->pfother = $generator->create_position(['tenantid' => $this->tenant->id, 'idnumber' => 'pfother']);
        $this->pftenantother = $generator->create_position(['tenantid' => $this->tenantother->id]);

        $this->pa = $generator->create_position(['parentid' => $this->pf->id, 'globalmanager' => 1,
            'globalpermissions' => \tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS |
                \tool_organisation\organisation::PERM_VIEW_REPORTS,
            'idnumber' => 'pa', ]);
        $this->pb = $generator->create_position(['parentid' => $this->pf->id, 'departmentmanager' => 1]);
        $this->pa1 = $generator->create_position(['parentid' => $this->pa->id]);
        $this->pa2 = $generator->create_position(['parentid' => $this->pa->id]);
        $this->pb1 = $generator->create_position(['parentid' => $this->pb->id]);

        $this->df = $generator->create_department(['tenantid' => $this->tenant->id]);
        $this->dfother = $generator->create_department(['tenantid' => $this->tenant->id, 'idnumber' => 'dfother']);
        $this->dftenantother = $generator->create_department(['tenantid' => $this->tenantother->id]);

        $this->da = $generator->create_department(['parentid' => $this->df->id, 'idnumber' => 'da']);
        $this->db = $generator->create_department(['parentid' => $this->df->id]);
        $this->da1 = $generator->create_department(['parentid' => $this->da->id]);
        $this->da2 = $generator->create_department(['parentid' => $this->da->id]);
        $this->db1 = $generator->create_department(['parentid' => $this->db->id,
            'description' => '<img src="@@PLUGINFILE@@/cat.png">', 'descriptionformat' => FORMAT_HTML, ]);
        // Add files to the description.
        get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'tool_organisation',
            'filearea' => \tool_organisation\department_manager::get_description_filearea(),
            'itemid' => $this->db1->id,
            'filepath' => '/',
            'filename' => 'cat.png',
        ], 'cat');
    }

    public function test_import_departments_positions() {
        global $DB;
        $this->generate_structure();
        $user = $this->generate_user('user1');
        $this->make_user_tenant_admin($user->id);
        $this->setUser($user);

        $settings = [orgstructure_exporter::EXPORT_INSTANCES => orgstructure_exporter::EXPORT_INSTANCES_ALL];
        $exportid = $this->get_workplace_generator()->perform_export(
            orgstructure_exporter::class, $settings);

        // Import into another tenant.
        $tenant2 = $this->get_tenant_generator()->create_tenant([]);
        $user2 = $this->getDataGenerator()->create_user();
        $this->get_tenant_generator()->allocate_user($user2->id, $tenant2->id);
        $this->make_user_tenant_admin($user2->id);
        $this->setUser($user2);

        // Now let's create an importer from the exported file.
        $settings = [orgstructure::IMPORT_INSTANCES => orgstructure::IMPORT_INSTANCES_ALL];
        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, $settings);

        // TODO make better assertions of imported entities.
        $newdepts = $DB->get_records('tool_organisation_department', ['tenantid' => $tenant2->id]);
        $this->assertCount(7, $newdepts);
        $newpos = $DB->get_records('tool_organisation_position', ['tenantid' => $tenant2->id]);
        $this->assertCount(7, $newpos);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertEquals(14, count($logs));
        for ($i = 0; $i < 7; $i++) {
            $this->assertStringContainsString('Created new department', $logs[$i]['detail']);
        }
        for ($i = 7; $i < 14; $i++) {
            $this->assertStringContainsString('Created new position', $logs[$i]['detail']);
        }
    }

    public function test_import_jobs() {
        global $DB;
        $this->generate_structure();
        $user1 = $this->generate_user('u4', 'pa1', 'da');
        $user1a = $this->generate_user('u4', 'pb', 'db');
        $user2 = $this->generate_user('u5', 'pb', 'db');
        $this->make_user_tenant_admin($user1->id);
        $this->setUser($user1);

        $settings = [
            jobs_exporter::EXPORT_TYPE => jobs_exporter::EXPORT_TYPE_ALL,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(
            jobs_exporter::class, $settings);

        // Remove all jobs from this tenant and import.
        \tool_organisation\job_manager::delete_all_jobs_for_tenant($this->tenant->id);
        $jobs = $DB->get_records('tool_organisation_job', ['tenantid' => $this->tenant->id]);
        $this->assertCount(0, $jobs);

        $settings = [
            jobs::IMPORT_TYPE => jobs::IMPORT_TYPE_ALL,
            jobs::IMPORT_FRAMEWORKS => 0,
        ];
        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, $settings);

        // TODO make better assertions of imported entities, for example dates.
        $jobs = $DB->get_records('tool_organisation_job', ['tenantid' => $this->tenant->id]);
        $this->assertCount(3, $jobs);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertEquals(3, count($logs));
        $this->assertStringContainsString(fullname($user1), $logs[0]['detail']);
        $this->assertStringContainsString(fullname($user1), $logs[1]['detail']);
        $this->assertStringContainsString(fullname($user2), $logs[2]['detail']);

        $this->assertEmpty($this->get_workplace_generator()->get_import_conflict_review($importid));
    }

    public function test_import_jobs_with_departments_and_positions() {
        global $DB;
        $this->generate_structure();
        $user = $this->generate_user('u4', 'pa1', 'da');
        $user2 = $this->generate_user('u4', 'pb', 'db');
        $this->make_user_tenant_admin($user->id);
        $this->setUser($user);

        $settings = [
            jobs_exporter::EXPORT_TYPE => jobs_exporter::EXPORT_TYPE_ALL,
            jobs_exporter::EXPORT_FRAMEWORKS => 1,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(
            jobs_exporter::class, $settings);

        // Remove all jobs from this tenant and import.
        (new \tool_organisation\department_manager())->delete_all_departments_for_tenant($this->tenant->id);
        (new \tool_organisation\position_manager())->delete_all_positions_for_tenant($this->tenant->id);
        \tool_organisation\job_manager::delete_all_jobs_for_tenant($this->tenant->id);
        $jobs = $DB->get_records('tool_organisation_job', ['tenantid' => $this->tenant->id]);
        $this->assertCount(0, $jobs);

        $settings = [
            jobs::IMPORT_TYPE => jobs::IMPORT_TYPE_ALL,
            jobs::IMPORT_FRAMEWORKS => 1,
        ];
        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, $settings);

        // TODO make better assertions of imported entities, for example dates.
        $jobs = $DB->get_records('tool_organisation_job', ['tenantid' => $this->tenant->id]);
        $this->assertCount(2, $jobs);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        // 1 pos framework, 5 positions, 1 dep framework, 5 departments and 2 jobs.
        // Empty frameworks were not exported and imported.
        $this->assertEquals(14, count($logs));

        $this->assertEmpty($this->get_workplace_generator()->get_import_conflict_review($importid));
    }

    public function test_import_jobs_error() {
        global $DB;
        $this->generate_structure();
        $user1 = $this->generate_user('u4', 'pa1', 'da');
        $user1a = $this->generate_user('u4', 'pb', 'db');
        $user2 = $this->generate_user('u5', 'pb', 'db');
        $this->make_user_tenant_admin($user1->id);
        $this->setUser($user1);

        $settings = [
            jobs_exporter::EXPORT_TYPE => jobs_exporter::EXPORT_TYPE_ALL,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(
            jobs_exporter::class, $settings);

        // Remove all jobs, departments and positions from this tenant and import.
        (new \tool_organisation\department_manager())->delete_all_departments_for_tenant($this->tenant->id);
        (new \tool_organisation\position_manager())->delete_all_positions_for_tenant($this->tenant->id);
        \tool_organisation\job_manager::delete_all_jobs_for_tenant($this->tenant->id);
        $jobs = $DB->get_records('tool_organisation_job', ['tenantid' => $this->tenant->id]);
        $this->assertCount(0, $jobs);

        $importsettings = [
            jobs::IMPORT_TYPE => jobs::IMPORT_TYPE_ALL,
            helper::get_setting_name_for_conflict_form('tool_organisation_department', 'action')
                => 'skip',
            helper::get_setting_name_for_conflict_form('tool_organisation_position', 'action')
                => 'skip',
        ];
        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, $importsettings);

        // No jobs were imported.
        $jobs = $DB->get_records('tool_organisation_job', ['tenantid' => $this->tenant->id]);
        $this->assertCount(0, $jobs);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertEquals(3, count($logs));
        $this->assertStringContainsString('Could not import job', $logs[0]['detail']);
        $this->assertStringContainsString('Could not import job', $logs[1]['detail']);
        $this->assertStringContainsString('Could not import job', $logs[2]['detail']);

        $this->assertCount(2, $logs[0]['errors']);
        $this->assertCount(0, $logs[0]['notices']);

        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($importid);
        $this->assertCount(2, $conflicts);
        $this->assertEquals('Some departments do not exist', $conflicts[0][0]);
        $this->assertEquals('Do not import', $conflicts[0][1]);
        $this->assertEquals('Some positions do not exist', $conflicts[1][0]);
        $this->assertEquals('Do not import', $conflicts[1][1]);
    }

    /**
     * Same test as previous but option to create missing departments
     */
    public function test_import_jobs_create_missing_department() {
        global $DB;
        $this->generate_structure();
        $user1 = $this->generate_user('u4', 'pa1', 'da');
        $user1a = $this->generate_user('u4', 'pb', 'db');
        $user2 = $this->generate_user('u5', 'pb', 'db');
        $this->make_user_tenant_admin($user1->id);
        $this->setUser($user1);

        $settings = [
            jobs_exporter::EXPORT_TYPE => jobs_exporter::EXPORT_TYPE_ALL,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(
            jobs_exporter::class, $settings);

        // Remove all jobs, departments and positions from this tenant and import.
        (new \tool_organisation\department_manager())->delete_all_departments_for_tenant($this->tenant->id);
        \tool_organisation\job_manager::delete_all_jobs_for_tenant($this->tenant->id);
        $jobs = $DB->get_records('tool_organisation_job', ['tenantid' => $this->tenant->id]);
        $this->assertCount(0, $jobs);
        // Create department and position frameworks.
        $df = $this->get_generator()->create_department(['tenantid' => $this->tenant->id]);

        $importsettings = [
            jobs::IMPORT_TYPE => jobs::IMPORT_TYPE_ALL,
            helper::get_setting_name_for_conflict_form('tool_organisation_department', 'action')
            => 'create',
            helper::get_setting_name_for_conflict_form('tool_organisation_department', 'frmid')
            => $df->id,
        ];
        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, $importsettings);

        // Three jobs were created.
        $jobs = $DB->get_records('tool_organisation_job', ['tenantid' => $this->tenant->id]);
        $this->assertCount(3, $jobs);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(2, $logs[0]['notices']);
        $this->assertStringContainsString('was created', $logs[0]['notices'][1]);
        $this->assertCount(2, $logs[1]['notices']);
        $this->assertCount(0, $logs[2]['notices']);

        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($importid);
        $this->assertCount(1, $conflicts);
        $this->assertEquals('Some departments do not exist', $conflicts[0][0]);
        // TODO the name must be resolved here.
        $this->assertEquals('Create in framework ' . $df->id, $conflicts[0][1]);
    }

    /**
     * Same test as previous but option to create missing positions
     */
    public function test_import_jobs_create_missing_positions() {
        global $DB;
        $this->generate_structure();
        $user1 = $this->generate_user('u4', 'pa1', 'da');
        $user1a = $this->generate_user('u4', 'pb', 'db');
        $user2 = $this->generate_user('u5', 'pb', 'db');
        $this->make_user_tenant_admin($user1->id);
        $this->setUser($user1);

        $settings = [
            jobs_exporter::EXPORT_TYPE => jobs_exporter::EXPORT_TYPE_ALL,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(
            jobs_exporter::class, $settings);

        // Remove all jobs, departments and positions from this tenant and import.
        (new \tool_organisation\position_manager())->delete_all_positions_for_tenant($this->tenant->id);
        \tool_organisation\job_manager::delete_all_jobs_for_tenant($this->tenant->id);
        $jobs = $DB->get_records('tool_organisation_job', ['tenantid' => $this->tenant->id]);
        $this->assertCount(0, $jobs);
        // Create department and position frameworks.
        $pf = $this->get_generator()->create_position(['tenantid' => $this->tenant->id]);

        $importsettings = [
            jobs::IMPORT_TYPE => jobs::IMPORT_TYPE_ALL,
            helper::get_setting_name_for_conflict_form('tool_organisation_position', 'action')
            => 'create',
            helper::get_setting_name_for_conflict_form('tool_organisation_position', 'frmid')
            => $pf->id,
        ];
        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, $importsettings);

        // Three jobs were created.
        $jobs = $DB->get_records('tool_organisation_job', ['tenantid' => $this->tenant->id]);
        $this->assertCount(3, $jobs);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(1, $logs[0]['notices']);
        $this->assertStringContainsString('was created', $logs[0]['notices'][0]);
        $this->assertCount(2, $logs[1]['notices']);
        $this->assertCount(0, $logs[2]['notices']);

        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($importid);
        $this->assertCount(1, $conflicts);
        $this->assertEquals('Some positions do not exist', $conflicts[0][0]);
        // TODO the name must be resolved here.
        $this->assertEquals('Create in framework ' . $pf->id, $conflicts[0][1]);
    }

    /**
     * Testing error and conlict resolution for ID number duplication for departments
     */
    public function test_department_import_idnumber_conflict() {
        global $DB;
        $this->resetAfterTest();
        $this->tenant = $this->get_tenant_generator()->create_tenant();
        $this->df = $this->get_generator()->create_department(['tenantid' => $this->tenant->id]);
        $this->da = $this->get_generator()->create_department(['parentid' => $this->df->id, 'idnumber' => 'da']);
        $this->db = $this->get_generator()->create_department(['parentid' => $this->df->id, 'idnumber' => 'db29']);
        $this->da1 = $this->get_generator()->create_department(['parentid' => $this->df->id, 'idnumber' => '']);
        $user = $this->generate_user('user1');
        $this->make_user_tenant_admin($user->id);
        $this->setUser($user);

        $settings = [
            orgstructure_exporter::EXPORT_INSTANCES => orgstructure_exporter::EXPORT_INSTANCES_DEPARTMENTS,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(
            orgstructure_exporter::class, $settings);

        // First try to prepare import without settings. There will idnumber conflicts.
        $settings = [
            orgstructure::IMPORT_INSTANCES => orgstructure::IMPORT_INSTANCES_ALL,
        ];
        $importid = $this->get_workplace_generator()->prepare_import_from_export_id($exportid, $settings);

        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);
        $errors = $importmanager->get_collected_errors();
        $this->assertEquals(1, count($errors));
        $error = reset($errors);
        $this->assertEquals('idnumberconflict', $error['errorcode']);
        $this->assertEquals(2, count($error['details']));
        $this->assertEquals('da', $error['details'][0]['originalidnumber']);
        $this->assertEquals('db29', $error['details'][1]['originalidnumber']);

        // Now set conflict resolution rule (to 'empty' the idnumbers) and perform import.
        $importmanager->save_settings([
            helper::get_importer_setting_name_for_conflict_form(
                'tool_organisation_department', 'idnumberconflict', 'action') => 'empty',
        ]);

        $this->get_workplace_generator()->perform_import($importid);

        // Four more departments were created, new departments have empty idnumbers.
        $depts = $DB->get_records('tool_organisation_department', ['tenantid' => $this->tenant->id], 'id');
        $this->assertCount(8, $depts);

        $depts = array_values($depts);
        $this->assertEquals('', $depts[5]->idnumber);
        $this->assertEquals('', $depts[6]->idnumber);
        $this->assertEquals('', $depts[7]->idnumber);

        // Now set conflict resolution rule to 'increment' and perform import again.
        $newsettings = [
            helper::get_importer_setting_name_for_conflict_form(
                'tool_organisation_department', 'idnumberconflict', 'action') => 'increment',
        ];
        $this->get_workplace_generator()->perform_import_from_export_id($exportid, $settings + $newsettings);

        // Four more departments were created, new departments have idnumbers 'da2' and 'db30' ('db29' + 1).
        $depts = $DB->get_records('tool_organisation_department', ['tenantid' => $this->tenant->id], 'id');
        $this->assertCount(12, $depts);

        $depts = array_values($depts);
        $this->assertEquals('da2', $depts[9]->idnumber);
        $this->assertEquals('db30', $depts[10]->idnumber);
        $this->assertEquals('', $depts[11]->idnumber);

        // One more import will create idnumbers 'da3' and 'db31'.
        $this->get_workplace_generator()->perform_import_from_export_id($exportid, $settings + $newsettings);
        $depts = $DB->get_records('tool_organisation_department', ['tenantid' => $this->tenant->id], 'id');
        $depts = array_values($depts);
        $this->assertEquals('da3', $depts[13]->idnumber);
        $this->assertEquals('db31', $depts[14]->idnumber);

        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($importid);
        $this->assertCount(1, $conflicts);
        $this->assertEquals('Department ID numbers already exist', $conflicts[0][0]);
        $this->assertEquals('Set ID number to empty string', $conflicts[0][1]);
    }

    /**
     * Testing error and conlict resolution for ID number duplication for positions
     */
    public function test_position_import_idnumber_conflict() {
        global $DB;
        $this->resetAfterTest();
        $this->tenant = $this->get_tenant_generator()->create_tenant();
        $this->pf = $this->get_generator()->create_position(['tenantid' => $this->tenant->id]);
        $this->pa = $this->get_generator()->create_position(['parentid' => $this->pf->id, 'idnumber' => 'pa']);
        $this->pb = $this->get_generator()->create_position(['parentid' => $this->pf->id, 'idnumber' => 'pb29']);
        $this->pa1 = $this->get_generator()->create_position(['parentid' => $this->pf->id, 'idnumber' => '']);
        $user = $this->generate_user('user1');
        $this->make_user_tenant_admin($user->id);
        $this->setUser($user);

        $settings = [
            orgstructure_exporter::EXPORT_INSTANCES => orgstructure_exporter::EXPORT_INSTANCES_POSITIONS,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(
            orgstructure_exporter::class, $settings);

        // First try to prepare import without settings. There will be idnumber conflicts.
        $importid = $this->get_workplace_generator()->prepare_import_from_export_id($exportid, $settings);

        $settings = [
            orgstructure::IMPORT_INSTANCES => orgstructure::IMPORT_INSTANCES_ALL,
        ];
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);
        $importmanager->save_settings($settings);
        $errors = $importmanager->get_collected_errors();
        $this->assertEquals(1, count($errors));
        $error = reset($errors);
        $this->assertEquals('idnumberconflict', $error['errorcode']);
        $this->assertEquals(2, count($error['details']));
        $this->assertEquals('pa', $error['details'][0]['originalidnumber']);
        $this->assertEquals('pb29', $error['details'][1]['originalidnumber']);

        // Now set conflict resolution rule (to 'empty' the idnumbers) and perform import.
        $importmanager->save_settings([
            helper::get_importer_setting_name_for_conflict_form(
                'tool_organisation_position', 'idnumberconflict', 'action') => 'empty',
        ]);

        $this->get_workplace_generator()->perform_import($importid);

        // Four more positions were created, new positions have empty idnumbers.
        $depts = $DB->get_records('tool_organisation_position', ['tenantid' => $this->tenant->id], 'id');
        $this->assertCount(8, $depts);

        $depts = array_values($depts);
        $this->assertEquals('', $depts[5]->idnumber);
        $this->assertEquals('', $depts[6]->idnumber);
        $this->assertEquals('', $depts[7]->idnumber);

        // Now set conflict resolution rule to 'increment' and perform import again.
        $newsettings = [
            helper::get_importer_setting_name_for_conflict_form(
                'tool_organisation_position', 'idnumberconflict', 'action') => 'increment',
        ];
        $this->get_workplace_generator()->perform_import_from_export_id($exportid, $settings + $newsettings);

        // Four more positions were created, new positions have idnumbers 'pa2' and 'pb30' ('pb29' + 1).
        $depts = $DB->get_records('tool_organisation_position', ['tenantid' => $this->tenant->id], 'id');
        $this->assertCount(12, $depts);

        $depts = array_values($depts);
        $this->assertEquals('pa2', $depts[9]->idnumber);
        $this->assertEquals('pb30', $depts[10]->idnumber);
        $this->assertEquals('', $depts[11]->idnumber);

        // One more import will create idnumbers 'pa3' and 'pb31'.
        $this->get_workplace_generator()->perform_import_from_export_id($exportid, $settings + $newsettings);
        $depts = $DB->get_records('tool_organisation_position', ['tenantid' => $this->tenant->id], 'id');
        $depts = array_values($depts);
        $this->assertEquals('pa3', $depts[13]->idnumber);
        $this->assertEquals('pb31', $depts[14]->idnumber);

        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($importid);
        $this->assertCount(1, $conflicts);
        $this->assertEquals('Position ID numbers already exist', $conflicts[0][0]);
        $this->assertEquals('Set ID number to empty string', $conflicts[0][1]);
    }

    /**
     * Test specifying the destination tenant that is different from the current tenant
     */
    public function test_import_tenant_destination() {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->get_generator();
        $df = $generator->create_department(['tenantid' => \tool_tenant\tenancy::get_default_tenant_id()]);
        $d = $generator->create_department(['parentid' => $df->id]);
        $pf = $generator->create_position(['tenantid' => \tool_tenant\tenancy::get_default_tenant_id()]);
        $p = $generator->create_position(['parentid' => $pf->id]);
        $user = $this->get_tenant_generator()->create_user([]);
        $job = $generator->assign_job(['userid' => $user->id, 'departmentid' => $d->id, 'positionid' => $p->id]);

        // Set admin user who has access to all tenants but belongs to the default tenant.
        $this->setAdminUser();

        $settings = [
            jobs_exporter::EXPORT_TYPE => jobs_exporter::EXPORT_TYPE_ALL,
            jobs_exporter::EXPORT_FRAMEWORKS => 1,
        ];

        // Export in the current (default) tenant.
        $exportid = $this->get_workplace_generator()->perform_export(
            jobs_exporter::class,
            $settings
        );

        // Import into another tenant.
        $tenant2 = $this->get_tenant_generator()->create_tenant([]);
        $this->get_tenant_generator()->allocate_user($user->id, $tenant2->id);
        $settings = [
            jobs::IMPORT_TYPE => jobs::IMPORT_TYPE_ALL,
            jobs::IMPORT_FRAMEWORKS => 1,
        ];
        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, [
                'tenantid' => $tenant2->id,
            ] + $settings);

        // Make sure the records were imported into the tenant2.
        $newdepts = $DB->get_records('tool_organisation_department', ['tenantid' => $tenant2->id]);
        $this->assertCount(2, $newdepts);
        $newpos = $DB->get_records('tool_organisation_position', ['tenantid' => $tenant2->id]);
        $this->assertCount(2, $newpos);
        $newjobs = $DB->get_records('tool_organisation_job', ['tenantid' => $tenant2->id]);
        $this->assertCount(1, $newjobs);

        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($importid);
        $this->assertEmpty($conflicts);
    }

    /**
     * Test looking up for position/department in the tenant that is different from the current user tenant
     */
    public function test_import_lookup_in_different_tenant() {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->get_generator();
        // Create department with idnumber 'd' and position with idnumber 'p' in the default tenant.
        $df = $generator->create_department(['tenantid' => \tool_tenant\tenancy::get_default_tenant_id()]);
        $d = $generator->create_department(['parentid' => $df->id, 'idnumber' => 'd']);
        $pf = $generator->create_position(['tenantid' => \tool_tenant\tenancy::get_default_tenant_id()]);
        $p = $generator->create_position(['parentid' => $pf->id, 'idnumber' => 'p']);
        // Assign a job to a user there.
        $user = $this->get_tenant_generator()->create_user([]);
        $job = $generator->assign_job(['userid' => $user->id, 'departmentid' => $d->id, 'positionid' => $p->id]);

        // Set admin user who has access to all tenants but belongs to the default tenant.
        $this->setAdminUser();

        $settings = [
            jobs_exporter::EXPORT_TYPE => jobs_exporter::EXPORT_TYPE_ALL,
        ];

        // Export the job in the current (default) tenant.
        $exportid = $this->get_workplace_generator()->perform_export(
            jobs_exporter::class,
            $settings
        );

        // Create another tenant that has the department and position with the same idnumbers and move our user there.
        $tenant2 = $this->get_tenant_generator()->create_tenant([]);
        $this->get_tenant_generator()->allocate_user($user->id, $tenant2->id);

        // Try to import jobs into another tenant - the department and position can not be found.
        $settings = [
            jobs::IMPORT_TYPE => jobs::IMPORT_TYPE_ALL,
        ];
        $importid = $this->get_workplace_generator()->prepare_import_from_export_id($exportid, [
                'tenantid' => $tenant2->id,
            ] + $settings);
        $errors = array_values((new \tool_wp\local\exportimport\import_manager($importid))->get_collected_errors());
        $this->assertCount(2, $errors);
        $this->assertEquals('tool_organisation_department', $errors[0]['entityname']);
        $this->assertEquals('tool_organisation_position', $errors[1]['entityname']);

        // Create the department and position with the same idnumbers in the new tenant.
        $df2 = $generator->create_department(['tenantid' => $tenant2->id]);
        $d2 = $generator->create_department(['parentid' => $df2->id, 'idnumber' => 'd']);
        $pf2 = $generator->create_position(['tenantid' => $tenant2->id]);
        $p2 = $generator->create_position(['parentid' => $pf2->id, 'idnumber' => 'p']);

        // Import the job again.
        $this->get_workplace_generator()->perform_import_from_export_id($exportid, [
                'tenantid' => $tenant2->id,
            ] + $settings);

        // Make sure job was created in the tenant2 and the department and position from there were found.
        $newjobs = $DB->get_records('tool_organisation_job', ['tenantid' => $tenant2->id]);
        $this->assertCount(1, $newjobs);
        $newjob = reset($newjobs);
        $this->assertEquals($d2->id, $newjob->departmentid);
        $this->assertEquals($p2->id, $newjob->positionid);
        $this->assertEquals($user->id, $newjob->userid);

        $conflicts = $this->get_workplace_generator()->get_import_conflict_review($importid);
        $this->assertEmpty($conflicts);
    }

    /**
     * Test import in jobs importer on manual dep/pos frameworks.
     */
    public function test_import_selected_departments_positions_from_jobs() {
        global $DB;
        $this->generate_structure();

        $generator = $this->get_generator();

        // Assign a job to a user there.
        $user1 = $this->get_tenant_generator()->create_user(['tenantid' => $this->tenant->id]);
        $job = $generator->assign_job(['userid' => $user1->id, 'departmentid' => $this->da->id, 'positionid' => $this->pa1->id]);

        $user = $this->generate_user('user1');
        $this->make_user_tenant_admin($user->id);
        self::setUser($user);

        $settings = [
            jobs_exporter::EXPORT_TYPE => jobs_exporter::EXPORT_TYPE_ALL,
            jobs_exporter::EXPORT_FRAMEWORKS => 1,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(
            jobs_exporter::class, $settings);
        $exportrecord = $DB->get_record('tool_wp_export', ['id' => $exportid]);
        $this->assertEquals(1, $exportrecord->status);

        // Remove all jobs from this tenant and import.
        \tool_organisation\job_manager::delete_all_jobs_for_tenant($this->tenant->id);
        $jobs = $DB->get_records('tool_organisation_job', ['tenantid' => $this->tenant->id]);
        $this->assertCount(0, $jobs);
        $DB->delete_records('tool_organisation_department');
        $this->assertCount(0, $DB->get_records('tool_organisation_department', ['tenantid' => $this->tenant->id]));
        $DB->delete_records('tool_organisation_position');
        $this->assertCount(0, $DB->get_records('tool_organisation_position', ['tenantid' => $this->tenant->id]));

        $settings = [
            jobs::IMPORT_TYPE => jobs::IMPORT_TYPE_MANUALLY,
            jobs::IMPORT_FROM_SELECTED_FRAMEWORKS => ['d'.$this->df->id, 'p'.$this->pf->id],
            jobs::IMPORT_FRAMEWORKS => 1,
        ];
        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, $settings);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $jobs = $DB->get_records('tool_organisation_job', ['tenantid' => $this->tenant->id]);
        $this->assertCount(1, $jobs);
        $departments = $DB->get_records('tool_organisation_department', ['tenantid' => $this->tenant->id]);
        $this->assertCount(6, $departments);
        $positions = $DB->get_records('tool_organisation_position', ['tenantid' => $this->tenant->id]);
        $this->assertCount(6, $positions);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertEquals(13, count($logs));

        $this->assertEmpty($this->get_workplace_generator()->get_import_conflict_review($importid));
    }

    /**
     * Testing error logs for orgstructure idnumber
     */
    public function test_orgstructure_error_logs() {
        global $DB;
        $this->resetAfterTest();
        $this->tenant = $this->get_tenant_generator()->create_tenant();
        $this->df = $this->get_generator()->create_department(['tenantid' => $this->tenant->id]);
        $this->da = $this->get_generator()->create_department(['parentid' => $this->df->id, 'idnumber' => 'da']);
        $this->db = $this->get_generator()->create_department(['parentid' => $this->df->id, 'idnumber' => 'db29']);
        $this->da1 = $this->get_generator()->create_department(['parentid' => $this->df->id, 'idnumber' => '']);
        $this->pf = $this->get_generator()->create_position(['tenantid' => $this->tenant->id]);
        $this->pa = $this->get_generator()->create_position(['parentid' => $this->pf->id, 'idnumber' => 'pa']);
        $this->pb = $this->get_generator()->create_position(['parentid' => $this->pf->id, 'idnumber' => 'pb29']);
        $this->pa1 = $this->get_generator()->create_position(['parentid' => $this->pf->id, 'idnumber' => '']);
        $user = $this->generate_user('user1');
        $this->make_user_tenant_admin($user->id);
        self::setUser($user);

        $settings = [
            orgstructure_exporter::EXPORT_INSTANCES => orgstructure_exporter::EXPORT_INSTANCES_ALL,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(
            orgstructure_exporter::class, $settings);
        $exportrecord = $DB->get_record('tool_wp_export', ['id' => $exportid]);
        $this->assertEquals(1, $exportrecord->status);

        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, $settings);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $settings = [
            orgstructure::IMPORT_INSTANCES => orgstructure::IMPORT_INSTANCES_ALL,
            helper::get_importer_setting_name_for_conflict_form(
                'tool_organisation_department', 'idnumberconflict', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form(
                'tool_organisation_position', 'idnumberconflict', 'action') => 'skip',
        ];
        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, $settings);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $errors = array_filter($logs, function($log) {
            return !empty($log['errors']);
        });
        $this->assertCount(4, $errors);

        $errormsgserrors = array_map(function($log) {
            return $log['errors'][0];
        }, $errors);
        $str1 = get_string('erroridnumberdepartment', 'tool_organisation', $this->da->idnumber);
        $str2 = get_string('erroridnumberdepartment', 'tool_organisation', $this->db->idnumber);
        $str3 = get_string('erroridnumberposition', 'tool_organisation', $this->pa->idnumber);
        $str4 = get_string('erroridnumberposition', 'tool_organisation', $this->pb->idnumber);
        $this->assertEqualsCanonicalizing([$str1, $str2, $str3, $str4], $errormsgserrors);

        $errormsgsdetail = array_map(function($log) {
            return $log['detail'];
        }, $errors);
        $str1 = get_string('importlogdeptfailed', 'tool_organisation', $this->da);
        $str2 = get_string('importlogdeptfailed', 'tool_organisation', $this->db);
        $str3 = get_string('importlogposfailed', 'tool_organisation', $this->pa);
        $str4 = get_string('importlogposfailed', 'tool_organisation', $this->pb);
        $this->assertEqualsCanonicalizing([$str1, $str2, $str3, $str4], $errormsgsdetail);
    }

    /**
     * Testing error logs for orgstructure frameworks idnumber
     */
    public function test_orgstructure_error_frameworks_logs() {
        global $DB;
        $this->resetAfterTest();
        $this->tenant = $this->get_tenant_generator()->create_tenant();
        $this->df = $this->get_generator()->create_department(['tenantid' => $this->tenant->id, 'idnumber' => 'df']);
        $this->da = $this->get_generator()->create_department(['parentid' => $this->df->id, 'idnumber' => 'da']);
        $this->db = $this->get_generator()->create_department(['parentid' => $this->df->id, 'idnumber' => 'db29']);
        $this->da1 = $this->get_generator()->create_department(['parentid' => $this->df->id, 'idnumber' => '']);
        $this->pf = $this->get_generator()->create_position(['tenantid' => $this->tenant->id, 'idnumber' => 'pf']);
        $this->pa = $this->get_generator()->create_position(['parentid' => $this->pf->id, 'idnumber' => 'pa']);
        $this->pb = $this->get_generator()->create_position(['parentid' => $this->pf->id, 'idnumber' => 'pb29']);
        $this->pa1 = $this->get_generator()->create_position(['parentid' => $this->pf->id, 'idnumber' => '']);
        $user = $this->generate_user('user1');
        $this->make_user_tenant_admin($user->id);
        self::setUser($user);

        $settings = [
            orgstructure_exporter::EXPORT_INSTANCES => orgstructure_exporter::EXPORT_INSTANCES_ALL,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(
            orgstructure_exporter::class, $settings);
        $exportrecord = $DB->get_record('tool_wp_export', ['id' => $exportid]);
        $this->assertEquals(1, $exportrecord->status);

        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, $settings);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $settings = [
            orgstructure::IMPORT_INSTANCES => orgstructure::IMPORT_INSTANCES_ALL,
            helper::get_importer_setting_name_for_conflict_form(
                'tool_organisation_department', 'idnumberconflict', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form(
                'tool_organisation_position', 'idnumberconflict', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form(
                'tool_organisation_department_framework', 'idnumberconflict', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form(
                'tool_organisation_position_framework', 'idnumberconflict', 'action') => 'skip',
        ];
        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, $settings);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $errors = array_filter($logs, function($log) {
            return !empty($log['errors']);
        });
        $this->assertCount(2, $errors);

        $errormsgserror = array_map(function($log) {
            return $log['errors'][0];
        }, $errors);
        $str1 = get_string('erroridnumberdepartment', 'tool_organisation', $this->df->idnumber);
        $str2 = get_string('erroridnumberposition', 'tool_organisation', $this->pf->idnumber);
        $this->assertEqualsCanonicalizing([$str1, $str2], $errormsgserror);

        $errormsgsdetail = array_map(function($log) {
            return $log['detail'];
        }, $errors);
        $str1 = get_string('importlogdeptfrmfailed', 'tool_organisation', $this->df);
        $str2 = get_string('importlogposfrmfailed', 'tool_organisation', $this->pf);
        $this->assertEqualsCanonicalizing([$str1, $str2], $errormsgsdetail);
    }

    /**
     * Testing error logs for orgstructure for departments and positions with missing parents
     */
    public function test_orgstructure_with_missing_parents() {
        global $DB;
        $this->resetAfterTest();
        $this->tenant = $this->get_tenant_generator()->create_tenant();

        $user = $this->generate_user('user1');
        $this->make_user_tenant_admin($user->id);
        self::setUser($user);

        $importid = $this->get_workplace_generator()->perform_import_from_file(__DIR__ . '/../../fixtures/orgstructurebroken.zip', [
            orgstructure::IMPORT_INSTANCES => orgstructure::IMPORT_INSTANCES_ALL,
            helper::get_importer_setting_name_for_conflict_form(
                'tool_organisation_department', 'unknownparent', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form(
                'tool_organisation_position', 'unknownparent', 'action') => 'skip',
        ]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);

        $this->assertCount(4, $logs);
        $errors = array_filter($logs, function($log) {
            return !empty($log['errors']);
        });
        $this->assertCount(2, $errors);

        $errormsgserror = array_map(function($log) {
            return $log['errors'][0];
        }, $errors);
        // Both idnumbers are IDNUMBER_A1 in the export file.
        $str1 = get_string('errorparentnotfounddepartment', 'tool_organisation', 'IDNUMBER_A1');
        $str2 = get_string('errorparentnotfoundposition', 'tool_organisation', 'IDNUMBER_A1');
        $this->assertEqualsCanonicalizing([$str1, $str2], $errormsgserror);

        $errormsgsdetail = array_map(function($log) {
            return $log['detail'];
        }, $errors);
        // These are the names in the export file.
        $str1 = get_string('importlogdeptfailed', 'tool_organisation', ['name' => 'Dep A1']);
        $str2 = get_string('importlogposfailed', 'tool_organisation', ['name' => 'Position A1']);
        $this->assertEqualsCanonicalizing([$str1, $str2], $errormsgsdetail);
    }

    /**
     * Data provider for {{@see test_import_departments_csv_new_framework_with_parent_by_idnumber}},
     * and {{@see test_import_departments_csv_existing_framework_with_parent_by_idnumber}}
     *
     * @return array
     */
    public static function settings_data_provider_departments(): array {
        return [
            'frameworkbyid' => [departments_csv::IMPORT_SELECT_FRAMEWORK, 'id'],
            'frameworkbyidnumber' => [departments_csv::IMPORT_SELECT_FRAMEWORKIDNUMBER, 'idnumber'],
        ];
    }

    /**
     * Testing import departments CSV to an existing framework with parents by idnumber.
     * Creating departments into an existing department using existing idnumber as parentid.
     *
     * @param string $settingkey
     * @param string $objectkey
     *
     * @dataProvider settings_data_provider_departments
     */
    public function test_import_departments_csv_existing_framework_with_parent_by_idnumber(string $settingkey,
            string $objectkey): void {
        global $DB;
        $this->generate_structure();

        // Make a department with idnumber in a department framework with idnumber.
        $this->get_generator()->create_department(['name' => 'Japan', 'parentid' => $this->dfother->id, 'idnumber' => 'japan']);

        $user = $this->generate_user('user1');
        $this->make_user_tenant_admin($user->id);
        self::setUser($user);

        $departments = \tool_organisation\department::get_records();
        $this->assertCount(9, $departments);
        $departmentframeworks = array_filter($departments, function($dep) {
            return ((int)$dep->get('pathlevel') === 1);
        });

        // Check there are 3 department frameworks.
        $this->assertCount(3, $departmentframeworks);

        // Check there are 2 departments with idnumber and pathlevel 2.
        $depswithidnumberpathlevel2 = array_filter($departments, function($dep) {
            return (!empty($dep->get('idnumber')) && (int)$dep->get('pathlevel') === 2);
        });
        $this->assertCount(2, $depswithidnumberpathlevel2);

        $tmpfile = make_request_directory().'/dep.csv';
        file_put_contents($tmpfile, <<<EOF
id,name,parentid,idnumber,description,descriptionformat
546003,"Tokyo",japan,tokyo,"Description",0
546004,"Shibuya",tokyo,shibu,,0
546007,"New department 8",unknown1,,"Description",1
546005,"New department 6",unknown2,,"Description",bbb
546006,"New department 7",,,,0
EOF
        );

        // Now let's create an importer from the exported file.
        $importid = $this->get_workplace_generator()->prepare_import_from_file($tmpfile);
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);

        // First of all make sure orgstructure is among importers.
        $importers = $importmanager->get_importers();
        $this->assertCount(2, $importers);
        /** @var \tool_organisation\tool_wp\importer\orgstructure $importer */
        $importer = $importers[0];
        $this->assertInstanceOf(departments_csv::class, $importer);

        $settings = [
            'importer' => 'tool_organisation\tool_wp\importer\departments_csv',
            departments_csv::IMPORT_TARGET_FRAMEWORK => departments_csv::IMPORT_TARGET_FRAMEWORK_SELECTED,
            $settingkey => $this->dfother->$objectkey,
            departments_csv::IMPORT_HIERARCHY => departments_csv::IMPORT_HIERARCHY_SELECTED,
            departments_csv::IMPORT_HIERARCHY_IDENTIFIER => $objectkey,
            'csvmapping:name' => 'name',
            'csvmapping:idnumber' => 'idnumber',
            'csvmapping:description' => 'description',
            'csvmapping:descriptionformat' => 'descriptionformat',
            'csvmapping:parentid' => 'parentid',
            helper::get_importer_setting_name_for_conflict_form(
                departments_csv::CSV_DATA, 'idnumberconflict', 'action') => 'empty',
        ];

        $importid = $this->get_workplace_generator()->perform_import_from_file($tmpfile, $settings);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(5, $logs);

        $this->assertEmpty($logs[0]['notices']);
        $this->assertEmpty($logs[1]['notices']);

        $logrecord = get_string('parentidchanged', 'tool_organisation', 'unknown1');
        $this->assertEquals($logrecord, $logs[2]['notices'][0]);

        $logrecord = get_string('parentidchanged', 'tool_organisation', 'unknown2');
        $this->assertEquals($logrecord, $logs[3]['notices'][0]);

        $this->assertEmpty($logs[4]['notices']);

        $departments = \tool_organisation\department::get_records();
        $this->assertCount(14, $departments);
        $departmentframeworks = array_filter($departments, function($dep) {
            return ((int)$dep->get('pathlevel') === 1);
        });
        // Check there still are 3 department frameworks.
        $this->assertCount(3, $departmentframeworks);

        $depswithidnumber = array_filter($departments, function($dep) {
            return (!empty($dep->get('idnumber')));
        });
        $this->assertCount(5, $depswithidnumber);

        // Check there is 1 department with idnumber tokyo and pathlevel 3.
        $deptokyo = array_filter($depswithidnumber, function($dep) {
            return ($dep->get('idnumber') === 'tokyo' && (int) $dep->get('pathlevel') === 3);
        });
        $this->assertCount(1, $deptokyo);
        // Check parentid (int) exists and is correct.
        $deptokyoparent = array_filter($depswithidnumber, function($dep) use ($deptokyo) {
            return ($dep->get('id') === reset($deptokyo)->get('parentid'));
        });
        $this->assertEquals('japan', reset($deptokyoparent)->get('idnumber'));

        // Check there is 1 department with idnumber shibu and pathlevel 4.
        $depshibuya = array_filter($depswithidnumber, function($dep) {
            return ($dep->get('idnumber') === 'shibu' && (int) $dep->get('pathlevel') === 4);
        });
        $this->assertCount(1, $depshibuya);
        // Check parentid (int) exists and is correct.
        $depshibuyaparent = array_filter($depswithidnumber, function($dep) use ($depshibuya) {
            return ($dep->get('id') === reset($depshibuya)->get('parentid'));
        });
        $this->assertEquals('tokyo', reset($depshibuyaparent)->get('idnumber'));
    }

    /**
     * Testing import departments CSV to a new framework with parents by idnumber.
     * Creating departments into an existing department using existing idnumber as parentid.
     *
     * @param string $settingkey
     * @param string $objectkey
     *
     * @dataProvider settings_data_provider_departments
     */
    public function test_import_departments_csv_new_framework_with_parent_by_idnumber(string $settingkey,
            string $objectkey): void {
        global $DB;
        $this->generate_structure();

        $user = $this->generate_user('user1');
        $this->make_user_tenant_admin($user->id);
        self::setUser($user);

        $departments = \tool_organisation\department::get_records();
        $this->assertCount(8, $departments);
        $departmentframeworks = array_filter($departments, function($dep) {
            return ((int)$dep->get('pathlevel') === 1);
        });

        // Check there are 3 department frameworks.
        $this->assertCount(3, $departmentframeworks);

        $tmpfile = make_request_directory().'/dep.csv';
        file_put_contents($tmpfile, <<<EOF
id,name,parentid,idnumber,description,descriptionformat
546003,"Tokyo",japan,tokyo,"Description",0
546004,"Shibuya",tokyo,shibu,,0
546007,"New department 8",unknown1,,"Description",1
546005,"New department 6",unknown2,,"Description",bbb
546006,"New department 7",,,,0
EOF
        );

        // Now let's create an importer from the exported file.
        $importid = $this->get_workplace_generator()->prepare_import_from_file($tmpfile);
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);

        // First of all make sure orgstructure is among importers.
        $importers = $importmanager->get_importers();
        $this->assertCount(2, $importers);
        /** @var \tool_organisation\tool_wp\importer\orgstructure $importer */
        $importer = $importers[0];
        $this->assertInstanceOf(departments_csv::class, $importer);

        $settings = [
            'importer' => 'tool_organisation\tool_wp\importer\departments_csv',
            departments_csv::IMPORT_TARGET_FRAMEWORK => departments_csv::IMPORT_TARGET_FRAMEWORK_NEW,
            $settingkey => $this->dfother->$objectkey,
            departments_csv::IMPORT_HIERARCHY => departments_csv::IMPORT_HIERARCHY_SELECTED,
            departments_csv::IMPORT_HIERARCHY_IDENTIFIER => $objectkey,
            'csvmapping:name' => 'name',
            'csvmapping:idnumber' => 'idnumber',
            'csvmapping:description' => 'description',
            'csvmapping:descriptionformat' => 'descriptionformat',
            'csvmapping:parentid' => 'parentid',
            helper::get_importer_setting_name_for_conflict_form(
                departments_csv::CSV_DATA, 'idnumberconflict', 'action') => 'empty',
        ];

        $importid = $this->get_workplace_generator()->perform_import_from_file($tmpfile, $settings);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(6, $logs);

        $this->assertEmpty($logs[0]['notices']);

        $logrecord = get_string('parentidchanged', 'tool_organisation', 'japan');
        $this->assertEquals($logrecord, $logs[1]['notices'][0]);

        $this->assertEmpty($logs[2]['notices']);

        $logrecord = get_string('parentidchanged', 'tool_organisation', 'unknown1');
        $this->assertEquals($logrecord, $logs[3]['notices'][0]);

        $logrecord = get_string('parentidchanged', 'tool_organisation', 'unknown2');
        $this->assertEquals($logrecord, $logs[4]['notices'][0]);

        $this->assertEmpty($logs[5]['notices']);

        $departments = \tool_organisation\department::get_records();
        $this->assertCount(14, $departments);
        $departmentframeworks = array_filter($departments, function($dep) {
            return ((int)$dep->get('pathlevel') === 1);
        });
        // Check there are 4 department frameworks.
        $this->assertCount(4, $departmentframeworks);

        $depswithidnumber = array_filter($departments, function($dep) {
            return (!empty($dep->get('idnumber')));
        });
        $this->assertCount(4, $depswithidnumber);

        // Check there is 1 department with idnumber tokyo and pathlevel 2.
        $deptokyo = array_filter($depswithidnumber, function($dep) {
            return ($dep->get('idnumber') === 'tokyo' && (int) $dep->get('pathlevel') === 2);
        });
        $this->assertCount(1, $deptokyo);
        // Check parentid (int) exists and is correct.
        $deptokyoparent = array_filter($departments, function($dep) use ($deptokyo) {
            return ($dep->get('id') === reset($deptokyo)->get('parentid'));
        });
        $this->assertCount(1, $deptokyoparent);
        // Parent is a framework.
        $this->assertNull(reset($deptokyoparent)->get('idnumber'));
        $this->assertNull(reset($deptokyoparent)->get('parentid'));
        $this->assertEquals(1, reset($deptokyoparent)->get('pathlevel'));
        $this->assertEquals('dep', reset($deptokyoparent)->get('name'));

        // Check there is 1 department with idnumber shibu and pathlevel 3.
        $depshibuya = array_filter($depswithidnumber, function($dep) {
            return ($dep->get('idnumber') === 'shibu' && (int) $dep->get('pathlevel') === 3);
        });
        $this->assertCount(1, $depshibuya);
        // Check parentid (int) exists and is correct.
        $depshibuyaparent = array_filter($depswithidnumber, function($dep) use ($depshibuya) {
            return ($dep->get('id') === reset($depshibuya)->get('parentid'));
        });
        $this->assertEquals('tokyo', reset($depshibuyaparent)->get('idnumber'));
    }

    /**
     * Data provider for {{@see test_import_positions_csv_existing_framework_with_parent_by_idnumber}}
     * and {{@see test_import_positions_csv_new_framework_with_parent_by_idnumber}}
     *
     * @return array
     */
    public static function settings_data_provider_positions(): array {
        return [
            'frameworkbyid' => [positions_csv::IMPORT_SELECT_FRAMEWORK, 'id'],
            'frameworkbyidnumber' => [positions_csv::IMPORT_SELECT_FRAMEWORKIDNUMBER, 'idnumber'],
        ];
    }

    /**
     * Testing import positions CSV to an existing framework with parents by idnumber.
     * Creating positions into an existing position using existing idnumber as parentid.
     *
     * @param string $settingkey
     * @param string $objectkey
     *
     * @dataProvider settings_data_provider_positions
     */
    public function test_import_positions_csv_existing_framework_with_parent_by_idnumber(string $settingkey,
                                                                                           string $objectkey): void {
        global $DB;
        $this->generate_structure();

        // Make a position with idnumber in a position framework with idnumber.
        $this->get_generator()->create_position(['name' => 'Manager', 'parentid' => $this->pfother->id, 'idnumber' => 'manager']);

        $user = $this->generate_user('user1');
        $this->make_user_tenant_admin($user->id);
        self::setUser($user);

        $positions = \tool_organisation\position::get_records();
        $this->assertCount(9, $positions);

        // Check there are 3 position frameworks.
        $this->assertEquals(3, $DB->count_records('tool_organisation_position', ['pathlevel' => 1]));

        // Check there are 2 positions with idnumber and pathlevel 2.
        $poswithidnumberpathlevel2 = array_filter($positions, static function($pos) {
            return (!empty($pos->get('idnumber')) && (int)$pos->get('pathlevel') === 2);
        });
        $this->assertCount(2, $poswithidnumberpathlevel2);

        $tmpfile = make_request_directory().'/pos.csv';
        file_put_contents($tmpfile, <<<EOF
id,name,parentid,idnumber,description,descriptionformat
546003,"Lead",manager,lead,"Description",0
546004,"Senior developer",lead,sendev,,0
546007,"New position 8",unknown1,,"Description",1
546005,"New position 6",unknown2,,"Description",bbb
546006,"New position 7",,,,0
EOF
        );

        // Now let's create an importer from the exported file.
        $importid = $this->get_workplace_generator()->prepare_import_from_file($tmpfile);
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);

        // First of all make sure orgstructure is among importers.
        $importers = $importmanager->get_importers();
        $this->assertCount(2, $importers);
        /** @var \tool_organisation\tool_wp\importer\orgstructure $importer */
        $importer = $importers[1];
        $this->assertInstanceOf(positions_csv::class, $importer);

        $settings = [
            'importer' => 'tool_organisation\tool_wp\importer\positions_csv',
            positions_csv::IMPORT_TARGET_FRAMEWORK => positions_csv::IMPORT_TARGET_FRAMEWORK_SELECTED,
            $settingkey => $this->pfother->$objectkey,
            positions_csv::IMPORT_HIERARCHY => positions_csv::IMPORT_HIERARCHY_SELECTED,
            positions_csv::IMPORT_HIERARCHY_IDENTIFIER => $objectkey,
            'csvmapping:name' => 'name',
            'csvmapping:idnumber' => 'idnumber',
            'csvmapping:description' => 'description',
            'csvmapping:descriptionformat' => 'descriptionformat',
            'csvmapping:parentid' => 'parentid',
            helper::get_importer_setting_name_for_conflict_form(
                positions_csv::CSV_DATA, 'idnumberconflict', 'action') => 'empty',
        ];

        $importid = $this->get_workplace_generator()->perform_import_from_file($tmpfile, $settings);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(5, $logs);

        $this->assertEmpty($logs[0]['notices']);
        $this->assertEmpty($logs[1]['notices']);

        $logrecord = get_string('parentidchangedposition', 'tool_organisation', 'unknown1');
        $this->assertEquals($logrecord, $logs[2]['notices'][0]);

        $logrecord = get_string('parentidchangedposition', 'tool_organisation', 'unknown2');
        $this->assertEquals($logrecord, $logs[3]['notices'][0]);

        $this->assertEmpty($logs[4]['notices']);

        $positions = \tool_organisation\position::get_records();

        // Check there still are 3 position frameworks.
        $this->assertEquals(3, $DB->count_records('tool_organisation_position', ['pathlevel' => 1]));

        $poswithidnumber = array_filter($positions, static function($pos) {
            return (!empty($pos->get('idnumber')));
        });
        $this->assertCount(5, $poswithidnumber);

        // Check there is 1 position with idnumber manager and pathlevel 2.
        $posmanager = $DB->get_record('tool_organisation_position', ['pathlevel' => 2, 'idnumber' => 'manager']);

        // Check there is 1 position with idnumber lead and pathlevel 3.
        $poslead = $DB->get_record('tool_organisation_position', ['pathlevel' => 3, 'idnumber' => 'lead',
            'parentid' => $posmanager->id, ]);

        // Check there is 1 position with idnumber sendev and pathlevel 4.
        $possendev = $DB->count_records('tool_organisation_position', ['pathlevel' => 4, 'idnumber' => 'sendev',
            'parentid' => $poslead->id, ]);
        $this->assertEquals(1, $possendev);
    }

    /**
     * Testing import positions CSV to a new framework with parents by idnumber.
     * Creating positions into an existing position using existing idnumber as parentid.
     *
     * @param string $settingkey
     * @param string $objectkey
     *
     * @dataProvider settings_data_provider_positions
     */
    public function test_import_positions_csv_new_framework_with_parent_by_idnumber(string $settingkey,
                                                                                      string $objectkey): void {
        global $DB;
        $this->generate_structure();

        $user = $this->generate_user('user1');
        $this->make_user_tenant_admin($user->id);
        self::setUser($user);

        $positions = \tool_organisation\position::get_records();
        $this->assertCount(8, $positions);
        $positionframeworks = array_filter($positions, static function($pos) {
            return ((int)$pos->get('pathlevel') === 1);
        });

        // Check there are 3 position frameworks.
        $this->assertCount(3, $positionframeworks);

        $tmpfile = make_request_directory().'/pos.csv';
        file_put_contents($tmpfile, <<<EOF
id,name,parentid,idnumber,description,descriptionformat
546003,"Lead",manager,lead,"Description",0
546004,"Senior developer",lead,sendev,,0
546007,"New position 8",unknown1,,"Description",1
546005,"New position 6",unknown2,,"Description",bbb
546006,"New position 7",,,,0
EOF
        );

        // Now let's create an importer from the exported file.
        $importid = $this->get_workplace_generator()->prepare_import_from_file($tmpfile);
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);

        // First of all make sure orgstructure is among importers.
        $importers = $importmanager->get_importers();
        $this->assertCount(2, $importers);
        /** @var \tool_organisation\tool_wp\importer\orgstructure $importer */
        $importer = $importers[1];
        $this->assertInstanceOf(positions_csv::class, $importer);

        $settings = [
            'importer' => 'tool_organisation\tool_wp\importer\positions_csv',
            positions_csv::IMPORT_TARGET_FRAMEWORK => positions_csv::IMPORT_TARGET_FRAMEWORK_NEW,
            $settingkey => $this->pfother->$objectkey,
            positions_csv::IMPORT_HIERARCHY => positions_csv::IMPORT_HIERARCHY_SELECTED,
            positions_csv::IMPORT_HIERARCHY_IDENTIFIER => $objectkey,
            'csvmapping:name' => 'name',
            'csvmapping:idnumber' => 'idnumber',
            'csvmapping:description' => 'description',
            'csvmapping:descriptionformat' => 'descriptionformat',
            'csvmapping:parentid' => 'parentid',
            helper::get_importer_setting_name_for_conflict_form(
                positions_csv::CSV_DATA, 'idnumberconflict', 'action') => 'empty',
        ];

        $importid = $this->get_workplace_generator()->perform_import_from_file($tmpfile, $settings);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(6, $logs);

        $this->assertEmpty($logs[0]['notices']);

        $logrecord = get_string('parentidchangedposition', 'tool_organisation', 'manager');
        $this->assertEquals($logrecord, $logs[1]['notices'][0]);

        $this->assertEmpty($logs[2]['notices']);

        $logrecord = get_string('parentidchangedposition', 'tool_organisation', 'unknown1');
        $this->assertEquals($logrecord, $logs[3]['notices'][0]);

        $logrecord = get_string('parentidchangedposition', 'tool_organisation', 'unknown2');
        $this->assertEquals($logrecord, $logs[4]['notices'][0]);

        $this->assertEmpty($logs[5]['notices']);

        $positions = \tool_organisation\position::get_records();
        $this->assertCount(14, $positions);

        // Check there are 4 position frameworks.
        $positionframeworks = $DB->count_records('tool_organisation_position', ['pathlevel' => 1]);
        $this->assertEquals(4, $positionframeworks);

        $poswithidnumber = array_filter($positions, static function($pos) {
            return (!empty($pos->get('idnumber')));
        });
        $this->assertCount(4, $poswithidnumber);

        // Check there is 1 position with idnumber lead and pathlevel 2.
        $poslead = $DB->get_record('tool_organisation_position', ['pathlevel' => 2, 'idnumber' => 'lead']);

        // Check there is 1 position with idnumber sendev and pathlevel 3.
        $possendev = $DB->count_records('tool_organisation_position', ['pathlevel' => 3, 'idnumber' => 'sendev',
            'parentid' => $poslead->id, ]);
        $this->assertEquals(1, $possendev);

        // Check Parent is a framework.
        $posleadparent = $DB->get_record('tool_organisation_position', ['id' => $poslead->parentid]);
        $this->assertNull($posleadparent->idnumber);
        $this->assertNull($posleadparent->parentid);
        $this->assertEquals(1, $posleadparent->pathlevel);
        $this->assertEquals('pos', $posleadparent->name);
    }

    /**
     * Test importing a CSV file with manager and department lead permissions
     */
    public function test_import_positions_csv_with_permissions(): void {
        global $DB;
        $this->generate_structure();

        $user = $this->generate_user('user1');
        $this->make_user_tenant_admin($user->id);
        self::setUser($user);

        $tmpfile = make_request_directory().'/pos.csv';
        file_put_contents($tmpfile, <<<EOF
path,idnumber,parentidnumber,name,ismanager,managerpermissions,isdepartmentlead,departmentleadpermissions,
CEO,CEO,,Chief Executive Officer,1,all,1,"reports,allocate",
CEO/COO,COO,CEO,Chief Operating Officer,1,6,1,all,
CEO/COO/HMD,HMD,COO,Head of Marketing and Design,1,allocate,1,,
CEO/PO,PO,CEO,Product owner,1,all,1,notifications,
CEO/PO/SDEV,SDEV,PO,Senior developer,0,,1,all,
CEO/PO/SDED/DEV,DEV,SDEV,Developer,0,all,0,,
CEO/PO/SDED/DEV/JDEV,JDEV,DEV,Junior developer,0,,1,,
CEO/PO/TEST,TEST,PO,Tester,1,-5,1,99,
EOF
        );

        // Now let's create an importer from the CSV file.
        $importid = $this->get_workplace_generator()->prepare_import_from_file($tmpfile);
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);

        // First of all make sure orgstructure is among importers.
        $importers = $importmanager->get_importers();
        $this->assertCount(2, $importers);
        /** @var \tool_organisation\tool_wp\importer\orgstructure $importer */
        $importer = $importers[1];
        $this->assertInstanceOf(positions_csv::class, $importer);

        $settings = [
            'importer' => 'tool_organisation\tool_wp\importer\positions_csv',
            positions_csv::IMPORT_TARGET_FRAMEWORK => positions_csv::IMPORT_TARGET_FRAMEWORK_NEW,
            positions_csv::IMPORT_HIERARCHY => positions_csv::IMPORT_HIERARCHY_SELECTED,
            positions_csv::IMPORT_HIERARCHY_IDENTIFIER => 'idnumber',
            'csvmapping:name' => 'name',
            'csvmapping:idnumber' => 'idnumber',
            'csvmapping:description' => 'description',
            'csvmapping:descriptionformat' => 'descriptionformat',
            'csvmapping:parentid' => 'parentidnumber',
            'csvmapping:ismanager' => 'ismanager',
            'csvmapping:managerpermissions' => 'managerpermissions',
            'csvmapping:isdepartmentlead' => 'isdepartmentlead',
            'csvmapping:departmentleadpermissions' => 'departmentleadpermissions',
            helper::get_importer_setting_name_for_conflict_form(
                positions_csv::CSV_DATA, 'idnumberconflict', 'action') => 'empty',
        ];

        $importid = $this->get_workplace_generator()->perform_import_from_file($tmpfile, $settings);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(9, $logs);
        $this->assertEmpty($logs[0]['notices']);

        $fields = 'globalmanager,globalpermissions,departmentmanager,departmentpermissions';

        $posceo = $DB->get_record('tool_organisation_position', ['pathlevel' => 2, 'idnumber' => 'CEO'], $fields);
        $this->assertEqualsCanonicalizing((object)[
            'globalmanager' => 1,
            'globalpermissions' => organisation::PERM_VIEW_REPORTS | organisation::PERM_ALLOCATE_PROGRAMS |
            organisation::PERM_RECEIVE_NOTIFICATIONS,
            'departmentmanager' => 1,
            'departmentpermissions' => organisation::PERM_VIEW_REPORTS | organisation::PERM_ALLOCATE_PROGRAMS,
        ], $posceo);

        $poscoo = $DB->get_record('tool_organisation_position', ['pathlevel' => 3, 'idnumber' => 'COO'], $fields);
        $this->assertEqualsCanonicalizing((object)[
            'globalmanager' => 1,
            'globalpermissions' => organisation::PERM_VIEW_REPORTS | organisation::PERM_RECEIVE_NOTIFICATIONS,
            'departmentmanager' => 1,
            'departmentpermissions' => organisation::PERM_VIEW_REPORTS | organisation::PERM_ALLOCATE_PROGRAMS |
            organisation::PERM_RECEIVE_NOTIFICATIONS,
        ], $poscoo);

        $poshmd = $DB->get_record('tool_organisation_position', ['pathlevel' => 4, 'idnumber' => 'HMD'], $fields);
        $this->assertEqualsCanonicalizing((object)[
            'globalmanager' => 1,
            'globalpermissions' => organisation::PERM_ALLOCATE_PROGRAMS,
            'departmentmanager' => 1,
            'departmentpermissions' => 0,
        ], $poshmd);

        $pospo = $DB->get_record('tool_organisation_position', ['pathlevel' => 3, 'idnumber' => 'PO'], $fields);
        $this->assertEqualsCanonicalizing((object)[
            'globalmanager' => 1,
            'globalpermissions' => organisation::PERM_VIEW_REPORTS | organisation::PERM_ALLOCATE_PROGRAMS |
            organisation::PERM_RECEIVE_NOTIFICATIONS,
            'departmentmanager' => 1,
            'departmentpermissions' => organisation::PERM_RECEIVE_NOTIFICATIONS,
        ], $pospo);

        $possendev = $DB->get_record('tool_organisation_position', ['pathlevel' => 4, 'idnumber' => 'SDEV'], $fields);
        $this->assertEqualsCanonicalizing((object)[
            'globalmanager' => 0,
            'globalpermissions' => 0,
            'departmentmanager' => 1,
            'departmentpermissions' => organisation::PERM_VIEW_REPORTS | organisation::PERM_ALLOCATE_PROGRAMS |
            organisation::PERM_RECEIVE_NOTIFICATIONS,
        ], $possendev);

        $posdev = $DB->get_record('tool_organisation_position', ['pathlevel' => 5, 'idnumber' => 'DEV'], $fields);
        $this->assertEqualsCanonicalizing((object)[
            'globalmanager' => 0,
            'globalpermissions' => 0,
            'departmentmanager' => 0,
            'departmentpermissions' => 0,
        ], $posdev);

        $posdev = $DB->get_record('tool_organisation_position', ['pathlevel' => 6, 'idnumber' => 'JDEV'], $fields);
        $this->assertEqualsCanonicalizing((object)[
            'globalmanager' => 0,
            'globalpermissions' => 0,
            'departmentmanager' => 1,
            'departmentpermissions' => 0,
        ], $posdev);

        $postester = $DB->get_record('tool_organisation_position', ['pathlevel' => 4, 'idnumber' => 'TEST'], $fields);
        $this->assertEqualsCanonicalizing((object)[
            'globalmanager' => 1,
            'globalpermissions' => 0,
            'departmentmanager' => 1,
            'departmentpermissions' => 0,
        ], $postester);
    }

    /**
     * Test importing a CSV file with manager and department lead permissions where permission columns are missing
     *
     * If the fields managerpermissions or departmentleadpermissions are not present in the CSV at all, assume "all"
     */
    public function test_import_positions_csv_with_permissions_missing_columns(): void {
        global $DB;
        $this->generate_structure();

        $user = $this->generate_user('user1');
        $this->make_user_tenant_admin($user->id);
        self::setUser($user);

        $tmpfile = make_request_directory().'/pos.csv';
        file_put_contents($tmpfile, <<<EOF
path,idnumber,parentidnumber,name,ismanager,isdepartmentlead,
DARTHVADER,DARTHVADER,,Supreme Commander of the Imperial Forces,1,1,
EOF
        );

        // Now let's create an importer from the CSV file.
        $importid = $this->get_workplace_generator()->prepare_import_from_file($tmpfile);
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);

        // First of all make sure orgstructure is among importers.
        $importers = $importmanager->get_importers();
        $this->assertCount(2, $importers);
        /** @var \tool_organisation\tool_wp\importer\orgstructure $importer */
        $importer = $importers[1];
        $this->assertInstanceOf(positions_csv::class, $importer);

        $settings = [
            'importer' => 'tool_organisation\tool_wp\importer\positions_csv',
            positions_csv::IMPORT_TARGET_FRAMEWORK => positions_csv::IMPORT_TARGET_FRAMEWORK_NEW,
            positions_csv::IMPORT_HIERARCHY => positions_csv::IMPORT_HIERARCHY_SELECTED,
            positions_csv::IMPORT_HIERARCHY_IDENTIFIER => 'idnumber',
            'csvmapping:name' => 'name',
            'csvmapping:idnumber' => 'idnumber',
            'csvmapping:description' => 'description',
            'csvmapping:descriptionformat' => 'descriptionformat',
            'csvmapping:parentid' => 'parentidnumber',
            'csvmapping:ismanager' => 'ismanager',
            'csvmapping:managerpermissions' => 'managerpermissions',
            'csvmapping:isdepartmentlead' => 'isdepartmentlead',
            'csvmapping:departmentleadpermissions' => 'departmentleadpermissions',
            helper::get_importer_setting_name_for_conflict_form(
                positions_csv::CSV_DATA, 'idnumberconflict', 'action') => 'empty',
        ];

        $importid = $this->get_workplace_generator()->perform_import_from_file($tmpfile, $settings);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(2, $logs);
        $this->assertEmpty($logs[0]['notices']);

        $posceo = $DB->get_record('tool_organisation_position', ['pathlevel' => 2, 'idnumber' => 'DARTHVADER'],
        'globalmanager,globalpermissions,departmentmanager,departmentpermissions');
        $this->assertEqualsCanonicalizing((object)[
            'globalmanager' => 1,
            'globalpermissions' => organisation::PERM_VIEW_REPORTS | organisation::PERM_ALLOCATE_PROGRAMS |
            organisation::PERM_RECEIVE_NOTIFICATIONS,
            'departmentmanager' => 1,
            'departmentpermissions' => organisation::PERM_VIEW_REPORTS | organisation::PERM_ALLOCATE_PROGRAMS |
            organisation::PERM_RECEIVE_NOTIFICATIONS,
        ], $posceo);
    }
}
