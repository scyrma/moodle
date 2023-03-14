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

namespace tool_organisation\tool_dynamicrule\outcome;

use tool_dynamicrule\outcome;
use tool_dynamicrule\rule;
use tool_dynamicrule\tool_wp\exporter\rules as exporter;
use tool_dynamicrule\tool_wp\importer\rules as importer;
use tool_organisation\department_manager;
use tool_organisation\job_manager;
use tool_organisation\position_manager;
use tool_wp\local\exportimport\import_manager;

/**
 * Unit tests for end jobs rule outcome
 *
 * @package     tool_organisation
 * @group       tool_organisation
 * @covers      \tool_organisation\tool_dynamicrule\outcome\end_jobs
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Ode Alba <odei.alba@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class end_jobs_test extends \advanced_testcase {

    /** @var \tool_organisation_generator $orggenerator */
    protected $orggenerator;

    /** @var \tool_dynamicrule_generator $dynamicrulegenerator */
    protected $dynamicrulegenerator;

    /** @var \tool_wp_generator $wpgenerator */
    protected $wpgenerator;

    /** @var job_manager $jobmanager */
    protected $jobmanager;

    /**
     * Test setup
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->orggenerator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
        $this->dynamicrulegenerator = $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        $this->wpgenerator = $this->getDataGenerator()->get_plugin_generator('tool_wp');
        $this->jobmanager = new job_manager();
    }

    /**
     * Test whether user can add outcome
     */
    public function test_user_can_add(): void {
        $this->setAdminUser();
        $this->assertTrue(end_jobs::instance()->user_can_add());

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assertFalse(end_jobs::instance()->user_can_add());
    }

    /**
     * Test whether user can edit outcome
     */
    public function test_user_can_edit(): void {
        $this->setAdminUser();
        $this->assertTrue(end_jobs::instance()->user_can_edit([]));

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assertFalse(end_jobs::instance()->user_can_edit([]));
    }

    /**
     * Test configuration validity
     */
    public function test_is_configuration_valid(): void {
        // Any department and position is affected.
        $ruleany = $this->dynamicrulegenerator->create_rule();
        $outcomeany = end_jobs::create($ruleany->id, [
            'departmentid' => end_jobs::ANY,
            'positionid' => end_jobs::ANY,
        ]);

        $this->assertTrue($outcomeany->is_configuration_valid());

        // Only defined department and position is affected.
        [$position, $department] = $this->orggenerator->create_position_and_department();

        $rule = $this->dynamicrulegenerator->create_rule();
        $outcome = end_jobs::create($rule->id, [
            'departmentid' => $department->id,
            'positionid' => $position->id,
        ]);

        $this->assertTrue($outcome->is_configuration_valid());

        // Delete the department.
        (new department_manager)->delete_department($department->id);
        $this->assertFalse($outcome->is_configuration_valid());
    }

    /**
     * Data provider for {@see test_get_description}
     *
     * @return array
     */
    public function get_description_provider(): array {
        global $CFG;
        $anydepartment = get_string('anydepartment', 'tool_organisation');
        $anyposition = get_string('anyposition', 'tool_organisation');
        $dayexecution = get_string('ruleoutcomeruledate', 'tool_organisation');
        $beforedayexecution = get_string('ruleoutcomedaybeforeruledate', 'tool_organisation');
        $yes = get_string('yes');
        $no = get_string('no');
        $tomorrow = strtotime('tomorrow');
        $tomorrowstr = userdate($tomorrow, '%Y-%m-%d', $CFG->timezone, false, false);
        $targetactive = get_string('ruleoutcomeactive', 'tool_organisation');
        $targetall = get_string('ruleoutcomeall', 'tool_organisation');

        return [
            [[
                'enddate' => end_jobs::END_ABSOLUTE,
                'enddateabsolute' => $tomorrow,
                'includesubdepartments' => true,
                'includesubpositions' => true,
                'target' => end_jobs::ALL_JOBS,
            ], [
                'position' => 'p1',
                'department' => 'd1',
                'includesubdepartments' => $yes,
                'includesubpositions' => $yes,
                'target' => $targetall,
                'enddate' => $tomorrowstr,
            ]],
            [[
                'enddate' => end_jobs::END_DAY_BEFORE_EXECUTION,
                'target' => end_jobs::ACTIVE_JOBS,
                'includesubdepartments' => false,
                'includesubpositions' => false,
            ], [
                'position' => 'p1',
                'department' => 'd1',
                'includesubdepartments' => $no,
                'includesubpositions' => $no,
                'target' => $targetactive,
                'enddate' => $beforedayexecution,
            ]],
            [[
                'useany' => true,
                'enddate' => end_jobs::END_EXECUTION,
                'target' => end_jobs::ALL_JOBS,
                'includesubdepartments' => true,
                'includesubpositions' => false,
            ], [
                'position' => $anydepartment,
                'department' => $anyposition,
                'includesubdepartments' => $yes,
                'includesubpositions' => $no,
                'target' => $targetall,
                'enddate' => $dayexecution,
            ]],
        ];
    }

    /**
     * Test outcome description
     *
     * @param array $ruleconfigdata
     * @param array $expecteddata
     *
     * @dataProvider get_description_provider
     */
    public function test_get_description(array $ruleconfigdata, array $expecteddata): void {
        [$position, $department] = $this->orggenerator->create_position_and_department(['name' => 'p1'], ['name' => 'd1']);

        $departmentid = $department->id;
        $positionid = $position->id;
        if (isset($ruleconfigdata['useany']) && $ruleconfigdata['useany'] === true) {
            $departmentid = end_jobs::ANY;
            $positionid = end_jobs::ANY;
        }

        $rule = $this->dynamicrulegenerator->create_rule();
        $outcome = end_jobs::create($rule->id, [
            'departmentid' => $departmentid,
            'positionid' => $positionid
        ] + $ruleconfigdata);

        $string = get_string('ruleoutcomeendjobsdesc', 'tool_organisation', $expecteddata);
        $this->assertEquals($string, $outcome->get_description());
    }

    /**
     * Data provider for {@see test_apply_to_users}
     *
     * @return array
     */
    public function apply_to_users_provider(): array {
        return [
            // Execution date for enddate.
            [[
                'positionid' => 'p11',
                'departmentid' => 'd11',
                'enddate' => end_jobs::END_EXECUTION,
                'target' => end_jobs::ALL_JOBS,
                'includesubdepartments' => true,
                'includesubpositions' => true,
            ], [
                'positionid' => 'p11',
                'departmentid' => 'd11',
                'startdate' => strtotime('- 3 days'),
            ], strtotime('today')],
            // Day before execution date for enddate.
            [[
                'positionid' => 'p11',
                'departmentid' => 'd11',
                'enddate' => end_jobs::END_DAY_BEFORE_EXECUTION,
                'target' => end_jobs::ALL_JOBS,
                'includesubdepartments' => true,
                'includesubpositions' => true,
            ], [
                'positionid' => 'p11',
                'departmentid' => 'd11',
                'startdate' => strtotime('- 3 days'),
            ], strtotime('yesterday')],
            // Date specified for enddate.
            [[
                'positionid' => 'p11',
                'departmentid' => 'd11',
                'enddate' => end_jobs::END_ABSOLUTE,
                'enddateabsolute' => strtotime('tomorrow'),
                'target' => end_jobs::ALL_JOBS,
                'includesubdepartments' => true,
                'includesubpositions' => true,
            ], [
                'positionid' => 'p11',
                'departmentid' => 'd11',
                'startdate' => strtotime('- 3 days'),
            ], strtotime('tomorrow')],
            // Job starts after defined enddate.
            [[
                'positionid' => 'p11',
                'departmentid' => 'd11',
                'enddate' => end_jobs::END_ABSOLUTE,
                'enddateabsolute' => strtotime('- 4 days'),
                'target' => end_jobs::ALL_JOBS,
                'includesubdepartments' => true,
                'includesubpositions' => true,
            ], [
                'positionid' => 'p11',
                'departmentid' => 'd11',
                'startdate' => strtotime('- 3 days'),
            ], 0],
            // Only active jobs are affected.
            [[
                'positionid' => 'p11',
                'departmentid' => 'd11',
                'enddate' => end_jobs::END_EXECUTION,
                'target' => end_jobs::ACTIVE_JOBS,
                'includesubdepartments' => true,
                'includesubpositions' => true,
            ], [
                'positionid' => 'p11',
                'departmentid' => 'd11',
                'startdate' => strtotime('- 3 days'),
                'enddate' => strtotime('- 2 days'),
            ], strtotime('- 2 days')],
            [[
                'positionid' => 'p11',
                'departmentid' => 'd11',
                'enddate' => end_jobs::END_EXECUTION,
                'target' => end_jobs::ACTIVE_JOBS,
                'includesubdepartments' => true,
                'includesubpositions' => true,
            ], [
                'positionid' => 'p11',
                'departmentid' => 'd11',
                'startdate' => strtotime('- 3 days'),
            ], strtotime('today')],
            // All jobs are affected. ("Execution date for enddate." covers the second part for not ended jobs).
            [[
                'positionid' => 'p11',
                'departmentid' => 'd11',
                'enddate' => end_jobs::END_EXECUTION,
                'target' => end_jobs::ALL_JOBS,
                'includesubdepartments' => true,
                'includesubpositions' => true,
            ], [
                'positionid' => 'p11',
                'departmentid' => 'd11',
                'startdate' => strtotime('- 3 days'),
                'enddate' => strtotime('- 2 days'),
            ], strtotime('today')],
            // Subdepartments and subpositions are included.
            [[
                'positionid' => 'p1',
                'departmentid' => 'd1',
                'enddate' => end_jobs::END_EXECUTION,
                'target' => end_jobs::ALL_JOBS,
                'includesubdepartments' => true,
                'includesubpositions' => true,
            ], [
                'positionid' => 'p11',
                'departmentid' => 'd11',
                'startdate' => strtotime('- 3 days'),
            ], strtotime('today')],
            // Subdepartments and subpositions are not included.
            [[
                'positionid' => 'p1',
                'departmentid' => 'd1',
                'enddate' => end_jobs::END_EXECUTION,
                'target' => end_jobs::ALL_JOBS,
                'includesubdepartments' => false,
                'includesubpositions' => false,
            ], [
                'positionid' => 'p11',
                'departmentid' => 'd11',
                'startdate' => strtotime('- 3 days'),
            ], 0],
            // Any department and any position are affected.
            [[
                'positionid' => end_jobs::ANY,
                'departmentid' => end_jobs::ANY,
                'enddate' => end_jobs::END_EXECUTION,
                'target' => end_jobs::ALL_JOBS,
                'includesubdepartments' => false,
                'includesubpositions' => false,
            ], [
                'positionid' => 'p11',
                'departmentid' => 'd11',
                'startdate' => strtotime('- 3 days'),
            ], strtotime('today')],
        ];
    }

    /**
     * Test applying the outcome to matching users
     *
     * @param array $ruleconfigdata
     * @param array $jobconfigdata
     * @param int $expectedenddate
     *
     * @dataProvider apply_to_users_provider
     */
    public function test_apply_to_users(array $ruleconfigdata, array $jobconfigdata, int $expectedenddate): void {
        $this->setAdminUser();

        // Create departments and positions.
        [$position1, $department1] = $this->orggenerator->create_position_and_department();
        [$position11, $department11] = $this->orggenerator->create_position_and_department(['parentid' => $position1->id],
            ['parentid' => $department1->id]);
        $posdeps = [
            end_jobs::ANY => end_jobs::ANY,
            'd1' => $department1->id,
            'd11' => $department11->id,
            'p1' => $position1->id,
            'p11' => $position11->id,
        ];

        // Set correct department and position ids.
        $ruleconfigdata['departmentid'] = $posdeps[$ruleconfigdata['departmentid']];
        $ruleconfigdata['positionid'] = $posdeps[$ruleconfigdata['positionid']];
        $jobconfigdata['departmentid'] = $posdeps[$jobconfigdata['departmentid']];
        $jobconfigdata['positionid'] = $posdeps[$jobconfigdata['positionid']];

        // Create a user.
        $user = $this->getDataGenerator()->create_user([
            'timecreated' => strtotime('2020-08-01 17:30'),
        ]);

        // Assign job to user.
        $job = $this->jobmanager->create_job((object)(['userid' => $user->id] + $jobconfigdata));

        // Create a new rule.
        $rule = $this->dynamicrulegenerator->create_rule(['enabled' => 1]);
        $this->dynamicrulegenerator->create_condition_alwaystrue($rule->id);
        end_jobs::create($rule->id, $ruleconfigdata);

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule);
        \tool_dynamicrule\api::process_rule($ruleinstance, $user->id);

        // Get updated job.
        $updatedjob = $this->jobmanager->get_job(['id' => $job->get('id')]);

        // Assert the job has he expected end date.
        $this->assertEquals($expectedenddate, $updatedjob->get('enddate'));
    }

    /**
     * Test field mapping during export/import
     */
    public function test_field_mapping(): void {
        $this->setAdminUser();

        [$position, $department] = $this->orggenerator->create_position_and_department();

        $rule = $this->dynamicrulegenerator->create_rule();
        $outcome = end_jobs::create($rule->id, [
            'departmentid' => $department->id,
            'positionid' => $position->id,
        ]);
        $ruleany = $this->dynamicrulegenerator->create_rule();
        $outcomeany = end_jobs::create($ruleany->id, [
            'departmentid' => end_jobs::ANY,
            'positionid' => end_jobs::ANY,
        ]);

        // Export our rule.
        $exportid = $this->wpgenerator->perform_export(exporter::class, [
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

        [$newposition, $newdepartment] = $this->orggenerator->create_position_and_department([
            'name' => $originalpositionname,
        ], [
            'name' => $originaldepartmentname,
        ]);

        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
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

        /** @var end_jobs $outcomeoriginal */
        $outcomeoriginal = end_jobs::instance(0, outcome::get_record(['ruleid' => $rules[0]->get('id')])->to_record());
        $this->assertEquals($originaldepartmentid, $outcomeoriginal->get_departmentid());
        $this->assertEquals($originalpositionid, $outcomeoriginal->get_positionid());
        // Position and department don't exist, so rule is not valid.
        $this->assertFalse($outcomeoriginal->is_configuration_valid());

        /** @var end_jobs $outcomeoriginalany */
        $outcomeoriginalany = end_jobs::instance(0, outcome::get_record(['ruleid' => $rules[1]->get('id')])->to_record());
        $this->assertEquals(end_jobs::ANY, $outcomeoriginalany->get_departmentid());
        $this->assertEquals(end_jobs::ANY, $outcomeoriginalany->get_positionid());
        $this->assertTrue($outcomeoriginalany->is_configuration_valid());

        /** @var end_jobs $outcome */
        $outcome = end_jobs::instance(0, outcome::get_record(['ruleid' => $rules[2]->get('id')])->to_record());
        $this->assertEquals($newdepartment->id, $outcome->get_departmentid());
        $this->assertEquals($newposition->id, $outcome->get_positionid());
        $this->assertTrue($outcome->is_configuration_valid());

        /** @var end_jobs $outcomeany */
        $outcomeany = end_jobs::instance(0, outcome::get_record(['ruleid' => $rules[3]->get('id')])->to_record());
        $this->assertEquals(end_jobs::ANY, $outcomeany->get_departmentid());
        $this->assertEquals(end_jobs::ANY, $outcomeany->get_positionid());
        $this->assertTrue($outcomeany->is_configuration_valid());
    }
}
