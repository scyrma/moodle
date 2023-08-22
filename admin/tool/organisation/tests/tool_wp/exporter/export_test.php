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

namespace tool_organisation\tool_wp\exporter;

use advanced_testcase;
use stdClass;
use tool_organisation_generator;
use tool_tenant_generator;
use tool_organisation\job;
use tool_organisation\position;
use tool_organisation\department;
use tool_wp\local\exportimport\helper;
use tool_organisation\tool_wp\importer\departments_csv as dep_importer_csv;
use tool_organisation\tool_wp\importer\positions_csv as pos_importer_csv;
use tool_wp_generator;

/**
 * Tests for the export API.
 *
 * @package    tool_organisation
 * @covers     \tool_organisation\tool_wp\exporter\orgstructure
 * @covers     \tool_organisation\tool_wp\exporter\jobs
 * @covers     \tool_organisation\tool_wp\exporter\positions_csv
 * @covers     \tool_organisation\tool_wp\exporter\departments_csv
 * @covers     \tool_organisation\tool_wp\exporter\jobs_csv
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_test extends advanced_testcase {

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
                'departmentid' => $this->{$department}->id]);
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
            'idnumber' => 'pa']);
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
            'description' => '<img src="@@PLUGINFILE@@/cat.png">', 'descriptionformat' => FORMAT_HTML]);
        // Add files to the description.
        get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'tool_organisation',
            'filearea' => \tool_organisation\department_manager::get_description_filearea(),
            'itemid' => $this->db1->id,
            'filepath' => '/',
            'filename' => 'cat.png'
        ], 'cat');
    }

    public function test_export() {
        global $DB;
        $this->generate_structure();
        $user = $this->generate_user('user1');
        $this->make_user_tenant_admin($user->id);
        $this->setUser($user);

        $settings = [
            orgstructure::EXPORT_INSTANCES => orgstructure::EXPORT_INSTANCES_ALL,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(
            orgstructure::class, $settings);
        $exportrecord = $DB->get_record('tool_wp_export', ['id' => $exportid]);
        $this->assertEquals(1, $exportrecord->status);

        // Now let's create an importer from the exported file.
        $importid = $this->get_workplace_generator()->prepare_import_from_export_id($exportid);
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);

        // First of all make sure orgstructure is among importers.
        $importers = $importmanager->get_importers();
        $this->assertCount(1, $importers);
        /** @var \tool_organisation\tool_wp\importer\orgstructure $importer */
        $importer = reset($importers);
        $this->assertTrue($importer instanceof \tool_organisation\tool_wp\importer\orgstructure);

        $entities = $importer->get_entities_in_workplace_export_file('tool_organisation_department');
        $this->assertCount(5, $entities);
        $entities = $importer->get_entities_in_workplace_export_file('tool_organisation_department_framework');
        $this->assertCount(2, $entities);
    }

    /**
     * Test export in jobs exporter on manual dep/pos frameworks.
     */
    public function test_export_selected_departments_positions_from_jobs() {
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
            jobs::EXPORT_TYPE => jobs::EXPORT_TYPE_MANUALLY,
            jobs::EXPORT_FROM_SELECTED_FRAMEWORKS => [$this->df->id, $this->pf->id],
            jobs::EXPORT_FRAMEWORKS => 1,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(
            jobs::class, $settings);
        $exportrecord = $DB->get_record('tool_wp_export', ['id' => $exportid]);
        $this->assertEquals(1, $exportrecord->status);

        // Now let's create an importer from the exported file.
        $importid = $this->get_workplace_generator()->prepare_import_from_export_id($exportid);
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);

        // First of all make sure orgstructure is among importers.
        $importers = $importmanager->get_importers();
        $this->assertCount(1, $importers);
        /** @var \tool_organisation\tool_wp\importer\orgstructure $importer */
        $importer = reset($importers);
        $this->assertTrue($importer instanceof \tool_organisation\tool_wp\importer\jobs);

        $entities = $importer->get_entities_in_workplace_export_file('tool_organisation_job');
        $this->assertCount(1, $entities);
        $entities = $importer->get_entities_in_workplace_export_file('tool_organisation_department');
        $this->assertCount(5, $entities);
        $entities = $importer->get_entities_in_workplace_export_file('tool_organisation_department_framework');
        $this->assertCount(1, $entities);
        $entities = $importer->get_entities_in_workplace_export_file('tool_organisation_position');
        $this->assertCount(5, $entities);
        $entities = $importer->get_entities_in_workplace_export_file('tool_organisation_position_framework');
        $this->assertCount(1, $entities);
    }

    /**
     * Data provider for {{@see test_export_import_departments_csv_existing_framework}}
     * and {{@see test_export_import_positions_csv_existing_framework}}
     *
     * @return array
     */
    public function settings_data_provider(): array {
        return [
            'frameworkbyid' => [dep_importer_csv::IMPORT_SELECT_FRAMEWORK, 'id'],
            'frameworkbyidnumber' => [dep_importer_csv::IMPORT_SELECT_FRAMEWORKIDNUMBER, 'idnumber'],
        ];
    }

    /**
     * Testing export and import departments CSV to an existing framework and with idnumber conflict resolution
     * set to empty.
     *
     * @param string $settingkey
     * @param string $objectkey
     *
     * @dataProvider settings_data_provider
     */
    public function test_export_import_departments_csv_existing_framework(string $settingkey, string $objectkey) {
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

        $depswithidnumber = array_filter($departments, function($dep) {
            return (!empty($dep->get('idnumber')));
        });
        $this->assertCount(2, $depswithidnumber);

        $tmpfile = make_request_directory().'/dep.csv';
        file_put_contents($tmpfile, <<<EOF
path,idnumber,parent,name,description,descriptionformat
da,da,,"New csv department 4",,0
546004,546004,,"New csv department 5",,0
da/546005,546005,da,"New csv department 6","Description",bbb
da/5,,da,"New csv department 7",,0
546004/546007,546007,546004,"New csv department 8","Description",1
546004/546008,546008,546004,"New csv department 9","Description",1
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
        $this->assertTrue($importer instanceof \tool_organisation\tool_wp\importer\departments_csv);

        $settings = [
            'importer' => 'tool_organisation\tool_wp\importer\departments_csv',
            dep_importer_csv::IMPORT_TARGET_FRAMEWORK => dep_importer_csv::IMPORT_TARGET_FRAMEWORK_SELECTED,
            $settingkey => $this->dfother->$objectkey,
            dep_importer_csv::IMPORT_HIERARCHY => dep_importer_csv::IMPORT_HIERARCHY_NONE,
            'csvmapping:name' => 'name',
            'csvmapping:idnumber' => 'idnumber',
            'csvmapping:description' => 'description',
            'csvmapping:descriptionformat' => 'descriptionformat',
            'csvmapping:parentid' => 'parent',
            helper::get_importer_setting_name_for_conflict_form(
                dep_importer_csv::CSV_DATA, 'idnumberconflict', 'action') => 'empty',
        ];

        $importid = $this->get_workplace_generator()->perform_import_from_file($tmpfile, $settings);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(6, $logs);
        $detailslog = array_filter($logs, function($log) {
            return (strpos($log['detail'], 'Created new department') === 0);
        });
        $this->assertCount(6, $detailslog);
        $logrecord = get_string('idnumberchanged', 'tool_wp', ['from' => 'da', 'to' => '']);
        $this->assertEquals($logrecord, $logs[0]['notices'][0]);

        $logrecord = get_string('exportimportfieldchanged', 'tool_wp', ['field' => 'descriptionformat',
            'from' => 'bbb', 'to' => FORMAT_HTML]);
        $this->assertEquals($logrecord, $logs[2]['notices'][0]);

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
        // There are only 5 csv departments and 'dfother' have idnumber.
        $this->assertCount(6, $depswithidnumber);
    }

    /**
     * Testing export and import positions CSV creating a new framework and with idnumber conflict resolution
     * set to increment.
     */
    public function test_export_import_positions_csv_new_framework() {
        global $DB;
        $this->generate_structure();
        $user = $this->generate_user('user1');
        $this->make_user_tenant_admin($user->id);
        self::setUser($user);

        $positions = \tool_organisation\position::get_records();
        $this->assertCount(8, $positions);
        $positionframeworks = array_filter($positions, function($pos) {
            return ((int)$pos->get('pathlevel') === 1);
        });
        // Check there are 3 position frameworks.
        $this->assertCount(3, $positionframeworks);

        $poswithidnumber = array_filter($positions, function($pos) {
            return (!empty($pos->get('idnumber')));
        });
        $this->assertCount(2, $poswithidnumber);

        $tmpfile = make_request_directory().'/pos.csv';
        file_put_contents($tmpfile, <<<EOF
path,name,parentid,idnumber,description,descriptionformat,departmentmanager,globalmanager,departmentpermissions,globalpermissions
pa,"New position 4",547000,pa,,0,0,1,0,3
547004,"New position 5",,547004,,0,1,0,0,0
547004/547007,"New position 8",547004,547007,,0,0,0,0,0
pa/6,"New position 6",pa,,,0,0,0,0,0
pa/7,"New position 7",pa,,,0,0,0,0,0
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
        $this->assertTrue($importer instanceof \tool_organisation\tool_wp\importer\positions_csv);

        $settings = [
            'importer' => 'tool_organisation\tool_wp\importer\positions_csv',
            pos_importer_csv::IMPORT_TARGET_FRAMEWORK => pos_importer_csv::IMPORT_TARGET_FRAMEWORK_NEW,
            pos_importer_csv::IMPORT_HIERARCHY => pos_importer_csv::IMPORT_HIERARCHY_NONE,
            'csvmapping:name' => 'name',
            'csvmapping:idnumber' => 'idnumber',
            'csvmapping:description' => 'description',
            'csvmapping:descriptionformat' => 'descriptionformat',
            'csvmapping:parentid' => 'parentid',
            'csvmapping:globalmanager' => 'ismanager',
            'csvmapping:departmentmanager' => 'ismanager',
            helper::get_importer_setting_name_for_conflict_form(
                pos_importer_csv::CSV_DATA, 'idnumberconflict', 'action') => 'increment',
        ];

        $importid = $this->get_workplace_generator()->perform_import_from_file($tmpfile, $settings);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertStringStartsWith('Created new position framework', $logs[0]['detail']);
        array_shift($logs); // Remove the first record that position framework was created.

        $this->assertCount(5, $logs);
        $this->assertStringStartsWith('ID number was changed from', $logs[0]['notices'][0]);

        $detailslog = array_map(function($log) {
            return $log['detail'];
        }, $logs);
        $this->assertCount(5, $detailslog);
        $expected = [
            get_string('importlogpossuccess', 'tool_organisation', ['name' => $this->pa->name]),
            get_string('importlogpossuccess', 'tool_organisation', ['name' => $this->pa1->name]),
            get_string('importlogpossuccess', 'tool_organisation', ['name' => $this->pa2->name]),
            get_string('importlogpossuccess', 'tool_organisation', ['name' => $this->pb->name]),
            get_string('importlogpossuccess', 'tool_organisation', ['name' => $this->pb1->name]),
        ];
        $this->assertEqualsCanonicalizing($expected, $detailslog);

        $positions = \tool_organisation\position::get_records();
        $this->assertCount(14, $positions);
        $positionframeworks = array_filter($positions, function($pos) {
            return ((int)$pos->get('pathlevel') === 1);
        });
        // Check there are 4 position frameworks.
        $this->assertCount(4, $positionframeworks);

        $poswithidnumber = array_filter($positions, function($pos) {
            return (!empty($pos->get('idnumber')));
        });
        // There are only 3 csv departments and existing 'pa' and 'pfother' have idnumber.
        $this->assertCount(5, $poswithidnumber);
    }

    /**
     * Testing export and import positions CSV to an existing framework and with idnumber conflict resolution
     * set to empty.
     *
     * @param string $settingkey
     * @param string $objectkey
     *
     * @dataProvider settings_data_provider
     */
    public function test_export_import_positions_csv_existing_framework(string $settingkey, string $objectkey) {
        global $DB;
        $this->generate_structure();
        $user = $this->generate_user('user1');
        $this->make_user_tenant_admin($user->id);
        self::setUser($user);

        $positions = \tool_organisation\position::get_records();
        $this->assertCount(8, $positions);
        $positionframeworks = array_filter($positions, function($pos) {
            return ((int)$pos->get('pathlevel') === 1);
        });
        // Check there are 3 position frameworks.
        $this->assertCount(3, $positionframeworks);

        $poswithidnumber = array_filter($positions, function($pos) {
            return (!empty($pos->get('idnumber')));
        });
        $this->assertCount(2, $poswithidnumber);

        $tmpfile = make_request_directory().'/pos.csv';
        file_put_contents($tmpfile, <<<EOF
path,name,parent,idnumber,description,descriptionformat,departmentmanager,globalmanager,departmentpermissions,globalpermissions
pa,"New position 4",,pa,,0,0,1,0,3
547000,"New position 5",,547000,,0,1,0,0,0
547000/547004,"New position 8",547000,547004,"description",bbb,0,0,0,0
pa/5,"New position 6",pa,,,0,0,0,0,0
pa/6,"New position 7",pa,,,0,0,0,0,0
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
        $this->assertTrue($importer instanceof \tool_organisation\tool_wp\importer\positions_csv);

        $settings = [
            'importer' => 'tool_organisation\tool_wp\importer\positions_csv',
            pos_importer_csv::IMPORT_TARGET_FRAMEWORK => pos_importer_csv::IMPORT_TARGET_FRAMEWORK_SELECTED,
            $settingkey => $this->pfother->$objectkey,
            pos_importer_csv::IMPORT_HIERARCHY => pos_importer_csv::IMPORT_HIERARCHY_NONE,
            'csvmapping:name' => 'name',
            'csvmapping:idnumber' => 'idnumber',
            'csvmapping:description' => 'description',
            'csvmapping:descriptionformat' => 'descriptionformat',
            'csvmapping:parentid' => 'parentid',
            helper::get_importer_setting_name_for_conflict_form(
                pos_importer_csv::CSV_DATA, 'idnumberconflict', 'action') => 'empty',
        ];

        $importid = $this->get_workplace_generator()->perform_import_from_file($tmpfile, $settings);
        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(5, $logs);
        $detailslog = array_filter($logs, function($log) {
            return (strpos($log['detail'], 'Created new position') === 0);
        });
        $this->assertCount(5, $detailslog);
        $logrecord = get_string('idnumberchanged', 'tool_wp', ['from' => 'pa', 'to' => '']);
        $this->assertEquals($logrecord, $logs[0]['notices'][0]);

        $logrecord = get_string('exportimportfieldchanged', 'tool_wp', ['field' => 'descriptionformat',
            'from' => 'bbb', 'to' => FORMAT_HTML]);
        $this->assertEquals($logrecord, $logs[2]['notices'][0]);

        $positions = \tool_organisation\position::get_records();
        $this->assertCount(13, $positions);
        $positionframeworks = array_filter($positions, function($pos) {
            return ((int)$pos->get('pathlevel') === 1);
        });
        // Check there still are 3 position frameworks.
        $this->assertCount(3, $positionframeworks);

        $poswithidnumber = array_filter($positions, function($pos) {
            return (!empty($pos->get('idnumber')));
        });
        // There are only 3 csv positions and 'pfother' have idnumber.
        $this->assertCount(4, $poswithidnumber);
    }

    /**
     * Test export position framework to a CSV file.
     */
    public function test_export_positions_csv(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->get_generator();

        // Create tenant, position frm and permissions to export.
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $pf = $generator->create_position(['tenantid' => $tenant1->id, 'idnumber' => 'pf']);
        $p1 = $generator->create_position(['parentid' => $pf->id, 'idnumber' => 'p1',
            'departmentmanager' => 1,
            'departmentpermissions' => \tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS |
                \tool_organisation\organisation::PERM_VIEW_REPORTS]);
        $p2 = $generator->create_position(['parentid' => $p1->id, 'idnumber' => 'p2',
            'globalmanager' => 1, 'globalpermissions' => \tool_organisation\organisation::PERM_VIEW_REPORTS]);
        $p3 = $generator->create_position(['parentid' => $p2->id]);

        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant1->id);
        $this->make_user_tenant_admin($user1->id);
        self::setUser($user1);

        // Export a specific framework.
        $settings = [
            positions_csv::EXPORT_SELECT_FRAMEWORK => $pf->id,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(positions_csv::class, $settings);
        $exportrecord = $DB->get_record('tool_wp_export', ['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $exportrecord->status);

        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'tool_wp',
            'export', $exportid, '', false);
        $this->assertCount(1, $files);
        $csvfilecontent = reset($files)->get_content();
        // Get rows of csv file.
        $contentrows = explode("\n", $csvfilecontent);
        array_pop($contentrows);

        // Validate CSV header row.
        $expectedheaderrow = "path,idnumber,name,description,ismanager,isdepartmentlead,managerpermissions,"
            . "departmentleadpermissions,parent";
        $this->assertEquals($expectedheaderrow, reset($contentrows));

        // Validate content rows.
        $expectedrow = implode(',', [
            'path' => $p1->idnumber,
            'idnumber' => $p1->idnumber,
            'name' => '"' . $p1->name . '"',
            'description' => $p1->description,
            'ismanager' => 0,
            'isdepartmentlead' => 1,
            'managerpermissions' => '',
            'departmentleadpermissions' => '"allocate,reports"',
            'parent' => '',
        ]);
        $actualrow = next($contentrows);
        $this->assertEquals($expectedrow, $actualrow);

        $expectedrow = implode(',', [
            'path' => $p1->idnumber . '/' . $p2->idnumber,
            'idnumber' => $p2->idnumber,
            'name' => '"' . $p2->name . '"',
            'description' => $p2->description,
            'ismanager' => 1,
            'isdepartmentlead' => 0,
            'managerpermissions' => 'reports',
            'departmentleadpermissions' => '',
            'parent' => $p1->idnumber,
        ]);
        $actualrow = next($contentrows);
        $this->assertEquals($expectedrow, $actualrow);

        // Pos p3 does not have idnumber, it is replaced with id in the path.
        $expectedrow = implode(',', [
            'path' => $p1->idnumber . '/' . $p2->idnumber  . '/' . $p3->id,
            'idnumber' => '',
            'name' => '"' . $p3->name . '"',
            'description' => $p3->description,
            'ismanager' => 0,
            'isdepartmentlead' => 0,
            'managerpermissions' => '',
            'departmentleadpermissions' => '',
            'parent' => $p2->idnumber,
        ]);
        $actualrow = next($contentrows);
        $this->assertEquals($expectedrow, $actualrow);
    }

    /**
     * Test export empty position framework to a CSV file.
     */
    public function test_export_positions_csv_empty_framework(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->get_generator();

        // Create tenant, position frm and permissions to export.
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $pf = $generator->create_position(['tenantid' => $tenant1->id, 'idnumber' => 'pf']);

        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant1->id);
        $this->make_user_tenant_admin($user1->id);
        self::setUser($user1);

        // Export a specific framework.
        $settings = [
            positions_csv::EXPORT_SELECT_FRAMEWORK => $pf->id,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(positions_csv::class, $settings);
        $exportrecord = $DB->get_record('tool_wp_export', ['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $exportrecord->status);

        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'tool_wp',
            'export', $exportid, '', false);
        $this->assertCount(1, $files);
        $csvfilecontent = reset($files)->get_content();
        // Get rows of csv file.
        $contentrows = explode("\n", $csvfilecontent);
        array_pop($contentrows);

        // Expect header and empty row.
        $this->assertCount(2, $contentrows);

        // Validate CSV header row.
        $expectedheaderrow = "path,idnumber,name,description,ismanager,isdepartmentlead,managerpermissions,"
            . "departmentleadpermissions,parent";
        $this->assertEquals($expectedheaderrow, reset($contentrows));

        // Validate empty row.
        $expectedemptyrow = ",,,,,,,,";
        $this->assertEquals($expectedemptyrow, next($contentrows));
    }

    /**
     * Test export position framework to a CSV file and import from it.
     */
    public function test_export_import_positions_csv(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->get_generator();

        // Create tenant, position frm and permissions to export.
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $pf = $generator->create_position(['tenantid' => $tenant1->id, 'idnumber' => 'pf']);
        $p1 = $generator->create_position(['parentid' => $pf->id, 'idnumber' => 'p1',
            'departmentmanager' => 1,
            'departmentpermissions' => \tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS |
                \tool_organisation\organisation::PERM_VIEW_REPORTS]);
        $p2 = $generator->create_position(['parentid' => $p1->id, 'idnumber' => 'p2',
            'globalmanager' => 1, 'globalpermissions' => \tool_organisation\organisation::PERM_VIEW_REPORTS]);
        $p3 = $generator->create_position(['parentid' => $p2->id, 'idnumber' => 'p3']);

        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant1->id);
        $this->make_user_tenant_admin($user1->id);
        self::setUser($user1);

        // Export a specific framework.
        $settings = [
            positions_csv::EXPORT_SELECT_FRAMEWORK => $pf->id,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(positions_csv::class, $settings);
        $exportrecord = $DB->get_record('tool_wp_export', ['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $exportrecord->status);

        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'tool_wp',
            'export', $exportid, '', false);
        $this->assertCount(1, $files);

        // Import into another tenant with another user.
        $tenant2 = $this->get_tenant_generator()->create_tenant();
        $user2 = $this->getDataGenerator()->create_user(['username' => 'user2']);
        $this->get_tenant_generator()->allocate_user($user2->id, $tenant2->id);
        $this->make_user_tenant_admin($user2->id);
        self::setUser($user2);

        // Now let's create an importer from the exported file.
        $importid = $this->get_workplace_generator()->prepare_import_from_export_id($exportid);
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);

        // First of all make sure orgstructure is among importers.
        $importers = $importmanager->get_importers();
        $this->assertCount(2, $importers);
        /** @var \tool_organisation\tool_wp\importer\positions_csv $importer */
        $importer = $importers[1];
        $this->assertInstanceOf(pos_importer_csv::class, $importer);

        $settings = [
            'importer' => 'tool_organisation\tool_wp\importer\positions_csv',
            pos_importer_csv::IMPORT_TARGET_FRAMEWORK => pos_importer_csv::IMPORT_TARGET_FRAMEWORK_NEW,
            pos_importer_csv::IMPORT_HIERARCHY => pos_importer_csv::IMPORT_HIERARCHY_SELECTED,
            pos_importer_csv::IMPORT_HIERARCHY_IDENTIFIER => 'idnumber',
            'csvmapping:name' => 'name',
            'csvmapping:idnumber' => 'idnumber',
            'csvmapping:description' => 'description',
            'csvmapping:descriptionformat' => 'descriptionformat',
            'csvmapping:parentid' => 'parent',
            'csvmapping:ismanager' => 'ismanager',
            'csvmapping:managerpermissions' => 'managerpermissions',
            'csvmapping:isdepartmentlead' => 'isdepartmentlead',
            'csvmapping:departmentleadpermissions' => 'departmentleadpermissions',
            helper::get_importer_setting_name_for_conflict_form(
                pos_importer_csv::CSV_DATA, 'idnumberconflict', 'action') => 'empty',
        ];
        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, $settings);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(4, $logs);
        $this->assertEmpty($logs[0]['notices']);

        // Check out imported positions.
        $expectedpositions = $DB->get_records_select(position::TABLE,
            'tenantid = :tenantid AND parentid IS NOT NULL',
            ['tenantid' => $tenant1->id]);
        foreach ($expectedpositions as $expected) {
            $imported = $DB->get_record(position::TABLE, [
                'idnumber' => $expected->idnumber,
                'pathlevel' => $expected->pathlevel,
                'tenantid' => $tenant2->id,
            ], 'name, globalmanager, globalpermissions, departmentmanager, departmentpermissions');
            $this->assertEqualsCanonicalizing((object)[
                'name' => $expected->name,
                'globalmanager' => $expected->globalmanager,
                'globalpermissions' => $expected->globalpermissions,
                'departmentmanager' => $expected->departmentmanager,
                'departmentpermissions' => $expected->departmentpermissions,
            ], $imported);
        }
    }

    /**
     * Test export department framework to a CSV file.
     */
    public function test_export_departments_csv(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->get_generator();

        // Create tenant and department frm.
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $df = $generator->create_department(['tenantid' => $tenant1->id, 'idnumber' => 'df']);
        $d1 = $generator->create_department(['parentid' => $df->id, 'idnumber' => 'd1']);
        $d2 = $generator->create_department(['parentid' => $d1->id, 'idnumber' => 'd2']);
        $d3 = $generator->create_department(['parentid' => $d2->id]);

        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant1->id);
        $this->make_user_tenant_admin($user1->id);
        self::setUser($user1);

        // Export a specific framework.
        $settings = [
            departments_csv::EXPORT_SELECT_FRAMEWORK => $df->id,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(departments_csv::class, $settings);
        $exportrecord = $DB->get_record('tool_wp_export', ['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $exportrecord->status);

        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'tool_wp',
            'export', $exportid, '', false);
        $this->assertCount(1, $files);
        $csvfilecontent = reset($files)->get_content();
        // Get rows of csv file.
        $contentrows = explode("\n", $csvfilecontent);
        array_pop($contentrows);

        // Validate CSV header row.
        $expectedheaderrow = "path,idnumber,name,description,parent";
        $this->assertEquals($expectedheaderrow, reset($contentrows));

        // Validate content rows.
        $expectedrow = implode(',', [
            'path' => $d1->idnumber,
            'idnumber' => $d1->idnumber,
            'name' => '"' . $d1->name . '"',
            'description' => $d1->description,
            'parent' => '',
        ]);
        $actualrow = next($contentrows);
        $this->assertEquals($expectedrow, $actualrow);

        $expectedrow = implode(',', [
            'path' => $d1->idnumber . '/' . $d2->idnumber,
            'idnumber' => $d2->idnumber,
            'name' => '"' . $d2->name . '"',
            'description' => $d2->description,
            'parent' => $d1->idnumber,
        ]);
        $actualrow = next($contentrows);
        $this->assertEquals($expectedrow, $actualrow);

        // Dep d3 does not have idnumber, it is replaced with id in the path.
        $expectedrow = implode(',', [
            'path' => $d1->idnumber . '/' . $d2->idnumber . '/' . $d3->id,
            'idnumber' => '',
            'name' => '"' . $d3->name . '"',
            'description' => $d3->description,
            'parent' => $d2->idnumber,
        ]);
        $actualrow = next($contentrows);
        $this->assertEquals($expectedrow, $actualrow);
    }

    /**
     * Test export empty department framework to a CSV file.
     */
    public function test_export_departments_csv_empty_framework(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->get_generator();

        // Create tenant and department frm.
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $df = $generator->create_department(['tenantid' => $tenant1->id, 'idnumber' => 'df']);

        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant1->id);
        $this->make_user_tenant_admin($user1->id);
        self::setUser($user1);

        // Export a specific framework.
        $settings = [
            departments_csv::EXPORT_SELECT_FRAMEWORK => $df->id,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(departments_csv::class, $settings);
        $exportrecord = $DB->get_record('tool_wp_export', ['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $exportrecord->status);

        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'tool_wp',
            'export', $exportid, '', false);
        $this->assertCount(1, $files);
        $csvfilecontent = reset($files)->get_content();
        // Get rows of csv file.
        $contentrows = explode("\n", $csvfilecontent);
        array_pop($contentrows);
        // Expect header and empty row.
        $this->assertCount(2, $contentrows);

        // Validate CSV header row.
        $expectedheaderrow = "path,idnumber,name,description,parent";
        $this->assertEquals($expectedheaderrow, reset($contentrows));
        // Validate empty row.
        $expectedemptyrow = ",,,,";
        $this->assertEquals($expectedemptyrow, next($contentrows));
    }

    /**
     * Test export department framework to a CSV file and import from it.
     */
    public function test_export_import_departments_csv(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->get_generator();

        // Create tenant and department frm.
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $df = $generator->create_department(['tenantid' => $tenant1->id, 'idnumber' => 'df']);
        $d1 = $generator->create_department(['parentid' => $df->id, 'idnumber' => 'd1']);
        $d2 = $generator->create_department(['parentid' => $d1->id, 'idnumber' => 'd2']);
        $d3 = $generator->create_department(['parentid' => $d2->id, 'idnumber' => 'd3']);

        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant1->id);
        $this->make_user_tenant_admin($user1->id);
        self::setUser($user1);

        // Export a specific framework.
        $settings = [
            departments_csv::EXPORT_SELECT_FRAMEWORK => $df->id,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(departments_csv::class, $settings);
        $exportrecord = $DB->get_record('tool_wp_export', ['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $exportrecord->status);

        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'tool_wp',
            'export', $exportid, '', false);
        $this->assertCount(1, $files);

        // Import into another tenant with another user.
        $tenant2 = $this->get_tenant_generator()->create_tenant();
        $user2 = $this->getDataGenerator()->create_user(['username' => 'user2']);
        $this->get_tenant_generator()->allocate_user($user2->id, $tenant2->id);
        $this->make_user_tenant_admin($user2->id);
        self::setUser($user2);

        // Now let's create an importer from the exported file.
        $importid = $this->get_workplace_generator()->prepare_import_from_export_id($exportid);
        $importmanager = new \tool_wp\local\exportimport\import_manager($importid);

        // First of all make sure orgstructure is among importers.
        $importers = $importmanager->get_importers();
        $this->assertCount(2, $importers);
        /** @var \tool_organisation\tool_wp\importer\departments_csv $importer */
        $importer = $importers[0];
        $this->assertInstanceOf(dep_importer_csv::class, $importer);

        $settings = [
            'importer' => 'tool_organisation\tool_wp\importer\positions_csv',
            dep_importer_csv::IMPORT_TARGET_FRAMEWORK => dep_importer_csv::IMPORT_TARGET_FRAMEWORK_NEW,
            dep_importer_csv::IMPORT_HIERARCHY => dep_importer_csv::IMPORT_HIERARCHY_SELECTED,
            dep_importer_csv::IMPORT_HIERARCHY_IDENTIFIER => 'idnumber',
            'csvmapping:name' => 'name',
            'csvmapping:idnumber' => 'idnumber',
            'csvmapping:description' => 'description',
            'csvmapping:descriptionformat' => 'descriptionformat',
            'csvmapping:parentid' => 'parent',
            helper::get_importer_setting_name_for_conflict_form(
                dep_importer_csv::CSV_DATA, 'idnumberconflict', 'action') => 'empty',
        ];
        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, $settings);

        $logs = $this->get_workplace_generator()->get_import_logs($importid);
        $this->assertCount(4, $logs);
        $this->assertEmpty($logs[0]['notices']);

        // Check out imported departments.
        $expecteddepartments = $DB->get_records_select(position::TABLE,
            'tenantid = :tenantid AND parentid IS NOT NULL',
            ['tenantid' => $tenant1->id]);
        foreach ($expecteddepartments as $expected) {
            $imported = $DB->get_record(position::TABLE, [
                'idnumber' => $expected->idnumber,
                'pathlevel' => $expected->pathlevel,
                'tenantid' => $tenant2->id,
            ], 'name');
            $this->assertEqualsCanonicalizing((object)['name' => $expected->name], $imported);
        }
    }

    /**
     * Test export jobs to a CSV file.
     */
    public function test_export_jobs_csv(): void {
        global $DB;
        $this->resetAfterTest();
        $this->generate_structure();

        // Create our tenantadmin.
        $tenantadmin = $this->generate_user('tenantadmin');
        $this->make_user_tenant_admin($tenantadmin->id);
        self::setUser($tenantadmin);
        // Create two users with jobs.
        $users['user1'] = $this->generate_user('user1', 'pa', 'da');
        $users['user2'] = $this->generate_user('user2', 'pa2', 'db1');
        // Get their jobid.
        $users['user1']->job = job::get_record(['tenantid' => $this->tenant->id, 'userid' => $users['user1']->id]);
        $users['user2']->job = job::get_record(['tenantid' => $this->tenant->id, 'userid' => $users['user2']->id]);

        // Export all jobs.
        $settings = [
            jobs_csv::EXPORT_TYPE => jobs_csv::EXPORT_TYPE_ALL,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(jobs_csv::class, $settings);
        $exportrecord = $DB->get_record('tool_wp_export', ['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $exportrecord->status);

        // There isn't a CSV importer for jobs yet so, we're going to validate its file content.
        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'tool_wp',
            'export', $exportid, 'id', false);
        $this->assertCount(1, $files);
        $csvfilecontent = reset($files)->get_content();
        // Get rows of csv file.
        $contentrows = explode("\n", $csvfilecontent);
        array_pop($contentrows);

        // Validate CSV header row.
        $expectedheaderrow = "userid,userfullname,email,startdate,enddate,departmentidnumber,"
            . "departmentpath,positionidnumber,positionpath,jobid";
        $this->assertEquals($expectedheaderrow, reset($contentrows));

        // Validate content rows.
        $expectedrow = implode(',', [
            'userid' => $users['user1']->id,
            'userfullname' => '"' . fullname($users['user1']) . '"' ,
            'email' => $users['user1']->email,
            'startdate' => \tool_organisation\helper::get_job_time_for_export($users['user1']->job->get('startdate')),
            'enddate' => '',
            'departmentidnumber' => $this->da->idnumber,
            'departmentpath' => $this->da->idnumber,
            'positionidnumber' => $this->pa->idnumber,
            'positionpath' => $this->pa->idnumber,
            'jobid' => $users['user1']->job->get('id'),
        ]);
        $actualrow = next($contentrows);
        $this->assertEquals($expectedrow, $actualrow);
        // Expect the path contain mix of idnumbers and ids (id is used for department/positions that do not have idnumber).
        $expectedrow = implode(',', [
            'userid' => $users['user2']->id,
            'userfullname' => '"' . fullname($users['user2']) . '"' ,
            'email' => $users['user2']->email,
            'startdate' => \tool_organisation\helper::get_job_time_for_export($users['user2']->job->get('startdate')),
            'enddate' => '',
            'departmentidnumber' => $this->db1->idnumber,
            'departmentpath' => $this->db->id . '/' . $this->db1->id,
            'positionidnumber' => $this->pa2->idnumber,
            'positionpath' => $this->pa->idnumber . '/' . $this->pa2->id,
            'jobid' => $users['user2']->job->get('id'),
        ]);
        $actualrow = next($contentrows);
        $this->assertEqualsCanonicalizing($expectedrow, $actualrow);
    }

    /**
     * Test export jobs from empty framework to a CSV file.
     */
    public function test_export_jobs_csv_empty_framework(): void {
        global $DB;
        $this->resetAfterTest();
        $this->generate_structure();

        // Create our tenantadmin.
        $tenantadmin = $this->generate_user('tenantadmin');
        $this->make_user_tenant_admin($tenantadmin->id);
        self::setUser($tenantadmin);

        // Export all jobs.
        $settings = [
            jobs_csv::EXPORT_TYPE => jobs_csv::EXPORT_TYPE_ALL,
        ];
        $exportid = $this->get_workplace_generator()->perform_export(jobs_csv::class, $settings);
        $exportrecord = $DB->get_record('tool_wp_export', ['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $exportrecord->status);

        // There isn't a CSV importer for jobs yet so, we're going to validate its file content.
        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'tool_wp',
            'export', $exportid, 'id', false);
        $this->assertCount(1, $files);
        $csvfilecontent = reset($files)->get_content();
        // Get rows of csv file.
        $contentrows = explode("\n", $csvfilecontent);
        array_pop($contentrows);

        // Expect header and empty row.
        $this->assertCount(2, $contentrows);

        // Validate CSV header row.
        $expectedheaderrow = "userid,userfullname,startdate,enddate,departmentidnumber,"
            . "departmentpath,positionidnumber,positionpath,jobid";
        $this->assertEquals($expectedheaderrow, reset($contentrows));

        // Validate empty row.
        $expectedemptyrow = ",,,,,,,,";
        $this->assertEquals($expectedemptyrow, next($contentrows));
    }
}
