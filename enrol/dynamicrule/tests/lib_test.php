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
 * Cohort enrolment sync functional test.
 *
 * @package    enrol_dynamicrule
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Workplace team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Contains tests for the cohort library.
 *
 * @package   enrol_dynamicrule
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Workplace team
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class enrol_dynamicrule_lib_testcase extends advanced_testcase {

    /**
     * Test for getting user enrolment actions.
     */
    public function test_get_user_enrolment_actions() {
        global $CFG, $PAGE;
        $this->resetAfterTest();

        // Set page URL to prevent debugging messages.
        $PAGE->set_url('/enrol/editinstance.php');

        $generator = $this->getDataGenerator();

        // Get the enrol plugin.
        $pluginname = 'dynamicrule';
        $plugin = enrol_get_plugin($pluginname);

        // Create a course.
        $course = $generator->create_course();
        // Enable this enrol plugin for the course.
        $plugin->add_instance($course, ['customint1' => 0]);

        // Create a student.
        $student = $generator->create_user();
        // Enrol the student to the course.
        $generator->enrol_user($student->id, $course->id, 'student', $pluginname);

        // Validate user enrolment.
        $this->setAdminUser();
        require_once($CFG->dirroot . '/enrol/locallib.php');
        $manager = new course_enrolment_manager($PAGE, $course);
        $userenrolments = $manager->get_user_enrolments($student->id);
        $this->assertCount(1, $userenrolments);

        // Validate user enrolment actions.
        $ue = reset($userenrolments);
        $actions = $plugin->get_user_enrolment_actions($manager, $ue);
        $this->assertCount(1, $actions);
        $this->assertEquals('Unenrol', $actions[0]->get_title());
    }

    /**
     * Test add_instance respects expected param.
     */
    public function test_add_instance_param() {
        global $PAGE;
        $this->resetAfterTest();

        // Set page URL to prevent debugging messages.
        $PAGE->set_url('/enrol/editinstance.php');

        $generator = $this->getDataGenerator();

        // Get the enrol plugin.
        $pluginname = 'dynamicrule';
        $plugin = enrol_get_plugin($pluginname);

        // Create a course.
        $course = $generator->create_course();

        // Enable this enrol plugin for the course, but don't pass customint1.
        $this->assertNull($plugin->add_instance($course));
        $this->assertDebuggingCalled();

        // Enable this enrol plugin for the course, but pass customint1.
        $this->assertIsInt($plugin->add_instance($course, ['customint1' => 0]));
        $this->assertDebuggingNotCalled();
    }
}
