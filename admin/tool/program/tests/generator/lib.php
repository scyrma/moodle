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
 * Generator for tool_program
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\constants;
use tool_program\persistent\program;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;
use tool_program\persistent\program_user;
use tool_tenant\tenancy;

/**
 * Class tool_program_generator
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_generator extends testing_module_generator {

    /**
     * Create a program
     *
     * Used in Behat step 'Given the following "tool_program > programs" exist:'.
     *
     * @param array $record
     * @return program
     * @throws coding_exception
     */
    public function create_program(array $record): program {

        $data = (object) [
            'fullname' => 'A program name',
            'tenantid' => tenancy::get_default_tenant_id(),
            'description' => 'A program description',
            'descriptionformat' => FORMAT_HTML,
            'archived' => 0,
            'visible' => constants::VISIBILITY_AVAILABLE,
            'allocationenddaterelative' => null,
            'allowdirectallocation' => 1,
            'autocreategroups' => \tool_program\api::GROUPS_TENANT,
            'program_tags' => ['hello', 'world'],
            'shared' => 0
        ];

        if (isset($record['program_tags']) && !is_array($record['program_tags'])) {
            $record['program_tags'] = explode(',', $record['program_tags']);
        }
        $record = $this->get_date_constant($record, 'enddatetype');
        $record = $this->get_date_constant($record, 'duedatetype');
        $record = $this->get_date_constant($record, 'startdatetype');

        $mergeddata = array_merge((array) $data, (array) $record);
        $program = \tool_program\api::create_program((object)$mergeddata);

        // Some properties like 'completioncriteria' and 'completionatleast' apply to the base set and not the program.
        if (isset($record['completioncriteria'])) {
            $record['setid'] = $program->get_base_set()->get('id');
            \tool_program\api::update_set_completion_criteria((object)$record);
        }

        if (!empty($record['generatecourses'])) {
            for ($i = 0; $i < $record['generatecourses']; $i++) {
                $course = $this->generate_course_with_completion_self();
                $this->add_course_to_set($course->id, $program->get_base_set()->get('id'));
            }
        }

        return $program;
    }

    /**
     * Convert the date constant name to its value
     *
     * @param array $data
     * @param string $fieldname
     * @return array
     */
    protected function get_date_constant(array $data, string $fieldname): array {
        if (!empty($data[$fieldname]) && !is_numeric($data[$fieldname])) {
            $data[$fieldname] = constant('\tool_program\constants::DATE_' . strtoupper($data[$fieldname]));
        }
        return $data;
    }

    /**
     * Create a program user allocation
     *
     * Used in Behat step 'Given the following "tool_program > program_users" exist:'.
     *
     * @param array $record
     * @return program_user
     */
    public function create_program_user(array $record): program_user {
        $programuserdata = $this->get_dummy_program_user_data($record);
        return \tool_program\api::allocate_user(new program($record['programid']), $programuserdata);
    }

    /**
     * Create a program course
     *
     * Used in Behat step 'Given the following "tool_program > program_courses" exist:'.
     *
     * @param array $record
     * @return program_course
     */
    public function create_program_course(array $record): program_course {
        return \tool_program\api::add_course_to_base_set($record['programid'], $record['courseid']);
    }

    /**
     * Create a program user allocation completion
     *
     * Used in Behat step 'Given the following "tool_program > program_completions" exist:'.
     *
     * @param array $record
     * @return void
     */
    public function create_program_completion(array $record): void {
        $program = new program($record['programid']);
        $this->complete_program($program, $record['userid']);
    }

    /**
     * Returns test data to create a program.
     *
     * @param bool $withdescriptioneditor
     * @return stdClass
     */
    public function get_dummy_program_data(bool $withdescriptioneditor = false): stdClass {
        $data = (object) [
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
            'autocreategroups' => \tool_program\api::GROUPS_TENANT,
            'program_tags' => ['hello', 'world'],
            'shared' => 0
        ];

        if ($withdescriptioneditor) {
            $this->add_program_description_editor($data);
        }

        return $data;
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
     * @param array $overrides any overrides (userid, programid, certificationid, etc)
     * @return stdClass
     */
    public function get_dummy_program_user_data(array $overrides = []): stdClass {
        // Remove empty associative array entries.
        $overrides = array_filter($overrides, function($value) {
            return ''.$value !== '';
        });

        return (object)( $overrides + [
            'programid' => 1,
            'userid' => 1,
            'certificationid' => 0,
            'allocationtype' => constants::ALLOCATION_MANUAL,
            'startdate' => strtotime('-7 day'),
            'duedate' => strtotime('+7 day'),
            'enddate' => strtotime('+7 day'),
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ]);
    }

    /**
     * Generates a program.
     *
     * @param stdClass|null $programdata
     * @param bool $withdescriptioneditor Adds description editor to programdata
     * @return program
     */
    public function generate_program(stdClass $programdata = null, bool $withdescriptioneditor = false): program {
        $mergeddata = array_merge((array) $this->get_dummy_program_data($withdescriptioneditor), (array) $programdata);
        return \tool_program\api::create_program((object)$mergeddata);
    }

    /**
     * Generates a program with a single course with self-completion inside it
     *
     * @param stdClass|null $programdata
     * @return program
     */
    public function generate_program_with_course(stdClass $programdata = null): program {
        $program = $this->generate_program($programdata);
        $course = $this->generate_course_with_completion_self();
        $this->add_course_to_set($course->id, $program->get_base_set()->get('id'));
        return $program;
    }

    /**
     * Generates a set.
     *
     * @param stdClass $setdata must have either parent or programid attribute, also optional: name,
     *      completioncriteria, completionatleast, sortorder
     * @return program_set
     */
    public function generate_set(stdClass $setdata): program_set {
        $setdata = (object)(array)$setdata;

        if (empty($setdata->parent) && empty($setdata->programid)) {
            throw new coding_exception('Parent or programid must be specified');
        }
        if (empty($setdata->name)) {
            static $namecounter = 1;
            $setdata->name = 'Set name '.($namecounter++);
        }
        if (empty($setdata->parent)) {
            $setdata->parent = (new program($setdata->programid))->get_base_set()->get('id');
        }
        $completion = ['completioncriteria' => program_set::COMPLETION_ALL_IN_ORDER, 'completionatleast' => 1];
        $completion = array_intersect_key((array)$setdata, $completion) + $completion;
        $set = \tool_program\api::add_set_to_parent_set($setdata->parent, $setdata->name, (object)$completion);
        if (isset($setdata->sortorder)) {
            $set->set('sortorder', $setdata->sortorder);
            $set->save();
        }
        return $set;
    }

    /**
     * Generates a course with completion criteria self enabled.
     *
     * @param array|stdClass $record
     * @return stdClass
     */
    public function generate_course_with_completion_self($record = null): stdClass {
        global $CFG;

        require_once("{$CFG->libdir}/completionlib.php");
        require_once($CFG->dirroot . '/course/lib.php');
        $record = $record ? (array)$record : [];
        $criteriadata = new stdClass();
        $criteriadata->criteria_self = COMPLETION_CRITERIA_TYPE_SELF;
        $course = \testing_util::get_data_generator()->create_course(
            array_merge($record, ['enablecompletion' => COMPLETION_ENABLED])
        );
        $criteriadata->id = $course->id;

        /** @var completion_criteria_self $coursecriterion */
        $coursecriterion = completion_criteria::factory(['criteriatype' => COMPLETION_CRITERIA_TYPE_SELF]);
        $coursecriterion->update_config($criteriadata);

        return $course;
    }

    /**
     * Complete a list of courses for a userid.
     * The courses are completed following the array order.
     *
     * @param array $coursesids
     * @param int $userid
     */
    public function complete_courses(array $coursesids, int $userid): void {
        foreach ($coursesids as $courseid) {
            $ccompletion = new completion_completion(array('course' => $courseid, 'userid' => $userid));
            $ccompletion->mark_complete();
        }
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
        $programcourse = \tool_program\api::add_course_to_parent_set($setid, $courseid);
        $programcourse->set('sortorder', $sortorder);
        $programcourse->update();
        return $programcourse;
    }

    /**
     * Completes a program for a user by marking all courses in the program as completed
     *
     * @param program $program
     * @param int $userid
     * @param int $completedate
     */
    public function complete_program(program $program, int $userid, int $completedate = null): void {
        $courseids = array_column($program->get_courses(), 'id');
        if (!$courseids) {
            throw new coding_exception('Program does not have any courses');
        }
        $this->complete_courses($courseids, $userid);
    }

    /**
     * Allocates a user to a program (= creates a program user).
     *
     * @param int $programid
     * @param int $userid
     * @param int $certificationid
     * @param array $params additional attributes for the user allocation
     * @return program_user
     */
    public function allocate_user_to_program(int $programid, int $userid, int $certificationid = 0,
                                             array $params = []): program_user {
        $programuserdata = $this->get_dummy_program_user_data(
            ['userid' => $userid, 'certificationid' => $certificationid, 'programid' => $programid] + $params);
        return \tool_program\api::allocate_user(new program($programid), $programuserdata);
    }

    /**
     * Allocates a user to a program (= creates a program user).
     *
     * @param int $programid
     * @param array $userids
     * @param int $certificationid
     * @return program_user[]
     */
    public function allocate_users_to_program(int $programid, array $userids, int $certificationid = 0): array {
        $rv = [];
        foreach ($userids as $userid) {
            $rv[$userid] = $this->allocate_user_to_program($programid, $userid, $certificationid);
        }
        return $rv;
    }

    /**
     * Enrols user in a program course.
     *
     * @param program_course $programcourse
     * @param program_user $programuser
     */
    public function enrol_user_to_program_course(program_course $programcourse, program_user $programuser): void {
        $userid = $programuser->get('userid');
        $programid = $programcourse->get_program()->get('id');
        \tool_program\api::enrol_in_program_course($programid, $programcourse->get_course(), $userid);
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
     * Creates a program with sets and courses with a user in same tenant.
     *
     * @param bool $markcompleted
     * @return stdClass
     */
    public function generate_program_filled_with_user_completion(bool $markcompleted = true): stdClass {
        $user = $this->datagenerator->create_user();

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
        $program = $this->generate_program($programdata);
        $programid = $program->get('id');
        $baseset = $program->get_base_set();
        $basesetid = $baseset->get('id');
        $parentset = $this->generate_set((object) ['programid' => $programid, 'parent' => $basesetid]);
        $parentsetid = $parentset->get('id');
        $course1 = $this->generate_course_with_completion_self();
        $course1id = $course1->id;
        $this->add_course_to_set($course1id, $parentsetid);
        $childset = $this->generate_set((object) ['programid' => $programid, 'parent' => $parentsetid]);
        $childsetid = $childset->get('id');
        $course2 = $this->generate_course_with_completion_self();
        $course2id = $course2->id;
        $this->add_course_to_set($course2id, $childsetid);

        if ($markcompleted) {
            $this->complete_courses([$course1->id, $course2->id], $user->id);
        }

        return (object) [
            'program' => $program,
            'baseset' => $baseset,
            'parentset' => $parentset,
            'childset' => $childset,
            'course1' => $course1,
            'course2' => $course2,
            'user' => $user,
        ];
    }
}
