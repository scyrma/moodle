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
 * Class containing tests for the implementation of the assign job rule outcome
 *
 * @package     tool_organisation
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\tool_dynamicrule\outcome;

use tool_dynamicrule\outcome;
use tool_dynamicrule\rule;
use tool_dynamicrule\tool_dynamicrule\condition\user_profile_field;
use tool_dynamicrule\tool_wp\exporter\rules as exporter;
use tool_dynamicrule\tool_wp\importer\rules as importer;
use tool_organisation\department_manager;
use tool_organisation\helper;
use tool_organisation\organisation;
use tool_organisation\position_manager;
use tool_wp\local\exportimport\import_manager;

/**
 * Unit tests for assign job rule outcome
 *
 * @package     tool_organisation
 * @group       tool_organisation
 * @covers      \tool_organisation\tool_dynamicrule\outcome\assign_job
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class rule_outcome_assign_job_test extends \advanced_testcase {

    /**
     * Test setup
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test whether user can add outcome
     */
    public function test_user_can_add(): void {
        $this->setAdminUser();
        $this->assertTrue(assign_job::instance()->user_can_add());

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assertFalse(assign_job::instance()->user_can_add());
    }

    /**
     * Test whether user can edit outcome
     */
    public function test_user_can_edit(): void {
        $this->setAdminUser();
        $this->assertTrue(assign_job::instance()->user_can_edit([]));

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assertFalse(assign_job::instance()->user_can_edit([]));
    }

    /**
     * Data provider for {@see test_validate_config_form}
     *
     * @return array
     */
    public function validate_config_form_provider(): array {
        return [
            [[
                'startdate' => assign_job::START_ABSOLUTE,
                'startdateabsolute' => strtotime('2020-11-01'),
                'enddate' => assign_job::END_RELATIVE,
                'enddaterelative' => '1 weeks',
            ], true],
            [[
                'startdate' => assign_job::START_ABSOLUTE,
                'startdateabsolute' => strtotime('2020-11-01'),
                'enddate' => assign_job::END_ABSOLUTE,
                'enddateabsolute' => strtotime('2021-04-01'),
            ], true],
            [[
                'startdate' => assign_job::START_ABSOLUTE,
                'startdateabsolute' => strtotime('2020-11-01'),
                'enddate' => assign_job::END_RELATIVE,
                'enddaterelative' => '0 weeks',
            ], false],
            [[
                'startdate' => assign_job::START_ABSOLUTE,
                'startdateabsolute' => strtotime('2020-11-01'),
                'enddate' => assign_job::END_ABSOLUTE,
                'enddateabsolute' => strtotime('2020-09-01'),
            ], false],
        ];
    }

    /**
     * Test config form validation of start/end dates
     *
     * @param array $data
     * @param bool $expectsuccess
     *
     * @dataProvider validate_config_form_provider
     */
    public function test_validate_config_form(array $data, bool $expectsuccess): void {
        $errors = assign_job::instance()->validate_config_form($data);

        if ($expectsuccess) {
            $this->assertEmpty($errors);
        } else {
            $this->assertArrayHasKey('enddategroup', $errors);
        }
    }

    /**
     * Test outcome description
     */
    public function test_get_description(): void {
        [$position, $department] = $this->get_plugin_generator()->create_position_and_department();

        $rule = $this->get_rule_generator()->create_rule();
        $outcome = assign_job::create($rule->id, [
            'departmentid' => $department->id,
            'positionid' => $position->id,
        ]);

        $this->assertEquals("Assign job in department '{$department->name}' with position '{$position->name}'",
            $outcome->get_description());
    }

    /**
     * Test configutation validity
     */
    public function test_is_configuration_valid(): void {
        [$position, $department] = $this->get_plugin_generator()->create_position_and_department();

        $rule = $this->get_rule_generator()->create_rule();
        $outcome = assign_job::create($rule->id, [
            'departmentid' => $department->id,
            'positionid' => $position->id,
        ]);

        $this->assertTrue($outcome->is_configuration_valid());

        // Delete the department.
        (new department_manager)->delete_department($department->id);
        $this->assertFalse($outcome->is_configuration_valid());
    }

    /**
     * Data provider for {@see test_apply_to_users}
     *
     * @return array
     */
    public function apply_to_users_provider(): array {
        return [
            [[
                'startdate' => assign_job::START_EXECUTION,
                'enddate' => assign_job::END_NONE,
            ], null, 0],
            [[
                'startdate' => assign_job::START_USER_CREATION,
                'enddate' => assign_job::END_NONE,
            ], strtotime('2020-08-01'), 0],
            [[
                'startdate' => assign_job::START_ABSOLUTE,
                'startdateabsolute' => strtotime('2021-11-01'),
                'enddate' => assign_job::END_NONE,
            ], strtotime('2021-11-01'), 0],
            [[
                'startdate' => assign_job::START_ABSOLUTE,
                'startdateabsolute' => strtotime('2021-11-01'),
                'enddate' => assign_job::END_RELATIVE,
                'enddaterelative' => '1 weeks',
            ], strtotime('2021-11-01'), strtotime('2021-11-08')],
            [[
                'startdate' => assign_job::START_ABSOLUTE,
                'startdateabsolute' => strtotime('2021-11-01'),
                'enddate' => assign_job::END_ABSOLUTE,
                'enddateabsolute' => strtotime('2022-04-01'),
            ], strtotime('2021-11-01'), strtotime('2022-04-01')],
        ];
    }

    /**
     * Test applying the outcome to matching users
     *
     * @param array $configdata
     * @param int|null $expectedstartdate expected startdate (or null if it should be "now")
     * @param int $expectedenddate
     *
     * @dataProvider apply_to_users_provider
     */
    public function test_apply_to_users(array $configdata, ?int $expectedstartdate, int $expectedenddate): void {
        [$position, $department] = $this->get_plugin_generator()->create_position_and_department();

        $rule = $this->get_rule_generator()->create_rule(['enabled' => 1]);
        $this->get_rule_generator()->create_condition_alwaystrue($rule->id);

        $outcome = assign_job::create($rule->id, $configdata + [
            'departmentid' => $department->id,
            'positionid' => $position->id,
        ]);

        $user = $this->getDataGenerator()->create_user([
            'timecreated' => strtotime('2020-08-01 17:30'),
        ]);

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Assert user has the new job.
        $jobs = organisation::get_user_with_jobs($user->id, null)->get_jobs();
        $this->assertCount(1, $jobs);

        $job = reset($jobs);
        $this->assertEquals($department->id, $job->get('departmentid'));
        $this->assertEquals($position->id, $job->get('positionid'));
        $this->assertEquals($expectedstartdate ?? helper::round_time(time()), $job->get('startdate'));
        $this->assertEquals($expectedenddate, $job->get('enddate'));
    }

    public function test_apply_to_user_in_other_tenant() {
        global $DB;
        $this->resetAfterTest();
        $tenant = $this->get_tenant_generator()->create_tenant();
        [$position, $department] = $this->get_plugin_generator()->create_position_and_department(
            ['tenantid' => $tenant->id], ['tenantid' => $tenant->id]
        );

        // Create rule with condition "user city == Perth" and outcome "assign job".
        $rule = $this->get_rule_generator()->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        user_profile_field::create($rule->id, ['userprofilefield' => 'city', 'city_value' => 'Perth',
            'city_op' => user_profile_field::TEXT_IS_EQUAL_TO]);
        assign_job::create($rule->id, ['departmentid' => $department->id, 'positionid' => $position->id,
            'startdate' => 0, 'enddate' => 0]);

        // Create a user without city - no matches, no jobs.
        $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id]);
        $this->assertEmpty($DB->get_records('tool_organisation_job'));
        $this->assertEmpty($DB->get_records('tool_dynamicrule_match'));

        // Create a user with city=Perth - user is assigned a job, there is a match in the rule.
        $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'city' => 'Perth']);
        $this->assertNotEmpty($DB->get_records('tool_dynamicrule_match'));
        $this->assertNotEmpty($DB->get_records('tool_organisation_job'));
    }

    /**
     * Test field mapping during export/import
     */
    public function test_field_mapping(): void {
        $this->setAdminUser();

        [$position, $department] = $this->get_plugin_generator()->create_position_and_department();

        $rule = $this->get_rule_generator()->create_rule();
        $outcome = assign_job::create($rule->id, [
            'departmentid' => $department->id,
            'positionid' => $position->id,
        ]);

        // Export our rule.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_CONTENT => 1,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
        ]);

        // Now delete the original department/position, and create a new ones with the same name.
        $originaldepartmentid = $department->id;
        $originaldepartmentname = $department->name;
        (new department_manager())->delete_department($originaldepartmentid);

        $originalpositionid = $position->id;
        $originalpositionname = $position->name;
        (new position_manager())->delete_position($originalpositionid);

        [$newposition, $newdepartment] = $this->get_plugin_generator()->create_position_and_department([
            'name' => $originalpositionname,
        ], [
            'name' => $originaldepartmentname,
        ]);

        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, [
            importer::IMPORT_CONTENT => 1,
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        // Confirm the field mapping data was added.
        $importmanager = new import_manager($importid);

        $departmentmappingdata = $importmanager->get_raw_mapping_from_workplace_export_file('tool_organisation_department',
            $originaldepartmentid);
        $this->assertIsArray($departmentmappingdata);
        $this->assertEquals($originaldepartmentid, $departmentmappingdata['id']);

        $positionmappingdata = $importmanager->get_raw_mapping_from_workplace_export_file('tool_organisation_position',
            $originalpositionid);
        $this->assertIsArray($positionmappingdata);
        $this->assertEquals($originalpositionid, $positionmappingdata['id']);

        // The imported outcome field should be mapped to the new department/position.
        $rules = rule::get_records([], 'id');

        /** @var assign_job $outcome */
        $outcome = assign_job::instance(0, outcome::get_record(['ruleid' => end($rules)->get('id')])->to_record());
        $this->assertEquals($newdepartment->id, $outcome->get_departmentid());
        $this->assertEquals($newposition->id, $outcome->get_positionid());

        $this->assertTrue($outcome->is_configuration_valid());
    }

    /**
     * Return organisation generator
     *
     * @return \tool_organisation_generator
     */
    private function get_plugin_generator(): \tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }

    /**
     * Return rule generator
     *
     * @return \tool_dynamicrule_generator
     */
    private function get_rule_generator(): \tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Return workplace generator
     *
     * @return \tool_wp_generator
     */
    private function get_workplace_generator(): \tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }

    /**
     * Return tenant generator
     *
     * @return \tool_tenant_generator
     */
    private function get_tenant_generator(): \tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

}
