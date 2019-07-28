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
 *  Tests for theme_workplace api.
 *
 * @package   theme_workplace
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use \theme_workplace\api;

/**
 * Unit tests for theme_workplace api
 *
 * @package    theme_workplace
 * @copyright  2019 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_test extends \core_privacy\tests\provider_testcase {
    /**
     * setUp.
     */
    public function setUp() {
        $this->resetAfterTest();
    }

    /**
     * Enrols user into a course as student, automatically creates enrolment method if missing
     *
     * @param int $userid
     * @param stdClass $course
     * @param string $enrol
     * @param int $timestart
     * @param int $timeend
     * @param int $status
     */
    protected function enrol_user(int $userid, stdClass $course, string $enrol = 'manual',
                                  int $timestart = 0, int $timeend = 0, int $status = ENROL_USER_ACTIVE) {
        global $DB;

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        if ($enrol !== 'manual') {
            // Make sure enrolment method exists in the course and is enabled.
            if (!$enrolplugin = enrol_get_plugin($enrol)) {
                throw new coding_exception('Enrolment plugin not found');
            }
            $instances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => $enrol]);
            if (!$instances) {
                $enrolplugin->add_instance($course, ['roleid' => $studentrole->id]);
            } else {
                $instance = reset($instances);
                if ($instance->status != ENROL_INSTANCE_ENABLED) {
                    $DB->update_record('enrol', ['id' => $instance->id, 'status' => ENROL_INSTANCE_ENABLED]);
                }
            }
        }

        $rv = $this->getDataGenerator()->enrol_user($userid, $course->id, $studentrole->id, $enrol,
            $timestart, $timeend, $status);

        if (!$rv) {
            throw new coding_exception('Could not enrol user');
        }
    }

    /**
     * Test for get_enrolled_courses_for_current_user().
     */
    public function test_get_enrolled_courses_for_current_user_by_lowest_enddate(): void {

        $user = self::getDataGenerator()->create_user();
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();
        $course3 = self::getDataGenerator()->create_course();
        $course4 = self::getDataGenerator()->create_course();

        self::setUser($user);

        $oneweek = strtotime('+1 week');
        $twoweeks = strtotime('+2 week');

        // Enrol user in course1 using manual and self enrolments both enabled.
        $this->enrol_user($user->id, $course1, 'self', 0, $oneweek);
        $this->enrol_user($user->id, $course1, 'manual', 0, $twoweeks);

        // Assert that we get 1 course with lowest enddate.
        $courses = api::get_enrolled_courses_for_current_user_by_lowest_enddate();
        $this->assertCount(1, $courses);
        $this->assertEquals($twoweeks, $courses[$course1->id]->enddate);
        $this->assertEquals($course1->id, $courses[$course1->id]->id);
        $this->assertEquals($course1->fullname, $courses[$course1->id]->fullname);
        $this->assertEquals($course1->shortname, $courses[$course1->id]->shortname);
        $this->assertEquals($course1->category, $courses[$course1->id]->category);
        $this->assertEquals($course1->summary, $courses[$course1->id]->summary);
        $this->assertEquals($course1->summaryformat, $courses[$course1->id]->summaryformat);
        $this->assertEquals($course1->idnumber, $courses[$course1->id]->idnumber);
        $this->assertEquals($course1->startdate, $courses[$course1->id]->startdate);

        // Enrol user in course2 using manual and self enrolments (one disabled, one enabled).
        $this->enrol_user($user->id, $course2, 'self', 0, $oneweek, ENROL_USER_SUSPENDED);
        $this->enrol_user($user->id, $course2, 'manual', 0, $twoweeks);

        // Assert that we get 2 courses.
        $courses = api::get_enrolled_courses_for_current_user_by_lowest_enddate();
        $this->assertCount(2, $courses);
        $this->assertEquals($twoweeks, $courses[$course2->id]->enddate);

        // Enrol user in course3 using manual and self enrolments (both enabled), one of them does not have enddate.
        $this->enrol_user($user->id, $course3, 'self', 0, 0);
        $this->enrol_user($user->id, $course3, 'manual', 0, $oneweek);

        // Assert that we get 3 courses.
        $courses = api::get_enrolled_courses_for_current_user_by_lowest_enddate();
        $this->assertCount(3, $courses);
        $this->assertEquals(0, $courses[$course3->id]->enddate);

        // Enrol user in course4 using manual and self enrolments (both disabled).
        $this->enrol_user($user->id, $course4, 'self', 0, $oneweek, ENROL_USER_SUSPENDED);
        $this->enrol_user($user->id, $course4, 'manual', 0, $twoweeks, ENROL_USER_SUSPENDED);

        // Assert that we get 3 courses.
        $courses = api::get_enrolled_courses_for_current_user_by_lowest_enddate();
        $this->assertCount(3, $courses);
        $this->assertArrayHasKey($course1->id, $courses);
        $this->assertArrayHasKey($course2->id, $courses);
        $this->assertArrayHasKey($course3->id, $courses);
        $this->assertArrayNotHasKey($course4->id, $courses);
    }

    /**
     * Test for get_enrolled_courses_for_current_user(), analysing course enddatee
     */
    public function test_get_enrolled_courses_for_current_user_course_enddate(): void {
        global $PAGE;

        $oneweek = strtotime('+1 week');
        $twoweeks = strtotime('+2 week');
        $threeweeks = strtotime('+2 week');

        $user = self::getDataGenerator()->create_user();

        // Course1 has no enddate, one enrolment without enddate.
        $course1 = self::getDataGenerator()->create_course();
        $this->enrol_user($user->id, $course1, 'manual', 0, 0);

        // Course2 has no enddate, one enrolment with enddate 1week.
        $course2 = self::getDataGenerator()->create_course();
        $this->enrol_user($user->id, $course2, 'manual', 0, $oneweek);

        // Course3 has enddate 1week, one enrolment with enddate 2weeks.
        $course3 = self::getDataGenerator()->create_course(['enddate' => $oneweek]);
        $this->enrol_user($user->id, $course3, 'manual', 0, $twoweeks);

        // Course4 has enddate 2week, one enrolment with enddate 1weeks.
        $course4 = self::getDataGenerator()->create_course(['enddate' => $twoweeks]);
        $this->enrol_user($user->id, $course4, 'manual', 0, $oneweek);

        // Course5 has enddate 1week, one enrolment with enddate 2weeks and one with enddate 3weeks.
        $course5 = self::getDataGenerator()->create_course(['enddate' => $oneweek]);
        $this->enrol_user($user->id, $course5, 'manual', 0, $twoweeks);
        $this->enrol_user($user->id, $course5, 'self', 0, $threeweeks);

        // Course6 has enddate 2week, one enrolment with enddate 1weeks and one with enddate 3weeks.
        $course6 = self::getDataGenerator()->create_course(['enddate' => $twoweeks]);
        $this->enrol_user($user->id, $course6, 'manual', 0, $oneweek);
        $this->enrol_user($user->id, $course6, 'self', 0, $threeweeks);

        // Course7 has enddate 1week, one enrolment with enddate 2weeks and one without enddate.
        $course7 = self::getDataGenerator()->create_course(['enddate' => $oneweek]);
        $this->enrol_user($user->id, $course7, 'manual', 0, $twoweeks);
        $this->enrol_user($user->id, $course7, 'self', 0, 0);

        // Course8 has enddate 2week, one enrolment with enddate 1weeks and one without enddate.
        $course8 = self::getDataGenerator()->create_course(['enddate' => $twoweeks]);
        $this->enrol_user($user->id, $course8, 'manual', 0, $oneweek);
        $this->enrol_user($user->id, $course8, 'self', 0, 0);

        // Course9 has an enrolment that is in the future.
        $course9 = self::getDataGenerator()->create_course(['enddate' => $twoweeks]);
        $this->enrol_user($user->id, $course9, 'manual', $oneweek, 0);

        // We get 8 courses.
        $this->setUser($user);
        $courses = api::get_enrolled_courses_for_current_user_by_lowest_enddate();
        $this->assertCount(8, $courses);
        $this->assertEquals(0, $courses[$course1->id]->enddate);
        $this->assertEquals($oneweek, $courses[$course2->id]->enddate);
        $this->assertEquals($oneweek, $courses[$course3->id]->enddate);
        $this->assertEquals($oneweek, $courses[$course4->id]->enddate);
        $this->assertEquals($oneweek, $courses[$course5->id]->enddate);
        $this->assertEquals($twoweeks, $courses[$course6->id]->enddate);
        $this->assertEquals($oneweek, $courses[$course7->id]->enddate);
        $this->assertEquals($twoweeks, $courses[$course8->id]->enddate);
        $this->assertArrayNotHasKey($course9->id, $courses);

        // Courses without enddate will be in the end of the list. Courses with enddate should be sorted by enddate.
        $this->assertEquals([$course2->id, $course3->id, $course4->id, $course5->id, $course7->id,
            $course6->id, $course8->id, $course1->id], array_keys($courses));

        // Make sure we return enough data to use the course_summary_exporter.
        $output = $PAGE->get_renderer('core');
        $courses = api::get_enrolled_courses_for_current_user_by_lowest_enddate();
        foreach ($courses as $course) {
            \context_helper::preload_from_record($course);
            $exporter = new \core_course\external\course_summary_exporter($course,
                ['context' => \context_course::instance($course->id)]);
            $exporter->export($output);
        }
    }
}