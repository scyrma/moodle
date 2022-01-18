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
 * Process rules task tests.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Process rules task tests.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\task\process_rules
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_task_testcase extends advanced_testcase {

    /**
     * Get dynamic rule generator
     *
     * @return tool_dynamicrule_generator
     */
    protected function get_generator(): tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Test get_name.
     */
    public function test_get_name() {
        $task = new \tool_dynamicrule\task\process_rules();
        $this->assertNotEmpty($task->get_name());
    }

    /**
     * Test the process rules task.
     */
    public function test_process_rules_task() {
        global $DB;

        $this->resetAfterTest();

        $this->getDataGenerator()->create_user(['lastaccess' => strtotime('today')]);

        // Start with no matches.
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_match'));

        $task = new \tool_dynamicrule\task\process_rules();
        $task->execute();

        // No rules, no matches.
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_match'));

        // A rule with valid condition and outcome.
        $rule0 = $this->get_generator()->create_rule(['enabled' => true]);
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        $subject = 'Test subject 1';
        $configdata = ['subject' => $subject,
            'body' => ['text' => 'Test body', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        $task->execute();

        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match'));
    }

    /**
     * Test the process rules task with event-based condition.
     *
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\tool_dynamicrule\condition\cohort_member
     */
    public function test_process_rules_task_with_event_condition() {
        global $DB;

        $this->resetAfterTest(true);

        // Create user0 and add it to cohort0.
        $cohort0 = $this->getDataGenerator()->create_cohort();
        $user0 = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort0->id, $user0->id);

        // Create rule0 with cohort0 membership conditon (event-based) and notification outcome.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['cohortid' => $cohort0->id];
        \tool_dynamicrule\tool_dynamicrule\condition\cohort_member::create($rule0->id, $configdata);
        $configdata = ['subject' => 'Added to cohort0',
            'body' => ['text' => 'Congratulations, you have been added to cohort0.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();
        $this->assertEquals(0, $DB->count_records('notifications'));

        // Process rules.
        $task = new \tool_dynamicrule\task\process_rules();
        $task->execute();

        // Check outcomes (all conditions are event-based).
        $messages = $sink->get_messages();
        $this->assertEquals(0, $sink->count());
        $sink->clear();
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_match'));

        // Add non-event based condition to the same rule.
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        // Process rules.
        $task = new \tool_dynamicrule\task\process_rules();
        $task->execute();

        // Check outcomes (rule has been triggered as there is one non-event based condition).
        $messages = $sink->get_messages();
        $this->assertEquals(1, $sink->count());
        $this->assertEquals($messages[0]->subject, 'Added to cohort0');
        $sink->clear();
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));
    }

    /**
     * Test rule process is triggered on enabling.
     *
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\tool_dynamicrule\condition\cohort_member
     */
    public function test_enable_rule_trigger_process_rule_task() {
        global $DB;

        $this->resetAfterTest(true);

        // Create user0 and add it to cohort0.
        $cohort0 = $this->getDataGenerator()->create_cohort();
        $user0 = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort0->id, $user0->id);

        // Create rule0 with cohort0 membership conditon (event-based) and notification outcome.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 0]);
        $configdata = ['cohortid' => $cohort0->id];
        \tool_dynamicrule\tool_dynamicrule\condition\cohort_member::create($rule0->id, $configdata);
        $configdata = ['subject' => 'Added to cohort0',
            'body' => ['text' => 'Congratulations, you have been added to cohort0.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();
        $this->assertEquals(0, $DB->count_records('notifications'));

        // Check there are no adhock tasks.
        $this->assertFalse($DB->record_exists('task_adhoc', ['component' => 'tool_dynamicrule']));

        // Enable rule (this should create adhock task).
        \tool_dynamicrule\api::enable_rule($rule0->id);

        // Check there is one adhock task.
        $this->assertEquals(1, $DB->count_records('task_adhoc', ['component' => 'tool_dynamicrule']));

        // Check outcomes (task is queued, but has not been processed yet).
        $messages = $sink->get_messages();
        $this->assertEquals(0, $sink->count());
        $sink->clear();
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_match'));

        // Trigger adhock task processing.
        $this->run_adhock_tasks();

        // Check outcomes (rule processing has been triggered).
        $messages = $sink->get_messages();
        $this->assertEquals(1, $sink->count());
        $this->assertEquals($messages[0]->subject, 'Added to cohort0');
        $sink->clear();
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));

        // Check there are no adhock tasks.
        $this->assertFalse($DB->record_exists('task_adhoc', ['component' => 'tool_dynamicrule']));
    }

    /**
     * Run adhoc tasks.
     */
    protected function run_adhock_tasks() {
        while ($task = \core\task\manager::get_next_adhoc_task(time())) {
            $task->execute();
            \core\task\manager::adhoc_task_complete($task);
        }
        $this->expectOutputRegex("/^Processing dynamic rule with id \d+/ms");
    }
}
