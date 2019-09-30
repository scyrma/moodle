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
 * File containing tests for functions in course_reset class.
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/rating/lib.php');
require_once($CFG->dirroot . '/comment/lib.php');
require_once("$CFG->dirroot/grade/querylib.php");

/**
 * Tests for functions in course_reset
 *
 * @package    tool_wp
 * @covers     \tool_wp\course_reset
 * @covers     \tool_wp\course_reset_api
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_wp_course_reset_testcase extends advanced_testcase {

    /**
     * @var
     */
    private $course;
    /**
     * @var
     */
    private $user;
    /**
     * @var
     */
    private $teacher;
    /**
     * @var
     */
    private $completion;

    /**
     * Test setup.
     */
    public function setUp() {
        global $CFG;

        $CFG->enablecompletion = true;
        $this->resetAfterTest();
    }

    /**
     * Completes glossary module with user data
     *
     * @param stdClass $glossary
     * @throws coding_exception
     */
    public function complete_glossary(stdClass $glossary): void {
        /** @var mod_glossary_generator $glossarygenerator */
        $glossarygenerator = $this->getDataGenerator()->get_plugin_generator('mod_glossary');
        $cm = get_coursemodule_from_id('glossary', $glossary->cmid);
        $context = context_module::instance($cm->id);

        // Create two entries.
        $params = ['concept' => 'first user glossary entry', 'approved' => 1, 'userid' => $this->user->id];
        $ge1 = $glossarygenerator->create_content($glossary, $params);
        $params['concept'] = 'second user glossary entry';
        $ge2 = $glossarygenerator->create_content($glossary, $params);

        // Attach tags.
        core_tag_tag::set_item_tags('mod_glossary', 'glossary_entries', $ge1->id, $context, ['Blue', 'Moon']);
        core_tag_tag::set_item_tags('mod_glossary', 'glossary_entries', $ge2->id, $context, ['Green', 'Mars']);

        // As a teacher, rate student first entry.
        $this->setUser($this->teacher);
        $ratingoptions = new stdClass;
        $ratingoptions->context = $context;
        $ratingoptions->ratingarea = 'entry';
        $ratingoptions->component = 'mod_glossary';
        $ratingoptions->itemid  = $ge1->id;
        $ratingoptions->scaleid = 2;
        $ratingoptions->userid  = $this->user->id;
        $rating = new \rating($ratingoptions);
        $rating->update_rating(2);
        $this->setUser($this->user);

        // Mark as completed.
        $this->completion->update_state($cm, COMPLETION_COMPLETE, $this->user->id);
    }

    /**
     * Completes choice module with user data
     *
     * @param stdClass $choice
     * @throws coding_exception
     */
    public function complete_choice(stdClass $choice): void {
        $choicewithoptions = choice_get_choice($choice->id);
        $optionids = array_keys($choicewithoptions->option);
        $cm = get_coursemodule_from_instance('choice', $choice->id);
        choice_user_submit_response($optionids[2], $choice, $this->user->id, $this->course, $cm);
        $this->completion->update_state($cm, COMPLETION_COMPLETE, $this->user->id);
    }

    /**
     * Method for creating a submission.
     *
     * @param  assign  $assign The assign object
     * @param  stdClass  $user The user object
     * @param  string  $submissiontext Submission text
     * @param  integer $attemptnumber The attempt number
     * @return object A submission object.
     */
    protected function create_submission($assign, $user, $submissiontext, $attemptnumber = 0) {
        $submission = $assign->get_user_submission($user->id, true, $attemptnumber);
        $submission->onlinetext_editor = ['text' => $submissiontext, 'format' => FORMAT_MOODLE];
        $this->setUser($user);
        $notices = [];
        $assign->save_submission($submission, $notices);
        return $submission;
    }

    /**
     * Completes assign module with user data
     *
     * @param assign $assign
     * @throws coding_exception
     * @throws dml_exception
     */
    public function complete_assign(assign $assign): void {
        global $DB;

        // Create and grade some submissions from the students.
        $cm = $assign->get_course_module();
        $submission1 = $this->create_submission($assign, $this->user, 'My first submission');

        $this->setUser($this->teacher);

        // Override.
        $overridedata = new \stdClass();
        $overridedata->assignid = $assign->get_instance()->id;
        $overridedata->userid = $this->user->id;
        $overridedata->duedate = time();
        $DB->insert_record('assign_overrides', $overridedata);
        assign_update_events($assign);

        // Give the submission a grade.
        $grade1 = '54.00';
        $teachercommenttext = 'Comment on user 1 attempt 1.';
        $data = new \stdClass();
        $data->attemptnumber = 0;
        $data->grade = $grade1;
        $data->assignfeedbackcomments_editor = ['text' => $teachercommenttext, 'format' => FORMAT_MOODLE];
        $assign->save_grade($this->user->id, $data);

        $this->setUser($this->user);

        $this->completion->update_state($cm, COMPLETION_COMPLETE, $this->user->id);
    }

    /**
     * Completes data module with user data
     *
     * @param stdClass $data
     * @param stdClass $group
     * @throws coding_exception
     * @throws dml_exception
     */
    public function complete_data(stdClass $data, stdClass $group): void {
        global $DB;

        $cm = get_coursemodule_from_id('data', $data->cmid);

        $fieldtypes = array('checkbox', 'date', 'menu', 'multimenu', 'number', 'radiobutton', 'text', 'textarea', 'url');

        $count = 1;
        $this->getDataGenerator()->create_group_member(['userid' => $this->user->id, 'groupid' => $group->id]);
        // Creating test Fields with default parameter values.
        foreach ($fieldtypes as $fieldtype) {

            // Creating variables dynamically.
            $fieldname = 'field-' . $count;
            $record = new StdClass();
            $record->name = $fieldname;
            $record->type = $fieldtype;

            ${$fieldname} = $this->getDataGenerator()->get_plugin_generator('mod_data')->create_field($record, $data);

            $this->assertInstanceOf('data_field_' . $fieldtype, ${$fieldname});
            $count++;
        }
        $addtemplate = $DB->get_record('data', array('id' => $data->id), 'addtemplate');
        $addtemplate = $addtemplate->addtemplate;

        for ($i = 1; $i < $count; $i++) {
            $fieldname = 'field-' . $i;
            $this->assertTrue(strpos($addtemplate, '[[' . $fieldname . ']]') >= 0);
        }

        $fields = $DB->get_records('data_fields', array('dataid' => $data->id), 'id');

        $contents = array();
        $contents[] = array('opt1', 'opt2', 'opt3', 'opt4');
        $contents[] = '01-01-2037'; // It should be lower than 2038, to avoid failing on 32-bit windows.
        $contents[] = 'menu1';
        $contents[] = array('multimenu1', 'multimenu2', 'multimenu3', 'multimenu4');
        $contents[] = '12345';
        $contents[] = 'radioopt1';
        $contents[] = 'text for testing';
        $contents[] = '<p>text area testing<br /></p>';
        $contents[] = array('example.url', 'sampleurl');

        $count = 0;
        $fieldcontents = array();
        foreach ($fields as $fieldrecord) {
            $fieldcontents[$fieldrecord->id] = $contents[$count++];
        }

        $this->setUser($this->user);
        $this->getDataGenerator()->get_plugin_generator('mod_data')->create_entry($data,
            $fieldcontents, $group->id, ['Mice', 'Dogs']);

        $this->completion->update_state($cm, COMPLETION_COMPLETE, $this->user->id);
    }

    /**
     * Completes forum module with user data
     *
     * @param stdClass $forum
     * @throws coding_exception
     * @throws dml_exception
     */
    public function complete_forum(stdClass $forum): void {
        global $DB;

        $cm = get_coursemodule_from_id('forum', $forum->cmid);
        $forumgenerator = $this->getDataGenerator()->get_plugin_generator('mod_forum');

        // Create a discussion in the forum, and then add a post to that discussion.
        $record = new stdClass();
        $record->course = $this->course->id;
        $record->userid = $this->user->id;
        $record->forum = $forum->id;
        $discussion = $forumgenerator->create_discussion($record);

        // Retrieve the post which was created by create_discussion.
        $post = $DB->get_record('forum_posts', array('discussion' => $discussion->id));
        // Tag the post.
        $context = \context_module::instance($cm->id);
        \core_tag_tag::set_item_tags('mod_forum', 'forum_posts', $post->id, $context, ['example', 'tag']);

        $ratingoptions = (object) [
            'context' => $context,
            'component' => 'mod_forum',
            'ratingarea' => 'post',
            'itemid' => $post->id,
            'scaleid' => 2,
            'userid' => $this->user->id,
        ];
        $rating = new \rating($ratingoptions);
        $rating->update_rating(75);

        $this->completion->update_state($cm, COMPLETION_COMPLETE, $this->user->id);
    }

    /**
     * Completes scorm module with user data
     *
     * @param stdClass $scorm
     * @throws dml_exception
     */
    public function complete_scorm(stdClass $scorm): void {
        global $DB;

        // Insert one attempt.
        $newattempt = 'on';
        $mode = 'normal';
        $attempt = 1;
        scorm_check_mode($scorm, $newattempt, $attempt, $this->user->id, $mode);
        $scoes = scorm_get_scoes($scorm->id);
        $sco = array_pop($scoes);
        scorm_insert_track($this->user->id, $scorm->id, $sco->id, $attempt, 'cmi.core.lesson_status', 'completed');
        scorm_insert_track($this->user->id, $scorm->id, $sco->id, $attempt, 'cmi.score.min', '0');
        $now = time();
        $hacpsession = [
            'scormid' => $scorm->id,
            'attempt' => $attempt,
            'hacpsession' => random_string(20),
            'userid' => $this->user->id,
            'timecreated' => $now,
            'timemodified' => $now
        ];
        $DB->insert_record('scorm_aicc_session', $hacpsession);
    }

    /**
     * Completes lti module with user data
     *
     * @param stdClass $lti
     * @throws dml_exception
     */
    public function complete_lti(stdClass $lti): void {
        global $DB;

        $ltisubmissiondata = [
            'ltiid' => $lti->id,
            'userid' => $this->user->id,
            'datesubmitted' => time(),
            'dateupdated' => time(),
            'gradepercent' => 65,
            'originalgrade' => 70,
            'launchid' => 3,
            'state' => 1
        ];
        $DB->insert_record('lti_submission', (object)$ltisubmissiondata);
    }

    /**
     * Completes lesson module with user data
     *
     * @param stdClass $lesson
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function complete_lesson(stdClass $lesson): void {
        global $DB;

        $lessongenerator = $this->getDataGenerator()->get_plugin_generator('mod_lesson');
        $tfrecord = $lessongenerator->create_question_truefalse($lesson);

        // Now, do something in the lesson as student1.
        $this->setUser($this->user);
        mod_lesson_external::launch_attempt($lesson->id);
        $data = array(
            array(
                'name' => 'answerid',
                'value' => $DB->get_field('lesson_answers', 'id', array('pageid' => $tfrecord->id, 'jumpto' => -1)),
            ),
            array(
                'name' => '_qf__lesson_display_answer_form_truefalse',
                'value' => 1,
            )
        );
        mod_lesson_external::process_page($lesson->id, $tfrecord->id, $data);
        mod_lesson_external::finish_attempt($lesson->id);

        $override1 = (object)[
            'lessonid' => $lesson->id,
            'userid' => $this->user->id,
            'available' => 100,
            'deadline' => 120
        ];
        $DB->insert_record('lesson_overrides', $override1);
    }

    /**
     * Completes workshop module with user data
     *
     * @param stdClass $workshop
     * @throws coding_exception
     * @throws dml_exception
     */
    public function complete_workshop(stdClass $workshop): void {
        global $DB;

        $workshopgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshop');
        $DB->set_field('workshop', 'phase', 50, ['id' => $workshop->id]);
        $this->submission = $workshopgenerator->create_submission($workshop->id, $this->user->id);
        $this->assessment = $workshopgenerator->create_assessment($this->submission, $this->user->id, [
            'grade' => 92,
        ]);
    }

    /**
     * Completes chat module with user data
     *
     * @param stdClass $chat
     * @throws dml_exception
     */
    public function complete_chat(stdClass $chat): void {
        global $DB;

        $this->setUser($this->user);
        chat_login_user($chat->id, 'basic', 0, $this->course);
        $u1chat1a = $DB->get_record('chat_users', ['userid' => $this->user->id, 'chatid' => $chat->id, 'groupid' => 0]);

        $this->setUser($this->teacher);
        chat_login_user($chat->id, 'basic', 0, $this->course);
        $u2chat1a = $DB->get_record('chat_users', ['userid' => $this->teacher->id, 'chatid' => $chat->id, 'groupid' => 0]);
        $this->setUser($this->user);

        chat_send_chatmessage($u1chat1a, 'Ça va ?');
        chat_send_chatmessage($u2chat1a, 'Oui, et toi ?');
        chat_send_chatmessage($u1chat1a, 'Good, thanks');
        chat_send_chatmessage($u2chat1a, 'Today is sunny');
    }

    /**
     * Completes wiki module with user data
     *
     * @param stdClass $wiki
     * @throws coding_exception
     */
    public function complete_wiki(stdClass $wiki): void {
        $wikigenerator = $this->getDataGenerator()->get_plugin_generator('mod_wiki');

        $wikigenerator->create_first_page($wiki, ['content' => 'content']);
        $wikigenerator->create_page($wiki, ['content' => 'initial content']);
    }

    /**
     * Completes survey module with user data
     *
     * @param stdClass $survey
     * @throws coding_exception
     * @throws dml_exception
     */
    public function complete_survey(stdClass $survey): void {
        global $DB;

        // Create answer.
        $record = (object) [
            'survey' => $survey->id,
            'question' => 1,
            'userid' => $this->user->id,
            'answer1' => 'Answer 1',
            'answer2' => 'Answer 2',
            'time' => time()
        ];
        $DB->insert_record('survey_answers', $record);

        // Create Analysis.
        $record = (object) [
            'survey' => $survey->id,
            'userid' => $this->user->id,
            'notes' => 'Notes'
        ];
        $DB->insert_record('survey_analysis', $record);

        $cm = get_coursemodule_from_id('survey', $survey->cmid);
        $this->completion->update_state($cm, COMPLETION_COMPLETE, $this->user->id);
    }

    /**
     * Completes feedback module with user data
     *
     * @param stdClass $feedback
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function complete_feedback(stdClass $feedback): void {
        $fgenerator = $this->getDataGenerator()->get_plugin_generator('mod_feedback');

        $i1 = $fgenerator->create_item_numeric($feedback);
        $i2 = $fgenerator->create_item_multichoice($feedback);
        $answers = ['numeric_' . $i1->id => '1', 'multichoice_' . $i2->id => [1]];

        $modinfo = get_fast_modinfo($feedback->course);
        $cm = $modinfo->get_cm($feedback->cmid);

        $feedbackcompletion = new mod_feedback_completion($feedback, $cm, $feedback->course, false, null, null, $this->user->id);
        $feedbackcompletion->save_response_tmp((object) $answers);
        $feedbackcompletion->save_response();
    }

    /**
     * Completes quiz module with user data
     *
     * @param stdClass $quiz
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function complete_quiz(stdClass $quiz): void {
        global $DB;

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        quiz_add_quiz_question($question->id, $quiz);

        $quizobj = quiz::create($quiz->id, $this->user->id);

        // Set grade to pass.
        $item = grade_item::fetch(array('courseid' => $this->course->id, 'itemtype' => 'mod',
            'itemmodule' => 'quiz', 'iteminstance' => $quiz->id, 'outcomeid' => null));
        $item->gradepass = 80;
        $item->update();

        // Start the passing attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $this->user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = quiz_attempt::create($attempt->id);
        $tosubmit = array(1 => array('answer' => '3.14'));
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = quiz_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);

        // Create one override for the user.
        $DB->insert_record('quiz_overrides', (object)[
            'quiz' => $quiz->id,
            'userid' => $this->user->id,
            'timeclose' => 1300,
            'timelimit' => null,
        ]);
        $cm = get_coursemodule_from_id('quiz', $quiz->cmid);
        $this->completion->update_state($cm, COMPLETION_COMPLETE, $this->user->id);
    }

    /**
     * Test reset_course().
     */
    public function test_reset_course(): void {
        global $DB;

        // Add a course that supports completion.
        $this->course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $this->completion = new completion_info($this->course);

        // Create and enrol a user in the course.
        $this->user = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id, $studentrole->id);

        // Create and enrol a teacher.
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        $coursecontext = context_course::instance($this->course->id);
        assign_capability('mod/assign:grade', CAP_ALLOW, $teacherrole->id, $coursecontext->id, true);
        $this->teacher = $this->getDataGenerator()->create_user();
        role_assign($teacherrole->id, $this->teacher->id, $coursecontext);

        $this->setUser($this->user);
        $params = ['course' => $this->course->id];

        // Add a glossary module to the course.
        $glossary = $this->getDataGenerator()->create_module('glossary', $params, ['completion' => 1]);
        $this->complete_glossary($glossary);

        // At this point we should have 2 glossary entries, one rating and 4 tag instances.
        $count = $DB->count_records('glossary_entries', ['glossaryid' => $glossary->id, 'userid' => $this->user->id]);
        $this->assertEquals(2, $count);
        $ratingparams = ['component' => 'mod_glossary', 'ratingarea' => 'entry', 'userid' => $this->user->id];
        $ratingcount = $DB->count_records('rating', $ratingparams);
        $this->assertEquals(1, $ratingcount);
        $tagcount = $DB->count_records('tag_instance', ['component' => 'mod_glossary', 'itemtype' => 'glossary_entries']);
        $this->assertEquals(4, $tagcount);

        // Add a choice module to the course.
        $choice = $this->getDataGenerator()->create_module('choice', $params, ['completion' => 1]);
        $this->complete_choice($choice);

        // At this point we should have 1 answer.
        $records = $DB->get_records('choice_answers', ['choiceid' => $choice->id, 'userid' => $this->user->id]);
        $this->assertCount(1, $records);

        // Add a quiz module to the course.
        // Make a scale and an outcome.
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $scale = $this->getDataGenerator()->create_scale();
        $data = array('courseid' => $this->course->id,
            'fullname' => 'Team work',
            'shortname' => 'Team work',
            'scaleid' => $scale->id);
        $outcome = $this->getDataGenerator()->create_grade_outcome($data);
        // Make a quiz with the outcome on.
        $data = array('course' => $this->course->id,
            'outcome_'.$outcome->id => 1,
            'grade' => 100.0,
            'questionsperpage' => 0,
            'sumgrades' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,
            'completionpass' => 1);
        $quiz = $quizgenerator->create_instance($data);
        $this->complete_quiz($quiz);

        // At this point we should have 1 quiz attempt and 1 outcome.
        $cm = get_coursemodule_from_id('quiz', $quiz->cmid);
        $this->assertTrue(quiz_get_completion_state($this->course, $cm, $this->user->id, 'return'));
        $records = $DB->get_records('quiz_attempts', ['quiz' => $quiz->id, 'userid' => $this->user->id]);
        $this->assertCount(1, $records);

        // Add an assign module to the course.
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id], ['completion' => 1]);
        $cm = get_coursemodule_from_id('assign', $assign->cmid);
        $context = context_module::instance($cm->id);
        $assign = new assign($context, $cm, $this->course);
        $this->complete_assign($assign);

        // At this point we should have 1 submission, 1 grade and 1 override.
        $assignid = $assign->get_instance()->id;
        $records = $DB->get_records('assign_submission', ['assignment' => $assignid, 'userid' => $this->user->id]);
        $this->assertCount(1, $records);
        $records = $DB->get_records('assign_grades', ['assignment' => $assignid, 'userid' => $this->user->id]);
        $this->assertCount(1, $records);
        $records = $DB->get_records('assign_overrides', ['assignid' => $assignid, 'userid' => $this->user->id]);
        $this->assertCount(1, $records);

        // Add data module to the course.
        $data = $this->getDataGenerator()->create_module('data', $params, ['completion' => 1]);
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'groupA']);
        $this->complete_data($data, $group);

        // At this point we should have 1 data record and 9 contents.
        $this->assertEquals(1, $DB->count_records('data_records', ['dataid' => $data->id]));
        $this->assertEquals(9, $DB->count_records('data_content'));
        $entry = $DB->get_record('data_records', ['dataid' => $data->id]);
        $this->assertEquals($entry->groupid, $group->id);

        // Add forum module to the course.
        $forum = $this->getDataGenerator()->create_module('forum', $params, ['completion' => 1]);
        $this->complete_forum($forum);

        // At this point we should have 1 rating, 2 tags, 1 discussion.
        $params = ['component' => 'mod_forum', 'ratingarea' => 'post', 'userid' => $this->user->id];
        $this->assertEquals(1, $DB->count_records('rating', $params));
        $params = ['course' => $this->course->id, 'userid' => $this->user->id];
        $this->assertEquals(1, $DB->count_records('forum_discussions', $params));
        $this->assertEquals(2, $DB->count_records('tag_instance', ['component' => 'mod_forum']));

        // Add survey module to the course.
        $survey = $this->getDataGenerator()->create_module('survey', $params, ['completion' => 1]);
        $this->complete_survey($survey);

        // At this point we should have 1 answer.
        $records = $DB->get_records('survey_answers', ['survey' => $survey->id, 'userid' => $this->user->id]);
        $this->assertCount(1, $records);

        // Add feedback module to the course.
        $feedback = $this->getDataGenerator()->create_module('feedback', $params, ['completion' => 1]);
        $this->complete_feedback($feedback);

        // At this point we should have 1 completed.
        $records = $DB->get_records('feedback_completed', ['feedback' => $feedback->id, 'userid' => $this->user->id]);
        $this->assertCount(1, $records);

        // Add scorm module to the course.
        $scorm = $this->getDataGenerator()->create_module('scorm', $params, ['completion' => 1]);
        $this->complete_scorm($scorm);

        // At this point we should have 1 attempt.
        $records = $DB->get_records('scorm_aicc_session', ['scormid' => $scorm->id, 'userid' => $this->user->id]);
        $this->assertCount(1, $records);

        // Add lti module to the course.
        $lti = $this->getDataGenerator()->create_module('lti', $params);
        $this->complete_lti($lti);

        // At this point we should have 1 submission.
        $records = $DB->get_records('lti_submission', ['ltiid' => $lti->id, 'userid' => $this->user->id]);
        $this->assertCount(1, $records);

        // Add lesson module to the course.
        $lesson = $this->getDataGenerator()->create_module('lesson', [
            'course' => $this->course->id,
            'completion' => 1,
            'completionendreached' => 1,
            'completiontimespent' => 3600
        ]);
        $this->complete_lesson($lesson);

        // At this point we should have 1 attempt, 1 grade and 1 override.
        $lessonparams = ['lessonid' => $lesson->id, 'userid' => $this->user->id];
        $this->assertEquals(1, $DB->count_records('lesson_attempts', $lessonparams));
        $this->assertEquals(1, $DB->count_records('lesson_grades', $lessonparams));
        $this->assertEquals(1, $DB->count_records('lesson_overrides', $lessonparams));

        // Add workshop module to the course.
        $workshop = $this->getDataGenerator()->create_module('workshop', $params, ['completion' => 1]);
        $this->complete_workshop($workshop);

        // At this point we should have 1 submission.
        $wshopparams = ['workshopid' => $workshop->id, 'authorid' => $this->user->id];
        $this->assertEquals(1, $DB->count_records('workshop_submissions', $wshopparams));

        // Add chat module to the course.
        $chat = $this->getDataGenerator()->create_module('chat', $params, ['completion' => 1]);
        $this->complete_chat($chat);

        // At this point we should have messages in the chat.
        $DB->record_exists('chat_messages', ['chatid' => $chat->id, 'userid' => $this->user->id]);
        $DB->record_exists('chat_messages', ['chatid' => $chat->id, 'userid' => $this->teacher->id]);

        // Add 2 wiki modules to the course (one individual and one collaborative).
        $wikiparams = ['course' => $this->course->id, 'wikimode' => 'collaborative'];
        $wiki = $this->getDataGenerator()->create_module('wiki', $wikiparams);
        $wikiparams = ['course' => $this->course->id, 'wikimode' => 'individual'];
        $wiki2 = $this->getDataGenerator()->create_module('wiki', $wikiparams);
        $this->complete_wiki($wiki);
        $this->complete_wiki($wiki2);

        // At this point we should have pages on the wikis.
        $this->assertEquals(4, $DB->count_records('wiki_pages', ['userid' => $this->user->id]));
        // Wiki collaborative module.
        $wikiparams = ['wikiid' => $wiki->id, 'userid' => $this->user->id];
        $this->assertEquals(0, $DB->count_records('wiki_subwikis', $wikiparams));
        // Wiki2 individual module.
        $wikiparams = ['wikiid' => $wiki2->id, 'userid' => $this->user->id];
        $this->assertEquals(1, $DB->count_records('wiki_subwikis', $wikiparams));

        // Add an assign activity that does *not* use completion.
        $this->getDataGenerator()->create_module('assign', array('course' => $this->course->id));

        // Check that course has all modules added.
        $this->assertEquals(16, $DB->count_records('course_modules', ['course' => $this->course->id]));

        // Get current course grades.
        $result = grade_get_course_grades($this->course->id, $this->user->id);
        $grd = $result->grades[$this->user->id];
        $originalgrade = ($grd->grade === null) ? 0 : $grd->grade;
        $this->assertGreaterThan(0, $originalgrade);

        $this->setUser($this->teacher);
        // Reset the course for this user.
        $creset = new \tool_wp\course_reset_api($this->course->id, $this->user->id);
        $resetparams = [
          'programid' => 33,
          'certificationid' => 44,
          'reason' => 'Testing course reset :)'
        ];

        $sink = $this->redirectEvents();
        $creset->reset_course($resetparams);
        $events = $sink->get_events();
        $sink->close();

        // Check course progress is zero.
        $this->assertEquals('0', \core_completion\progress::get_course_progress_percentage($this->course, $this->user->id));

        // Check that at least there are 17 events.
        $this->assertGreaterThan(17, $events);

        // Choice module.
        $params = ['choiceid' => $choice->id, 'userid' => $this->user->id];
        $this->assertCount(0, $DB->get_records('choice_answers', $params));

        // Quiz module.
        $params = ['quiz' => $quiz->id, 'userid' => $this->user->id];
        $this->assertCount(1, $DB->get_records('quiz', ['course' => $this->course->id]));
        $this->assertCount(0, $DB->get_records('quiz_attempts', $params));
        $this->assertCount(0, $DB->get_records('quiz_grades', $params));
        $this->assertCount(0, $DB->get_records('quiz_overrides', $params));

        // Survey module.
        $params = ['survey' => $survey->id, 'userid' => $this->user->id];
        $this->assertCount(1, $DB->get_records('survey', ['course' => $this->course->id]));
        $this->assertCount(0, $DB->get_records('survey_answers', $params));
        $this->assertCount(0, $DB->get_records('survey_analysis', $params));

        // Glossary module.
        $count = $DB->count_records('glossary_entries', ['glossaryid' => $glossary->id, 'userid' => $this->user->id]);
        $this->assertEquals(0, $count);
        $tagcount = $DB->count_records('tag_instance', ['component' => 'mod_glossary', 'itemtype' => 'glossary_entries']);
        $this->assertEquals(0, $tagcount);
        $params = ['component' => 'mod_glossary', 'ratingarea' => 'entry', 'userid' => $this->user->id];
        $ratingcount = $DB->count_records('rating', $params);
        $this->assertEquals(0, $ratingcount);

        // Assign module.
        $assignid = $assign->get_instance()->id;
        $count = $DB->count_records('assign_submission', ['assignment' => $assignid, 'userid' => $this->user->id]);
        $this->assertEquals(0, $count);
        $count = $DB->count_records('assign_grades', ['assignment' => $assignid, 'userid' => $this->user->id]);
        $this->assertEquals(0, $count);
        $count = $DB->count_records('assign_overrides', ['assignid' => $assignid, 'userid' => $this->user->id]);
        $this->assertEquals(0, $count);

        // Data module.
        $sql = "
            SELECT dr.id, dr.dataid
            FROM {data_records} dr
            JOIN {data} d ON dr.dataid = d.id
            WHERE d.course = ? AND dr.userid = ? AND d.id = ?
        ";
        $rs = $DB->get_recordset_sql($sql, [$this->course->id, $this->user->id, $data->id]);
        $this->assertCount(0, $rs);

        // Forum module.
        $params = ['component' => 'mod_forum', 'ratingarea' => 'post', 'userid' => $this->user->id];
        $this->assertEquals(0, $DB->count_records('rating', $params));

        // Lesson module.
        $params = ['lessonid' => $lesson->id, 'userid' => $this->user->id];
        $this->assertEquals(0, $DB->count_records('lesson_attempts', $params));
        $this->assertEquals(0, $DB->count_records('lesson_branch', $params));
        $this->assertEquals(0, $DB->count_records('lesson_grades', $params));
        $this->assertEquals(0, $DB->count_records('lesson_overrides', $params));
        $this->assertEquals(0, $DB->count_records('lesson_timer', $params));

        // Wiki module.
        $params = ['userid' => $this->user->id];
        $this->assertEquals(2, $DB->count_records('wiki_pages', $params));
        // Wiki collaborative module.
        $params = ['wikiid' => $wiki->id, 'userid' => $this->user->id];
        $this->assertEquals(0, $DB->count_records('wiki_subwikis', $params));
        // Wiki2 individual module.
        $params = ['wikiid' => $wiki2->id, 'userid' => $this->user->id];
        $this->assertEquals(0, $DB->count_records('wiki_subwikis', $params));

        // Chat module. Don't delete user chat messages.
        $params = ['chatid' => $chat->id, 'userid' => $this->user->id];
        $this->assertEquals(3, $DB->count_records('chat_messages', $params));

        // LTI module.
        $this->assertCount(0, $DB->get_records('lti_submission'));

        // Feddback module.
        $records = $DB->get_records('feedback_completed', ['feedback' => $feedback->id, 'userid' => $this->user->id]);
        $this->assertCount(0, $records);
        $records = $DB->get_records('feedback_completedtmp', ['feedback' => $feedback->id, 'userid' => $this->user->id]);
        $this->assertCount(0, $records);

        // Scorm module.
        $records = $DB->get_records('scorm_aicc_session', ['scormid' => $scorm->id, 'userid' => $this->user->id]);
        $this->assertCount(0, $records);

        // Workshop module. Don't delete anything.
        $records = $DB->get_records('workshop_submissions', ['workshopid' => $workshop->id, 'authorid' => $this->user->id]);
        $this->assertCount(1, $records);

        // Check tool_wp_course_reset table.
        $params = ['courseid' => $this->course->id, 'userid' => $this->user->id];
        $record = $DB->get_record('tool_wp_course_reset', $params);
        $resetinfo = json_decode($record->resetinfo);
        $this->assertIsArray($resetinfo);
        $this->assertNotEmpty($record->resetinfo);
        $this->assertEquals($originalgrade, $record->grade);
        $this->assertEquals(33, $record->programid);
        $this->assertEquals(44, $record->certificationid);
        $this->assertEquals('Testing course reset :)', $record->reason);
        $this->assertEquals(0, $record->wascompleted);
    }
}