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

declare(strict_types=1);

namespace tool_catalogue;

use advanced_testcase;
use completion_completion;
use tool_certification\api;
use tool_certification\certification;
use tool_certification\certification_completion;
use tool_certification\constants;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use tool_program_generator;

/**
 * Unit tests for manager class
 *
 * @package     tool_catalogue
 * @covers      \tool_catalogue\manager
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager_test extends advanced_testcase {

    /**
     * Test get_user_accessible_programs
     */
    public function test_get_user_accessible_programs(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $program1 = $programgenerator->generate_program();
        // Program 2 will not be accesible because is archived.
        $program2 = $programgenerator->generate_program((object)['archived' => true]);
        $program3 = $programgenerator->generate_program();
        $programgenerator->allocate_user_to_program($program1->get('id'), (int) $USER->id);
        $programgenerator->allocate_user_to_program($program2->get('id'), (int) $USER->id);
        $programgenerator->allocate_user_to_program($program3->get('id'), (int) $USER->id);

        $programs = manager::get_user_accessible_programs((int) $USER->id);

        // User can access only program1 and program3.
        $this->assertEqualsCanonicalizing([$program1->get('id'), $program3->get('id')], array_keys($programs));
        $this->assertInstanceOf(program::class, $programs[$program1->get('id')]);
        $this->assertEquals($program1->get('fullname'), $programs[$program1->get('id')]->get('fullname'));
    }

    /**
     * Test get user allocations
     */
    public function test_get_user_allocations(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $userid = (int) $USER->id;

        $program1 = $programgenerator->generate_program();
        $program2 = $programgenerator->generate_program();
        $allocation1 = $programgenerator->allocate_user_to_program($program1->get('id'), $userid);

        $certification1 = $certificationgenerator->generate_certification(['archived' => 0, 'program' => $program1->get('id')]);
        $allocation2 = $certificationgenerator->allocate_user($userid, $certification1->get('id'));

        // This allocation belongs to an archived certification and should not be returned by the method.
        $certification2 = $certificationgenerator->generate_certification(['archived' => 1, 'program' => $program2->get('id')]);
        $certificationgenerator->allocate_user($userid, $certification2->get('id'));

        $allocations = manager::get_user_allocations($userid);

        $certificationids = array_map(static function(program_user $allocation): int {
            return $allocation->get('certificationid');
        }, $allocations);

        // We should have one direct allocation and one using certification1.
        $this->assertEqualsCanonicalizing($certificationids,
            [$allocation1->get('certificationid'), $allocation2->get('certificationid')]);

        // Test passing a program id.
        $allocation3 = $programgenerator->allocate_user_to_program($program2->get('id'), $userid);
        $allocations = manager::get_user_allocations($userid, $program2->get('id'));

        $allocationids = array_map(static function(program_user $allocation): int {
            return $allocation->get('id');
        }, $allocations);

        $this->assertEquals($allocationids, [$allocation3->get('id')]);
    }

    /**
     * Test get_user_accessible_courses method.
     */
    public function test_get_user_accessible_courses(): void {
        $this->resetAfterTest();

        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();
        $course3 = self::getDataGenerator()->create_course(['visible' => false]);

        self::getDataGenerator()->enrol_user($user->id, $course1->id, 'student');
        self::getDataGenerator()->enrol_user($user->id, $course2->id, 'teacher');
        // This course is hidden and should not be returned.
        self::getDataGenerator()->enrol_user($user->id, $course3->id, 'student');

        // Add a program course enrolment. This course should not be returned by get_user_accessible_courses method.
        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $program1 = $programgenerator->generate_program();
        $course3 = self::getDataGenerator()->create_course();
        $programgenerator->add_course_to_set((int) $course3->id, $program1->get_base_set()->get('id'));
        $programgenerator->allocate_user_to_program($program1->get('id'), (int) $user->id);

        $courses = manager::get_user_accessible_courses((int) $user->id);
        $courseids = array_map(static function($course) {
            return $course->id;
        }, $courses);
        $this->assertEqualsCanonicalizing([$course1->id, $course2->id], $courseids);
    }

    /**
     * Test get_user_courses_completion method.
     */
    public function test_get_user_courses_completion(): void {
        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user->id, $course1->id, 'student');
        $this->getDataGenerator()->enrol_user($user->id, $course2->id, 'student');

        $starttime = time() - DAYSECS;
        $completiontime = time();
        $ccompletion = new completion_completion(['course' => $course2->id, 'userid' => $user->id]);
        $ccompletion->mark_inprogress($starttime);
        $ccompletion->mark_complete($completiontime);

        $completions = manager::get_user_courses_completion([$course1, $course2], (int) $user->id);

        $this->assertEqualsCanonicalizing([$course1->id, $course2->id], array_keys($completions));
        $this->assertNull($completions[$course1->id]->timecompleted);
        $this->assertEquals($completiontime, $completions[$course2->id]->timecompleted);
    }

    /**
     * Test get certifications by user id.
     */
    public function test_get_certifications_by_userid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');

        $user = self::getDataGenerator()->create_user();
        $certification1 = $certificationgenerator->generate_certification();
        $certificationgenerator->allocate_user((int) $user->id, $certification1->get('id'));
        $certification2 = $certificationgenerator->generate_certification();
        $certification3 = $certificationgenerator->generate_certification();
        $certificationgenerator->allocate_user((int) $user->id, $certification3->get('id'));

        $certifications = manager::get_certifications_by_userid((int) $user->id);

        $this->assertCount(2, $certifications);
        $this->assertContainsOnlyInstancesOf(certification::class, $certifications);
        $this->assertArrayHasKey($certification1->get('id'), $certifications);
        $this->assertArrayNotHasKey($certification2->get('id'), $certifications);
        $this->assertArrayHasKey($certification3->get('id'), $certifications);
    }

    /**
     * Test get_last_course_access
     */
    public function test_get_last_course_access(): void {
        global $DB;
        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user->id, $course1->id, 'student');
        $this->getDataGenerator()->enrol_user($user->id, $course2->id, 'student');

        $timeaccess1 = time();
        $timeaccess2 = time() + DAYSECS;
        $DB->insert_record('user_lastaccess', ['userid' => $user->id, 'courseid' => $course1->id, 'timeaccess' => $timeaccess1]);
        $DB->insert_record('user_lastaccess', ['userid' => $user->id, 'courseid' => $course2->id, 'timeaccess' => $timeaccess2]);

        $lastaccesses = manager::get_last_course_access((int) $user->id, [$course1->id, $course2->id]);

        $this->assertEqualsCanonicalizing([$course1->id, $course2->id], array_keys($lastaccesses));
        $this->assertEquals($lastaccesses[$course1->id]->timeaccess, $timeaccess1);
        $this->assertEquals($lastaccesses[$course2->id]->timeaccess, $timeaccess2);

        $timeaccess3 = $timeaccess2 + DAYSECS;
        $DB->set_field('user_lastaccess', 'timeaccess', $timeaccess3, [
            'userid' => $user->id,
            'courseid' => $course2->id,
        ]);

        $lastaccesses2 = manager::get_last_course_access((int) $user->id, [$course1->id, $course2->id]);

        $this->assertEquals($lastaccesses2[$course2->id]->timeaccess, $timeaccess3);
    }

    /**
     * Test get_certification_allocations_status
     */
    public function test_get_certification_allocations_status(): void {
        $this->resetAfterTest();

        $certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $user = self::getDataGenerator()->create_user();
        $certification = $certificationgenerator->generate_certification();

        $now = time();
        $twodaysless = strtotime('-2 day', $now);
        $onedaymore = strtotime('+1 day', $now);

        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        $allocation = api::allocate_user($certification, $userdata);

        // Status OPEN.
        $status = manager::get_certification_allocations_status([$allocation], (int) $user->id);
        $this->assertEquals([$allocation->get('id') => constants::STATUS_OPEN], $status);

        // Status FUTURE ALLOCATION.
        $programallocation = program_user::get_record(['certificationid' => $certification->get('id'), 'userid' => $user->id]);
        $programallocation->set('startdate', $onedaymore);
        $programallocation->update();
        $status = manager::get_certification_allocations_status([$allocation], (int) $user->id);
        $this->assertEquals([$allocation->get('id') => constants::STATUS_FUTUREALLOCATION], $status);

        // Status OVERDUE.
        $programallocation->read();
        $programallocation->set('startdate', $twodaysless);
        $programallocation->set('duedate', $now - DAYSECS);
        $programallocation->update();
        $status = manager::get_certification_allocations_status([$allocation], (int) $user->id);
        $this->assertEquals([$allocation->get('id') => constants::STATUS_OVERDUE], $status);

        // Status SUSPENDED.
        $allocation->set('status', constants::STATUS_SUSPENDED);
        $allocation->update();
        $allocation->read();
        $status = manager::get_certification_allocations_status([$allocation], (int) $user->id);
        $this->assertEquals([$allocation->get('id') => constants::STATUS_SUSPENDED], $status);

        // Status CERTIFIED.
        $allocation->set('status', constants::STATUS_OVERRIDE_DEFAULT);
        $allocation->update();
        api::set_user_as_certified((int) $user->id, $certification->get('id'));
        $status = manager::get_certification_allocations_status([$allocation], (int) $user->id);
        $this->assertEquals([$allocation->get('id') => constants::STATUS_CERTIFIED], $status);

        // Status EXPIRED.
        $completion = certification_completion::get_record(
            ['userid' => $user->id, 'certificationid' => $certification->get('id')]);
        $completion->set('expirydate', $twodaysless);
        $completion->update();
        $status = manager::get_certification_allocations_status([$allocation], (int) $user->id);
        $this->assertEquals([$allocation->get('id') => constants::STATUS_EXPIRED], $status);
    }

    /**
     * Test get_certifications_completion
     */
    public function test_get_certifications_completion(): void {
        $this->resetAfterTest();

        $this->setAdminUser();
        $certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');

        $user = self::getDataGenerator()->create_user();
        $certification1 = $certificationgenerator->generate_certification();
        $certificationgenerator->allocate_user((int) $user->id, $certification1->get('id'));
        $certification2 = $certificationgenerator->generate_certification();
        $certificationgenerator->allocate_user((int) $user->id, $certification2->get('id'));
        $certification3 = $certificationgenerator->generate_certification();
        $certificationgenerator->allocate_user((int) $user->id, $certification3->get('id'));

        $timecertified1 = time();
        $timeexpired1 = time() + YEARSECS;
        \tool_certification\api::set_user_as_certified((int) $user->id, $certification2->get('id'));
        \tool_certification\api::set_user_as_certified((int) $user->id, $certification3->get('id'),
            $timeexpired1, $timecertified1);

        $completions = manager::get_certifications_completion([$certification1, $certification2, $certification3],
            (int) $user->id);

        $certificationids = array_map(static function($certification) {
            return $certification->get('certificationid');
        }, $completions);
        $this->assertEqualsCanonicalizing([$certification2->get('id'), $certification3->get('id')], $certificationids);

        $completion3 = array_filter($completions, static function($completion) use ($certification3) {
            return $completion->get('certificationid') === $certification3->get('id');
        });

        $completion = reset($completion3);
        $this->assertEquals($timecertified1, $completion->get('timecertified'));
        $this->assertEquals($timeexpired1, $completion->get('expirydate'));
    }

    /**
     * Data provider for {@see test_get_duedate_badge}
     *
     * @return array
     */
    public function get_duedate_badge_provider(): array {
        return [
            [null, 5, 'duedateinfodays', '', ''],
            [6 * DAYSECS, 5, 'duedateinfodays', '', ''],
            [3 * DAYSECS + HOURSECS, 5, 'duedateinfodays', 'warning', 'Due in 3 days'],
            [DAYSECS + HOURSECS, 5, 'duedateinfodays', 'warning', 'Due in 1 day'],
            [-DAYSECS, 5, 'duedateinfodays', 'danger', 'Overdue'],
            [null, 5, 'daysleft', '', ''],
            [6 * DAYSECS, 5, 'daysleft', '', ''],
            [3 * DAYSECS + HOURSECS, 5, 'daysleft', 'warning', '3 days left'],
            [DAYSECS + HOURSECS, 5, 'daysleft', 'warning', 'Due in 1 day'],
            [-DAYSECS, 5, 'daysleft', 'danger', 'Overdue'],
        ];
    }

    /**
     * Test get_duedate_badge
     *
     * @param int|null $duedateoffset
     * @param int $duelimit
     * @param string $daysremainingidentifier
     * @param string $expectedtype
     * @param string $expectedstring
     *
     * @dataProvider get_duedate_badge_provider
     */
    public function test_get_duedate_badge(?int $duedateoffset, int $duelimit,
                                           string $daysremainingidentifier, string $expectedtype, string $expectedstring): void {
        $duedate = !is_null($duedateoffset) ? (time() + $duedateoffset) : 0;
        [$duedatebadgetype, $duedatebadgestr] = manager::get_duedate_badge($duedate, $duelimit, $daysremainingidentifier);
        $this->assertEquals($expectedtype, $duedatebadgetype);
        $this->assertEquals($expectedstring, $duedatebadgestr);
    }
}
