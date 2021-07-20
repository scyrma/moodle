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
 * File containing tests for permission class
 *
 * @package     tool_datastore
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_datastore\permission;

/**
 * Test class
 *
 * @package     tool_datastore
 * @group       tool_datastore
 * @category    test
 * @covers      \tool_datastore\permission
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_datastore_permission_testcase extends advanced_testcase {

    /** @var stdClass $course */
    protected $course;

    /** @var stdClass $teacher */
    protected $teacher;

    /** @var stdClass $student */
    protected $student;

    /** @var stdClass $tenant */
    protected $tenant;

    /**
     * Returns the tenant generator
     *
     * @return tool_tenant_generator
     */
    protected function get_tenantgenerator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Test setup
     */
    public function setUp(): void {
        $this->resetAfterTest();

        // Create our test course and users.
        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->student = $this->getDataGenerator()->create_user();

        $this->setUser($this->teacher);

        // Create test tenant, allocate teacher to it.
        $this->tenant = $this->get_tenantgenerator()->create_tenant();
        $this->get_tenantgenerator()->allocate_user($this->teacher->id, $this->tenant->id);
    }

    /**
     * Test class can_upload_course_completion method with user from same tenant
     */
    public function test_can_upload_course_completion_same_tenant(): void {
        $this->get_tenantgenerator()->allocate_user($this->student->id, $this->tenant->id);

        $this->assertTrue(permission::can_upload_course_completion($this->student, $this->course));
    }

    /**
     * Test class can_upload_course_completion method with user from different tenant
     */
    public function test_can_upload_course_completion_different_tenant(): void {
        $newtenant = $this->get_tenantgenerator()->create_tenant();
        $this->get_tenantgenerator()->allocate_user($this->student->id, $newtenant->id);

        $this->assertFalse(permission::can_upload_course_completion($this->student, $this->course));
    }

    /**
     * Test class can_upload_course_completion method with user from different tenant as admin
     */
    public function test_can_upload_course_completion_different_tenant_admin(): void {
        $this->setAdminUser();

        $newtenant = $this->get_tenantgenerator()->create_tenant();
        $this->get_tenantgenerator()->allocate_user($this->student->id, $newtenant->id);

        $this->assertTrue(permission::can_upload_course_completion($this->student, $this->course));
    }

    /**
     * Test class can_upload_course_completion method with overridden capability
     */
    public function test_can_upload_course_completion_prevent_capability(): void {
        global $DB;

        $this->get_tenantgenerator()->allocate_user($this->student->id, $this->tenant->id);

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $context = context_course::instance($this->course->id);
        role_change_permission($roleid, $context, 'tool/datastore:uploadcoursecompletion', CAP_PREVENT);

        $this->assertFalse(permission::can_upload_course_completion($this->student, $this->course));
    }
}
