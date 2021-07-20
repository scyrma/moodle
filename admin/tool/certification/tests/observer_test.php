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
 * Tests for observer
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for observer
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_observer_testcase extends advanced_testcase {
    /** @var tool_certification_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var tool_program_generator */
    protected $programgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->resetAfterTest();
    }

    public function test_tenant_user_updated() {
        global $DB;
        self::setAdminUser();

        // We retrieve default tenant.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        [$tenant2, [$user1, $user2, $user3, $user4]] = $this->tenantgenerator->create_tenant_and_users(4);

        $program = $this->programgenerator->generate_program_with_course((object)['tenantid' => $tenant2->id]);
        $certification = $this->generator->generate_certification([
            'archived' => 0,
            'tenantid' => $tenant2->id,
            'program' => $program->get('id'),
        ]);
        $this->generator->allocate_user($user1->id, $certification->get('id'));
        $this->generator->allocate_user($user2->id, $certification->get('id'));
        $this->generator->allocate_user($user3->id, $certification->get('id'));
        $this->generator->allocate_user($user4->id, $certification->get('id'));

        $allocations = $DB->get_records('tool_certification_users', ['certificationid' => $certification->get('id')]);
        $this->assertCount(4, $allocations);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id, $user4->id], array_column($allocations, 'userid'));

        $allocations = $DB->get_records('tool_program_users', ['programid' => $program->get('id')]);
        $this->assertCount(4, $allocations);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id, $user4->id], array_column($allocations, 'userid'));

        // Testing that tenant_user_updated event observer is called.
        $manager = new \tool_tenant\manager();
        $manager->allocate_user($user2->id, $defaulttenantid, 'tool_certification', '');
        $manager->allocate_user($user3->id, $defaulttenantid, 'tool_certification', '');

        $allocations = $DB->get_records('tool_certification_users', ['certificationid' => $certification->get('id')]);
        $this->assertCount(2, $allocations);
        $this->assertEqualsCanonicalizing([$user1->id, $user4->id], array_column($allocations, 'userid'));

        $allocations = $DB->get_records('tool_program_users', ['programid' => $program->get('id')]);
        $this->assertCount(2, $allocations);
        $this->assertEqualsCanonicalizing([$user1->id, $user4->id], array_column($allocations, 'userid'));
    }

    /**
     * Updates the grade of a user in the given assign module instance.
     *
     * @param stdClass $assignrow Assignment row from database
     * @param int $userid User id
     * @param float $grade Grade
     */
    protected function set_grade_in_course(stdClass $assignrow, $userid, $grade) {
        $grades = [];
        $grades[$userid] = (object)[
            'rawgrade' => $grade, 'userid' => $userid
        ];
        $assignrow->cmidnumber = null;
        return assign_grade_item_update($assignrow, $grades);
    }

    public function test_tenant_user_updated_shared_certification() {
        global $DB;
        self::setAdminUser();

        // We retrieve default tenant.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        [$tenant2, [$user1, $user2, $user3, $user4]] = $this->tenantgenerator->create_tenant_and_users(4, ['name' => 'T2']);
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();

        $courseshared = self::getDataGenerator()->create_course();
        $assignrow = $this->getDataGenerator()->create_module('assign', ['course' => $courseshared->id]);
        $program = $this->programgenerator->generate_program((object)['tenantid' => $sharedspaceid]);
        \tool_program\api::add_course_to_base_set($program->get('id'), $courseshared->id);
        $certification = $this->generator->generate_certification([
            'fullname' => 'C1',
            'tenantid' => $sharedspaceid,
            'program' => $program->get('id'),
            'autocreategroups' => \tool_program\api::GROUPS_CERTIFICATION + \tool_program\api::GROUPS_TENANT,
        ]);
        $this->generator->allocate_user($user1->id, $certification->get('id'));
        $this->generator->allocate_user($user2->id, $certification->get('id'));
        $this->generator->allocate_user($user3->id, $certification->get('id'));
        $this->generator->allocate_user($user4->id, $certification->get('id'));
        // User3 is enrolled in a course and has a grade.
        \tool_program\api::enrol_in_program_course($program->get('id'), $courseshared, $user3->id);
        $this->set_grade_in_course($assignrow, $user3->id, 50);

        // 4 users are allocated to certifications and programs in them.
        $allocations = $DB->get_records('tool_certification_users', ['certificationid' => $certification->get('id')]);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id, $user4->id], array_column($allocations, 'userid'));
        $allocations = $DB->get_records('tool_program_users', ['programid' => $program->get('id')]);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id, $user4->id], array_column($allocations, 'userid'));

        // User3 now belongs to the group for the tenant T2.
        $usergroups = groups_get_all_groups($courseshared->id, $user3->id);
        $this->assertCount(1, $usergroups);
        $this->assertEquals('T2 - C1', (reset($usergroups))->name);

        // Move two users (user2 and user3) to default tenant.
        $manager = new \tool_tenant\manager();
        $manager->allocate_user($user2->id, $defaulttenantid, 'tool_certification', '');
        $manager->allocate_user($user3->id, $defaulttenantid, 'tool_certification', '');

        // Still all 4 users are allocated to certification and program.
        $allocations = $DB->get_records('tool_certification_users', ['certificationid' => $certification->get('id')]);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id, $user4->id], array_column($allocations, 'userid'));
        $allocations = $DB->get_records('tool_program_users', ['programid' => $program->get('id')]);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id, $user4->id], array_column($allocations, 'userid'));

        // User3 is still enrolled. Grades are preserved. Groups are changed.
        $gradinginfo = grade_get_grades($courseshared->id, 'mod', 'assign', $assignrow->id, $user3->id);
        $this->assertEquals(50, $gradinginfo->items[0]->grades[$user3->id]->grade);
        $usergroups = groups_get_all_groups($courseshared->id, $user3->id);
        $this->assertCount(1, $usergroups);
        $this->assertEquals('Default tenant - C1', (reset($usergroups))->name);
    }
}
