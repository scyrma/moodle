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
 * File contains the unit tests for condition\user_profile_field class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_dynamicrule\tool_dynamicrule\condition\user_profile_field;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for condition\user_profile_field  class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field
 * @covers     \tool_dynamicrule\condition_base
 * @covers     \tool_dynamicrule\condition_sql
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_condition_user_profile_field_testcase extends advanced_testcase {

    /**
     * Get dynamic rule generator
     *
     * @return tool_dynamicrule_generator
     */
    protected function get_generator(): tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Test get_title
     */
    public function test_get_title() {
        $condition = new user_profile_field();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $condition = new user_profile_field();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        global $DB;

        $this->resetAfterTest();

        $condition = new user_profile_field();
        $configform = ['userprofilefield' => 'invalidfield'];
        $this->assertArrayHasKey('userprofilefieldgroup', $condition->validate_config_form($configform));

        // Add a custom field of text type.
        $categoryid = $DB->insert_record('user_info_category', (object)['name' => 't']);
        $DB->insert_record('user_info_field', (object)['shortname' => 'frogdesc', 'name' => 'Description of frog',
            'categoryid' => $categoryid, 'datatype' => 'text']);

        $configform = ['userprofilefield' => 'frogdesc', 'frogdesc_value' => 'Froggy', 'frogdesc_op' => 2];
        $this->assertArrayNotHasKey('userprofilefieldgroup', $condition->validate_config_form($configform));
    }

    /**
     * Test get_description
     */
    public function test_get_description() {
        global $DB;

        $this->resetAfterTest();

        $rule1 = $this->get_generator()->create_rule();
        $fieldshortname = 'city';
        $configdata1 = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'Perth', $fieldshortname . '_op' => 2];
        $condition1 = user_profile_field::create($rule1->id, $configdata1);

        $a = (object)['fieldname' => get_string('city'), 'fieldvalue' => 'is equal to Perth'];
        $this->assertEquals(get_string('conditionuserprofilefielddescriptiontext', 'tool_dynamicrule', $a),
                            $condition1->get_description());

        // Add a custom field of text type.
        $fieldshortname = 'someinfo';
        $fieldname = 'Example field';
        $categoryid = $DB->insert_record('user_info_category', (object)['name' => 't']);
        $fieldid = $DB->insert_record('user_info_field', (object)['shortname' => $fieldshortname, 'name' => $fieldname,
                                                               'categoryid' => $categoryid, 'datatype' => 'text']);

        $rule3 = $this->get_generator()->create_rule();
        $configdata2 = [
            'userprofilefield' => $fieldshortname,
            $fieldshortname . '_value' => 'Example value',
            $fieldshortname . '_op' => 2
        ];
        $condition3 = user_profile_field::create($rule3->id, $configdata2);

        $a = (object)['fieldname' => $fieldname, 'fieldvalue' => 'is equal to Example value'];
        $str = get_string('conditionuserprofilefielddescriptiontext', 'tool_dynamicrule', $a);
        $this->assertEquals($str, $condition3->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid() {
        global $DB;

        $this->resetAfterTest();

        $fieldshortname = 'someinfo';
        $categoryid = $DB->insert_record('user_info_category', (object)['name' => 't']);
        $fieldid = $DB->insert_record('user_info_field', (object)['shortname' => $fieldshortname, 'name' => 'Example field',
            'categoryid' => $categoryid, 'datatype' => 'text']);

        $rule = $this->get_generator()->create_rule();
        $configdata = [
            'userprofilefield' => $fieldshortname,
            $fieldshortname . '_value' => 'Example value',
            $fieldshortname . '_op' => 2
        ];
        $condition = user_profile_field::create($rule->id, $configdata);
        $this->assertTrue($condition->is_configuration_valid());
        $DB->delete_records('user_info_field', ['id' => $fieldid]);
        $this->assertFalse($condition->is_configuration_valid());

        $configdata = [];
        $condition = user_profile_field::create($rule->id, $configdata);
        $this->assertFalse($condition->is_configuration_valid());
        $this->assertNull($condition->get_userprofilefield_value());

        $configdata = ['userprofilefield' => 'invalid'];
        $condition = user_profile_field::create($rule->id, $configdata);
        $this->assertFalse($condition->is_configuration_valid());
    }

    /**
     * Test user_enrolled event is triggering rule processing.
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_trigger_rule_processing() {
        global $DB, $CFG;

        $this->resetAfterTest();

        require_once($CFG->dirroot.'/user/lib.php');

        $user0 = $this->getDataGenerator()->create_user();

        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $fieldshortname = 'city';
        // Check for value of field city equal to Perth.
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'Perth', $fieldshortname . '_op' => 2];
        user_profile_field::create($rule0->id, $configdata);
        $configdata = ['subject' => 'User updated',
            'body' => ['text' => 'Congratulations, you updated your profile.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule0->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();
        $this->assertEquals(0, $DB->count_records('notifications'));

        $user0->city = 'Perth';
        $user0->password = 'Moodle1!';
        user_update_user($user0);

        // Check outcomes.
        $messages = $sink->get_messages();
        $this->assertEquals(1, $sink->count());
        $this->assertEquals($messages[0]->subject, 'User updated');
        $sink->clear();

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule0->id]));
    }

    /**
     * Test text field condition.
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_text_field_condition() {
        global $DB, $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot.'/user/lib.php');

        $user0 = $this->getDataGenerator()->create_user();
        $user1 = $this->getDataGenerator()->create_user();
        $fieldshortname = 'city';

        $user0->city = 'El Catllar';
        $user0->password = 'Moodle1!';
        user_update_user($user0);

        // Check for value Contain.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'arra', $fieldshortname . '_op' => 0];
        user_profile_field::create($rule1->id, $configdata);
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $user0->city = 'Tarragona';
        user_update_user($user0);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule1->id));

        // Check for value Does not contain.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'arra', $fieldshortname . '_op' => 1];
        user_profile_field::create($rule1->id, $configdata);
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $user0->city = 'El Catllar';
        user_update_user($user0);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule1->id));

        // Check for Equal to.
        $rule2 = $this->get_generator()->create_rule();
        $configdata = [
            'userprofilefield' => $fieldshortname,
            $fieldshortname . '_value' => 'Tarragona',
            $fieldshortname . '_op' => 2
        ];
        user_profile_field::create($rule2->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule2->id));
        $user0->city = 'Tarragona';
        user_update_user($user0);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule2->id));

        // Check for value Starts with.
        $rule3 = $this->get_generator()->create_rule();
        $fieldshortname = 'city';
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'Barce', $fieldshortname . '_op' => 3];
        user_profile_field::create($rule3->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule3->id));
        $user0->city = 'Barcelona';
        user_update_user($user0);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule3->id));

        // Check for value Ends with.
        $rule4 = $this->get_generator()->create_rule();
        $fieldshortname = 'city';
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'abc', $fieldshortname . '_op' => 4];
        user_profile_field::create($rule4->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule4->id));
        $user0->city = 'qwertyabc';
        user_update_user($user0);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule4->id));

        // Check for value Empty.
        $rule5 = $this->get_generator()->create_rule();
        $fieldshortname = 'city';
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '', $fieldshortname . '_op' => 5];
        user_profile_field::create($rule5->id, $configdata);
        $users = \tool_dynamicrule\api::get_matching_users($rule5->id);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule5->id));
        $user0->city = '';
        user_update_user($user0);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule5->id));

        // Check for value Not Empty.
        $rule6 = $this->get_generator()->create_rule();
        $fieldshortname = 'city';
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '', $fieldshortname . '_op' => 6];
        user_profile_field::create($rule6->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule6->id));
        $user0->city = 'notempty';
        user_update_user($user0);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule6->id));
        $user1->city = 'anotherone';
        $user1->password = 'Moodle1!';
        user_update_user($user1);
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule6->id));

        // Test with a custom profile field.
        $fieldshortname = 'upf_field';
        $categoryid = $DB->insert_record('user_info_category', ['name' => 't']);
        $fieldid = $DB->insert_record('user_info_field', (object)['shortname' => $fieldshortname, 'name' => 'Example field',
            'categoryid' => $categoryid, 'datatype' => 'text']);
        $DB->insert_record('user_info_data', (object)['userid' => $user0->id, 'fieldid' => $fieldid, 'data' => 'Hello world']);
        $rule7 = $this->get_generator()->create_rule();
        // Check Starts with.
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'Hello', $fieldshortname . '_op' => 3];
        user_profile_field::create($rule7->id, $configdata);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule7->id));
    }

    /**
     * Test date time field condition.
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_datetime_field_condition() {
        global $DB, $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/user/lib.php');

        $user0 = $this->getDataGenerator()->create_user();

        $fieldshortname = 'somedatetime';
        $categoryid = $DB->insert_record('user_info_category', (object)['name' => 't']);
        $fieldid = $DB->insert_record('user_info_field', (object)['shortname' => $fieldshortname, 'name' => 'Example field',
            'categoryid' => $categoryid, 'datatype' => 'datetime']);

        // Check for value Is not empty (op = 1).
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '', $fieldshortname . '_op' => 1,
            $fieldshortname . '_op2' => 1];
        user_profile_field::create($rule1->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $id = $DB->insert_record('user_info_data', (object)['userid' => $user0->id, 'fieldid' => $fieldid, 'data' => time()]);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule1->id));

        // Check for value Is empty (op = 2).
        $rule2 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '', $fieldshortname . '_op' => 2,
            $fieldshortname . '_op2' => 1];
        user_profile_field::create($rule2->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule2->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => '0']);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule2->id));

        // Check for value In the past (op = 3).
        $rule3 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '', $fieldshortname . '_op' => 3,
            $fieldshortname . '_op2' => 1];
        user_profile_field::create($rule3->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule3->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => (time() + 3600)]);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule3->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => (time() - 3600)]);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule3->id));

        // Check for value In the future (op = 4).
        $rule4 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '', $fieldshortname . '_op' => 4,
            $fieldshortname . '_op2' => 1];
        user_profile_field::create($rule4->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule4->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => (time() + 3600)]);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule4->id));

        // Check for value Last 7 days (op = 5) (value = 7).
        $rule5 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '7', $fieldshortname . '_op' => 5,
            $fieldshortname . '_op2' => 1];
        user_profile_field::create($rule5->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule5->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => (time() - (6 * DAYSECS))]);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule5->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => (time() - (8 * DAYSECS))]);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule5->id));

        // Check for value Next 7 days (op = 6) (value = 7).
        $rule6 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '7', $fieldshortname . '_op' => 6,
            $fieldshortname . '_op2' => 1];
        user_profile_field::create($rule6->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule6->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => (time() + (6 * DAYSECS))]);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule6->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => (time() + (8 * DAYSECS))]);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule6->id));

        // Check for value Current (op = 7) day (op2 = 1).
        $rule7 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '', $fieldshortname . '_op' => 7,
            $fieldshortname . '_op2' => 1];
        user_profile_field::create($rule7->id, $configdata);
        $beginofday = strtotime("today", time());
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule7->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => $beginofday + 3600]);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule7->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => ($beginofday + (2 * DAYSECS))]);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule7->id));

        // Check for value Previous (op = 8) week (op2 = 2).
        $rule8 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '', $fieldshortname . '_op' => 8,
            $fieldshortname . '_op2' => 2];
        user_profile_field::create($rule8->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule8->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => ($beginofday - (7 * DAYSECS))]);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule8->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => ($beginofday - (16 * DAYSECS))]);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule8->id));

        // Check for value Upcoming (op = 9) week (op2 = 2).
        $rule9 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '', $fieldshortname . '_op' => 9,
            $fieldshortname . '_op2' => 2];
        user_profile_field::create($rule9->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule9->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => $beginofday + (7 * DAYSECS)]);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule9->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => ($beginofday + (18 * DAYSECS))]);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule9->id));
    }

    /**
     * Test menu field condition.
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_menu_field_condition() {
        global $DB, $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/user/lib.php');

        $user0 = $this->getDataGenerator()->create_user();

        $fieldshortname = 'somemenu';
        $categoryid = $DB->insert_record('user_info_category', (object)['name' => 't']);
        $fieldid = $DB->insert_record('user_info_field', (object)['shortname' => $fieldshortname, 'name' => 'Example field',
            'categoryid' => $categoryid, 'datatype' => 'menu', 'param1' => "OPTION1\nOPTION2\nOPTION3"]);

        // Check for value OPTION2.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '', $fieldshortname . '_op' => 1,
            $fieldshortname . '_op2' => 1];
        user_profile_field::create($rule1->id, $configdata);
        $id = $DB->insert_record('user_info_data', (object)['userid' => $user0->id, 'fieldid' => $fieldid, 'data' => 1]);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => 3]);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule1->id));
    }

    /**
     * Test checkbox field condition.
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_checkbox_field_condition() {
        global $DB, $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/user/lib.php');

        $user0 = $this->getDataGenerator()->create_user();

        $fieldshortname = 'somemenu';
        $categoryid = $DB->insert_record('user_info_category', (object)['name' => 't']);
        $fieldid = $DB->insert_record('user_info_field', (object)['shortname' => $fieldshortname, 'name' => 'Example field',
            'categoryid' => $categoryid, 'datatype' => 'checkbox']);

        // Check for value checked.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '', $fieldshortname . '_op' => 1,
            $fieldshortname . '_op2' => 1];
        user_profile_field::create($rule1->id, $configdata);
        $id = $DB->insert_record('user_info_data', (object)['userid' => $user0->id, 'fieldid' => $fieldid, 'data' => 1]);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => 0]);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule1->id));
    }
}
