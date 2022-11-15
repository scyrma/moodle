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

namespace tool_program;

use advanced_testcase;
use tool_program_generator;
use tool_tenant_generator;

/**
 * Test for tenants and program completions
 *
 * @package   tool_program
 * @covers    \tool_program\api
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tenants_test extends advanced_testcase {
    /** @var tool_program_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    /**
     * Test complete program with active program.
     */
    public function test_complete_program(): void {
        global $DB;

        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);

        $course1 = $this->generator->generate_course_with_completion_self();
        $program = $this->generator->generate_program((object)['tenantid' => $tenant->id]);
        $baseset = $program->get_base_set();
        $programcourse = $this->generator->add_course_to_set($course1->id, $baseset->get('id'));
        $programuser1 = $this->generator->allocate_user_to_program($program->get('id'), $user1->id);
        $programuser2 = $this->generator->allocate_user_to_program($program->get('id'), $user2->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1);

        // As a user complete the course.
        $this->generator->complete_courses([$course1->id], $user1->id);

        // Make sure program is marked as completed for this user.
        $this->assertTrue($DB->record_exists('tool_program_set_completion',
            ['setid' => $baseset->get('id'), 'userid' => $user1->id]));
        // Make sure program is NOT marked as completed for this user.
        $this->assertFalse($DB->record_exists('tool_program_set_completion',
            ['setid' => $baseset->get('id'), 'userid' => $user2->id]));
    }

    /**
     * Test complete a shared program with active program.
     */
    public function test_complete_shared_program(): void {
        global $DB;

        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();

        $program = $this->generator->generate_program_with_course((object)['tenantid' => $sharedtenantid]);
        $baseset = $program->get_base_set();
        $this->generator->allocate_user_to_program($program->get('id'), $user1->id);
        $this->generator->allocate_user_to_program($program->get('id'), $user2->id);

        // As a user complete the course.
        $this->generator->complete_program($program, $user1->id);

        // Make sure program is marked as completed for this user.
        $this->assertTrue($DB->record_exists('tool_program_set_completion',
            ['setid' => $baseset->get('id'), 'userid' => $user1->id]));
        // Make sure program is NOT marked as completed for this user.
        $this->assertFalse($DB->record_exists('tool_program_set_completion',
            ['setid' => $baseset->get('id'), 'userid' => $user2->id]));
    }

    /**
     * Test complete program with archived program.
     */
    public function test_complete_program_archive_program(): void {
        global $DB;

        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);

        $course1 = $this->generator->generate_course_with_completion_self();
        $program = $this->generator->generate_program((object)['tenantid' => $tenant->id]);
        $baseset = $program->get_base_set();
        $programcourse = $this->generator->add_course_to_set($course1->id, $baseset->get('id'));
        $programuser1 = $this->generator->allocate_user_to_program($program->get('id'), $user1->id);
        $programuser2 = $this->generator->allocate_user_to_program($program->get('id'), $user2->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1);

        // Archive the program.
        api::archive_program($program);

        // As a user complete the course.
        $this->generator->complete_courses([$course1->id], $user1->id);

        // Make sure program is NOT marked as completed for this user.
        $this->assertFalse($DB->record_exists('tool_program_set_completion',
            ['setid' => $baseset->get('id'), 'userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('tool_program_set_completion',
            ['setid' => $baseset->get('id'), 'userid' => $user2->id]));
    }

    /**
     * Test complete program with archived tenant.
     */
    public function test_complete_program_archive_tenant(): void {
        global $DB;

        [$tenant, [$user1]] = $this->tenantgenerator->create_tenant_and_users(1);
        [$tenant2, [$user2]] = $this->tenantgenerator->create_tenant_and_users(1);

        $course1 = $this->generator->generate_course_with_completion_self();
        $program1 = $this->generator->generate_program((object)['tenantid' => $tenant->id]);
        $program2 = $this->generator->generate_program((object)['tenantid' => $tenant2->id]);
        $baseset = $program1->get_base_set();
        $programcourse = $this->generator->add_course_to_set($course1->id, $baseset->get('id'));
        $baseset2 = $program2->get_base_set();
        $programcourse2 = $this->generator->add_course_to_set($course1->id, $baseset2->get('id'));
        $programuser1 = $this->generator->allocate_user_to_program($program1->get('id'), $user1->id);
        $programuser2 = $this->generator->allocate_user_to_program($program2->get('id'), $user2->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1);
        $this->generator->enrol_user_to_program_course($programcourse2, $programuser2);

        // Archive the tenant.
        $manager = new \tool_tenant\manager();
        $manager->archive_tenant($tenant->id);

        // Course enrolments should be suspended for program1.
        $enrolments = api::get_all_program_user_course_enrolments($program1->get('id'), $user1->id);
        $this->assertCount(0, $enrolments);
        // Course enrolments should be active for program2.
        $enrolments = api::get_all_program_user_course_enrolments($program2->get('id'), $user2->id);
        $this->assertCount(1, $enrolments);

        // As a user complete the course.
        $this->generator->complete_courses([$course1->id], $user1->id);
        $this->generator->complete_courses([$course1->id], $user2->id);

        // Make sure program is NOT marked as completed for this user.
        $this->assertFalse($DB->record_exists('tool_program_set_completion',
            ['setid' => $baseset->get('id'), 'userid' => $user1->id]));
        $this->assertTrue($DB->record_exists('tool_program_set_completion',
            ['setid' => $baseset2->get('id'), 'userid' => $user2->id]));
    }

    /**
     * Test complete_program with deleted program.
     */
    public function test_delete_program(): void {
        global $DB;

        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);

        $course1 = $this->generator->generate_course_with_completion_self();
        $program = $this->generator->generate_program((object)['tenantid' => $tenant->id]);
        $baseset = $program->get_base_set();
        $programcourse = $this->generator->add_course_to_set($course1->id, $baseset->get('id'));
        $programuser1 = $this->generator->allocate_user_to_program($program->get('id'), $user1->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1);

        // Make sure that user1 has active enrolment in the course and user2 has suspended enrolment in the course.
        $enrolments = api::get_all_program_user_course_enrolments($program->get('id'), $user1->id);
        $this->assertCount(1, $enrolments);
        $enrolments = api::get_all_program_user_course_enrolments($program->get('id'), $user2->id);
        $this->assertCount(0, $enrolments);

        // Make sure user has completed the program.
        $this->generator->complete_courses([$course1->id], $user1->id);
        $this->generator->complete_courses([$course1->id], $user2->id);

        // Make sure there is relevant data in all tool_program database tables.
        $programsets = $DB->get_fieldset_select('tool_program_sets', 'id', 'programid = :programid',
            ['programid' => $program->get('id')]);
        list($setsql, $setparams) = $DB->get_in_or_equal($programsets);
        $this->assertCount(1, $DB->get_records('tool_program', ['id' => $program->get('id')]));
        $this->assertCount(1, $DB->get_records('tool_program_sets', ['programid' => $program->get('id')]));
        $this->assertCount(1, $DB->get_records('tool_program_users', ['programid' => $program->get('id')]));
        $this->assertCount(1, $DB->get_records_select('tool_program_courses', 'setid' . $setsql, $setparams));
        $this->assertCount(1, $DB->get_records_select('tool_program_set_completion', 'setid' . $setsql, $setparams));

        // Archive the program and delete the program.
        api::archive_program($program);
        api::delete_program($program);

        // Make sure there is no data left in program tables.
        $this->assertCount(0, $DB->get_records('tool_program', ['id' => $program->get('id')]));
        $this->assertCount(0, $DB->get_records('tool_program_sets', ['programid' => $program->get('id')]));
        $this->assertCount(0, $DB->get_records('tool_program_users', ['programid' => $program->get('id')]));
        $this->assertCount(0, $DB->get_records_select('tool_program_courses', 'setid' . $setsql, $setparams));
        $this->assertCount(0, $DB->get_records_select('tool_program_set_completion', 'setid' . $setsql, $setparams));

        // Make sure the user enrolment in the course is suspended.
        $enrolments = api::get_all_program_user_course_enrolments($program->get('id'), $user1->id);
        $this->assertCount(0, $enrolments);
        $enrolments = api::get_all_program_user_course_enrolments($program->get('id'), $user2->id);
        $this->assertCount(0, $enrolments);
    }

    /**
     * Test delete tenant.
     */
    public function test_delete_tenant(): void {
        global $DB;

        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);

        $course1 = $this->generator->generate_course_with_completion_self();
        $program = $this->generator->generate_program((object)['tenantid' => $tenant->id]);
        $baseset = $program->get_base_set();
        $programcourse = $this->generator->add_course_to_set($course1->id, $baseset->get('id'));
        $programuser1 = $this->generator->allocate_user_to_program($program->get('id'), $user1->id);
        $programuser2 = $this->generator->allocate_user_to_program($program->get('id'), $user2->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser2);

        // Make sure that user1 and user2 have active enrolments in the course.
        $enrolments = api::get_all_program_user_course_enrolments($program->get('id'), $user1->id);
        $this->assertCount(1, $enrolments);
        $enrolments = api::get_all_program_user_course_enrolments($program->get('id'), $user2->id);
        $this->assertCount(1, $enrolments);

        // Make sure user has completed the program.
        $this->generator->complete_courses([$course1->id], $user1->id);

        // Make sure there is relevant data in all tool_program database tables.
        $programsets = $DB->get_fieldset_select('tool_program_sets', 'id', 'programid = :programid',
            ['programid' => $program->get('id')]);
        [$setsql, $setparams] = $DB->get_in_or_equal($programsets);
        $this->assertCount(1, $DB->get_records('tool_program', ['id' => $program->get('id')]));
        $this->assertCount(1, $DB->get_records('tool_program_sets', ['programid' => $program->get('id')]));
        $this->assertCount(2, $DB->get_records('tool_program_users', ['programid' => $program->get('id')]));
        $this->assertCount(1, $DB->get_records_select('tool_program_courses', 'setid' . $setsql, $setparams));
        $this->assertCount(1, $DB->get_records_select('tool_program_set_completion', 'setid' . $setsql, $setparams));

        // Archive the tenant and delete the tenant.
        $manager = new \tool_tenant\manager();
        $manager->archive_tenant($tenant->id);
        $manager->delete_tenant($tenant->id);

        // Make sure there is no data left in program tables.
        $this->assertCount(0, $DB->get_records('tool_program', ['id' => $program->get('id')]));
        $this->assertCount(0, $DB->get_records('tool_program_sets', ['programid' => $program->get('id')]));
        $this->assertCount(0, $DB->get_records('tool_program_users', ['programid' => $program->get('id')]));
        $this->assertCount(0, $DB->get_records_select('tool_program_courses', 'setid' . $setsql, $setparams));
        $this->assertCount(0, $DB->get_records_select('tool_program_set_completion', 'setid' . $setsql, $setparams));

        // Make sure the user enrolments in the course are suspended.
        $enrolments = api::get_all_program_user_course_enrolments($program->get('id'), $user1->id);
        $this->assertCount(0, $enrolments);
        $enrolments = api::get_all_program_user_course_enrolments($program->get('id'), $user2->id);
        $this->assertCount(0, $enrolments);
    }
}
