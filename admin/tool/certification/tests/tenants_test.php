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
 * Test for tenants
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Mikel Martín <mikel@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification;

use advanced_testcase;
use context_course;
use tool_certification_generator;
use tool_program_generator;
use tool_tenant_generator;

/**
 * Class tool_certification_tenants_testcase
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Mikel Martín <mikel@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tenants_test extends advanced_testcase {
    /** @var tool_certification_generator */
    protected $generator;
    /** @var tool_program_generator */
    protected $programgenerator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    /**
     * Test archive certification.
     */
    public function test_archive_certification(): void {
        $tenant1 = $this->tenantgenerator->create_tenant();
        $user1 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1->id, $tenant1->id);

        $course1 = $this->programgenerator->generate_course_with_completion_self();
        $program1 = $this->programgenerator->generate_program((object)['tenantid' => $tenant1->id]);
        $programcourse11 = $this->programgenerator->add_course_to_set($course1->id, $program1->get_base_set()->get('id'));

        $certification1 = $this->generator->generate_certification([
            'tenantid' => $tenant1->id,
            'program' => $program1->get('id'),
        ]);
        $certification2 = $this->generator->generate_certification([
            'tenantid' => $tenant1->id,
            'program' => $program1->get('id'),
        ]);

        $this->generator->allocate_user($user1->id, $certification1->get('id'));
        \tool_program\api::enrol_in_program_course($program1->get('id'), $programcourse11->get_course(), $user1->id);

        // Archive certification2.
        api::archive_certification($certification2->get('id'));

        // As a user complete the course.
        $this->programgenerator->complete_courses([$course1->id], $user1->id);

        // Make sure user1 is certified in certification1.
        $this->assertTrue(api::is_user_certified($user1->id, $certification1->get('id')));
        // Make sure user1 is NOT certified in certification2 (certification was archived).
        $this->assertFalse(api::is_user_certified($user1->id, $certification2->get('id')));
    }

    /**
     * Test archive program.
     */
    public function test_archive_program(): void {
        $tenant1 = $this->tenantgenerator->create_tenant();
        $user1 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1->id, $tenant1->id);

        $course1 = $this->programgenerator->generate_course_with_completion_self();
        $program2 = $this->programgenerator->generate_program((object)['tenantid' => $tenant1->id]);
        $programcourse12 = $this->programgenerator->add_course_to_set($course1->id, $program2->get_base_set()->get('id'));

        $certification3 = $this->generator->generate_certification([
            'tenantid' => $tenant1->id,
            'program' => $program2->get('id'),
        ]);

        $this->generator->allocate_user($user1->id, $certification3->get('id'));
        \tool_program\api::enrol_in_program_course($program2->get('id'), $programcourse12->get_course(), $user1->id);

        // Archive program2.
        \tool_program\api::archive_program($program2);

        // As a user complete the course.
        $this->programgenerator->complete_courses([$course1->id], $user1->id);

        // Make sure user1 is NOT certified in certification3 (program was archived).
        $this->assertFalse(api::is_user_certified($user1->id, $certification3->get('id')));
    }

    /**
     * Test archive tenant.
     */
    public function test_archive_tenant(): void {
        $tenant2 = $this->tenantgenerator->create_tenant();
        $user2 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user2->id, $tenant2->id);

        $course2 = $this->programgenerator->generate_course_with_completion_self();
        $program3 = $this->programgenerator->generate_program((object)['tenantid' => $tenant2->id]);
        $programcourse21 = $this->programgenerator->add_course_to_set($course2->id, $program3->get_base_set()->get('id'));

        $certification4 = $this->generator->generate_certification([
            'tenantid' => $tenant2->id,
            'program' => $program3->get('id'),
        ]);

        $this->generator->allocate_user($user2->id, $certification4->get('id'));
        \tool_program\api::enrol_in_program_course($program3->get('id'), $programcourse21->get_course(), $user2->id);

        // Archive tenant2.
        (new \tool_tenant\manager())->archive_tenant($tenant2->id);

        // As a user complete the course.
        $this->programgenerator->complete_courses([$course2->id], $user2->id);

        // Make sure user2 is NOT certified in certification4 (tenant was archived).
        $this->assertFalse(api::is_user_certified($user2->id, $certification4->get('id')));
    }

    /**
     * Test archive certification with recertification.
     */
    public function test_archive_certification_recertification(): void {
        global $DB;

        $tenant1 = $this->tenantgenerator->create_tenant();
        $user1 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1->id, $tenant1->id);

        $course1 = $this->programgenerator->generate_course_with_completion_self();
        $program1 = $this->programgenerator->generate_program((object)['tenantid' => $tenant1->id]);
        $recertprogram1 = $this->programgenerator->generate_program((object)['tenantid' => $tenant1->id]);
        $program2 = $this->programgenerator->generate_program((object)['tenantid' => $tenant1->id]);
        $recertprogram2 = $this->programgenerator->generate_program((object)['tenantid' => $tenant1->id]);
        $programcourse11 = $this->programgenerator->add_course_to_set($course1->id, $program1->get_base_set()->get('id'));
        $programcourse21 = $this->programgenerator->add_course_to_set($course1->id, $program2->get_base_set()->get('id'));

        $certification1 = $this->generator->generate_certification([
            'tenantid' => $tenant1->id,
            'program' => $program1->get('id'),
            'recertificationprogram' => $recertprogram1->get('id'),
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '1 day',
            'recertstartdaterelative' => '2 day',
        ], true);
        $certification2 = $this->generator->generate_certification([
            'tenantid' => $tenant1->id,
            'program' => $program2->get('id'),
            'recertificationprogram' => $recertprogram2->get('id'),
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '1 day',
            'recertstartdaterelative' => '2 day',
        ], true);
        $this->generator->allocate_user($user1->id, $certification1->get('id'));
        $this->generator->allocate_user($user1->id, $certification2->get('id'));

        // User1 completes course1.
        \tool_program\api::enrol_in_program_course($program1->get('id'), $programcourse11->get_course(), $user1->id);
        \tool_program\api::enrol_in_program_course($program2->get('id'), $programcourse21->get_course(), $user1->id);
        $this->programgenerator->complete_courses([$course1->id], $user1->id);

        // Make sure program1 is marked as completed for user1 and is certified in certification1.
        $this->assertTrue($DB->record_exists('tool_program_set_completion',
            ['setid' => $program1->get_base_set()->get('id'), 'userid' => $user1->id]));
        $this->assertTrue(api::is_user_certified($user1->id, $certification1->get('id')));
        // Make sure program2 is marked as completed for user1 and is certified in certification2.
        $this->assertTrue($DB->record_exists('tool_program_set_completion',
            ['setid' => $program2->get_base_set()->get('id'), 'userid' => $user1->id]));
        $this->assertTrue(api::is_user_certified($user1->id, $certification2->get('id')));

        // Archive certification2.
        api::archive_certification($certification2->get('id'));

        // Trigger recertification.
        (new \tool_certification\task\recertification())->execute();

        // Make sure user1 is allocated in recertification program (recertprogram1).
        $this->assertTrue($DB->record_exists('tool_program_users',
            ['programid' => $recertprogram1->get('id'), 'userid' => $user1->id]));
        // Make sure user1 NOT is allocated in recertification program (recertprogram2).
        $this->assertFalse($DB->record_exists('tool_program_users',
            ['programid' => $recertprogram2->get('id'), 'userid' => $user1->id]));

        // Restore certification2.
        api::restore_certification($certification2->get('id'));

        // Trigger recertification.
        (new \tool_certification\task\recertification())->execute();

        // Make sure user1 is allocated in recertification program (recertprogram2).
        $this->assertTrue($DB->record_exists('tool_program_users',
            ['programid' => $recertprogram2->get('id'), 'userid' => $user1->id]));
    }

    /**
     * Test archive tenant with recertification.
     */
    public function test_archive_tenant_recertification(): void {
        global $DB;

        $tenant2 = $this->tenantgenerator->create_tenant();
        $user2 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user2->id, $tenant2->id);

        $course2 = $this->programgenerator->generate_course_with_completion_self();
        $program3 = $this->programgenerator->generate_program((object)['tenantid' => $tenant2->id]);
        $recertprogram3 = $this->programgenerator->generate_program((object)['tenantid' => $tenant2->id]);
        $programcourse32 = $this->programgenerator->add_course_to_set($course2->id, $program3->get_base_set()->get('id'));

        $certification3 = $this->generator->generate_certification([
            'tenantid' => $tenant2->id,
            'program' => $program3->get('id'),
            'recertificationprogram' => $recertprogram3->get('id'),
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '1 day',
            'recertstartdaterelative' => '2 day',
        ], true);
        $this->generator->allocate_user($user2->id, $certification3->get('id'));

        // User2 completes course2.
        \tool_program\api::enrol_in_program_course($program3->get('id'), $programcourse32->get_course(), $user2->id);
        $this->programgenerator->complete_courses([$course2->id], $user2->id);

        // Make sure program3 is marked as completed for user2 and is certified in certification3.
        $this->assertTrue($DB->record_exists('tool_program_set_completion',
            ['setid' => $program3->get_base_set()->get('id'), 'userid' => $user2->id]));
        $this->assertTrue(api::is_user_certified($user2->id, $certification3->get('id')));

        // Archive tenant2.
        (new \tool_tenant\manager())->archive_tenant($tenant2->id);

        // Trigger recertification.
        (new \tool_certification\task\recertification())->execute();

        // Make sure user2 NOT is allocated in recertification program (recertprogram3).
        $this->assertFalse($DB->record_exists('tool_program_users',
            ['programid' => $recertprogram3->get('id'), 'userid' => $user2->id]));

        // Restore tenant2.
        (new \tool_tenant\manager())->restore_tenant($tenant2->id);

        // Trigger recertification.
        (new \tool_certification\task\recertification())->execute();

        // Make sure user2 is allocated in recertification program (recertprogram3).
        $this->assertTrue($DB->record_exists('tool_program_users',
            ['programid' => $recertprogram3->get('id'), 'userid' => $user2->id]));
    }

    /**
     * Test archive and delete certification.
     */
    public function test_delete_certification(): void {
        global $DB;

        $tenant1 = $this->tenantgenerator->create_tenant();
        $user1 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1->id, $tenant1->id);

        $course1 = $this->programgenerator->generate_course_with_completion_self();
        $program1 = $this->programgenerator->generate_program((object)['tenantid' => $tenant1->id]);
        $programcourse11 = $this->programgenerator->add_course_to_set($course1->id, $program1->get_base_set()->get('id'));

        $certification1 = $this->generator->generate_certification([
            'tenantid' => $tenant1->id,
            'program' => $program1->get('id'),
        ]);
        $certificationid = $certification1->get('id');
        $this->generator->allocate_user($user1->id, $certificationid);

        // User1 completes course1.
        \tool_program\api::enrol_in_program_course($program1->get('id'), $programcourse11->get_course(), $user1->id);
        $this->programgenerator->complete_courses([$course1->id], $user1->id);

        // Make sure that user1 has active enrolment in the course1.
        $coursecontext = context_course::instance($course1->id);
        $this->assertTrue(is_enrolled($coursecontext, $user1->id, '', true));
        // Make sure that user1 is certified in certification1.
        $this->assertTrue(api::is_user_certified($user1->id, $certificationid));

        // Make sure there is relevant data in certification tables.
        $this->assertCount(1, $DB->get_records('tool_certification',
            ['id' => $certificationid]));
        $this->assertCount(1, $DB->get_records('tool_certification_users',
            ['certificationid' => $certificationid]));
        $this->assertCount(1, $DB->get_records('tool_certification_compltion',
            ['certificationid' => $certificationid]));

        // Archive and delete certification1.
        api::archive_certification($certificationid);
        $certification1 = new \tool_certification\certification($certificationid);
        api::delete_certification($certification1);

        // Make sure there is no data left in certification tables.
        $this->assertCount(0, $DB->get_records('tool_certification',
            ['id' => $certificationid]));
        $this->assertCount(0, $DB->get_records('tool_certification_users',
            ['certificationid' => $certificationid]));
        $this->assertCount(0, $DB->get_records('tool_certification_compltion',
            ['certificationid' => $certificationid]));

        // Make sure the user1 enrolment in the course1 is suspended.
        $coursecontext = context_course::instance($course1->id);
        $this->assertFalse(is_enrolled($coursecontext, $user1->id, '', true));
    }

    /**
     * Test archive and delete tenant.
     */
    public function test_delete_tenant(): void {
        global $DB;

        $tenant2 = $this->tenantgenerator->create_tenant();
        $user2 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user2->id, $tenant2->id);

        $course2 = $this->programgenerator->generate_course_with_completion_self();
        $program2 = $this->programgenerator->generate_program((object)['tenantid' => $tenant2->id]);
        $programcourse22 = $this->programgenerator->add_course_to_set($course2->id, $program2->get_base_set()->get('id'));

        $certification2 = $this->generator->generate_certification([
            'tenantid' => $tenant2->id,
            'program' => $program2->get('id')
        ]);
        $certificationid = $certification2->get('id');
        $this->generator->allocate_user($user2->id, $certificationid);

        // User2 completes course2.
        \tool_program\api::enrol_in_program_course($program2->get('id'), $programcourse22->get_course(), $user2->id);
        $this->programgenerator->complete_courses([$course2->id], $user2->id);

        // Make sure that user2 has active enrolment in the course2.
        $coursecontext = context_course::instance($course2->id);
        $this->assertTrue(is_enrolled($coursecontext, $user2->id, '', true));
        // Make sure that user2 is certified in certification2.
        $this->assertTrue(api::is_user_certified($user2->id, $certificationid));

        // Make sure there is relevant data in certification tables.
        $this->assertCount(1, $DB->get_records('tool_certification',
            ['id' => $certificationid]));
        $this->assertCount(1, $DB->get_records('tool_certification_users',
            ['certificationid' => $certificationid]));
        $this->assertCount(1, $DB->get_records('tool_certification_compltion',
            ['certificationid' => $certificationid]));

        // Archive and delete tenant2.
        $manager = new \tool_tenant\manager();
        $tenant2 = $manager->archive_tenant($tenant2->id);
        $manager->delete_tenant($tenant2->get('id'));

        // Make sure there is no data left in certification tables.
        $this->assertCount(0, $DB->get_records('tool_certification',
            ['id' => $certificationid]));
        $this->assertCount(0, $DB->get_records('tool_certification_users',
            ['certificationid' => $certificationid]));
        $this->assertCount(0, $DB->get_records('tool_certification_compltion',
            ['certificationid' => $certificationid]));

        // Make sure the user2 enrolment in the course is suspended.
        $coursecontext = context_course::instance($course2->id);
        $this->assertFalse(is_enrolled($coursecontext, $user2->id, '', true));
    }
}
