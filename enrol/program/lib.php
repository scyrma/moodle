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
 * The enrol program plugin is defined here.
 *
 * @package     enrol_program
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Workplace team
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\persistent\program;
use tool_program\persistent\program_user;

defined('MOODLE_INTERNAL') || die();

// The base class 'enrol_plugin' can be found at lib/enrollib.php.
// Override methods as necessary.

/**
 * Class enrol_program_plugin.
 *
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Workplace team
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class enrol_program_plugin extends enrol_plugin {
    /**
     * Does this plugin allow manual unenrolment of a specific user?
     *
     * All plugins allowing this must implement 'enrol/program:unenrol' capability.
     *
     * This is useful especially for synchronisation plugins that
     * do suspend instead of full unenrolment.
     *
     * @param stdClass $instance Course enrol instance.
     * @param stdClass $ue Record from user_enrolments table, specifies user.
     * @return bool True means user with 'enrol/program:unenrol' may unenrol this user,
     *              false means nobody may touch this user enrolment.
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue): bool {
        return false;
    }

    /**
     * Perform custom validation of the data used to edit the instance.
     *
     * @since Moodle 3.1.
     * @param array $data Array of ("fieldname"=>value) of submitted data.
     * @param array $files Array of uploaded files "element_name"=>tmp_file_path.
     * @param object $instance The instance data loaded from the DB.
     * @param context $context The context of the instance we are editing.
     * @return array Array of "element_name"=>"error_description" if there are errors, empty otherwise.
     */
    public function edit_instance_validation($data, $files, $instance, $context): array {
        return [];
    }

    /**
     * Return whether or not, given the current state, it is possible to add a new instance
     * of this enrolment plugin to the course.
     *
     * @param int $courseid .
     * @return bool.
     */
    public function can_add_instance($courseid): bool {
        return false;
    }

    /**
     * Add new instance of enrol plugin.
     *
     * @param stdClass $course
     * @param array|null $fields
     * @return int|null
     */
    public function add_instance($course, array $fields = null): ?int {
        global $DB;

        if ($DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'program', 'customint1' => $fields['customint1']])) {
            // Only one instance programid-courseid allowed, sorry.
            // We use customint1 to store program id.
            return null;
        }

        return parent::add_instance($course, $fields);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance): bool {
        return false;
    }

    /**
     * Checks if user can self enrol.
     *
     * @param stdClass $instance enrolment instance
     * @param bool $checkuserenrolment if true will check if user enrolment is inactive.
     *             used by navigation to improve performance.
     * @return bool|string true if successful, else error message or false
     */
    public function can_self_enrol(stdClass $instance, $checkuserenrolment = true) {
        global $DB, $OUTPUT, $USER;

        if ($checkuserenrolment) {
            if (isguestuser()) {
                // Can not enrol guest.
                return get_string('noguestaccess', 'enrol') . $OUTPUT->continue_button(get_login_url());
            }
            // Check if user is already enroled.
            if ($DB->get_record('user_enrolments', ['userid' => $USER->id, 'enrolid' => $instance->id])) {
                return get_string('canntenrol', 'enrol_program');
            }
        }

        if (ENROL_INSTANCE_ENABLED !== (int) $instance->status) {
            return get_string('canntenrol', 'enrol_program');
        }

        $now = time();
        if (!empty($instance->enrolstartdate) && $instance->enrolstartdate > $now) {
            return get_string('canntenrolearly', 'enrol_program', userdate($instance->enrolstartdate));
        }

        if (!empty($instance->enrolenddate) && $instance->enrolenddate < $now) {
            return get_string('canntenrollate', 'enrol_program', userdate($instance->enrolenddate));
        }

        if (!$program = program::get_record(['id' => $instance->customint1])) {
            return get_string('canntenrol', 'enrol_program');
        }

        if (!\tool_program\permission::can_self_enrol_to_course($instance->courseid, $program)) {
            if (program_user::record_exists_select(
                'userid = :userid AND programid = :programid',
                ['userid' => $USER->id, 'programid' => $program->get('id')])
            ) {
                $programtreeprogress = new \tool_program\program_tree_progress($program, $USER->id);
                $programcourseitem = $programtreeprogress->get_first_program_course_item_by_courseid($instance->courseid);
                if (!$programcourseitem->isunlocked) {
                    return get_string('canntenrollocked', 'enrol_program');
                }
            }
            return get_string('notenrollable', 'enrol');
        }

        return true;
    }

    /**
     * Creates course enrol form, checks if form submitted
     * and enrols user if necessary. It can also redirect.
     *
     * @param stdClass $instance
     * @return string html text, usually a form in a text box
     */
    public function enrol_page_hook(stdClass $instance) {
        global $CFG, $OUTPUT, $USER, $SESSION;

        require_once("$CFG->dirroot/enrol/self/locallib.php");

        $programid = $instance->customint1;
        $userid = $USER->id;

        // Don't show enrolment method if user is not allocated in the program.
        if (!program_user::record_exists_select(
            'userid = :userid AND programid = :programid',
            ['userid' => $userid, 'programid' => $programid])
        ) {
            return false;
        }

        $enrolstatus = $this->can_self_enrol($instance);

        if (true === $enrolstatus) {
            // Enrol user.
            $this->enrol_user($instance, $userid, $instance->roleid);
            // Add to groups.
            \tool_program\api::add_user_to_course_groups($instance, $USER->id);
            // Redirect.
            if (!empty($SESSION->wantsurl)) {
                $destination = $SESSION->wantsurl;
                unset($SESSION->wantsurl);
            } else {
                $destination = course_get_url($instance->courseid);
            }
            redirect($destination);
        } else {
            // Return the form with enrolstatus message.
            $data = new stdClass();
            $data->header = $this->get_instance_name($instance);
            $data->info = $enrolstatus;

            // The can_self_enrol call returns a button to the login page if the user is a
            // guest, setting the login url to the form if that is the case.
            $url = isguestuser() ? get_login_url() : null;
            $form = new enrol_program_empty_form($url, $data);
            ob_start();
            $form->display();
            $output = ob_get_clean();
            return $OUTPUT->box($output);
        }
    }

    /**
     * Self enrol user to course
     *
     * @deprecated since 3.8 No longer used
     *
     * @param stdClass $instance enrolment instance
     * @return void
     */
    public function enrol_self(stdClass $instance): void {
        global $USER;
        $this->enrol_user($instance, $USER->id, $instance->roleid);
    }

    /**
     * Enrol any user by user id
     *
     * @deprecated since 3.8 No longer used
     *
     * @param stdClass $instance
     * @param null $data
     */
    public function enrol_user_by_id(stdClass $instance, $data = null): void {
        $this->enrol_user($instance, $data['userid'], $instance->roleid);
    }

    /**
     * Returns localised name of enrol instance
     *
     * @param stdClass $instance
     * @return string
     */
    public function get_instance_name($instance): string {
        $pluginname = get_string('pluginname', 'enrol_program');
        if ($program = program::get_record(['id' => $instance->customint1])) {
            $programname = format_string($program->get('fullname'));
            return $pluginname . ' (' . $programname . ')';
        }
        return $pluginname;
    }

    /**
     * Restore instances as enrol manual instances and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid): void {
        global $DB;
        // There is only 1 manual enrol instance allowed per course.
        if ($instances = $DB->get_records('enrol', ['courseid' => $data->courseid, 'enrol' => 'manual'], 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } else {
            $instanceid = $this->add_instance($course, (array) $data);
        }
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    /**
     * Unenrol user from course,
     * the last unenrolment removes all remaining roles.
     *
     * @param stdClass $instance
     * @param int $userid
     * @return void
     */
    public function unenrol_user(stdClass $instance, $userid) {
        global $DB;

        parent::unenrol_user($instance, $userid);

        // Parent method removes user from component-less tenant group, if any other program course enrolment is
        // left that is supposed to use component-less tenant group, re-add to the group.
        $sql = "
            SELECT ttg.*, e.id as instanceid
            FROM {tool_tenant_group} ttg
            JOIN {enrol} e ON e.enrol = 'program' AND e.courseid = ttg.courseid AND e.status = 0
            JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
            WHERE ttg.component IS NULL AND ttg.area IS NULL AND ttg.itemid IS NULL
            AND ttg.courseid = :courseid AND ue.userid = :userid AND ttg.tenantid = :tenantid
        ";
        $tenantid = \tool_tenant\tenancy::get_tenant_id($userid);
        $params = ['courseid' => $instance->courseid, 'userid' => $userid, 'tenantid' => $tenantid];
        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $record) {
            groups_add_member($record->groupid, $userid, 'enrol_program', $record->instanceid);
        }
    }
}

/**
 * Prevent removal of enrol program group membership.
 * @param int $itemid
 * @param int $groupid
 * @param int $userid
 * @return bool
 */
function enrol_program_allow_group_member_remove($itemid, $groupid, $userid) {
    return false;
}
