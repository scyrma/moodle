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

namespace tool_dynamicrule\tool_dynamicrule\condition;

use advanced_testcase;
use context_system;
use tool_dynamicrule\rule;
use tool_dynamicrule_generator;
use tool_wp\local\helpers\string_helper;
use tool_dynamicrule\tool_dynamicrule\outcome\notification;

/**
 * Unit tests for condition\course_last_access class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\condition\course_last_access
 * @covers     \tool_dynamicrule\condition_base
 * @covers     \tool_dynamicrule\condition_sql
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_last_access_test extends advanced_testcase {

    /**
     * Get dynamic rule generator
     *
     * @return tool_dynamicrule_generator
     */
    protected function get_generator(): tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test supports_rule_types
     */
    public function test_supports_rule_types(): void {
        $condition = course_last_access::instance();
        $this->assertEquals(rule::TYPE_NORMAL + rule::TYPE_SHARED, $condition->supports_rule_types());
    }

    /**
     * Test get_title
     */
    public function test_get_title() {
        $condition = course_last_access::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $condition = course_last_access::instance();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {

        // No time selected.
        $condition = course_last_access::instance();
        $configform = ['operator' => course_last_access::OPERATOR_BEFORE, 'lastaccesstimestamp' => ''];
        $this->assertArrayHasKey('lastaccesstimestamp', $condition->validate_config_form($configform));

        $configform = ['operator' => course_last_access::OPERATOR_AFTER, 'lastaccesstimestamp' => ''];
        $this->assertArrayHasKey('lastaccesstimestamp', $condition->validate_config_form($configform));

        // No relative time.
        $configform = ['operator' => course_last_access::OPERATOR_BEFORELAST, 'lastaccessrelative' => ''];
        $this->assertArrayHasKey('lastaccessrelative', $condition->validate_config_form($configform));

        $configform = ['operator' => course_last_access::OPERATOR_INLAST, 'lastaccessrelative' => ''];
        $this->assertArrayHasKey('lastaccessrelative', $condition->validate_config_form($configform));

        // 0 days/weeks/years.
        $configform = ['operator' => course_last_access::OPERATOR_BEFORELAST, 'lastaccessrelative' => '0 weeks'];
        $this->assertArrayHasKey('lastaccessrelative', $condition->validate_config_form($configform));

        $configform = ['operator' => course_last_access::OPERATOR_INLAST, 'lastaccessrelative' => '0 days'];
        $this->assertArrayHasKey('lastaccessrelative', $condition->validate_config_form($configform));

        // Valid cases.
        $configform = ['operator' => course_last_access::OPERATOR_EVER];
        $this->assertEmpty($condition->validate_config_form($configform));

        $configform = ['operator' => course_last_access::OPERATOR_NEVER];
        $this->assertEmpty($condition->validate_config_form($configform));

        $configform = ['operator' => course_last_access::OPERATOR_BEFORELAST, 'lastaccessrelative' => '1 weeks'];
        $this->assertEmpty($condition->validate_config_form($configform));
    }

    /**
     * Data provider for {{@see test_get_matching_users}}
     *
     * @return array
     */
    public function provider_get_matching_users(): array {
        return [
            'never' => [course_last_access::OPERATOR_NEVER, null, null, ['user3']],
            'ever' => [course_last_access::OPERATOR_EVER, null, null, ['user0', 'user1', 'user2']],
            'before' => [course_last_access::OPERATOR_BEFORE, strtotime('- 1 week'), null, ['user1', 'user2']],
            'before1' => [course_last_access::OPERATOR_BEFORE, strtotime('- 2 weeks'), null, ['user2']],
            'after' => [course_last_access::OPERATOR_AFTER, strtotime('- 1 week'), null, ['user0']],
            'after1' => [course_last_access::OPERATOR_AFTER, strtotime('- 2 weeks'), null, ['user0', 'user1']],
            'inlast' => [course_last_access::OPERATOR_INLAST, null, '1 weeks', ['user0']],
            'inlast1' => [course_last_access::OPERATOR_INLAST, null, '2 weeks', ['user0', 'user1']],
            'beforelast' => [course_last_access::OPERATOR_BEFORELAST, null, '1 weeks', ['user1', 'user2']],
            'beforelast1' => [course_last_access::OPERATOR_BEFORELAST, null, '2 weeks', ['user2']],
        ];
    }

    /**
     * Test getting matching users.
     *
     * @param string $operator
     * @param int|null $lastaccesstimestamp
     * @param string|null $lastaccessrelative
     * @param string[] $matchedusers
     *
     * @dataProvider provider_get_matching_users
     */
    public function test_get_matching_users(string $operator, ?int $lastaccesstimestamp,
            ?string $lastaccessrelative, array $matchedusers): void {

        $course1 = $this->getDataGenerator()->create_course();

        $users = [
            'user0' => $this->getDataGenerator()->create_user(),
            'user1' => $this->getDataGenerator()->create_user(),
            'user2' => $this->getDataGenerator()->create_user(),
            'user3' => $this->getDataGenerator()->create_user(),
        ];

        foreach ($users as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $course1->id, 'student');
        }

        // Register each user access to course. User 3 never accessed.
        $this->getDataGenerator()->create_user_course_lastaccess($users['user0'], $course1, strtotime('- 1 days'));
        $this->getDataGenerator()->create_user_course_lastaccess($users['user1'], $course1, strtotime('- 8 days'));
        $this->getDataGenerator()->create_user_course_lastaccess($users['user2'], $course1, strtotime('- 15 days'));

        // Rule with last course access condition.
        $configdata = [
            'courseid' => $course1->id,
            'operator' => $operator,
        ];
        if ($lastaccesstimestamp) {
            $configdata['lastaccesstimestamp'] = $lastaccesstimestamp;
        }
        if ($lastaccessrelative) {
            $configdata['lastaccessrelative'] = $lastaccessrelative;
        }

        // Sanity check.
        $condition = course_last_access::instance();
        $this->assertEmpty($condition->validate_config_form($configdata));

        // Create rule.
        $rule0 = $this->get_generator()->create_rule();
        course_last_access::create($rule0->id, $configdata);

        // Test.
        $this->assertEquals(count($matchedusers), \tool_dynamicrule\api::count_matching_users($rule0->id));
        $matchingusers = \tool_dynamicrule\api::get_matching_users($rule0->id);
        $userids = array_map(function($key) use ($users) {
            return $users[$key]->id;
        }, $matchedusers);
        $this->assertEqualsCanonicalizing($userids, array_column($matchingusers, 'id'));
    }

    /**
     * Test get_description
     */
    public function test_get_description() {

        $course1 = $this->getDataGenerator()->create_course();
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id, 'operator' => course_last_access::OPERATOR_EVER];
        $condition1 = course_last_access::create($rule1->id, $configdata);

        $this->assertStringMatchesFormat("Users who have accessed the course %a at least once",
            $condition1->get_description());

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id, 'operator' => course_last_access::OPERATOR_NEVER];
        $condition1 = course_last_access::create($rule1->id, $configdata);

        $this->assertStringMatchesFormat('Users who have never accessed the course %a', $condition1->get_description());

        $rule1 = $this->get_generator()->create_rule();
        $lastaccesstimestamp = strtotime('- 3 days');
        $formattedtime = userdate($lastaccesstimestamp, get_string('strftimedatetimeshort', 'langconfig'));
        $configdata = ['courseid' => $course1->id, 'operator' => course_last_access::OPERATOR_BEFORE,
            'lastaccesstimestamp' => $lastaccesstimestamp];
        $condition1 = course_last_access::create($rule1->id, $configdata);

        $this->assertStringMatchesFormat('Users who last accessed the course %a before ' . $formattedtime,
            $condition1->get_description());

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id, 'operator' => course_last_access::OPERATOR_AFTER,
            'lastaccesstimestamp' => $lastaccesstimestamp];
        $condition1 = course_last_access::create($rule1->id, $configdata);

        $this->assertStringMatchesFormat('Users who last accessed the course %a since ' . $formattedtime,
            $condition1->get_description());

        $rule1 = $this->get_generator()->create_rule();
        $lastaccessrelative = '3 day';
        $formattedtime = string_helper::translate_relativedate_string($lastaccessrelative);
        $configdata = ['courseid' => $course1->id, 'operator' => course_last_access::OPERATOR_BEFORELAST,
            'lastaccessrelative' => $lastaccessrelative];
        $condition1 = course_last_access::create($rule1->id, $configdata);

        $this->assertStringMatchesFormat('Users who have not accessed the course %a for ' . $formattedtime,
            $condition1->get_description());

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['courseid' => $course1->id, 'operator' => course_last_access::OPERATOR_INLAST,
            'lastaccessrelative' => $lastaccessrelative];
        $condition1 = course_last_access::create($rule1->id, $configdata);

        $this->assertStringMatchesFormat('Users who have accessed the course %a in last ' . $formattedtime,
            $condition1->get_description());
    }

    /**
     * Test rule processing.
     *
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     * @uses \tool_dynamicrule\tool_dynamicrule\condition\course_last_access::get_data_for_outcome
     */
    public function test_trigger_rule_processing() {
        global $CFG, $DB;

        // Create course customfields.
        $catid = $this->getDataGenerator()->create_custom_field_category([])->get('id');
        $this->getDataGenerator()->create_custom_field(['categoryid' => $catid, 'type' => 'text',
            'shortname' => 'credits']);
        $this->getDataGenerator()->create_custom_field(['categoryid' => $catid, 'type' => 'date',
            'shortname' => 'finaltestdate']);
        $this->getDataGenerator()->create_custom_field(['categoryid' => $catid, 'type' => 'select',
            'shortname' => 'coursetype', 'configdata' => ['options' => "Degree\nMaster"]]);

        // Create course.
        $course = $this->getDataGenerator()->create_course([
            'shortname' => 'MATH101',
            'fullname' => 'Mathematics 101',
            'customfield_credits' => '60',
            'customfield_finaltestdate' => strtotime('1 January 2020 00:00'),
            'customfield_coursetype' => 2
            ]);

        // User enrolled into course.
        $timelastaccess = strtotime('- 1 days');
        $user0 = $this->getDataGenerator()->create_user(['firstname' => 'John', 'lastname' => 'Smith']);
        $this->getDataGenerator()->enrol_user($user0->id, $course->id, 'student', 'manual');
        $this->getDataGenerator()->create_user_course_lastaccess($user0, $course, $timelastaccess);

        // Create rule0 with course last access conditon and notification outcome.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $course->id, 'operator' => course_last_access::OPERATOR_EVER];
        course_last_access::create($rule0->id, $configdata);
        $configdata = ['subject' => 'Completed {{courseshortname}}',
            'body' => ['text' => 'Congratulations {{userfullname}}, ' .
                'you finally accessed (<a href="{{courseurl}}">{{coursefullname}}</a>). ' .
                'Course credits: {{coursecustomfield_credits}}, ' .
                'Course type: {{coursecustomfield_coursetype}}, ' .
                'Final test date: {{coursecustomfield_finaltestdate}}, ' .
                'Last access time: {{courselastaccesstime}}',
                'format' => FORMAT_HTML]];
        notification::create($rule0->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();
        $this->assertEquals(0, $DB->count_records('notifications'));

        // Process rule manually as if we enabled it.
        $ruleinstance = new rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Check outcomes.
        $messages = $sink->get_messages();
        // Keep only DR messages.
        $messages = array_filter($messages, function($message) {
            return ($message->eventtype === 'notificationoutcome');
        });
        $this->assertCount(1, $messages);
        $this->assertEquals('Completed MATH101', $messages[0]->subject);
        $url = $CFG->wwwroot.'/course/view.php?id=' . $course->id;
        $this->assertEquals('Congratulations John Smith, you finally accessed (<a href="' .
            $url . '">Mathematics 101</a>). ' .
            'Course credits: 60, ' .
            'Course type: Master, ' .
            'Final test date: Wednesday, 1 January 2020, 12:00 AM, ' .
            'Last access time: ' . userdate($timelastaccess, get_string('strftimedatefullshort')),
            $messages[0]->fullmessagehtml);
        $sink->clear();

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule0->id]));
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add() {
        // Anyone can add this condition.
        $condition = course_last_access::instance();
        $this->assertTrue($condition->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit() {
        $course0 = $this->getDataGenerator()->create_course();
        $configform = ['courseid' => $course0->id];
        $condition = course_last_access::instance();

        // Admin user.
        $this->setAdminUser();
        $this->assertTrue($condition->user_can_edit($configform));

        // Non-priveleged user.
        $user = $this->getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse($condition->user_can_edit($configform));

        // Grant priveleges to user.
        $this->getDataGenerator()->enrol_user($user->id, $course0->id, 'editingteacher');
        $this->assertTrue($condition->user_can_edit($configform));
    }
}
