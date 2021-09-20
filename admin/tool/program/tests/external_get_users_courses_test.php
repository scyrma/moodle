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
 * Tests for the tool_program get_users_courses external class.
 *
 * @package   tool_program
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;

use tool_program\api;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the tool_program get_users_courses external class.
 *
 * @covers     \tool_program\external\get_users_courses
 * @package    tool_program
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_external_get_users_courses_testcase extends \externallib_advanced_testcase {

    /** @var \tool_program_generator */
    protected $generator;
    /** @var \stdClass $user Currently logged user */
    private $user;
    /** @var int $defaulttenantid Default tenant id (also the tenant id of $this->loggeduser) */
    private $defaulttenantid;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->user = self::getDataGenerator()->create_user();
        self::setUser($this->user);
        $this->defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();

        $this->resetAfterTest();
    }

    public function test_execute(): void {
        $program1 = $this->generator->generate_program((object) [
            'fullname' => 'Program number 1',
            'tenantid' => $this->defaulttenantid,
        ]);
        $program2 = $this->generator->generate_program((object) [
            'fullname' => 'Program number 2',
            'tenantid' => $this->defaulttenantid,
        ]);
        $program3 = $this->generator->generate_program((object) [
            'fullname' => 'Program number 3',
            'tenantid' => $this->defaulttenantid,
        ]);

        // Add course1 to program1.
        $course1 = $this->getDataGenerator()->create_course();
        $programcourse1 = $this->generator->add_course_to_set($course1->id, $program1->get_base_set()->get('id'));

        // Add course2 to program2.
        $course2 = $this->getDataGenerator()->create_course();
        $programcourse2 = $this->generator->add_course_to_set($course2->id, $program2->get_base_set()->get('id'));

        // Add course3 to program3.
        $course3 = $this->getDataGenerator()->create_course();
        $programcourse3 = $this->generator->add_course_to_set($course3->id, $program3->get_base_set()->get('id'));

        // Allocate user into program1, program2 and program3.
        $userdata = (object) [
            'userid' => $this->user->id,
            'certificationid' => 0,
        ];
        $programuser1 = api::allocate_user($program1, $userdata);
        $programuser2 = api::allocate_user($program2, $userdata);
        $programuser3 = api::allocate_user($program3, $userdata);
        $this->generator->enrol_user_to_program_course($programcourse1, $programuser1);
        $this->generator->enrol_user_to_program_course($programcourse2, $programuser2);
        $this->generator->enrol_user_to_program_course($programcourse3, $programuser3);

        // Enrol user into two separate courses that do not belong to a program.
        $course4 = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->user->id, $course4->id);
        $course5 = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->user->id, $course5->id);

        // Test with hideprogramcourses set to false. Should return all 5 courses.
        set_config('hideprogramcourses', 0, 'theme_workplace');

        $result = get_users_courses::execute($this->user->id);
        $cleanresult = \external_api::clean_returnvalue(get_users_courses::execute_returns(), $result);

        $this->assertEqualsCanonicalizing([$course1->id, $course2->id, $course3->id, $course4->id, $course5->id],
            array_column($cleanresult, 'id'));

        // Test with hideprogramcourses set to true. Should return only course4 and course5 enrolments.
        set_config('hideprogramcourses', 1, 'theme_workplace');

        $result = get_users_courses::execute($this->user->id);
        $cleanresult = \external_api::clean_returnvalue(get_users_courses::execute_returns(), $result);

        $this->assertEqualsCanonicalizing([$course4->id, $course5->id], array_column($cleanresult, 'id'));

        // Manual enrol user into course3, which belongs also to a program.
        $this->getDataGenerator()->enrol_user($this->user->id, $course3->id);

        $result = get_users_courses::execute($this->user->id);
        $cleanresult = \external_api::clean_returnvalue(get_users_courses::execute_returns(), $result);

        $this->assertEqualsCanonicalizing([$course3->id, $course4->id, $course5->id], array_column($cleanresult, 'id'));
    }
}
