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
 * File that contains program progress testcase class.
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019  Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\api;
use tool_program\constants;
use tool_program\event\program_completed;
use tool_program\persistent\program_set;
use tool_program\program_tree_progress;

defined('MOODLE_INTERNAL') || die();

/**
 * Program progress tests.
 *
 * @covers     \tool_program\program_tree_progress
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019  Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_progress_testcase extends advanced_testcase {
    /**
     * @var tool_program_generator
     */
    protected $generator;

    /**
     * setUp.
     */
    public function setUp() {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->resetAfterTest();
    }

    /**
     * Test get program tree progress.
     */
    public function test_get_program_progress(): void {
        global $CFG;
        $CFG->enablecompletion = true;
        self::setAdminUser();
        $user = self::getDataGenerator()->create_user();
        $program = $this->generator->generate_program_with_base_set();
        $baseset = $program->get_base_set();

        // Items within the base set.
        $course11 = self::getDataGenerator()->create_course(['enablecompletion' => true]);
        $assign11 = self::getDataGenerator()->create_module('assign', ['course' => $course11->id], ['completion' => 1]);
        $programcourse11 = $this->generator->add_course_to_set($course11->id, $baseset->get('id'), 1);
        $this->generator->enable_program_enrol_instance($programcourse11);
        $childset12 = $this->generator->generate_set((object) [
            'name' => 'Child set 12',
            'programid' => $program->get('id'),
            'parent' => $baseset->get('id'),
            'completioncriteria' => program_set::COMPLETION_ALL_IN_ANY_ORDER,
            'completionatleast' => 1,
            'sortorder' => 2,
        ]);
        $course13 = self::getDataGenerator()->create_course(['enablecompletion' => true]);
        $assign13 = self::getDataGenerator()->create_module('assign', ['course' => $course13->id], ['completion' => 1]);
        $programcourse13 = $this->generator->add_course_to_set($course13->id, $baseset->get('id'), 3);
        $this->generator->enable_program_enrol_instance($programcourse13);
        $childset21 = $this->generator->generate_set((object) [
            'name' => 'Child set 21',
            'programid' => $program->get('id'),
            'parent' => $childset12->get('id'),
            'completioncriteria' => program_set::COMPLETION_AT_LEAST,
            'completionatleast' => 1,
            'sortorder' => 1,
        ]);
        $course31 = self::getDataGenerator()->create_course(['enablecompletion' => true]);
        $assign31 = self::getDataGenerator()->create_module('assign', ['course' => $course31->id], ['completion' => 1]);
        $programcourse31 = $this->generator->add_course_to_set($course31->id, $childset21->get('id'), 1);
        $this->generator->enable_program_enrol_instance($programcourse31);
        $course32 = self::getDataGenerator()->create_course(['enablecompletion' => true]);
        $assign32 = self::getDataGenerator()->create_module('assign', ['course' => $course32->id], ['completion' => 1]);
        $programcourse32 = $this->generator->add_course_to_set($course32->id, $childset21->get('id'), 2);
        $this->generator->enable_program_enrol_instance($programcourse32);
        $programcourse22 = $this->generator->add_course_to_set($course13->id, $childset12->get('id'), 2);
        $this->generator->enable_program_enrol_instance($programcourse22);
        // Allocate user to program.
        $programuser = $this->generator->allocate_user_to_program($program->get('id'), $user->id);

        /*
         * Initial situation:
         *                                          Completion                      Locked
         * Program contents                         criteria            Progress    status
         * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
         * 1 Base set                               All in order        0%          Unlocked
         *      1 Programcourse11 / Course11        -                   0%          Unlocked
         *      2 Childset12                        All in any order    0%          Locked
         *          1 Childset21                    At least 1          0%          Locked
         *              1 Programcourse31/Course31  -                   0%          Locked
         *              1 Programcourse32/Course32  -                   0%          Locked
         *          2 Programcourse22/Course13      -                   0%          Locked
         *      3 Programcourse13/Course13          -                   0%          Locked
         */

        // Enrol user to programcourse11.
        $this->generator->enrol_user_to_program_course($programcourse11, $programuser);

        // Check items progress considering that the user has not finished anything within any course.
        $programtreeprogress = new program_tree_progress($program, $user->id);

        // Check ongoing course id.
        $this->assertEquals($course11->id, $programtreeprogress->get_ongoing_courseid());

        // Base set.
        $item = $programtreeprogress->get_baseset();
        $this->assertEquals(3, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        $this->assertEquals(4, $item->weight);
        $this->assertEquals([
            ['completion' => 0, 'weight' => 1],
            ['completion' => 0, 'weight' => 2],
            ['completion' => 0, 'weight' => 1],
        ], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(null, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 11.
        $item = $programtreeprogress->get_baseset()->items[0];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(true, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Child set 12.
        $item = $programtreeprogress->get_baseset()->items[1];
        $this->assertEquals(2, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        $this->assertEquals(2, $item->weight);
        $this->assertEquals([
            ['completion' => 0, 'weight' => 1],
            ['completion' => 0, 'weight' => 1],
        ], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(null, $item->isenrolled);
        $this->assertEquals(false, $item->isunlocked);

        // Child set 21.
        $item = $programtreeprogress->get_baseset()->items[1]->items[0];
        $this->assertEquals(2, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        // At least 1 completion criteria => sum first 1 weight from the completion-weights array ordered by weight.
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([
            ['completion' => 0, 'weight' => 1],
            ['completion' => 0, 'weight' => 1],
        ], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(null, $item->isenrolled);
        $this->assertEquals(false, $item->isunlocked);

        // Program course 31.
        $item = $programtreeprogress->get_baseset()->items[1]->items[0]->items[0];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(false, $item->isenrolled);
        $this->assertEquals(false, $item->isunlocked);

        // Program course 32.
        $item = $programtreeprogress->get_baseset()->items[1]->items[0]->items[1];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(false, $item->isenrolled);
        $this->assertEquals(false, $item->isunlocked);

        // Program course 22.
        $item = $programtreeprogress->get_baseset()->items[1]->items[1];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(false, $item->isenrolled);
        $this->assertEquals(false, $item->isunlocked);

        // Program course 13.
        $item = $programtreeprogress->get_baseset()->items[2];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(false, $item->isenrolled);
        $this->assertEquals(false, $item->isunlocked);

        // Complete course11 and re-check all progress data considering the new situation.
        $cmassign = get_coursemodule_from_id('assign', $assign11->cmid);
        $completion = new completion_info($course11);
        $completion->update_state($cmassign, COMPLETION_COMPLETE, $user->id);
        $ccompletion = new completion_completion(['course' => $course11->id, 'userid' => $user->id]);
        $ccompletion->mark_complete();
        $programtreeprogress = new program_tree_progress($program, $user->id);

        /*
         * Current situation:
         *                                          Completion                      Locked
         * Program contents                         criteria            Progress    status
         * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
         * 1 Base set                               All in order        -> 25%      Unlocked
         *      1 Programcourse11 / Course11        -                   -> 100%     Unlocked
         *      2 Childset12                        All in any order    0%          -> Unlocked
         *          1 Childset21                    At least 1          0%          -> Unlocked
         *              1 Programcourse31/Course31  -                   0%          -> Unlocked
         *              1 Programcourse32/Course32  -                   0%          -> Unlocked
         *          2 Programcourse22/Course13      -                   0%          -> Unlocked
         *      3 Programcourse13/Course13          -                   0%          Locked
         */

        // Check ongoing course id.
        $this->assertEquals($course31->id, $programtreeprogress->get_ongoing_courseid());

        // Base set.
        $item = $programtreeprogress->get_baseset();
        $this->assertEquals(3, $item->totalitems);
        $this->assertEquals(1, $item->completeditems);
        $this->assertEquals(1, $item->completion);
        $this->assertEquals(4, $item->weight);
        $this->assertEquals([
            ['completion' => 1, 'weight' => 1],
            ['completion' => 0, 'weight' => 2],
            ['completion' => 0, 'weight' => 1],
        ], $item->childrencompletionweightpairs);
        $this->assertEquals(25, $item->progresspercentage); // 1 completion / 4 weight => 25%.
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(null, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 11.
        $item = $programtreeprogress->get_baseset()->items[0];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(1, $item->completeditems);
        $this->assertEquals(1, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(100, $item->progresspercentage);
        $this->assertEquals(true, $item->iscompleted);
        $this->assertEquals(true, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Child set 12.
        $item = $programtreeprogress->get_baseset()->items[1];
        $this->assertEquals(2, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        $this->assertEquals(2, $item->weight);
        $this->assertEquals([
            ['completion' => 0, 'weight' => 1],
            ['completion' => 0, 'weight' => 1],
        ], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(null, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Child set 21.
        $item = $programtreeprogress->get_baseset()->items[1]->items[0];
        $this->assertEquals(2, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([
            ['completion' => 0, 'weight' => 1],
            ['completion' => 0, 'weight' => 1],
        ], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(null, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 31.
        $item = $programtreeprogress->get_baseset()->items[1]->items[0]->items[0];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(false, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 32.
        $item = $programtreeprogress->get_baseset()->items[1]->items[0]->items[1];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(false, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 22.
        $item = $programtreeprogress->get_baseset()->items[1]->items[1];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(false, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 13.
        $item = $programtreeprogress->get_baseset()->items[2];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(false, $item->isenrolled);
        $this->assertEquals(false, $item->isunlocked);

        // Complete course32 and re-check all progress data considering the new situation.
        $this->generator->enrol_user_to_program_course($programcourse32, $programuser);
        $cmassign = get_coursemodule_from_id('assign', $assign32->cmid);
        $completion = new completion_info($course32);
        $completion->update_state($cmassign, COMPLETION_COMPLETE, $user->id);
        $ccompletion = new completion_completion(['course' => $course32->id, 'userid' => $user->id]);
        $ccompletion->mark_complete();
        $programtreeprogress = new program_tree_progress($program, $user->id);

        /*
         * Current situation:
         *                                          Completion                      Locked
         * Program contents                         criteria            Progress    status
         * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
         * 1 Base set                               All in order        -> 50%      Unlocked
         *      1 Programcourse11 / Course11        -                   100%        Unlocked
         *      2 Childset12                        All in any order    -> 50%      Unlocked
         *          1 Childset21                    At least 1          -> 100%     Unlocked
         *              1 Programcourse31/Course31  -                   0%          Unlocked
         *              1 Programcourse32/Course32  -                   -> 100%     Unlocked
         *          2 Programcourse22/Course13      -                   0%          Unlocked
         *      3 Programcourse13/Course13          -                   0%          Locked
         */

        // Check ongoing course id.
        $this->assertEquals($course31->id, $programtreeprogress->get_ongoing_courseid());

        // Base set.
        $item = $programtreeprogress->get_baseset();
        $this->assertEquals(3, $item->totalitems);
        $this->assertEquals(1, $item->completeditems);
        $this->assertEquals(2, $item->completion);
        $this->assertEquals(4, $item->weight);
        $this->assertEquals([
            ['completion' => 1, 'weight' => 1],
            ['completion' => 1, 'weight' => 2],
            ['completion' => 0, 'weight' => 1],
        ], $item->childrencompletionweightpairs);
        $this->assertEquals(50, $item->progresspercentage); // 2 completion / 4 weight => 50%.
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(null, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 11.
        $item = $programtreeprogress->get_baseset()->items[0];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(1, $item->completeditems);
        $this->assertEquals(1, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(100, $item->progresspercentage);
        $this->assertEquals(true, $item->iscompleted);
        $this->assertEquals(true, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Child set 12.
        $item = $programtreeprogress->get_baseset()->items[1];
        $this->assertEquals(2, $item->totalitems);
        $this->assertEquals(1, $item->completeditems);
        $this->assertEquals(1, $item->completion);
        $this->assertEquals(2, $item->weight);
        $this->assertEquals([
            ['completion' => 1, 'weight' => 1],
            ['completion' => 0, 'weight' => 1],
        ], $item->childrencompletionweightpairs);
        $this->assertEquals(50, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(null, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Child set 21.
        $item = $programtreeprogress->get_baseset()->items[1]->items[0];
        $this->assertEquals(2, $item->totalitems);
        $this->assertEquals(1, $item->completeditems);
        $this->assertEquals(1, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([
            ['completion' => 1, 'weight' => 1],
            ['completion' => 0, 'weight' => 1],
        ], $item->childrencompletionweightpairs);
        $this->assertEquals(100, $item->progresspercentage);
        $this->assertEquals(true, $item->iscompleted);
        $this->assertEquals(null, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 31.
        $item = $programtreeprogress->get_baseset()->items[1]->items[0]->items[0];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(false, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 32.
        $item = $programtreeprogress->get_baseset()->items[1]->items[0]->items[1];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(1, $item->completeditems);
        $this->assertEquals(1, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(100, $item->progresspercentage);
        $this->assertEquals(true, $item->iscompleted);
        $this->assertEquals(true, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 22.
        $item = $programtreeprogress->get_baseset()->items[1]->items[1];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(false, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 13.
        $item = $programtreeprogress->get_baseset()->items[2];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(false, $item->isenrolled);
        $this->assertEquals(false, $item->isunlocked);

        // Complete course31 and re-check all progress data considering the new situation.
        $this->generator->enrol_user_to_program_course($programcourse31, $programuser);
        $cmassign = get_coursemodule_from_id('assign', $assign31->cmid);
        $completion = new completion_info($course31);
        $completion->update_state($cmassign, COMPLETION_COMPLETE, $user->id);
        $ccompletion = new completion_completion(['course' => $course31->id, 'userid' => $user->id]);
        $ccompletion->mark_complete();
        $programtreeprogress = new program_tree_progress($program, $user->id);

        /*
         * Current situation:
         *                                          Completion                      Locked
         * Program contents                         criteria            Progress    status
         * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
         * 1 Base set                               All in order        50%         Unlocked
         *      1 Programcourse11 / Course11        -                   100%        Unlocked
         *      2 Childset12                        All in any order    50%         Unlocked
         *          1 Childset21                    At least 1          100%        Unlocked
         *              1 Programcourse31/Course31  -                   -> 100%     Unlocked
         *              1 Programcourse32/Course32  -                   100%        Unlocked
         *          2 Programcourse22/Course13      -                   0%          Unlocked
         *      3 Programcourse13/Course13          -                   0%          Locked
         */

        // Check ongoing course id.
        $this->assertEquals($course13->id, $programtreeprogress->get_ongoing_courseid());

        // Base set.
        $item = $programtreeprogress->get_baseset();
        $this->assertEquals(3, $item->totalitems);
        $this->assertEquals(1, $item->completeditems);
        $this->assertEquals(2, $item->completion);
        $this->assertEquals(4, $item->weight);
        $this->assertEquals([
            ['completion' => 1, 'weight' => 1],
            ['completion' => 1, 'weight' => 2],
            ['completion' => 0, 'weight' => 1],
        ], $item->childrencompletionweightpairs);
        $this->assertEquals(50, $item->progresspercentage); // 2 completion / 4 weight => 50%.
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(null, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 11.
        $item = $programtreeprogress->get_baseset()->items[0];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(1, $item->completeditems);
        $this->assertEquals(1, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(100, $item->progresspercentage);
        $this->assertEquals(true, $item->iscompleted);
        $this->assertEquals(true, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Child set 12.
        $item = $programtreeprogress->get_baseset()->items[1];
        $this->assertEquals(2, $item->totalitems);
        $this->assertEquals(1, $item->completeditems);
        $this->assertEquals(1, $item->completion);
        $this->assertEquals(2, $item->weight);
        $this->assertEquals([
            ['completion' => 1, 'weight' => 1],
            ['completion' => 0, 'weight' => 1],
        ], $item->childrencompletionweightpairs);
        $this->assertEquals(50, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(null, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Child set 21.
        $item = $programtreeprogress->get_baseset()->items[1]->items[0];
        $this->assertEquals(2, $item->totalitems);
        $this->assertEquals(2, $item->completeditems);
        $this->assertEquals(1, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([
            ['completion' => 1, 'weight' => 1],
            ['completion' => 1, 'weight' => 1],
        ], $item->childrencompletionweightpairs);
        $this->assertEquals(100, $item->progresspercentage);
        $this->assertEquals(true, $item->iscompleted);
        $this->assertEquals(null, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 31.
        $item = $programtreeprogress->get_baseset()->items[1]->items[0]->items[0];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(1, $item->completeditems);
        $this->assertEquals(1, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(100, $item->progresspercentage);
        $this->assertEquals(true, $item->iscompleted);
        $this->assertEquals(true, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 32.
        $item = $programtreeprogress->get_baseset()->items[1]->items[0]->items[1];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(1, $item->completeditems);
        $this->assertEquals(1, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(100, $item->progresspercentage);
        $this->assertEquals(true, $item->iscompleted);
        $this->assertEquals(true, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 22.
        $item = $programtreeprogress->get_baseset()->items[1]->items[1];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(false, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 13.
        $item = $programtreeprogress->get_baseset()->items[2];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(0, $item->completeditems);
        $this->assertEquals(0, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(0, $item->progresspercentage);
        $this->assertEquals(false, $item->iscompleted);
        $this->assertEquals(false, $item->isenrolled);
        $this->assertEquals(false, $item->isunlocked);

        // Complete course22 and re-check all progress data considering the new situation.
        $this->generator->enrol_user_to_program_course($programcourse22, $programuser);
        $cmassign = get_coursemodule_from_id('assign', $assign13->cmid);
        $completion = new completion_info($course13);
        $completion->update_state($cmassign, COMPLETION_COMPLETE, $user->id);
        $ccompletion = new completion_completion(['course' => $course31->id, 'userid' => $user->id]);
        $ccompletion->mark_complete();
        $programtreeprogress = new program_tree_progress($program, $user->id);

        /*
         * Current situation:
         *                                          Completion                      Locked
         * Program contents                         criteria            Progress    status
         * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
         * 1 Base set                               All in order        -> 100%     Unlocked
         *      1 Programcourse11 / Course11        -                   100%        Unlocked
         *      2 Childset12                        All in any order    -> 100%     Unlocked
         *          1 Childset21                    At least 1          100%        Unlocked
         *              1 Programcourse31/Course31  -                   100%        Unlocked
         *              1 Programcourse32/Course32  -                   100%        Unlocked
         *          2 Programcourse22/Course13      -                   -> 100%     Unlocked
         *      3 Programcourse13/Course13          -                   -> 100%     -> Unlocked
         */

        // Base set.
        $item = $programtreeprogress->get_baseset();
        $this->assertEquals(3, $item->totalitems);
        $this->assertEquals(3, $item->completeditems);
        $this->assertEquals(4, $item->completion);
        $this->assertEquals(4, $item->weight);
        $this->assertEquals([
            ['completion' => 1, 'weight' => 1],
            ['completion' => 2, 'weight' => 2],
            ['completion' => 1, 'weight' => 1],
        ], $item->childrencompletionweightpairs);
        $this->assertEquals(100, $item->progresspercentage); // 4 completion / 4 weight => 100%.
        $this->assertEquals(true, $item->iscompleted);
        $this->assertEquals(null, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 11.
        $item = $programtreeprogress->get_baseset()->items[0];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(1, $item->completeditems);
        $this->assertEquals(1, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(100, $item->progresspercentage);
        $this->assertEquals(true, $item->iscompleted);
        $this->assertEquals(true, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Child set 12.
        $item = $programtreeprogress->get_baseset()->items[1];
        $this->assertEquals(2, $item->totalitems);
        $this->assertEquals(2, $item->completeditems);
        $this->assertEquals(2, $item->completion);
        $this->assertEquals(2, $item->weight);
        $this->assertEquals([
            ['completion' => 1, 'weight' => 1],
            ['completion' => 1, 'weight' => 1],
        ], $item->childrencompletionweightpairs);
        $this->assertEquals(100, $item->progresspercentage);
        $this->assertEquals(true, $item->iscompleted);
        $this->assertEquals(null, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Child set 21.
        $item = $programtreeprogress->get_baseset()->items[1]->items[0];
        $this->assertEquals(2, $item->totalitems);
        $this->assertEquals(2, $item->completeditems);
        $this->assertEquals(1, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([
            ['completion' => 1, 'weight' => 1],
            ['completion' => 1, 'weight' => 1],
        ], $item->childrencompletionweightpairs);
        $this->assertEquals(100, $item->progresspercentage);
        $this->assertEquals(true, $item->iscompleted);
        $this->assertEquals(null, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 31.
        $item = $programtreeprogress->get_baseset()->items[1]->items[0]->items[0];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(1, $item->completeditems);
        $this->assertEquals(1, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(100, $item->progresspercentage);
        $this->assertEquals(true, $item->iscompleted);
        $this->assertEquals(true, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 32.
        $item = $programtreeprogress->get_baseset()->items[1]->items[0]->items[1];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(1, $item->completeditems);
        $this->assertEquals(1, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(100, $item->progresspercentage);
        $this->assertEquals(true, $item->iscompleted);
        $this->assertEquals(true, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 22.
        $item = $programtreeprogress->get_baseset()->items[1]->items[1];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(1, $item->completeditems);
        $this->assertEquals(1, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(100, $item->progresspercentage);
        $this->assertEquals(true, $item->iscompleted);
        $this->assertEquals(true, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);

        // Program course 13.
        $item = $programtreeprogress->get_baseset()->items[2];
        $this->assertEquals(1, $item->totalitems);
        $this->assertEquals(1, $item->completeditems);
        $this->assertEquals(1, $item->completion);
        $this->assertEquals(1, $item->weight);
        $this->assertEquals([], $item->childrencompletionweightpairs);
        $this->assertEquals(100, $item->progresspercentage);
        $this->assertEquals(true, $item->iscompleted);
        $this->assertEquals(true, $item->isenrolled);
        $this->assertEquals(true, $item->isunlocked);
    }

    public function test_program_does_not_get_completed_if_user_has_only_suspended_allocations(): void {
        global $CFG;
        $CFG->enablecompletion = true;
        self::setAdminUser();
        $user = self::getDataGenerator()->create_user();
        $program = $this->generator->generate_program_with_base_set();
        $baseset = $program->get_base_set();
        $programuser = $this->generator->allocate_user_to_program($program->get('id'), $user->id);
        // Course within the base set.
        $course = self::getDataGenerator()->create_course(['enablecompletion' => true]);
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));
        $this->generator->enable_program_enrol_instance($programcourse);
        // Module within the course.
        $assign = self::getDataGenerator()->create_module('assign', ['course' => $course->id], ['completion' => 1]);

        // Suspend the only allocation of this user.
        $programuser->set('status', constants::STATUS_OVERRIDE_SUSPENDED);
        $programuser->update();

        // Complete the course.
        $completion = new completion_info($course);
        $cmassign = get_coursemodule_from_id('assign', $assign->cmid);
        $completion->update_state($cmassign, COMPLETION_COMPLETE, $user->id);
        $ccompletion = new completion_completion(['course' => $course->id, 'userid' => $user->id]);
        $ccompletion->mark_complete();

        // Check program progress and verify it has not trigger program completion event.
        $sink = $this->redirectEvents();
        new program_tree_progress($program, $user->id);
        $events = $sink->get_events();
        $events = array_filter($events, static function($event) {
            return $event instanceof program_completed;
        });
        $sink->close();
        $this->assertEmpty($events);

        // Check the status methods do not find the program to be completed.
        $statuses = array_column(api::get_user_allocation_statuses($program->get('id'), $user->id, 0), 'status');
        $this->assertNotContains('program_user_status_completed', $statuses);

        // Test when is not suspended anymore program is completed (events fired and status returned by get status methods).
        $programuser->set('status', constants::STATUS_OVERRIDE_DEFAULT);
        $programuser->update();

        $sink = $this->redirectEvents();
        new program_tree_progress($program, $user->id);
        $events = $sink->get_events();
        $events = array_filter($events, static function($event) {
            return $event instanceof program_completed;
        });
        $sink->close();
        $this->assertCount(1, $events);

        $statuses = array_column(api::get_user_allocation_statuses($program->get('id'), $user->id, 0), 'status');
        $this->assertContains('program_user_status_completed', $statuses);
    }

    public function test_program_does_not_get_completed_if_program_archived(): void {
        global $CFG;
        $CFG->enablecompletion = true;
        self::setAdminUser();
        $user = self::getDataGenerator()->create_user();
        $program = $this->generator->generate_program_with_base_set();
        $baseset = $program->get_base_set();
        $programuser = $this->generator->allocate_user_to_program($program->get('id'), $user->id);
        // Course within the base set.
        $course = self::getDataGenerator()->create_course(['enablecompletion' => true]);
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));
        $this->generator->enable_program_enrol_instance($programcourse);
        // Module within the course.
        $assign = self::getDataGenerator()->create_module('assign', ['course' => $course->id], ['completion' => 1]);

        // Archive the program.
        $program->set('archived', true);
        $program->update();

        // Complete the course.
        $completion = new completion_info($course);
        $cmassign = get_coursemodule_from_id('assign', $assign->cmid);
        $completion->update_state($cmassign, COMPLETION_COMPLETE, $user->id);
        $ccompletion = new completion_completion(['course' => $course->id, 'userid' => $user->id]);
        $ccompletion->mark_complete();

        // Check program progress and verify it has not trigger program completion event.
        $sink = $this->redirectEvents();
        new program_tree_progress($program, $user->id);
        $events = $sink->get_events();
        $events = array_filter($events, static function($event) {
            return $event instanceof program_completed;
        });
        $sink->close();
        $this->assertEmpty($events);

        // Check the status methods do not find the program to be completed.
        $statuses = array_column(api::get_user_allocation_statuses($program->get('id'), $user->id, 0), 'status');
        $this->assertNotContains('program_user_status_completed', $statuses);

        // Test when is not archived anymore, the program can be completed (events fired and completed status returned).
        $program->set('archived', false);
        $program->update();

        $sink = $this->redirectEvents();
        new program_tree_progress($program, $user->id);
        $events = $sink->get_events();
        $events = array_filter($events, static function($event) {
            return $event instanceof program_completed;
        });
        $sink->close();
        $this->assertCount(1, $events);

        $statuses = array_column(api::get_user_allocation_statuses($program->get('id'), $user->id, 0), 'status');
        $this->assertContains('program_user_status_completed', $statuses);
    }
}
