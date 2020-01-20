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
 * File for course reset class.
 *
 * @package   tool_wp
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/rating/lib.php');
require_once("$CFG->dirroot/grade/querylib.php");
require_once("$CFG->libdir/completionlib.php");
require_once("$CFG->libdir/grade/grade_item.php");
require_once("$CFG->libdir/grade/grade_category.php");
require_once("$CFG->libdir/grade/constants.php");
require_once("$CFG->libdir/gradelib.php");
require_once($CFG->dirroot.'/mod/assign/locallib.php');

use assign_plugin;
use cm_info;
use core_component;
use core_privacy\local\request\approved_contextlist;
use lesson;
use stdClass;
use rating_manager;
use context_module;
use cache;
use core_user;
use core_tag_tag;
use grade_item;
use grade_grade;

/**
 * Course reset class.
 *
 * @package   tool_wp
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_reset_api {

    /**
     * @var int $userid User id
     */
    private $userid;
    /**
     * @var int $courseid Course id
     */
    private $courseid;
    /**
     * @var stdClass $user User
     */
    private $user;
    /**
     * @var array $moduletypes All module types
     */
    private $moduletypes = [];
    /**
     * @var array $resetinfo Info about what has been reset
     */
    private $resetinfo = [];
    /**
     * @var array $cminfo Course module info
     */
    private $cminfo = [];

    /** @var int Nothing to do */
    public const RESET_NOTHING = 0;
    /** @var int Course completion or course completion criteria reset */
    public const RESET_COURSE_COMPLETION = 1;
    /** @var int Grades reset */
    public const RESET_GRADES = 2;
    /** @var int Outcomes reset */
    public const RESET_OUTCOMES = 4;
    /** @var int Activity completion reset */
    public const RESET_ACTIVITY_COMPLETION = 16;
    /** @var int Successful reset of plugins with all or partial module-specific user data being reset (including ratings) */
    public const RESET_ALL_DATA = 32;
    /** @var int Skipped plugins with user data because reset is not possible */
    public const RESET_NOT_POSSIBLE = 64;

    /**
     * course_reset constructor.
     *
     * @param int $courseid
     * @param int $userid
     * @throws \dml_exception
     */
    public function __construct(int $courseid, int $userid) {
        $this->courseid = $courseid;
        $this->userid = $userid;
        $this->user = core_user::get_user($userid);
        $this->moduletypes = $this->get_module_types();
    }

    /**
     * Returns available module types
     *
     * @return array
     * @throws \dml_exception
     */
    private function get_module_types(): array {
        global $DB;
        return $DB->get_records('modules');
    }

    /**
     * Resets course for a given userid
     *
     * $resetdata may contain: Reason for reset, program id and certification id.
     *
     * @param array $resetdata
     * @return bool
     * @throws \dml_exception
     */
    public function reset_course(array $resetdata = []): bool {
        global $DB;

        $resetdata['courseid'] = $this->courseid;
        $resetdata['userid'] = $this->userid;
        $resetdata['resetstatus'] = self::RESET_NOTHING;

        $modules = $DB->get_records('course_modules', ['course' => $this->courseid, 'deletioninprogress' => 0]);

        // Get current course grades.
        $result = grade_get_course_grades($this->courseid, $this->userid);
        $grd = $result->grades[$this->userid];
        $resetdata['grade'] = ($grd->grade === null || !$grd->grade) ? 0 : $grd->str_grade;

        // Get current course completion.
        $course = get_course($this->courseid);
        $cinfo = new \completion_info($course);
        $resetdata['wascompleted'] = (int)$cinfo->is_course_complete($this->userid);

        foreach ($modules as $module) {
            $this->cminfo['status'] = self::RESET_NOTHING;

            // Call each module method if exists, if not try to call 'delete_data_for_user' from privacy api.
            $modname = $this->moduletypes[$module->module]->name;
            $method = 'reset_mod_' . $modname;
            if (method_exists($this, $method)) {
                $this->$method($module);
            } else {
                // Check if dataprivacy api exists and use it.
                $this->cminfo = [
                    'cmid' => $module->id,
                    'plugin' => 'mod_' . $modname,
                    'status' => self::RESET_NOT_POSSIBLE
                ];
                $provider = '\mod_' . $modname . '\privacy\provider::class';
                if (class_exists($provider) && method_exists($provider, 'delete_data_for_user')) {
                    $this->reset_mod_using_privacy_api($module, 'mod_' . $modname, $provider);
                    $this->cminfo['status'] = self::RESET_ALL_DATA;
                }
            }

            // Reset mod completion.
            $this->reset_mod_completion($module);
            // Add module reset info to resetinfo field.
            $this->resetinfo[] = $this->cminfo;
            // Trigger course module reset event.
            \tool_wp\event\course_module_reset::create_from_course_module_reset((object)$resetdata, $module->id)->trigger();
        }

        $resetdata['resetstatus'] += $this->reset_course_completions();
        $resetdata['resetstatus'] += $this->delete_grades();

        cache::make('core', 'completion')->purge();
        cache::make('core', 'coursecompletion')->purge();

        $resetdata['resetinfo'] = json_encode($this->resetinfo);
        // Log reset info to db.
        $recordid = $this->add_log($resetdata);
        \tool_wp\event\course_reset::create_from_course_reset((object)$resetdata, $recordid)->trigger();

        return true;
    }

    /**
     * Resets module using the privacy API
     *
     * @param stdClass $cm
     * @param string $modname
     * @param string $provider
     */
    private function reset_mod_using_privacy_api(stdClass $cm, string $modname, string $provider): void {
        $context = context_module::instance($cm->id);

        $approvedcontextlist = new approved_contextlist(
            $this->user,
            $modname,
            [$context->id]
        );

        $provider::delete_data_for_user($approvedcontextlist);
    }

    /**
     * Resets glossary module
     *
     * @param stdClass $cm
     * @throws \dml_exception
     */
    private function reset_mod_glossary(stdClass $cm): void {
        global $DB;

        $status = self::RESET_NOTHING;
        if ($DB->count_records('glossary_entries', ['glossaryid' => $cm->instance, 'userid' => $this->userid]) > 0) {
            $this->reset_mod_using_privacy_api($cm, 'mod_glossary', \mod_glossary\privacy\provider::class);
            $status = self::RESET_ALL_DATA;
        }

        $this->cminfo = [
            'cmid' => $cm->id,
            'plugin' => 'mod_glossary',
            'status' => $status
        ];
    }

    /**
     * Resets choice module
     *
     * @param stdClass $cm
     * @throws \dml_exception
     */
    private function reset_mod_choice(stdClass $cm): void {
        global $DB;

        $status = self::RESET_NOTHING;
        if ($DB->count_records('choice_answers', ['choiceid' => $cm->instance, 'userid' => $this->userid]) > 0) {
            $this->reset_mod_using_privacy_api($cm, 'mod_choice', \mod_choice\privacy\provider::class);
            $status = self::RESET_ALL_DATA;
        }

        $this->cminfo = [
            'cmid' => $cm->id,
            'plugin' => 'mod_choice',
            'status' => $status
        ];
    }

    /**
     * Resets scorm module
     *
     * @param stdClass $cm
     * @throws \dml_exception
     */
    private function reset_mod_scorm(stdClass $cm): void {
        global $DB;

        $status = self::RESET_NOTHING;
        if ($DB->count_records('scorm_scoes_track', ['scormid' => $cm->instance, 'userid' => $this->userid]) > 0) {
            $this->reset_mod_using_privacy_api($cm, 'mod_scorm', \mod_scorm\privacy\provider::class);
            $status = self::RESET_ALL_DATA;
        }

        $this->cminfo = [
            'cmid' => $cm->id,
            'plugin' => 'mod_scorm',
            'status' => $status
        ];
    }

    /**
     * Resets quiz module
     *
     * @param stdClass $cm
     * @throws \dml_exception
     */
    private function reset_mod_quiz(stdClass $cm): void {
        global $DB;

        $status = self::RESET_NOTHING;
        if ($DB->count_records('quiz_attempts', ['quiz' => $cm->instance, 'userid' => $this->userid]) > 0) {
            $this->reset_mod_using_privacy_api($cm, 'mod_quiz', \mod_quiz\privacy\provider::class);
            $status = self::RESET_ALL_DATA;
        }

        $this->cminfo = [
            'cmid' => $cm->id,
            'plugin' => 'mod_quiz',
            'status' => $status
        ];
    }

    /**
     * Resets survey module
     *
     * @param stdClass $cm
     * @throws \dml_exception
     */
    private function reset_mod_survey(stdClass $cm): void {
        global $DB;

        $status = self::RESET_NOTHING;
        if ($DB->count_records('survey_answers', ['survey' => $cm->instance, 'userid' => $this->userid]) > 0) {
            $this->reset_mod_using_privacy_api($cm, 'mod_survey', \mod_survey\privacy\provider::class);
            $status = self::RESET_ALL_DATA;
        }

        $this->cminfo = [
            'cmid' => $cm->id,
            'plugin' => 'mod_survey',
            'status' => $status
        ];
    }

    /**
     * Resets LTI module
     *
     * @param stdClass $cm
     * @throws \dml_exception
     */
    private function reset_mod_lti(stdClass $cm): void {
        global $DB;

        $status = self::RESET_NOTHING;
        if ($DB->count_records('lti_submission', ['ltiid' => $cm->instance, 'userid' => $this->userid]) > 0) {
            $this->reset_mod_using_privacy_api($cm, 'mod_lti', \mod_lti\privacy\provider::class);
            $status = self::RESET_ALL_DATA;
        }

        $this->cminfo = [
            'cmid' => $cm->id,
            'plugin' => 'mod_lti',
            'status' => $status
        ];
    }

    /**
     * Resets feedback module
     *
     * @param stdClass $cm
     * @throws \dml_exception
     */
    private function reset_mod_feedback(stdClass $cm): void {
        global $DB;

        $status = self::RESET_NOTHING;
        // Delete completed submissions.
        $params = ['feedback' => $cm->instance, 'userid' => $this->userid];
        if ($completeds = $DB->get_records('feedback_completed', $params)) {
            foreach ($completeds as $completed) {
                feedback_delete_completed($completed);
            }
            $status = self::RESET_ALL_DATA;
        }
        // Delete incompleted submissions.
        if ($tmps = $DB->get_records('feedback_completedtmp', $params)) {
            foreach ($tmps as $tmp) {
                // Delete the temp records.
                $DB->delete_records('feedback_completedtmp', array('id' => $tmp->id));
                $DB->delete_records('feedback_valuetmp', array('completed' => $tmp->id));
            }
            $status = self::RESET_ALL_DATA;
        }

        $this->cminfo = [
            'cmid' => $cm->id,
            'plugin' => 'mod_feedback',
            'status' => $status
        ];
    }

    /**
     * This will retrieve a grade object from the db.
     *
     * @param stdClass $cm
     * @param int $attemptnumber The attempt number to retrieve the grade for. -1 means the latest submission.
     * @return stdClass The grade record
     */
    protected function get_user_grade(stdClass $cm, int $attemptnumber=-1) {
        global $DB;

        $submission = null;

        $params = ['id' => $cm->instance];
        $instance = $DB->get_record('assign', $params, '*', MUST_EXIST);

        $params = ['assignment' => $cm->instance, 'userid' => $this->userid];
        if ($attemptnumber < 0) {
            // Make sure this grade matches the latest submission attempt.
            if ($instance->teamsubmission) {
                $submission = $this->get_group_submission(0, $cm, $attemptnumber);
            } else {
                $submission = $this->get_user_submission($cm, $attemptnumber);
            }
            if ($submission) {
                $attemptnumber = $submission->attemptnumber;
            }
        }

        if ($attemptnumber >= 0) {
            $params['attemptnumber'] = $attemptnumber;
        }

        $grades = $DB->get_records('assign_grades', $params, 'attemptnumber DESC', '*', 0, 1);

        if ($grades) {
            return reset($grades);
        }

        return false;
    }

    /**
     * Load the plugins from the sub folders under subtype.
     *
     * @param string $subtype - either submission or feedback
     * @param \assign $assign
     * @return array
     */
    protected function load_plugins(string $subtype, \assign $assign): array {
        $result = array();

        $names = core_component::get_plugin_list($subtype);

        foreach ($names as $name => $path) {
            if (file_exists($path . '/locallib.php')) {
                require_once($path . '/locallib.php');

                $shortsubtype = substr($subtype, strlen('assign'));
                $pluginclass = 'assign_' . $shortsubtype . '_' . $name;

                $plugin = new $pluginclass($assign, $name);

                if ($plugin instanceof assign_plugin) {
                    $idx = $plugin->get_sort_order();
                    while (array_key_exists($idx, $result)) {
                        $idx += 1;
                    }
                    $result[$idx] = $plugin;
                }
            }
        }
        ksort($result);
        return $result;
    }

    /**
     *  Deletes submission and grade for a user.
     *
     * @param stdClass $cm
     * @param \assign $assignment
     * @return bool
     * @throws \ddl_exception
     * @throws \ddl_table_missing_exception
     * @throws \dml_exception
     */
    protected function delete_user_submission(stdClass $cm, \assign $assignment): bool {
        global $DB;

        $grade = $this->get_user_grade($cm);
        $context = context_module::instance($cm->id);

        if ($submission = $this->get_user_submission($cm)) {
            // Create file storage object.
            $fs = get_file_storage();
            // Dbman to check if a plugin table exists.
            $dbman = $DB->get_manager();

            // Delete files associated with this assignment for this user.
            $assignmentid = $cm->instance;
            $plugintypes = array();
            $plugintypes['submissionplugins'] = ['fieldname' => 'submission', 'itemid' => $submission->id];
            if ($grade) {
                $plugintypes['feedbackplugins'] = array('fieldname' => 'grade', 'itemid' => $grade->id);
            }

            $submissionplugins = $this->load_plugins('assignsubmission', $assignment);

            foreach ($plugintypes as $plugintype => $params) {
                $deleteparams = array('assignment' => $assignmentid, $params['fieldname'] => $params['itemid'] );
                foreach ($submissionplugins as $plugin) {
                    $plugincomponent = $plugin->get_subtype() . '_' . $plugin->get_type();
                    $fileareas = $plugin->get_file_areas();
                    foreach ($fileareas as $filearea) {
                        $fs->delete_area_files($context->id, $plugincomponent, $filearea, $params['itemid']);
                    }
                    // If a plugin component table exists then delete the records for this submission/feedback.
                    if ($dbman->table_exists($plugincomponent) &&
                        $dbman->field_exists($plugincomponent, $params['fieldname']) &&
                        $DB->record_exists($plugincomponent, $deleteparams)) {
                        $DB->delete_records($plugincomponent, $deleteparams);
                    }
                }
            }

            $DB->delete_records('assign_submission', array('id' => $submission->id));
        }

        // Remove assignment grade.
        if ($grade) {
            $DB->delete_records('assign_grades', array('id' => $grade->id));
        }

        return true;
    }

    /**
     * Load the submission object for a particular user.
     *
     * For team assignments there are 2 submissions - the student submission and the team submission
     * All files are associated with the team submission but the status of the students contribution is
     * recorded separately.
     *
     * @param stdClass $cm
     * @param int $attemptnumber - -1 means the latest attempt
     * @return stdClass The submission
     */
    protected function get_user_submission(stdClass $cm, int $attemptnumber=-1) {
        global $DB;

        // If the userid is not null then use userid.
        $params = array('assignment' => $cm->instance, 'userid' => $this->userid, 'groupid' => 0);
        if ($attemptnumber >= 0) {
            $params['attemptnumber'] = $attemptnumber;
        }

        // Only return the row with the highest attemptnumber.
        $submission = null;
        $submissions = $DB->get_records('assign_submission', $params, 'attemptnumber DESC', '*', 0, 1);
        if ($submissions) {
            $submission = reset($submissions);
        }

        if ($submission) {
            return $submission;
        }

        return false;
    }

    /**
     * This is used for team assignments to get the group for the specified user.
     * If the user is a member of multiple or no groups this will return false
     *
     * @return mixed The group or false
     */
    protected function get_submission_group() {
        $groups = groups_get_all_groups($this->courseid, $this->userid);
        if (count($groups) != 1) {
            return false;
        }
        return array_pop($groups);
    }

    /**
     * Load the group submission object for a particular user.
     *
     * @param int $groupid The id of the group for this user.
     * @param stdClass $cm
     * @param int $attemptnumber - -1 means the latest attempt
     * @return stdClass The submission
     */
    protected function get_group_submission(int $groupid, stdClass $cm, int $attemptnumber=-1) {
        global $DB;

        if ($groupid == 0) {
            $group = $this->get_submission_group();
            if ($group) {
                $groupid = $group->id;
            }
        }

        // Now get the group submission.
        $params = array('assignment' => $cm->instance, 'groupid' => $groupid, 'userid' => 0);
        if ($attemptnumber >= 0) {
            $params['attemptnumber'] = $attemptnumber;
        }

        // Only return the row with the highest attemptnumber.
        $submission = null;
        $submissions = $DB->get_records('assign_submission', $params, 'attemptnumber DESC', '*', 0, 1);
        if ($submissions) {
            $submission = reset($submissions);
        }

        if ($submission) {
            return $submission;
        }

        return false;
    }

    /**
     * Unlock the student submission.
     *
     * @param stdClass $cm
     * @param \assign $assignment
     * @return bool
     */
    protected function unlock_submission(stdClass $cm, \assign $assignment): bool {
        global $DB;

        // Need grade permission.
        $context = context_module::instance($cm->id);
        require_capability('mod/assign:grade', $context);

        // Give each submission plugin a chance to process the unlocking.
        $plugins = $this->load_plugins('assignsubmission', $assignment);
        $submission = $this->get_user_submission($cm, false);

        $flags = $this->get_user_flags($cm);
        if ($flags) {
            $flags->locked = 0;
            $this->update_user_flags($flags);

            foreach ($plugins as $plugin) {
                if ($plugin->is_enabled() && $plugin->is_visible()) {
                    $plugin->unlock($submission, $flags);
                }
            }
        }

        $user = $DB->get_record('user', array('id' => $this->userid), '*', MUST_EXIST);
        \mod_assign\event\submission_unlocked::create_from_user($assignment, $user)->trigger();
        return true;
    }

    /**
     * Update user flags for this user in this assignment.
     *
     * @param stdClass $flags a flags record keyed on id
     * @return bool true for success
     */
    protected function update_user_flags(stdClass $flags): bool {
        global $DB;
        if ($flags->userid <= 0 || $flags->assignment <= 0 || $flags->id <= 0) {
            return false;
        }

        return $DB->update_record('assign_user_flags', $flags);
    }

    /**
     * This will retrieve a user flags object from the db.
     *
     * @param stdClass $cm
     * @return stdClass|bool The flags record
     */
    protected function get_user_flags(stdClass $cm) {
        global $DB;

        $params = array('assignment' => $cm->instance, 'userid' => $this->userid);

        $flags = $DB->get_record('assign_user_flags', $params);

        if ($flags) {
            return $flags;
        }

        return false;
    }

    /**
     * Resets assign module
     *
     * @param stdClass $cm
     * @throws \dml_exception
     */
    private function reset_mod_assign(stdClass $cm): void {
        global $DB;

        $context = context_module::instance($cm->id);
        $course = get_course($this->courseid);
        $assignment = new \assign($context, $cm, $course);

        $status = self::RESET_NOTHING;

        $sql = "SELECT asb.id
            FROM {assign_submission} asb
            JOIN {assign} a ON asb.assignment = a.id
            JOIN {course_modules} cm ON cm.instance = a.id
            WHERE cm.id = :cmid AND asb.userid = :userid";
        $params = [
            'cmid' => $cm->id,
            'userid' => $this->userid
        ];
        $ids = $DB->get_fieldset_sql($sql, $params);

        if ($ids) {
            $this->delete_user_submission($cm, $assignment);
            $this->unlock_submission($cm, $assignment);

            $params = ['assignid' => $cm->instance, 'userid' => $this->userid];
            $records = $DB->get_records('assign_overrides', $params);
            if ($records) {
                foreach ($records as $record) {
                    $assignment->delete_override($record->id);
                }
            }

            assign_refresh_events($this->courseid);

            $status += self::RESET_ALL_DATA;
        }

        $this->cminfo = [
            'cmid' => $cm->id,
            'plugin' => 'mod_assign',
            'status' => $status
        ];
    }

    /**
     * Resets data module
     *
     * @param stdClass $cm
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function reset_mod_data(stdClass $cm): void {
        global $DB;

        $status = self::RESET_NOTHING;
        $fs = get_file_storage();
        $rm = new rating_manager();
        $ratingdeloptions = new stdClass;
        $ratingdeloptions->component = 'mod_data';
        $ratingdeloptions->ratingarea = 'entry';

        $sql = "
            SELECT dr.id, dr.dataid
            FROM {data_records} dr
            JOIN {data} d ON dr.dataid = d.id
            WHERE d.course = ? AND dr.userid = ? AND d.id = ?
        ";
        $rs = $DB->get_recordset_sql($sql, [$this->courseid, $this->userid, $cm->instance]);
        foreach ($rs as $record) {
            if (!$cminstance = get_coursemodule_from_instance('data', $record->dataid)) {
                continue;
            }
            $datacontext = context_module::instance($cminstance->id);
            $ratingdeloptions->contextid = $datacontext->id;
            $ratingdeloptions->itemid = $record->id;
            $rm->delete_ratings($ratingdeloptions);

            // Delete any files that may exist.
            if ($contents = $DB->get_records('data_content', ['recordid' => $record->id], '', 'id')) {
                foreach ($contents as $content) {
                    $fs->delete_area_files($datacontext->id, 'mod_data', 'content', $content->id);
                }
            }
            core_tag_tag::remove_all_item_tags('mod_data', 'data_records', $record->id);
            $DB->delete_records('comments', ['itemid' => $record->id, 'commentarea' => 'database_entry']);
            $DB->delete_records('data_content', ['recordid' => $record->id]);
            $DB->delete_records('data_records', ['id' => $record->id]);

            $status = self::RESET_ALL_DATA;
        }
        $rs->close();

        $this->cminfo = [
            'cmid' => $cm->id,
            'plugin' => 'mod_data',
            'status' => $status
        ];
    }

    /**
     * Resets lesson module
     *
     * @param stdClass $cm
     * @throws \dml_exception
     */
    private function reset_mod_lesson(stdClass $cm): void {
        global $DB;

        $status = self::RESET_NOTHING;

        $lessonbranch = $DB->count_records('lesson_branch', ['lessonid' => $cm->instance, 'userid' => $this->userid]);
        $lessonattempts = $DB->count_records('lesson_attempts', ['lessonid' => $cm->instance, 'userid' => $this->userid]);
        if ($lessonbranch > 0 || $lessonattempts > 0) {
            $this->reset_mod_using_privacy_api($cm, 'mod_lesson', \mod_lesson\privacy\provider::class);
            $lesson = new lesson($DB->get_record('lesson', array('id' => $cm->instance)));
            lesson_update_events($lesson);

            $status = self::RESET_ALL_DATA;
        }

        $this->cminfo = [
            'cmid' => $cm->id,
            'plugin' => 'mod_lesson',
            'status' => $status
        ];
    }

    /**
     * Resets forum module
     *
     * @param stdClass $cm
     * @throws \dml_exception
     */
    private function reset_mod_forum(stdClass $cm): void {
        global $DB;

        // Delete all ratings that are linked to the items post by this user (not the ratings submitted by this user).
        $rm = new rating_manager();
        $ratingdeloptions = new stdClass;
        $ratingdeloptions->component = 'mod_forum';
        $ratingdeloptions->ratingarea = 'post';

        $sql = "
            SELECT p.id
            FROM {forum_posts} p
            JOIN {forum_discussions} d ON d.id = p.discussion
            JOIN {forum} f ON d.forum = f.id
            WHERE f.course = ? AND p.userid = ? AND f.id = ?
        ";
        $rs = $DB->get_recordset_sql($sql, [$this->courseid, $this->userid, $cm->instance]);
        foreach ($rs as $record) {
            $datacontext = context_module::instance($cm->id);
            $ratingdeloptions->contextid = $datacontext->id;
            $ratingdeloptions->itemid = $record->id;
            $rm->delete_ratings($ratingdeloptions);
        }

        $this->cminfo = [
            'cmid' => $cm->id,
            'plugin' => 'mod_forum',
            'status' => self::RESET_NOTHING
        ];
    }

    /**
     * Resets workshop module
     *
     * @param stdClass $cm
     */
    private function reset_mod_workshop(stdClass $cm): void {
        global $DB;

        $sql = "SELECT ws.id
            FROM {workshop_submissions} ws
            JOIN {workshop} w ON ws.workshopid = w.id
            JOIN {course_modules} cm ON cm.instance = w.id
            WHERE cm.id = :cmid AND ws.authorid = :authorid";
        $params = [
                'cmid' => $cm->id,
                'authorid' => $this->userid
            ];
        $submissionids = $DB->get_fieldset_sql($sql, $params);

        $this->cminfo = [
            'cmid' => $cm->id,
            'plugin' => 'mod_workshop',
            'status' => self::RESET_NOTHING
        ];

        // Add to log if there is user data.
        if ($submissionids) {
            $this->cminfo['msg'] = 'Contains user data';
        }
    }

    /**
     * Resets chat module
     *
     * @param stdClass $cm
     */
    private function reset_mod_chat(stdClass $cm): void {
        global $DB;

        $sql = "SELECT cmsg.id
            FROM {chat_messages} cmsg
            JOIN {chat} c ON cmsg.chatid = c.id
            JOIN {course_modules} cm ON cm.instance = c.id
            WHERE cm.id = :cmid AND cmsg.userid = :userid";
        $params = [
            'cmid' => $cm->id,
            'userid' => $this->userid
        ];
        $chatids = $DB->get_fieldset_sql($sql, $params);

        $this->cminfo = [
            'cmid' => $cm->instance,
            'plugin' => 'mod_chat',
            'status' => self::RESET_NOTHING
        ];

        // Add to log if there is user data.
        if ($chatids) {
            $this->cminfo['msg'] = 'Contains user data';
        }
    }

    /**
     * Resets individual wiki module
     *
     * @param stdClass $cm
     * @throws \dml_exception
     */
    private function reset_mod_wiki(stdClass $cm): void {
        global $DB;

        $status = self::RESET_NOTHING;
        $msg = '';

        $record = $DB->get_record('wiki', ['id' => $cm->instance]);
        if ((string)$record->wikimode === 'individual') {
            $this->reset_mod_using_privacy_api($cm, 'mod_wiki', \mod_wiki\privacy\provider::class);
            $status = self::RESET_ALL_DATA;
        }
        if ((string)$record->wikimode === 'collaborative') {
            $sql = "SELECT wp.id
            FROM {wiki_pages} wp
            JOIN {wiki_subwikis} wsw ON wsw.id = wp.subwikiid
            JOIN {wiki} w ON wsw.wikiid = w.id
            JOIN {course_modules} cm ON cm.instance = w.id
            WHERE cm.id = :cmid AND wp.userid = :userid AND w.wikimode = 'collaborative'";
            $params = [
                'cmid' => $cm->id,
                'userid' => $this->userid
            ];
            $ids = $DB->get_fieldset_sql($sql, $params);

            $status = self::RESET_NOTHING;
            // Add to log if there is user data.
            if ($ids) {
                $msg = 'Contains user data';
            }
        }

        $this->cminfo = [
            'cmid' => $cm->id,
            'plugin' => 'mod_wiki',
            'status' => $status,
            'msg' => $msg
        ];
    }

    /**
     * Delete grades
     *
     * @return int
     */
    private function delete_grades(): int {
        $rv = 0;
        if ($items = grade_item::fetch_all(array('courseid' => $this->courseid))) {
            foreach ($items as $item) {
                if ($grades = grade_grade::fetch_all(array('userid' => $this->userid, 'itemid' => $item->id))) {
                    foreach ($grades as $grade) {
                        $grade->delete();
                        $rv = self::RESET_GRADES;
                    }
                }
            }
        }
        return $rv;
    }

    /**
     * Resets completion for module
     *
     * @param stdClass $cm
     * @throws \dml_exception
     */
    private function reset_mod_completion(stdClass $cm): void {
        $course = get_course($this->courseid);
        $completion = new \completion_info($course);

        // Reset viewed.
        $modinfo = cm_info::create((object)array('id' => $cm->id, 'course' => $course->id), $this->userid);
        $this->reset_module_viewed($completion, $modinfo);
        // And reset completion, in case viewed is not a required condition.
        $completion->update_state($cm, COMPLETION_INCOMPLETE, $this->userid);
    }

    /**
     * Reset module viewed
     *
     * @param \completion_info $completion
     * @param cm_info $cm
     * @throws \moodle_exception
     */
    public function reset_module_viewed(\completion_info $completion, \cm_info $cm): void {
        if ((int)$cm->completionview === COMPLETION_VIEW_NOT_REQUIRED || !$completion->is_enabled($cm)) {
            return;
        }
        // Get current completion state.
        $data = $completion->get_data($cm, false, $this->userid);
        // If we haven't already viewed it, don't do anything.
        if ((int)$data->viewed === COMPLETION_NOT_VIEWED) {
            return;
        }
        // Change state, save it and update completion.
        $data->viewed = COMPLETION_NOT_VIEWED;
        $completion->internal_set_data($cm, $data);
        $completion->update_state($cm, COMPLETION_INCOMPLETE, $this->userid);
    }

    /**
     * Resets completions
     *
     * @throws \dml_exception
     */
    private function reset_course_completions(): int {
        global $DB;

        $reset = 0;
        $params = array('userid' => $this->userid, 'course' => $this->courseid);

        if ($DB->count_records('course_completions', $params) > 0) {
            $DB->delete_records('course_completions', $params);
            $reset = self::RESET_COURSE_COMPLETION;
        }
        if ($DB->count_records('course_completion_crit_compl', $params) > 0) {
            $DB->delete_records('course_completion_crit_compl', $params);
            $reset = self::RESET_COURSE_COMPLETION;
        }
        return $reset;
    }

    /**
     * Adds log to tool_wp_course_reset
     *
     * @param array $resetdata
     * @return bool|int
     */
    private function add_log(array $resetdata) {
        global $USER;

        // Prevent passing extra data.
        $insertdata = array_intersect_key($resetdata, [
            'courseid' => 1,
            'userid' => 1,
            'programid' => 1,
            'certificationid' => 1,
            'reason' => 1,
            'grade' => 1,
            'wascompleted' => 1,
            'resetstatus' => 1,
            'resetinfo' => 1,
        ]);

        $insertdata['userrequested'] = $USER->id;
        $insertdata['timecreated'] = time();

        $newcoursereset = new course_reset(0, (object)$insertdata);
        $reset = $newcoursereset->create();
        return $reset->get('id');
    }

    /**
     * Decode reset statuses for course modules into messages
     *
     * @param int $data
     * @return string
     */
    public static function decode_statuses(int $data): string {
        $statuses = [];

        if ($data & self::RESET_NOTHING) {
            $statuses[] = get_string('nothing', 'tool_wp');
        }
        if ($data & self::RESET_COURSE_COMPLETION) {
            $statuses[] = get_string('coursecompletion');
        }
        if ($data & self::RESET_GRADES) {
            $statuses[] = get_string('grades');
        }
        if ($data & self::RESET_OUTCOMES) {
            $statuses[] = get_string('outcomes', 'tool_wp');
        }
        if ($data & self::RESET_ACTIVITY_COMPLETION) {
            $statuses[] = get_string('activitycompletion', 'tool_wp');
        }
        if ($data & self::RESET_ALL_DATA) {
            $statuses[] = get_string('alldata', 'tool_wp');
        }
        if ($data & self::RESET_NOT_POSSIBLE) {
            $statuses[] = get_string('notpossible', 'tool_wp');
        }
        return implode(',', $statuses);
    }
}