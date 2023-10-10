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
 * tool_program_course_groups_testcase
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program;

use advanced_testcase;
use context_course;
use core_collator;
use core_course_category;
use stdClass;
use tool_program_generator;
use tool_tenant_generator;
use tool_certification_generator;

/**
 * Course group allocations tests.
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_groups_test extends advanced_testcase {
    /**
     * @var tool_program_generator
     */
    protected $generator;
    /**
     * @var tool_certification_generator
     */
    protected $certificationgenerator;
    /**
     * @var tool_tenant_generator
     */
    protected $tenantgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    /**
     * Create a program and add courses to it
     *
     * @param array $programdata
     * @param array $courses
     * @return \tool_program\persistent\program
     */
    protected function create_program_with_courses(array $programdata, array $courses): \tool_program\persistent\program {
        $program = $this->generator->generate_program((object)$programdata);
        foreach ($courses as $course) {
            \tool_program\api::add_course_to_base_set($program->get('id'), $course->id);
        }
        return $program;
    }

    /**
     * Create a tenant with a user
     *
     * @param array $tenantdata
     * @return array
     */
    protected function create_tenant_with_user(array $tenantdata = []): array {
        $tenant = $this->tenantgenerator->create_tenant($tenantdata);
        $user = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        return [$tenant, $user];
    }

    /**
     * User will be enrolled in a tenant group in the shared course and without group in a tenant course
     */
    public function test_enrol_in_groups(): void {
        self::setAdminUser();
        $misccat = core_course_category::get_default();
        $coursecat = self::getDataGenerator()->create_category([]);
        [$tenant, $user] = $this->create_tenant_with_user(['categoryid' => $coursecat->id]);
        $courseshared = self::getDataGenerator()->create_course(['category' => $misccat->id]);
        $coursetenant = self::getDataGenerator()->create_course(['category' => $coursecat->id]);

        $program = $this->create_program_with_courses(['tenantid' => $tenant->id], [$courseshared, $coursetenant]);
        $this->generator->allocate_user_to_program($program->get('id'), $user->id);

        \tool_program\api::enrol_in_program_course($program->get('id'), $coursetenant, $user->id);
        \tool_program\api::enrol_in_program_course($program->get('id'), $courseshared, $user->id);

        $groups = groups_get_all_groups($coursetenant->id);
        $this->assertEmpty($groups);

        $groups = groups_get_all_groups($courseshared->id);
        $this->assertEquals(1, count($groups));
        $group = reset($groups);
        $this->assertEquals($tenant->name, $group->name);
        $this->assertEquals([[$group->id]], groups_get_user_groups($courseshared->id, $user->id));
    }

    /**
     * User will be enrolled in a tenant group in the shared course and without group in a tenant course
     */
    public function test_enrol_in_groups_shared_space(): void {
        self::setAdminUser();

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant, [$user11, $user12, $user13]] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22, $user23]] = $this->tenantgenerator->create_tenant_and_users(3);

        $misccat = core_course_category::get_default();
        $courseshared = self::getDataGenerator()->create_course(['category' => $misccat->id]);

        $program = $this->create_program_with_courses(['tenantid' => $sharedspaceid], [$courseshared]);
        $this->generator->allocate_users_to_program($program->get('id'), [$user11->id, $user12->id, $user21->id]);

        \tool_program\api::enrol_in_program_course($program->get('id'), $courseshared, $user11->id);
        \tool_program\api::enrol_in_program_course($program->get('id'), $courseshared, $user12->id);
        \tool_program\api::enrol_in_program_course($program->get('id'), $courseshared, $user21->id);

        $groups = groups_get_all_groups($courseshared->id);
        core_collator::asort_objects_by_property($groups, 'name');
        $this->assertEquals(2, count($groups));
        $group = reset($groups);
        $this->assertEquals($tenant->name, $group->name);
        $this->assertEquals([[$group->id]], groups_get_user_groups($courseshared->id, $user11->id));
        $this->assertEquals([[$group->id]], groups_get_user_groups($courseshared->id, $user12->id));
        $group = next($groups);
        $this->assertEquals($tenant2->name, $group->name);
        $this->assertEquals([[$group->id]], groups_get_user_groups($courseshared->id, $user21->id));
    }

    /**
     * User will be enrolled in a tenant group in the shared course and without group in a tenant course
     */
    public function test_enrol_in_groups_per_program(): void {
        self::setAdminUser();
        $misccat = core_course_category::get_default();
        $coursecat = self::getDataGenerator()->create_category([]);
        [$tenant, $user] = $this->create_tenant_with_user(['categoryid' => $coursecat->id]);
        $courseshared = self::getDataGenerator()->create_course(['category' => $misccat->id]);
        $coursetenant = self::getDataGenerator()->create_course(['category' => $coursecat->id]);

        $program = $this->create_program_with_courses(['tenantid' => $tenant->id,
            'autocreategroups' => \tool_program\api::GROUPS_PROGRAM + \tool_program\api::GROUPS_TENANT],
            [$courseshared, $coursetenant]);
        $this->generator->allocate_user_to_program($program->get('id'), $user->id);

        \tool_program\api::enrol_in_program_course($program->get('id'), $coursetenant, $user->id);
        \tool_program\api::enrol_in_program_course($program->get('id'), $courseshared, $user->id);

        $groups = groups_get_all_groups($coursetenant->id);
        $this->assertEquals(1, count($groups));
        $group = reset($groups);
        $this->assertEquals($program->get('fullname'), $group->name);
        $this->assertEquals([[$group->id]], groups_get_user_groups($coursetenant->id, $user->id));

        $groups = groups_get_all_groups($courseshared->id);
        $this->assertEquals(1, count($groups));
        $group = reset($groups);
        $this->assertEquals($tenant->name . ' - ' . $program->get('fullname'), $group->name);
        $this->assertEquals([[$group->id]], groups_get_user_groups($courseshared->id, $user->id));
    }

    /**
     * User will be enrolled in a tenant group in the shared course and without group in a tenant course
     */
    public function test_enrol_in_groups_per_program_shared_space(): void {
        self::setAdminUser();

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant, [$user11, $user12, $user13]] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22, $user23]] = $this->tenantgenerator->create_tenant_and_users(3);

        $misccat = core_course_category::get_default();
        $courseshared = self::getDataGenerator()->create_course(['category' => $misccat->id]);

        $program = $this->create_program_with_courses(['tenantid' => $sharedspaceid,
            'autocreategroups' => \tool_program\api::GROUPS_PROGRAM + \tool_program\api::GROUPS_TENANT], [$courseshared]);
        $this->generator->allocate_users_to_program($program->get('id'), [$user11->id, $user12->id, $user21->id]);

        \tool_program\api::enrol_in_program_course($program->get('id'), $courseshared, $user11->id);
        \tool_program\api::enrol_in_program_course($program->get('id'), $courseshared, $user12->id);
        \tool_program\api::enrol_in_program_course($program->get('id'), $courseshared, $user21->id);

        $groups = groups_get_all_groups($courseshared->id);
        core_collator::asort_objects_by_property($groups, 'name');
        $this->assertEquals(2, count($groups));
        $group = reset($groups);
        $this->assertEquals($tenant->name . ' - ' . $program->get('fullname'), $group->name);
        $this->assertEquals([[$group->id]], groups_get_user_groups($courseshared->id, $user11->id));
        $this->assertEquals([[$group->id]], groups_get_user_groups($courseshared->id, $user12->id));
        $group = next($groups);
        $this->assertEquals($tenant2->name . ' - ' . $program->get('fullname'), $group->name);
        $this->assertEquals([[$group->id]], groups_get_user_groups($courseshared->id, $user21->id));
    }

    /**
     * User will be enrolled in a tenant group in the shared course and without group in a tenant course
     */
    public function test_enrol_in_groups_per_certification(): void {
        self::setAdminUser();
        $misccat = core_course_category::get_default();
        $coursecat = self::getDataGenerator()->create_category([]);
        [$tenant, $user] = $this->create_tenant_with_user(['categoryid' => $coursecat->id]);
        $courseshared = self::getDataGenerator()->create_course(['category' => $misccat->id]);
        $coursetenant = self::getDataGenerator()->create_course(['category' => $coursecat->id]);

        $program = $this->create_program_with_courses(['tenantid' => $tenant->id],
            [$courseshared, $coursetenant]);
        $certification1 = $this->certificationgenerator->generate_certification(['tenantid' => $tenant->id,
            'autocreategroups' => \tool_program\api::GROUPS_CERTIFICATION + \tool_program\api::GROUPS_TENANT,
            'program' => $program->get('id')]);
        $this->certificationgenerator->allocate_user($user->id, $certification1->get('id'));

        \tool_program\api::enrol_in_program_course($program->get('id'), $coursetenant, $user->id);
        \tool_program\api::enrol_in_program_course($program->get('id'), $courseshared, $user->id);

        $groups = groups_get_all_groups($coursetenant->id);
        $this->assertEquals(1, count($groups));
        $group = reset($groups);
        $this->assertEquals($certification1->get('fullname'), $group->name);
        $this->assertEquals([[$group->id]], groups_get_user_groups($coursetenant->id, $user->id));

        $groups = groups_get_all_groups($courseshared->id);
        $this->assertEquals(1, count($groups));
        $group = reset($groups);
        $this->assertEquals($tenant->name . ' - ' . $certification1->get('fullname'), $group->name);
        $this->assertEquals([[$group->id]], groups_get_user_groups($courseshared->id, $user->id));
    }

    /**
     * Updates the grade of a user in the given assign module instance.
     *
     * @param stdClass $assignrow Assignment row from database
     * @param int $userid User id
     * @param float $grade Grade
     * @return int
     */
    protected function set_grade_in_course(stdClass $assignrow, int $userid, float $grade): int {
        $grades = [];
        $grades[$userid] = (object)[
            'rawgrade' => $grade, 'userid' => $userid
        ];
        $assignrow->cmidnumber = null;
        return assign_grade_item_update($assignrow, $grades);
    }

    /**
     * User enrolled in a tenant+program group in a shared program and moved to another tenant
     */
    public function test_enrol_in_program_groups_move_tenant(): void {
        self::setAdminUser();

        // Create two tenants with users, shared program with a course.
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant, [$user11, $user12]] = $this->tenantgenerator->create_tenant_and_users(2, ['name' => 'T1']);
        [$tenant2, [$user21]] = $this->tenantgenerator->create_tenant_and_users(1, ['name' => 'T2']);

        $misccat = core_course_category::get_default();
        $courseshared = self::getDataGenerator()->create_course(['category' => $misccat->id]);
        $assignrow = $this->getDataGenerator()->create_module('assign', ['course' => $courseshared->id]);

        $program = $this->create_program_with_courses(['tenantid' => $sharedspaceid,
            'autocreategroups' => \tool_program\api::GROUPS_PROGRAM + \tool_program\api::GROUPS_TENANT,
            'fullname' => 'PS'], [$courseshared]);
        $this->generator->allocate_users_to_program($program->get('id'), [$user11->id, $user12->id, $user21->id]);

        // Enrol a user into the shared courses, add grades for user12.
        \tool_program\api::enrol_in_program_course($program->get('id'), $courseshared, $user11->id);
        \tool_program\api::enrol_in_program_course($program->get('id'), $courseshared, $user12->id);
        $this->set_grade_in_course($assignrow, $user12->id, 50);

        // There is only one group now (for the first tenant) and users are allocated to it.
        $groups = groups_get_all_groups($courseshared->id);
        core_collator::asort_objects_by_property($groups, 'name');
        [$group1] = array_values($groups);
        $this->assertEquals('T1 - PS', $group1->name);
        $this->assertEquals([[$group1->id]], groups_get_user_groups($courseshared->id, $user11->id));
        $this->assertEquals([[$group1->id]], groups_get_user_groups($courseshared->id, $user12->id));

        // Move one user to another tenant. Now another group is created and he is moved to another group.
        // He is still enrolled. Grades are preserved.
        $this->tenantgenerator->allocate_user($user12->id, $tenant2->id);
        $groups = groups_get_all_groups($courseshared->id);
        core_collator::asort_objects_by_property($groups, 'name');
        [$group1, $group2] = array_values($groups);
        $this->assertEquals('T1 - PS', $group1->name);
        $this->assertEquals('T2 - PS', $group2->name);
        $this->assertEquals([[$group2->id]], groups_get_user_groups($courseshared->id, $user12->id));
        $this->assertTrue(is_enrolled(context_course::instance($courseshared->id), $user12->id));
        $gradinginfo = grade_get_grades($courseshared->id, 'mod', 'assign', $assignrow->id, $user12->id);
        $this->assertEquals(50, $gradinginfo->items[0]->grades[$user12->id]->grade);
    }

    /**
     * User enrolled in a tenant group in a shared program and moved to another tenant
     */
    public function test_enrol_in_tenant_groups_move_tenant(): void {
        self::setAdminUser();

        // Create two tenants with users, shared program with a course.
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant, [$user11, $user12]] = $this->tenantgenerator->create_tenant_and_users(2, ['name' => 'T1']);
        [$tenant2, [$user21]] = $this->tenantgenerator->create_tenant_and_users(1, ['name' => 'T2']);

        $misccat = core_course_category::get_default();
        $courseshared = self::getDataGenerator()->create_course(['category' => $misccat->id]);
        $assignrow = $this->getDataGenerator()->create_module('assign', ['course' => $courseshared->id]);

        // The difference from the previous test is that this program has groups for tenant only (not for program).
        $program = $this->create_program_with_courses(['tenantid' => $sharedspaceid], [$courseshared]);
        $this->generator->allocate_users_to_program($program->get('id'), [$user11->id, $user12->id, $user21->id]);

        // Enrol users from first tenant into the shared courses, add grades for user12.
        \tool_program\api::enrol_in_program_course($program->get('id'), $courseshared, $user11->id);
        \tool_program\api::enrol_in_program_course($program->get('id'), $courseshared, $user12->id);
        $this->set_grade_in_course($assignrow, $user12->id, 50);

        // There is only one group now (for the first tenant) and users are allocated to it.
        $groups = groups_get_all_groups($courseshared->id);
        [$group1] = array_values($groups);
        $this->assertEquals('T1', $group1->name);
        $this->assertEquals([[$group1->id]], groups_get_user_groups($courseshared->id, $user11->id));
        $this->assertEquals([[$group1->id]], groups_get_user_groups($courseshared->id, $user12->id));

        // Move one user to another tenant. Now another group is created and he is moved to another group.
        // He is still enrolled. Grades are preserved.
        $this->tenantgenerator->allocate_user($user12->id, $tenant2->id);
        $groups = groups_get_all_groups($courseshared->id);
        core_collator::asort_objects_by_property($groups, 'name');
        [$group1, $group2] = array_values($groups);
        $this->assertEquals('T1', $group1->name);
        $this->assertEquals('T2', $group2->name);
        $this->assertEquals([[$group2->id]], groups_get_user_groups($courseshared->id, $user12->id));
        $this->assertTrue(is_enrolled(context_course::instance($courseshared->id), $user12->id));
        $gradinginfo = grade_get_grades($courseshared->id, 'mod', 'assign', $assignrow->id, $user12->id);
        $this->assertEquals(50, $gradinginfo->items[0]->grades[$user12->id]->grade);
    }
}
