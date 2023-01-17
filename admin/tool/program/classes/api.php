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
 * Api class for tool_program
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program;

use calendar_event;
use coding_exception;
use context;
use context_course;
use context_system;
use core\message\message;
use core_completion\progress;
use core_geopattern;
use core_reportbuilder\local\helpers\database;
use core_tag_tag;
use core_user;
use enrol_program_plugin;
use html_writer;
use moodle_exception;
use stdClass;
use tool_certification\certification;
use tool_certification\certification_user;
use tool_program\customfield\program_handler;
use tool_program\event\program_course_created;
use tool_program\event\program_course_deleted;
use tool_program\event\program_course_updated;
use tool_program\event\program_created;
use tool_program\event\program_set_created;
use tool_program\event\program_deleted;
use tool_program\event\program_updated;
use tool_program\event\program_set_deleted;
use tool_program\event\program_set_updated;
use tool_program\event\user_allocation_created;
use tool_program\event\user_allocation_deleted;
use tool_program\external\program_tree_progress_exporter;
use tool_program\form\edit_program_details_form;
use tool_program\persistent\program;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;
use tool_program\persistent\program_set_completion;
use tool_program\persistent\program_user;
use tool_tenant\hierarchy;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use core_text;
use tool_tenant\tenant;
use tool_tenant\tenant_group;
use tool_wp\course_reset_api;
use tool_wp\db;
use tool_wp\local\helpers\string_helper;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/lib.php');

/**
 * Class api
 *
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class api {

    /** @var int For certifications, use the same setting for groups as in programs */
    const GROUPS_AS_IN_PROGRAMS = -1;
    /** @var int Do not create groups in courses */
    const GROUPS_NONE = 0;
    /** @var int Create groups in shared courses for each tenant */
    const GROUPS_TENANT = 1;
    /** @var int Create groups in courses for each program */
    const GROUPS_PROGRAM = 2;
    /** @var int Create groups in courses for each tenant and each program */
    const GROUPS_TENANT_PROGRAM = 3;
    /** @var int Create groups in courses for each certification */
    const GROUPS_CERTIFICATION = 4;

    /**
     * Create a program.
     *
     * @param stdClass $data
     * @return program
     */
    public static function create_program(stdClass $data): program {
        // Prevent passing extra data to the persistent.
        $insertdata = (object) array_intersect_key((array) $data, [
            'tenantid' => 1,
            'fullname' => 1,
            'idnumber' => 1,
            'shared' => 0,
            'startdatetype' => 1,
            'startdaterelative' => 1,
            'startdateabsolute' => 1,
            'enddatetype' => 1,
            'enddaterelative' => 1,
            'enddateabsolute' => 1,
            'duedatetype' => 1,
            'duedaterelative' => 1,
            'duedateabsolute' => 1,
            'allocationstartdatetype' => 1,
            'allocationstartdateabsolute' => 1,
            'allocationenddatetype' => 1,
            'allocationenddateabsolute' => 1,
            'allocationenddaterelative' => 1,
            'status' => 1,
            'archived' => 1,
            'visible' => 1,
            'allowdirectallocation' => 1,
            'descriptionformat' => 1,
            'description' => 1,
            'autocreategroups' => self::GROUPS_TENANT
        ]);

        // If no tenantid provided, default to current user tenant id.
        if (empty($insertdata->tenantid)) {
            $insertdata->tenantid = tenancy::get_tenant_id();
        }
        $insertdata->shared = sharedspace::is_shared_space($insertdata->tenantid) ? 1 : 0; // Hardcoded for now.

        $newprogram = new program(0, $insertdata);
        $newprogram->create();

        $context = context_system::instance();

        // Create base set.
        self::create_set((object) [
            'programid' => $newprogram->get('id'),
            'parent' => 0,
            'name' => '',
            'sortorder' => 1,
        ]);

        // Save draft files embedded in the description text.
        if (isset($data->description_editor)) {
            $data = file_postupdate_standard_editor($data, 'description', edit_program_details_form::description_editor_options(),
                $context, 'tool_program', 'program_description', $newprogram->get('id'));

            $newprogram->set('descriptionformat', $data->descriptionformat);
            $newprogram->set('description', $data->description);
            $newprogram->update();
        }

        // Save program image.
        file_postupdate_standard_filemanager($data, 'image', edit_program_details_form::image_filemanager_options(),
            $context, 'tool_program', 'program_image', $newprogram->get('id'));

        // Save program tags.
        $tagnames = empty($data->program_tags) ? [] : $data->program_tags;
        core_tag_tag::set_item_tags('tool_program', 'tool_program', $newprogram->get('id'), $context, $tagnames);

        // Create default dynamic rules for dynamic rules tab.
        self::add_default_dynamicrule_conditions_to_program($newprogram->get('id'), $newprogram->get('tenantid'));

        // Save custom fields data.
        $customfieldsdata = fullclone($data);
        $customfieldsdata->id = $newprogram->get('id');
        program_handler::create()->instance_form_save($customfieldsdata, true);

        // Trigger event.
        program_created::create_from_program_created($newprogram)->trigger();

        return $newprogram;
    }

    /**
     * Adds default conditions to program.
     *
     * @param int $programid
     * @param int $tenantid
     */
    public static function add_default_dynamicrule_conditions_to_program(int $programid, int $tenantid): void {
        // Check if tool_dynamicrule is installed.
        if (!class_exists(\tool_dynamicrule\api::class)) {
            return;
        }
        $component = 'tool_program';
        $componentarea = 'program';
        $configdata = ['programid' => $programid];

        $conditions = self::get_available_dynamicrule_conditions();

        foreach ($conditions as $condition => $stringid) {
            // Create rule.
            $name = get_string($stringid, 'tool_program');
            $ruleid = \tool_dynamicrule\api::create_rule_for_component($component, $componentarea, $programid, $tenantid, $name);
            $conditionclass = '\\tool_program\\tool_dynamicrule\\condition\\' . $condition;
            // Create condition. No need to verify user tenancy,
            // we are creating condition for rule that was just created.
            \tool_dynamicrule\api::create_rule_condition($ruleid, $conditionclass, $configdata, true);
        }
    }

    /**
     * Returns list of all available program conditions
     *
     * @return array|string[]
     */
    public static function get_available_dynamicrule_conditions(): array {
        return [
            'user_allocated' => 'conditionuserallocated',
            'user_not_allocated' => 'conditionusernotallocated',
            'program_completed' => 'conditionprogramcompleted',
            'program_not_completed' => 'conditionprogramnotcompleted',
            'program_overdue' => 'conditionprogramoverdue',
            'program_suspended' => 'conditionprogramsuspended',
        ];
    }

    /**
     * Updates details from a program.
     *
     * @param stdClass $data
     */
    public static function update_program_details(stdClass $data): void {
        $context = context_system::instance();

        // Update draft files embedded in the description text.
        if (isset($data->description_editor)) {
            $data = file_postupdate_standard_editor($data, 'description', edit_program_details_form::description_editor_options(),
                $context, 'tool_program', 'program_description', $data->id);
        }

        // Update program image.
        $data = file_postupdate_standard_filemanager($data, 'image', edit_program_details_form::image_filemanager_options(),
            $context, 'tool_program', 'program_image', $data->id);

        // Update program tags.
        $tagnames = empty($data->program_tags) ? [] : $data->program_tags;
        core_tag_tag::set_item_tags('tool_program', 'tool_program', $data->id, $context, $tagnames);

        $program = new program($data->id);
        $oldrecord = $program->to_record();
        $program->set('fullname', $data->fullname);
        $program->set('idnumber', $data->idnumber);
        $program->set('description', $data->description);
        $program->set('descriptionformat', $data->descriptionformat);
        $program->set('visible', $data->visible);
        $program->set('allowdirectallocation', $data->allowdirectallocation);
        $program->set('autocreategroups', $data->autocreategroups);
        $program->update();

        // Update program name in all events for this program.
        self::update_program_name_in_calendar_events($data->id, $data->fullname);

        // Trigger event.
        program_updated::create_from_program_updated($program, $oldrecord)->trigger();
    }

    /**
     * Updates calendar tab from a program.
     *
     * @param stdClass $data
     * @return bool
     */
    public static function update_program_calendar(stdClass $data): bool {
        $program = new program($data->id);
        $oldrecord = $program->to_record();
        $program->set('startdatetype', $data->startdatetype);
        $program->set('startdateabsolute', $data->startdateabsolute);
        $program->set('startdaterelative', $data->startdaterelative);
        $program->set('duedatetype', $data->duedatetype);
        $program->set('duedateabsolute', $data->duedateabsolute);
        $program->set('duedaterelative', $data->duedaterelative);
        $program->set('enddatetype', $data->enddatetype);
        $program->set('enddateabsolute', $data->enddateabsolute);
        $program->set('enddaterelative', $data->enddaterelative);
        $program->set('allocationstartdatetype', $data->allocationstartdatetype);
        $program->set('allocationstartdateabsolute', $data->allocationstartdateabsolute);
        $program->set('allocationenddatetype', $data->allocationenddatetype);
        $program->set('allocationenddateabsolute', $data->allocationenddateabsolute);
        $program->set('allocationenddaterelative', $data->allocationenddaterelative);

        $program->update();

        // Trigger event.
        program_updated::create_from_program_updated($program, $oldrecord)->trigger();

        self::recalculate_all_program_users_dates($program);

        return true;
    }

    /**
     * Deletes a program.
     *
     * @param program $program
     * @return bool
     */
    public static function delete_program(program $program): bool {
        if (!$program->is_archived()) {
            throw new moodle_exception('errorcantdeletenotarchivedprogram', 'tool_program');
        }

        // Delete all program enrolments of all the allocated users.
        self::delete_all_allocated_users_enrolments($program);

        // Delete all program allocated users.
        self::delete_program_users($program);

        // Delete all program contents.
        if ($baseset = $program->get_base_set()) {
            // Deletes sets and courses recursively.
            // This should also disable the program courses enrol instances.
            self::delete_set($baseset);
        }

        // Delete groups associations.
        tenant_group::delete_for_component('tool_program', 'tool_program', $program->get('id'));

        // Delete dynamic rules associated to this program.
        self::delete_program_dynamic_rules($program->get('id'));

        // Create event.
        $event = program_deleted::create_from_program_deleted($program);

        // Delete program custom fields.
        $handler = program_handler::create();
        $handler->delete_instance($program->get('id'));

        // Delete program files.
        $fs = get_file_storage();
        $context = $program->get_context();
        $fs->delete_area_files($context->id, 'tool_program', false, $program->get('id'));

        // Delete the program.
        $program->delete();

        // Trigger program deleted event.
        $event->trigger();

        return true;
    }

    /**
     * Creates a set.
     *
     * @param stdClass $data
     * @return program_set
     */
    private static function create_set(stdClass $data): program_set {
        // Prevent passing extra data to the persistent.
        $insertdata = (object) array_intersect_key((array) $data, [
            'programid' => 1,
            'parent' => 1,
            'name' => 1,
            'sortorder' => 1,
            'completioncriteria' => 1,
            'completionatleast' => 1,
        ]);
        $newset = new program_set(0, $insertdata);
        $newset->create();

        // Trigger event.
        program_set_created::create_from_program_set_created($newset)->trigger();

        return $newset;
    }

    /**
     * Updates a set.
     *
     * @param stdClass $data
     */
    public static function update_set(stdClass $data): void {
        $programset = new program_set($data->id);
        $programset->set('parent', $data->parent);
        $programset->set('name', $data->name);
        $programset->set('sortorder', $data->sortorder);
        $programset->update();

        // Trigger event.
        program_set_updated::create_from_program_set_updated($programset)->trigger();
    }

    /**
     * Updates set completion criteria.
     *
     * @param stdClass $data
     * @return bool
     */
    public static function update_set_completion_criteria(stdClass $data): bool {
        $programset = new program_set($data->setid);
        $programset->set('completioncriteria', $data->completioncriteria);
        if (program_set::COMPLETION_AT_LEAST === (int) $data->completioncriteria) {
            $programset->set('completionatleast', $data->completionatleast);
        }
        $programset->update();

        // Trigger event.
        program_set_updated::create_from_program_set_updated($programset)->trigger();

        return true;
    }

    /**
     * Deletes the given set and its contents recursively.
     *
     * @param program_set $programset
     * @return bool
     */
    public static function delete_set(program_set $programset): bool {
        // Delete courses inside this set.
        $childprogramcourses = $programset->get_program_courses();
        foreach ($childprogramcourses as $childprogramcourse) {
            self::delete_program_course($childprogramcourse);
        }

        // Delete set completions.
        $setcompletions = program_set_completion::get_records(['setid' => $programset->get('id')]);
        foreach ($setcompletions as $setcompletion) {
            $setcompletion->delete();
        }

        // Delete sets inside this set recursively.
        $childsets = $programset->get_subsets();
        foreach ($childsets as $childset) {
            self::delete_set($childset);
        }

        // Create event.
        $event = program_set_deleted::create_from_program_set_deleted($programset);

        $programset->delete();

        // Trigger event.
        $event->trigger();

        return true;
    }

    /**
     * Adds a course to a set.
     *
     * @param stdClass $data
     * @return program_course
     */
    private static function add_course_to_a_set(stdClass $data): program_course {
        // Prevent passing extra data to the persistent.
        $insertdata = (object) array_intersect_key((array) $data, [
            'setid' => 1,
            'courseid' => 1,
            'sortorder' => 1,
        ]);

        $newprogramcourse = new program_course(0, $insertdata);
        $newprogramcourse->create();

        // Add enrol_program enrolment method to the course.
        $course = get_course($newprogramcourse->get('courseid'));
        if (!self::enable_program_course_enrol_instance($data->programid, $course)) {
            throw new moodle_exception('errornostudentsrolefound', 'tool_program');
        }

        // Enrol users who have completed the course in the past or enrolled already.
        $program = new program($data->programid);
        $programusers = $program->get_program_users();
        foreach ($programusers as $programuser) {
            self::enrol_in_program_course_if_user_completed_or_enrolled($program, $course, $programuser);
            self::calculate_user_program_progress($program, $programuser->get('userid'));
        }

        // Trigger event.
        program_course_created::create_from_program_course_created($newprogramcourse, $data->programid)->trigger();

        return $newprogramcourse;
    }

    /**
     * Adds a set to a base set.
     *
     * @param int $programid
     * @param string $name
     * @return program_set
     */
    public static function add_set_to_base_set(int $programid, string $name): program_set {
        $program = new program($programid);
        if (!$parentset = $program->get_base_set()) {
            throw new moodle_exception('errorbasesetnotfound', 'tool_program');
        }

        $sortorder = $parentset->get_next_child_sortorder();

        return self::create_set((object) [
            'programid' => $program->get('id'),
            'parent' => $parentset->get('id'),
            'name' => $name,
            'sortorder' => $sortorder,
        ]);
    }

    /**
     * Adds a course to a base set.
     *
     * @param int $programid
     * @param int $courseid
     * @return program_course
     */
    public static function add_course_to_base_set(int $programid, int $courseid): program_course {
        $program = new program($programid);
        if (!$parentset = $program->get_base_set()) {
            throw new moodle_exception('errorbasesetnotfound', 'tool_program');
        }

        $sortorder = $parentset->get_next_child_sortorder();

        return self::add_course_to_a_set((object) [
            'programid' => $programid,
            'setid' => (int) $parentset->get('id'),
            'courseid' => $courseid,
            'sortorder' => $sortorder,
        ]);
    }

    /**
     * Removes course from a set.
     *
     * @param program_course $programcourse
     * @return bool
     */
    public static function delete_program_course(program_course $programcourse): bool {
        global $DB;

        // Course might have been deleted.
        if ($DB->record_exists('course', ['id' => $programcourse->get('courseid')])) {
            $course = $programcourse->get_course();
        } else {
            $course = new stdClass();
            $course->id = $programcourse->get('courseid');
        }

        $program = $programcourse->get_program();
        $programid = $program->get('id');

        // Create event.
        $event = program_course_deleted::create_from_program_course_deleted($programcourse, $programid);

        $programcourse->delete();

        // Check if this is the last instance of this course within this program.
        if (!self::is_course_in_program($program, $course->id)) {
            // If this course does not exist anymore within the program, delete its enrol_program instance.
            self::delete_program_course_enrol_instance($programid, $course->id);
        }

        // Trigger event.
        $event->trigger();

        return true;
    }

    /**
     * Allocates a user into a program.
     *
     * @param program $program
     * @param stdClass $programuserdata
     * @return program_user
     */
    public static function allocate_user(program $program, stdClass $programuserdata): program_user {
        // Prevent passing extra data to the persistent.
        // Dates can be passed if user is allocated from certification.
        $insertdata = (object) array_intersect_key((array) $programuserdata, [
            'userid' => 1,
            'certificationid' => 1,
            'allocationtype' => 1,
            'startdate' => 1,
            'startdatelocked' => 1,
            'enddate' => 1,
            'enddatelocked' => 1,
            'duedate' => 1,
            'duedatelocked' => 1,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ]);
        $insertdata->programid = $program->get('id');

        if (isset($insertdata->status) && constants::STATUS_OVERRIDE_SUSPENDED === (int) $insertdata->status) {
            $insertdata->timesuspended = time();
        }

        // Check for existing active allocation, if exists we will skip enrolment.
        $activeallocationexists = self::is_active_allocation($program->get('id'), $insertdata->userid);

        // Allocate user to this program.
        $newuser = new program_user(0, $insertdata);
        $newuser->set_program($program);
        $newuser->create();

        if (!$newuser->is_certification_allocation()) {
            self::recalculate_program_user_dates($program, $newuser);
        }

        $courses = $program->get_courses();
        foreach ($courses as $course) {
            if (!$activeallocationexists || (int)$newuser->get('status') === constants::STATUS_OVERRIDE_DEFAULT) {
                // By default we leave user not enrolled, so they can "Start" the program and
                // get enrolled on accessing the course (see enrol_program_plugin::enrol_page_hook())
                // However, there are cases when we need to enrol user: enrol the user in courses she
                // completed already and add enrolment methods to course user is enrolled already using
                // different method.
                // Calling this method also updates enrolment status if it is different to
                // existing enrolment. We use this feature to activate enrolment if user is enrolled as active,
                // but another allocation existed in suspended status (see conditions for this block).
                self::enrol_in_program_course_if_user_completed_or_enrolled($program, $course, $newuser);
            }
        }

        if ((int)$newuser->get('status') !== constants::STATUS_OVERRIDE_SUSPENDED) {
            self::calculate_user_program_progress($program, $newuser->get('userid'));
        }

        // Create due date and end date calendar event.
        if ($newuser->get('duedate') !== constants::DATE_NONE) {
            $data = (object) [
                'userid' => $newuser->get('userid'),
                'name' => $program->get_formatted_name(),
                'programid' => $program->get('id'),
                'timestart' => $newuser->get('duedate'),
                'programdatetype' => constants::CALENDAR_EVENT_DUE_DATE
            ];
            self::update_calendar_event($data);
        }
        if ($newuser->get('enddate') !== constants::DATE_NONE) {
            $data = (object) [
                'userid' => $newuser->get('userid'),
                'name' => $program->get_formatted_name(),
                'programid' => $program->get('id'),
                'timestart' => $newuser->get('enddate'),
                'programdatetype' => constants::CALENDAR_EVENT_END_DATE
            ];
            self::update_calendar_event($data);
        }

        // Send user allocated notification if is a direct allocation to the program.
        if ((int)$newuser->get('certificationid') === 0) {
            self::send_program_user_allocation_created_notification($newuser);
        }

        // Trigger event.
        // TODO WP-2923 this event should be triggered before the notification is sent.
        user_allocation_created::create_from_user_allocation_created($newuser)->trigger();

        return $newuser;
    }

    /**
     * Deallocates user from a program given program, user and certification id.
     * If allocation type is specified, it will consider also the allocation origin.
     *
     * @param int $programid
     * @param int $userid
     * @param int $certificationid Defaults to 0 (allocation without related certification)
     * @param int|null $allocationtype
     * @return bool
     */
    public static function deallocate_user(int $programid, int $userid, int $certificationid = 0,
        int $allocationtype = null): bool {

        $params = [
            'programid' => $programid,
            'userid' => $userid,
            'certificationid' => $certificationid,
        ];

        if ($allocationtype !== null) {
            $params['allocationtype'] = $allocationtype;
        }

        if (!$programuser = program_user::get_record($params)) {
            return false;
        }

        // Delete user allocation.
        $event = user_allocation_deleted::create_from_user_allocation_deleted($programuser);
        $programuser->delete();

        if (program_user::count_records(['programid' => $programid, 'userid' => $userid]) === 0) {
            // If there was only one user allocation, remove all program enrolments
            // from all the program courses and remove from groups.
            self::remove_user_from_program_courses($programuser);
        } else {
            if (self::can_user_be_removed_from_group($programid, $userid, $certificationid)) {
                // This is not the only allocation, keep user enrolled and only
                // remove user from groups associated with this allocation.
                self::remove_user_from_program_course_groups($programuser);
            }
            if ((int) $programuser->get('status') === constants::STATUS_OVERRIDE_DEFAULT &&
                    !self::is_active_allocation($programid, $userid)) {
                // We removed last active allocation, suspend course enrolments.
                if ($enrolplugin = enrol_get_plugin('program')) {
                    $enrolinstances = self::get_program_enrol_instances_by_programid_and_userid($programid, $userid);
                    foreach ($enrolinstances as $instance) {
                        $enrolplugin->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
                    }
                }
            }
        }

        // Delete calendar events for this user and program.
        $data = (object) [
            'userid' => $userid,
            'programid' => $programid,
        ];
        self::delete_calendar_events($data);

        // Trigger event.
        $event->trigger();

        // Send notification if is a direct program allocation, otherwise tool_certification will send it.
        if (!$certificationid) {
            self::send_program_user_deallocated_notification($userid, $programid);
        }

        return true;
    }

    /**
     * Adds a set to a parent set.
     *
     * @param int $parent Set id of the parent program set
     * @param string $name Name of the new set
     * @param stdClass $completion Completion criteria and completion atleast values
     * @return program_set
     */
    public static function add_set_to_parent_set(int $parent, string $name, stdClass $completion): program_set {
        $parentset = new program_set($parent);
        if (!$program = $parentset->get_program()) {
            throw new moodle_exception('errorprogramnotfound', 'tool_program');
        }

        $sortorder = $parentset->get_next_child_sortorder();

        return self::create_set((object) [
            'programid' => $program->get('id'),
            'parent' => $parentset->get('id'),
            'name' => $name,
            'completioncriteria' => $completion->completioncriteria,
            'completionatleast' => $completion->completionatleast,
            'sortorder' => $sortorder,
        ]);
    }

    /**
     * Adds a course to a parent set.
     *
     * @param int $parent
     * @param int $courseid
     * @return program_course
     */
    public static function add_course_to_parent_set(int $parent, int $courseid): program_course {
        $parentset = new program_set($parent);
        if (!$program = $parentset->get_program()) {
            throw new moodle_exception('errorprogramnotfound', 'tool_program');
        }

        $sortorder = $parentset->get_next_child_sortorder();

        return self::add_course_to_a_set((object) [
            'programid' => $program->get('id'),
            'setid' => (int) $parentset->get('id'),
            'courseid' => $courseid,
            'sortorder' => $sortorder,
        ]);
    }

    /**
     * Recalculates all program users dates.
     *
     * @param program $program
     */
    private static function recalculate_all_program_users_dates(program $program): void {
        $programusers = $program->get_program_users();
        foreach ($programusers as $programuser) {
            if (!$programuser->is_certification_allocation()) {
                self::recalculate_program_user_dates($program, $programuser);
            }
        }
    }

    /**
     * Recalculates program user dates.
     *
     * @param program $program
     * @param program_user $programuser
     * @return bool
     */
    public static function recalculate_program_user_dates(program $program, program_user $programuser): bool {
        $userallocationdate = (int) $programuser->get('timecreated');
        $userstartdate = self::recalculate_user_start_date($program, $programuser, $userallocationdate);
        $userduedate = self::recalculate_user_due_date($program, $programuser, $userallocationdate, $userstartdate);
        $userenddate = self::recalculate_user_end_date($program, $programuser, $userallocationdate, $userstartdate, $userduedate);

        $startdatehaschanged = $userstartdate != $programuser->get('startdate');
        $duedatehaschanged = $userduedate != $programuser->get('duedate');
        $enddatehaschanged = $userenddate != $programuser->get('enddate');

        if (!$startdatehaschanged && !$duedatehaschanged && !$enddatehaschanged) {
            return true;
        }

        $programuser->set('startdate', $userstartdate);
        $programuser->set('duedate', $userduedate);
        $programuser->set('enddate', $userenddate);

        $programid = $programuser->get('programid');
        $userid = $programuser->get('userid');

        if ($duedatehaschanged) {
            // Create or update due date calendar event.
            $data = (object) [
                'userid' => $userid,
                'name' => $program->get_formatted_name(),
                'programid' => $programid,
                'timestart' => $userduedate,
                'programdatetype' => constants::CALENDAR_EVENT_DUE_DATE
            ];
            self::update_calendar_event($data);
        }

        if ($enddatehaschanged) {
            // Create or update end date calendar event.
            $data = (object) [
                'userid' => $userid,
                'name' => $program->get_formatted_name(),
                'programid' => $programid,
                'timestart' => $userenddate,
                'programdatetype' => constants::CALENDAR_EVENT_END_DATE
            ];
            self::update_calendar_event($data);
        }

        if ($startdatehaschanged || $enddatehaschanged) {
            self::update_user_course_enrolment_dates($programid, $userid, $userstartdate, $userenddate);
        }

        return $programuser->update();
    }

    /**
     * Moves item to a new position.
     *
     * @param int $itemid The moving item id
     * @param bool $isset Wether the moving item is a program set
     * @param int $sourcesetid The source set that the item is moving from
     * @param int $targetsetid The target set that the item is moving to
     * @param int $nextitemid The item after which the item is moving to (0 if moving to last position in a set)
     * @param bool $nextisset Wether the item after which the item is moving to is a set
     * @return program_course[]|program_set[]
     */
    public static function move_item_to_new_position(int $itemid, bool $isset, int $sourcesetid, int $targetsetid, int $nextitemid,
        bool $nextisset): array {

        $validparams = ($itemid > 0 && $sourcesetid > 0 && $targetsetid > 0 && $nextitemid > -1);
        if (!$validparams) {
            throw new coding_exception('invalid parameter value passed to move_set_to_new_position');
        }

        if ($isset) {
            $movingitem = new program_set($itemid);
            $movingitemparentsetid = $movingitem->get('parent');
        } else {
            $movingitem = new program_course($itemid);
            $movingitemparentsetid = $movingitem->get('setid');
        }

        // We check if same course id is already in the target set.
        if (!$isset && $sourcesetid !== $targetsetid && self::is_course_in_set($targetsetid, $movingitem->get('courseid'))) {
            throw new moodle_exception('coursealreadyinset', 'tool_program');
        }

        // If we are moving an item within the same set that it was before, source and target set are the same set.
        // In this case we just need to reorder one set (we will reorder it as the "target" set).
        $targetset = new program_set($targetsetid);
        $programid = $targetset->get('programid');
        if ($sourcesetid !== $targetsetid) {
            $sourceset = new program_set($sourcesetid);
            if ($movingitemparentsetid !== $sourceset->get('id')) {
                // Moving item must belong to the provided source set!
                throw new moodle_exception('errorinvalidprogramitemmove', 'tool_program');
            }
        } else {
            $sourceset = null;
            if ($movingitemparentsetid !== $targetset->get('id')) {
                // Moving item must belong to the provided target set!
                throw new moodle_exception('errorinvalidprogramitemmove', 'tool_program');
            }
        }

        if ($nextitemid === 0) {
            $nextitem = null;
            $targetparentsetid = null;
        } else if ($nextisset) {
            $nextitem = new program_set($nextitemid);
            $targetparentsetid = $nextitem->get('parent');
        } else {
            $nextitem = new program_course($nextitemid);
            $targetparentsetid = $nextitem->get('setid');
        }

        if (!empty($targetparentsetid) && $targetparentsetid !== $targetset->get('id')) {
            // Next item must belong to the provided target set!
            throw new moodle_exception('errorinvalidprogramitemmove', 'tool_program');
        }

        $reordereditems = self::reorder_source_set_and_target_set_items($sourceset, $targetset, $movingitem, $nextitem);
        foreach ($reordereditems as $reordereditem) {
            $reordereditem->update();
        }

        // Trigger events.
        foreach ($reordereditems as $reordereditem) {
            if ($reordereditem instanceof program_course) {
                $event = program_course_updated::create_from_program_course_updated($reordereditem, $programid);
            } else {
                $event = program_set_updated::create_from_program_set_updated($reordereditem);
            }
            $event->trigger();
        }

        return $reordereditems;
    }

    /**
     * Reordering algorithm for items within a set. Given a source and target array of set items (sets and/or courses),
     * the moving item and the next item within the target set, rebuilds both source and target arrays so that the
     * move is made without any duplication of item sortorders (positions).
     *
     * Note: If moving within the same set (source set and target set are the same set), pass $sourceset as null.
     *
     * Note: $nextitem is the item BEFORE WHICH the moving item will be inserted. If moving to last position pass $nextitem as null.
     *
     * @param program_set|null $sourceset Source set of the moving item.
     * @param program_set $targetset Target set of the moving item.
     * @param program_set|program_course $movingitem The program set or course item that is being moved.
     * @param program_set|program_course|null $nextitem The sibling item BEFORE WHICH the moving item is being moved.
     * @return program_set[]|program_course[]
     */
    private static function reorder_source_set_and_target_set_items($sourceset, $targetset, $movingitem, $nextitem): array {
        $reordereditems = [];

        if (null !== $sourceset) {
            self::reorder_source_set($sourceset, $movingitem, $reordereditems);
            // We are moving the item to a new set, so we set target set as the new parent set for the moving item.
            $parentidfield = $movingitem instanceof program_set ? 'parent' : 'setid';
            $movingitem->set($parentidfield, $targetset->get('id'));
        }

        $targetsetitems = $targetset->get_sorted_children();

        if (empty($targetsetitems)) {
            // If target set was empty moving item is the first item within the target set and we are done.
            $movingitem->set('sortorder', 1);
            $reordereditems[] = $movingitem;
            return $reordereditems;
        }

        self::reorder_target_set($sourceset, $movingitem, $nextitem, $targetsetitems, $reordereditems);

        return $reordereditems;
    }

    /**
     * Checks if a course is in a program.
     *
     * @param program $program
     * @param int $courseid
     * @return bool
     */
    public static function is_course_in_program(program $program, int $courseid): bool {
        $coursesids = $program->get_courses_ids();

        return in_array($courseid, $coursesids, false);
    }

    /**
     * Disables instance of program enrol method in a course.
     *
     * @param int $programid
     * @param int $courseid
     * @return bool
     */
    private static function disable_program_course_enrol_instance(int $programid, int $courseid): bool {
        global $DB;

        if (!$enrolplugin = enrol_get_plugin('program')) {
            return false;
        }

        $params = [
            'courseid' => $courseid,
            'enrol' => 'program',
            'customint1' => $programid,
        ];
        if (!$enrolinstance = $DB->get_record('enrol', $params)) {
            return false;
        }

        $enrolplugin->update_status($enrolinstance, ENROL_INSTANCE_DISABLED);

        return true;
    }

    /**
     * Creates or enables instance of program enrol method in a course.
     *
     * This method checks if enrol_program method instance exists in the course,
     * and adds it if missing.
     *
     * @param int $programid
     * @param stdClass $course
     * @return ?stdClass enrol instance
     */
    private static function enable_program_course_enrol_instance(int $programid, stdClass $course): ?stdClass {
        global $DB;

        if (!$enrolplugin = enrol_get_plugin('program')) {
            return null;
        }

        $currentenrolinstance = $DB->get_record('enrol', [
            'courseid' => $course->id,
            'enrol' => 'program',
            'customint1' => $programid
        ]);

        if ($currentenrolinstance) {
            if (ENROL_INSTANCE_ENABLED !== (int) $currentenrolinstance->status) {
                // Enable enrolment method instance.
                $enrolplugin->update_status($currentenrolinstance, ENROL_INSTANCE_ENABLED);
                $currentenrolinstance->status = ENROL_INSTANCE_ENABLED;
            }
            return $currentenrolinstance;
        }

        // Get all roles from archetype student, ordered by sortorder, and use the first one.
        $studentroles = get_archetype_roles('student');
        if (empty($studentroles)) {
            return null;
        }
        $studentrole = reset($studentroles);
        $id = $enrolplugin->add_instance($course, ['customint1' => $programid, 'roleid' => $studentrole->id]);
        return $DB->get_record('enrol', ['id' => $id]);
    }

    /**
     * Enrol user to a program course.
     *
     * This method enrols user to program course and adds user to groups.
     * For performance reasons this function does not check that course is inside the program
     * and user is allocated to the program. This has to be checked before calling this method.
     *
     * @param int $programid
     * @param stdClass $course
     * @param int $userid
     * @param int $status defaults to active
     * @return bool
     */
    public static function enrol_in_program_course(int $programid, stdClass $course, int $userid,
            int $status = constants::STATUS_OVERRIDE_DEFAULT): bool {
        /** @var enrol_program_plugin $enrolplugin */
        $enrolplugin = enrol_get_plugin('program');
        if (!$enrolplugin) {
            return false;
        }

        if (!$enrolinstance = self::enable_program_course_enrol_instance($programid, $course)) {
            return false;
        }

        $enrolstatus = ($status === constants::STATUS_OVERRIDE_SUSPENDED) ? ENROL_USER_SUSPENDED : ENROL_USER_ACTIVE;
        $enrolplugin->enrol_user($enrolinstance, $userid, $enrolinstance->roleid, 0, 0, $enrolstatus);

        // Add to groups if we don't enrol user in suspended status.
        if ($status !== constants::STATUS_OVERRIDE_SUSPENDED) {
            self::add_user_to_course_groups($enrolinstance, $userid, $course);
        }

        return true;
    }

    /**
     * Adds user to all necessary groups in the course (called after enrolling a user or unsuspending enrolment)
     *
     * @param stdClass $enrolinstance record from {enrol} table, it has columns customint1 (corresponds to program id),
     *     enrol (always equals to 'program'), courseid (course id)
     * @param int $userid
     * @param stdClass|null $course course object if known (if not specified it will be retrieved)
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function add_user_to_course_groups(stdClass $enrolinstance, int $userid, stdClass $course = null): void {
        $programid = $enrolinstance->customint1;
        $courseid = $enrolinstance->courseid;
        $course = $course ?? get_course($courseid);
        $groups = self::get_user_groups_in_course($programid, $course, $userid);
        foreach ($groups as $groupid) {
            groups_add_member($groupid, $userid, 'enrol_program', $enrolinstance->id);
        }
    }

    /**
     * List of groups where user should be added.
     *
     * This returns a list of course groups users should be added to when gets enrolled
     * in the course through program or certification. Also method is used to restore groups
     * membership when user enrolment gets un-suspended.
     *
     * @param int $programid
     * @param stdClass $course
     * @param int $userid
     * @return array list of group ids
     */
    public static function get_user_groups_in_course(int $programid, stdClass $course, int $userid): array {

        // Only return groups where allocation is active.
        /** @var program_user[] $allocations */
        $allocations = program_user::get_records([
            'programid' => $programid,
            'userid' => $userid,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ]);
        $program = new program($programid);

        $groups = [];
        foreach ($allocations as $allocation) {
            if (($certification = $allocation->get_certification())
                    && $certification->get('autocreategroups') != self::GROUPS_AS_IN_PROGRAMS) {
                // If certification is archived do not add user to certification group.
                if ($certification->get('archived') == 1) {
                    continue;
                }
                $autocreategroups = $certification->get('autocreategroups');
            } else {
                $autocreategroups = $program->get('autocreategroups');
            }
            $usertenantid = tenancy::get_tenant_id($userid);
            $defaultname = [];
            $component = $area = $itemid = null;
            if (($autocreategroups & self::GROUPS_TENANT) && (tenancy::is_shared_course($course, $usertenantid))) {
                $tenantid = $usertenantid;
                $defaultname[] = tenancy::get_tenant_name_from_id($usertenantid);
            } else {
                $tenantid = null;
            }
            if ($autocreategroups & self::GROUPS_CERTIFICATION) {
                $component = $area = 'tool_certification';
                $itemid = $certification->get('id');
                $defaultname[] = format_string($certification->get('fullname'), true,
                    ['context' => $certification->get_context()->id]);
            } else if ($autocreategroups & self::GROUPS_PROGRAM) {
                $component = $area = 'tool_program';
                $itemid = $programid;
                $defaultname[] = format_string($program->get('fullname'), true, ['context' => $program->get_context()->id]);
            }
            // Note: the method below also creates group if it does not exist.
            $groups[] = tenancy::get_course_group($course, join(' - ', $defaultname), $tenantid, $component, $area, $itemid);
        }
        return array_values(array_filter(array_unique($groups)));
    }

    /**
     * Enrol in program course if user completed the course previously or currently enrolled.
     *
     * When user never access the course we can leave user not enrolled, so they can "Start" the program and
     * get enrolled on accessing the course (see enrol_program_plugin::enrol_page_hook()).
     * This method address cases when we need to enrol user explicitly:
     *   - course has already been completed by user
     *   - user is currently enrolled in course using other method
     * If above is the case, enrol user in course via this program enrolment instance.
     * It is advised to trigger progress update following this method use (see self::calculate_user_program_progress()).
     *
     * @param program $program
     * @param stdClass $course
     * @param program_user $programuser
     * @return void
     */
    private static function enrol_in_program_course_if_user_completed_or_enrolled(program $program, stdClass $course,
            program_user $programuser): void {
        global $CFG;
        require_once($CFG->libdir . "/completionlib.php");
        $completion = new \completion_info($course);
        $userid = $programuser->get('userid');

        if (is_enrolled(context_course::instance($course->id), $userid, '', true) ||
                ($completion->is_enabled() && $completion->is_course_complete($userid))) {
            self::enrol_in_program_course($program->get('id'), $course, $userid, (int) $programuser->get('status'));
        }
    }

    /**
     * Self enrols to a course.
     *
     * @param int $courseid
     * @param int $programid
     * @return bool
     */
    public static function self_enrol_to_course(int $courseid, int $programid): bool {
        global $USER;
        if (!self::enrol_in_program_course($programid, get_course($courseid), $USER->id)) {
            throw new moodle_exception('errormissingenrolprogramplugin', 'tool_program');
        }
        return true;
    }

    /**
     * Updates program visibility
     *
     * @param program $program
     * @param int $visibility
     * @return bool
     */
    public static function update_program_visibility(program $program, int $visibility): bool {
        $oldrecord = $program->to_record();
        $program->set('visible', $visibility);
        $program->update();

        if ($visibility === 0) {
            // Suspend all program enrolments of all the allocated users.
            self::suspend_all_allocated_users_enrolments($program);
        } else {
            // Restore all program enrolments of all the allocated users.
            self::restore_all_allocated_users_enrolments($program);
        }

        // Trigger event.
        program_updated::create_from_program_updated($program, $oldrecord)->trigger();

        return true;
    }

    /**
     * Archives a program.
     *
     * @param program $program
     * @return bool
     */
    public static function archive_program(program $program): bool {
        $oldrecord = $program->to_record();
        $program->set('archived', 1);
        $program->set('timearchived', time());
        $program->update();

        // Suspend all program enrolments of all the allocated users.
        self::suspend_all_allocated_users_enrolments($program);

        // Hide all user calendar events for this program.
        self::hide_program_user_calendar_events($program);

        // Trigger event.
        program_updated::create_from_program_updated($program, $oldrecord)->trigger();

        return true;
    }

    /**
     * Restores a program.
     *
     * @param program $program
     * @return bool
     */
    public static function restore_program(program $program): bool {
        $oldrecord = $program->to_record();
        $program->set('archived', 0);
        $program->set('timearchived', 0);
        // Check that idnumber is unique and is not present in another active program in this tenant.
        if (!self::is_idnumber_unique((int)$program->get('id'), (string)$program->get('idnumber'))) {
            $program->set('idnumber', '');
        }
        $program->update();

        // Restore all program enrolments of all the allocated users.
        self::restore_all_allocated_users_enrolments($program);

        // Show all user calendar events for this program.
        self::show_program_user_calendar_events($program);

        // Trigger event.
        program_updated::create_from_program_updated($program, $oldrecord)->trigger();

        self::recalculate_all_users_program_progress($program);

        return true;
    }

    /**
     * Suspends all allocated users enrolments for all courses in program.
     *
     * This disables program enrolment method in all courses in the program and also removes
     * all users from course tenant groups.
     *
     * @param program $program
     */
    public static function suspend_all_allocated_users_enrolments(program $program): void {
        // Suspend all program course enrol instances.
        $coursesids = $program->get_courses_ids();
        foreach ($coursesids as $courseid) {
            self::disable_program_course_enrol_instance($program->get('id'), $courseid);
        }

        // Remove all users from course tenant groups that have component reference to this program.
        self::remove_all_users_from_program_course_groups($program);
    }

    /**
     * Restores all allocated users enrolments for all courses in program.
     *
     * This enabled program enrolment method in all courses in the program and also restores
     * groups membership.
     *
     * @param program $program
     */
    public static function restore_all_allocated_users_enrolments(program $program): void {
        // Restore all program course enrol instances.
        $courses = $program->get_courses();
        foreach ($courses as $course) {
            self::enable_program_course_enrol_instance($program->get('id'), $course);
        }

        // Restore all users membership in tenant course groups.
        self::restore_all_users_in_program_course_groups($program);
    }

    /**
     * Duplicates program content recursively.
     *
     * @param program_item[] Program items to be duplicated recursively.
     * @param int $newprogramid New program id.
     * @param int $newparent New parent set id for this iteration of items.
     */
    private static function duplicate_program_content(array $programitems, int $newprogramid, int $newparent): void {
        foreach ($programitems as $item) {
            if ($item->is_set()) {
                // Duplicate set.
                $newset = self::create_set((object) [
                    'programid' => $newprogramid,
                    'parent' => $newparent,
                    'name' => $item->get_name(),
                    'sortorder' => $item->get_sortorder(),
                    'completioncriteria' => $item->get_completion_criteria(),
                    'completionatleast' => $item->get_completion_atleast()
                ]);
                // Duplicate set children.
                self::duplicate_program_content($item->items, $newprogramid, $newset->get('id'));
            } else if ($item->is_course()) {
                // Duplicate course.
                self::add_course_to_a_set((object) [
                    'programid' => $newprogramid,
                    'setid' => $newparent,
                    'courseid' => $item->get_courseid(),
                    'sortorder' => $item->get_sortorder(),
                ]);
            }
        }
    }

    /**
     * Duplicate program dynamic rules into another program.
     *
     * @param int $programid Origin program id
     * @param int $newprogramid Destination program id
     */
    public static function duplicate_program_dynamicrules(int $programid, int $newprogramid): void {
        global $DB;

        // Check if tool_dynamicrule is installed.
        if (!class_exists(\tool_dynamicrule\api::class)) {
            return;
        }

        $params = [
            'component' => 'tool_program',
            'componentarea' => 'program',
            'itemid' => $programid
        ];
        $programrules = $DB->get_records('tool_dynamicrule', $params);

        if (!empty($programrules)) {
            foreach ($programrules as $rule) {
                $originalruleid = $rule->id;
                $rule->itemid = $newprogramid;
                unset ($rule->id);
                $newrule = \tool_dynamicrule\api::create_rule((object) $rule);

                // Duplicate conditions.
                $programconditions = $DB->get_records('tool_dynamicrule_condition', ['ruleid' => $originalruleid]);
                if (!empty($programconditions)) {
                    foreach ($programconditions as $condition) {
                        $configdata = json_decode($condition->configdata, true);
                        $configdata['programid'] = $newprogramid;
                        // No need to verify user tenancy, we are creating condition for rule that was just created.
                        \tool_dynamicrule\api::create_rule_condition($newrule->get('id'), $condition->classname,
                            $configdata, true);
                    }
                }

                // Duplicate outcomes.
                $programoutcomes = $DB->get_records('tool_dynamicrule_outcome', ['ruleid' => $originalruleid]);
                if (!empty($programoutcomes)) {
                    foreach ($programoutcomes as $outcome) {
                        $configdata = json_decode($outcome->configdata, true);
                        // No need to verify user tenancy, we are creating condition for rule that was just created.
                        \tool_dynamicrule\api::create_rule_outcome($newrule->get('id'), $outcome->classname, $configdata, true);
                    }
                }
            }
        }
    }

    /**
     * Duplicates a program.
     *
     * @param program $program
     * @return program
     */
    public static function duplicate_program(program $program): program {
        $programid = $program->get('id');
        $context = context_system::instance();

        // Duplicate program record data.
        $record = $program->to_record();
        unset($record->id);
        $record->fullname .= ' ' . get_string('copy', 'tool_program');
        // ID number must be unique within same tenant.
        $record->idnumber = '';
        $record->tenantid = tenancy::get_tenant_id();
        $record->shared = sharedspace::is_shared_space() ? 1 : 0;
        $newprogram = new program(0, $record);
        $newprogram->create();

        // Create new base set for the duplicated program.
        $baseset = $program->get_base_set();
        $newprogramid = $newprogram->get('id');
        $newbaseset = self::create_set((object) [
            'programid' => $newprogramid,
            'parent' => 0,
            'name' => '',
            'sortorder' => 1,
            'completioncriteria' => $baseset->get('completioncriteria'),
            'completionatleast' => $baseset->get('completionatleast')
        ]);

        // Duplicate dynamic rules.
        self::duplicate_program_dynamicrules($programid, $newprogramid);

        // Copy program tags.
        $tags = core_tag_tag::get_item_tags_array('tool_program', 'tool_program', $programid);
        core_tag_tag::set_item_tags('tool_program', 'tool_program', $newprogramid, $context, $tags);

        // Copy program image.
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'tool_program', 'program_image', $programid, 'timecreated DESC', false);
        if (!empty($files)) {
            $file = reset($files);
            $params = (object) [
                'contextid' => $file->get_contextid(),
                'component' => 'tool_program',
                'filearea' => $file->get_filearea(),
                'filepath' => $file->get_filepath(),
                'itemid' => $newprogramid,
                'filename' => $file->get_filename()
            ];
            $fs->create_file_from_storedfile($params, $file);
        }

        // Copy program contents (sets and courses structure).
        $programtree = new program_tree($program);
        $programitems = $programtree->get_baseset_children_items();
        self::duplicate_program_content($programitems, $newprogramid, $newbaseset->get('id'));

        // Copy program custom fields.
        $programdata = $program->to_record();
        program_handler::create()->instance_form_before_set_data($programdata);
        $programdata->id = $newprogramid;
        program_handler::create()->instance_form_save($programdata);

        // Trigger event.
        program_created::create_from_program_created($newprogram)->trigger();

        return $newprogram;
    }

    /**
     * Gets allocation name for a user.
     *
     * @param int $type
     * @return string
     */
    public static function get_user_allocation_name(int $type): string {
        switch ($type) {
            case constants::ALLOCATION_MANUAL:
                return get_string('manual', 'tool_program');
                break;
            case constants::ALLOCATION_DYNAMIC:
                return get_string('dynamic', 'tool_program');
                break;
            case constants::ALLOCATION_CERTIFICATION:
                return get_string('certification', 'tool_program');
                break;
            default:
                throw new coding_exception('Unexpected program allocation type');
                break;
        }
    }

    /**
     * Returns user allocations.
     *
     * @param int $userid
     * @return array program_user
     */
    public static function get_user_allocations(int $userid): array {
        return program_user::get_records(['userid' => $userid]);
    }

    /**
     * Returns program customfields.
     *
     * @param int $programid
     * @return array \core_customfield\output\field_data
     */
    public static function get_program_customfields(int $programid): array {
        $handler = program_handler::create();
        return $handler->export_instance_data($programid);
    }

    /**
     * SQL for retrieving active user allocations to a given program
     *
     * @param string $pu alias for the program_user table in the main query where these joins are added to
     * @return array [$joins, $where]
     */
    protected static function get_active_allocations_sql(string $pu = 'pu'): array {
        $p = database::generate_alias();
        $c = database::generate_alias();
        $now = time();
        $joins = " JOIN {".program::TABLE."} {$p} ON {$pu}.programid = {$p}.id AND {$p}.archived = 0 AND {$p}.visible <> 0
            LEFT JOIN {".certification::TABLE."} {$c} ON {$c}.id = {$pu}.certificationid ";
        $where = " ({$pu}.certificationid = 0 OR {$c}.archived = 0)
                AND {$pu}.status = 1
                AND {$pu}.startdate <= $now
                AND ({$pu}.enddate = 0 OR {$pu}.enddate >= $now)
                ";
        return [$joins, $where];
    }

    /**
     * Checks if user has an allocation, is not suspended and allocation is currently within its start and end dates.
     *
     * @param int $programid
     * @param int $userid
     * @return bool
     */
    public static function is_active_allocation(int $programid, int $userid): bool {
        global $DB, $USER;
        $userid = $userid ?: $USER->id;
        [$joins, $where] = self::get_active_allocations_sql();
        $sql = "SELECT 1 FROM {".program_user::TABLE."} pu $joins ".
            "WHERE $where AND pu.programid = :programid AND pu.userid = :userid";
        $params = ['programid' => $programid, 'userid' => $userid];
        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Recalculates user start date.
     *
     * @param program $program
     * @param program_user $programuser
     * @param int $userallocationdate
     * @return int
     */
    private static function recalculate_user_start_date(program $program, program_user $programuser, int $userallocationdate): int {
        if ($programuser->get('startdate' . 'locked')) {
            return (int) $programuser->get('startdate');
        }

        switch ($program->get('startdate' . 'type')) {
            case constants::DATE_NONE:
                $userstartdate = constants::DATE_NONE;
                break;
            case constants::DATE_ABSOLUTE:
                $userstartdate = (int) $program->get('startdate' . 'absolute');
                break;
            case constants::DATE_AFTER_USER_ALLOCATION:
                $userstartdate = strtotime('+' . $program->get('startdate' . 'relative'), $userallocationdate);
                break;
            default:
                throw new coding_exception('unexpected program start date type');
                break;
        }

        return $userstartdate;
    }

    /**
     * Recalculates user due date.
     *
     * @param program $program
     * @param program_user $programuser
     * @param int $userallocationdate
     * @param int $userstartdate
     * @return int
     */
    private static function recalculate_user_due_date(program $program, program_user $programuser, int $userallocationdate,
        int $userstartdate): int {

        if ($programuser->get('duedate' . 'locked')) {
            return (int) $programuser->get('duedate');
        }

        switch ($program->get('duedate' . 'type')) {
            case constants::DATE_NONE:
                $userduedate = constants::DATE_NONE;
                break;
            case constants::DATE_ABSOLUTE:
                $userduedate = (int) $program->get('duedate' . 'absolute');
                break;
            case constants::DATE_AFTER_START:
                if (constants::DATE_NONE === $userstartdate) {
                    $userduedate = constants::DATE_NONE;
                } else {
                    $userduedate = strtotime('+' . $program->get('duedate' . 'relative'), $userstartdate);
                }
                break;
            case constants::DATE_AFTER_USER_ALLOCATION:
                $userduedate = strtotime('+' . $program->get('duedate' . 'relative'), $userallocationdate);
                break;
            case constants::DATE_BEFORE_END:
                switch ($program->get('enddate' . 'type')) {
                    case constants::DATE_NONE:
                        $userduedate = constants::DATE_NONE;
                        break;
                    case constants::DATE_ABSOLUTE:
                        $userenddate = (int) $program->get('enddate' . 'absolute');
                        $userduedate = strtotime('-' . $program->get('duedate' . 'relative'), $userenddate);
                        break;
                    case constants::DATE_AFTER_START:
                        if (constants::DATE_NONE === $userstartdate) {
                            $userduedate = constants::DATE_NONE;
                        } else {
                            $userenddate = strtotime('+' . $program->get('enddate' . 'relative'), $userstartdate);
                            $userduedate = strtotime('-' . $program->get('duedate' . 'relative'), $userenddate);
                        }
                        break;
                    case constants::DATE_AFTER_DUE:
                        // Due date and end date are related to each other! Not possible to calculate!
                        $userduedate = constants::DATE_NONE;
                        break;
                    case constants::DATE_AFTER_USER_ALLOCATION:
                        $userenddate = strtotime('+' . $program->get('enddate' . 'relative'), $userallocationdate);
                        $userduedate = strtotime('-' . $program->get('duedate' . 'relative'), $userenddate);
                        break;
                    default:
                        throw new coding_exception('unexpected program end date type');
                        break;
                }
                break;
            default:
                throw new coding_exception('unexpected program due date type');
                break;
        }

        return $userduedate;
    }

    /**
     * Recalculates user end date.
     *
     * @param program $program
     * @param program_user $programuser
     * @param int $userallocationdate
     * @param int $userstartdate
     * @param int $userduedate
     * @return int
     */
    private static function recalculate_user_end_date(program $program, program_user $programuser, int $userallocationdate,
        int $userstartdate, int $userduedate): int {

        if ($programuser->get('enddate' . 'locked')) {
            return (int) $programuser->get('enddate');
        }

        switch ($program->get('enddate' . 'type')) {
            case constants::DATE_NONE:
                $userenddate = constants::DATE_NONE;
                break;
            case constants::DATE_ABSOLUTE:
                $userenddate = (int) $program->get('enddate' . 'absolute');
                break;
            case constants::DATE_AFTER_START:
                if (constants::DATE_NONE === $userstartdate) {
                    $userenddate = constants::DATE_NONE;
                } else {
                    $userenddate = strtotime('+' . $program->get('enddate' . 'relative'), $userstartdate);
                }
                break;
            case constants::DATE_AFTER_USER_ALLOCATION:
                $userenddate = strtotime('+' . $program->get('enddate' . 'relative'), $userallocationdate);
                break;
            case constants::DATE_AFTER_DUE:
                if (constants::DATE_NONE === $userduedate) {
                    $userenddate = constants::DATE_NONE;
                } else {
                    $userenddate = strtotime('+' . $program->get('enddate' . 'relative'), $userduedate);
                }
                break;
            default:
                throw new coding_exception('unexpected program end date type');
                break;
        }

        return $userenddate;
    }

    /**
     * Updates user dates and status.
     *
     * @param program_user $programuser
     * @param stdClass $validateddata
     * @return bool
     */
    public static function update_program_user_dates_and_status(program_user $programuser, stdClass $validateddata): bool {

        $startdatehaschanged = $validateddata->startdate != $programuser->get('startdate');
        $duedatehaschanged = $validateddata->duedate != $programuser->get('duedate');
        $enddatehaschanged = $validateddata->enddate != $programuser->get('enddate');

        $oldstatus = (int) $programuser->get('status');
        $newstatus = (int) $validateddata->status;
        $suspendedstatus = constants::STATUS_OVERRIDE_SUSPENDED;
        if ($suspendedstatus === $newstatus && $suspendedstatus !== $oldstatus) {
            // If just suspended, update suspended timestamp. Timesuspended is only to be used in dynamic rules.
            $programuser->set('timesuspended', time());
        }
        $programuser->set('status', $validateddata->status);
        $programuser->set('startdatelocked', $validateddata->startdatelocked);
        $programuser->set('startdate', $validateddata->startdate);
        $programuser->set('duedatelocked', $validateddata->duedatelocked);
        $programuser->set('duedate', $validateddata->duedate);
        $programuser->set('enddatelocked', $validateddata->enddatelocked);
        $programuser->set('enddate', $validateddata->enddate);
        $programuser->update();

        $program = $programuser->get_program();

        // Recalculate dates for this user in case some where set from overriden to default.
        self::recalculate_program_user_dates($program, $programuser);

        $activestatus = constants::STATUS_OVERRIDE_DEFAULT;
        if ($activestatus !== $oldstatus && $activestatus === $newstatus) {
            // If just reactivated, reactivate program course enrolments for this user.
            self::reactivate_allocated_user_program_enrolments($programuser);
            // If just reactivated, trigger program progress recalculation for this user.
            self::calculate_user_program_progress($program, $programuser->get('userid'));
        }

        if ($suspendedstatus !== $oldstatus && $suspendedstatus === $newstatus) {
            // If just suspended, suspend program course enrolments for this user and remove from program course group.
            self::suspend_allocated_user_enrolments($programuser);
        }

        $userid = $programuser->get('userid');
        $programid = $program->get('id');

        if ($duedatehaschanged) {
            // Create or update due date calendar event.
            $data = (object) [
                'userid' => $userid,
                'name' => $program->get_formatted_name(),
                'programid' => $programid,
                'timestart' => $validateddata->duedate,
                'programdatetype' => constants::CALENDAR_EVENT_DUE_DATE
            ];
            self::update_calendar_event($data);
        }

        if ($enddatehaschanged) {
            // Create or update end date calendar event.
            $data = (object) [
                'userid' => $userid,
                'name' => $program->get_formatted_name(),
                'programid' => $programid,
                'timestart' => $validateddata->enddate,
                'programdatetype' => constants::CALENDAR_EVENT_END_DATE
            ];
            self::update_calendar_event($data);
        }

        if ($startdatehaschanged || $enddatehaschanged) {
            self::update_user_course_enrolment_dates($programid, $userid, $validateddata->startdate, $validateddata->enddate);
        }

        return true;
    }

    /**
     * Updates start and end date for all user program course enrolments
     *
     * @param int $programid
     * @param int $userid
     * @param int $startdate
     * @param int $enddate
     * @return bool
     * @throws coding_exception
     */
    public static function update_user_course_enrolment_dates(int $programid, int $userid, int $startdate, int $enddate): bool {
        if (!$enrolplugin = enrol_get_plugin('program')) {
            return false;
        }
        $enrolinstances = self::get_program_enrol_instances_by_programid_and_userid($programid, $userid);
        foreach ($enrolinstances as $enrolinstance) {
            $enrolplugin->update_user_enrol($enrolinstance, $userid, null, $startdate, $enddate);
        }
        return true;
    }

    /**
     * Deletes all users from a program.
     *
     * @see self::delete_program()
     * @param program $program
     */
    private static function delete_program_users(program $program): void {
        $programusers = $program->get_program_users();
        foreach ($programusers as $programuser) {
            // Delete calendar events for this user and program.
            $data = (object) [
                'userid' => $programuser->get('userid'),
                'programid' => $programuser->get('programid'),
            ];
            self::delete_calendar_events($data);

            // Remove user from course tenant groups that have component reference to this program.
            self::remove_user_from_program_course_groups($programuser);

            $programuser->delete();
        }
    }

    /**
     * Gets certifications by user id
     * TODO SP-427: move method to certification plugin?
     *
     * @param int $userid
     * @return certification[]
     */
    public static function get_certifications_by_userid($userid): array {
        global $DB;

        $certifications = [];
        $sql = 'SELECT cert.*
                FROM {' . certification::TABLE . '} cert
                WHERE id IN (
                  SELECT cuser.certificationid
                  FROM {' . certification_user::TABLE . '} cuser
                  WHERE cuser.userid = :userid
                ) ';
        $certificationnrecords = $DB->get_records_sql($sql, ['userid' => $userid]);
        foreach ($certificationnrecords as $certificationrecord) {
            $certifications[$certificationrecord->id] = new certification(0, $certificationrecord);
        }

        return $certifications;
    }

    /**
     * Recalculates program progress for all the programs related to the given course and user.
     * Create program enrolment for the user for every program containing the course.
     *
     * @param int $courseid
     * @param int $userid
     */
    public static function recalculate_program_progress_by_courseid_and_userid(int $courseid, int $userid): void {
        $programs = self::get_programs_by_courseid_and_userid($courseid, $userid);
        foreach ($programs as $program) {
            $tenant = new tenant($program->get('tenantid'));
            if (!$program->is_archived() && (int)$tenant->get('archived') === 0 && !$program->is_hidden()) {
                self::enrol_in_program_course($program->get('id'), get_course($courseid), $userid);
                self::calculate_user_program_progress($program, $userid);
            }
        }
    }

    /**
     * Recalculates program completion for all the users related to the given program.
     *
     * @param program $program
     */
    public static function recalculate_all_users_program_progress(program $program): void {
        $users = $program->get_users();
        foreach ($users as $user) {
            self::calculate_user_program_progress($program, $user->id);
        }
    }

    /**
     * Returns a list of programs that contains the given course and where the given user is allocated.
     *
     * @param int $courseid
     * @param int $userid
     * @return program[]
     */
    public static function get_programs_by_courseid_and_userid(int $courseid, int $userid): array {
        global $DB;

        $programs = [];
        $sql = 'SELECT DISTINCT pr.id
                  FROM {' . program::TABLE . '} pr
            INNER JOIN {' . program_set::TABLE . '} s
                    ON s.programid = pr.id
            INNER JOIN {' . program_course::TABLE . '} pco
                    ON pco.setid = s.id
            INNER JOIN {' . program_user::TABLE . '} pu
                    ON pu.programid = pr.id
                 WHERE pco.courseid = :coursesid
                   AND pu.userid = :userid ';
        $programids = $DB->get_fieldset_sql($sql, ['coursesid' => $courseid, 'userid' => $userid]);
        if (!empty($programids)) {
            [$sql, $params] = $DB->get_in_or_equal($programids, SQL_PARAMS_NAMED, 'id');
            $programrecords = $DB->get_records_sql('SELECT pr.* FROM {' . program::TABLE . '} pr WHERE id ' . $sql, $params);
            foreach ($programrecords as $programrecord) {
                $programs[$programrecord->id] = new program(0, $programrecord);
            }
        }

        return $programs;
    }

    /**
     * Returns user allocation statuses (it's possible to have more than one at the same time, like "completed" and "suspended").
     *
     * @param int $programid
     * @param int $userid
     * @param int $certificationid
     * @return array with allocation status and stringid for mobile app WS
     */
    public static function get_user_allocation_statuses(int $programid, int $userid, int $certificationid): array {
        $statuses = [];
        $now = time();
        $program = new program($programid);
        $baseset = $program->get_base_set();
        $programcompletion = program_set_completion::get_record([
            'setid' => $baseset->get('id'),
            'userid' => $userid,
        ]);
        /** @var program_user|false $programuser */
        $programuser = program_user::get_record([
            'programid' => $programid,
            'userid' => $userid,
            'certificationid' => $certificationid,
        ]);
        if (!$programuser) {
            return $statuses;
        }

        $issuspended = $programuser->is_suspended();
        if ($issuspended) {
            $statuses[] = [
                'status' => constants::STATUS_SUSPENDED,
                'stringid' => 'suspended'
            ];
        }

        $iscompleted = !empty($programcompletion);
        if ($iscompleted) {
            $statuses[] = [
                'status' => constants::STATUS_COMPLETED,
                'stringid' => 'completed'
            ];
        }

        if (!$iscompleted && !$issuspended) {
            $startdate = (int) $programuser->get('startdate');
            $hasstartdate = 0 !== $startdate;
            $duedate = (int) $programuser->get('duedate');
            $hasduedate = 0 !== $duedate;
            $isopen = (!$hasstartdate && !$hasduedate)
                || (!$hasstartdate && $hasduedate && $now < $duedate)
                || ($hasstartdate && $now >= $startdate && !$hasduedate)
                || ($hasstartdate && $now >= $startdate && $hasduedate && $now < $duedate);
            if ($hasstartdate && $now < $startdate) {
                // Future allocation - Before start date.
                $statuses[] = [
                    'status' => constants::STATUS_FUTUREALLOCATION,
                    'stringid' => 'futureallocation'
                ];
            } else if ($hasduedate && $now > $duedate) {
                // Overdue - Program not completed by the due date.
                $statuses[] = [
                    'status' => constants::STATUS_OVERDUE,
                    'stringid' => 'overdue'
                ];
            } else if ($isopen) {
                // Open - After start date and before due date.
                $statuses[] = [
                    'status' => constants::STATUS_OPEN,
                    'stringid' => 'open'
                ];
            }
        }

        return $statuses;
    }

    /**
     * Get user allocation status HTML badges
     *
     * @param int $status
     * @return string
     * @throws coding_exception
     */
    public static function get_user_allocation_status_badges(int $status): string {
        switch ($status) {
            case constants::STATUS_FUTUREALLOCATION:
                return html_writer::span(
                    get_string('futureallocation', 'tool_program'),
                    'badge badge-info'
                );
            case constants::STATUS_COMPLETED:
                return html_writer::span(
                    get_string('completed', 'tool_program'),
                    'badge badge-success'
                );
            case constants::STATUS_OPEN:
                return html_writer::span(
                    get_string('open', 'tool_program'),
                    'badge badge-info'
                );
            case constants::STATUS_OVERDUE:
                return html_writer::span(
                    get_string('overdue', 'tool_program'),
                    'badge badge-warning'
                );
            case constants::STATUS_SUSPENDED:
                return html_writer::span(
                    get_string('suspended', 'tool_program'),
                    'badge badge-danger'
                );
            default:
                return '';
        }
    }

    /**
     * Returns a list of accessible programs for the given user.
     *
     * Uses can_view_program() which does no take into consideration program tenants.
     *
     * @param int $userid
     * @return program[]
     */
    public static function get_user_accessible_programs(int $userid): array {
        // TODO this is never called for user other than current user.
        /** @var program[] $programs */
        $programs = self::get_programs_by_userid($userid);
        $accessibleprograms = [];
        foreach ($programs as $pkey => $program) {
            if (permission::can_view_program($program, $userid)) {
                $accessibleprograms[$pkey] = $program;
            }
        }

        return $accessibleprograms;
    }

    /**
     * Get all programs tree progress.
     *
     * @param array $programs
     * @param int $userid
     * @return array
     */
    public static function get_programs_tree_progress(array $programs, int $userid): array {
        $programstreeprogress = [];
        if ($programs) {
            foreach ($programs as $program) {
                $programstreeprogress[$program->get('id')] = new program_tree_progress($program, $userid);
            }
        }
        return $programstreeprogress;
    }

    /**
     * Returns a list of programs where the user is allocated to.
     *
     * @param int $userid
     * @return program[]
     */
    private static function get_programs_by_userid(int $userid): array {
        global $DB;

        $programs = [];
        $sql = 'SELECT p.*
                FROM {' . program::TABLE . '} p
                WHERE id IN (
                    SELECT pu.programid
                    FROM {' . program_user::TABLE . '} pu
                    WHERE pu.userid = :userid
                )';
        $programrecords = $DB->get_records_sql($sql,
            ['userid' => $userid]);
        foreach ($programrecords as $programrecord) {
            $programs[$programrecord->id] = new program(0, $programrecord);
        }

        return $programs;
    }

    /**
     * Get the program pattern datauri for programs that have no image set.
     *
     * The datauri is an encoded svg that can be passed as a url.
     * @param int $programid
     * @return string datauri
     */
    public static function get_program_pattern(int $programid): string {
        global $OUTPUT;
        $color = $OUTPUT->get_generated_color_for_id($programid);
        $pattern = new core_geopattern();
        $pattern->setColor($color);
        $pattern->patternbyid($programid);

        return $pattern->datauri();
    }

    /**
     * Checks if the passed items are the same item.
     *
     * @param program_set|program_course $item1
     * @param program_set|program_course $item2
     * @return bool True if same item, false otherwise.
     */
    private static function is_same_child_item($item1, $item2): bool {
        if (get_class($item1) !== get_class($item2)) {
            return false;
        }
        return $item1->get('id') === $item2->get('id');
    }

    /**
     * Returns a list of ids of the unlocked courses considering the program tree progress state for the given user.
     * If the same course appears unlocked more than once in the program tree, its ID will be returned only once (no duplicates).
     *
     * @param program $program
     * @param int $userid
     * @return int[]
     */
    public static function get_unlocked_courses_ids(program $program, int $userid): array {
        $unlockedcoursesids = [];
        $programtree = new program_tree_progress($program, $userid);
        $programitemslist = $programtree->to_list();
        foreach ($programitemslist as $programitem) {
            if ($programitem->isunlocked && $programitem->is_course()) {
                $courseid = $programitem->get_courseid();
                if (!in_array($courseid, $unlockedcoursesids, true)) {
                    $unlockedcoursesids[] = $programitem->get_courseid();
                }
            }
        }

        return $unlockedcoursesids;
    }

    /**
     * Checks if user allocation window is open for a given program.
     *
     * @param program $program
     * @return bool
     */
    public static function is_allocation_window_open(program $program): bool {
        $startdatetype = (int) $program->get('allocationstartdatetype');
        $enddatetype = (int) $program->get('allocationenddatetype');
        $nostartdate = $startdatetype === constants::DATE_NONE;
        $noenddate = $enddatetype === constants::DATE_NONE;
        if ($nostartdate && $noenddate) {
            return true;
        }
        $startdate = (int) $program->get('allocationstartdateabsolute');
        $enddate = (int) $program->get('allocationenddateabsolute');
        if ($enddatetype === constants::DATE_AFTER_ALLOCATION_STARTS) {
            if ($nostartdate) {
                return true;
            }

            // Calculate absolute date from the relative one.
            $enddaterelative = $program->get('allocationenddaterelative');
            $enddate = strtotime('+' . $enddaterelative, $startdate);
            $enddatetype = constants::DATE_ABSOLUTE;
        }
        $absolutestartdate = $startdatetype === constants::DATE_ABSOLUTE;
        $absoluteenddate = $enddatetype === constants::DATE_ABSOLUTE;
        $now = time();
        if ($absolutestartdate && $noenddate && $now > $startdate) {
            return true;
        }
        if ($nostartdate && $absoluteenddate && $now < $enddate) {
            return true;
        }
        if ($absolutestartdate && $now > $startdate && $absoluteenddate && $now < $enddate) {
            return true;
        }
        return false;
    }

    /**
     * Check if user can be removed from a program/certification course group
     *
     * Finds if this is the last allocation for this program or certification group. Certifications can have their own groups or
     * use same groups from the programs they are associated to.
     *
     * @param int $programid
     * @param int $userid
     * @param int $certificationid
     * @return bool
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function can_user_be_removed_from_group(int $programid, int $userid, int $certificationid): bool {
        global $DB;

        // Check if this group is shared with more user allocations. If all are suspended remove user from group.
        $certification = new certification($certificationid);
        $program = new program($programid);

        // Get the group type for this user allocation.
        if ($certificationid > 0 &&
            (int)$certification->get('autocreategroups') === (self::GROUPS_CERTIFICATION + self::GROUPS_TENANT)) {
            $grouptype = (int)$certification->get('autocreategroups');
        } else {
            $grouptype = (int)$program->get('autocreategroups');
        }

        $islastallocationtogroup = true;
        $sql = '
            SELECT tpu.*, tc.archived, tc.autocreategroups as certgroup, tp.autocreategroups as proggroup
            FROM {tool_program_users} tpu
            JOIN {tool_program} tp ON tp.id = tpu.programid
            LEFT JOIN {tool_certification} tc ON tc.id = tpu.certificationid
            WHERE tpu.programid = :programid AND tpu.userid = :userid AND tpu.status = :status
        ';
        $params = ['programid' => $programid, 'userid' => $userid, 'status' => constants::STATUS_OVERRIDE_DEFAULT];
        $programallocations = $DB->get_records_sql($sql, $params);

        // Need to check if this is the last allocation for this program OR certification tenant group user belongs to.
        foreach ($programallocations as $allocation) {
            if ($allocation->certificationid > 0 &&
                (int)$allocation->certgroup === (self::GROUPS_CERTIFICATION + self::GROUPS_TENANT)) {
                $grouptocompare = $allocation->certgroup;
            } else {
                $grouptocompare = $allocation->proggroup;
            }

            if ($grouptype == $grouptocompare && (!$allocation->certificationid || !$allocation->archived)) {
                $islastallocationtogroup = false;
                break;
            }
        }

        return $islastallocationtogroup;
    }

    /**
     * Suspend program enrolment instances for the given program user.
     *
     * @param program_user $programuser
     * @return bool
     */
    public static function suspend_allocated_user_enrolments(program_user $programuser): bool {
        if (!$enrolplugin = enrol_get_plugin('program')) {
            return false;
        }

        $programid = $programuser->get('programid');
        $userid = $programuser->get('userid');
        $certificationid = $programuser->get('certificationid');

        // Check program course groups.
        if (self::can_user_be_removed_from_group($programid, $userid, $certificationid)) {
            // Remove user from program course groups.
            self::remove_user_from_program_course_groups($programuser);
        }

        if (!self::is_active_allocation($programid, $userid)) {
            // Last active allocation has been suspended, suspend course enrolments.
            $enrolinstances = self::get_program_enrol_instances_by_programid_and_userid($programid, $userid);
            foreach ($enrolinstances as $instance) {
                $enrolplugin->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
            }
        }

        return true;
    }

    /**
     * Reactivate program enrolment instances for the given program user.
     *
     * @param program_user $programuser
     * @return bool
     */
    public static function reactivate_allocated_user_program_enrolments(program_user $programuser): bool {
        global $DB;
        if (!$enrolplugin = enrol_get_plugin('program')) {
            return false;
        }

        $programid = (int) $programuser->get('programid');
        $userid = (int) $programuser->get('userid');

        // Restore user in course tenant groups that have component reference to this program.
        self::restore_user_in_program_course_groups($programuser);

        if (self::is_active_allocation($programid, $userid)) {
            // If there is at least one active allocation, activate course enrolments.
            $enrolinstances = self::get_program_enrol_instances_by_programid_and_userid($programid, $userid);
            foreach ($enrolinstances as $instance) {
                $enrolplugin->update_user_enrol($instance, $userid, ENROL_USER_ACTIVE);
            }
        }

        return true;
    }

    /**
     * Returns program enrol instances/records given the program and user ids.
     *
     * @param int $programid
     * @param int $userid
     * @return stdClass[]
     */
    private static function get_program_enrol_instances_by_programid_and_userid(int $programid, int $userid): array {
        global $DB;

        $enrolinstances = [];
        $sql = "SELECT DISTINCT e.id
                  FROM {enrol} e
            INNER JOIN {user_enrolments} ue
                    ON e.id = ue.enrolid
                 WHERE e.customint1 = :programid
                   AND ue.userid = :userid
                   AND e.enrol = 'program' ";
        $enrolids = $DB->get_fieldset_sql($sql, ['programid' => $programid, 'userid' => $userid]);
        if (!empty($enrolids)) {
            [$sql, $params] = $DB->get_in_or_equal($enrolids, SQL_PARAMS_NAMED, 'id');
            $enrolinstances = $DB->get_records_sql('SELECT e.* FROM {enrol} e WHERE id ' . $sql, $params);
        }

        return $enrolinstances;
    }

    /**
     * Returns the program enrol instance related to the user enrolment of the given user (given its userid).
     *
     * @param int $programid
     * @param int $courseid
     * @param int $userid
     * @return stdClass|false
     */
    private static function get_program_enrol_instance_by_enrolled_user(int $programid, int $courseid, int $userid) {
        global $DB;

        $sql = "SELECT e.id
                  FROM {enrol} e
            INNER JOIN {user_enrolments} ue
                    ON e.id = ue.enrolid
                 WHERE e.customint1 = :programid
                   AND e.courseid = :courseid
                   AND ue.userid = :userid
                   AND e.enrol = 'program' ";

        $params = ['programid' => $programid, 'courseid' => $courseid, 'userid' => $userid];

        return $DB->get_record_sql('SELECT e.* FROM {enrol} e WHERE id IN (' . $sql . ')', $params);
    }

    /**
     * Check if given program belongs to any non archived certification.
     *
     * @param program $program
     * @return bool
     */
    public static function belongs_to_non_archived_certification(program $program): bool {
        // TODO WP-946 WP-966 WP-960 performs DB queries and is used in a check executed once per report row.
        $params = ['program' => $program->get('id'), 'archived' => 0, 'tenantid' => $program->get('tenantid')];
        return (bool) certification::get_records($params);
    }

    /**
     * Returns the courses ids for those courses the given user only has enrol program instances.
     *
     * @param int $userid
     * @return array
     */
    public static function get_course_ids_with_only_enrol_program_instance(int $userid): array {
        global $DB;

        $sql = "SELECT DISTINCT e.courseid
                  FROM {enrol} e
            INNER JOIN {user_enrolments} ue
                    ON e.id = ue.enrolid
                 WHERE ue.userid = ?
                   AND ue.status = 0
                   AND e.status = 0
                   AND e.enrol = 'program'
                   AND e.courseid NOT IN ( SELECT DISTINCT e.courseid
                                             FROM {enrol} e
                                       INNER JOIN {user_enrolments} ue
                                               ON e.id = ue.enrolid
                                            WHERE ue.userid = ?
                                              AND ue.status = 0
                                              AND e.status = 0
                                              AND e.enrol <> 'program' ) ";
        $courseids = $DB->get_fieldset_sql($sql, [$userid, $userid]);

        return array_values($courseids);
    }

    /**
     * Check program exists within the same tenant than the given user (defaults to current user if none provided).
     *
     * @param int $programid
     * @param int $userid
     * @return bool
     */
    public static function program_exists_in_tenant(int $programid, int $userid = 0): bool {
        global $DB;

        return $DB->record_exists(program::TABLE, [
            'id' => $programid,
            'tenantid' => tenancy::get_tenant_id($userid),
        ]);
    }

    /**
     * Get a mapped list of program ids and names, sorted by program name.
     *
     * @param int $userid
     * @return array
     */
    public static function get_programs_in_tenant_fieldset(int $userid = 0): array {
        $fieldset = [];

        [$tenantsql, $tenantparams] =
            hierarchy::filter_own_or_sub_or_parent_shared_entities_sql('tenantid');
        $programs = program::get_records_select($tenantsql, $tenantparams, 'fullname');

        foreach ($programs as $program) {
            $fieldset[$program->get('id')] = $program->get_formatted_name();
        }

        return $fieldset;
    }

    /**
     * Get a list of statuses to be used in select fields.
     *
     * @return array
     */
    public static function get_program_statuses_fieldset(): array {
        return [
            constants::STATUS_SUSPENDED => get_string('suspended', 'tool_program'),
            constants::STATUS_COMPLETED => get_string('completed', 'tool_program'),
            constants::STATUS_FUTUREALLOCATION => get_string('futureallocation', 'tool_program'),
            constants::STATUS_OVERDUE => get_string('overdue', 'tool_program'),
            constants::STATUS_OPEN => get_string('open', 'tool_program'),
        ];
    }

    /**
     * Get sql join needed to recover user program(s) statuses.
     *
     * @param string $u Table alias for table users
     * @param string $pu Table alias for table program users
     * @param string $p Table alias for table programs
     * @param string $ps Table alias for table program sets
     * @param string $psc Table alias for table program set completions
     * @param string $pid Name for named param program id. Null if fetching all user program statuses (program unspecified).
     * @return string
     */
    public static function get_status_sql_join(string $u, string $pu, string $p, string $ps, string $psc,
        string $pid = null): string {
        $specificprogram = $pid === null ? '' : "AND {$pu}.programid = :{$pid}";
        return "
            INNER JOIN {tool_program_users} {$pu}
                    ON {$pu}.userid = {$u}.id {$specificprogram}
            INNER JOIN {tool_program} {$p}
                    ON {$p}.id = {$pu}.programid
            INNER JOIN {tool_program_sets} {$ps}
                    ON {$ps}.parent = 0
                   AND {$ps}.programid = {$pu}.programid
             LEFT JOIN {tool_program_set_completion} {$psc}
                    ON {$psc}.userid = {$u}.id
                   AND {$psc}.setid = {$ps}.id
        ";
    }

    /**
     * Returns the sql status cases to be used within a sql query (WHERE CASE... THEN...) that retrieve user program statuses.
     *
     * @param int $status
     * @param string $pu Table alias for table program users
     * @param string $psc Table alias for table program set completions
     * @return string
     */
    public static function get_status_sql_cases(int $status, string $pu = 'pu', string $psc = 'psc'): string {
        $now = time();
        $statusoverridesuspendedvalue = constants::STATUS_OVERRIDE_SUSPENDED;
        $suspendedvalue = constants::STATUS_SUSPENDED;
        $completedvalue = constants::STATUS_COMPLETED;
        $futureallocationvalue = constants::STATUS_FUTUREALLOCATION;
        $overduevalue = constants::STATUS_OVERDUE;
        $openvalue = constants::STATUS_OPEN;
        $unkownvalue = constants::STATUS_UNKNOWN;
        $suspended = "{$pu}.status = {$statusoverridesuspendedvalue}";
        $notsuspended = "{$pu}.status <> {$statusoverridesuspendedvalue}";
        $completed = "{$psc}.id IS NOT NULL";
        $notcompleted = "{$psc}.id IS NULL";
        $futureallocation = "{$now} < {$pu}.startdate AND {$pu}.startdate > 0";
        $overdue = "{$pu}.duedate < {$now} AND {$pu}.duedate > 0";
        $open = "({$pu}.startdate <= {$now} OR {$pu}.startdate = 0) AND ({$pu}.duedate > {$now} OR {$pu}.duedate = 0)";
        $suspendedorcompletedvalue = ($status === $suspendedvalue || $status === $completedvalue) ? $status : $unkownvalue;

        return "
            (CASE
                WHEN {$suspended} AND {$notcompleted}
                THEN {$suspendedvalue}
                WHEN {$completed} AND {$notsuspended}
                THEN {$completedvalue}
                WHEN {$suspended} AND {$completed}
                THEN {$suspendedorcompletedvalue}
                WHEN {$futureallocation}
                THEN {$futureallocationvalue}
                WHEN {$overdue}
                THEN {$overduevalue}
                WHEN {$open}
                THEN {$openvalue}
                ELSE {$unkownvalue}
            END)
        ";
    }

    /**
     * Returns array with join, where, params to build an sql query to fetch related users with a given program status.
     *
     * @param int $programid Given program id
     * @param int $status Given status
     * @param bool $isnegated Wether we are looking for programs with the given status or without the given status
     * @param string $u Table alias for table users
     * @param string $pu Table alias for table program users
     * @param string $p Table alias for table programs
     * @param string $ps Table alias for table program sets
     * @param string $psc Table alias for table program set completions
     * @param string $pid Name for named param program id
     * @return array
     */
    public static function get_program_status_sql_query(int $programid, int $status, bool $isnegated = false,
        string $u = 'u', string $pu = 'pu', string $p = 'pro', string $ps = 'pse', string $psc = 'psc',
        string $pid = 'pid'): array {

        $join = self::get_status_sql_join($u, $pu, $p, $ps, $psc, $pid);
        $statuscondition = self::get_status_sql_cases($status, $pu, $psc);
        $whereoperator = $isnegated ? '<>' : '=';
        $where = "{$statuscondition} {$whereoperator} {$status} ";
        $params = [$pid => $programid];

        return [$join, $where, $params];
    }

    /**
     * Returns subquery that checks that a user has expected status on the given program
     *
     * Example - select all users who completed the program $pid:
     *     [$where,$params] = api::get_programs_with_criteria_conditions($pid, constants::STATUS_COMPLETED);
     *     $sql = "SELECT * FROM {user} u WHERE $where";
     *
     * @param int $programid Given program id
     * @param int $status Expected status of the program
     * @param int|null $completiondate Minimum completion date for the program (if $status==constants::STATUS_COMPLETED)
     * @param string $u Table alias for table {user} from the main query
     * @return array array [$sqlsubquery, $params]
     */
    public static function get_programs_with_criteria_conditions(int $programid, int $status,
                int $completiondate = null, string $u = 'u'): array {

        $wheredate = '';
        $u2 = db::generate_alias();
        $pu = db::generate_alias();
        $p = db::generate_alias();
        $ps = db::generate_alias();
        $psc = db::generate_alias();
        $programparam = db::generate_param_name();

        $join = self::get_status_sql_join($u2, $pu, $p, $ps, $psc, $programparam);
        $statuscase = self::get_status_sql_cases($status, $pu, $psc);

        if ($completiondate) {
            $wheredate = " AND {$psc}.completeddate >= " . $completiondate;
        }

        $where = " EXISTS (SELECT 1 FROM {user} {$u2}
                      {$join}
                WHERE {$u2}.id = {$u}.id
                  AND {$statuscase} = {$status}
                  AND {$p}.archived = 0  {$wheredate})";

        $params = [$programparam => $programid];
        return [$where, $params];
    }

    /**
     * Get programs by status and userid. This method returns only non-archived and visible programs.
     *
     * @param int $status
     * @param int $userid
     * @return program[]
     */
    public static function get_programs_by_status_and_userid(int $status, int $userid): array {
        if (!array_key_exists($status, self::get_program_statuses_fieldset())) {
            throw new coding_exception('Unexpected status passed.');
        }

        global $DB;
        $programs = [];

        $u = 'u'; // Users table alias.
        $p = 'p'; // Programs table alias.
        $pu = 'pu'; // Program users table alias.
        $ps = 'ps'; // Program sets table alias.
        $psc = 'psc'; // Program set completions table alias.
        $join = self::get_status_sql_join($u, $pu, $p, $ps, $psc);
        $statuscase = self::get_status_sql_cases($status, $pu, $psc);
        $usertenantid = tenancy::get_tenant_id($userid);

        $sql = "SELECT DISTINCT {$p}.id
                 FROM {user} {$u}
                      {$join}
                WHERE {$u}.id = :userid
                  AND {$statuscase} = {$status}
                  AND {$p}.archived = 0
                  AND {$p}.visible = 1";
        $params = [
            'userid' => $userid,
            'usertenantid' => $usertenantid,
        ];
        $programids = $DB->get_fieldset_sql($sql, $params);
        if (!empty($programids)) {
            [$sql, $params] = $DB->get_in_or_equal($programids, SQL_PARAMS_NAMED, 'id');
            $programrecords = $DB->get_records_sql('SELECT pr.* FROM {' . program::TABLE . '} pr WHERE id ' . $sql, $params);
            foreach ($programrecords as $programrecord) {
                $programs[$programrecord->id] = new program(0, $programrecord);
            }
        }

        return $programs;
    }

    /**
     * Determines if given user is enrolled to given course with the enrol program plugin.
     *
     * @param int $programid
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    public static function is_actively_enrolled_with_enrol_program(int $programid, int $courseid, int $userid): bool {
        global $DB;

        $sql = "SELECT DISTINCT e.id
                  FROM {enrol} e
            INNER JOIN {user_enrolments} ue
                    ON e.id = ue.enrolid
                 WHERE e.customint1 = :programid
                   AND e.status = 0
                   AND e.courseid = :courseid
                   AND ue.userid = :userid
                   AND ue.status = 0
                   AND e.enrol = 'program' ";

        return $DB->record_exists_sql($sql, ['programid' => $programid, 'courseid' => $courseid, 'userid' => $userid]);
    }

    /**
     * Returns the list of the program courses where this user is enrolled with the enrol program plugin.
     *
     * @param int $programid
     * @param int $userid
     * @param int $courseid
     * @return array
     * @throws \dml_exception
     */
    public static function get_all_courses_actively_enrolled_with_enrol_program(
        int $programid,
        int $userid,
        int $courseid = 0
    ): array {
        $enrolments = self::get_all_program_user_course_enrolments($programid, $userid, $courseid);
        return array_filter($enrolments, function($e) use ($programid) {
            return $e->enrol == 'program' && $e->customint1 == $programid;
        });
    }

    /**
     * Returns the list of user enrolments of the program courses
     *
     * @param int $programid
     * @param int $userid
     * @param int $courseid
     * @return array
     * @throws \dml_exception
     */
    public static function get_all_program_user_course_enrolments(
        int $programid,
        int $userid,
        int $courseid = 0
    ): array {
        global $DB;

        $sql = 'SELECT e.id, e.courseid, e.enrol, e.customint1
              FROM {enrol} e
        INNER JOIN {user_enrolments} ue
                ON e.id = ue.enrolid
             WHERE e.status = 0
               AND ue.userid = :userid
               AND ue.status = 0
               AND e.courseid IN (
                SELECT DISTINCT pco.courseid
                           FROM {' . program_course::TABLE . '} pco
                     INNER JOIN {' . program_set::TABLE . '} s
                             ON s.id = pco.setid
                          WHERE s.programid = :programid
                )';

        $params = ['programid' => $programid, 'userid' => $userid];
        if ($courseid > 0) {
            $sql .= 'AND e.courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Checks if course is already in the set.
     *
     * @param int $setid
     * @param int $courseid
     * @return bool
     */
    public static function is_course_in_set(int $setid, int $courseid): bool {
        global $DB;
        return $DB->record_exists(program_course::TABLE, ['setid' => $setid, 'courseid' => $courseid]);
    }

    /**
     * Reset program completion/progress for the given program user.
     *
     * Caller must check {@see permission::can_reset_progress()} before calling this method,
     * otherwise it may throw exceptions and reset the courses only partially.
     *
     * @param program_user $programuser
     * @param bool $marknotcompleted
     * @param bool $resetcourses
     * @return bool
     */
    public static function reset_program_progress(program_user $programuser, bool $marknotcompleted = true,
        bool $resetcourses = true): bool {
        $program = $programuser->get_program();
        $programtree = new program_tree($program);
        $baseset = $programtree->get_baseset();
        if (!$user = $programuser->get_user()) {
            return false;
        }

        $userid = $user->id;
        self::reset_set_progress($baseset, $userid, $marknotcompleted, $resetcourses);

        return true;
    }

    /**
     * Resets given set progress and its children progress (recursively).
     *
     * @param program_item $set
     * @param int $userid
     * @param bool $marknotcompleted
     * @param bool $resetcourseprogress
     */
    private static function reset_set_progress(program_item $set, int $userid, bool $marknotcompleted = true,
        bool $resetcourseprogress = true): void {
        if ($marknotcompleted) {
            // Delete completion record.
            if ($completion = program_set_completion::get_record(['setid' => $set->get_id(), 'userid' => $userid])) {
                $completion->delete();
            }
        }

        // Reset children sets and courses.
        foreach ($set->items as $item) {
            if ($item->is_set()) {
                self::reset_set_progress($item, $userid);
            } else if ($resetcourseprogress && $item->is_course()) {
                self::reset_course_progress($item, $userid);
            }
        }
    }

    /**
     * Resets given course progress.
     *
     * @param program_item $courseitem
     * @param int $userid
     * @return bool
     */
    private static function reset_course_progress(program_item $courseitem, int $userid): bool {
        $courseid = $courseitem->get_courseid();
        $programid = $courseitem->get_programid();
        $reason = get_string('programreset', 'tool_program');
        $coursereset = new course_reset_api($courseid, $userid);
        $coursereset->reset_course(['programid' => $programid, 'reason' => $reason]);

        return true;
    }

    /**
     * Add a calendar event.
     *
     * @param stdClass $data
     */
    public static function add_calendar_event(stdClass $data): void {
        $event = new stdClass();

        switch ($data->programdatetype) {
            case constants::CALENDAR_EVENT_DUE_DATE:
                $event->name = get_string('calendarduedate', 'tool_program', $data->name);
                break;
            case constants::CALENDAR_EVENT_END_DATE:
                $event->name = get_string('calendarenddate', 'tool_program', $data->name);
                break;
        }

        $event->component = 'tool_program';
        $event->eventtype = 'tool_program' . $data->programdatetype;
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->description = $event->name;
        $event->courseid = 0;
        $event->categoryid = 0;
        $event->groupid = 0;
        $event->userid = $data->userid;
        $event->modulename = '';
        $event->instance = $data->programid;
        $event->timestart = $data->timestart;
        $event->visible = 1;
        $event->timeduration = 0;
        $event->context = context_system::instance();

        calendar_event::create($event, false);
    }

    /**
     * Update a calendar event.
     *
     * Parameter $data must contain: userid, programid, name, timestart and programdatetype.
     * Values for programdatetype can be: constants::CALENDAR_EVENT_DUE_DATE and constants::CALENDAR_EVENT_END_DATE.
     *
     * @param stdClass $data
     */
    public static function update_calendar_event(stdClass $data): void {
        global $DB;

        $params = [
            'component' => 'tool_program',
            'eventtype' => 'tool_program' . $data->programdatetype,
            'instance' => $data->programid,
            'type' => CALENDAR_EVENT_TYPE_ACTION,
            'userid' => $data->userid,
        ];
        $record = $DB->get_record('event', $params);

        if ($record) {
            $record->timestart = $data->timestart;
            $DB->update_record('event', $record);
        } else {
            self::add_calendar_event($data);
        }
    }

    /**
     * Delete calendar events for a program user.
     *
     * @param stdClass $data
     * @param int|null $eventtype Remove only a specific event type (eg. On program completion remove due date event).
     */
    public static function delete_calendar_events(stdClass $data, int $eventtype = null): void {
        global $DB;

        // Remove only a specific event type.
        if ($eventtype) {
            $params = [
                'component' => 'tool_program',
                'eventtype' => 'tool_program' . $eventtype,
                'instance' => $data->programid,
                'userid' => $data->userid,
            ];

            $DB->delete_records('event', $params);

            return;
        }

        $params = [
            'component' => 'tool_program',
            'eventtype' => 'tool_program' . constants::CALENDAR_EVENT_DUE_DATE,
            'instance' => $data->programid,
            'userid' => $data->userid,
        ];

        if ($DB->record_exists('event', $params)) {
            $DB->delete_records('event', $params);
        }

        $params['eventtype'] = 'tool_program' . constants::CALENDAR_EVENT_END_DATE;

        if ($DB->record_exists('event', $params)) {
            $DB->delete_records('event', $params);
        }
    }

    /**
     * Send program completed notification to user.
     *
     * @param int $userid
     * @param int $programid
     */
    public static function send_program_completed_notification(int $userid, int $programid): void {
        global $SITE;
        $user = core_user::get_user($userid);
        $provider = 'programcompleted';
        $program = new program($programid);
        $programname = $program->get_formatted_name();
        $contextid = \context_system::instance()->id;
        $subject = get_string_manager()->get_string('notificationsubjectprogramcompleted', 'tool_program',
            $programname, $user->lang);

        $a = [
            'userfullname' => fullname($user),
            'programname' => $programname,
            'sitename' => format_string($SITE->fullname, true, ['context' => $contextid, 'escape' => false]),
            'siteurl' => (new \moodle_url('/'))->out(false),
        ];
        $fullmessage = get_string_manager()->get_string('notificationprogramcompleted', 'tool_program', $a, $user->lang);
        self::send_moodle_notification($user, $provider, $subject, $fullmessage, $program);
    }

    /**
     * Send allocated notification to the user involved.
     *
     * @deprecated since 3.11 - please use api::send_program_user_allocation_created_notification() instead.
     *
     * @param int $userid
     * @param int $programid
     * @param int $certificationid
     */
    public static function send_program_user_allocated_notification(int $userid, int $programid, int $certificationid = 0): void {
        debugging('This function is deprecated, please use api::send_program_user_allocation_created_notification() instead',
            DEBUG_DEVELOPER);
        $programuser = program_user::get_record(['userid' => $userid, 'programid' => $programid,
            'certificationid' => $certificationid]);
        self::send_program_user_allocation_created_notification($programuser);
    }

    /**
     * Send user allocated notification to the user involved.
     *
     * @param program_user $programuser
     */
    public static function send_program_user_allocation_created_notification(program_user $programuser): void {
        global $SITE;
        $user = core_user::get_user($programuser->get('userid'));
        $provider = 'programuserallocated';
        $program = $programuser->get_program();
        $programname = $program->get_formatted_name();
        $subject = get_string_manager()->get_string('notificationsubjectprogramuserallocated', 'tool_program',
            $programname, $user->lang);
        $contextid = \context_system::instance()->id;

        $duedate = '';
        $userduedate = self::get_program_user_dates($programuser)->userduedate;
        if ($userduedate !== 0 && !$programuser->get('duedatelocked')) {
            $due = userdate($userduedate, get_string_manager()->get_string('strftimedatefullshort', 'langconfig',
                null, $user->lang));
            $duedate = get_string_manager()->get_string('notificationduedate', 'tool_program', $due, $user->lang);
        }

        $a = [
            'userfullname' => fullname($user),
            'programname' => $programname,
            'duedatemsg' => $duedate,
            'sitename' => format_string($SITE->fullname, true, ['context' => $contextid, 'escape' => false]),
            'siteurl' => (new \moodle_url('/'))->out(false),
        ];
        $fullmessage = get_string_manager()->get_string('notificationprogramuserallocated', 'tool_program', $a, $user->lang);

        self::send_moodle_notification($user, $provider, $subject, $fullmessage, $program);
    }

    /**
     * Send deallocated notification to the user involved.
     *
     * @param int $userid
     * @param int $programid
     */
    public static function send_program_user_deallocated_notification(int $userid, int $programid): void {
        global $SITE;
        if (program::record_exists($programid)) {
            $user = core_user::get_user($userid);
            $provider = 'programuserdeallocated';
            $program = new program($programid);
            $programname = $program->get_formatted_name();
            $contextid = \context_system::instance()->id;
            $subject = get_string_manager()->get_string('notificationsubjectprogramuserdeallocated', 'tool_program',
                $programname, $user->lang);

            $a = [
                'userfullname' => fullname($user),
                'programname' => $programname,
                'sitename' => format_string($SITE->fullname, true, ['context' => $contextid, 'escape' => false]),
                'siteurl' => (new \moodle_url('/'))->out(false),
            ];
            $fullmessage = get_string_manager()->get_string('notificationprogramuserdeallocated', 'tool_program', $a, $user->lang);

            self::send_moodle_notification($user, $provider, $subject, $fullmessage, $program);
        }
    }

    /**
     * Sends a moodle message of the notification type.
     *
     * @param stdClass $user
     * @param string $provider
     * @param string $subject
     * @param string $fullmessage
     * @param program $program
     */
    private static function send_moodle_notification(stdClass $user, string $provider, string $subject, string $fullmessage,
                                                     program $program): void {
        $url = (new \moodle_url('/my'))->out();
        $dashboardstr = get_string_manager()->get_string('myhome', 'moodle', null, $user->lang);

        $message = new message();
        $message->courseid = SITEID;
        $message->component = 'tool_program';
        $message->name = $provider;
        $message->notification = 1;
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = html_to_text($fullmessage);
        $message->fullmessagehtml = $fullmessage;
        $message->fullmessageformat = FORMAT_HTML;
        $message->smallmessage = '';
        $message->contexturl = $url;
        $message->contexturlname = $dashboardstr;
        // Add program image url to the notification.
        $programimageurl = $program->get_image_url();
        if (!empty($programimageurl)) {
            $message->customdata = [
                'notificationiconurl' => $programimageurl,
            ];
        }

        message_send($message);
    }

    /**
     * Returns an object with the default dates for a given program.
     *
     * @param program $program
     * @return stdClass
     */
    public static function get_default_program_dates(program $program): stdClass {
        return (object) [
            'startdate' => self::get_default_program_startdate($program),
            'duedate' => self::get_default_program_duedate($program),
            'enddate' => self::get_default_program_enddate($program),
        ];
    }

    /**
     * Returns the default start date for program.
     *
     * @param program $program
     * @return string
     */
    private static function get_default_program_startdate(program $program): string {
        switch ((int) $program->get('startdatetype')) {
            case constants::DATE_NONE:
                return get_string('notset', 'tool_program');
                break;
            case constants::DATE_ABSOLUTE:
                $startdateabsolute = $program->get('startdateabsolute');
                return userdate($startdateabsolute, get_string('strftimedatefullshort'));
                break;
            case constants::DATE_AFTER_USER_ALLOCATION:
                $str = string_helper::translate_relativedate_string($program->get('startdaterelative'));
                return get_string('afteruserallocationdatewithrelativedate', 'tool_program', $str);
                break;
            default:
                throw new coding_exception('unexpected program start date type');
                break;
        }
    }

    /**
     * Returns the default due date for program.
     *
     * @param program $program
     * @return string
     */
    private static function get_default_program_duedate(program $program): string {
        switch ((int)$program->get('duedatetype')) {
            case constants::DATE_NONE:
                return get_string('notset', 'tool_program');
                break;
            case constants::DATE_ABSOLUTE:
                $duedateabsolute = $program->get('duedateabsolute');
                return userdate($duedateabsolute, get_string('strftimedatefullshort'));
                break;
            case constants::DATE_AFTER_START:
                $str = string_helper::translate_relativedate_string($program->get('duedaterelative'));
                return get_string('afterstartdatewithrelativedate', 'tool_program', $str);
                break;
            case constants::DATE_AFTER_USER_ALLOCATION:
                $str = string_helper::translate_relativedate_string($program->get('duedaterelative'));
                return get_string('afteruserallocationdatewithrelativedate', 'tool_program', $str);
                break;
            case constants::DATE_BEFORE_END:
                $str = string_helper::translate_relativedate_string($program->get('duedaterelative'));
                return get_string('beforeenddatewithrelativedate', 'tool_program', $str);
                break;
            default:
                throw new coding_exception('unexpected program due date type');
                break;
        }
    }

    /**
     * Returns the default end date for program.
     *
     * @param program $program
     * @return string
     */
    private static function get_default_program_enddate(program $program): string {
        switch ((int) $program->get('enddatetype')) {
            case constants::DATE_NONE:
                return get_string('notset', 'tool_program');
                break;
            case constants::DATE_ABSOLUTE:
                $duedateabsolute = $program->get('enddateabsolute');
                return userdate($duedateabsolute, get_string('strftimedatefullshort'));
                break;
            case constants::DATE_AFTER_START:
                $str = string_helper::translate_relativedate_string($program->get('enddaterelative'));
                return get_string('afterstartdatewithrelativedate', 'tool_program', $str);
                break;
            case constants::DATE_AFTER_DUE:
                $str = string_helper::translate_relativedate_string($program->get('enddaterelative'));
                return get_string('afterduedatewithrelativedate', 'tool_program', $str);
                break;
            case constants::DATE_AFTER_USER_ALLOCATION:
                $str = string_helper::translate_relativedate_string($program->get('enddaterelative'));
                return get_string('afteruserallocationdatewithrelativedate', 'tool_program', $str);
                break;
            default:
                throw new coding_exception('unexpected program end date type');
                break;
        }
    }

    /**
     * Reorders source set given the source set and the moving item (that is going to be removed from this set).
     *
     * @param program_set $sourceset
     * @param program_set|program_course $movingitem
     * @param program_set[]|program_course[] $reordereditems
     */
    private static function reorder_source_set($sourceset, $movingitem, array &$reordereditems): void {
        // Reorder source set.
        $expectedsortorder = 1;
        $foundmoving = false;
        $sourcesetitems = $sourceset->get_sorted_children();
        foreach ($sourcesetitems as $ikey => $sourceitem) {
            if (!$foundmoving && self::is_same_child_item($sourceitem, $movingitem)) {
                $foundmoving = true;
                unset($sourcesetitems[$ikey]);
                continue;
            }
            $currentitemsortorder = (int) $sourceitem->get('sortorder');
            if ($currentitemsortorder !== $expectedsortorder) {
                $sourceitem->set('sortorder', $expectedsortorder);
                $reordereditems[] = $sourceitem;
            }
            ++$expectedsortorder;
        }
    }

    /**
     * Reorders target set given the source set, moving item, next item within the target set, and the target set items.
     *
     * @param program_set $sourceset
     * @param program_set|program_course $movingitem
     * @param program_set|program_course $nextitem
     * @param program_set[]|program_course[] $targetsetitems
     * @param program_set[]|program_course[] $reordereditems
     */
    private static function reorder_target_set($sourceset, $movingitem, $nextitem, $targetsetitems, array &$reordereditems): void {
        $expectedsortorder = 1;
        $foundmoving = false;
        $foundnext = false;
        foreach ($targetsetitems as $ikey => $targetitem) {
            if ($sourceset === null && !$foundmoving && self::is_same_child_item($targetitem, $movingitem)) {
                $foundmoving = true;
                unset($targetsetitems[$ikey]);
                continue;
            }
            $currentitemsortorder = (int) $targetitem->get('sortorder');
            if ($nextitem !== null && !$foundnext && self::is_same_child_item($targetitem, $nextitem)) {
                $foundnext = true;
                // Clone moving item so we can use the unmodified values of the original instance in following iterations.
                /** @var program_set|program_course $clonedmovingitem */
                $clonedmovingitem = clone $movingitem;
                $clonedmovingitem->set('sortorder', $expectedsortorder);
                $reordereditems[] = $clonedmovingitem;
                ++$expectedsortorder;
            }
            if ($currentitemsortorder !== $expectedsortorder) {
                $targetitem->set('sortorder', $expectedsortorder);
                $reordereditems[] = $targetitem;
            }
            ++$expectedsortorder;
        }
        if ($nextitem === null) {
            // Add moving item at the end of the set.
            $movingitem->set('sortorder', $expectedsortorder);
            $reordereditems[] = $movingitem;
        }
    }

    /**
     * Wether the user has at least on allocation to the given program that is not marked as suspended.
     *
     * @param int $programid
     * @param int $userid
     * @return bool
     */
    public static function has_unsuspended_allocations(int $programid, int $userid): bool {
        /** @var program_user[]|false $allocations */
        $allocations = program_user::get_records(['programid' => $programid, 'userid' => $userid]);
        if (!$allocations) {
            return false;
        }

        foreach ($allocations as $allocation) {
            if (!$allocation->is_suspended()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Wether the user has any allocations to the program (used to check if progress report can be displayed).
     *
     * @param int $programid
     * @param int $userid
     * @return bool
     */
    public static function has_any_allocations(int $programid, int $userid): bool {
        /** @var program_user[]|false $allocations */
        $allocations = program_user::get_records(['programid' => $programid, 'userid' => $userid]);
        return !empty($allocations);
    }

    /**
     * By instancing a program tree with progress we automatically trigger the sets progress and completion calculations.
     *
     * @param program $program
     * @param int $userid
     * @return program_tree_progress
     */
    public static function calculate_user_program_progress(program $program, int $userid): program_tree_progress {
        return new program_tree_progress($program, $userid);
    }

    /**
     * Removes a deleted course from all programs.
     *
     * There could be more than one instance of a course within a program.
     * Used in the course_deleted observer.
     *
     * @param int $courseid
     * @throws \dml_exception
     */
    public static function remove_deleted_course_from_programs(int $courseid): void {
        global $DB;

        $sql = 'SELECT pco.*
                           FROM {' . program_course::TABLE . '} pco
                     INNER JOIN {' . program_set::TABLE . '} ps
                     ON ps.id = pco.setid
                     INNER JOIN {' . program::TABLE . '} p
                             ON p.id = ps.programid
                          WHERE pco.courseid = ?';

        $courselist = $DB->get_records_sql($sql, [$courseid]);

        if (!empty($courselist)) {
            foreach ($courselist as $item) {
                $programcourse = new program_course(0, $item);
                self::delete_program_course($programcourse);
            }
        }
    }

    /**
     * Removes a deleted user from all programs.
     * Used in the user_deleted observer.
     *
     * @param int $userid
     */
    public static function remove_deleted_user_from_programs(int $userid): void {
        $programs = self::get_programs_by_userid($userid);
        if (!empty($programs)) {
            foreach ($programs as $program) {
                self::deallocate_user($program->get('id'), $userid);
            }
        }
    }

    /**
     * Checks if program idnumber is unique within a tenant. We can have more than one idnumber empty.
     *
     * @param int $programid
     * @param string $idnumber
     * @return bool
     */
    public static function is_idnumber_unique(int $programid, string $idnumber): bool {
        global $DB;

        if (!strlen($idnumber)) {
            return true;
        }

        // We need a case insensitive comparison on the value.
        $equal = $DB->sql_equal('idnumber', ':idnumber', false);
        $query = "SELECT COUNT(1)
                FROM {tool_program}
                WHERE $equal AND tenantid = :tenantid and archived = 0 AND id <> :programid AND idnumber <> :emptystring";
        $params = [
            'idnumber' => $idnumber,
            'tenantid' => tenancy::get_tenant_id(),
            'programid' => $programid,
            'emptystring' => ''
        ];

        return !($DB->count_records_sql($query, $params) > 0);
    }

    /**
     * Returns program record by idnumber
     *
     * @param string $idnumber
     * @param int $tenantid
     * @return program
     * @throws \dml_exception
     */
    public static function get_program_by_idnumber(string $idnumber, int $tenantid): ?program {
        global $DB;

        [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid', 'shared=1', $tenantid);
        // We need a case insensitive comparison on the value.
        $equal = $DB->sql_equal('idnumber', ':idnumber', false);
        $query = "SELECT *
                FROM {tool_program}
                WHERE $equal AND $sql and archived = 0";
        $params += ['idnumber' => $idnumber];

        if ($record = $DB->get_record_sql($query, $params)) {
            return new program(0, $record);
        }
        return null;
    }

    /**
     * Deletes dynamic rules associated to the program. Used when deleting a program.
     *
     * @param int $programid
     */
    public static function delete_program_dynamic_rules(int $programid): void {
        // Check if tool_dynamicrule is installed.
        if (!class_exists(\tool_dynamicrule\api::class)) {
            return;
        }
        $params = [
            'component' => 'tool_program',
            'componentarea' => 'program',
            'itemid' => $programid
        ];
        $rules = \tool_dynamicrule\rule::get_records($params);
        foreach ($rules as $rule) {
            // TODO Change when WP-1293 is implemented.
            $rule->delete();
        }
    }

    /**
     * Get potential programs for the program selector.
     *
     * @uses \tool_dynamicrule\permission::can_manage_rules
     *
     * @param string $search
     * @return array
     * @throws \dml_exception
     */
    public static function get_potential_programs(string $search): array {
        global $DB;

        if (!component_class_callback('\tool_dynamicrule\permission', 'can_manage_rules', [], false) &&
            !permission::has_edit_capability()) {
            return [];
        }

        $params = ['isshared' => 1];
        [$tenantsql, $tenantparams] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid', 'shared=:isshared');
        $query = 'SELECT id, fullname
            FROM {tool_program}
            WHERE archived = 0 AND '.$tenantsql;

        $i = 0;
        $params += $tenantparams;

        foreach (preg_split('/ +/', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $i++;
            $query .= ' AND (' .
                $DB->sql_like('fullname', ":search{$i}1", false, false)
                . ' OR ' .
                $DB->sql_like('idnumber', ":search{$i}2", false, false)
                . ')';
            $params += ["search{$i}1" => '%' . $word . '%', "search{$i}2" => '%' . $word . '%'];
        }
        $query .= ' ORDER BY fullname';

        $results = $DB->get_records_sql($query, $params);

        // We apply format string to the fullname.
        $formatparams = ['context' => context_system::instance(), 'escape' => false];
        foreach ($results as $result) {
            $result->fullname = format_string($result->fullname, true, $formatparams);
        }

        return $results;
    }

    /**
     * Updates the program name in all program related calendar events
     *
     * @param int $programid
     * @param string $programname
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function update_program_name_in_calendar_events(int $programid, string $programname): void {
        global $DB;
        $params = [
            'component' => 'tool_program',
            'instance' => $programid,
        ];
        $events = $DB->get_records('event', $params);
        foreach ($events as $event) {
            switch ($event->eventtype) {
                case 'tool_program' . constants::CALENDAR_EVENT_DUE_DATE:
                    $event->name = get_string('calendarduedate', 'tool_program', $programname);
                    $event->description = get_string('calendarduedate', 'tool_program', $programname);
                    break;
                case 'tool_program' . constants::CALENDAR_EVENT_END_DATE:
                    $event->name = get_string('calendarenddate', 'tool_program', $programname);
                    $event->description = get_string('calendarenddate', 'tool_program', $programname);
                    break;
            }
            $DB->update_record('event', $event);
        }
    }

    /**
     * Hide all user calendar events for a given program
     *
     * @param program $program
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function hide_program_user_calendar_events(program $program): void {
        global $DB;
        $params = [
            'component' => 'tool_program',
            'instance' => $program->get('id'),
        ];
        $events = $DB->get_records('event', $params);
        foreach ($events as $event) {
            $event->visible = 0;
            $DB->update_record('event', $event);
        }
    }

    /**
     * Show all user calendar events for a given program
     *
     * @param program $program
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function show_program_user_calendar_events(program $program): void {
        global $DB;
        $params = [
            'component' => 'tool_program',
            'instance' => $program->get('id'),
        ];
        $events = $DB->get_records('event', $params);
        foreach ($events as $event) {
            $event->visible = 1;
            $DB->update_record('event', $event);
        }
    }

    /**
     * Remove all users from tenant groups that have component reference to this program.
     *
     * @param program $program
     * @throws coding_exception
     */
    public static function remove_all_users_from_program_course_groups(program $program): void {
        $programusers = program_user::get_records(['programid' => $program->get('id')]);

        foreach ($programusers as $programuser) {
            self::remove_user_from_program_course_groups($programuser);
        }
    }

    /**
     * Remove user from program course groups.
     *
     * Depending whether this program user is allocated through program or certification,
     * user will be removed from respected component-specific tenant groups in the course.
     *
     * @param program_user $programuser
     * @throws coding_exception
     */
    public static function remove_user_from_program_course_groups(program_user $programuser): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $programid = (int)$programuser->get('programid');
        $userid = (int)$programuser->get('userid');
        $certificationid = (int)$programuser->get('certificationid');

        $tenantgroups = self::get_tenant_group_records($programid, $certificationid);

        foreach ($tenantgroups as $tenantgroup) {
            groups_remove_member($tenantgroup->get('groupid'), $userid);
        }
    }

    /**
     * Restore all program users membership in tenant course groups.
     *
     * Restore all program users membership in tenant course groups that have
     * component reference to this program.
     *
     * @param program $program
     * @throws coding_exception
     */
    public static function restore_all_users_in_program_course_groups(program $program): void {
        $programusers = program_user::get_records([
            'programid' => $program->get('id'),
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ]);

        foreach ($programusers as $programuser) {
            self::restore_user_in_program_course_groups($programuser);
        }
    }

    /**
     * Restore program user membership in tenant course groups that have component reference to this program.
     *
     * @param program_user $programuser
     * @throws coding_exception
     */
    public static function restore_user_in_program_course_groups(program_user $programuser): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        if ((int)$programuser->get('status') === constants::STATUS_SUSPENDED) {
            // We don't restore groups for suspended users.
            return;
        }

        $programid = (int)$programuser->get('programid');
        $userid = (int)$programuser->get('userid');
        $certificationid = (int)$programuser->get('certificationid');

        // If certification is archived and has its own group do not restore course groups.
        $certification = new certification($certificationid);
        if ((int)$certification->get('archived') === 1 &&
            $certification->get('autocreategroups') == (self::GROUPS_CERTIFICATION + self::GROUPS_TENANT)) {
            return;
        }

        $courses = $programuser->get_program()->get_courses();
        foreach ($courses as $course) {
            $enrolinstance = self::get_program_enrol_instance_by_enrolled_user($programid, $course->id, $userid);
            if ($enrolinstance) {
                self::add_user_to_course_groups($enrolinstance, $userid, $course);
            }
        }
    }

    /**
     * Returns existing tenant groups for program/certification.
     *
     * Only tenant groups that have component reference are returned. If there are no enrolled students, some
     * groups may not exist yet, do not use this function for adding students to the groups, only for removing.
     *
     * @param int $programid Pass 0 in case of certification
     * @param int $certificationid
     * @return array
     * @throws coding_exception
     */
    public static function get_tenant_group_records(int $programid, int $certificationid): array {
        $program = new program($programid);
        $certification = new certification($certificationid);

        if ($certificationid > 0 &&
            ((int)$certification->get('autocreategroups') === (self::GROUPS_CERTIFICATION + self::GROUPS_TENANT))) {
            $params = [
                'itemid' => $certificationid,
                'component' => 'tool_certification',
                'area' => 'tool_certification',
            ];
        } else if ((int)$program->get('autocreategroups') === (self::GROUPS_PROGRAM + self::GROUPS_TENANT)) {
            $params = [
                'itemid' => $programid,
                'component' => 'tool_program',
                'area' => 'tool_program',
            ];
        } else {
            return [];
        }

        return tenant_group::get_records($params);
    }

    /**
     * Deletes tenant groups that have component reference to this program/certification course
     *
     * @param string $component tool_program or tool_certification
     * @param string $area tool_program or tool_certification
     * @param int $itemid programid or certificationid depending on component
     * @param int $courseid
     * @throws coding_exception
     */
    public static function delete_component_course_tenant_group(string $component, string $area, int $itemid, int $courseid): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $params = [
            'component' => $component,
            'area' => $area,
            'itemid' => $itemid,
            'courseid' => $courseid,
        ];
        $records = tenant_group::get_records($params);
        foreach ($records as $record) {
            // Delete course group.
            groups_delete_group($record->get('groupid'));
            // Delete tenant group record.
            $record->delete();
        }
    }

    /**
     * Deletes instance of program enrol method in a course.
     *
     * @param int $programid
     * @param int $courseid
     * @return bool
     */
    public static function delete_program_course_enrol_instance(int $programid, int $courseid): bool {
        global $DB;

        if (!$enrolplugin = enrol_get_plugin('program')) {
            return false;
        }

        // Remove program component-specific tenant group from the course.
        self::delete_component_course_tenant_group('tool_program', 'tool_program', $programid, $courseid);

        // Remove certification component-specific tenant group (if program is associated with any certifications and
        // certification settings were "create groups for this certification") from the course.
        $autocreategroups = self::GROUPS_CERTIFICATION + self::GROUPS_TENANT;
        $certifications = certification::get_records(['program' => $programid, 'autocreategroups' => $autocreategroups]);
        foreach ($certifications as $certification) {
            self::delete_component_course_tenant_group('tool_certification', 'tool_certification',
                $certification->get('id'), $courseid);
        }

        $params = [
            'courseid' => $courseid,
            'enrol' => 'program',
            'customint1' => $programid,
        ];
        if (!$enrolinstance = $DB->get_record('enrol', $params)) {
            return false;
        }

        $enrolplugin->delete_instance($enrolinstance);

        return true;
    }

    /**
     * Deletes all allocated users enrolments for all courses related to the given program.
     *
     * @param program $program
     */
    public static function delete_all_allocated_users_enrolments(program $program): void {
        // Delete all program course enrol instances.
        $coursesids = $program->get_courses_ids();
        foreach ($coursesids as $courseid) {
            self::delete_program_course_enrol_instance($program->get('id'), $courseid);
        }
    }

    /**
     * Unenrol program user from program courses.
     *
     * @param program_user $programuser
     * @return bool
     */
    public static function remove_user_from_program_courses(program_user $programuser): bool {
        if (!$enrolplugin = enrol_get_plugin('program')) {
            return false;
        }

        $programid = (int) $programuser->get('programid');
        $userid = (int) $programuser->get('userid');

        $enrolinstances = self::get_program_enrol_instances_by_programid_and_userid($programid, $userid);
        foreach ($enrolinstances as $instance) {
            // Unenrol user. This will remove user from all groups linked to enrolment.
            $enrolplugin->unenrol_user($instance, $userid);
        }
        return true;
    }

    /**
     * Remove users from all tenant groups that do not belong to his current tenant,
     * regardless of any program allocations.
     *
     * @param int $userid
     * @throws \dml_exception
     */
    public static function remove_user_from_previous_tenant_groups(int $userid): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        // Remove this user from all component-less tenant groups that are associated
        // with non-current tenant.
        $sql = "
            SELECT gm.id, gm.groupid, gm.userid
              FROM {groups_members} gm
              JOIN {tool_tenant_group} ttg ON ttg.groupid = gm.groupid
              JOIN {groups} g ON g.courseid = ttg.courseid AND gm.groupid = g.id
             WHERE ttg.component IS NULL AND ttg.area IS NULL AND ttg.itemid IS NULL
               AND ttg.tenantid <> :usernewtenantid AND gm.userid = :userid
               AND gm.component = 'enrol_program'
        ";
        $params = ['userid' => $userid, 'usernewtenantid' => tenancy::get_tenant_id($userid)];
        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $record) {
            groups_remove_member($record->groupid, $record->userid);
        }
    }

    /**
     * Returns a list of accessible courses for the given user excluding courses enrolled only with the enrol program plugin.
     *
     * @param int $userid
     * @return stdClass[]
     */
    public static function get_user_accessible_courses(int $userid): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $hiddencourses = get_hidden_courses_on_timeline();
        $workplaceexcludedcourses = self::get_course_ids_with_only_enrol_program_instance($userid);
        $hiddencourses = array_unique(array_merge($hiddencourses, $workplaceexcludedcourses));
        return enrol_get_my_courses('summary, summaryformat, enddate', null, 0, [], false, 0, $hiddencourses);
    }

    /**
     * Get courses completions for a given user
     *
     * @param stdClass[] $courses
     * @param int $userid
     * @return array
     */
    public static function get_user_courses_completion(array $courses, int $userid): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED, 'cid', true, 0);

        $sql = "SELECT course, timecompleted
                FROM {course_completions}
                WHERE course $insql AND userid = :userid";
        $params = $inparams + ['userid' => $userid];
        $completions = $DB->get_records_sql($sql, $params);
        $coursecompletions = [];
        foreach ($courses as $course) {
            $completion = new \completion_info($course);
            $coursecompletions[$course->id] = (object) [
                'timecompleted' => $completions[$course->id]->timecompleted ?? null,
                'completionenabled' => $completion->is_enabled(),
            ];
        }
        return $coursecompletions;
    }

    /**
     * Returns list of progress percentage for each course from a given user
     *
     * @param int $userid
     * @param array $courses
     * @param array|null $coursescompletion
     * @return array
     */
    public static function get_user_courses_progress(int $userid, array $courses, ?array $coursescompletion = null): array {
        $coursesprogress = [];

        if (!$coursescompletion) {
            $coursescompletion = self::get_user_courses_completion($courses, $userid);
        }

        foreach ($courses as $course) {
            $courseprogress = progress::get_course_progress_percentage($course, $userid) ?? 0;
            // Adjust progress to maximum 95% if course is not completed.
            if (is_null($coursescompletion[$course->id]->timecompleted)) {
                $courseprogress = min($courseprogress, 95);
            }
            $coursesprogress[$course->id] = (float)$courseprogress;
        }
        return $coursesprogress;
    }

    /**
     * Get last access timestamp for the user for multiple courses at once
     *
     * @param int $userid
     * @param array $courseids
     * @return array
     */
    public static function get_last_course_access(int $userid, array $courseids): array {
        global $DB;
        if (!$courseids) {
            return [];
        }
        $courseids = array_unique($courseids);
        [$sql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
        return $DB->get_records_select('user_lastaccess', 'userid=:user AND courseid '.$sql,
            $params + ['user' => $userid], '', 'courseid, timeaccess');
    }

    /**
     * Get all user start dates for the courses
     *
     * @param int $userid
     * @param array $courseids
     * @return array
     */
    public static function get_all_user_course_startdates(int $userid, array $courseids): array {
        global $DB;
        if (!$courseids) {
            return [];
        }
        $courseids = array_unique($courseids);
        [$sqlids, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');

        $sql = "SELECT e.courseid, MIN(ue.timestart) as timestart
                  FROM {enrol} e
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = :userid
                  JOIN {course} c ON c.id = e.courseid
                 WHERE ue.status = :active
                   AND e.status = :enabled
                   AND ue.timestart >= c.startdate
                   AND c.id $sqlids
                   GROUP BY e.courseid";
        $params['userid'] = $userid;
        $params['active'] = ENROL_USER_ACTIVE;
        $params['enabled'] = ENROL_INSTANCE_ENABLED;

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns all calculated program user dates
     *
     * @param program_user $programuser
     * @return stdClass
     * @throws coding_exception
     */
    public static function get_program_user_dates(program_user $programuser): stdClass {
        $userallocationdate = (int)$programuser->get('timecreated');
        $program = $programuser->get_program();
        $userstartdate = self::recalculate_user_start_date($program, $programuser, $userallocationdate);
        $userduedate = self::recalculate_user_due_date($program, $programuser, $userallocationdate, $userstartdate);
        $userenddate = self::recalculate_user_end_date($program, $programuser, $userallocationdate, $userstartdate, $userduedate);

        return (object)[
            'userstartdate' => $userstartdate,
            'userduedate' => $userduedate,
            'userenddate' => $userenddate,
        ];
    }

    /**
     * Filter array of courses by hideprogramcourses setting
     *
     * @param array $courses
     * @return array
     */
    public static function filter_by_hideprogramcourses(array $courses): array {
        global $USER;

        // If hideprogramcourses setting is not enabled, myoverview block should not hide program courses.
        if (empty(get_config('theme_workplace', 'hideprogramcourses'))) {
            return $courses;
        }

        $programcoursesids = self::get_course_ids_with_only_enrol_program_instance($USER->id);
        return array_filter($courses, static function(stdClass $course) use ($programcoursesids) {
            return !in_array($course->id, $programcoursesids);
        });
    }

    /**
     * Return program set completion records for a given program and user
     *
     * @param int $programid
     * @param int $userid
     * @return array
     */
    public static function get_program_set_completions(int $programid, int $userid): array {
        global $DB;

        $sql = "
            SELECT psc.*
            FROM {tool_program_set_completion} psc
            JOIN {tool_program_sets} ps ON psc.setid = ps.id
            WHERE ps.programid = :programid AND psc.userid = :userid";
        return $DB->get_records_sql($sql, ['programid' => $programid, 'userid' => $userid]);
    }

    /**
     * Recalculate program completion for a user
     *
     * @param program_user $programuser
     * @return void
     */
    public static function recalculate_program_user_completion(program_user $programuser): void {
        global $DB;

        $completiondates = [];
        $records = self::get_program_set_completions($programuser->get('programid'), $programuser->get('userid'));
        foreach ($records as $record) {
            // Completion dates have to be preserved.
            $completiondates[$record->setid] = $record->completeddate;
            $DB->delete_records('tool_program_set_completion', ['id' => $record->id]);
        }

        self::calculate_user_program_progress($programuser->get_program(), $programuser->get('userid'));

        // Replace new records with the old completion dates.
        $records = self::get_program_set_completions($programuser->get('programid'), $programuser->get('userid'));
        foreach ($records as $record) {
            if (isset($completiondates[$record->setid])) {
                $record->completeddate = $completiondates[$record->setid];
                $DB->update_record('tool_program_set_completion', $record);
            }
        }
    }

    /**
     * Returns list of programs that contain the given course and are accesible by the user
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public static function get_linked_programs_to_a_user_course(int $courseid, int $userid = 0): array {
        global $USER;
        [$joins, $where] = self::get_active_allocations_sql();
        $sql = "SELECT pu.programid
                      FROM {".program_user::TABLE."} pu
                      $joins
                      JOIN {tool_program_sets} ps ON ps.programid = pu.programid
                      JOIN {tool_program_courses} pc ON pc.setid = ps.id
                     WHERE $where
                      AND pu.userid = :userid
                      AND pc.courseid = :courseid";

        return program::get_records_select("id in ($sql)",
            ['courseid' => $courseid, 'userid' => $userid ?: $USER->id]);
    }

    /**
     * Checks if a course belongs to any program the user is allocated to
     *
     * @param int $courseid
     * @param int|stdClass|null $user
     * @return bool
     */
    public static function is_course_in_user_programs(int $courseid, $user = null): bool {
        if ($user) {
            $userid = is_int($user) ? $user : $user->id;
        }

        $persistents = self::get_linked_programs_to_a_user_course($courseid, $userid ?? 0);
        return !empty($persistents);
    }
}
