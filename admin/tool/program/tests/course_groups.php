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
 * tool_program_course_groups_testcase
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Course group allocations tests.
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_course_groups_testcase extends advanced_testcase {
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
    public function setUp() {
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
        $program = $this->generator->generate_program_with_base_set((object)$programdata);
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
    protected function create_tenant_with_user(array $tenantdata = []) {
        $tenant = $this->tenantgenerator->create_tenant($tenantdata);
        $user = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        return [$tenant, $user];
    }

    /**
     * User will be enrolled in a tenant group in the shared course and without group in a tenant course
     */
    public function test_enrol_in_groups() {
        self::setAdminUser();
        $misccat = core_course_category::get_default();
        $coursecat = self::getDataGenerator()->create_category([]);
        list($tenant, $user) = $this->create_tenant_with_user(['categoryid' => $coursecat->id]);
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
    public function test_enrol_in_groups_per_program() {
        self::setAdminUser();
        $misccat = core_course_category::get_default();
        $coursecat = self::getDataGenerator()->create_category([]);
        list($tenant, $user) = $this->create_tenant_with_user(['categoryid' => $coursecat->id]);
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
    public function test_enrol_in_groups_per_certification() {
        self::setAdminUser();
        $misccat = core_course_category::get_default();
        $coursecat = self::getDataGenerator()->create_category([]);
        list($tenant, $user) = $this->create_tenant_with_user(['categoryid' => $coursecat->id]);
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

}
