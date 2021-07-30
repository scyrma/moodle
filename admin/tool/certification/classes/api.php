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
 * Class tool_certification/api
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification;

use calendar_event;
use coding_exception;
use context_system;
use core\message\message;
use core_tag_tag;
use core_user;
use dml_exception;
use invalid_parameter_exception;
use moodle_exception;
use stdClass;
use tool_certification\customfield\certification_handler;
use tool_certification\event\deallocate_after_graceperiodends;
use tool_certification\event\recertification_started;
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
use tool_tenant\hierarchy;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use tool_tenant\tenant_group;
use tool_wp\course_reset_api;
use tool_wp\local\helpers\string_helper;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/lib.php');

/**
 * Class api
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
            'allocationstartdatetype' => 1,
            'allocationstartdateabsolute' => 1,
            'allocationenddatetype' => 1,
            'allocationenddateabsolute' => 1,
            'archived' => 1,
            'timearchived' => 1,
            'autocreategroups' => \tool_program\api::GROUPS_AS_IN_PROGRAMS,
            'requirerecertification' => 1,
            'recertdifferentprogram' => 1,
            'recertificationprogram' => 1,
            'recertstartdaterelative' => 1,
            'recertgraceperiod' => 1,
            'recertexpirydatetype' => 1,
            'recertexpirydaterelative' => 1,
            'shared' => 1,
        ]);

        if ((defined('BEHAT_SITE_RUNNING') || (defined('PHPUNIT_TEST') && PHPUNIT_TEST)) && !empty($data->tenantid)) {
            // In tests allow to override the current tenant ID.
            $insertdata->tenantid = $data->tenantid;
        } else {
            // Get current user tenant ID.
            $insertdata->tenantid = tenancy::get_tenant_id($USER->id);
        }

        // We default due date type to Date after start date.
        if (!isset($insertdata->duedatetype)) {
            $insertdata->duedatetype = constants::DATE_AFTER_START_DATE;
        }
        // We default relative due date to 3 months after start date.
        if (!isset($insertdata->duedaterelative)) {
            $insertdata->duedaterelative = '3 month';
        }
        if (!isset($insertdata->startdatetype)) {
            $insertdata->startdatetype = constants::DATE_USER_ALLOCATION_DATE;
        }
        if (!isset($insertdata->expirydatetype)) {
            $insertdata->expirydatetype = constants::DATE_NEVER;
        }

        // Set initial program as default for recertification program.
        if (empty($insertdata->recertificationprogram)) {
            $insertdata->recertificationprogram = $insertdata->program;
        }

        // Get original allocation dates.
        if (isset($data->duplicatecertification) && 0 !== (int) $data->duplicatecertification) {
            $originalcert = new certification($data->duplicatecertification);

            $insertdata->startdatetype = $originalcert->get('startdatetype');
            $insertdata->startdateabsolute = $originalcert->get('startdateabsolute');
            $insertdata->startdaterelative = $originalcert->get('startdaterelative');
            $insertdata->duedateabsolute = $originalcert->get('duedateabsolute');
            $insertdata->duedaterelative = $originalcert->get('duedaterelative');
            $insertdata->expirydatetype = $originalcert->get('expirydatetype');
            $insertdata->expirydateabsolute = $originalcert->get('expirydateabsolute');
            $insertdata->expirydaterelative = $originalcert->get('expirydaterelative');
            $insertdata->allocationstartdatetype = $originalcert->get('allocationstartdatetype');
            $insertdata->allocationstartdateabsolute = $originalcert->get('allocationstartdateabsolute');
            $insertdata->allocationenddatetype = $originalcert->get('allocationenddatetype');
            $insertdata->allocationenddateabsolute = $originalcert->get('allocationenddateabsolute');
            // Recertification.
            $insertdata->requirerecertification = $originalcert->get('requirerecertification');
            $insertdata->recertdifferentprogram = $originalcert->get('recertdifferentprogram');
            $insertdata->recertificationprogram = $originalcert->get('recertificationprogram');
            $insertdata->recertstartdaterelative = $originalcert->get('recertstartdaterelative');
            $insertdata->recertgraceperiod = $originalcert->get('recertgraceperiod');
            $insertdata->recertexpirydatetype = $originalcert->get('recertexpirydatetype');
            $insertdata->recertexpirydaterelative = $originalcert->get('recertexpirydaterelative');
        }

        $insertdata->shared = sharedspace::is_shared_space($insertdata->tenantid) ? 1 : 0; // Hardcoded for now.

        $newcertification = new certification(0, $insertdata);
        $newcertification->create();

        $id = $newcertification->get('id');

        // Save certification tags.
        core_tag_tag::set_item_tags('tool_certification', 'tool_certification', $id, $context, $data->certification_tags);

        // Save custom fields data.
        $customfieldsdata = fullclone($data);
        $customfieldsdata->id = $id;
        certification_handler::create()->instance_form_save($customfieldsdata, true);

        if (isset($data->duplicatecertification) && 0 !== (int) $data->duplicatecertification) {
            // If we are duplicating a certification duplicate dynamic rules.
            $originalcert = new certification($data->duplicatecertification);
            self::duplicate_certification_dynamicrules($originalcert->get('id'), $id);
        } else {
            // Create default dynamic rules for dynamic rules tab.
            self::add_default_dynamicrule_conditions_to_certification($id, $newcertification->get('tenantid'));
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

        // Check if tool_dynamicrule is installed.
        if (!class_exists('\\tool_dynamicrule\\rules_list')) {
            return;
        }

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
        // Check if tool_dynamicrule is installed.
        if (!class_exists('\\tool_dynamicrule\\rules_list')) {
            return;
        }
        $component = 'tool_certification';
        $componentarea = 'certification';
        $configdata = ['certificationid' => $certificationid];

        $conditions = self::get_available_dynamicrule_conditions();

        foreach ($conditions as $condition => $stringid) {
            // Create rule.
            $name = get_string($stringid, 'tool_certification');
            $ruleid = \tool_dynamicrule\api::create_rule_for_component($component, $componentarea,
                $certificationid, $tenantid, $name);
            $conditionclass = '\\tool_certification\\tool_dynamicrule\\condition\\' . $condition;
            // Create condition. No need to verify user tenancy,
            // we are creating condition for rule that was just created.
            \tool_dynamicrule\api::create_rule_condition($ruleid, $conditionclass, $configdata, true);
        }
    }

    /**
     * Returns list of all available certification conditions
     *
     * @return string[]
     */
    public static function get_available_dynamicrule_conditions(): array {
        return [
            'user_allocated' => 'conditionuserallocated',
            'certification_overdue' => 'conditioncertificationoverdue',
            'certification_certified' => 'conditioncertificationcertified',
            'certification_not_certified' => 'conditioncertificationnotcertified',
            'certification_expired' => 'conditioncertificationexpired',
            'certification_suspended' => 'conditioncertificationsuspended',
            'recertification_period_started' => 'conditionrecertificationstarted',
            'recertification_grace_period_ended' => 'conditionrecertificationgraceperiod'
        ];
    }

    /**
     * Updates Certification details.
     *
     * @param stdClass $data
     */
    public static function update_certification_details(stdClass $data): void {
        $context = context_system::instance();
        $certification = new certification($data->id);
        $oldrecord = $certification->to_record();

        // Update certification tags.
        core_tag_tag::set_item_tags('tool_certification', 'tool_certification', $data->id, $context, $data->certification_tags);

        $certification->set('fullname', $data->fullname);
        $certification->set('idnumber', $data->idnumber);

        $certification->update();

        // Save custom fields data.
        certification_handler::create()->instance_form_save($data, false);

        // Update certification name in all calendar events for this certification.
        self::update_certification_name_in_calendar_events($data->id, $data->fullname);

        // Trigger event.
        certification_updated::create_from_certification_updated($certification, $oldrecord)->trigger();
    }

    /**
     * Allocates a user into a certification.
     *
     * @param certification $certification
     * @param stdClass $data
     * @return certification_user
     */
    public static function allocate_user(certification $certification, stdClass $data): certification_user {
        $certificationid = $certification->get('id');
        $programid = $currentprogramid = $certification->get('program');
        $userid = (int)$data->userid;

        // We check if allocation in certification already exists.
        $params = ['userid' => $userid, 'certificationid' => $certificationid];
        if (!empty(certification_user::get_record($params))) {
            throw new moodle_exception('erroruseralreadyallocatedincertification', 'tool_certification');
        }
        // We check if allocation in program already exists.
        if (!empty(program_user::get_record($params))) {
            throw new moodle_exception('erroruseralreadyallocatedinprogram', 'tool_certification');
        }

        $lastcompletionrecord = self::get_last_completion_record($userid, $certificationid);
        if ($lastcompletionrecord) {
            $currentprogramid = $lastcompletionrecord->get('programid');
        }

        $issuspended = isset($data->status) && constants::STATUS_OVERRIDE_SUSPENDED === (int) $data->status;
        $timesuspended = $issuspended ? time() : 0;
        $startdate = $data->startdate ?? 0;
        $startdatelocked = $data->startdatelocked ?? 0;
        $duedate = $data->duedate ?? 0;
        $duedatelocked = $data->duedatelocked ?? 0;
        $allocationtype = $data->allocationtype ?? constants::ALLOCATION_MANUAL;

        // We allocate user to this certification.
        $userdata = (object) [
            'userid' => $userid,
            'certificationid' => $certificationid,
            'status' => $data->status,
            'timesuspended' => $timesuspended,
            'allocationtype' => $allocationtype,
            'currentprogramid' => $currentprogramid
        ];
        $newuser = new certification_user(0, $userdata);
        $newuser->set_certification($certification);
        $newuser->create();

        // We allocate user into the program with the dates.
        $program = new program($programid);
        $programdata = (object) [
            'userid' => $userid,
            'certificationid' => $certificationid,
            'allocationtype' => constants::ALLOCATION_CERTIFICATION,
            'startdate' => $startdate,
            'startdatelocked' => $startdatelocked,
            'duedate' => $duedate,
            'duedatelocked' => $duedatelocked,
            'enddate' => 0,
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

        if ($lastcompletionrecord) {
            // If a completion record already exists we need to set user as certified and set currentprogramid to null.
            // And calculate nextstartdate if recertification is active.
            $userallocdate = (int) $newuser->get('timecreated');
            $userduedate = (int) $programuser->get('duedate');
            $expirydate = self::recalculate_user_expiry_date($certification, $newuser, $userallocdate, $userduedate);
            $recertificationstartdate = self::recalculate_nextstartdate($certification, $expirydate);

            $newuser->set('currentprogramid', null);
            $newuser->set('nextstartdate', $recertificationstartdate);
            $newuser->update();

            // Delete Due date calendar event for this user and certification.
            $eventdata = (object) [
                'userid' => $userid,
                'certificationid' => $certificationid,
            ];
            self::delete_calendar_events($eventdata, constants::CALENDAR_EVENT_DUE_DATE);

        } else if (!$issuspended && self::is_program_completed($programid, $userid)) {
            // If program was already completed previously set user as certified.
            self::set_user_as_certified($userid, $certificationid);
        }

        self::send_certification_user_allocation_created_notification($newuser);

        // Trigger event.
        user_allocation_created::create_from_user_allocation_created($newuser)->trigger();

        return $newuser;
    }

    /**
     * Reallocates user into the initial certification program
     *
     * @param certification $certification
     * @param certification_user $certificationuser
     * @return program_user
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function reallocate_user_into_initial_program(certification $certification,
                                                                certification_user $certificationuser): program_user {
        $certificationid = $certification->get('id');
        $userid = $certificationuser->get('userid');

        // Deallocate user from all program user allocations and allocate into current initial one.
        global $DB;
        $params = ['userid' => $userid, 'certificationid' => $certificationid];
        $programallocations = $DB->get_records('tool_program_users', $params);
        foreach ($programallocations as $allocation) {
            \tool_program\api::deallocate_user($allocation->programid, $userid, $certificationid);
        }

        // We allocate user into the program.
        $programid = (int)$certification->get('program');
        $program = new program($programid);
        $programdata = (object) [
            'userid' => $userid,
            'certificationid' => $certificationid,
            'programid' => $programid,
            'allocationtype' => constants::ALLOCATION_CERTIFICATION,
            'startdate' => 0,
            'startdatelocked' => 0,
            'duedate' => 0,
            'duedatedatelocked' => 0,
            'enddate' => 0,
            'status' => $certificationuser->get('status')
        ];

        /** @var program_user $programuser */
        $programuser = \tool_program\api::allocate_user($program, $programdata);
        if (!$programuser) {
            throw new moodle_exception('errorallocatinguserintorelatedprogram', 'tool_certification');
        }

        $certificationuser->set('currentprogramid', $programid);
        $certificationuser->update();

        // Recalculate dates.
        self::recalculate_certification_user_dates($certification, $certificationuser);

        // Check if program is already completed.
        if (self::is_program_completed($certification->get('program'), $userid)) {
            self::set_user_as_certified($userid, $certificationid);
        }

        self::send_certification_user_allocation_created_notification($certificationuser);

        // Trigger event.
        user_allocation_created::create_from_user_allocation_created($certificationuser)->trigger();

        return $programuser;
    }

    /**
     * Allocate user into a recertification
     *
     * @param certification $certification
     * @param int $programid the new recertification program
     * @param int $userid
     * @param int $status
     * @return certification_user
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function allocate_user_recertification(certification $certification, int $programid, int $userid,
                                                         int $status): certification_user {
        $certificationid = $certification->get('id');

        // We check if allocation in certification already exists.
        $params = ['userid' => $userid, 'certificationid' => $certificationid];
        /** @var certification_user $certificationuser */
        $certificationuser = certification_user::get_record($params);

        $lastcompletion = self::get_last_completion_record($userid, $certificationid);
        if (!$lastcompletion) {
            throw new coding_exception('Previous completion should exist');
        }
        // User dates.
        $expirydate = $recertificationduedate = (int)$lastcompletion->get('expirydate');
        $recertificationstartdate = self::recalculate_nextstartdate($certificationuser->get_certification(), $expirydate);
        // Check if its a recertification and update grace period for this user.
        $usergraceperioddate = 0;
        if (self::is_recertification_program_different($certification, $certificationuser)) {
            $usergraceperioddate = self::recalculate_graceperiodends($certification, $expirydate);
        }

        $certificationuser->set('graceperiodends', $usergraceperioddate);
        $certificationuser->set('currentprogramid', $programid);
        $certificationuser->set('isrecertification', 1);
        $certificationuser->update();

        $initialprogram = (int)$certification->get('program');
        // Find LATEST programuser allocation.
        /** @var program_user $programuser */
        $programuser = self::get_latest_programuser_allocation($userid, $certificationid);

        // Check if program needs to change.
        if ($programid === (int)$programuser->get('programid')) {
            // It is the same program again. No need to realocate user.
            $programuser->set('startdate', $recertificationstartdate);
            $programuser->set('startdatelocked', 0);
            $programuser->set('duedate', $recertificationduedate);
            $programuser->set('duedatelocked', 0);
            $programuser->set('enddate', 0);
            $programuser->set('status', $status);
            $programuser->update();
        } else {
            // Program needs to change.

            // If current program is not initial program dealocate user.
            if ((int)$programuser->get('programid') !== $initialprogram) {
                \tool_program\api::deallocate_user($programuser->get('programid'), $userid, $certificationid);
            }

            // Check if new program is the same as initial program.
            if ($programid === $initialprogram) {
                // User is already alocated to initial program.
                $programuser->set('startdate', $recertificationstartdate);
                $programuser->set('startdatelocked', 0);
                $programuser->set('duedate', $recertificationduedate);
                $programuser->set('duedatelocked', 0);
                $programuser->set('enddate', 0);
                $programuser->set('status', $status);
                $programuser->update();

            } else {
                // We allocate user into the new program.
                $program = new program($programid);
                $programdata = (object) [
                    'userid' => $userid,
                    'certificationid' => $certificationid,
                    'programid' => $programid,
                    'allocationtype' => constants::ALLOCATION_CERTIFICATION,
                    'startdate' => $recertificationstartdate,
                    'startdatelocked' => 0,
                    'duedate' => $recertificationduedate,
                    'duedatedatelocked' => 0,
                    'enddate' => 0,
                    'status' => $status
                ];

                $programuser = \tool_program\api::allocate_user($program, $programdata);
                if (!$programuser) {
                    throw new moodle_exception('errorallocatinguserintorelatedprogram', 'tool_certification');
                }
            }
        }

        self::send_certification_user_allocation_created_notification($certificationuser);

        // Trigger event.
        user_allocation_created::create_from_user_allocation_created($certificationuser)->trigger();

        return $certificationuser;
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

        $certificationid = $certificationuser->get('certificationid');
        $userid = $certificationuser->get('userid');
        /** @var program_user $programuser */
        $programuser = program_user::get_record(['certificationid' => $certificationid, 'userid' => $userid]);

        $userallocdate = (int) $certificationuser->get('timecreated');
        $userstartdate = self::recalculate_user_start_date($certification, $certificationuser, $programuser, $userallocdate);
        $userduedate = self::recalculate_user_due_date($certification, $certificationuser, $programuser, $userstartdate);
        $userexpirydate = self::recalculate_user_expiry_date($certification, $certificationuser, $userallocdate, $userduedate);

        $programuser->set('startdate', $userstartdate);
        $programuser->set('duedate', $userduedate);
        $programuser->set('enddate', 0);
        $programuser->update();

        // If recertification was deactivated and it's activated we need to set nextstartdate for certified users.
        // That are not in a recertification period.
        $lastcompletion = self::get_last_completion_record($userid, $certificationuser->get('certificationid'));
        if ($lastcompletion && (int)$certification->get('requirerecertification') === 1
            && (int)$certificationuser->get('nextstartdate') === 0) {
            $nextstartdate = self::recalculate_nextstartdate($certification, (int)$lastcompletion->get('expirydate'));
            $certificationuser->set('nextstartdate', $nextstartdate);
            $certificationuser->update();
        }

        // If recertification is not required and user and current program is null.
        // Then grace period ends should be set to 0.
        if ((int)$certification->get('requirerecertification') === 0 && (int)$certificationuser->get('graceperiodends') > 0
            && !$certificationuser->get('currentprogramid')) {
            $certificationuser->set('graceperiodends', 0);
            $certificationuser->update();
        }

        // Create or update due date calendar event.
        $data = (object) [
            'userid' => $userid,
            'name' => format_string($certification->get('fullname')),
            'certificationid' => $certificationid,
            'timestart' => $userduedate,
            'certificationdatetype' => constants::CALENDAR_EVENT_DUE_DATE
        ];
        self::update_calendar_event($data);

        if (self::is_user_certified($userid, $certificationid)) {
            $data->certificationdatetype = constants::CALENDAR_EVENT_EXPIRY_DATE;
            $data->timestart = $userexpirydate;
            $data->name = format_string($certification->get('fullname'));
            self::update_calendar_event($data);
        }

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
        $oldrecord = $certification->to_record();
        $certification->set('archived', 1);
        $certification->set('timearchived', time());
        $certification->update();

        // Get all user allocations and suspend the course enrolments if there are no more actives.
        $programuserallocations = program_user::get_records(['certificationid' => $certificationid]);
        foreach ($programuserallocations as $programuser) {
            \tool_program\api::suspend_allocated_user_enrolments($programuser);
        }

        // Remove all users from certification course groups.
        self::remove_all_users_from_certification_course_groups($certification);

        // Hide all user calendar events for this certification.
        self::hide_certification_user_calendar_events($certification);

        // Trigger event.
        certification_updated::create_from_certification_updated($certification, $oldrecord)->trigger();

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
        $oldrecord = $certification->to_record();

        $certification->set('archived', 0);
        $certification->set('timearchived', 0);
        // Check that idnumber is unique and is not present in another active certification in this tenant.
        if (!self::is_idnumber_unique($certificationid, (string)$certification->get('idnumber'))) {
            $certification->set('idnumber', '');
        }
        $certification->update();

        // Get all active user allocations and activate the course enrolments if there is at least one active program allocation.
        $params = ['certificationid' => $certificationid, 'status' => constants::STATUS_OVERRIDE_DEFAULT];
        $programuserallocations = program_user::get_records($params);
        foreach ($programuserallocations as $programuser) {
            \tool_program\api::reactivate_allocated_user_program_enrolments($programuser);
            // We add user back to certification course groups.
            \tool_program\api::restore_user_in_program_course_groups($programuser);
        }

        // Restore all certification users membership in tenant course groups that
        // have component reference to this certification.
        self::restore_all_users_in_certification_course_groups($certification);

        // Show all user calendar events for this certification.
        self::show_certification_user_calendar_events($certification);

        // Trigger event.
        certification_updated::create_from_certification_updated($certification, $oldrecord)->trigger();

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

        // Deallocate users from certification and associated program.
        $certusers = $certification->get_certification_users();
        foreach ($certusers as $certuser) {
                self::deallocate_user($certification->get('id'), $certuser->get('userid'));
        }

        $certcompletions = $certification->get_certification_completions();
        foreach ($certcompletions as $certcompletion) {
            $certcompletion->delete();
        }

        // Remove all certification course groups.
        self::delete_all_certification_course_tenant_groups($certification);

        // Delete groups associations.
        tenant_group::delete_for_component('tool_certification', 'tool_certification', $certification->get('id'));

        // Delete dynamic rules associated to this certification.
        self::delete_certification_dynamic_rules($certification->get('id'));

        // Create event.
        $event = certification_deleted::create_from_certification_deleted($certification);

        // Delete certification custom fields.
        $handler = \tool_certification\customfield\certification_handler::create();
        $handler->delete_instance($certification->get('id'));

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

        $params = ['certificationid' => $certificationid, 'userid' => $userid];
        $certificationuser = certification_user::get_record($params);

        // Deallocate from related program first.
        $records = $DB->get_records(program_user::TABLE, $params, 'programid');
        if (!$records) {
            throw new coding_exception('Program user allocation not found');
        }

        // Remove all program allocations related to this certification user.
        foreach ($records as $record) {
            \tool_program\api::deallocate_user($record->programid, $userid, $certificationid);
        }

        // Create event.
        $event = user_allocation_deleted::create_from_user_allocation_deleted($certificationuser);

        // Delete record.
        $certificationuser->delete();

        // Remove user from certification course groups.
        self::remove_user_from_certification_course_groups($certificationuser);

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
     * Updates certification dates and initial program id (Certification tab).
     *
     * @param stdClass $data
     * @return bool
     */
    public static function update_certification_calendar(stdClass $data): bool {
        $certification = new certification($data->id);
        $oldrecord = $certification->to_record();
        $certification->set('startdatetype', $data->startdatetype);
        $certification->set('startdaterelative', $data->startdaterelative);
        $certification->set('startdateabsolute', $data->startdateabsolute);
        $certification->set('duedatetype', $data->duedatetype);
        $certification->set('duedaterelative', $data->duedaterelative);
        $certification->set('expirydatetype', $data->expirydatetype);
        $certification->set('expirydateabsolute', $data->expirydateabsolute);
        $certification->set('expirydaterelative', $data->expirydaterelative);
        $certification->set('allocationstartdatetype', $data->allocationstartdatetype);
        $certification->set('allocationstartdateabsolute', $data->allocationstartdateabsolute);
        $certification->set('allocationenddatetype', $data->allocationenddatetype);
        $certification->set('allocationenddateabsolute', $data->allocationenddateabsolute);
        // Save Program id and autocreategroups setting.
        $certification->set('program', $data->program);
        $certification->set('autocreategroups', $data->autocreategroups);

        $certification->update();

        // Update dates for all users.
        self::recalculate_all_certification_users_dates($certification);

        // Trigger event.
        certification_updated::create_from_certification_updated($certification, $oldrecord)->trigger();

        return true;
    }

    /**
     * Recalculates user start date.
     *
     * @param certification $certification
     * @param certification_user $certificationuser
     * @param program_user $programuser
     * @param int $userallocationdate
     * @return int
     */
    private static function recalculate_user_start_date(certification $certification,
        certification_user $certificationuser,
        program_user $programuser,
        int $userallocationdate): int {

        // If start date for this user is locked just leave it as it is.
        if ($programuser->get('startdatelocked')) {
            return (int) $programuser->get('startdate');
        }

        // We cannot change startdate after it is in the past.
        if ((int)$programuser->get('startdate') > 0 && time() > (int)$programuser->get('startdate')) {
            return (int) $programuser->get('startdate');
        }

        // Check if its a recertification.
        if ((bool) $certificationuser->get('isrecertification')) {
            // We calculate new startdate for recertification.
            $userid = $certificationuser->get('userid');
            $lastcompletion = self::get_last_completion_record($userid, $certificationuser->get('certificationid'));
            $expirydate = (int)$lastcompletion->get('expirydate');
            return self::recalculate_nextstartdate($certification, $expirydate);
        }

        switch ($certification->get('startdatetype')) {
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
     * @param program_user $programuser
     * @param int $userstartdate
     * @return int
     */
    private static function recalculate_user_due_date(certification $certification,
        certification_user $certificationuser,
        program_user $programuser,
        int $userstartdate): int {

        // If due date for this user is locked just leave it as it is.
        if ($programuser->get('duedatelocked')) {
            return (int) $programuser->get('duedate');
        }

        // Check if its a recertification.
        if ((bool) $certificationuser->get('isrecertification')) {
            return self::recalculate_recertification_user_due_date($certificationuser);
        }

        if (constants::DATE_NONE === $userstartdate || (int)$certification->get('duedatetype') === constants::DATE_NEVER) {
            $userduedate = constants::DATE_NONE;
        } else {
            // Due date is set to be relative After start date.
            $userduedate = strtotime('+' . $certification->get('duedaterelative'), $userstartdate);
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
    public static function recalculate_user_expiry_date(certification $certification,
        certification_user $certificationuser,
        int $userallocationdate,
        int $userduedate): int {

        $userid = $certificationuser->get('userid');

        // Check if its a recertification.
        if ((bool) $certificationuser->get('isrecertification')) {
            return self::recalculate_recertification_user_expiry_date($certification, $userid);
        }

        switch ($certification->get('expirydatetype')) {
            case constants::DATE_NONE:
                $userexpirydate = constants::DATE_NONE;
                break;
            case constants::DATE_NEVER:
                // We must set to 0.
                $userexpirydate = constants::DATE_NONE;
                break;
            case constants::DATE_ABSOLUTE:
                $userexpirydate = (int) $certification->get('expirydateabsolute');
                break;
            case constants::DATE_AFTER_COMPLETION:
                $certcompletion = self::get_last_completion_record($userid, $certification->get('id'));
                if (!$certcompletion) {
                    $expirydaterelative = $certification->get('expirydaterelative');
                    $userexpirydate = strtotime('+' . $expirydaterelative);
                    return $userexpirydate;
                }

                $program = new program($certcompletion->get('programid'));
                $baseset = $program->get_base_set();
                $programcompletion = program_set_completion::get_record(['setid' => $baseset->get('id'), 'userid' => $userid]);
                $userexpirydate = 0;
                if (!empty($programcompletion)) {
                    // If program is completed apply relative date to completion date.
                    $expirydaterelative = $certification->get('expirydaterelative');
                    $userexpirydate = strtotime('+' . $expirydaterelative, $programcompletion->get('timecreated'));
                } else if (!empty($certcompletion)) {
                    // If user has been certified manually apply relative date to certified date.
                    $expirydaterelative = $certification->get('expirydaterelative');
                    $userexpirydate = strtotime('+' . $expirydaterelative, $certcompletion->get('timecertified'));
                }
                break;
            case constants::DATE_AFTER_ALLOCATION_DATE:
                $userexpirydate = strtotime('+' . $certification->get('expirydaterelative'), $userallocationdate);
                break;
            case constants::DATE_AFTER_DUE_DATE:
                if (constants::DATE_NONE === $userduedate) {
                    $userexpirydate = constants::DATE_NONE;
                } else {
                    $userexpirydate = strtotime('+' . $certification->get('expirydaterelative'), $userduedate);
                }
                break;
            default:
                throw new coding_exception('unexpected certification expiry date type');
                break;
        }

        return $userexpirydate;
    }

    /**
     * Recalculates user expiry date on a recertification.
     *
     * @param certification $certification
     * @param int $userid
     * @return false|int
     * @throws coding_exception
     */
    private static function recalculate_recertification_user_expiry_date(certification $certification,
                                                                         int $userid) {
        $certcompletion = self::get_last_completion_record($userid, $certification->get('id'));
        if (!$certcompletion) {
            throw new coding_exception('completion should exist for this allocation');
        }

        switch ((int) $certification->get('recertexpirydatetype')) {
            case constants::RECERT_EXPIRY_DATE_NEVER_DATE:
                return 0;
                break;
            case constants::RECERT_EXPIRY_DATE_AFTR_PREV_COMPL:
                return strtotime('+' . $certification->get('recertexpirydaterelative'), (int)$certcompletion->get('timecertified'));
                break;
            case constants::RECERT_EXPIRY_DATE_AFTR_PREV_EXP:
                return strtotime('+' . $certification->get('recertexpirydaterelative'), (int)$certcompletion->get('expirydate'));
                break;
            case constants::RECERT_EXPIRY_DATE_AFTR_LATEST:
                $latest = max([(int)$certcompletion->get('timecertified'), (int)$certcompletion->get('expirydate')]);
                return strtotime('+' . $certification->get('recertexpirydaterelative'), $latest);
                break;
            default:
                throw new coding_exception('unexpected recertification expiry date type');
                break;
        }
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
        $certificationuser->set('status', $validateddata->status);

        $iscertified = (bool)self::get_last_completion_record($userid, $certificationid);
        $currentprogramid = (int)$certificationuser->get('currentprogramid');

        /** @var program_user $programuser */
        $programuser = program_user::get_record(['certificationid' => $certificationid, 'userid' => $userid]);
        $programvalidateddata = $programuser->to_record();
        $programvalidateddata->status = $newstatus;
        \tool_program\api::update_program_user_dates_and_status($programuser, $programvalidateddata);

        if (!$iscertified) {
            // User is not certified. We just update status, startdate and duedate.
            // Update program user dates for this certification allocation.
            if (isset($validateddata->startdatelocked)) {
                $programuser->set('startdatelocked', $validateddata->startdatelocked);
            }
            if (isset($validateddata->duedatelocked)) {
                $programuser->set('duedatelocked', $validateddata->duedatelocked);
            }
            if (isset($validateddata->startdate)) {
                $programuser->set('startdate', $validateddata->startdate);
            }
            if (isset($validateddata->duedate)) {
                $programuser->set('duedate', $validateddata->duedate);
            }

        } else if ($iscertified && !$currentprogramid) {
            // User is certified and has not started a recertification.

            // Check if expiry date has changed. If it has changed duplicate completion record.
            // Get last valid completion record.
            $lastcompletion = self::get_last_completion_record($userid, $certificationid);
            if ((int)$validateddata->expirydate !== (int)$lastcompletion->get('expirydate')) {
                self::duplicate_and_revoke_last_completion($validateddata, $lastcompletion);
            }

            // Check if graceperiodtype is default to recalculate.
            $certification = $certificationuser->get_certification();
            if ((int)$validateddata->graceperiodendstype === constants::DATE_NONE) {
                $expirydate = (int)$validateddata->expirydate;
                $validateddata->graceperiodends = self::recalculate_graceperiodends($certification, $expirydate);
            }
            if ($certification->get('requirerecertification')) {
                $certificationuser->set('nextstartdate', $validateddata->startdate);
            } else {
                $certificationuser->set('nextstartdate', 0);
            }
            $certificationuser->set('graceperiodendslocked', $validateddata->graceperiodendstype);
            $certificationuser->set('graceperiodends', $validateddata->graceperiodends);
        } else {
            // User is certified and started already a recertification.

            // Check if expiry date has changed. If it has changed duplicate completion record.
            // Get last valid completion record.
            $lastcompletion = self::get_last_completion_record($userid, $certificationid);
            if ((int)$validateddata->expirydate !== (int)$lastcompletion->get('expirydate')) {
                self::duplicate_and_revoke_last_completion($validateddata, $lastcompletion);
            }

            // Check if graceperiodtype is default to recalculate.
            if ((int)$validateddata->graceperiodendstype === constants::DATE_NONE) {
                $certification = $certificationuser->get_certification();
                $expirydate = (int)$validateddata->expirydate;
                $validateddata->graceperiodends = self::recalculate_graceperiodends($certification, $expirydate);
            }

            // User is certified and started already a recertification.
            $certificationuser->set('graceperiodendslocked', $validateddata->graceperiodendstype);
            $certificationuser->set('graceperiodends', $validateddata->graceperiodends);

            // Change current program for this specific user.
            if ($currentprogramid !== (int)$validateddata->currentprogram) {

                $programuser->set('programid', (int)$validateddata->currentprogram);
                $certificationuser->set('currentprogramid', (int)$validateddata->currentprogram);

                // Reset the courses that are part of the new program(P1) that were not part of the old program(P2).
                if (isset($validateddata->resetprogramcourses)) {
                    $programinitial = new program($currentprogramid);
                    $programrecertification = new program((int)$validateddata->currentprogram);
                    self::reset_courses_in_program1_not_present_in_program2($programinitial, $programrecertification, $userid);
                }
            }
        }

        $certificationuser->update();
        $programuser->update();

        $activestatus = constants::STATUS_OVERRIDE_DEFAULT;
        if ($activestatus === $newstatus && $activestatus !== $oldstatus) {
            // If just reactivated, reactivate program course enrolments for this user.
            \tool_program\api::reactivate_allocated_user_program_enrolments($programuser);
            // Restore this user from this certification course groups.
            self::restore_user_in_certification_course_groups($certificationuser);
        }

        if ($suspendedstatus === $newstatus && $suspendedstatus !== $oldstatus) {
            // Remove user from certification course groups.
            self::remove_user_from_certification_course_groups($certificationuser);

            // If just suspended, suspend program course enrolments for this user and remove from course groups.
            \tool_program\api::suspend_allocated_user_enrolments($programuser);
        }

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
     * Creates a new completion duplicating the previous completion and revokes this previous one.
     *
     * @param stdClass $validateddata
     * @param certification_completion $lastcompletion
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     */
    private static function duplicate_and_revoke_last_completion(stdClass $validateddata,
                                                                 certification_completion $lastcompletion): void {
        global $USER;
        $record = $lastcompletion->to_record();
        unset($record->id);
        $record->expirydate = $validateddata->expirydate;
        $record->certifiedby = $USER->id;
        // We create a new record duplicating the last one every time we update completion record.
        $newcompletion = new certification_completion(0, $record);
        $newcompletion->create();
        // And we revoked last previous valid completion record.
        $lastcompletion->revoke_completion();
    }

    /**
     * Returns user allocation status in array form with status value and string.
     *
     * @param int $certificationid
     * @param int $userid
     * @return array css class, string, associated constant and stringid for mobile app WS
     */
    public static function get_user_allocation_status(int $certificationid, int $userid): array {
        $statuslist = [];

        $params = ['userid' => $userid, 'certificationid' => $certificationid];
        $certuser = certification_user::get_record($params);
        $now = time();
        $lastcompletion = self::get_last_completion_record($userid, $certificationid);
        $iscertified = ($lastcompletion &&
            ((int)$lastcompletion->get('expirydate') === 0 || (int)$lastcompletion->get('expirydate') > $now));
        $isexpired = ($lastcompletion &&
            (int)$lastcompletion->get('expirydate') > 0 && (int)$lastcompletion->get('expirydate') < $now);

        // User is suspended.
        if (!$iscertified && constants::STATUS_OVERRIDE_SUSPENDED === (int) $certuser->get('status')) {
            $statusstr = get_string('suspended', 'tool_certification');
            $statuslist[] = [
                'status'    => 'cert_user_status_suspended',
                'statusstr' => $statusstr,
                'statusint' => constants::STATUS_SUSPENDED,
                'stringid' => 'suspended',
            ];
            return $statuslist;
        }

        // Expired - certified and certification expired.
        if ($isexpired) {
            // Certified but expired.
            $statusstr = get_string('expired', 'tool_certification');
            $statuslist[] = [
                'status'    => 'cert_user_status_expired',
                'statusstr' => $statusstr,
                'statusint' => constants::STATUS_EXPIRED,
                'stringid' => 'expired',
            ];
            return $statuslist;
        }

        // Certified - Program completed and not yet expired.
        if ($iscertified) {
            if (constants::STATUS_OVERRIDE_SUSPENDED === (int) $certuser->get('status')) {
                // When user is certified and AFTER that the allocation is suspended:
                // We should display both statuses - "Certified" and "Suspended".
                $statusstr = get_string('suspended', 'tool_certification');
                $statuslist[] = [
                    'status'    => 'cert_user_status_suspended',
                    'statusstr' => $statusstr,
                    'statusint' => constants::STATUS_SUSPENDED,
                    'stringid' => 'suspended',
                ];
            }
            // Certified.
            $statusstr = get_string('certified', 'tool_certification');
            $statuslist[] = [
                'status'    => 'cert_user_status_certified',
                'statusstr' => $statusstr,
                'statusint' => constants::STATUS_CERTIFIED,
                'stringid' => 'certified',
            ];
            return $statuslist;
        }

        $programuser = program_user::get_record($params);
        // Future allocation - Before start date.
        $startdate = $programuser->get('startdate');
        if ($startdate > $now) {
            $statusstr = get_string('futureallocation', 'tool_certification');
            $statuslist[] = [
                'status'    => 'cert_user_status_futureallocation',
                'statusstr' => $statusstr,
                'statusint' => constants::STATUS_FUTUREALLOCATION,
                'stringid' => 'futureallocation',
            ];
            return $statuslist;
        }

        // Open - After start date and before due date.
        $duedate = (int)$programuser->get('duedate');
        if (($startdate <= $now && $now <= $duedate) || $duedate === 0) {
            $statusstr = get_string('open', 'tool_certification');
            $statuslist[] = [
                'status'    => 'cert_user_status_open',
                'statusstr' => $statusstr,
                'statusint' => constants::STATUS_OPEN,
                'stringid' => 'open',
            ];
            return $statuslist;
        }

        // Overdue - Program not completed by the due date.
        if ($now > $duedate && !$iscertified) {
            $statusstr = get_string('overdue', 'tool_certification');
            $statuslist[] = [
                'status'    => 'cert_user_status_overdue',
                'statusstr' => $statusstr,
                'statusint' => constants::STATUS_OVERDUE,
                'stringid' => 'overdue',
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
     * @param int $expirydate Timestamp with the expirydate
     * @param int $timecertified Timestamp with the date user was certified
     * @param int $certifiedby userid for manual certification
     */
    public static function set_user_as_certified(int $userid, int $certificationid, int $expirydate = null, int $timecertified = 0,
        int $certifiedby = null): void {
        // Check if there is a previous active completion. Set is last to zero in case it exists.
        $certificationcompletion = self::get_last_completion_record($userid, $certificationid);
        $certification = new certification($certificationid);
        $now = time();
        $params = ['userid' => $userid, 'certificationid' => $certificationid];
        /** @var certification_user $certificationuser */
        $certificationuser = certification_user::get_record($params);

        $currentprogramid = $certificationuser->get('currentprogramid');
        if (!$currentprogramid) {
            throw new coding_exception('current program id should not be null');
        }

        // If there is no expiry date set or expirydate is set in the past we calculate default expirydate.
        if ($expirydate === null || ($expirydate > 0 && $now > $expirydate)) {
            // Find CURRENT programuser allocation.
            /** @var program_user $programuser */
            $programuser = self::get_latest_programuser_allocation($userid, $certificationid);

            $userallocdate = (int) $certificationuser->get('timecreated');
            $userduedate = (int) $programuser->get('duedate');
            $expirydate = self::recalculate_user_expiry_date($certification, $certificationuser, $userallocdate, $userduedate);
        }

        // If timecertified is set make sure is not set in the future.
        if ($timecertified === 0) {
            $timecertified = $now;
        } else if ($timecertified > $now) {
            $timecertified = $now;
        }

        // We calculate next start date for recertification.
        $recertificationstartdate = 0;
        if ((int)$certification->get('requirerecertification') === 1) {
            $recertificationstartdate = self::recalculate_nextstartdate($certification, $expirydate);
        }

        // If recertification program is different calculate graceperiodends.
        $graceperiodends = 0;
        if (self::is_recertification_program_different($certification, $certificationuser)) {
            $graceperiodends = self::recalculate_graceperiodends($certification, $expirydate);
        }

        $certificationuser->set('currentprogramid', null);
        $certificationuser->set('graceperiodends', $graceperiodends);
        $certificationuser->set('nextstartdate', $recertificationstartdate);
        $certificationuser->update();

        // We update previous completion record.
        if ($certificationcompletion) {
            $certificationcompletion->set('islast', 0);
            $certificationcompletion->update();
        }

        // We create the new completion record.
        $userdata = (object) [
            'userid' => $userid,
            'certificationid' => $certificationid,
            'expirydate' => $expirydate,
            'timerevoked' => 0,
            'revokedby' => null,
            'programid' => $currentprogramid,
            'islast' => 1,
            'timecertified' => $timecertified,
            'certifiedby' => $certifiedby,
        ];
        $certcompletion = new certification_completion(0, $userdata);
        $certcompletion->create();

        // Trigger event.
        certification_completion_created::create_from_certification_completion_created($certcompletion)->trigger();

        // Update calendar event.
        $data = new stdClass();
        $data->certificationid = $certificationid;
        $data->userid = $userid;
        $data->certificationdatetype = constants::CALENDAR_EVENT_EXPIRY_DATE;
        $data->timestart = $expirydate;
        $data->name = format_string($certification->get('fullname'));
        self::update_calendar_event($data);

        // Delete Due date calendar event for this user and certification.
        $eventdata = (object) [
            'userid' => $userid,
            'certificationid' => $certificationid,
        ];
        self::delete_calendar_events($eventdata, constants::CALENDAR_EVENT_DUE_DATE);
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

        $sql = '
                SELECT 1 FROM {tool_certification_users} tcu
                JOIN {tool_certification_compltion} tcc
                ON tcu.userid = tcc.userid AND tcu.certificationid = tcc.certificationid AND tcc.timerevoked = 0 AND tcc.islast = 1
                WHERE tcu.userid = :userid AND tcu.certificationid = :certificationid
                AND (tcc.expirydate = 0 OR tcc.expirydate > :now)
        ';
        $params = ['userid' => $userid, 'certificationid' => $certificationid, 'now' => time()];
        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Checks if the certification for this user has expired.
     *
     * @param int $userid
     * @param int $certificationid
     * @return bool
     */
    public static function is_user_certification_expired(int $userid, int $certificationid): bool {
        global $DB;

        $sql = '
                SELECT 1 FROM {tool_certification_users} tcu
                JOIN {tool_certification_compltion} tcc
                ON tcu.userid = tcc.userid AND tcu.certificationid = tcc.certificationid AND tcc.timerevoked = 0 AND tcc.islast = 1
                WHERE tcu.userid = :userid AND tcu.certificationid = :certificationid
                AND tcc.expirydate > 0 AND tcc.expirydate < :now
        ';
        $params = ['userid' => $userid, 'certificationid' => $certificationid, 'now' => time()];
        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Revokes a certification for a user.
     *
     * @param int $userid
     * @param int $certificationid
     */
    public static function revoke_certification_from_user(int $userid, int $certificationid): void {
        $certification = new certification($certificationid);
        $initialprogramid = (int)$certification->get('program');

        $currentcertcompletion = self::get_last_completion_record($userid, $certificationid);
        if (!$currentcertcompletion) {
            return;
        }
        $programid = (int)$currentcertcompletion->get('programid');

        if (self::is_program_completed($programid, $userid)) {
            throw new coding_exception('Can not revoke certification from user if program is completed');
        }

        /** @var certification_user|false $certificationuser */
        $certificationuser = certification_user::get_record(['certificationid' => $certificationid, 'userid' => $userid]);

        // We revoke this completion record.
        $currentcertcompletion->revoke_completion();

        // We find the previous last valid completion record in the tool_certification_compltion table.
        $lastcompletionrecord = self::get_previous_valid_completion_record($userid, $certificationid);

        // Check if there is a previous completion record, it might be the initial completion.
        if ($lastcompletionrecord) {
            // We do not reuse previous record. Instead we revoke it and create a new one using same data so we can keep a log.
            $previouscompletion = new certification_completion(0, $lastcompletionrecord);
            $previouscompletion->revoke_completion();

            unset($lastcompletionrecord->id);
            $lastcompletionrecord->islast = 1;
            $lastcompletionrecord->timerevoked = 0;
            $lastcompletionrecord->revokedby = null;
            $newcompletion = new certification_completion(0, $lastcompletionrecord);
            $newcompletion->create();

            // If currently 'currentprogramid' is null user is not in a recertification round. We need to calculate nextstartdate.
            if (!$certificationuser->get('currentprogramid')) {
                $nextstartdate = self::recalculate_nextstartdate($certification, $lastcompletionrecord->expirydate);
                $certificationuser->set('nextstartdate', $nextstartdate);
            }
            $graceperiod = self::recalculate_graceperiodends($certification, $lastcompletionrecord->expirydate);

            // We set currentprogramid to null and we wait for cron to pass on nextstartdate to allocate user into recertification.
            // In case the certification requires recertification.
            $certificationuser->set('graceperiodends', $graceperiod);
            $certificationuser->set('currentprogramid', null);
            $certificationuser->set('isrecertification', 1);
            $certificationuser->update();

        } else {
            $certificationuser->set('isrecertification', 0);
            $certificationuser->set('currentprogramid', $initialprogramid);
            $certificationuser->update();

            // There is no previous completion record. Set allocation to initial program.
            self::reallocate_user_into_initial_program($certification, $certificationuser);
        }
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

        $params = ['isshared' => 1];
        [$tenantsql, $tenantparams] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid', 'shared=:isshared');
        $query = 'SELECT id, fullname
            FROM {tool_certification}
            WHERE archived = 0 AND '.$tenantsql;

        $i = 0;
        $params += $tenantparams;

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

        $event->component = 'tool_certification';
        $event->eventtype = 'tool_certification' . $data->certificationdatetype;
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->description = $event->name;
        $event->courseid = 0;
        $event->categoryid = 0;
        $event->groupid = 0;
        $event->userid = $data->userid;
        $event->modulename = '';
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
     * Parameter $data must contain: userid, certificationid, name, timestart and certificationdatetype.
     * Values for certificationdatetype are: constants::CALENDAR_EVENT_DUE_DATE and constants::CALENDAR_EVENT_EXPIRY_DATE.
     *
     * @param stdClass $data
     * @throws dml_exception
     * @throws coding_exception
     */
    public static function update_calendar_event(stdClass $data): void {
        global $DB;

        $params = [
            'component' => 'tool_certification',
            'eventtype' => 'tool_certification' . $data->certificationdatetype,
            'instance' => $data->certificationid,
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
     * Delete calendar events for a certification user.
     *
     * @param stdClass $data
     * @param int|null $eventtype Remove only a specific event type (eg. On certification completion remove due date event).
     * @throws dml_exception
     */
    public static function delete_calendar_events(stdClass $data, int $eventtype = null): void {
        global $DB;

        // Remove only a specific event type.
        if ($eventtype) {
            $params = [
                'component' => 'tool_certification',
                'eventtype' => 'tool_certification' . $eventtype,
                'instance' => $data->certificationid,
                'userid' => $data->userid,
            ];

            $DB->delete_records('event', $params);

            return;
        }

        $params = [
            'component' => 'tool_certification',
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
     * @param string $pu Table alias for table program users
     * @param string $cid Name for named param certification id. Null if fetching all certification statuses (cert unspecified).
     * @return string
     */
    public static function get_status_sql_join(string $u = 'u', string $c = 'c', string $cu = 'cu', string $cc = 'cc',
        string $pr = 'pr', string $pu = 'pu', string $cid = null): string {

        $specificcertification = $cid === null ? '' : " AND {$cu}.certificationid = :{$cid}";

        return "
            INNER JOIN {" . certification_user::TABLE . "} {$cu}
                    ON {$cu}.userid = {$u}.id {$specificcertification}
            INNER JOIN {" . certification::TABLE . "} {$c}
                    ON {$c}.id = {$cu}.certificationid
            INNER JOIN {" . program::TABLE . "} {$pr}
                    ON {$pr}.id = {$c}.program
            INNER JOIN {tool_program_users} {$pu}
                    ON {$pu}.userid = {$cu}.userid AND {$pu}.certificationid = {$cu}.certificationid
             LEFT JOIN {" . certification_completion::TABLE . "} {$cc}
                    ON {$cc}.certificationid = {$cu}.certificationid AND {$cc}.userid = {$u}.id
                    AND {$cc}.timerevoked = 0 AND {$cc}.islast = 1
        ";
    }

    /**
     * Returns the sql status cases to be used within a sql query (WHERE CASE... THEN...) that retrieve user certification statuses.
     *
     * @param int $statusid status
     * @param string $cu Table alias for table certification users
     * @param string $cc Table alias for table certification user completions
     * @param string $pu Table alias for table program users
     * @param bool $filter Check if is a filter or column. On columns can get multiple statuses like certified and suspended.
     * @return string
     */
    public static function get_status_sql_cases(int $statusid, string $cu = 'cu', string $cc = 'cc',
                                                string $pu = 'pu', $filter = false): string {

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
        $certified = "{$cc}.id IS NOT NULL AND {$cc}.timerevoked = 0 AND {$cc}.islast = 1";
        $notcertified = "({$cc}.id IS NULL OR {$cc}.timerevoked <> 0 OR {$cc}.islast <> 1)";
        $suspended = "{$cu}.status = {$statusoverridesuspendedvalue}";
        $notsuspended = "{$cu}.status <> {$statusoverridesuspendedvalue}";
        $futureallocation = "{$pu}.startdate > 0 AND {$now} < {$pu}.startdate";
        $expired = "{$cc}.expirydate > 0 AND {$now} > {$cc}.expirydate";
        $open = "({$now} >= {$pu}.startdate OR {$pu}.startdate = 0) AND ({$now} < {$pu}.duedate OR {$pu}.duedate = 0)";
        $overdue = "{$pu}.duedate > 0 AND {$now} > {$pu}.duedate";
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
     * @param string $pu Table alias for table program users
     * @return array
     */
    public static function get_certification_status_sql_query(int $certificationid, int $statusid, bool $isnegated = false,
        string $u = 'u', string $c = 'c', string $cu = 'cu', string $cc = 'cc', string $pr = 'pr',
        string $cid = 'certificationid', string $pu = 'pu'): array {

        $join = self::get_status_sql_join($u, $c, $cu, $cc, $pr, $pu, $cid);
        $statuscondition = self::get_status_sql_cases($statusid, $cu, $cc, $pu, true);
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
     * @param int $programid
     * @throws coding_exception
     */
    public static function send_certification_completed_notification(int $userid, int $itemid, int $programid = 0): void {
        global $SITE;
        $user = core_user::get_user($userid);
        $provider = 'certificationcompleted';
        $certification = new certification($itemid);
        $certificationname = $certification->get_formatted_name();
        $program = new program($programid);
        $programname = $program->get_formatted_name();
        $contextid = \context_system::instance()->id;

        $lastcompletion = self::get_last_completion_record($userid, $itemid);
        $expirydate = $lastcompletion->get('expirydate');
        $expirymessage = '';
        if ($expirydate) {
            $expirydatestr = userdate($expirydate, get_string_manager()->get_string('strftimedatefullshort', 'langconfig',
                null, $user->lang));
            $certificationuser = certification_user::get_record(['certificationid' => $itemid, 'userid' => $userid]);

            $nextstartdate = $certificationuser->get('nextstartdate');
            $reopendate = userdate($nextstartdate, get_string_manager()->get_string('strftimedatefullshort', 'langconfig',
                null, $user->lang));

            if ($certification->get('requirerecertification')) {
                $recertificationprogram = new program($certification->get('recertificationprogram'));
                $recertificationprogramname = $recertificationprogram->get_formatted_name();

                $a = [
                    'expirydate' => $expirydatestr,
                    'reopendate' => $reopendate,
                    'recertificationprogram' => $recertificationprogramname,
                ];
                $expirymessage = get_string_manager()->get_string('notificationcertificationcompletedexpiry', 'tool_certification',
                    $a, $user->lang);
            } else {
                $expirymessage = get_string_manager()->get_string('notificationcertificationwillexpireon', 'tool_certification',
                    $expirydatestr, $user->lang);
            }
        }

        $a = [
            'userfullname' => fullname($user),
            'programname' => $programname,
            'certificationname' => $certificationname,
            'expirymessage' => $expirymessage,
            'sitename' => format_string($SITE->fullname, true, ['context' => $contextid, 'escape' => false]),
            'siteurl' => (new \moodle_url('/'))->out(false),
        ];

        // Check if completed as a result of program completion or as a result of manual certification.
        if (self::is_program_completed($programid, $userid)) {
            $subject = get_string_manager()->get_string('notificationsubjectcertificationcompleted', 'tool_certification',
                $programname, $user->lang);
            $fullmessage = get_string_manager()->get_string('notificationcertificationcompletedprogram', 'tool_certification',
                $a, $user->lang);
        } else {
            $subject = get_string_manager()->get_string('notificationsubjectcertificationcompletedmanual', 'tool_certification',
                $certificationname, $user->lang);
            $fullmessage = get_string_manager()->get_string('notificationcertificationcompletedmanual', 'tool_certification',
                $a, $user->lang);
        }

        self::send_moodle_notification($user, $provider, $subject, $fullmessage, $program);
    }

    /**
     * Send allocated notification to the user involved.
     *
     * @deprecated since 3.11 - please use api::send_certification_user_allocation_created_notification() instead.
     *
     * @param int $userid
     * @param int $itemid
     * @param int|null $programid
     */
    public static function send_certification_user_allocated_notification(int $userid, int $itemid, ?int $programid = 0): void {
        debugging('This function is deprecated, please use ' .
            'api::send_certification_user_allocation_created_notification() instead', DEBUG_DEVELOPER);
        $certificationuser = certification_user::get_record(['certificationid' => $itemid, 'userid' => $userid]);
        self::send_certification_user_allocation_created_notification($certificationuser);
    }

    /**
     * Send allocated notification to the user involved.
     *
     * @param certification_user $certificationuser
     */
    public static function send_certification_user_allocation_created_notification(certification_user $certificationuser): void {
        global $SITE;
        $user = core_user::get_user($certificationuser->get('userid'));
        $provider = 'certificationuserallocated';
        $certificationname = ($certificationuser->get_certification())->get_formatted_name();
        $contextid = \context_system::instance()->id;

        // If programid is not set, user might be certified and between recertification rounds.
        if ($certificationuser->get('currentprogramid')) {
            $program = new program($certificationuser->get('currentprogramid'));
            $programname = $program->get_formatted_name();

            $programuser = program_user::get_record([
                'certificationid' => $certificationuser->get('certificationid'),
                'userid' => $certificationuser->get('userid'),
                'programid' => $certificationuser->get('currentprogramid'),
            ]);

            $userduedate = self::get_certification_user_dates($certificationuser)->userduedate;
            if ($userduedate !== 0 && !$programuser->get('duedatelocked')) {
                $duedate = userdate($userduedate, get_string_manager()->get_string('strftimedatefullshort', 'langconfig',
                    null, $user->lang));
                $data = [
                    'certificationname' => $certificationname,
                    'duedate' => $duedate,
                ];
                $certmsg = get_string_manager()->get_string('notificationcertificationdue', 'tool_certification',
                    $data, $user->lang);
            } else {
                $data = ['certificationname' => $certificationname];
                $certmsg = get_string_manager()->get_string('notificationcertification', 'tool_certification', $data, $user->lang);
            }

            $a = [
                'userfullname' => fullname($user),
                'programname' => $programname,
                'certmsg' => $certmsg,
                'sitename' => format_string($SITE->fullname, true, ['context' => $contextid, 'escape' => false]),
                'siteurl' => (new \moodle_url('/'))->out(false),
            ];
            $subject = get_string_manager()->get_string('notificationsubjectcertificationuserallocated', 'tool_certification',
                $programname, $user->lang);
            $fullmessage = get_string_manager()->get_string('notificationcertificationuserallocated', 'tool_certification',
                $a, $user->lang);

            self::send_moodle_notification($user, $provider, $subject, $fullmessage, $program);
        }
    }

    /**
     * Send deallocated notification to the user involved.
     *
     * @param int $userid
     * @param int $itemid
     * @throws coding_exception
     */
    public static function send_certification_user_deallocated_notification(int $userid, int $itemid): void {
        global $SITE;
        if (program::record_exists($itemid)) {
            $user = core_user::get_user($userid);
            $provider = 'certificationuserdeallocated';
            $program = new program($itemid);
            $programname = $program->get_formatted_name();
            $contextid = \context_system::instance()->id;
            $a = [
                'userfullname' => fullname($user),
                'programname' => $programname,
                'sitename' => format_string($SITE->fullname, true, ['context' => $contextid, 'escape' => false]),
                'siteurl' => (new \moodle_url('/'))->out(false),
            ];
            $subject = get_string_manager()->get_string('notificationsubjectcertificationuserdeallocated', 'tool_certification',
                $programname, $user->lang);
            $fullmessage = get_string_manager()->get_string('notificationcertificationuserdeallocated', 'tool_certification',
                $a, $user->lang);

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
     * @throws coding_exception
     */
    private static function send_moodle_notification(stdClass $user, string $provider, string $subject, string $fullmessage,
                                                     program $program): void {
        $url = (new \moodle_url('/my'))->out();
        $dashboardstr = get_string_manager()->get_string('myhome', 'moodle', null, $user->lang);

        $message = new message();
        $message->courseid = SITEID;
        $message->component = 'tool_certification';
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
        $pu = 'pu';
        $join = self::get_status_sql_join($u, $c, $cu, $cc, $pr, $pu);
        $statuscase = self::get_status_sql_cases($status, $cu, $cc, $pu, true);
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
                $str = string_helper::translate_relativedate_string($certification->get('expirydaterelative'));
                return get_string('afterallocationdatewithrelativedate', 'tool_certification', $str);
                break;
            case constants::DATE_AFTER_COMPLETION:
                $str = string_helper::translate_relativedate_string($certification->get('expirydaterelative'));
                return get_string('aftercompletionwithrelativedate', 'tool_certification', $str);
                break;
            case constants::DATE_AFTER_DUE_DATE:
                $str = string_helper::translate_relativedate_string($certification->get('expirydaterelative'));
                return get_string('afterduedatewithrelativedate', 'tool_certification', $str);
                break;
            default:
                throw new coding_exception('unexpected certification expiry date type');
                break;
        }
    }

    /**
     * Returns the default expiry date for recertification.
     *
     * @param certification $certification
     * @return string
     * @throws coding_exception
     */
    private static function get_default_recertification_expirydate(certification $certification): string {
        switch ((int) $certification->get('recertexpirydatetype')) {
            case constants::RECERT_EXPIRY_DATE_NEVER_DATE:
                return get_string('never', 'tool_certification');
                break;
            case constants::RECERT_EXPIRY_DATE_AFTR_PREV_COMPL:
                $str = string_helper::translate_relativedate_string($certification->get('recertexpirydaterelative'));
                return get_string('afteractualcertcompletionwithrelativedate', 'tool_certification', $str);
                break;
            case constants::RECERT_EXPIRY_DATE_AFTR_PREV_EXP:
                $str = string_helper::translate_relativedate_string($certification->get('recertexpirydaterelative'));
                return get_string('afterpreviouscertexpdatewithrelativedate', 'tool_certification', $str);
                break;
            case constants::RECERT_EXPIRY_DATE_AFTR_LATEST:
                $str = string_helper::translate_relativedate_string($certification->get('recertexpirydaterelative'));
                return get_string('afterlatestwithrelativedate', 'tool_certification', $str);
                break;
            default:
                throw new coding_exception('unexpected recertification expiry date type');
                break;
        }
    }

    /**
     * Returns the default grace period for recertification.
     *
     * @param certification $certification
     * @return string
     * @throws coding_exception
     */
    private static function get_default_recertification_graceperiod(certification $certification): string {
        if (!$certification->get('recertgraceperiod')) {
            return '';
        }
        $str = string_helper::translate_relativedate_string($certification->get('recertgraceperiod'));
        return get_string('afterpreviouscertexpdatewithrelativedate', 'tool_certification', $str);
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
                $str = string_helper::translate_relativedate_string($certification->get('startdaterelative'));
                return get_string('afterallocationdatewithrelativedate', 'tool_certification', $str);
                break;
            default:
                throw new coding_exception('unexpected certification start date type');
                break;
        }
    }

    /**
     * Returns the default due date for certification.
     *
     * @param certification $certification
     * @return string
     * @throws coding_exception
     */
    private static function get_default_certification_duedate(certification $certification): string {
        switch ((int) $certification->get('duedatetype')) {
            case constants::DATE_NEVER:
                return get_string('never');
                break;
            case constants::DATE_AFTER_START_DATE:
                $str = string_helper::translate_relativedate_string($certification->get('duedaterelative'));
                return get_string('afterstartdatewithrelativedate', 'tool_certification', $str);
                break;
            default:
                throw new coding_exception('unexpected certification due date type');
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
        return (object) [
            'startdate' => self::get_default_certification_startdate($certification),
            'duedate' => self::get_default_certification_duedate($certification),
            'expirydate' => self::get_default_certification_expirydate($certification),
            'expirydaterecertification' => self::get_default_recertification_expirydate($certification),
            'graceperiod' => self::get_default_recertification_graceperiod($certification)
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
                if (!$certificationallocation || $certificationallocation->is_suspended()
                || (int)$certificationallocation->get('currentprogramid') !== $programid) {
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
            if ((int)$allocation->get('isrecertification') === 1 && (int)$certification->get('recertdifferentprogram') === 1) {
                $programid = $certification->get('recertificationprogram');
            }
            if (!self::is_program_completed($programid, $userid)) {
                continue;
            }
            // If user is already certified and not in a recertification period currentprogramid should be null.
            $currentprogramid = $allocation->get('currentprogramid');
            if (!isset($currentprogramid) && self::get_last_completion_record($userid, $certificationid)) {
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
        if (self::is_user_certified($userid, $certificationid) || self::is_user_certification_expired($userid, $certificationid)) {
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

        [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid', 'shared=1', $tenantid);
        // We need a case insensitive comparison on the value.
        $equal = $DB->sql_equal('idnumber', ':idnumber', false);
        $query = "SELECT *
                FROM {tool_certification}
                WHERE $equal AND $sql and archived = 0";
        $params += ['idnumber' => $idnumber];
        if ($record = $DB->get_record_sql($query, $params)) {
            return new certification(0, $record);
        }
        return null;
    }

    /**
     * Updates certification recertification data.
     *
     * @param stdClass $data
     * @return bool
     */
    public static function update_certification_recertification(stdClass $data): bool {
        $certification = new certification($data->id);
        $oldrecord = $certification->to_record();

        $certification->set('requirerecertification', $data->requirerecertification);
        if (1 === (int)$data->requirerecertification) {
            $certification->set('recertdifferentprogram', $data->recertdifferentprogram);
            if (1 === (int)$data->recertdifferentprogram) {
                $certification->set('recertificationprogram', $data->recertificationprogram);
            }
            $certification->set('recertstartdaterelative', $data->recertstartdaterelative);
            $certification->set('recertgraceperiod', $data->recertgraceperiod);
            $certification->set('recertexpirydatetype', $data->recertexpirydatetype);
            $certification->set('recertexpirydaterelative', $data->recertexpirydaterelative);
        }
        $certification->update();

        // Update dates for all users.
        self::recalculate_all_certification_users_dates($certification);

        // Trigger event.
        certification_updated::create_from_certification_updated($certification, $oldrecord)->trigger();

        return true;
    }

    /**
     * Allocate recertification users
     *
     * We look for completion records that have the recertification window open and it has not been yet expired.
     * Also checks that expirydate is not set to never, that certification has not been deleted and
     * That requirerecertification is enabled.
     *
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function allocate_recertification_users(): void {
        global $DB;

        $sql = '
            SELECT tcu.*, tc.program, tc.recertdifferentprogram, tc.recertificationprogram
            FROM {tool_certification_users} tcu
            JOIN {tool_certification} tc
            ON tc.id = tcu.certificationid AND tc.archived = 0
            JOIN {tool_tenant} tt
            ON tt.id = tc.tenantid AND tt.archived = 0
            WHERE tcu.nextstartdate > 0 AND tcu.nextstartdate <= :now AND tcu.currentprogramid IS NULL AND tcu.status = :enabled
            AND tc.requirerecertification = 1
        ';
        $records = $DB->get_records_sql($sql, ['now' => time(), 'enabled' => constants::STATUS_OVERRIDE_DEFAULT]);

        foreach ($records as $record) {
            $certificationid = $record->certificationid;
            $programid = $record->program;
            if ((int)$record->recertdifferentprogram === 1) {
                $programid = $record->recertificationprogram;
            }

            $certification = new certification($certificationid);
            $certificationuser = self::allocate_user_recertification($certification, $programid, $record->userid,
                constants::STATUS_OVERRIDE_DEFAULT);

            // Reset program for this user.
            $params = [
                'programid' => $programid,
                'certificationid' => $certificationid,
                'userid' => $record->userid,
            ];
            $programuser = program_user::get_record($params);
            \tool_program\api::reset_program_progress($programuser);

            // Trigger event.
            recertification_started::create_from_recertification_started($certificationuser, $programuser)->trigger();
        }
    }

    /**
     * Deallocate users after grace period ends
     *
     * We reallocate user from recertification program to initial program and reset all courses in the initial program
     * that ARE NOT present in the recertification program.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function deallocate_users_after_grace_period_end(): void {
        global $DB;

        $now = time();
        $sql = '
            SELECT tcu.*, tc.program
            FROM {tool_certification_users} tcu
            LEFT JOIN {tool_certification} tc
            ON tc.id = tcu.certificationid AND tc.archived = 0
            LEFT JOIN {tool_certification_compltion} tcc
            ON tcu.userid = tcc.userid AND tcu.certificationid = tcc.certificationid AND tcc.islast = 1 AND tcc.timerevoked = 0
            WHERE tcc.expirydate > 0 AND tcc.expirydate <= :now2
            AND tcu.currentprogramid IS NOT NULL AND tcu.currentprogramid <> tc.program
            AND tcu.currentprogramid = tc.recertificationprogram
            AND tcu.graceperiodends > 0 AND tcu.graceperiodends <= :now3
            AND tcu.status = :enabled AND tc.requirerecertification = 1
        ';
        $params = ['now1' => $now, 'now2' => $now, 'now3' => $now, 'enabled' => constants::STATUS_OVERRIDE_DEFAULT];
        $records = $DB->get_records_sql($sql, $params);

        if ($records) {
            foreach ($records as $record) {
                // If currentprogramid is different from initial program reallocate user to the initial program.
                $programinitial = new program($record->program);
                $programid = $record->program;
                unset($record->program);
                $certificationuser = new certification_user(0, $record);
                $certification = $certificationuser->get_certification();

                // Reallocate user into initial program.
                self::reallocate_user_into_initial_program($certification, $certificationuser);

                // Reset all courses for this user in the initial program that ARE NOT present in the recertification program.
                $programrecertification = new program($record->currentprogramid);
                self::reset_courses_in_program1_not_present_in_program2($programinitial, $programrecertification, $record->userid);

                // Set currentprogramid to initial certification program id.
                $certificationuser->set('currentprogramid', $programid);
                $certificationuser->update();

                // Trigger event.
                deallocate_after_graceperiodends::create_from_deallocate_after_graceperiodends($certificationuser,
                    $record->currentprogramid)->trigger();
            }
        }
    }

    /**
     * Reset all courses for this user in Program1 that ARE NOT present in Program2.
     *
     * @param program $program1
     * @param program $program2
     * @param int $userid
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function reset_courses_in_program1_not_present_in_program2(program $program1, program $program2,
        int $userid): void {
        $courseidstoreset = array_diff($program1->get_courses_ids(), $program2->get_courses_ids());
        $reason = get_string('programreset', 'tool_program');

        foreach ($courseidstoreset as $courseid) {
            $coursereset = new course_reset_api($courseid, $userid);
            $coursereset->reset_course(['programid' => $program1->get('id'), 'reason' => $reason]);
        }
    }

    /**
     * Returns last valid completion record for a user
     *
     * @param int $userid
     * @param int $certificationid
     * @return bool|certification_completion
     * @throws dml_exception
     */
    public static function get_last_completion_record(int $userid, int $certificationid) {
        global $DB;

        $sql = '
            SELECT MAX(id) as id
            FROM {tool_certification_compltion}
            WHERE userid = :userid AND certificationid = :certificationid
            AND timerevoked = 0 AND islast = 1
        ';
        $params = ['userid' => $userid, 'certificationid' => $certificationid];
        $lastrecord = $DB->get_record_sql($sql, $params);
        if ($lastrecord->id) {
            return new certification_completion($lastrecord->id);
        }
        return false;
    }

    /**
     * Returns the previous (valid and not revoked completion) record to the last one for this user and certification.
     *
     * @param int $userid
     * @param int $certificationid
     * @return mixed
     * @throws dml_exception
     */
    public static function get_previous_valid_completion_record(int $userid, int $certificationid) {
        global $DB;

        // We find the last valid completion record in the tool_certification_compltion table.
        $sql = '
                SELECT *
                FROM {tool_certification_compltion}
                WHERE id = (
                    SELECT MAX(id)
                    FROM {tool_certification_compltion}
                    WHERE userid = :userid AND certificationid = :certificationid AND timerevoked = 0
                )
            ';
        $params = ['userid' => $userid, 'certificationid' => $certificationid];
        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Recalculates user due date for recertification.
     *
     * @param certification_user $certificationuser
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function recalculate_recertification_user_due_date(certification_user $certificationuser): int {
        // We need previous expirydate.
        $userid = $certificationuser->get('userid');
        $lastcomlpetion = self::get_last_completion_record($userid, $certificationuser->get('certificationid'));
        if (!$lastcomlpetion) {
            throw new coding_exception('Completion records should exist if is recertification');
        }
        return (int)$lastcomlpetion->get('expirydate');
    }

    /**
     * Recalculates next start date for recertification.
     *
     * @param certification $certification
     * @param int $expirydate
     * @return int
     * @throws coding_exception
     */
    public static function recalculate_nextstartdate(certification $certification, int $expirydate): int {
        if ($expirydate === 0) {
            return 0;
        }
        return strtotime('-' . $certification->get('recertstartdaterelative'), $expirydate);
    }

    /**
     * Recalculates graceperiodends for recertification.
     *
     * @param certification $certification
     * @param int $expirydate
     * @return int
     * @throws coding_exception
     */
    public static function recalculate_graceperiodends(certification $certification, int $expirydate): int {
        if ($expirydate === 0) {
            return 0;
        }
        return strtotime('+' . $certification->get('recertgraceperiod'), $expirydate);
    }

    /**
     * Checks if recertification program is different from the initial one.
     *
     * @param certification $certification
     * @param certification_user $certificationuser
     * @return bool
     * @throws coding_exception
     */
    public static function is_recertification_program_different(certification $certification,
                                                                certification_user $certificationuser): bool {
        $currentprogram = (int)$certificationuser->get('currentprogramid');
        if ($currentprogram) {
            return (int)$certification->get('program') !== $currentprogram;
        } else {
            return (bool)$certification->get('recertdifferentprogram');
        }
    }

    /**
     * Deletes dynamic rules associated to the certification. Used when deleting a certification.
     *
     * @param int $certificationid
     */
    public static function delete_certification_dynamic_rules(int $certificationid): void {
        // Check if tool_dynamicrule is installed.
        if (!class_exists('\\tool_dynamicrule\\rules_list')) {
            return;
        }
        $params = [
            'component' => 'tool_certification',
            'componentarea' => 'certification',
            'itemid' => $certificationid
        ];
        $rules = \tool_dynamicrule\rule::get_records($params);
        foreach ($rules as $rule) {
            // TODO Change when WP-1293 is implemented.
            $rule->delete();
        }
    }

    /**
     * Returns latest programuser allocation to the certification/recertification.
     *
     * When user starts a recertification and the certification program is different than the initial program we are going to
     * have 2 program user allocations (initial and recertification). If only the initial exists it will return the initial.
     * If there is also a second allocations will return the second one that is the program is currently taking.
     *
     * @param int $userid
     * @param int $certificationid
     * @return program_user
     * @throws dml_exception
     */
    public static function get_latest_programuser_allocation(int $userid, int $certificationid): program_user {
        global $DB;
        // We have to get latest allocation. If it is only the initial one get it, if not get the other one.
        $sql = '
            SELECT tpu.*, tc.program AS initialprogram
            FROM {tool_program_users} tpu
            INNER JOIN {tool_certification} tc
            ON tpu.certificationid = tc.id
            WHERE tpu.userid = :userid AND tpu.certificationid = :certificationid
        ';
        $params = ['userid' => $userid, 'certificationid' => $certificationid];
        $records = $DB->get_records_sql($sql, $params);
        // If there is more than 1 record we need to get the programid that is not the initial program set in the certification.
        if (count($records) > 1) {
            $records = array_filter($records, static function($record) {
                return (int)$record->programid !== (int)$record->initialprogram;
            });
        }

        $record = reset($records);
        unset($record->initialprogram);
        return new program_user(0, $record);
    }

    /**
     * Translates a stored relative date (ex. 1 day/2 week/2 month/1 year) with correct language string.
     *
     * @param string $relativedate
     * @return string
     *
     * @deprecated since 3.11 - please use tool_wp\local\helpers\string_helper::translate_relativedate_string() instead.
     */
    public static function translate_relativedate_string(string $relativedate): string {
        debugging('This function is deprecated, please use ' .
            'tool_wp\local\helpers\string_helper::translate_relativedate_string() instead', DEBUG_DEVELOPER);
        return string_helper::translate_relativedate_string($relativedate);
    }

    /**
     * Updates the certification name in all certification related calendar events
     *
     * @param int $certificationid
     * @param string $certificationname
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function update_certification_name_in_calendar_events(int $certificationid, string $certificationname): void {
        global $DB;
        $params = [
            'component' => 'tool_certification',
            'instance' => $certificationid,
        ];
        $events = $DB->get_records('event', $params);
        foreach ($events as $event) {
            switch ($event->eventtype) {
                case 'tool_certification' . constants::CALENDAR_EVENT_DUE_DATE:
                    $event->name = get_string('calendarduedate', 'tool_certification', $certificationname);
                    $event->description = get_string('calendarduedate', 'tool_certification', $certificationname);
                    break;
                case 'tool_certification' . constants::CALENDAR_EVENT_EXPIRY_DATE:
                    $event->name = get_string('calendarexpirydate', 'tool_certification', $certificationname);
                    $event->description = get_string('calendarexpirydate', 'tool_certification', $certificationname);
                    break;
            }
            $DB->update_record('event', $event);
        }
    }

    /**
     * Hide all user calendar events for a given certification
     *
     * @param certification $certification
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function hide_certification_user_calendar_events(certification $certification): void {
        global $DB;
        $params = [
            'component' => 'tool_certification',
            'instance' => $certification->get('id'),
        ];
        $events = $DB->get_records('event', $params);
        foreach ($events as $event) {
            $event->visible = 0;
            $DB->update_record('event', $event);
        }
    }

    /**
     * Show all user calendar events for a given certification
     *
     * @param certification $certification
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function show_certification_user_calendar_events(certification $certification): void {
        global $DB;
        $params = [
            'component' => 'tool_certification',
            'instance' => $certification->get('id'),
        ];
        $events = $DB->get_records('event', $params);
        foreach ($events as $event) {
            $event->visible = 1;
            $DB->update_record('event', $event);
        }
    }

    /**
     * Remove all users from tenant groups that have component reference to this certification.
     *
     * @param certification $certification
     * @throws coding_exception
     */
    public static function remove_all_users_from_certification_course_groups(certification $certification): void {
        $certificationusers = certification_user::get_records(['certificationid' => $certification->get('id')]);

        foreach ($certificationusers as $certificationuser) {
            self::remove_user_from_certification_course_groups($certificationuser);
        }
    }

    /**
     * Remove user from tenant groups that have component reference to this certification.
     *
     * @param certification_user $certificationuser
     * @throws coding_exception
     */
    public static function remove_user_from_certification_course_groups(certification_user $certificationuser): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $userid = (int)$certificationuser->get('userid');
        $certificationid = (int)$certificationuser->get('certificationid');

        $tenantgroups = \tool_program\api::get_tenant_group_records(0, $certificationid);

        foreach ($tenantgroups as $tenantgroup) {
            groups_remove_member($tenantgroup->get('groupid'), $userid);
        }
    }

    /**
     * Restore all certification users membership in course groups.
     *
     * Restore all certification users membership in tenant course groups that
     * have component reference to this certification.
     *
     * @param certification $certification
     * @throws coding_exception
     */
    public static function restore_all_users_in_certification_course_groups(certification $certification): void {
        $programusers = program_user::get_records([
            'certificationid' => $certification->get('id'),
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ]);

        foreach ($programusers as $programuser) {
            \tool_program\api::restore_user_in_program_course_groups($programuser);
        }
    }

    /**
     * Restore user from tenant groups that have component reference to this certification.
     *
     * @param certification_user $certificationuser
     * @throws coding_exception
     */
    public static function restore_user_in_certification_course_groups(certification_user $certificationuser): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        if ((int)$certificationuser->get('status') === constants::STATUS_SUSPENDED) {
            return;
        }

        $userid = (int)$certificationuser->get('userid');
        $certificationid = (int)$certificationuser->get('certificationid');

        $programuser = program_user::get_record(['userid' => $userid, 'certificationid' => $certificationid]);
        \tool_program\api::restore_user_in_program_course_groups($programuser);
    }

    /**
     * Deletes all tenant groups that have component reference to this certification.
     *
     * @param certification $certification
     */
    public static function delete_all_certification_course_tenant_groups(certification $certification): void {
        $tenantgroups = tenant_group::get_records([
            'itemid' => $certification->get('id'),
            'component' => 'tool_certification',
            'area' => 'tool_certification',
        ]);
        foreach ($tenantgroups as $tenantgroup) {
            \tool_program\api::delete_component_course_tenant_group('tool_certification', 'tool_certification',
                $certification->get('id'), $tenantgroup->get('courseid'));
        }
    }

    /**
     * Returns all calculated certification user dates
     *
     * @param certification_user $certificationuser
     * @return stdClass
     * @throws coding_exception
     */
    public static function get_certification_user_dates(certification_user $certificationuser): stdClass {
        $userallocdate = (int)$certificationuser->get('timecreated');
        $certification = $certificationuser->get_certification();
        $certificationid = $certificationuser->get('certificationid');
        $userid = $certificationuser->get('userid');
        $programuser = program_user::get_record(['certificationid' => $certificationid, 'userid' => $userid,
            'programid' => $certificationuser->get('currentprogramid')]);

        $userstartdate = self::recalculate_user_start_date($certification, $certificationuser, $programuser, $userallocdate);
        $userduedate = self::recalculate_user_due_date($certification, $certificationuser, $programuser, $userstartdate);
        $userexpirydate = self::recalculate_user_expiry_date($certification, $certificationuser, $userallocdate, $userduedate);

        return (object)[
            'userstartdate' => $userstartdate,
            'userduedate' => $userduedate,
            'userexpirydate' => $userexpirydate,
        ];
    }
}
