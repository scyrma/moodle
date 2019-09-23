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
 * Class tool_certification/api
 *
 * @package   tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification;

use calendar_event;
use coding_exception;
use context_system;
use core\message\message;
use core_tag_tag;
use core_text;
use core_user;
use dml_exception;
use invalid_parameter_exception;
use moodle_exception;
use stdClass;
use tool_certification\event\user_allocation_updated;
use tool_certification\event\user_allocation_created;
use tool_certification\event\user_allocation_deleted;
use tool_certification\event\certification_created;
use tool_certification\event\certification_updated;
use tool_certification\event\certification_deleted;
use tool_certification\event\certification_completion_created;
use tool_program\persistent\program;
use tool_program\persistent\program_set_completion;
use tool_program\persistent\program_user;
use tool_tenant\tenancy;
use tool_tenant\tenant_group;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/lib.php');

/**
 * Class api
 *
 * @package tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /**
     * Creates a certification.
     *
     * @param stdClass $data
     * @return certification
     */
    public static function create_certification(stdClass $data): certification {
        global $USER;

        $context = context_system::instance();

        // Prevent passing extra data to the persistent.
        $insertdata = (object) array_intersect_key((array) $data, [
            'fullname' => 1,
            'idnumber' => 1,
            'program' => 1,
            'description' => 1,
            'shortname' => 1,
            'startdatetype' => 1,
            'startdateabsolute' => 1,
            'startdaterelative' => 1,
            'duedatetype' => 1,
            'duedateabsolute' => 1,
            'duedaterelative' => 1,
            'expirydatetype' => 1,
            'expirydateabsolute' => 1,
            'expirydaterelative' => 1,
            'allowdirectallocation' => 1,
            'visible' => 1,
            'archived' => 1,
            'timearchived' => 1,
            'autocreategroups' => \tool_program\api::GROUPS_AS_IN_PROGRAMS,
        ]);

        // Get current user tenant ID.
        $insertdata->tenantid = tenancy::get_tenant_id($USER->id);

        $insertdata->duedatetype = constants::DATE_AFTER_START_DATE;

        // Get original allocation dates.
        if (isset($data->duplicatecertification) && 0 !== (int) $data->duplicatecertification) {
            $originalcert = new certification($data->duplicatecertification);
            $insertdata->allocationstartdatetype = $originalcert->get('allocationstartdatetype');
            $insertdata->allocationstartdateabsolute = $originalcert->get('allocationstartdateabsolute');
            $insertdata->allocationenddatetype = $originalcert->get('allocationenddatetype');
            $insertdata->allocationenddateabsolute = $originalcert->get('allocationenddateabsolute');
        }

        $newcertification = new certification(0, $insertdata);
        $newcertification->create();

        $id = $newcertification->get('id');

        // Save certification tags.
        core_tag_tag::set_item_tags('tool_certification', 'tool_certification', $id, $context, $data->certification_tags);

        // Check if tool_dynamicrule is installed.
        if (class_exists('\\tool_dynamicrule\\rules_list')) {
            if (isset($data->duplicatecertification) && 0 !== (int) $data->duplicatecertification) {
                // If we are duplicating a certification duplicate dynamic rules.
                $originalcert = new certification($data->duplicatecertification);
                self::duplicate_certification_dynamicrules($originalcert->get('id'), $id);
            } else {
                // Create default dynamic rules for dynamic rules tab.
                self::add_default_dynamicrule_conditions_to_certification($id, $newcertification->get('tenantid'));
            }
        }

        // Trigger event.
        certification_created::create_from_certification_created($newcertification)->trigger();

        return $newcertification;
    }

    /**
     * Duplicate certification dynamic rules into another certification.
     *
     * @param int $certificationid Origin certification id
     * @param int $newcertificationid Destination certification id
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws coding_exception
     */
    private static function duplicate_certification_dynamicrules(int $certificationid, int $newcertificationid): void {
        global $DB;

        $params = [
            'component' => 'tool_certification',
            'componentarea' => 'certification',
            'itemid' => $certificationid
        ];
        $certrules = $DB->get_records('tool_dynamicrule', $params);

        if (!empty($certrules)) {
            foreach ($certrules as $rule) {
                $originalruleid = $rule->id;
                $rule->itemid = $newcertificationid;
                unset ($rule->id);
                $newrule = \tool_dynamicrule\api::create_rule((object) $rule);

                // Duplicate conditions.
                $certconditions = $DB->get_records('tool_dynamicrule_condition', ['ruleid' => $originalruleid]);
                if (!empty($certconditions)) {
                    foreach ($certconditions as $condition) {
                        $configdata = json_decode($condition->configdata, true);
                        $configdata['certificationid'] = $newcertificationid;
                        // No need to verify user tenancy, we are creating condition for rule that was just created.
                        \tool_dynamicrule\api::create_rule_condition($newrule->get('id'), $condition->classname,
                            $configdata, true);
                    }
                }

                // Duplicate outcomes.
                $certoutcomes = $DB->get_records('tool_dynamicrule_outcome', ['ruleid' => $originalruleid]);
                if (!empty($certoutcomes)) {
                    foreach ($certoutcomes as $outcome) {
                        $configdata = json_decode($outcome->configdata, true);
                        // No need to verify user tenancy, we are creating condition for rule that was just created.
                        \tool_dynamicrule\api::create_rule_outcome($newrule->get('id'), $outcome->classname, $configdata, true);
                    }
                }
            }
        }
    }

    /**
     * Adds default conditions to certification.
     *
     * @param int $certificationid
     * @param int $tenantid
     */
    public static function add_default_dynamicrule_conditions_to_certification(int $certificationid, int $tenantid): void {
        $component = 'tool_certification';
        $componentarea = 'certification';
        $configdata = ['certificationid' => $certificationid];
        $name = get_string('certificationrules', 'tool_certification');

        $conditions = [
            'user_allocated',
            'certification_overdue',
            'certification_certified',
            'certification_not_certified',
            'certification_expired',
            'certification_suspended',
        ];

        foreach ($conditions as $condition) {
            // Create rule.
            $ruleid = \tool_dynamicrule\api::create_rule_for_component($component, $componentarea,
                $certificationid, $tenantid, $name);
            $conditionclass = '\\tool_certification\\tool_dynamicrule\\condition\\' . $condition;
            // Create condition. No need to verify user tenancy,
            // we are creating condition for rule that was just created.
            \tool_dynamicrule\api::create_rule_condition($ruleid, $conditionclass, $configdata, true);
        }
    }

    /**
     * Updates Certification details.
     *
     * @param stdClass $data
     */
    public static function update_certification_details(stdClass $data): void {
        $context = context_system::instance();
        $certification = new certification($data->id);

        // Update certification tags.
        core_tag_tag::set_item_tags('tool_certification', 'tool_certification', $data->id, $context, $data->certification_tags);

        $certification->set('fullname', $data->fullname);
        $certification->set('idnumber', $data->idnumber);
        $certification->set('startdatetype', $data->startdatetype);
        $certification->set('startdaterelative', $data->startdaterelative);
        $certification->set('startdateabsolute', $data->startdateabsolute);
        $certification->set('duedatetype', constants::DATE_AFTER_START_DATE);
        $certification->set('duedaterelative', $data->duedaterelative);
        $certification->set('expirydatetype', $data->expirydatetype);
        $certification->set('expirydateabsolute', $data->expirydateabsolute);
        $certification->set('expirydaterelative', $data->expirydaterelative);
        $certification->set('autocreategroups', $data->autocreategroups);

        // Update dates for all users.
        self::recalculate_all_certification_users_dates($certification);

        $certification->update();

        // Trigger event.
        certification_updated::create_from_certification_updated($certification)->trigger();
    }

    /**
     * Allocates a user into a certification.
     *
     * @param certification $certification
     * @param stdClass $data
     * @return certification_user
     */
    public static function allocate_user(certification $certification, stdClass $data): certification_user {
        // Prevent passing extra data to the persistent.
        $insertdata = array_intersect_key((array) $data, [
            'userid' => 1,
            'startdate' => 1,
            'startdatelocked' => 1,
            'duedate' => 1,
            'duedatelocked' => 1,
            'enddate' => 1,
            'enddatelocked' => 1,
            'expirydate' => 1,
            'expirydatelocked' => 1,
            'status' => 1,
            'allocationtype' => 1,
        ]);
        $insertdata['certificationid'] = $certification->get('id');

        $certificationid = $certification->get('id');
        $programid = $certification->get('program');

        // We check if allocation in certification already exists.
        $params = ['userid' => $data->userid, 'certificationid' => $certificationid];
        if (!empty(certification_user::get_record($params))) {
            throw new moodle_exception('erroruseralreadyallocatedincertification', 'tool_certification');
        }

        // By default, the certification allocation has no end date (this should be inherited by the program allocation).
        $insertdata['enddate'] = 0;
        $insertdata['enddatelocked'] = 1;

        $issuspended = isset($insertdata['status']) && constants::STATUS_OVERRIDE_SUSPENDED === (int) $insertdata['status'];
        if ($issuspended) {
            $insertdata['timesuspended'] = time();
        }

        // We allocate user to this certification.
        $newuser = new certification_user(0, (object) $insertdata);
        $newuser->set_certification($certification);
        $newuser->create();

        // We check if allocation in program already exists with this user and certification.
        $params = ['userid' => $data->userid, 'certificationid' => $certificationid];
        if (!empty(program_user::get_record($params))) {
            throw new moodle_exception('erroruseralreadyallocatedinprogram', 'tool_certification');
        }

        // We allocate user into the program.
        $program = new program($programid);
        $programdata = (object) [
            'userid' => $newuser->get('userid'),
            'certificationid' => $certificationid,
            'allocationtype' => constants::ALLOCATION_CERTIFICATION,
            'startdate' => $newuser->get('startdate'),
            'enddate' => $newuser->get('enddate'),
            'duedate' => $newuser->get('duedate'),
            'status' => $data->status
        ];
        if ($issuspended) {
            $programdata->timesuspended = time();
        }

        $programuser = \tool_program\api::allocate_user($program, $programdata);
        if (!$programuser) {
            throw new moodle_exception('errorallocatinguserintorelatedprogram', 'tool_certification');
        }

        // Recalculate dates.
        self::recalculate_certification_user_dates($certification, $newuser);

        // Check if program is already completed.
        if (!$issuspended && self::is_program_completed($programid, $data->userid)) {
            self::set_user_as_certified($data->userid, $certificationid);
        }

        // Trigger event.
        user_allocation_created::create_from_user_allocation_created($newuser)->trigger();

        return $newuser;
    }

    /**
     * Recalculates all certification users dates.
     *
     * @param certification $certification
     */
    private static function recalculate_all_certification_users_dates(certification $certification): void {
        $certusers = $certification->get_certification_users();
        foreach ($certusers as $certuser) {
            self::recalculate_certification_user_dates($certification, $certuser);
        }
    }

    /**
     * Recalculates a user dates.
     *
     * @param certification $certification
     * @param certification_user $certificationuser
     * @return bool
     */
    public static function recalculate_certification_user_dates(certification $certification,
        certification_user $certificationuser): bool {
        global $DB;

        $userallocdate = (int) $certificationuser->get('timecreated');
        $userstartdate = self::recalculate_user_start_date($certification, $certificationuser, $userallocdate);
        $userduedate = self::recalculate_user_due_date($certification, $certificationuser, $userstartdate);
        $userenddate = 0; // End date from a certification is always "not set" and program allocation date must inherit this value.
        $userexpirydate = self::recalculate_user_expiry_date($certification, $certificationuser, $userallocdate, $userduedate);

        $certificationuser->set('startdate', $userstartdate);
        $certificationuser->set('duedate', $userduedate);
        $certificationuser->set('enddate', $userenddate);
        $certificationuser->set('expirydate', $userexpirydate);
        $certificationuser->update();

        $certificationid = $certificationuser->get('certificationid');
        $userid = $certificationuser->get('userid');
        $data = $DB->get_record(program_user::TABLE, ['certificationid' => $certificationid, 'userid' => $userid]);
        $programuser = new program_user($data->id);
        $programuser->set('startdate', $userstartdate);
        $programuser->set('duedate', $userduedate);
        $programuser->set('enddate', $userenddate);
        $programuser->update();

        // Create or update due date calendar event.
        $data = (object) [
            'userid' => $userid,
            'name' => format_string($certification->get('fullname')),
            'certificationid' => $certificationid,
            'timestart' => $userduedate,
            'certificationdatetype' => constants::CALENDAR_EVENT_DUE_DATE
        ];
        self::update_calendar_event($data);

        $data->certificationdatetype = constants::CALENDAR_EVENT_EXPIRY_DATE;
        $data->timestart = $userexpirydate;
        $data->name = format_string($certification->get('fullname'));
        self::update_calendar_event($data);

        return true;
    }

    /**
     * Archives a certification.
     *
     * @param int $certificationid
     * @return bool
     */
    public static function archive_certification(int $certificationid): bool {
        $certification = new certification($certificationid);
        $certification->set('archived', 1);
        $certification->set('timearchived', time());
        $certification->update();

        // Trigger event.
        certification_updated::create_from_certification_updated($certification)->trigger();

        return true;
    }

    /**
     * Restores a certification.
     *
     * @param int $certificationid
     * @return bool
     */
    public static function restore_certification(int $certificationid): bool {
        $certification = new certification($certificationid);

        $certification->set('archived', 0);
        $certification->set('timearchived', 0);
        // Check that idnumber is unique and is not present in another active certification in this tenant.
        if (!self::is_idnumber_unique((int)$certificationid, (string)$certification->get('idnumber'))) {
            $certification->set('idnumber', '');
        }
        $certification->update();

        // Trigger event.
        certification_updated::create_from_certification_updated($certification)->trigger();

        self::calculate_certification_completion_for_all_certification_users($certification);

        return true;
    }

    /**
     * Deletes a certification.
     *
     * @param certification $certification
     * @return bool
     */
    public static function delete_certification(certification $certification): bool {

        // Check that certification is archived.
        if (!$certification->get('archived')) {
            throw new moodle_exception('errorcantdeletenotarchivedcertification', 'tool_certification');
        }

        // TODO SP-410: Check how enrol works on certifications.

        // Deallocate users from certification and associated program.
        $certusers = $certification->get_certification_users();
        foreach ($certusers as $certuser) {
            self::deallocate_user($certification->get('id'), $certuser->get('userid'));
        }

        // Delete groups associations.
        tenant_group::delete_for_component('tool_certification', 'tool_certification', $certification->get('id'));

        // Create event.
        $event = certification_deleted::create_from_certification_deleted($certification);

        // Delete the certification.
        $certification->delete();

        // Trigger event.
        $event->trigger();

        return true;
    }

    /**
     * Deallocates user from a certification.
     *
     * @param int $certificationid
     * @param int $userid
     */
    public static function deallocate_user(int $certificationid, int $userid): void {
        global $DB;
        $certification = new certification($certificationid);

        // Deallocate from related program first.
        \tool_program\api::deallocate_user($certification->get('program'), $userid, $certificationid);

        // Deallocate from certification.
        $data = $DB->get_record(certification_user::TABLE, ['certificationid' => $certificationid, 'userid' => $userid]);
        $certificationuser = new certification_user($data->id);

        // Create event.
        $event = user_allocation_deleted::create_from_user_allocation_deleted($certificationuser);

        // Delete record.
        $certificationuser->delete();

        // TODO SP-410: handle enrol user instance.

        // Delete calendar events for this user and certification.
        $data = (object) [
            'userid' => $userid,
            'certificationid' => $certificationid,
        ];
        self::delete_calendar_events($data);

        // Trigger event.
        $event->trigger();
    }

    /**
     * Updates certification dates.
     *
     * Updates dates from the calendar in manager,
     *
     * @param stdClass $data
     * @return bool
     */
    public static function update_certification_calendar(stdClass $data): bool {
        $certification = new certification($data->id);
        $certification->set('allocationstartdatetype', $data->allocationstartdatetype);
        $certification->set('allocationstartdateabsolute', $data->allocationstartdateabsolute);
        $certification->set('allocationenddatetype', $data->allocationenddatetype);
        $certification->set('allocationenddateabsolute', $data->allocationenddateabsolute);

        $certification->update();

        // Trigger event.
        certification_updated::create_from_certification_updated($certification)->trigger();

        return true;
    }

    /**
     * Recalculates user start date.
     *
     * @param certification $certification
     * @param certification_user $certificationuser
     * @param int $userallocationdate
     * @return int
     */
    private static function recalculate_user_start_date(certification $certification,
        certification_user $certificationuser,
        int $userallocationdate): int {
        if ($certificationuser->get('startdate' . 'locked')) {
            return (int) $certificationuser->get('startdate');
        }

        switch ($certification->get('startdate' . 'type')) {
            case constants::DATE_NONE:
                $userstartdate = constants::DATE_NONE;
                break;
            case constants::DATE_USER_ALLOCATION_DATE:
                $userstartdate = $userallocationdate;
                break;
            case constants::DATE_ABSOLUTE:
                $userstartdate = (int) $certification->get('startdate' . 'absolute');
                break;
            case constants::DATE_RELATIVE_TO_ALLOCATION_DATE:
                $userstartdate = strtotime('+' . $certification->get('startdate' . 'relative'), $userallocationdate);
                break;
            default:
                throw new coding_exception('unexpected certification start date type');
                break;
        }

        return $userstartdate;
    }

    /**
     * Recalculates user due date.
     *
     * @param certification $certification
     * @param certification_user $certificationuser
     * @param int $userstartdate
     * @return int
     */
    private static function recalculate_user_due_date(certification $certification,
        certification_user $certificationuser,
        int $userstartdate): int {
        if ($certificationuser->get('duedate' . 'locked')) {
            return (int) $certificationuser->get('duedate');
        }
        if (constants::DATE_NONE === $userstartdate) {
            $userduedate = constants::DATE_NONE;
        } else {
            $userduedate = strtotime('+' . $certification->get('duedate' . 'relative'), $userstartdate);
        }

        return $userduedate;
    }

    /**
     * Recalculates user expiry date.
     *
     * @param certification $certification
     * @param certification_user $certificationuser
     * @param int $userallocationdate
     * @param int $userduedate
     * @return int
     */
    private static function recalculate_user_expiry_date(certification $certification,
        certification_user $certificationuser,
        int $userallocationdate,
        int $userduedate): int {

        if ($certificationuser->get('expirydate' . 'locked')) {
            return (int) $certificationuser->get('expirydate');
        }

        switch ($certification->get('expirydate' . 'type')) {
            case constants::DATE_NONE:
                $userexpirydate = constants::DATE_NONE;
                break;
            case constants::DATE_NEVER:
                // We must set to 0.
                $userexpirydate = constants::DATE_NONE;
                break;
            case constants::DATE_ABSOLUTE:
                $userexpirydate = (int) $certification->get('expirydate' . 'absolute');
                break;
            case constants::DATE_AFTER_COMPLETION:
                $programid = $certification->get('program');
                $userid = $certificationuser->get('userid');
                $program = new program($programid);
                $baseset = $program->get_base_set();
                $programcompletion = program_set_completion::get_record(['setid' => $baseset->get('id'), 'userid' => $userid]);

                $params = ['certificationid' => $certification->get('id'), 'userid' => $userid, 'timerevoked' => 0];
                $certcompletion = certification_completion::get_record($params);
                $userexpirydate = 0;
                if (!empty($programcompletion)) {
                    // If program is completed apply relative date to completion date.
                    $expirydaterelative = $certification->get('expirydate' . 'relative');
                    $userexpirydate = strtotime('+' . $expirydaterelative, $programcompletion->get('timecreated'));
                } else if (!empty($certcompletion)) {
                    // If user has been certified manually apply relative date to certified date.
                    $expirydaterelative = $certification->get('expirydate' . 'relative');
                    $userexpirydate = strtotime('+' . $expirydaterelative, $certcompletion->get('timecreated'));
                }
                break;
            case constants::DATE_AFTER_ALLOCATION_DATE:
                $userexpirydate = strtotime('+' . $certification->get('expirydate' . 'relative'), $userallocationdate);
                break;
            case constants::DATE_AFTER_DUE_DATE:
                if (constants::DATE_NONE === $userduedate) {
                    $userexpirydate = constants::DATE_NONE;
                } else {
                    $userexpirydate = strtotime('+' . $certification->get('expirydate' . 'relative'), $userduedate);
                }
                break;
            default:
                throw new coding_exception('unexpected certification expiry date type');
                break;
        }

        return $userexpirydate;
    }

    /**
     * Updates user dates and status.
     *
     * @param certification_user $certificationuser
     * @param stdClass $validateddata
     * @return bool
     */
    public static function update_certification_user_dates_and_status(certification_user $certificationuser,
        stdClass $validateddata): bool {
        global $DB;

        $now = time();
        $certificationid = $certificationuser->get('certificationid');
        $userid = $certificationuser->get('userid');
        $suspendedstatus = constants::STATUS_OVERRIDE_SUSPENDED;
        $activestatus = constants::STATUS_OVERRIDE_DEFAULT;
        $newstatus = (int) $validateddata->status;
        $oldstatus = (int) $certificationuser->get('status');
        $hasbeensuspended = $suspendedstatus === $newstatus && $suspendedstatus !== $oldstatus;
        $hasbeenactivated = $activestatus === $newstatus && $activestatus !== $oldstatus;

        // Timesuspended is only to be used in dynamic rules.
        if ($hasbeensuspended) {
            $certificationuser->set('timesuspended', $now);
        }

        $certificationuser->set('startdatelocked', $validateddata->startdatelocked);
        $certificationuser->set('startdate', $validateddata->startdate);
        $certificationuser->set('duedatelocked', $validateddata->duedatelocked);
        $certificationuser->set('duedate', $validateddata->duedate);
        $certificationuser->set('status', $validateddata->status);

        // We can modify expirydate if user is certified.
        $iscertified = self::is_user_certified($userid, $certificationid);
        if ($iscertified) {
            switch ((int) $validateddata->expirydatetype) {
                case constants::DATE_NONE:
                    $certification = $certificationuser->get_certification();
                    self::recalculate_certification_user_dates($certification, $certificationuser);
                    break;
                case constants::DATE_NEVER:
                    $certificationuser->set('expirydate', 0);
                    $certificationuser->set('expirydatelocked', constants::DATE_LOCKED);
                    break;
                case constants::DATE_ABSOLUTE:
                    $certificationuser->set('expirydate', $validateddata->expirydate);
                    $certificationuser->set('expirydatelocked', constants::DATE_LOCKED);
                    break;
                default:
                    throw new coding_exception('unexpected certification expiry date type');
                    break;
            }

            // Update expiry date on completion table.
            $params = ['userid' => $userid, 'certificationid' => $certificationid, 'timerevoked' => 0];
            $record = $DB->get_record(certification_completion::TABLE, $params);
            $certcomp = new certification_completion(0, $record);
            $certcomp->set('expirydate', $validateddata->expirydate);
            $certcomp->update();
        }

        $certificationuser->update();

        // Update program user dates too to be in sync with certification user dates.
        $data = $DB->get_record(program_user::TABLE, ['certificationid' => $certificationid, 'userid' => $userid]);
        $programuser = new program_user($data->id);
        $programuser->set('startdatelocked', $validateddata->startdatelocked);
        $programuser->set('startdate', $validateddata->startdate);
        $programuser->set('duedatelocked', $validateddata->duedatelocked);
        $programuser->set('duedate', $validateddata->duedate);
        $programuser->set('status', $validateddata->status);
        if ($hasbeensuspended) {
            $programuser->set('timesuspended', $now);
        }
        $programuser->update();

        // Recalculate dates for this user in case some where set from overriden to default.
        self::recalculate_certification_user_dates($certificationuser->get_certification(), $certificationuser);

        // Trigger event.
        user_allocation_updated::create_from_user_allocation_updated($certificationuser)->trigger();

        if ($hasbeenactivated) {
            self::calculate_certification_completion_for_certification_user($certificationuser);
        }

        return true;
    }

    /**
     * Returns user allocation status in array form with status value and string.
     *
     * @param int $certificationid
     * @param int $userid
     * @return array css class, string and associated constant
     */
    public static function get_user_allocation_status(int $certificationid, int $userid): array {
        $statuslist = [];

        $certuser = certification_user::get_record(['userid' => $userid, 'certificationid' => $certificationid]);
        $params = [
            'userid' => $userid,
            'certificationid' => $certificationid,
            'timerevoked' => 0
        ];
        $certcompletion = certification_completion::get_record($params);
        $now = time();

        // User is suspended.
        if (!$certcompletion && constants::STATUS_OVERRIDE_SUSPENDED === (int) $certuser->get('status')) {
            $statusstr = get_string('suspended', 'tool_certification');
            $statuslist[] = [
                'status'    => 'cert_user_status_suspended',
                'statusstr' => $statusstr,
                'statusint' => constants::STATUS_SUSPENDED
            ];
            return $statuslist;
        }

        // Expired - certified and certification expired.
        // Certified - Program completed and not yet expired.
        if ($certcompletion) {
            // We get expiry date from cert completion.
            $expirydate = $certcompletion->get('expirydate');
            if (0 !== (int) $expirydate && $expirydate < $now) {
                // Certified but expired.
                $statusstr = get_string('expired', 'tool_certification');
                $statuslist[] = [
                    'status'    => 'cert_user_status_expired',
                    'statusstr' => $statusstr,
                    'statusint' => constants::STATUS_EXPIRED
                ];
                return $statuslist;
            }
            if (constants::STATUS_OVERRIDE_SUSPENDED === (int) $certuser->get('status')) {
                // When user is certified and AFTER that the allocation is suspended:
                // We should display both statuses - "Certified" and "Suspended".
                $statusstr = get_string('suspended', 'tool_certification');
                $statuslist[] = [
                    'status'    => 'cert_user_status_suspended',
                    'statusstr' => $statusstr,
                    'statusint' => constants::STATUS_SUSPENDED
                ];
            }
            // Certified.
            $statusstr = get_string('certified', 'tool_certification');
            $statuslist[] = [
                'status'    => 'cert_user_status_certified',
                'statusstr' => $statusstr,
                'statusint' => constants::STATUS_CERTIFIED
            ];
            return $statuslist;
        }

        // Future allocation - Before start date.
        $startdate = $certuser->get('startdate');
        if ($startdate > $now) {
            $statusstr = get_string('futureallocation', 'tool_certification');
            $statuslist[] = [
                'status'    => 'cert_user_status_futureallocation',
                'statusstr' => $statusstr,
                'statusint' => constants::STATUS_FUTUREALLOCATION
            ];
            return $statuslist;
        }

        // Open - After start date and before due date.
        $duedate = $certuser->get('duedate');
        if ($startdate <= $now && $now <= $duedate) {
            $statusstr = get_string('open', 'tool_certification');
            $statuslist[] = [
                'status'    => 'cert_user_status_open',
                'statusstr' => $statusstr,
                'statusint' => constants::STATUS_OPEN
            ];
            return $statuslist;
        }

        // Overdue - Program not completed by the due date.
        if ($now > $duedate && !$certcompletion) {
            $statusstr = get_string('overdue', 'tool_certification');
            $statuslist[] = [
                'status'    => 'cert_user_status_overdue',
                'statusstr' => $statusstr,
                'statusint' => constants::STATUS_OVERDUE
            ];
            return $statuslist;
        }

        // If we get here we throw exception.
        throw new moodle_exception('errorevaluatinguserallocationstatus', 'tool_certification');
    }

    /**
     * Checks if allocate user window is available to allocate user.
     *
     * @param certification $certification
     * @return bool
     */
    public static function is_certification_allocation_open(certification $certification): bool {
        // We check allocate window from certifications.
        $now = time();

        $allocstartdatetype = (int) $certification->get('allocationstartdatetype');
        $allocenddatetype = (int) $certification->get('allocationenddatetype');
        $allocationstartdate = (int) $certification->get('allocationstartdateabsolute');
        $allocationenddate = (int) $certification->get('allocationenddateabsolute');

        if ($allocstartdatetype === constants::ALLOCATION_NOT_SET
            && $allocenddatetype === constants::ALLOCATION_NOT_SET) {
            // Dates are not set.
            return true;
        }

        if ($allocstartdatetype === constants::ALLOCATION_NOT_SET
            && $allocenddatetype === constants::ALLOCATION_SET
            && $now < $allocationenddate) {
            // Only end date is set.
            return true;
        }

        if ($allocstartdatetype === constants::ALLOCATION_SET
            && $allocenddatetype === constants::ALLOCATION_NOT_SET
            && $now > $allocationstartdate) {
            // Only end start is set.
            return true;
        }

        if ($allocstartdatetype === constants::ALLOCATION_SET
            && $allocenddatetype === constants::ALLOCATION_SET
            && $now > $allocationstartdate && $now < $allocationenddate) {
            // Both dates are set.
            return true;
        }

        return false;
    }

    /**
     * Sets user as certified.
     *
     * @param int $userid
     * @param int $certificationid
     * @param bool $suspendprogramallocation
     * @param int $expirydate Timestamp with the expirydate
     */
    public static function set_user_as_certified(int $userid, int $certificationid, bool $suspendprogramallocation = false,
        int $expirydate = null): void {

        // Avoid duplication of non revoked certification completions.
        $select = 'userid = ? AND certificationid = ? AND timerevoked = ? ';
        $params = ['userid' => $userid, 'certificationid' => $certificationid, 'timerevoked' => 0];
        if (certification_completion::record_exists_select($select, $params)) {
            return;
        }

        // If no value passed we get the default one calculated for the user.
        $params = ['userid' => $userid, 'certificationid' => $certificationid];
        if ($expirydate === null) {
            $certuser = certification_user::get_record($params);
            $expirydate = $certuser->get('expirydate');
        }

        $userdata = (object) [
            'userid' => $userid,
            'certificationid' => $certificationid,
            'expirydate' => $expirydate,
            'timerevoked' => 0
        ];
        $certcompletion = new certification_completion(0, $userdata);
        $certcompletion->create();

        // Trigger event.
        certification_completion_created::create_from_certification_completion_created($certcompletion)->trigger();

        // Suspend program allocation checkbox coming from certify modal.
        $certification = new certification($certificationid);
        $params = [
            'userid' => $userid,
            'programid' => $certification->get('program'),
            'certificationid' => $certificationid
        ];

        if ($suspendprogramallocation) {
            // We suspend program allocation for this user.
            $programuser = program_user::get_record($params);
            $programuser->set('status', constants::STATUS_OVERRIDE_SUSPENDED);
            $programuser->update();
        } else {
            // We clear the due date for this user and lock it.
            $programuser = program_user::get_record($params);
            $programuser->set('duedate', \tool_program\constants::DATE_NONE);
            $programuser->set('duedatelocked', \tool_program\constants::DATE_LOCKED);
            $programuser->update();

            $certificationuser = certification_user::get_record(['certificationid' => $certificationid, 'userid' => $userid]);
            $certificationuser->set('duedate', \tool_program\constants::DATE_NONE);
            $certificationuser->set('duedatelocked', \tool_program\constants::DATE_LOCKED);
            $certificationuser->update();
        }
    }

    /**
     * Checks if user is certified.
     *
     * @param int $userid
     * @param int $certificationid
     * @return bool
     */
    public static function is_user_certified(int $userid, int $certificationid): bool {
        global $DB;
        $params = ['userid' => $userid, 'certificationid' => $certificationid, 'timerevoked' => 0];
        return $DB->record_exists('tool_certification_compltion', $params);
    }

    /**
     * Revokes a certification for a user.
     *
     * @param int $userid
     * @param int $certificationid
     */
    public static function revoke_certification_from_user(int $userid, int $certificationid): void {
        $certification = new certification($certificationid);
        $programid = $certification->get('program');

        if (self::is_program_completed($programid, $userid)) {
            throw new coding_exception('Can not revoke certification from user if program is completed');
        }
        // We mark completion record as revoked (not certified anymore) from certifications.
        $params = ['userid' => $userid, 'certificationid' => $certificationid, 'timerevoked' => 0];
        $certcompletion = certification_completion::get_record($params);
        $certcompletion->set('timerevoked', time());
        $certcompletion->update();

        $params = ['programid' => $programid, 'userid' => $userid, 'certificationid' => $certificationid];
        /** @var program_user|false $programuser */
        $programuser = program_user::get_record($params);
        /** @var certification_user|false $certificationuser */
        $certificationuser = certification_user::get_record(['certificationid' => $certificationid, 'userid' => $userid]);

        // We unlock date and status on program user allocation and recalculate dates.
        $programuser->set('duedatelocked', 0);
        if (constants::STATUS_OVERRIDE_DEFAULT === (int) $certificationuser->get('status')) {
            $programuser->set('status', 1);
        }
        $programuser->update();

        $certificationuser->set('duedatelocked', constants::DATE_UNLOCKED);
        $certificationuser->update();

        self::recalculate_certification_user_dates($certification, $certificationuser);

        // Trigger event.
        user_allocation_updated::create_from_user_allocation_updated($certificationuser)->trigger();
    }

    /**
     * Check certification exists within the same tenant than the given user (defaults to current user if none provided).
     *
     * @param int $certificationid
     * @param int $userid
     * @return bool
     */
    public static function certification_exists_in_tenant(int $certificationid, int $userid = 0): bool {
        global $DB;
        return $DB->record_exists(certification::TABLE, [
            'id' => $certificationid,
            'tenantid' => tenancy::get_tenant_id($userid),
        ]);
    }

    /**
     * Get a mapped list of certification ids and names, sorted by certification name.
     *
     * @param int $archived
     * @param int $userid
     * @return array
     */
    public static function get_certifications_in_tenant_fieldset(int $archived = 0, int $userid = 0): array {
        global $DB;
        $certifications = $DB->get_records('tool_certification', [
            'tenantid' => tenancy::get_tenant_id($userid),
            'archived' => $archived,
        ], 'fullname', 'id, fullname');
        $fieldset = [];
        foreach ($certifications as $certification) {
            $fieldset[$certification->id] = format_string($certification->fullname);
        }
        return $fieldset;
    }

    /**
     * Get potential programs for the program selector.
     *
     * @uses \tool_dynamicrule\permission::can_manage_rules
     *
     * @param string $search
     * @return array
     * @throws dml_exception
     */
    public static function get_potential_programs(string $search): array {
        global $USER, $DB;

        if (!component_class_callback('\tool_dynamicrule\permission', 'can_manage_rules', [], false) &&
                !permission::has_edit_capability()) {
            return [];
        }

        $tenantuser = tenancy::get_tenant_id($USER->id);

        $query = "SELECT id, fullname
            FROM {tool_program}
            WHERE archived = 0 AND tenantid = :tenantid";

        $i = 0;
        $params = ['tenantid' => $tenantuser];

        foreach (preg_split('/ +/', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $i++;
            $query .= " AND (" .
                $DB->sql_like('fullname', ":search{$i}1", false, false)
                . ' OR ' .
                $DB->sql_like('idnumber', ":search{$i}2", false, false)
                . ')';
            $params += ["search{$i}1" => '%' . $word . '%', "search{$i}2" => '%' . $word . '%'];
        }

        $results = $DB->get_records_sql($query, $params);

        // We apply format string to the fullname.
        $formatparams = ['context' => context_system::instance(), 'escape' => false];
        foreach ($results as $result) {
            $result->fullname = format_string($result->fullname, true, $formatparams);
        }

        return $results;
    }

    /**
     * Get potential certifications for the certification selector.
     *
     * @uses \tool_dynamicrule\permission::can_manage_rules
     *
     * @param string $search
     * @return array
     * @throws dml_exception
     */
    public static function get_potential_certifications(string $search): array {
        global $USER, $DB;

        if (!component_class_callback('\tool_dynamicrule\permission', 'can_manage_rules', [], false) &&
                !permission::has_edit_capability()) {
            return [];
        }

        $tenantuser = tenancy::get_tenant_id($USER->id);

        $query = "SELECT id, fullname
            FROM {tool_certification}
            WHERE archived = 0 AND tenantid = :tenantid";

        $i = 0;
        $params = ['tenantid' => $tenantuser];

        foreach (preg_split('/ +/', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $i++;
            $query .= " AND (" .
                $DB->sql_like('fullname', ":search{$i}1", false, false)
                . ' OR ' .
                $DB->sql_like('idnumber', ":search{$i}2", false, false)
                . ')';
            $params += ["search{$i}1" => '%' . $word . '%', "search{$i}2" => '%' . $word . '%'];
        }

        $results = $DB->get_records_sql($query, $params);

        // We apply format string to the fullname.
        $formatparams = ['context' => context_system::instance(), 'escape' => false];
        foreach ($results as $result) {
            $result->fullname = format_string($result->fullname, true, $formatparams);
        }

        return $results;
    }

    /**
     * Add calendar event.
     *
     * @param stdClass $data
     * @throws dml_exception
     * @throws coding_exception
     */
    public static function add_calendar_event(stdClass $data): void {
        $event = new stdClass();

        switch ($data->certificationdatetype) {
            case constants::CALENDAR_EVENT_DUE_DATE:
                $event->name = get_string('calendarduedate', 'tool_certification', $data->name);
                break;
            case constants::CALENDAR_EVENT_EXPIRY_DATE:
                $event->name = get_string('calendarexpirydate', 'tool_certification', $data->name);
                break;
        }

        $event->eventtype = 'tool_certification' . $data->certificationdatetype;
        $event->type = CALENDAR_EVENT_TYPE_STANDARD;
        $event->description = $event->name;
        $event->courseid = 0;
        $event->groupid = 0;
        $event->userid = $data->userid;
        $event->modulename = '0';
        $event->instance = $data->certificationid;
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
     * @throws dml_exception
     * @throws coding_exception
     */
    public static function update_calendar_event(stdClass $data): void {
        global $DB;

        $params = [
            'eventtype' => 'tool_certification' . $data->certificationdatetype,
            'instance' => $data->certificationid,
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
     * Delete calendar events for a certification user.
     *
     * @param stdClass $data
     * @throws dml_exception
     */
    public static function delete_calendar_events(stdClass $data): void {
        global $DB;

        $params = [
            'eventtype' => 'tool_certification' . constants::CALENDAR_EVENT_DUE_DATE,
            'instance' => $data->certificationid,
            'userid' => $data->userid,
        ];

        if ($DB->record_exists('event', $params)) {
            $DB->delete_records('event', $params);
        }

        $params['eventtype'] = 'tool_certification' . constants::CALENDAR_EVENT_EXPIRY_DATE;

        if ($DB->record_exists('event', $params)) {
            $DB->delete_records('event', $params);
        }
    }

    /**
     * Get a list of statuses to be used in select fields.
     *
     * @return array
     */
    public static function get_certification_statuses_fieldset(): array {
        return [
            constants::STATUS_OVERRIDE_SUSPENDED => get_string('suspended', 'tool_certification'),
            constants::STATUS_FUTUREALLOCATION => get_string('futureallocation', 'tool_certification'),
            constants::STATUS_EXPIRED => get_string('expired', 'tool_certification'),
            constants::STATUS_CERTIFIED => get_string('certified', 'tool_certification'),
            constants::STATUS_OPEN => get_string('open', 'tool_certification'),
            constants::STATUS_OVERDUE => get_string('overdue', 'tool_certification'),
        ];
    }

    /**
     * Get sql join needed to recover user certification(s) statuses.
     *
     * @param string $u Table alias for table users
     * @param string $c Table alias for table certifications
     * @param string $cu Table alias for table certification users
     * @param string $cc Table alias for table certification user completions
     * @param string $pr Table alias for table programs
     * @param string $cid Name for named param certification id. Null if fetching all certification statuses (cert unspecified).
     * @return string
     */
    public static function get_status_sql_join(string $u = 'u', string $c = 'c', string $cu = 'cu', string $cc = 'cc',
        string $pr = 'pr', string $cid = null): string {

        $specificcertification = $cid === null ? '' : " AND {$cu}.certificationid = :{$cid}";

        return "
            INNER JOIN {" . certification_user::TABLE . "} {$cu}
                    ON {$cu}.userid = {$u}.id {$specificcertification}
            INNER JOIN {" . certification::TABLE . "} {$c}
                    ON {$c}.id = {$cu}.certificationid
            INNER JOIN {" . program::TABLE . "} {$pr}
                    ON {$pr}.id = {$c}.program
             LEFT JOIN {" . certification_completion::TABLE . "} {$cc}
                    ON {$cc}.certificationid = {$cu}.certificationid AND {$cc}.userid = {$u}.id AND {$cc}.timerevoked = 0
        ";
    }

    /**
     * Returns the sql status cases to be used within a sql query (WHERE CASE... THEN...) that retrieve user certification statuses.
     *
     * @param int $statusid status
     * @param string $cu Table alias for table certification users
     * @param string $cc Table alias for table certification user completions
     * @param bool $filter Check if is a filter or column. On columns can get multiple statuses like certified and suspended.
     * @return string
     */
    public static function get_status_sql_cases(int $statusid, string $cu = 'cu', string $cc = 'cc', $filter = false): string {

        $now = time();
        $statusoverridesuspendedvalue = constants::STATUS_OVERRIDE_SUSPENDED;
        $suspendedvalue = constants::STATUS_SUSPENDED;
        $futureallocationvalue = constants::STATUS_FUTUREALLOCATION;
        $expiredvalue = constants::STATUS_EXPIRED;
        $certifiedvalue = constants::STATUS_CERTIFIED;
        $openvalue = constants::STATUS_OPEN;
        $overduevalue = constants::STATUS_OVERDUE;
        $unknownvalue = constants::STATUS_UNKNOWN;
        $certifiedandsuspendedvalue = constants::STATUS_CERTIFIED_AND_SUSPENDED;
        $certified = "{$cc}.id IS NOT NULL AND {$cc}.timerevoked = 0";
        $notcertified = "({$cc}.id IS NULL OR {$cc}.timerevoked <> 0)";
        $suspended = "{$cu}.status = {$statusoverridesuspendedvalue}";
        $notsuspended = "{$cu}.status <> {$statusoverridesuspendedvalue}";
        $futureallocation = "{$cu}.startdate > 0 AND {$now} < {$cu}.startdate";
        $expired = "{$cc}.expirydate > 0 AND {$now} > {$cc}.expirydate";
        $open = "({$now} > {$cu}.startdate OR {$cu}.startdate = 0) AND ({$now} < {$cu}.duedate OR {$cu}.duedate = 0)";
        $overdue = "{$cu}.duedate > 0 AND {$now} > {$cu}.duedate";
        if ($filter) {
            $certifiedandsuspendedvalue = ($statusid === $suspendedvalue) ? $suspendedvalue : $certifiedvalue;
        }

        return "
            (CASE
                WHEN {$suspended} AND {$notcertified}
                THEN {$suspendedvalue}
                WHEN {$futureallocation} AND {$notcertified}
                THEN {$futureallocationvalue}
                WHEN {$certified} AND {$expired}
                THEN {$expiredvalue}
                WHEN {$certified} AND {$notsuspended}
                THEN {$certifiedvalue}
                WHEN {$certified} AND {$suspended}
                THEN {$certifiedandsuspendedvalue}
                WHEN {$open}
                THEN {$openvalue}
                WHEN {$overdue}
                THEN {$overduevalue}
                ELSE {$unknownvalue}
            END)
        ";
    }

    /**
     * Returns array with join, where, params to build an sql query to fetch related users with a given certification status.
     *
     * @param int $certificationid Given certification id
     * @param int $statusid Given status id
     * @param bool $isnegated Wether we are looking for certifications with the given status or without the given status
     * @param string $u Table alias for table users
     * @param string $c Table alias for table certification
     * @param string $cu Table alias for table certification users
     * @param string $cc Table alias for table certification completions
     * @param string $pr Table alias for table programs
     * @param string $cid Name for named param certification id
     * @return array
     */
    public static function get_certification_status_sql_query(int $certificationid, int $statusid, bool $isnegated = false,
        string $u = 'u', string $c = 'c', string $cu = 'cu', string $cc = 'cc', string $pr = 'pr',
        string $cid = 'certificationid'): array {

        $join = self::get_status_sql_join($u, $c, $cu, $cc, $pr, $cid);
        $statuscondition = self::get_status_sql_cases($statusid, $cu, $cc, true);
        $whereoperator = $isnegated ? '<>' : '=';
        $where = "{$statuscondition} {$whereoperator} $statusid ";
        $params = [$cid => $certificationid];

        return [$join, $where, $params];
    }

    /**
     * Send certification completed notification to user.
     *
     * @param int $userid
     * @param int $itemid
     * @throws coding_exception
     */
    public static function send_certification_completed_notification(int $userid, int $itemid): void {
        $provider = 'certificationcompleted';
        $certification = new certification($itemid);
        $certificationname = format_string($certification->get('fullname'), true,
            ['context' => context_system::instance(), 'escape' => false]);
        $subject = get_string('notificationsubjectcertificationcompleted', 'tool_certification', $certificationname);
        $fullmessage = get_string('notificationmsgcertificationcompleted', 'tool_certification', $certificationname);

        self::send_moodle_notification($userid, $provider, $subject, $fullmessage);
    }

    /**
     * Send allocated notification to the user involved.
     *
     * @param int $userid
     * @param int $itemid
     * @throws coding_exception
     */
    public static function send_certification_user_allocated_notification(int $userid, int $itemid): void {
        $provider = 'certificationuserallocated';
        $certification = new certification($itemid);
        $certificationname = format_string($certification->get('fullname'), true,
            ['context' => context_system::instance(), 'escape' => false]);
        $subject = get_string('notificationsubjectcertificationuserallocated', 'tool_certification', $certificationname);
        $fullmessage = get_string('notificationmsgcertificationuserallocated', 'tool_certification', $certificationname);

        self::send_moodle_notification($userid, $provider, $subject, $fullmessage);
    }

    /**
     * Send deallocated notification to the user involved.
     *
     * @param int $userid
     * @param int $itemid
     * @throws coding_exception
     */
    public static function send_certification_user_deallocated_notification(int $userid, int $itemid): void {
        $provider = 'certificationuserdeallocated';
        $certification = new certification($itemid);
        $certificationname = format_string($certification->get('fullname'), true,
            ['context' => context_system::instance(), 'escape' => false]);
        $subject = get_string('notificationsubjectcertificationuserdeallocated', 'tool_certification', $certificationname);
        $fullmessage = get_string('notificationmsgcertificationuserdeallocated', 'tool_certification', $certificationname);

        self::send_moodle_notification($userid, $provider, $subject, $fullmessage);
    }

    /**
     * Sends a moodle message of the notification type.
     *
     * @param int $userid
     * @param string $provider
     * @param string $subject
     * @param string $fullmessage
     * @throws coding_exception
     */
    private static function send_moodle_notification(int $userid, string $provider, string $subject, string $fullmessage): void {
        $message = new message();
        $message->courseid = SITEID;
        $message->component = 'tool_certification';
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
     * Get certifications by status and userid.
     *
     * @param int $status
     * @param int $userid
     * @return certification[]
     */
    public static function get_certifications_by_status_and_userid(int $status, int $userid): array {
        if (!array_key_exists($status, self::get_certification_statuses_fieldset())) {
            throw new coding_exception('Unexpected status passed.');
        }

        global $DB;
        $certifications = [];

        $u = 'u'; // Users table alias.
        $c = 'c'; // Certifications table alias.
        $cu = 'cu'; // Certification users table alias.
        $cc = 'cc'; // Certification completions table alias.
        $pr = 'pr'; // Programs table alias.
        $join = self::get_status_sql_join($u, $c, $cu, $cc, $pr);
        $statuscase = self::get_status_sql_cases($status, $cu, $cc, true);
        $usertenantid = tenancy::get_tenant_id($userid);

        $sql = "SELECT DISTINCT {$c}.id
                 FROM {user} {$u}
                      {$join}
                WHERE {$u}.id = :userid
                  AND {$statuscase} = $status
                  AND {$c}.tenantid = :usertenantid
                  AND {$c}.archived = 0  ";
        $params = ['userid' => $userid, 'usertenantid' => $usertenantid];
        $certids = $DB->get_fieldset_sql($sql, $params);

        if (!empty($certids)) {
            [$sql, $params] = $DB->get_in_or_equal($certids, SQL_PARAMS_NAMED, 'id');
            $certrecords = $DB->get_records_sql('SELECT * FROM {' . certification::TABLE . '} WHERE id ' . $sql, $params);
            foreach ($certrecords as $certrecord) {
                $certifications[$certrecord->id] = new certification(0, $certrecord);
            }
        }

        return $certifications;
    }

    /**
     * Returns the default expiry date for certification.
     *
     * @param certification $certification
     * @return string
     * @throws coding_exception
     */
    private static function get_default_certification_expirydate(certification $certification): string {
        switch ((int) $certification->get('expirydatetype')) {
            case constants::DATE_ABSOLUTE:
                $expirydateabsolute = $certification->get('expirydateabsolute');
                return userdate($expirydateabsolute, get_string('strftimedatefullshort'));
                break;
            case constants::DATE_NEVER:
                return get_string('never', 'tool_certification');
                break;
            case constants::DATE_AFTER_ALLOCATION_DATE:
                $afterallocdatestr = core_text::strtolower(get_string('afterallocationdate', 'tool_certification'));
                return $certification->get('expirydaterelative') . ' ' . $afterallocdatestr;
                break;
            case constants::DATE_AFTER_COMPLETION:
                $aftercompletionstr = core_text::strtolower(get_string('aftercompletion', 'tool_certification'));
                return $certification->get('expirydaterelative') . ' ' . $aftercompletionstr;
                break;
            case constants::DATE_AFTER_DUE_DATE:
                $afterduedatestr = core_text::strtolower(get_string('afterduedate', 'tool_certification'));
                return $certification->get('expirydaterelative') . ' ' . $afterduedatestr;
                break;
            default:
                throw new coding_exception('unexpected certification expiry date type');
                break;
        }
    }

    /**
     * Returns the default start date for certification.
     *
     * @param certification $certification
     * @return string
     * @throws coding_exception
     */
    private static function get_default_certification_startdate(certification $certification): string {
        switch ((int) $certification->get('startdatetype')) {
            case constants::DATE_USER_ALLOCATION_DATE:
                return get_string('allocationdate', 'tool_certification');
                break;
            case constants::DATE_ABSOLUTE:
                $startdateabsolute = $certification->get('startdateabsolute');
                return userdate($startdateabsolute, get_string('strftimedatefullshort'));
                break;
            case constants::DATE_RELATIVE_TO_ALLOCATION_DATE:
                $relativestr = get_string('relativeallocationdate', 'tool_certification');
                $startdaterelative = $certification->get('startdaterelative');
                return $startdaterelative . ' ' . core_text::strtolower($relativestr);
                break;
            default:
                throw new coding_exception('unexpected certification start date type');
                break;
        }
    }

    /**
     * Returns an object with the default dates for a given certification.
     *
     * @param certification $certification
     * @return stdClass
     * @throws coding_exception
     */
    public static function get_default_certification_dates(certification $certification): stdClass {
        $afterstartdate = core_text::strtolower(get_string('afterstartdate', 'tool_certification'));
        return (object) [
            'startdate' => self::get_default_certification_startdate($certification),
            'duedate' => $certification->get('duedaterelative') . ' ' . $afterstartdate,
            'expirydate' => self::get_default_certification_expirydate($certification),
        ];
    }

    /**
     * Checks if program is completed.
     *
     * @param int $programid
     * @param int $userid
     * @return bool
     * @throws coding_exception
     */
    public static function is_program_completed(int $programid, int $userid): bool {
        global $DB;
        $program = new program($programid);
        $baseset = $program->get_base_set();
        $params = [
            'setid' => $baseset->get('id'),
            'userid' => $userid,
        ];
        return $DB->record_exists(program_set_completion::TABLE, $params);
    }

    /**
     * Sets certifications as completed given a user and a program.
     * This method assumes the completion criteria for the certification has been already met
     * and just checks that it is actually possible to mark the certification as completed.
     * Called from program_completed event observer.
     *
     * @param int $userid
     * @param int $programid
     */
    public static function set_certification_completed_by_user_and_program(int $userid, int $programid): void {
        /** @var program_user[] $allocations */
        $allocations = program_user::get_records(['userid' => $userid, 'programid' => $programid]);
        // It is possible it can contain more than one certification for this user and program.
        if (!empty($allocations)) {
            foreach ($allocations as $allocation) {
                if (!$allocation->is_certification_allocation()) {
                    continue;
                }
                $certificationid = $allocation->get('certificationid');
                /** @var certification|false $certification */
                $certification = certification::get_record(['id' => $certificationid]);
                if (!$certification || $certification->is_archived()) {
                    continue;
                }
                $params = ['userid' => $userid, 'certificationid' => $certificationid];
                /** @var certification_user|false $certificationallocation */
                $certificationallocation = certification_user::get_record($params);
                if (!$certificationallocation || $certificationallocation->is_suspended()) {
                    continue;
                }
                self::set_user_as_certified($userid, $certificationid);
            }
        }
    }

    /**
     * Recalculates certification completion for all users of a given certification.
     *
     * @param certification $certification
     * @throws coding_exception
     */
    private static function calculate_certification_completion_for_all_certification_users(certification $certification): void {
        if ($certification->is_archived()) {
            return;
        }
        $certificationid = $certification->get('id');
        $programid = $certification->get('program');
        /** @var program_user[] $allocations */
        $allocations = certification_user::get_records(['certificationid' => $certificationid]);
        foreach ($allocations as $allocation) {
            if ($allocation->is_suspended()) {
                continue;
            }
            $userid = $allocation->get('userid');
            if (!self::is_program_completed($programid, $userid)) {
                continue;
            }
            self::set_user_as_certified($userid, $certificationid);
        }
    }

    /**
     * Calculate certification completion given the certification user.
     *
     * @param certification_user $certificationuser
     * @throws coding_exception
     */
    private static function calculate_certification_completion_for_certification_user(certification_user $certificationuser): void {
        if ($certificationuser->is_suspended()) {
            return;
        }
        $certification = $certificationuser->get_certification();
        if (!$certification || $certification->is_archived()) {
            return;
        }
        $programid = $certification->get('program');
        $userid = $certificationuser->get('userid');
        $certificationid = $certificationuser->get('certificationid');
        if (!self::is_program_completed($programid, $userid)) {
            return;
        }
        self::set_user_as_certified($userid, $certificationid);
    }

    /**
     * Returns a list of certifications where the user is allocated to.
     *
     * @param int $userid
     * @return certification[]
     */
    private static function get_certifications_by_userid(int $userid): array {
        global $DB;

        $certifications = [];
        $sql = 'SELECT c.*
                FROM {' . certification::TABLE . '} c
                WHERE id IN (
                    SELECT cu.certificationid
                    FROM {' . certification_user::TABLE . '} cu
                    WHERE cu.userid = :userid
                ) ';
        $certificationrecords = $DB->get_records_sql($sql, ['userid' => $userid]);
        foreach ($certificationrecords as $certificationrecord) {
            $certifications[$certificationrecord->id] = new certification(0, $certificationrecord);
        }

        return $certifications;
    }

    /**
     * Removes a deleted user from all certifications (and associated programs).
     * Used in the user_deleted observer.
     *
     * @param int $userid
     */
    public static function remove_deleted_user_from_certifications(int $userid): void {
        $certifications = self::get_certifications_by_userid($userid);
        if (!empty($certifications)) {
            foreach ($certifications as $certification) {
                self::deallocate_user($certification->get('id'), $userid);
            }
        }
    }

    /**
     * Checks if certification idnumber is unique within a tenant. We can have more than one idnumber empty.
     *
     * @param int $certificationid
     * @param string $idnumber
     * @return bool
     * @throws dml_exception
     */
    public static function is_idnumber_unique(int $certificationid, string $idnumber): bool {
        global $DB;

        if (!strlen($idnumber)) {
            return true;
        }

        // We need a case insensitive comparison on the value.
        $equal = $DB->sql_equal('idnumber', ':idnumber', false);
        $query = "SELECT COUNT(1)
                FROM {tool_certification}
                WHERE $equal AND tenantid = :tenantid and archived = 0 AND id <> :certificationid AND idnumber <> :emptystring";
        $params = [
            'idnumber' => $idnumber,
            'tenantid' => tenancy::get_tenant_id(),
            'certificationid' => $certificationid,
            'emptystring' => ''
        ];

        return !($DB->count_records_sql($query, $params) > 0);
    }

    /**
     * Returns certification record by idnumber
     *
     * @param string $idnumber
     * @param int $tenantid
     * @return certification
     * @throws \dml_exception
     */
    public static function get_certification_by_idnumber(string $idnumber, int $tenantid): ?certification {
        global $DB;

        // We need a case insensitive comparison on the value.
        $equal = $DB->sql_equal('idnumber', ':idnumber', false);
        $query = "SELECT *
                FROM {tool_certification}
                WHERE $equal AND tenantid = :tenantid and archived = 0";
        $params = ['idnumber' => $idnumber, 'tenantid' => $tenantid];
        if ($record = $DB->get_record_sql($query, $params)) {
            return new certification(0, $record);
        }
        return null;
    }
}