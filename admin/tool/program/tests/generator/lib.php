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
 * Generator for tool_program
 *
 * @package   tool_program
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use tool_program\constants;
use tool_program\persistent\program;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;
use tool_program\persistent\program_set_completion;
use tool_program\persistent\program_user;
use tool_tenant\tenancy;

/**
 * Class tool_program_generator
 *
 * @package   tool_program
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_program_generator extends testing_module_generator {
    /**
     * Returns test data to create a program.
     *
     * @return stdClass
     */
    public function get_dummy_program_data(): stdClass {
        return (object) [
            'fullname' => 'A program name',
            'tenantid' => tenancy::get_default_tenant_id(),
            'idnumber' => '1',
            'description' => 'A program description',
            'descriptionformat' => FORMAT_HTML,
            'archived' => 0,
            'visible' => constants::VISIBILITY_AVAILABLE,
            'startdatetype' => constants::DATE_ABSOLUTE,
            'startdateabsolute' => strtotime('-7 day'),
            'startdaterelative' => null,
            'enddatetype' => constants::DATE_ABSOLUTE,
            'enddateabsolute' => strtotime('+7 day'),
            'enddaterelative' => null,
            'duedatetype' => constants::DATE_ABSOLUTE,
            'duedateabsolute' => strtotime('+7 day'),
            'duedaterelative' => null,
            'allocationstartdatetype' => constants::DATE_ABSOLUTE,
            'allocationstartdateabsolute' => strtotime('-7 day'),
            'allocationenddatetype' => constants::DATE_ABSOLUTE,
            'allocationenddateabsolute' => strtotime('+7 day'),
            'allocationenddaterelative' => null,
            'allowdirectallocation' => 1,
        ];
    }

    /**
     * Add dummy tags to program data.
     *
     * @param stdClass $programdata
     * @return void
     */
    public function add_program_description_editor(stdClass $programdata): void {
        $programdata->description_editor = [
            'itemid' => 1,
            'text' => $programdata->description ?? 'A program description',
            'format' => $programdata->descriptionformat ?? FORMAT_HTML,
        ];
        unset($programdata->description, $programdata->descriptionformat);
    }

    /**
     * Add dummy tags to program data.
     *
     * @param stdClass $programdata
     * @return void
     */
    public function add_dummy_program_tags(stdClass $programdata): void {
        $programdata->program_tags = [
            'hello',
            'world',
        ];
    }

    /**
     * Returns test data to create a set.
     *
     * @return stdClass
     */
    public function get_dummy_program_set_data(): stdClass {
        return (object) [
            'programid' => 1,
            'name' => 'A set name',
            'parent' => 1,
            'sortorder' => 1,
            'completioncriteria' => program_set::COMPLETION_ALL_IN_ORDER,
            'completionatleast' => 1
        ];
    }

    /**
     * Returns test data to create a program user.
     *
     * @return stdClass
     */
    public function get_dummy_program_user_data(): stdClass {
        return (object) [
            'programid' => 1,
            'userid' => 1,
            'certificationid' => 1,
            'allocationtype' => constants::ALLOCATION_MANUAL,
            'startdate' => strtotime('-7 day'),
            'duedate' => strtotime('+7 day'),
            'enddate' => strtotime('+7 day'),
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
    }

    /**
     * Generates a program.
     *
     * @param stdClass $programdata
     * @return program
     */
    public function generate_program(stdClass $programdata = null): program {
        $mergeddata = array_merge((array) $this->get_dummy_program_data(), (array) $programdata);
        $program = new program(0, (object) $mergeddata);
        $program->create();

        return $program;
    }

    /**
     * Generates a program with a base set.
     *
     * @param stdClass $programdata
     * @return program
     */
    public function generate_program_with_base_set(stdClass $programdata = null): program {
        $program = $this->generate_program($programdata);
        $this->generate_base_set($program->get('id'));

        return $program;
    }

    /**
     * Generates a set.
     *
     * @param stdClass $setdata
     * @return program_set
     */
    public function generate_set(stdClass $setdata = null): program_set {
        $mergeddata = array_merge((array) $this->get_dummy_program_set_data(), (array) $setdata);
        $set = new program_set(0, (object) $mergeddata);
        $set->create();

        return $set;
    }

    /**
     * Generates a base set.
     *
     * @param int $programid
     * @return program_set
     */
    public function generate_base_set(int $programid): program_set {
        // We create a base set (parent=0).
        return $this->generate_set((object) [
            'programid' => $programid,
            'parent' => 0,
            'name' => 'A base set',
            'sortorder' => 1,
        ]);
    }

    /**
     * Adds course to a set.
     *
     * @param int $courseid
     * @param int $setid
     * @param int $sortorder
     * @return program_course
     */
    public function add_course_to_set(int $courseid, int $setid, int $sortorder = 1): program_course {
        // Now we can create the course within the set.
        $programcourse = new program_course(0, (object) [
            'setid' => $setid,
            'courseid' => $courseid,
            'sortorder' => $sortorder,
        ]);
        $programcourse->create();

        return $programcourse;
    }

    /**
     * Enables a program enrol instance for the given program course.
     *
     * @param program_course $programcourse
     */
    public function enable_program_enrol_instance(program_course $programcourse): void {
        global $DB;
        $enrolplugin = enrol_get_plugin('program');
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $program = $programcourse->get_program();
        $course = $programcourse->get_course();
        $enrolplugin->add_instance($course, ['customint1' => $program->get('id'), 'roleid' => $studentrole->id]);
    }

    /**
     * Completes a program.
     *
     * @param program $program
     * @param int $userid
     */
    public function complete_program(program $program, int $userid): void {
        $setid = $program->get_base_set()->get('id');
        $this->complete_set($setid, $userid);
    }

    /**
     * Completes a set.
     *
     * @param int $setid
     * @param int $userid
     */
    public function complete_set(int $setid, int $userid): void {
        $setcompletion = new program_set_completion(0, (object) [
            'setid' => $setid,
            'userid' => $userid,
            'completeddate' => time(),
        ]);
        $setcompletion->create();
    }

    /**
     * Allocates a user to a program (= creates a program user).
     *
     * @param int $programid
     * @param int $userid
     * @param int $certificationid
     * @return program_user
     */
    public function allocate_user_to_program(int $programid, int $userid, int $certificationid = 0): program_user {
        $programuserdata = $this->get_dummy_program_user_data();
        $programuserdata->programid = $programid;
        $programuserdata->userid = $userid;
        $programuserdata->certificationid = $certificationid;
        $programuser = new program_user(0, $programuserdata);
        $programuser->create();

        return $programuser;
    }

    /**
     * Enables a program enrol instance for the course and program related to the given program course and program user.
     *
     * @param program_course $programcourse
     * @param program_user $programuser
     */
    public function enrol_user_to_program_course(program_course $programcourse, program_user $programuser): void {
        global $DB;

        $userid = $programuser->get('userid');
        $courseid = $programcourse->get_course()->id;
        $programid = $programcourse->get_program()->get('id');
        $params = ['courseid' => $courseid, 'enrol' => 'program', 'customint1' => $programid];
        $enrolinstance = $DB->get_record('enrol', $params, '*', MUST_EXIST);
        /** @var enrol_program_plugin $enrolplugin */
        $enrolplugin = enrol_get_plugin('program');
        $enrolplugin->enrol_user_by_id($enrolinstance, ['programid' => $programid, 'userid' => $userid]);
    }

    /**
     * Assigns edit capability.
     * @param int $userid
     * @param context $context
     * @return void
     */
    public function assign_edit_capability(int $userid, context $context): void {
        // We assign capability to user.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('tool/program:edit', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $userid, $context->id);
    }

    /**
     * Assigns allocateuser capability.
     * @param int $userid
     * @param context $context
     * @return void
     */
    public function assign_allocateuser_capability(int $userid, context $context): void {
        // We assign capability to user.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('tool/program:allocateuser', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $userid, $context->id);
    }

    /**
     * Creates tenant and assigns user.
     * @return object
     */
    public function create_tenant_and_user() {
        // We retrieve default tenant.
        $defaulttenantid = tenancy::get_default_tenant_id();
        // Create one user, he will be allocated to default tenant.
        $user = phpunit_util::get_data_generator()->create_user();
        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = phpunit_util::get_data_generator()->get_plugin_generator('tool_tenant');
        // Create one more tenant.
        $othertenant = $tenantgenerator->create_tenant([]);
        return (object)[
            'user' => $user,
            'defaulttenantid' => $defaulttenantid,
            'othertenantid' => $othertenant->id
        ];
    }

    /**
     * Creates a program with sets and courses with a user in same tenant.
     *
     * @param bool $markcompleted
     * @return stdClass
     */
    public function generate_program_filled_with_user_completion(bool $markcompleted = true): stdClass {
        $data = $this->create_tenant_and_user();

        /*
         * Situation:
         *
         * 1 Base set
         *      - 1 parentset
         *          - 1 course1
         *          - 2 childset
         *              - 1 course2
         */

        $programdata = $this->get_dummy_program_data();
        $programdata->tenantid = $data->defaulttenantid;
        $program = $this->generate_program_with_base_set($programdata);
        $programid = $program->get('id');
        $baseset = $program->get_base_set();
        $basesetid = $baseset->get('id');
        $parentset = $this->generate_set((object) ['programid' => $programid, 'parent' => $basesetid]);
        $parentsetid = $parentset->get('id');
        $course1 = phpunit_util::get_data_generator()->create_course();
        $course1id = $course1->id;
        $this->add_course_to_set($course1id, $parentsetid);
        $childset = $this->generate_set((object) ['programid' => $programid, 'parent' => $parentsetid]);
        $childsetid = $childset->get('id');
        $course2 = phpunit_util::get_data_generator()->create_course();
        $course2id = $course2->id;
        $this->add_course_to_set($course2id, $childsetid);

        if ($markcompleted) {
            $this->complete_set($childsetid, $data->user->id);
            $this->complete_set($parentsetid, $data->user->id);
            $this->complete_set($basesetid, $data->user->id);
        }

        return (object) [
            'program' => $program,
            'baseset' => $baseset,
            'parentset' => $parentset,
            'childset' => $childset,
            'course1' => $course1,
            'course2' => $course2,
            'user' => $data->user,
            'defaulttenantid' => $data->defaulttenantid,
            'othertenantid' => $data->othertenantid
        ];
    }
}
