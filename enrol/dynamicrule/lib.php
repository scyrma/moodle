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
 * The enrol plugin dynamicrule is defined here.
 *
 * @package     enrol_dynamicrule
 * @copyright   2018 SP
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class enrol_dynamicrule_plugin.
 *
 * @package     enrol_dynamicrule
 * @copyright   2018 SP
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_dynamicrule_plugin extends enrol_plugin {

    /**
     * Adds form elements to add/edit instance form.
     *
     * @param stdClass $instance Enrol instance or null if does not exist yet.
     * @param MoodleQuickForm $mform
     * @param context $context
     * @return void
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        // Do nothing by default.
    }

    /**
     * Perform custom validation of the data used to edit the instance.
     *
     * @param array $data Array of ("fieldname"=>value) of submitted data.
     * @param array $files Array of uploaded files "element_name"=>tmp_file_path.
     * @param stdClass $instance The instance data loaded from the DB.
     * @param context $context The context of the instance we are editing.
     * @return array Array of "element_name"=>"error_description" if there are errors, empty otherwise.
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        // No errors by default.
        return array();
    }

    /**
     * This plugin can only be added by tool_dynamicrule.
     *
     * @param int $courseid
     * @return bool
     */
    public function can_add_instance($courseid) {
        return false;
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        return false;
    }

    /**
     * Returns localised name of enrol instance
     *
     * @param stdClass $instance
     * @return string
     */
    public function get_instance_name($instance): string {
        $pluginname = get_string('pluginname', 'enrol_dynamicrule');
        if ($rule = \tool_dynamicrule\rule::get_record(['id' => $instance->customint1])) {
            $rulename = format_string($rule->get('name'));
            return $pluginname . ' (' . $rulename . ')';
        }
        return $pluginname;
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

        $params = ['courseid' => $course->id, 'enrol' => 'dynamicrule', 'customint1' => $fields['customint1']];
        if ($DB->record_exists('enrol', $params)) {
            // Only one instance ruleid-courseid allowed.
            // We use customint1 to store rule id.
            return null;
        }

        return parent::add_instance($course, $fields);
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
