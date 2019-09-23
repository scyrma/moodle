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
 * Api class for tool_program
 *
 * @package    tool_program
 * @copyright  2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program;

use calendar_event;
use coding_exception;
use context_course;
use context_system;
use core\message\message;
use core_geopattern;
use core_tag_tag;
use core_user;
use enrol_program_plugin;
use moodle_exception;
use stdClass;
use tool_certification\certification;
use tool_certification\certification_user;
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
use tool_program\form\edit_program_details_form;
use tool_program\persistent\program;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;
use tool_program\persistent\program_set_completion;
use tool_program\persistent\program_user;
use tool_tenant\tenancy;
use core_text;
use tool_tenant\tenant_group;
use tool_wp\course_reset;
use tool_wp\course_reset_api;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/lib.php');

/**
 * Class api
 *
 * @package tool_program
 * @copyright  2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
            'startdatetype' => 1,
            'startdateabsolute' => 1,
            'enddatetype' => 1,
            'enddateabsolute' => 1,
            'duedatetype' => 1,
            'duedateabsolute' => 1,
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
        core_tag_tag::set_item_tags('tool_program', 'tool_program', $newprogram->get('id'), $context, $data->program_tags);

        // Check if tool_dynamicrule is installed.
        if (class_exists('\\tool_dynamicrule\\rules_list')) {
            // Create default dynamic rules for dynamic rules tab.
            self::add_default_dynamicrule_conditions_to_program($newprogram->get('id'), $newprogram->get('tenantid'));
        }

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
        $component = 'tool_program';
        $componentarea = 'program';
        $configdata = ['programid' => $programid];
        $name = get_string('programrules', 'tool_program');

        $conditions = [
            'user_allocated',
            'program_completed',
            'program_overdue',
            'program_suspended',
        ];

        foreach ($conditions as $condition) {
            // Create rule.
            $ruleid = \tool_dynamicrule\api::create_rule_for_component($component, $componentarea, $programid, $tenantid, $name);
            $conditionclass = '\\tool_program\\tool_dynamicrule\\condition\\' . $condition;
            // Create condition. No need to verify user tenancy,
            // we are creating condition for rule that was just created.
            \tool_dynamicrule\api::create_rule_condition($ruleid, $conditionclass, $configdata, true);
        }
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
        core_tag_tag::set_item_tags('tool_program', 'tool_program', $data->id, $context, $data->program_tags);

        $program = new program($data->id);
        $program->set('fullname', $data->fullname);
        $program->set('idnumber', $data->idnumber);
        $program->set('description', $data->description);
        $program->set('descriptionformat', $data->descriptionformat);
        $program->set('visible', $data->visible);
        $program->set('allowdirectallocation', $data->allowdirectallocation);
        $program->set('autocreategroups', $data->autocreategroups);
        $program->update();

        // Trigger event.
        program_updated::create_from_program_updated($program)->trigger();
    }

    /**
     * Updates calendar tab from a program.
     *
     * @param stdClass $data
     * @return bool
     */
    public static function update_program_calendar(stdClass $data): bool {
        $program = new program($data->id);
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
        program_updated::create_from_program_updated($program)->trigger();

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

        // Suspend all program enrolments of all the allocated users.
        self::suspend_all_allocated_users_enrolments($program);

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

        // Create event.
        $event = program_deleted::create_from_program_deleted($program);

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

        $course = get_course($newprogramcourse->get('courseid'));
        self::enable_program_course_enrol_instance($data->programid, $course);

        // Trigger event.
        program_course_created::create_from_program_course_created($newprogramcourse, $data->programid)->trigger();

        // TODO enrol allocated users if they are already enrolled.

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

        // If this course does not exist anymore within the program, disable its enrol_program instance.
        if (!self::is_course_in_program($program, $course->id)) {
            self::disable_course_enrol_program_instance($programid, $course->id);
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
            'status' => 1,
        ]);
        $insertdata->programid = $program->get('id');

        if (isset($insertdata->status) && constants::STATUS_OVERRIDE_SUSPENDED === (int) $insertdata->status) {
            $insertdata->timesuspended = time();
        }

        // Allocate user to this program.
        $newuser = new program_user(0, $insertdata);
        $newuser->set_program($program);
        $newuser->create();

        if (!$newuser->is_certification_allocation()) {
            self::recalculate_program_user_dates($program, $newuser);
        }
        self::create_enrol_instances_if_user_already_enroled($program, $newuser);
        self::reactivate_allocated_user_program_enrolments($newuser);
        self::calculate_user_program_progress($program, $newuser->get('userid'));

        // Trigger event.
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

        /** @var program_user|false $programuser */
        $programuser = program_user::get_record($params);

        if (!$programuser) {
            return false;
        }

        // Create event.
        $event = user_allocation_deleted::create_from_user_allocation_deleted($programuser);

        $programuser->delete();

        if (!program_user::get_records(['programid' => $programid, 'userid' => $userid])) {
            // If no user allocations are left, suspend all program enrolments to all the program courses.
            self::suspend_allocated_user_enrolments($programuser);
        }

        // Delete calendar events for this user and program.
        $data = (object) [
            'userid' => $userid,
            'programid' => $programid,
        ];
        self::delete_calendar_events($data);

        // Trigger event.
        $event->trigger();

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

        $programuser->set('startdate', $userstartdate);
        $programuser->set('duedate', $userduedate);
        $programuser->set('enddate', $userenddate);

        $programid = $programuser->get('programid');
        $userid = $programuser->get('userid');

        // Create or update due date calendar event.
        $data = (object) [
            'userid' => $userid,
            'name' => format_string($program->get('fullname')),
            'programid' => $programid,
            'timestart' => $userduedate,
            'programdatetype' => constants::CALENDAR_EVENT_DUE_DATE
        ];
        self::update_calendar_event($data);

        $data->programdatetype = constants::CALENDAR_EVENT_END_DATE;
        $data->timestart = $userenddate;
        $data->name = format_string($program->get('fullname'));
        self::update_calendar_event($data);

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
     * Disables instance of course in the program.
     *
     * @param int $programid
     * @param int $courseid
     * @return bool
     */
    private static function disable_course_enrol_program_instance(int $programid, int $courseid): bool {
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
     * Creates or enables program enrol instance in a course.
     *
     * @param int $programid
     * @param stdClass $course
     * @return ?stdClass
     */
    private static function enable_program_course_enrol_instance(int $programid, stdClass $course): ?stdClass {
        global $DB;

        if (!$enrolplugin = enrol_get_plugin('program')) {
            return null;
        }

        $previousenrolinstance = $DB->get_record('enrol', [
            'courseid' => $course->id,
            'enrol' => 'program',
            'customint1' => $programid
        ]);

        if ($previousenrolinstance) {
            if (ENROL_INSTANCE_ENABLED === (int) $previousenrolinstance->status) {
                return $previousenrolinstance;
            }
            $previousenrolinstance->status = ENROL_INSTANCE_ENABLED;
            $DB->update_record('enrol', $previousenrolinstance);
            return $previousenrolinstance;
        }

        $studentrole = $DB->get_record('role', ['shortname' => 'student']); // TODO make sure it is customizable.
        $id = $enrolplugin->add_instance($course, ['customint1' => $programid, 'roleid' => $studentrole->id]);
        return $DB->get_record('enrol', ['id' => $id]);
    }

    /**
     * Enrol user to a course via the program, refreshes the enrolment and groups if needed
     *
     * For performance reasons this function does not check that course is inside the program
     * and user is allocated to the program. This has to be checked before calling this method.
     *
     * @param int $programid
     * @param stdClass $course
     * @param int $userid
     * @return bool
     */
    public static function enrol_in_program_course(int $programid, stdClass $course, int $userid) {
        /** @var enrol_program_plugin $enrolplugin */
        $enrolplugin = enrol_get_plugin('program');
        if (!$enrolplugin) {
            return false;
        }
        if (!$enrolinstance = self::enable_program_course_enrol_instance($programid, $course)) {
            return false;
        }
        $enrolplugin->enrol_user($enrolinstance, $userid, $enrolinstance->roleid, 0, 0, ENROL_USER_ACTIVE);

        // Add to groups.
        $groups = self::get_user_groups_in_course($programid, $course, $userid);
        foreach ($groups as $groupid) {
            if (!groups_is_member($groupid, $userid)) {
                groups_add_member($groupid, $userid, 'enrol_program', $enrolinstance->id);
            }
        }

        return true;
    }

    /**
     * List of groups where user should be added to when enrolling in the course
     *
     * If user has multiple allocation to the program (direct and via certifications) this can return more
     * than one group but usually it will be either empty array or array with one group
     *
     * @param int $programid
     * @param stdClass $course
     * @param int $userid
     * @return array list of group ids
     */
    protected static function get_user_groups_in_course(int $programid, stdClass $course, int $userid): array {

        /** @var program_user[] $allocations */
        $allocations = program_user::get_records(['programid' => $programid, 'userid' => $userid]);
        $program = new program($programid);

        $groups = [];
        foreach ($allocations as $allocation) {
            if (($certification = $allocation->get_certification())
                    && $certification->get('autocreategroups') != self::GROUPS_AS_IN_PROGRAMS) {
                $autocreategroups = $certification->get('autocreategroups');
            } else {
                $autocreategroups = $program->get('autocreategroups');
            }
            $usertenantid = tenancy::get_tenant_id($userid);
            $defaultname = [];
            $component = $area = $itemid = null;
            if (($autocreategroups & self::GROUPS_TENANT) && (tenancy::is_shared_course($course, $usertenantid))) {
                $tenantid = $usertenantid;
                $defaultname[] = tenancy::get_tenants()[$usertenantid]->name;
            } else {
                $tenantid = null;
            }
            if ($autocreategroups & self::GROUPS_CERTIFICATION) {
                $component = $area = 'tool_certification';
                $itemid = $certification->get('id');
                $defaultname[] = $certification->get('fullname');
            } else if ($autocreategroups & self::GROUPS_PROGRAM) {
                $component = $area = 'tool_program';
                $itemid = $programid;
                $defaultname[] = $program->get('fullname');
            }
            $groups[] = tenancy::get_course_group($course, join(' - ', $defaultname), $tenantid, $component, $area, $itemid);
        }
        return array_values(array_filter(array_unique($groups)));
    }

    /**
     * Check if user is enroled in a course within the program, then create user enrol program instances.
     *
     * @param program $program
     * @param program_user $programuser
     * @return bool
     */
    private static function create_enrol_instances_if_user_already_enroled(program $program, program_user $programuser): bool {
        $courses = $program->get_courses();
        $programid = $program->get('id');
        $userid = $programuser->get('userid');

        foreach ($courses as $course) {
            if (is_enrolled(context_course::instance($course->id), $userid, '', true)) {
                self::enrol_in_program_course($programid, $course, $userid);
            }
        }

        return true;
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
        $program->set('visible', $visibility);
        $program->update();

        // Trigger event.
        program_updated::create_from_program_updated($program)->trigger();

        return true;
    }

    /**
     * Archives a program.
     *
     * @param program $program
     * @return bool
     */
    public static function archive_program(program $program): bool {
        $program->set('archived', 1);
        $program->set('timearchived', time());
        $program->update();

        // Trigger event.
        program_updated::create_from_program_updated($program)->trigger();

        return true;
    }

    /**
     * Restores a program.
     *
     * @param program $program
     * @return bool
     */
    public static function restore_program(program $program): bool {
        $program->set('archived', 0);
        $program->set('timearchived', 0);
        // Check that idnumber is unique and is not present in another active program in this tenant.
        if (!self::is_idnumber_unique((int)$program->get('id'), (string)$program->get('idnumber'))) {
            $program->set('idnumber', '');
        }
        $program->update();

        // Trigger event.
        program_updated::create_from_program_updated($program)->trigger();

        self::recalculate_all_users_program_progress($program);

        return true;
    }

    /**
     * Suspends all allocated users enrolments for all courses related to the given program.
     *
     * @param program $program
     */
    private static function suspend_all_allocated_users_enrolments(program $program): void {
        if ($enrolplugin = enrol_get_plugin('program')) {
            $programid = $program->get('id');
            $userslist = $program->get_users();
            foreach ($userslist as $user) {
                $enrolinstances = self::get_program_enrol_instances_by_programid_and_userid($programid, $user->id);
                foreach ($enrolinstances as $instance) {
                    $enrolplugin->update_user_enrol($instance, $user->id, ENROL_USER_SUSPENDED);
                }
            }
        }
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
     * Checks if user has an allocation, is not suspended and allocation is currently within its start and end dates.
     *
     * @param int $programid
     * @param int $userid
     * @return bool
     */
    public static function is_active_allocation(int $programid, int $userid): bool {
        $now = time();
        /** @var program_user[] $allocations **/
        $allocations = program_user::get_records(['programid' => $programid, 'userid' => $userid]);
        foreach ($allocations as $allocation) {
            $allocationstatus = (int) $allocation->get('status');
            if (constants::STATUS_OVERRIDE_SUSPENDED === $allocationstatus) {
                continue;
            }
            $certificationid = (int) $allocation->get('certificationid');
            if (0 !== $certificationid) {
                // If this allocation comes from certification and certification is archived, then is not an active allocation.
                $certification = new certification($certificationid);
                if ($certification->is_archived()) {
                    continue;
                }
            }
            $startdate = (int) $allocation->get('startdate');
            $endadate = (int) $allocation->get('enddate');
            $issetstartdate = 0 !== $startdate;
            $issetenddate = 0 !== $endadate;
            if ($issetstartdate && $issetenddate) {
                if ($now >= $startdate && $now <= $endadate) {
                    return true;
                }
            } else if (!$issetstartdate && $issetenddate) {
                if ($now <= $endadate) {
                    return true;
                }
            } else if ($issetstartdate && !$issetenddate) {
                if ($now >= $startdate) {
                    return true;
                }
            } else if (!$issetstartdate && !$issetenddate) {
                return true;
            }
        }

        return false;
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
        if ($activestatus === $newstatus && $activestatus !== $oldstatus) {
            // If just reactivated, trigger program progress recalculation for this user.
            self::calculate_user_program_progress($program, $programuser->get('userid'));
        }

        return true;
    }

    /**
     * Deletes all users from a program.
     *
     * @param program $program
     */
    private static function delete_program_users(program $program): void {
        $programusers = $program->get_program_users();
        foreach ($programusers as $programuser) {
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
     *
     * @param int $courseid
     * @param int $userid
     */
    public static function recalculate_program_progress_by_courseid_and_userid(int $courseid, int $userid): void {
        $programs = self::get_programs_by_courseid_and_userid($courseid, $userid);
        foreach ($programs as $program) {
            self::calculate_user_program_progress($program, $userid);
        }
    }

    /**
     * Recalculates program completion for all the users related to the given program.
     *
     * @param program $program
     */
    private static function recalculate_all_users_program_progress(program $program): void {
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
     * TODO SP-427: separate state calculations from exporter functionality?
     *
     * @param int $programid
     * @param int $userid
     * @param int $certificationid
     * @return array
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
            $statusstr = get_string('suspended', 'tool_program');
            $statuses[] = ['status' => 'program_user_status_suspended', 'statusstr' => $statusstr];
        }

        $iscompleted = !empty($programcompletion);
        if ($iscompleted) {
            $statusstr = get_string('completed', 'tool_program');
            $statuses[] = ['status' => 'program_user_status_completed', 'statusstr' => $statusstr];
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
                $statusstr = get_string('futureallocation', 'tool_program');
                $statuses[] = ['status' => 'program_user_status_futureallocation', 'statusstr' => $statusstr];
            } else if ($hasduedate && $now > $duedate) {
                // Overdue - Program not completed by the due date.
                $statusstr = get_string('overdue', 'tool_program');
                $statuses[] = ['status' => 'program_user_status_overdue', 'statusstr' => $statusstr];
            } else if ($isopen) {
                // Open - After start date and before due date.
                $statusstr = get_string('open', 'tool_program');
                $statuses[] = ['status' => 'program_user_status_open', 'statusstr' => $statusstr];
            }
        }

        return $statuses;
    }

    /**
     * Returns a list of accessible programs for the given user.
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
                ) AND p.tenantid = :tenantid';
        $programrecords = $DB->get_records_sql($sql,
            ['userid' => $userid, 'tenantid' => tenancy::get_tenant_id($userid)]);
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
     * Suspend program enrolment instances for the given program user.
     *
     * @param program_user $programuser
     * @return bool
     */
    private static function suspend_allocated_user_enrolments(program_user $programuser): bool {
        if (!$enrolplugin = enrol_get_plugin('program')) {
            return false;
        }

        $programid = (int) $programuser->get('programid');
        $userid = (int) $programuser->get('userid');
        $enrolinstances = self::get_program_enrol_instances_by_programid_and_userid($programid, $userid);
        foreach ($enrolinstances as $instance) {
            $enrolplugin->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
        }

        return true;
    }

    /**
     * Reactivate program enrolment instances for the given program user.
     *
     * @param program_user $programuser
     * @return bool
     */
    private static function reactivate_allocated_user_program_enrolments(program_user $programuser): bool {
        if (!$enrolplugin = enrol_get_plugin('program')) {
            return false;
        }

        $programid = (int) $programuser->get('programid');
        $userid = (int) $programuser->get('userid');
        $enrolinstances = self::get_program_enrol_instances_by_programid_and_userid($programid, $userid);
        foreach ($enrolinstances as $instance) {
            $enrolplugin->update_user_enrol($instance, $userid, ENROL_USER_ACTIVE);
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

        $sql = "SELECT DISTINCT e.id
                  FROM {enrol} e
            INNER JOIN {user_enrolments} ue
                    ON e.id = ue.enrolid
                 WHERE e.customint1 = :programid
                   AND e.courseid = :courseid
                   AND ue.userid = :userid
                   AND e.enrol = 'program' ";

        $enrolids = $DB->get_fieldset_sql($sql, ['programid' => $programid, 'courseid' => $courseid, 'userid' => $userid]);
        if (empty($enrolids)) {
            return false;
        }

        [$sql, $params] = $DB->get_in_or_equal($enrolids, SQL_PARAMS_NAMED, 'id');
        $enrolinstance = $DB->get_record_sql('SELECT e.* FROM {enrol} e WHERE id ' . $sql, $params);

        return $enrolinstance;
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
        global $DB;

        $programs = $DB->get_records('tool_program', [
            'tenantid' => tenancy::get_tenant_id($userid),
            'archived' => 0,
        ], 'fullname', 'id, fullname');

        $fieldset = [];
        foreach ($programs as $program) {
            $fieldset[$program->id] = format_string($program->fullname);
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
        $open = "({$pu}.startdate < {$now} OR {$pu}.startdate = 0) AND ({$pu}.duedate > {$now} OR {$pu}.duedate = 0)";
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
     * Get programs by status and userid.
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
                  AND {$p}.tenantid = :usertenantid
                  AND {$p}.archived = 0  ";
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

        $event->eventtype = 'tool_program' . $data->programdatetype;
        $event->type = CALENDAR_EVENT_TYPE_STANDARD;
        $event->description = $event->name;
        $event->courseid = 0;
        $event->groupid = 0;
        $event->userid = $data->userid;
        $event->modulename = '0';
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
     * @param stdClass $data
     */
    public static function update_calendar_event(stdClass $data): void {
        global $DB;

        $params = [
            'eventtype' => 'tool_program' . $data->programdatetype,
            'instance' => $data->programid,
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
     */
    public static function delete_calendar_events(stdClass $data): void {
        global $DB;

        $params = [
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
     * @throws coding_exception
     */
    public static function send_program_completed_notification(int $userid, int $programid): void {
        $provider = 'programcompleted';
        $program = new program($programid);
        $programname = format_string($program->get('fullname'), true,
            ['context' => context_system::instance(), 'escape' => false]);
        $subject = get_string('notificationsubjectprogramcompleted', 'tool_program', $programname);
        $fullmessage = get_string('notificationmsgprogramcompleted', 'tool_program', $programname);

        self::send_moodle_notification($userid, $provider, $subject, $fullmessage);
    }

    /**
     * Send allocated notification to the user involved.
     *
     * @param int $userid
     * @param int $programid
     */
    public static function send_program_user_allocated_notification(int $userid, int $programid): void {
        $provider = 'programuserallocated';
        $program = new program($programid);
        $programname = format_string($program->get('fullname'), true,
            ['context' => context_system::instance(), 'escape' => false]);
        $subject = get_string('notificationsubjectprogramuserallocated', 'tool_program', $programname);
        $fullmessage = get_string('notificationmsgprogramuserallocated', 'tool_program', $programname);

        self::send_moodle_notification($userid, $provider, $subject, $fullmessage);
    }

    /**
     * Send deallocated notification to the user involved.
     *
     * @param int $userid
     * @param int $programid
     */
    public static function send_program_user_deallocated_notification(int $userid, int $programid): void {
        $provider = 'programuserdeallocated';
        $program = new program($programid);
        $programname = format_string($program->get('fullname'), true,
            ['context' => context_system::instance(), 'escape' => false]);
        $subject = get_string('notificationsubjectprogramuserdeallocated', 'tool_program', $programname);
        $fullmessage = get_string('notificationmsgprogramuserdeallocated', 'tool_program', $programname);

        self::send_moodle_notification($userid, $provider, $subject, $fullmessage);
    }

    /**
     * Sends a moodle message of the notification type.
     *
     * @param int $userid
     * @param string $provider
     * @param string $subject
     * @param string $fullmessage
     */
    private static function send_moodle_notification(int $userid, string $provider, string $subject, string $fullmessage): void {
        $message = new message();
        $message->courseid = SITEID;
        $message->component = 'tool_program';
        $message->name = $provider;
        $message->notification = 1;
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = core_user::get_user($userid);
        $message->subject = $subject;
        $message->fullmessage = html_to_text($fullmessage);
        $message->fullmessagehtml = $fullmessage;
        $message->fullmessageformat = FORMAT_HTML;
        $message->smallmessage = '';

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
                $relativestr = get_string('afteruserallocationdate', 'tool_program');
                $startdaterelative = $program->get('startdaterelative');
                return $startdaterelative . ' ' . core_text::strtolower($relativestr);
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
                $relativestr = get_string('afterstartdate', 'tool_program');
                $duedaterelative = $program->get('duedaterelative');
                return $duedaterelative . ' ' . core_text::strtolower($relativestr);
                break;
            case constants::DATE_AFTER_USER_ALLOCATION:
                $relativestr = get_string('afteruserallocationdate', 'tool_program');
                $duedaterelative = $program->get('duedaterelative');
                return $duedaterelative . ' ' . core_text::strtolower($relativestr);
                break;
            case constants::DATE_BEFORE_END:
                $relativestr = get_string('beforeenddate', 'tool_program');
                $duedaterelative = $program->get('duedaterelative');
                return $duedaterelative . ' ' . core_text::strtolower($relativestr);
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
                $relativestr = get_string('afterstartdate', 'tool_program');
                $enddaterelative = $program->get('enddaterelative');
                return $enddaterelative . ' ' . core_text::strtolower($relativestr);
                break;
            case constants::DATE_AFTER_DUE:
                $relativestr = get_string('afterduedate', 'tool_program');
                $enddaterelative = $program->get('enddaterelative');
                return $enddaterelative . ' ' . core_text::strtolower($relativestr);
                break;
            case constants::DATE_AFTER_USER_ALLOCATION:
                $relativestr = get_string('afteruserallocationdate', 'tool_program');
                $enddaterelative = $program->get('enddaterelative');
                return $enddaterelative . ' ' . core_text::strtolower($relativestr);
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
    private static function calculate_user_program_progress(program $program, int $userid): program_tree_progress {
        return new program_tree_progress($program, $userid);
    }

    /**
     * Removes a deleted course from all programs. A course can be several times inside one program.
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

        // We need a case insensitive comparison on the value.
        $equal = $DB->sql_equal('idnumber', ':idnumber', false);
        $query = "SELECT *
                FROM {tool_program}
                WHERE $equal AND tenantid = :tenantid and archived = 0";
        $params = ['idnumber' => $idnumber, 'tenantid' => $tenantid];

        if ($record = $DB->get_record_sql($query, $params)) {
            return new program(0, $record);
        }
        return null;
    }
}
