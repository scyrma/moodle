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
 * External functions for tool_program.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Mitxel Moriana
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/externallib.php");

use coding_exception;
use external_api;
use context;
use core_tag;
use moodle_url;
use tool_certification\certification;
use tool_certification\certification_completion;
use tool_certification\certification_user;
use tool_program\external\my_program_progress_exporter;
use tool_program\external\my_programs_progress_exporter;
use tool_program\external\program_course_exporter;
use tool_program\external\program_overview_course_exporter;
use tool_program\external\program_set_exporter;
use context_system;
use core_user;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use external_warnings;
use moodle_exception;
use tool_program\form\edit_program_set_completion_form;
use tool_program\persistent\program;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;
use tool_program\persistent\program_user;
use tool_program\task\reset_program;
use tool_wp\course_reset_api;

/**
 * Class external
 *
 * @package tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Mitxel Moriana
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class external extends external_api {

    /**
     * Parameters for delete_set
     *
     * @return external_function_parameters
     */
    public static function delete_set_parameters(): external_function_parameters {
        return new external_function_parameters([
            'setid' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Deletes a set.
     *
     * @param int $setid
     * @return array
     */
    public static function delete_set(int $setid): array {
        // Parameter validation.
        $params = self::validate_parameters(self::delete_set_parameters(), [
            'setid' => $setid,
        ]);
        $setid = $params['setid'];

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);
        $programset = new program_set($setid);
        $program = $programset->get_program();
        permission::require_can_edit_details($program);

        $warnings = [];
        $result = api::delete_set($programset);

        return [
            'result' => $result,
            'warnings' => $warnings
        ];
    }

    /**
     * Return for delete_set
     *
     * @return external_single_structure
     */
    public static function delete_set_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL),
            'warnings' => new external_warnings()
        ]);
    }

    /**
     * Parameters for delete_course
     *
     * @return external_function_parameters
     */
    public static function delete_course_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programcourseid' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Deletes a course from a program.
     *
     * @param int $programcourseid
     * @return array
     */
    public static function delete_course(int $programcourseid): array {
        // Parameter validation.
        $params = self::validate_parameters(self::delete_course_parameters(), [
            'programcourseid' => $programcourseid,
        ]);
        $programcourseid = $params['programcourseid'];

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);
        $programcourse = new program_course($programcourseid);
        $program = $programcourse->get_program();
        permission::require_can_edit_details($program);

        $warnings = [];
        $result = api::delete_program_course($programcourse);

        return [
            'result' => $result,
            'warnings' => $warnings
        ];
    }

    /**
     * Return for delete_course
     *
     * @return external_single_structure
     */
    public static function delete_course_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL),
            'warnings' => new external_warnings()
        ]);
    }

    /**
     * Parameters for move_program_item
     *
     * @return external_function_parameters
     */
    public static function move_program_item_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programid' => new external_value(PARAM_INT),
            'itemid' => new external_value(PARAM_INT),
            'isset' => new external_value(PARAM_BOOL),
            'sourcesetid' => new external_value(PARAM_INT),
            'targetsetid' => new external_value(PARAM_INT),
            'nextitemid' => new external_value(PARAM_INT),
            'nextisset' => new external_value(PARAM_BOOL),
        ]);
    }

    /**
     * Moves a program item.
     *
     * @param int $programid
     * @param int $itemid
     * @param bool $isset
     * @param int $sourcesetid
     * @param int $targetsetid
     * @param int $nextitemid
     * @param int $nextisset
     * @return array
     */
    public static function move_program_item(int $programid, int $itemid, bool $isset, int $sourcesetid, int $targetsetid,
        int $nextitemid, int $nextisset): array {
        global $PAGE;

        // Parameter validation.
        $params = self::validate_parameters(self::move_program_item_parameters(), [
            'programid' => $programid,
            'itemid' => $itemid,
            'isset' => $isset,
            'sourcesetid' => $sourcesetid,
            'targetsetid' => $targetsetid,
            'nextitemid' => $nextitemid,
            'nextisset' => $nextisset,
        ]);
        $programid = $params['programid'];
        $itemid = $params['itemid'];
        $isset = $params['isset'];
        $sourcesetid = $params['sourcesetid'];
        $targetsetid = $params['targetsetid'];
        $nextitemid = $params['nextitemid'];
        $nextisset = $params['nextisset'];

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);
        $program = new program($programid);
        permission::require_can_edit_details($program);
        $courses = $program->get_courses();

        $warnings = [];
        $result = [];

        $moveditems = api::move_item_to_new_position($itemid, $isset, $sourcesetid, $targetsetid, $nextitemid, $nextisset);
        $renderer = $PAGE->get_renderer('tool_program');
        foreach ($moveditems as $moveditem) {
            if ($moveditem instanceof program_set) {
                $exporter = new program_set_exporter($moveditem, ['context' => $context]);
                $exporteditem = $exporter->export($renderer);
                $exporteditem->isset = true;
            } else if ($moveditem instanceof program_course) {
                $exporter = new program_course_exporter($moveditem, [
                    'context' => $context,
                    'programid' => (int) $program->get('id'),
                    'course' => $courses[$moveditem->get('courseid')],
                ]);
                $exporteditem = $exporter->export($renderer);
                $exporteditem->isset = false;
            } else {
                throw new coding_exception('Unexpected instance of program item');
            }
            $result[] = $exporteditem;
        }

        return [
            'result' => $result,
            'warnings' => $warnings
        ];
    }

    /**
     * Return for move_program_item
     *
     * @return external_single_structure
     */
    public static function move_program_item_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT),
                'sortorder' => new external_value(PARAM_INT),
                'isset' => new external_value(PARAM_BOOL),
            ], '', VALUE_OPTIONAL), ''),
            'warnings' => new external_warnings()
        ]);
    }

    /**
     * Parameters for deallocate_user
     *
     * @return external_function_parameters
     */
    public static function deallocate_user_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programid' => new external_value(PARAM_INT),
            'userid' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Deallocates a user from a program.
     *
     * @param int $programid
     * @param int $userid
     * @return array
     */
    public static function deallocate_user(int $programid, int $userid): array {
        // Parameter validation.
        $params = self::validate_parameters(self::deallocate_user_parameters(), [
            'programid' => $programid,
            'userid' => $userid,
        ]);
        $programid = $params['programid'];
        $userid = $params['userid'];

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);
        /** @var program_user|false $programuser */
        $programuser = program_user::get_record(['programid' => $programid, 'userid' => $userid, 'certificationid' => 0]);
        permission::require_can_edit_user_allocation($programuser ?: null);

        $warnings = [];
        $result = api::deallocate_user($programid, $userid);

        return [
            'result' => $result,
            'warnings' => $warnings,
        ];
    }

    /**
     * Return for deallocate_user.
     *
     * @return external_function_parameters
     */
    public static function deallocate_user_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL),
            'warnings' => new external_warnings()
        ]);
    }

    /**
     * Parameters for potential_courses_program_selector
     *
     * @return external_function_parameters
     */
    public static function potential_courses_program_selector_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT),
        ]);
    }

    /**
     * Selector for potential courses.
     *
     * @param string $search
     * @return array
     */
    public static function potential_courses_program_selector(string $search): array {
        // Parameter validation.
        $params = self::validate_parameters(self::potential_courses_program_selector_parameters(), [
            'search' => $search,
        ]);
        $search = $params['search'];

        // We always must call validate_context in a webservice.
        $context = context_system::instance();
        self::validate_context($context);
        permission::require_can_create($context);

        $searchterms = preg_split('|\s+|', trim($search), 0, PREG_SPLIT_NO_EMPTY);
        $requiredcapabilities = [];
        $courselist = get_courses_search($searchterms, 'c.sortorder ASC', 0, 9999999, $totalcount, $requiredcapabilities);

        return $courselist;
    }

    /**
     * Return for potential_courses_program_selector
     *
     * @return external_multiple_structure
     */
    public static function potential_courses_program_selector_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(core_user::get_property_type('id'), 'ID of the course'),
            'fullname' => new external_value(core_user::get_property_type('firstname'), 'The fullname of the course'),
        ]));
    }

    /**
     * Parameters for submit_edit_program_set_completion_form
     *
     * @return external_function_parameters
     */
    public static function submit_edit_program_set_completion_form_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
            'jsonformdata' => new external_value(PARAM_RAW, 'The data from the form, encoded as a json array')
        ]);
    }

    /**
     * Process data submited from set completion form.
     *
     * @param int $contextid
     * @param string $jsonformdata
     * @return array
     */
    public static function submit_edit_program_set_completion_form(int $contextid, string $jsonformdata): array {
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_edit_program_set_completion_form_parameters(), [
            'contextid' => $contextid,
            'jsonformdata' => $jsonformdata
        ]);
        $contextid = $params['contextid'];
        $jsonformdata = $params['jsonformdata'];

        // We always must call validate_context in a webservice.
        $context = context::instance_by_id($contextid);
        self::validate_context($context);

        $serialiseddata = json_decode($jsonformdata);
        $data = [];
        parse_str($serialiseddata, $data);

        $setid = (int) $data['setid'];
        $programset = new program_set($setid);
        $program = $programset->get_program();
        permission::require_can_edit_details($program);

        $warnings = [];

        // The last param is the ajax submitted data.
        $mform = new edit_program_set_completion_form(null, ['id' => $setid], 'post', '', null, true, $data);
        if ($validateddata = $mform->get_data()) {
            $result = api::update_set_completion_criteria($validateddata);
        } else {
            throw new moodle_exception('programsetcompletionformvalidationerror', 'tool_program');
        }

        return [
            'result' => $result,
            'warnings' => $warnings
        ];
    }

    /**
     * Return for submit_edit_program_set_completion_form
     *
     * @return external_single_structure
     */
    public static function submit_edit_program_set_completion_form_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL),
            'warnings' => new external_warnings()
        ]);
    }

    /**
     * Parameters for enrol_user_to_course
     *
     * @return external_function_parameters
     */
    public static function enrol_user_to_course_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT),
            'programid' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Enrols a user to a course.
     *
     * @param int $courseid
     * @param int $programid
     * @return array
     */
    public static function enrol_user_to_course(int $courseid, int $programid): array {
        // Parameter validation.
        $params = self::validate_parameters(self::enrol_user_to_course_parameters(), [
            'courseid' => $courseid,
            'programid' => $programid
        ]);
        $courseid = $params['courseid'];
        $programid = $params['programid'];

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);
        $program = new program($programid);
        permission::require_can_self_enrol_to_course($courseid, $program);

        $res = [];
        $res['warnings'] = [];

        api::self_enrol_to_course($courseid, $programid);

        $res['status'] = true;
        $redirecturl = new moodle_url('/course/view.php', ['id' => $courseid]);
        $res['redirecturl'] = $redirecturl->out();

        return $res;
    }

    /**
     * Return for enrol_user_to_course
     *
     * @return external_single_structure
     */
    public static function enrol_user_to_course_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'True if the user is successfully enrolled.'),
            'redirecturl' => new external_value(PARAM_URL, 'URL to the course the user has just enrolled.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for update_program_visibility
     *
     * @return external_function_parameters
     */
    public static function update_program_visibility_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programid' => new external_value(PARAM_INT),
            'visibility' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Update program visibility.
     *
     * @param int $programid
     * @param int $visibility
     * @return array
     */
    public static function update_program_visibility(int $programid, int $visibility): array {
        // Parameter validation.
        $params = self::validate_parameters(self::update_program_visibility_parameters(), [
            'programid' => $programid,
            'visibility' => $visibility
        ]);
        $programid = $params['programid'];
        $visibility = $params['visibility'];

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);
        $program = new program($programid);
        permission::require_can_edit_details($program);

        $program = new program($programid);
        $result = api::update_program_visibility($program, $visibility);

        return [
            'result' => $result,
        ];
    }

    /**
     * Return for update_program_visibility
     *
     * @return external_single_structure
     */
    public static function update_program_visibility_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL),
        ]);
    }

    /**
     * Parameters for delete_program
     *
     * @return external_function_parameters
     */
    public static function delete_program_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programid' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Deletes a program.
     *
     * @param int $programid
     * @return array
     */
    public static function delete_program(int $programid): array {
        // Parameter validation.
        $params = self::validate_parameters(self::delete_program_parameters(), [
            'programid' => $programid,
        ]);
        $programid = $params['programid'];

        // We always must call validate_context in a webservice.
        $context = context_system::instance();
        self::validate_context($context);
        $program = new program($programid);
        permission::require_can_delete($program);

        $result = api::delete_program($program);

        return [
            'result' => $result,
        ];
    }

    /**
     * Return for delete_program
     *
     * @return external_single_structure
     */
    public static function delete_program_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL),
        ]);
    }

    /**
     * Parameters for archive_program
     *
     * @return external_function_parameters
     */
    public static function archive_program_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programid' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Archives or restores a program.
     *
     * @param int $programid
     * @return array
     */
    public static function archive_program(int $programid): array {
        // Parameter validation.
        $params = self::validate_parameters(self::archive_program_parameters(), [
            'programid' => $programid,
        ]);
        $programid = $params['programid'];

        // We always must call validate_context in a webservice.
        $context = context_system::instance();
        self::validate_context($context);
        $program = new program($programid);
        permission::require_can_archive($program);
        $result = api::archive_program($program);

        return [
            'result' => $result,
        ];
    }

    /**
     * Return for archive_program
     *
     * @return external_single_structure
     */
    public static function archive_program_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL),
        ]);
    }

    /**
     * Parameters for restore_program
     *
     * @return external_function_parameters
     */
    public static function restore_program_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programid' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Restores a program.
     *
     * @param int $programid
     * @return array
     */
    public static function restore_program(int $programid): array {
        // Parameter validation.
        $params = self::validate_parameters(self::restore_program_parameters(), [
            'programid' => $programid,
        ]);
        $programid = $params['programid'];

        // We always must call validate_context in a webservice.
        $context = context_system::instance();
        self::validate_context($context);
        $program = new program($programid);
        permission::require_can_restore($program);
        $result = api::restore_program($program);

        return [
            'result' => $result,
        ];
    }

    /**
     * Return for
     *
     * @return external_single_structure
     */
    public static function restore_program_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL),
        ]);
    }

    /**
     * Parameters for duplicate_program
     *
     * @return external_function_parameters
     */
    public static function duplicate_program_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programid' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Duplicates a program.
     *
     * @param int $programid
     * @return array
     */
    public static function duplicate_program(int $programid): array {
        // Parameter validation.
        $params = self::validate_parameters(self::duplicate_program_parameters(), [
            'programid' => $programid,
        ]);
        $programid = $params['programid'];

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);
        $program = new program($programid);
        permission::require_can_duplicate($program);

        $duplicatedprogram = api::duplicate_program($program);
        $duplicatedprogramid = $duplicatedprogram->get('id');
        $redirecturl = new moodle_url('/admin/tool/program/edit.php', ['id' => $duplicatedprogramid]);
        $result = !empty($duplicatedprogramid);

        return [
            'result' => $result,
            'duplicatedprogramid' => $duplicatedprogramid,
            'redirecturl' => $redirecturl->out()
        ];
    }

    /**
     * Return for duplicate_program
     *
     * @return external_single_structure
     */
    public static function duplicate_program_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL, ''),
            'duplicatedprogramid' => new external_value(PARAM_INT, ''),
            'redirecturl' => new external_value(PARAM_LOCALURL, ''),
        ]);
    }

    /**
     * Parameters for get_user_programs.
     *
     * @return external_function_parameters
     */
    public static function get_user_programs_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Get programs and courses for the current user.
     *
     * @return array
     */
    public static function get_user_programs(): array {
        global $DB, $PAGE, $USER;
        self::validate_parameters(self::get_user_programs_parameters(), []);

        $userid = (int) $USER->id;
        $context = context_system::instance();
        self::validate_context($context);

        $res = [];
        $res['warnings'] = [];

        $programs = api::get_user_accessible_programs($userid);
        $userallocations = program_user::get_records(['userid' => $userid]);
        $certifications = api::get_certifications_by_userid($userid);
        $usercertifications = certification_user::get_records(['userid' => $userid]);
        $onlyenrolprogramcourseids = api::get_course_ids_with_only_enrol_program_instance($userid);

        $programstreeprogress = [];
        foreach ($programs as $program) {
            $programstreeprogress[$program->get('id')] = new program_tree_progress($program, $userid);
        }

        // Array with all user enroled course IDs (from program enrolments and from manual enrolments).
        // This will be used to retrieve all lastaccess courses values.
        $courseids = [];

        // Get user enrolled courses using the enrol program plugin.
        // The variable $programsenrolledcourses will store all courses the user is enroled using the enrol program plugin.
        $programsenrolledcourses = [];
        foreach ($programstreeprogress as $programtreeprogress) {
            $userenrolments = $programtreeprogress->get_user_enrolments();
            $courseids = array_merge($courseids, array_column($userenrolments, 'courseid'));
            $programsenrolledcourses[$programtreeprogress->get_program()->get('id')] = $userenrolments;
        }

        // Get user enrolled courses excluding courses enrolled only with the enrol program plugin.
        // The variable $exportedcourses will store all courses the user is enroled that exclude all courses enrolled only with
        // the enrol program plugin.
        $exportedcourses = api::get_user_accessible_courses($userid);
        $coursescompletion = api::get_user_courses_completion($exportedcourses, $userid);
        $coursesprogress = api::get_user_courses_progress($userid, $exportedcourses, $coursescompletion);
        $courseids = array_merge(array_keys($exportedcourses), $courseids);

        foreach ($exportedcourses as $exportedcourse) {
            $exportedcourse->coursecompletion = $coursescompletion[$exportedcourse->id];
            $exportedcourse->coursesprogress = $coursesprogress[$exportedcourse->id];
        }

        $renderer = $PAGE->get_renderer('tool_program');
        $exporter = new my_programs_progress_exporter(null, [
            'context' => $context,
            'userid' => $userid,
            'programs' => $programs,
            'userallocations' => $userallocations,
            'certifications' => $certifications,
            'usercertifications' => $usercertifications,
            'courses' => $exportedcourses,
            'lastcourseaccess' => api::get_last_course_access($userid, $courseids),
            'programsenrolledcourses' => $programsenrolledcourses,
        ]);

        $exporteddata = $exporter->export($renderer);

        // Filter course image. If course has no image, then the exporter has added the pattern image and needs to be removed.
        $courses = (array) $exporteddata->courses;
        foreach ($courses as $course) {
            if (strpos($course->image, 'http') !== 0) {
                $course->image = '';
            }
        }

        // Related certification allocations messages for each program.
        $origins = [];
        $params = ['userid' => $userid, 'timerevoked' => 0, 'islast' => 1];
        $certificationcompletions = $DB->get_records(certification_completion::TABLE, $params, '',
            'certificationid,expirydate');
        foreach ($exporteddata->programs as $program) {
            foreach ($program->allocations as $allocation) {
                if ($allocation->certificationid > 0 && (int)$allocation->status === 1) {
                    $origin = [];
                    $completion = $certificationcompletions[$allocation->certificationid] ?? null;
                    $status = \tool_certification\api::get_user_allocation_status($allocation->certificationid, $userid);
                    if ($status[0]['status'] == constants::STATUS_SUSPENDED) {
                        continue;
                    }

                    [$origin['alert'], $origin['stringid'], $origin['component'], $origin['date']] =
                        self::get_certification_allocation_messages($status[0]['status'], $completion, $allocation->duedate);

                    $origin['programid'] = $allocation->programid;
                    $origin['certificationid'] = $allocation->certificationid;
                    $origins[] = $origin;
                }
            }
        }

        $res['programs'] = (array) $exporteddata->programs;
        $res['courses'] = $courses;
        $res['onlyenrolprogramcourseids'] = $onlyenrolprogramcourseids;
        $res['status'] = true;
        $res['origins'] = $origins;

        return $res;
    }

    /**
     * Generates all certification allocation messages to show on programs.
     *
     * @param int $status
     * @param null|\stdClass $completion
     * @param int $allocationduedate
     * @return array
     */
    private static function get_certification_allocation_messages(int $status, ?\stdClass $completion,
                                                                  int $allocationduedate): array {
        $component = 'tool_program';
        $date = 0;
        switch ($status) {
            case \tool_certification\constants::STATUS_EXPIRED:
                $date = $completion->expirydate;
                $alertstyle = 'danger';
                $stringid = 'certificationmsgexpired';
                break;
            case \tool_certification\constants::STATUS_CERTIFIED:
                if (0 === (int) $completion->expirydate) {
                    $stringid = 'certificationmsgcompleted';
                } else {
                    $date = $completion->expirydate;
                    $stringid = 'certificationmsgcompletedexpired';
                }
                $alertstyle = 'success';
                break;
            case \tool_certification\constants::STATUS_OPEN:
                if ($allocationduedate === \tool_certification\constants::DATE_NONE) {
                    $stringid = 'certificationmsgduedatenotset';
                } else {
                    $date = $allocationduedate;
                    $stringid = 'certificationmsgactive';
                }
                $alertstyle = 'info';
                break;
            case \tool_certification\constants::STATUS_OVERDUE:
            default:
                $date = $allocationduedate;
                $alertstyle = 'warning';
                $stringid = 'certificationmsgoverdue';
                break;
        }

        return [$alertstyle, $stringid, $component, $date];
    }

    /**
     * Returned parameters for get_user_programs.
     *
     * @return external_single_structure
     */
    public static function get_user_programs_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'True if user programs successfully returned.'),
            'programs' => new external_multiple_structure(my_program_progress_exporter::get_read_structure()),
            'courses' => new external_multiple_structure(program_overview_course_exporter::get_read_structure()),
            'onlyenrolprogramcourseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course id'), 'Array of course ids', VALUE_DEFAULT, []
            ),
            'warnings' => new external_warnings(),
            'origins' => new external_multiple_structure(
                new external_single_structure([
                    'programid' => new external_value(PARAM_INT, ''),
                    'certificationid' => new external_value(PARAM_INT, ''),
                    'alert' => new external_value(PARAM_TEXT, ''),
                    'stringid' => new external_value(PARAM_TEXT, ''),
                    'component' => new external_value(PARAM_TEXT, ''),
                    'date' => new external_value(PARAM_INT, ''),
                ])
            )
        ]);
    }

    /**
     * Reset program progress parameters
     *
     * @return external_function_parameters
     */
    public static function reset_program_progress_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programuserid' => new external_value(PARAM_INT, 'Program user id'),
        ]);
    }

    /**
     * Reset program progress external function.
     *
     * @param int $programuserid
     * @return array
     */
    public static function reset_program_progress(int $programuserid): array {
        global $USER;
        // Parameter validation.
        $params = self::validate_parameters(self::reset_program_progress_parameters(), [
            'programuserid' => $programuserid,
        ]);
        $programuserid = $params['programuserid'];

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);
        $programuser = new program_user($programuserid);
        permission::require_can_reset_progress($programuser, true);

        $resettask = new reset_program();
        $resettask->set_custom_data(array(
            'programid' => $programuser->get('programid'),
            'userid' => $programuser->get('userid'),
            'marknotcompleted' => true,
            'resetcourses' => true,
        ));
        $resettask->set_component('tool_program');
        $resettask->set_userid($USER->id);
        \core\task\manager::queue_adhoc_task($resettask);

        return [
            'result' => true
        ];
    }

    /**
     * Reset program progress returns
     *
     * @return external_single_structure
     */
    public static function reset_program_progress_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL),
        ]);
    }

    /**
     * Parameters for the program selector WS.
     * @return external_function_parameters
     */
    public static function potential_program_selector_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_NOTAGS, 'Search string', VALUE_REQUIRED),
        ]);
    }

    /**
     * Program selector.
     *
     * @param string $search
     * @return array
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \restricted_context_exception
     */
    public static function potential_program_selector(string $search): array {
        $params = self::validate_parameters(self::potential_program_selector_parameters(),
            ['search' => $search]);
        $search = $params['search'];

        // We always must call validate_context in a webservice.
        $context = context_system::instance();
        self::validate_context($context);

        return api::get_potential_programs($search);
    }

    /**
     * Return for program selector.
     * @return external_multiple_structure
     */
    public static function potential_program_selector_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'ID of the program'),
            'fullname' => new external_value(PARAM_TEXT, 'The fullname of the program'),
        ]));
    }

    /**
     * Parameters for get_user_learning_statuses
     *
     * @return external_function_parameters
     */
    public static function get_user_learning_statuses_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Returns the list of programs for a user and the current status in each one
     *
     * @param int $userid
     * @return array
     */
    public static function get_user_learning_statuses(int $userid): array {
        global $USER;
        // Parameter validation.
        $params = self::validate_parameters(self::get_user_learning_statuses_parameters(), [
            'userid' => $userid,
        ]);
        $userid = $params['userid'];

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);

        // Check that current user has permission to view this users learning statuses.
        permission::require_can_view_user_programs_progress($userid);

        // Retrieve direct program allocations.
        $programs = [];
        $userallocations = program_user::get_records(['userid' => $userid, 'certificationid' => 0]);
        foreach ($userallocations as $allocation) {
            $element = [];
            $element['id'] = $allocation->get('programid');

            $program = new program($allocation->get('programid'));
            $element['fullname'] = $program->get_formatted_name();

            // One allocation can have status 'Completed' and 'Suspended' at the same time.
            $statuses = \tool_program\api::get_user_allocation_statuses($allocation->get('programid'), $userid, 0);
            foreach ($statuses as $status) {
                $element['statuses'][]['stringid'] = $status['stringid'];
            }
            $programs[] = $element;
        }

        // Retrieve certification program allocations.
        $certifications = [];
        $hasexpiredcertifications = false;
        $userallocations = certification_user::get_records(['userid' => $userid]);
        foreach ($userallocations as $allocation) {
            $element = [];
            $element['id'] = $allocation->get('certificationid');

            $certification = new certification($allocation->get('certificationid'));
            $element['fullname'] = $certification->get_formatted_name();

            // One allocation can have status 'Completed' and 'Suspended' at the same time.
            $statuses = \tool_certification\api::get_user_allocation_status($allocation->get('certificationid'),
                $userid);

            foreach ($statuses as $status) {
                $element['statuses'][]['stringid'] = $status['stringid'];

                if ($status['status'] == \tool_certification\constants::STATUS_EXPIRED) {
                    $hasexpiredcertifications = true;
                }
            }

            $certifications[] = $element;
        }

        return [
            'result' => true,
            'programs' => $programs,
            'certifications' => $certifications,
            'hasexpiredcertifications' => (int)$hasexpiredcertifications,
        ];
    }

    /**
     * Return for get_user_learning_statuses
     *
     * @return external_single_structure
     */
    public static function get_user_learning_statuses_returns(): external_single_structure {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL, ''),
            'programs' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'The id of the program'),
                'fullname' => new external_value(PARAM_TEXT, 'The fullname of the program'),
                'statuses' => new external_multiple_structure(new external_single_structure([
                    'stringid' => new external_value(PARAM_TEXT, 'Status string id'),
                ]))
            ])),
            'certifications' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'The id of the certification'),
                'fullname' => new external_value(PARAM_TEXT, 'The fullname of the certification'),
                'statuses' => new external_multiple_structure(new external_single_structure([
                    'stringid' => new external_value(PARAM_TEXT, 'Status string id'),
                ]))
            ])),
            'hasexpiredcertifications' => new external_value(PARAM_INT, ''),
        ]);
    }

    /**
     * Parameters for deallocate_user
     *
     * @return external_function_parameters
     */
    public static function bulk_deallocate_user_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programuserids' => new external_multiple_structure(new external_value(PARAM_INT)),
        ]);
    }

    /**
     * Deallocates a user from a program.
     *
     * @param array $programuserids
     * @return array
     */
    public static function bulk_deallocate_user(array $programuserids): array {
        // Parameter validation.
        $params = self::validate_parameters(self::bulk_deallocate_user_parameters(), [
            'programuserids' => $programuserids,
        ]);
        $programuserids = $params['programuserids'];

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);
        $successcount = 0;
        $skippedcount = 0;

        foreach ($programuserids as $programuserid) {
            $programuser = new program_user($programuserid);
            if (!$programuser || !permission::can_delete_user_allocation($programuser)) {
                $skippedcount++;
            } else {
                api::deallocate_user($programuser->get('programid'), $programuser->get('userid'));
                $successcount++;
            }
        }

        return [
            'successcount' => $successcount,
            'skippedcount' => $skippedcount,
        ];
    }

    /**
     * Return for deallocate_user.
     *
     * @return external_function_parameters
     */
    public static function bulk_deallocate_user_returns(): external_single_structure {
        return new external_single_structure([
            'successcount' => new external_value(PARAM_INT),
            'skippedcount' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Reset program progress parameters
     *
     * @return external_function_parameters
     */
    public static function bulk_reset_program_progress_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programuserids' => new external_multiple_structure(new external_value(PARAM_INT)),
        ]);
    }

    /**
     * Reset program progress external function.
     *
     * @param array $programuserids
     * @return array
     */
    public static function bulk_reset_program_progress(array $programuserids): array {
        global $USER;
        // Parameter validation.
        $params = self::validate_parameters(self::bulk_reset_program_progress_parameters(), [
            'programuserids' => $programuserids,
        ]);
        $programuserids = $params['programuserids'];

        $successcount = 0;
        $skippedcount = 0;

        // From web services we don't call require_login(), but rather validate_context.
        $context = context_system::instance();
        self::validate_context($context);

        foreach ($programuserids as $programuserid) {
            $programuser = new program_user($programuserid);

            if (!$programuser || !permission::can_reset_progress($programuser, true)) {
                $skippedcount++;
            } else {
                $resettask = new reset_program();
                $resettask->set_custom_data(array(
                    'programid' => $programuser->get('programid'),
                    'userid' => $programuser->get('userid'),
                    'marknotcompleted' => true,
                    'resetcourses' => true
                ));
                $resettask->set_component('tool_program');
                $resettask->set_userid($USER->id);
                \core\task\manager::queue_adhoc_task($resettask);
                $successcount++;
            }
        }

        return [
            'successcount' => $successcount,
            'skippedcount' => $skippedcount,
        ];
    }

    /**
     * Reset program progress returns
     *
     * @return external_single_structure
     */
    public static function bulk_reset_program_progress_returns(): external_single_structure {
        return new external_single_structure([
            'successcount' => new external_value(PARAM_INT),
            'skippedcount' => new external_value(PARAM_INT),
        ]);
    }
}
