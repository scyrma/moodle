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
 * The enrol plugin dynamicrule is defined here.
 *
 * @package     enrol_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Workplace team
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Class enrol_dynamicrule_plugin.
 *
 * @package     enrol_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Workplace team
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class enrol_dynamicrule_plugin extends enrol_plugin {

    /**
     * This plugin allows manual user unenrolment.
     *
     * This is only possible if 'enrol/dynamicrule:unenrol' capability permits so.
     *
     * @param stdClass $instance course enrol instance
     * @return bool
     */
    public function allow_unenrol(stdClass $instance) : bool {
        return true;
    }

    /**
     * This plugin does not allow manual enrolments.
     *
     * However 'enrol/dynamicrule:enrol' capability exists and used to determine
     * if course_enrol outcome can be used with selected course.
     *
     * @param stdClass $instance course enrol instance
     * @return bool
     */
    public function allow_enrol(stdClass $instance) : bool {
        return false;
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
        return [];
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

        if (!isset($fields['customint1'])) {
            debugging('Missing rule id that supposed to be passed as $fields[\'customint1\'].');
            return null;
        }

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

    /**
     * Is it possible to delete this instance
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance): bool {
        // If rule has been deleted we can allow to manually delete this instance.
        if (!\tool_dynamicrule\rule::get_record(['id' => $instance->customint1])) {
            return true;
        }

        // If outcome enrolling into the course from this rule is removed, we can allow to manually delete this instance.
        $outcomehasbeendeleted = true;
        $outcomes = \tool_dynamicrule\outcome::get_records([
            'ruleid' => $instance->customint1,
            'classname' => 'enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol',
        ]);
        foreach ($outcomes as $outcome) {
            $outcomebase = \tool_dynamicrule\outcome_base::instance(0, $outcome->to_record());
            if ($outcomebase->get_configdata()['coursetoenrol'] == $instance->courseid) {
                $outcomehasbeendeleted = false;
            }
        }

        return $outcomehasbeendeleted;
    }
}
