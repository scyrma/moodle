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
 * External service definitions
 *
 * @package    format_wplist
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 <bas@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Starred courses block external functions.
 *
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 <bas@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class format_wplist_external extends core_course_external {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.6
     */
    public static function move_section_parameters() {
        return new external_function_parameters([
            'sectionnumber' => new external_value(PARAM_INT, 'Section number', VALUE_DEFAULT, 0),
            'sectiontarget' => new external_value(PARAM_INT, 'Target section number', VALUE_DEFAULT, 0),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0)
        ]);
    }

    /**
     * Move course section.
     *
     * @param int $sectionnumber Section Number
     * @param int $sectiontarget Section Target Number
     * @param int $courseid Course ID
     *
     * @return  array of warnings
     */
    public static function move_section($sectionnumber, $sectiontarget, $courseid) {
        global $DB;

        $params = self::validate_parameters(self::move_section_parameters(), [
            'sectionnumber' => $sectionnumber,
            'sectiontarget' => $sectiontarget,
            'courseid' => $courseid
        ]);

        $sectionnumber = $params['sectionnumber'];
        $sectiontarget = $params['sectiontarget'];
        $courseid = $params['courseid'];

        $coursecontext = context_course::instance($courseid);
        self::validate_context($coursecontext);
        require_capability('moodle/course:movesections', $coursecontext);

        if ($sectionnumber == 0) {
            throw new moodle_exception('Bad section number ' . $sectionnumber);
        }

        if (!$DB->record_exists('course_sections', array('course' => $courseid, 'section' => $sectionnumber))) {
            throw new moodle_exception('Bad section number ' . $sectionnumber);
        }
        $maxsection = $DB->get_fieldset_sql('SELECT max(section) FROM {course_sections} WHERE course = ?', [$courseid]);

        $course = get_course($courseid);

        $warnings = [];

        if (!$sectiontarget) {
            $destination = $maxsection;
        } else if ($sectionnumber < $sectiontarget) {
            $destination = $sectiontarget - 1;
        } else {
            $destination = $sectiontarget;
        }
        if ($destination <= 0 || $destination > $maxsection) {
            throw new moodle_exception('Bad target section number ' . $sectiontarget);
        }
        if (!move_section_to($course, $sectionnumber, $destination, true)) {
            $warnings[] = array(
                'item' => 'section',
                'itemid' => $sectionnumber,
                'warningcode' => 'movesectionfailed',
                'message' => 'Section: ' . $sectionnumber . ' SectionTarget: ' . $sectiontarget . ' CourseID: ' . $courseid
            );
        }

        $result = [];
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.6
     */
    public static function move_section_returns() {
        return new external_single_structure(
            array(
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.6
     */
    public static function move_module_parameters() {
        return new external_function_parameters([
            'moduleid' => new external_value(PARAM_INT, 'Module ID', VALUE_DEFAULT, 0),
            'moduletarget' => new external_value(PARAM_INT, 'Target module ID', VALUE_DEFAULT, 0),
            'sectionnumber' => new external_value(PARAM_INT, 'Section number', VALUE_DEFAULT, 0),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0)
        ]);
    }

    /**
     * Move course module.
     *
     * @param int $moduleid module ID
     * @param int $moduletarget module Target ID
     * @param int $sectionnumber Section Number
     * @param int $unused
     *
     * @return  array of warnings
     */
    public static function move_module($moduleid, $moduletarget, $sectionnumber, $unused) {
        global $DB;

        // TODO $courseid is not needed here.
        $params = self::validate_parameters(self::move_module_parameters(), [
            'moduleid' => $moduleid,
            'moduletarget' => $moduletarget,
            'sectionnumber' => $sectionnumber,
            'courseid' => $unused
        ]);

        $moduleid = $params['moduleid'];
        $moduletarget = $params['moduletarget'];
        $sectionnumber = $params['sectionnumber'];

        $mod = get_coursemodule_from_id(null, $moduleid, 0, false, MUST_EXIST);
        $courseid = $mod->course;
        self::validate_context(context_course::instance($courseid));
        $modcontext = context_module::instance($mod->id);
        require_capability('moodle/course:manageactivities', $modcontext);

        $section = $DB->get_record('course_sections', ['course' => $courseid, 'section' => $sectionnumber], '*', MUST_EXIST);

        $warnings = [];
        if (!moveto_module($mod, $section, $moduletarget)) {
            $warnings[] = array(
                'item' => 'module',
                'itemid' => $moduleid,
                'warningcode' => 'movemodulefailed',
                'message' => 'module: ' . $moduleid . ' moduleTarget: ' . $moduletarget .
                    ' CourseID: ' . $courseid . ' sectionnumber ' . $sectionnumber
            );
        }

        $result = [];
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.6
     */
    public static function move_module_returns() {
        return new external_single_structure(
            array(
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.6
     */
    public static function module_completion_parameters() {
        return new external_function_parameters([
            'moduleid' => new external_value(PARAM_INT, 'Module ID', VALUE_DEFAULT, 0),
            'targetstate' => new external_value(PARAM_INT, 'Target Target', VALUE_DEFAULT, 0),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0)
        ]);
    }

    /**
     * update module completion.
     *
     * @param int $moduleid module ID
     * @param int $targetstate 1 for set completed, 0 for removing completion.
     * @param int $unused
     *
     * @return  array of warnings
     */
    public static function module_completion($moduleid, $targetstate, $unused) {
        global $PAGE;

        // TODO courseid is not needed in this web service.
        $params = self::validate_parameters(self::module_completion_parameters(), [
            'moduleid' => $moduleid,
            'targetstate' => $targetstate,
            'courseid' => $unused
        ]);
        $targetstate = $params['targetstate'];

        /** @var cm_info $cm */
        list($course, $cm) = get_course_and_cm_from_cmid($params['moduleid']);
        self::validate_context($cm->context);

        // Set up completion object and check it is enabled.
        $completion = new completion_info($course);
        if (!$completion->is_enabled()) {
            throw new moodle_exception('completionnotenabled', 'completion');
        }

        // NOTE: All users are allowed to toggle their completion state, including
        // users for whom completion information is not directly tracked. (I.e. even
        // if you are a teacher, or admin who is not enrolled, you can still toggle
        // your own completion state. You just don't appear on the reports.).

        // Check completion state is manual.
        $warnings = [];
        if ($cm->completion != COMPLETION_TRACKING_MANUAL) {
            $warnings[] = array(
                'item' => 'module',
                'itemid' => $moduleid,
                'warningcode' => 'completion change failed',
                'message' => 'module: ' . $moduleid . ' TargetState: ' .
                    $targetstate . ' CourseID: ' . $cm->course
            );
        }

        $completion->update_state($cm, $targetstate);

        /** @var format_wplist_renderer $renderer */
        $renderer = $PAGE->get_renderer('format_wplist');
        $result = [
            'completionicon' => $renderer->course_section_cm_completion($course, $completion, $cm, []),
            'warnings' => $warnings
        ];
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.6
     */
    public static function module_completion_returns() {
        return new external_single_structure(
            array(
                'completionicon' => new external_value(PARAM_RAW, 'JSON-encoded data for template'),
                'warnings' => new external_warnings()
            )
        );
    }
}
