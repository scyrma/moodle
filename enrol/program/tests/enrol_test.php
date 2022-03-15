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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Tests for enrol_program.
 *
 * @package   enrol_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\persistent\program;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;

defined('MOODLE_INTERNAL') || die();

/**
 * Class enrol_program_enrol_testcase
 *
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class enrol_program_enrol_testcase extends \core_privacy\tests\provider_testcase {
    /** @var tool_program_generator */
    protected $generator;
    /** @var stdClass */
    protected $course1;
    /** @var program */
    protected $program1;
    /** @var program_set */
    protected $baseset;
    /** @var program_course */
    protected $programcourse1;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        if (!core_component::get_component_directory('tool_program')) {
            $this->markTestSkipped('Can not find tool_program');
        }

        $this->course1 = self::getDataGenerator()->create_course(); // Empty course.
        $this->program1 = $this->generator->generate_program();
        $this->baseset = $this->program1->get_base_set();
        $this->programcourse1 = $this->generator->add_course_to_set($this->course1->id, $this->baseset->get('id'));
    }

    /**
     * Get enrolment instance for enrol_program
     *
     * @param int $courseid
     * @param bool $enabled
     * @return stdClass|null
     */
    protected function get_enrol_instance(int $courseid = 0, $enabled = true): ?stdClass {
        $courseid = $courseid ?: $this->course1->id;
        $instances = array_filter(enrol_get_instances($courseid, $enabled), function($e) {
            return $e->enrol === 'program';
        });
        return $instances ? reset($instances) : null;
    }

    public function test_enrol_instance_exists() {
        $enrols = enrol_get_plugins(true);
        $this->assertTrue(array_key_exists('program', $enrols));
        $this->assertInstanceOf(enrol_program_plugin::class, $enrols['program']);

        $enrolinstances = enrol_get_instances($this->course1->id, true);
        $filtered = array_filter($enrolinstances, function($e) {
            return $e->enrol === 'program';
        });
        $this->assertCount(1, $filtered);
    }

    /**
     * Enrol in a course using the enrol_page_hook (expected redirection)
     *
     * @param enrol_program_plugin $enrol
     * @param stdClass $instance
     */
    protected function enrol_in_a_course(enrol_program_plugin $enrol, stdClass $instance) {
        try {
            $enrol->enrol_page_hook($instance);
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
            $this->assertEquals('Unsupported redirect detected, script execution terminated', $e->getMessage());
            return;
        }
        $this->fail('Redirection expected');
    }

    public function test_enrol_hook() {
        /** @var enrol_program_plugin $enrol */
        $enrol = enrol_get_plugins(true)['program'];
        $instance = $this->get_enrol_instance();

        // Create a user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Try to "access" course enrolment page - nothing happens.
        $this->assertFalse($enrol->enrol_page_hook($instance));

        // Allocate user to the program. This user is not yet enrolled in a course.
        $this->generator->allocate_user_to_program($this->program1->get('id'), $user->id);
        $this->assertEmpty(enrol_get_my_courses());

        // Try to "access" course enrolment page (it should redirect to the course).
        $this->enrol_in_a_course($enrol, $instance);

        // Now user is enrolled into a course.
        $mycourses = enrol_get_my_courses();
        $this->assertNotEmpty($mycourses[$this->course1->id]);
    }

    public function test_can_self_enrol() {
        /** @var enrol_program_plugin $enrol */
        $enrol = enrol_get_plugins(true)['program'];
        $instance = $this->get_enrol_instance();

        // Create a user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // User is not yet allocated to program, they cannot enrol in the course.
        $this->assertEquals('You cannot enrol yourself in this course.', $enrol->can_self_enrol($instance));

        // Allocate the user to a program. Now user can self enrol.
        $this->generator->allocate_user_to_program($this->program1->get('id'), $user->id);
        $this->assertEquals(true, $enrol->can_self_enrol($instance));

        // Hide the program. User can no longer enrol.
        \tool_program\api::update_program_visibility($this->program1, 0);
        $instance = $this->get_enrol_instance($this->course1->id, false);
        $this->assertEquals('Enrolment is disabled or inactive', $enrol->can_self_enrol($instance));

        // TODO tests for other cases: user is already enrolled, enrolment starts in the future, enrol ended, etc.
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

    public function test_re_enrolment_loses_grades() {
        $assignrow = $this->getDataGenerator()->create_module('assign', ['course' => $this->course1->id]);

        // Create a user.
        $user = $this->getDataGenerator()->create_user();
        // Allocate to a program, enrol in a course and add a grade.
        $this->generator->allocate_user_to_program($this->program1->get('id'), $user->id);
        $this->setUser($user);
        $this->enrol_in_a_course(enrol_get_plugins(true)['program'], $this->get_enrol_instance());
        // Add a grade.
        $this->assertEquals(GRADE_UPDATE_OK, $this->set_grade_in_course($assignrow, $user->id, 50));
        $gradinginfo = grade_get_grades($this->course1->id, 'mod', 'assign', $assignrow->id, $user->id);
        $this->assertEquals(50, $gradinginfo->items[0]->grades[$user->id]->grade);

        // Unallocate and allocate user back to the program, then enrol in the course.
        \tool_program\api::deallocate_user($this->program1->get('id'), $user->id);
        $this->generator->allocate_user_to_program($this->program1->get('id'), $user->id);
        $this->enrol_in_a_course(enrol_get_plugins(true)['program'], $this->get_enrol_instance());

        // Grades are not restored.
        $gradinginfo = grade_get_grades($this->course1->id, 'mod', 'assign', $assignrow->id, $user->id);
        $this->assertEmpty($gradinginfo->items[0]->grades[$user->id]->grade);
    }

    public function test_re_enrolment_recover_grades() {
        global $CFG;
        // Set to recover grades on re-enrolment.
        $CFG->recovergradesdefault = 1;

        $assignrow = $this->getDataGenerator()->create_module('assign', ['course' => $this->course1->id]);

        // Create a user.
        $user = $this->getDataGenerator()->create_user();
        // Allocate to a program, enrol in a course and add a grade.
        $this->generator->allocate_user_to_program($this->program1->get('id'), $user->id);
        $this->setUser($user);
        $this->enrol_in_a_course(enrol_get_plugins(true)['program'], $this->get_enrol_instance());
        // Add a grade.
        $this->assertEquals(GRADE_UPDATE_OK, $this->set_grade_in_course($assignrow, $user->id, 50));
        $gradinginfo = grade_get_grades($this->course1->id, 'mod', 'assign', $assignrow->id, $user->id);
        $this->assertEquals(50, $gradinginfo->items[0]->grades[$user->id]->grade);

        // Unallocate and allocate user back to the program, then enrol in the course.
        \tool_program\api::deallocate_user($this->program1->get('id'), $user->id);
        $this->generator->allocate_user_to_program($this->program1->get('id'), $user->id);
        $this->enrol_in_a_course(enrol_get_plugins(true)['program'], $this->get_enrol_instance());

        // Grades are restored.
        $gradinginfo = grade_get_grades($this->course1->id, 'mod', 'assign', $assignrow->id, $user->id);
        $this->assertEquals(50, $gradinginfo->items[0]->grades[$user->id]->grade);
    }
}
