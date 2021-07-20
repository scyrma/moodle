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
 * Program enrolment tests.
 *
 * @package    tool_program
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\api;
use tool_program\persistent\program;
use tool_program\persistent\program_set;
use tool_program\program_tree_progress;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/completionlib.php');

/**
 * Class program enrolment tests.
 *
 * @package    tool_program
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_enrolment_testcase extends advanced_testcase {

    /** @var tool_program_generator */
    protected $generator;
    /** @var stdClass */
    protected $course1;
    /** @var stdClass */
    protected $course2;
    /** @var stdClass */
    protected $course3;
    /** @var program */
    protected $program1;
    /** @var stdClass */
    protected $user1;
    /** @var stdClass */
    protected $user2;
    /** @var stdClass */
    protected $user3;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->resetAfterTest();

        set_config('enablecompletion', COMPLETION_ENABLED);

        // Generate courses with completion self criteria.
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_program');

        $this->course1 = $this->generator->generate_course_with_completion_self();
        $this->course2 = $this->generator->generate_course_with_completion_self();
        $this->course3 = $this->generator->generate_course_with_completion_self();

        // Generate program with courses course1 and course2 with completion "at least 1" and "autocreategroups" enabled.
        $this->program1 = $this->generator->generate_program((object)[
            'fullname' => 'Program1',
            'autocreategroups' => api::GROUPS_TENANT_PROGRAM
        ]);
        $baseset = $this->program1->get_base_set();
        $baseset->set('completioncriteria', program_set::COMPLETION_AT_LEAST);
        $baseset->set('completionatleast', 1);
        $baseset->update();

        $this->generator->add_course_to_set($this->course1->id, $this->program1->get_base_set()->get('id'));
        $this->generator->add_course_to_set($this->course2->id, $this->program1->get_base_set()->get('id'));

        // Generate users and allocate user1 and user2 in program.
        $this->user1 = self::getDataGenerator()->create_user();
        $this->user2 = self::getDataGenerator()->create_user();
        $this->user3 = self::getDataGenerator()->create_user();
        $this->generator->allocate_user_to_program($this->program1->get('id'), $this->user1->id);
        $this->generator->allocate_user_to_program($this->program1->get('id'), $this->user2->id);
    }

    /**
     * Test self enrol on access and program completion.
     */

    public function test_self_enrol_on_access(): void {
        $this->setUser($this->user1);

        // User1 access the course using external funcion. Dashboard access simulation.
        \tool_program\external::enrol_user_to_course($this->course1->id, $this->program1->get('id'));

        // Test user enrol instance correctly created and active.
        $enrolments = api::get_all_courses_actively_enrolled_with_enrol_program($this->program1->get('id'),
            $this->user1->id, $this->course1->id);
        $this->assertCount(1, $enrolments);

        // Complete the course.
        $ccompletion = new completion_completion(array('course' => $this->course1->id, 'userid' => $this->user1->id));
        $ccompletion->mark_complete();

        // Test program is completed.
        $programtreeprogress = new program_tree_progress($this->program1, $this->user1->id);
        $this->assertTrue($programtreeprogress->get_baseset()->iscompleted);

        // Test program group is created.
        $course1groups = groups_get_all_groups($this->course1->id);
        $this->assertEquals('Program1', reset($course1groups)->name);

        // Generate other tenant.
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $cat2 = $this->getDataGenerator()->create_category();
        $tenantgenerator->create_tenant(['categoryid' => $cat2->id]);

        // User2 access the course using external funcion. Dashboard access simulation.
        $this->setUser($this->user2);
        \tool_program\external::enrol_user_to_course($this->course1->id, $this->program1->get('id'));

        // Test tenant program group is created.
        $course1groups = groups_get_all_groups($this->course1->id);
        $this->assertEquals('Default tenant - Program1', reset($course1groups)->name);
    }

    /**
     * Test program enrolment when course is added to program with already completed course.
     */
    public function test_add_course_program_with_completed_course(): void {
        $this->setUser($this->user2);

        // Enrol in course.
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course3->id);

        // Complete the course.
        $ccompletion = new completion_completion(array('course' => $this->course3->id, 'userid' => $this->user2->id));
        $ccompletion->mark_complete();

        // Add course to program.
        $this->generator->add_course_to_set($this->course3->id, $this->program1->get_base_set()->get('id'));

        // Test user enrol instance correctly created and active.
        $enrolments = api::get_all_courses_actively_enrolled_with_enrol_program($this->program1->get('id'),
            $this->user2->id, $this->course3->id);
        $this->assertCount(1, $enrolments);

        // Test program is completed.
        $programtreeprogress = new program_tree_progress($this->program1, $this->user2->id);
        $this->assertTrue($programtreeprogress->get_baseset()->iscompleted);

        // Add same completed course to program in a new set.
        $set1 = $this->generator->generate_set((object) [
            'name' => 'Child set 01',
            'programid' => $this->program1->get('id'),
            'parent' => $this->program1->get_base_set()->get('id'),
            'completioncriteria' => program_set::COMPLETION_AT_LEAST,
            'completionatleast' => 1,
            'sortorder' => 1,
        ]);
        $this->generator->add_course_to_set($this->course3->id, $set1->get('id'));

        // Test user enrol instance is not created.
        $enrolments = api::get_all_courses_actively_enrolled_with_enrol_program($this->program1->get('id'),
            $this->user2->id, $this->course3->id);
        $this->assertCount(1, $enrolments);

        // Test program is still completed.
        $programtreeprogress = new program_tree_progress($this->program1, $this->user2->id);
        $this->assertEquals('1', $programtreeprogress->get_baseset()->completion);
    }

    /**
     * Test program enrolment when user is allocated with already completed course.
     */
    public function test_allocate_user_with_completed_course(): void {
        $this->setUser($this->user3);

        // Enrol in course.
        $this->getDataGenerator()->enrol_user($this->user3->id, $this->course1->id);

        // Complete the course.
        $ccompletion = new completion_completion(array('course' => $this->course1->id, 'userid' => $this->user3->id));
        $ccompletion->mark_complete();

        // Allocate user to program.
        api::allocate_user($this->program1, (object) ['userid' => $this->user3->id]);

        // Test user enrol instance correctly created and active.
        $enrolments = api::get_all_courses_actively_enrolled_with_enrol_program($this->program1->get('id'),
            $this->user3->id, $this->course1->id);
        $this->assertCount(1, $enrolments);

        // Test program is completed.
        $programtreeprogress = new program_tree_progress($this->program1, $this->user3->id);
        $this->assertTrue($programtreeprogress->get_baseset()->iscompleted);
    }
}
