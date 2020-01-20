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

        return true;
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
}
