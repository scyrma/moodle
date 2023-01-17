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

namespace tool_wp;

use advanced_testcase;
use context_system;
use core_customfield_generator;
use tool_program_generator;
use tool_wp_generator;
use tool_tenant_generator;
use tool_program\tool_wp\importer\programs as programs_importer;
use tool_program\tool_wp\exporter\programs as programs_exporter;
use tool_wp\local\exportimport\helper;

/**
 * Class export_import_customfields_test
 *
 * @covers     \tool_wp\tool_wp\exporter\customfields
 * @covers     \tool_wp\tool_wp\importer\customfields
 * @package    tool_wp
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_import_customfields_test extends advanced_testcase {
    /** @var tool_program_generator */
    protected $programgenerator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var tool_wp_generator */
    protected $wpgenerator;
    /** @var core_customfield_generator */
    protected $cfgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->wpgenerator = self::getDataGenerator()->get_plugin_generator('tool_wp');
        $this->cfgenerator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->resetAfterTest();
    }

    /**
     * Create and export a program with custom fields
     *
     * @param array $programparams
     * @param array $exportsettings settings for export (by default export only this program)
     * @return array [$program, $exportid]
     */
    protected function create_and_export_program(array $programparams, array $exportsettings = []) {
        $params = [
            'component' => 'tool_program',
            'area' => 'program',
            'itemid' => 0,
            'contextid' => context_system::instance()->id
        ];
        $category = $this->cfgenerator->create_category($params);
        $this->cfgenerator->create_field(['categoryid' => $category->get('id'),
            'type' => 'text', 'shortname' => 'fld1']);
        $this->cfgenerator->create_field(['categoryid' => $category->get('id'),
            'type' => 'text', 'shortname' => 'fld2']);
        $this->cfgenerator->create_field(['categoryid' => $category->get('id'),
            'type' => 'text', 'shortname' => 'fld3']);
        $this->cfgenerator->create_field(['categoryid' => $category->get('id'),
            'type' => 'select', 'shortname' => 'fld4']);
        // Another custom field that will have no data set and will not be exported.
        $this->cfgenerator->create_field(['categoryid' => $category->get('id'),
            'type' => 'text', 'shortname' => 'fld5']);

        $programparams['customfield_fld1'] = 'Hello1';
        $programparams['customfield_fld2'] = 'Hello2';
        $programparams['customfield_fld3'] = 'Hello3';
        $programparams['customfield_fld4'] = '1';

        $program = $this->programgenerator->generate_program_with_course((object)$programparams);
        $newset = $this->programgenerator->generate_set((object)['parent' => $program->get_base_set()->get('id')]);
        $course = $this->programgenerator->generate_course_with_completion_self(['shortname' => 'c2']);
        $this->programgenerator->add_course_to_set($course->id, $newset->get('id'));

        // Export program $program.
        $settings = $exportsettings + [
                programs_exporter::EXPORT_CONTENT => 1,
                programs_exporter::EXPORT_INSTANCES => programs_exporter::EXPORT_INSTANCES_SELECTED,
                programs_exporter::EXPORT_USER_ALLOCATIONS => 1,
                programs_exporter::EXPORT_COURSE_BACKUPS => 0,
                programs_exporter::EXPORT_PROGRAM_DYNAMICRULES => 1,
                programs_exporter::EXPORT_SELECT_PROGRAMS => [$program->get('id')]
            ];
        $exportid = $this->wpgenerator->perform_export(
            programs_exporter::class, $settings);
        return [$program, $exportid];
    }

    /**
     * Generates tenant admin with program:edit and course_create capabilities and users
     */
    private function generate_tenant_and_users() {
        global $DB;
        $category = self::getDataGenerator()->create_category();
        [$tenant, $users] = $this->tenantgenerator->create_tenant_and_users(3);
        $tenant = new \tool_tenant\tenant(0, $tenant);
        $tenant->set('categoryid', $category->id);
        $tenant->update();
        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        $context = context_system::instance();
        self::getDataGenerator()->role_assign($managerrole->id, $users[0]->id, $context);
        assign_capability('tool/program:edit', CAP_ALLOW, $managerrole->id, $context->id);
        assign_capability('moodle/course:create', CAP_ALLOW, $managerrole->id, $context->id);
        return [$tenant, $users];
    }

    /**
     * Test exporting a program with customfields.
     */
    public function test_export_program_with_customfields(): void {
        global $DB;

        [$tenant, $users] = $this->generate_tenant_and_users();
        self::setUser($users[0]);

        [$program, $exportid] = $this->create_and_export_program([
            'tenantid' => $tenant->get('id'),
            'idnumber' => 'TESTP',
            'fullname' => 'My program1'
        ], []);

        $this->assertCount(1, $DB->get_records('customfield_category'));
        $this->assertCount(5, $DB->get_records('customfield_field'));
        $this->assertCount(4, $DB->get_records('customfield_data'));

        $export = \tool_wp\local\exportimport\export_persistent::get_record(['id' => $exportid]);
        $this->assertEquals(helper::STATUS_DONE, $export->get('status'));

        // Import the export we previously created.
        $importid = $this->wpgenerator->prepare_import_from_export_id($export->get('id'));

        $importers = (new \tool_wp\local\exportimport\import_manager($importid))->get_importers();
        $this->assertCount(1, $importers);

        $importer = reset($importers);
        $this->assertInstanceOf(programs_importer::class, $importer);

        $customfielddata = $importer->get_entities_in_workplace_export_file('customfield_data');
        $this->assertCount(4, $customfielddata);

        // Test than component, area and instanceid are correctly matched.
        $entities = iterator_to_array($customfielddata, false);
        foreach ($entities as $entity) {
            $this->assertEquals('tool_program', $entity->get_raw_field('component'));
            $this->assertEquals('program', $entity->get_raw_field('area'));
            $this->assertEquals($program->get('id'), $entity->get_raw_field('instanceid'));
        }
    }

    /**
     * Test importing a program with customfields.
     */
    public function test_import_program_with_customfields(): void {
        global $DB;

        [$tenant, $users] = $this->generate_tenant_and_users();
        self::setUser($users[0]);

        [$program, $exportid] = $this->create_and_export_program([
            'tenantid' => $tenant->get('id'),
            'idnumber' => 'TESTP',
            'fullname' => 'My program1'
        ], []);

        $this->assertCount(4, $DB->get_records('customfield_data'));

        // Now let's create an importer from the exported file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_SELECTED,
            programs_importer::IMPORT_SELECTED_PROGRAMS => [$program->get('id')],
            programs_importer::IMPORT_USER_ALLOCATIONS => 0,
            programs_importer::IMPORT_COURSE_BACKUPS => 1,
            programs_importer::IMPORT_PROGRAM_DYNAMICRULES => 0,
            helper::get_importer_setting_name_for_conflict_form('tool_program', 'idnumberconflict', 'action') => 'increment',
        ]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $this->assertCount(8, $DB->get_records('customfield_data'));

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(1, $logs);
        $this->assertStringStartsWith('Created new program', $logs[0]['detail']);
    }

    /**
     * Test importing a program with customfields where fields don't exist in this instance.
     */
    public function test_import_program_with_customfields_field_conflict(): void {
        global $DB;

        [$tenant, $users] = $this->generate_tenant_and_users();
        self::setUser($users[0]);

        [$program, $exportid] = $this->create_and_export_program([
            'tenantid' => $tenant->get('id'),
            'idnumber' => 'TESTP',
            'fullname' => 'My program1'
        ], []);

        $DB->delete_records('customfield_field');
        $DB->delete_records('customfield_data');

        // Now let's create an importer from the exported file.
        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            programs_importer::IMPORT_CONTENT => 1,
            programs_importer::IMPORT_INSTANCES => programs_importer::IMPORT_INSTANCES_SELECTED,
            programs_importer::IMPORT_SELECTED_PROGRAMS => [$program->get('id')],
            programs_importer::IMPORT_USER_ALLOCATIONS => 0,
            programs_importer::IMPORT_COURSE_BACKUPS => 1,
            programs_importer::IMPORT_PROGRAM_DYNAMICRULES => 0,
            helper::get_setting_name_for_conflict_form('customfield_field', 'action') => 'skip',
            helper::get_importer_setting_name_for_conflict_form('tool_program', 'idnumberconflict', 'action') => 'increment',
        ]);

        $importrecord = $DB->get_record('tool_wp_import', ['id' => $importid]);
        $this->assertEquals(helper::STATUS_DONE, $importrecord->status);

        $this->assertCount(0, $DB->get_records('customfield_data'));

        // Analyse logs.
        $logs = $this->wpgenerator->get_import_logs($importid);
        $this->assertCount(5, $logs);
        $errors = array_filter($logs, function($log) {
            return !empty($log['errors']);
        });
        $this->assertCount(4, $errors);

        $errormsgsdetail = array_map(function($log) {
            return $log['detail'];
        }, $errors);
        $str1 = get_string('errorcustomfielddoesnotexist', 'tool_wp', 'Hello1');
        $str2 = get_string('errorcustomfielddoesnotexist', 'tool_wp', 'Hello2');
        $str3 = get_string('errorcustomfielddoesnotexist', 'tool_wp', 'Hello3');
        $str4 = get_string('errorcustomfielddoesnotexist', 'tool_wp', '1');
        $this->assertEqualsCanonicalizing([$str1, $str2, $str3, $str4], $errormsgsdetail);

        $errormsgs = array_map(function($log) {
            return $log['errors'][0];
        }, $errors);
        $str1 = get_string('errorcustomfieldnotfounddetail', 'tool_wp', 'fld1');
        $str2 = get_string('errorcustomfieldnotfounddetail', 'tool_wp', 'fld2');
        $str3 = get_string('errorcustomfieldnotfounddetail', 'tool_wp', 'fld3');
        $str4 = get_string('errorcustomfieldnotfounddetail', 'tool_wp', 'fld4');
        $this->assertEqualsCanonicalizing([$str1, $str2, $str3, $str4], $errormsgs);
    }
}
