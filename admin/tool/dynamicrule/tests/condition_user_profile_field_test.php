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
 * File contains the unit tests for condition\user_profile_field class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_dynamicrule\tool_dynamicrule\condition\user_profile_field;
use tool_dynamicrule\tool_dynamicrule\outcome\notification;

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

    /** @var int $visibility Visibility for custom field */
    public $visibility;

    /**
     * Set up
     */
    public function setUp(): void {
        global $CFG;
        require_once($CFG->dirroot.'/user/profile/lib.php');
        require_once($CFG->dirroot . '/user/lib.php');
        $this->visibility = PROFILE_VISIBLE_ALL;
        $this->resetAfterTest();
    }

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
        $condition = user_profile_field::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $condition = user_profile_field::instance();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        global $DB;

        $condition = user_profile_field::instance();
        $configform = ['userprofilefield' => 'invalidfield'];
        $this->assertArrayHasKey('userprofilefieldgroup', $condition->validate_config_form($configform));

        // Add a custom field of text type.
        $categoryid = $this->add_profile_category();
        $DB->insert_record('user_info_field', (object)['shortname' => 'frogdesc', 'name' => 'Description of frog',
            'categoryid' => $categoryid, 'datatype' => 'text', 'visible' => $this->visibility]);

        $configform = [
            'userprofilefield' => 'frogdesc',
            'frogdesc_value' => 'Froggy',
            'frogdesc_op' => user_profile_field::TEXT_IS_EQUAL_TO
        ];
        $this->assertArrayNotHasKey('userprofilefieldgroup', $condition->validate_config_form($configform));
    }

    /**
     * Test get_description
     */
    public function test_get_description() {
        global $DB;

        $rule1 = $this->get_generator()->create_rule();
        $fieldshortname = 'city';
        $configdata1 = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'Perth',
            $fieldshortname . '_op' => user_profile_field::TEXT_IS_EQUAL_TO];
        $condition1 = user_profile_field::create($rule1->id, $configdata1);

        $a = (object)['fieldname' => get_string('city'), 'fieldvalue' => 'is equal to Perth'];
        $this->assertEquals(get_string('conditionuserprofilefielddescriptiontext', 'tool_dynamicrule', $a),
                            $condition1->get_description());

        // Add a custom field of text type.
        $fieldshortname = 'someinfo';
        $fieldname = 'Example field';
        $categoryid = $this->add_profile_category();
        $fieldid = $DB->insert_record('user_info_field', (object)['shortname' => $fieldshortname, 'name' => $fieldname,
            'categoryid' => $categoryid, 'datatype' => 'text', 'visible' => $this->visibility]);

        $rule3 = $this->get_generator()->create_rule();
        $configdata2 = [
            'userprofilefield' => $fieldshortname,
            $fieldshortname . '_value' => 'Example value',
            $fieldshortname . '_op' => user_profile_field::TEXT_IS_EQUAL_TO
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

        $fieldshortname = 'someinfo';
        $categoryid = $this->add_profile_category();
        $fieldid = $DB->insert_record('user_info_field', (object)['shortname' => $fieldshortname, 'name' => 'Example field',
            'categoryid' => $categoryid, 'datatype' => 'text']);

        $rule = $this->get_generator()->create_rule();
        $configdata = [
            'userprofilefield' => $fieldshortname,
            $fieldshortname . '_value' => 'Example value',
            $fieldshortname . '_op' => user_profile_field::TEXT_IS_EQUAL_TO
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
     * Test get_profile_fields_info permissions.
     */
    public function test_get_profile_fields_info() {
        global $DB;

        // Override method visibility.
        $condition = user_profile_field::instance();
        $reflector = new ReflectionClass($condition);
        $method = $reflector->getMethod('get_profile_fields_info');
        $method->setAccessible(true);

        // Create custom fields.
        $categoryid = $this->add_profile_category();
        $fieldid0 = $this->add_profile_field('text', $categoryid, 'visibleall', PROFILE_VISIBLE_ALL);
        $fieldid1 = $this->add_profile_field('text', $categoryid, 'visibleprivate', PROFILE_VISIBLE_PRIVATE);

        // Test as admin, all custom fields are listed.
        self::setAdminUser();
        $fields = $method->invoke($condition);
        $this->assertArrayHasKey('visibleall', $fields);
        $this->assertArrayHasKey('visibleprivate', $fields);

        // Test as user, private field is not listed.
        $user0 = $this->getDataGenerator()->create_user();
        self::setUser($user0);
        $fields = $method->invoke($condition);
        $this->assertArrayHasKey('visibleall', $fields);
        $this->assertArrayNotHasKey('visibleprivate', $fields);

        // Test as user with ignore permission check, private field is listed.
        $fields = $method->invoke($condition, false);
        $this->assertArrayHasKey('visibleall', $fields);
        $this->assertArrayHasKey('visibleprivate', $fields);
    }

    /**
     * Data provider for {{@see test_get_matching_users}}
     *
     * @return array
     */
    public function fields_data_provider(): array {
        return [
            'nomatch' => ['lastname', 'Petrov', user_profile_field::TEXT_IS_EQUAL_TO, null, []],
            'nomatch1' => ['country', '', 'AO', null, []],
            'cities' => ['city', 'Lancaster', user_profile_field::TEXT_IS_EQUAL_TO, null, ['user0', 'user2']],
            'names' => ['firstname', 'Alex', user_profile_field::TEXT_IS_EQUAL_TO, null, ['user0', 'user1']],
            'countries' => ['country', '', 'GB', null, ['user2', 'user3']],
            'customtext' => ['textprivate', 'Hello', user_profile_field::TEXT_STARTS_WITH, null, ['user0', 'user1']],
            'customtext1' => ['textprivate', 'Hello world', user_profile_field::TEXT_IS_EQUAL_TO, null, ['user0']],
            'customdate' => ['dateprivate', '', user_profile_field::DATE_CURRENT,
                user_profile_field::TIME_DAY, ['user2', 'user3']],
            'customdate1' => ['dateprivate', '', user_profile_field::DATE_IS_IN_THE_PAST,
                user_profile_field::TIME_DAY, ['user1', 'user2', 'user3']],
            'custommenuopt1' => ['menupublic', '', 'OPTION1', null, ['user1', 'user2']],
            'custommenuopt2' => ['menupublic', '', 'OPTION2', null, ['user3']],
            'customcheckbox0' => ['checkboxpublic', '', 0, null, ['user1']],
            'customcheckbox1' => ['checkboxpublic', '', 1, null, ['user0']],
        ];
    }

    /**
     * Test getting matched users.
     *
     * @param string $fieldshortname
     * @param string $fieldvalue
     * @param mixed $fieldop
     * @param null|int $fieldop2
     * @param string[] $matchedusers
     *
     * @uses \tool_dynamicrule\api::count_matching_users
     * @uses \tool_dynamicrule\api::get_matching_users
     *
     * @dataProvider fields_data_provider
     */
    public function test_get_matching_users(string $fieldshortname, string $fieldvalue, $fieldop,
            ?int $fieldop2, array $matchedusers): void {

        // Create custom fields.
        $categoryid = $this->add_profile_category();
        // Custom text field defined for user0 and user1.
        $this->add_profile_field('text', $categoryid, 'textprivate', PROFILE_VISIBLE_PRIVATE);
        // Custom data field defined for user1, user2 and user3.
        $this->add_profile_field('datetime', $categoryid, 'dateprivate', PROFILE_VISIBLE_PRIVATE, date("Y"), date("Y"));
        // Custom menu field defined for user1, user2 and user3.
        $this->add_profile_field('menu', $categoryid, 'menupublic', PROFILE_VISIBLE_ALL, "OPTION1\nOPTION2\nOPTION3");
        // Custom checkbox field defined for user0 and user1.
        $this->add_profile_field('checkbox', $categoryid, 'checkboxpublic', PROFILE_VISIBLE_ALL);

        // Users.
        $users = [
            'user0' => $this->getDataGenerator()->create_user([
                'city' => 'Lancaster',
                'firstname' => 'Alex',
                'profile_field_textprivate' => 'Hello world',
                'profile_field_checkboxpublic' => 1,
            ]),
            'user1' => $this->getDataGenerator()->create_user([
                'firstname' => 'Alex',
                'profile_field_textprivate' => 'Hello friend',
                'profile_field_dateprivate' => strtotime('-1 day'),
                'profile_field_menupublic' => 'OPTION1',
                'profile_field_checkboxpublic' => 0,
            ]),
            'user2' => $this->getDataGenerator()->create_user([
                'country' => 'GB',
                'city' => 'Lancaster',
                'profile_field_dateprivate' => time(),
                'profile_field_menupublic' => 'OPTION1',
            ]),
            'user3' => $this->getDataGenerator()->create_user([
                'country' => 'GB',
                'profile_field_dateprivate' => time(),
                'profile_field_menupublic' => 'OPTION2',
            ]),
            'user4' => $this->getDataGenerator()->create_user(),
        ];

        // Rule with profile field condition.
        $rule0 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => $fieldvalue,
            $fieldshortname . '_op' => $fieldop, $fieldshortname . '_op2' => $fieldop2];
        user_profile_field::create($rule0->id, $configdata);

        // Test.
        $this->assertEquals(count($matchedusers), \tool_dynamicrule\api::count_matching_users($rule0->id));
        $matchingusers = \tool_dynamicrule\api::get_matching_users($rule0->id);

        $userids = array_map(function($key) use ($users) {
            return $users[$key]->id;
        }, $matchedusers);
        $this->assertEqualsCanonicalizing($userids, array_column($matchingusers, 'id'));
    }

    /**
     * Test user_enrolled event is triggering rule processing.
     *
     * @uses \tool_dynamicrule\event\observer
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\notification
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_trigger_rule_processing() {
        global $DB;

        $user0 = $this->getDataGenerator()->create_user();

        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $fieldshortname = 'city';
        // Check for value of field city equal to Perth.
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'Perth',
            $fieldshortname . '_op' => user_profile_field::TEXT_IS_EQUAL_TO];
        user_profile_field::create($rule0->id, $configdata);
        $configdata = ['subject' => 'User updated',
            'body' => ['text' => 'Congratulations, you updated your profile.', 'format' => FORMAT_MOODLE]];
        notification::create($rule0->id, $configdata);

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
        global $DB;

        $user0 = $this->getDataGenerator()->create_user();
        $user1 = $this->getDataGenerator()->create_user();
        $fieldshortname = 'city';

        $user0->city = 'El Catllar';
        $user0->password = 'Moodle1!';
        user_update_user($user0);

        // Check for value Contain.
        $rule0 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'arra',
            $fieldshortname . '_op' => user_profile_field::TEXT_CONTAINS];
        user_profile_field::create($rule0->id, $configdata);
        $this->assertCount(0, \tool_dynamicrule\api::get_matching_users($rule0->id));
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule0->id));
        $user0->city = 'Tarragona';
        user_update_user($user0);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule0->id));
        $this->assertCount(1, \tool_dynamicrule\api::get_matching_users($rule0->id));

        // Check for value Does not contain.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'arra',
            $fieldshortname . '_op' => user_profile_field::TEXT_DOES_NOT_CONTAIN];
        user_profile_field::create($rule1->id, $configdata);
        $this->assertCount(2, \tool_dynamicrule\api::get_matching_users($rule1->id));
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $user0->city = 'El Catllar';
        user_update_user($user0);
        $this->assertCount(3, \tool_dynamicrule\api::get_matching_users($rule1->id));
        $this->assertEquals(3, \tool_dynamicrule\api::count_matching_users($rule1->id));

        // Check for Equal to.
        $rule2 = $this->get_generator()->create_rule();
        $configdata = [
            'userprofilefield' => $fieldshortname,
            $fieldshortname . '_value' => 'Tarragona',
            $fieldshortname . '_op' => user_profile_field::TEXT_IS_EQUAL_TO
        ];
        user_profile_field::create($rule2->id, $configdata);
        $this->assertCount(0, \tool_dynamicrule\api::get_matching_users($rule2->id));
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule2->id));
        $user0->city = 'Tarragona';
        user_update_user($user0);
        $this->assertCount(1, \tool_dynamicrule\api::get_matching_users($rule2->id));
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule2->id));

        // Check for value Starts with.
        $rule3 = $this->get_generator()->create_rule();
        $fieldshortname = 'city';
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'Barce',
            $fieldshortname . '_op' => user_profile_field::TEXT_STARTS_WITH];
        user_profile_field::create($rule3->id, $configdata);
        $this->assertCount(0, \tool_dynamicrule\api::get_matching_users($rule3->id));
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule3->id));
        $user0->city = 'Barcelona';
        user_update_user($user0);
        $this->assertCount(1, \tool_dynamicrule\api::get_matching_users($rule3->id));
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule3->id));

        // Check for value Ends with.
        $rule4 = $this->get_generator()->create_rule();
        $fieldshortname = 'city';
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'abc',
            $fieldshortname . '_op' => user_profile_field::TEXT_ENDS_WITH];
        user_profile_field::create($rule4->id, $configdata);
        $this->assertCount(0, \tool_dynamicrule\api::get_matching_users($rule4->id));
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule4->id));
        $user0->city = 'qwertyabc';
        user_update_user($user0);
        $this->assertCount(1, \tool_dynamicrule\api::get_matching_users($rule4->id));
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule4->id));

        // Check for value Empty.
        $rule5 = $this->get_generator()->create_rule();
        $fieldshortname = 'city';
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '',
            $fieldshortname . '_op' => user_profile_field::TEXT_IS_EMPTY];
        user_profile_field::create($rule5->id, $configdata);
        $this->assertCount(2, \tool_dynamicrule\api::get_matching_users($rule5->id));
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule5->id));
        $user0->city = '';
        user_update_user($user0);
        $this->assertCount(3, \tool_dynamicrule\api::get_matching_users($rule5->id));
        $this->assertEquals(3, \tool_dynamicrule\api::count_matching_users($rule5->id));

        // Check for value Not Empty.
        $rule6 = $this->get_generator()->create_rule();
        $fieldshortname = 'city';
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '',
            $fieldshortname . '_op' => user_profile_field::TEXT_IS_NOT_EMPTY];
        user_profile_field::create($rule6->id, $configdata);
        $this->assertCount(0, \tool_dynamicrule\api::get_matching_users($rule6->id));
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule6->id));
        $user0->city = 'notempty';
        user_update_user($user0);
        $this->assertCount(1, \tool_dynamicrule\api::get_matching_users($rule6->id));
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule6->id));
        $user1->city = 'anotherone';
        $user1->password = 'Moodle1!';
        user_update_user($user1);
        $this->assertCount(2, \tool_dynamicrule\api::get_matching_users($rule6->id));
        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule6->id));

        // Test with a custom profile field.
        $fieldshortname = 'upf_field';
        $categoryid = $this->add_profile_category();
        $fieldid = $this->add_profile_field('text', $categoryid, $fieldshortname, $this->visibility);
        $DB->insert_record('user_info_data', (object)['userid' => $user0->id, 'fieldid' => $fieldid, 'data' => 'Hello world']);
        $rule7 = $this->get_generator()->create_rule();
        // Check Starts with.
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'Hello',
            $fieldshortname . '_op' => user_profile_field::TEXT_STARTS_WITH];
        user_profile_field::create($rule7->id, $configdata);
        $this->assertCount(1, \tool_dynamicrule\api::get_matching_users($rule7->id));
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
        global $DB;

        $user0 = $this->getDataGenerator()->create_user();
        $categoryid = $this->add_profile_category();

        $fieldshortname = 'somedatetime';
        $fieldid = $DB->insert_record('user_info_field', (object)['shortname' => $fieldshortname, 'name' => 'Example field',
            'categoryid' => $categoryid, 'datatype' => 'datetime', 'visible' => $this->visibility]);

        // Check for value Is not empty (op = 1).
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '',
            $fieldshortname . '_op' => user_profile_field::DATE_IS_NOT_EMPTY,
            $fieldshortname . '_op2' => user_profile_field::TIME_DAY];
        user_profile_field::create($rule1->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $id = $DB->insert_record('user_info_data', (object)['userid' => $user0->id, 'fieldid' => $fieldid, 'data' => time()]);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule1->id));

        // Check for value Is empty (op = 2).
        $rule2 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '',
            $fieldshortname . '_op' => user_profile_field::DATE_IS_EMPTY,
            $fieldshortname . '_op2' => user_profile_field::TIME_DAY];
        user_profile_field::create($rule2->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule2->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => '0']);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule2->id));

        // Check for value In the past (op = 3).
        $rule3 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '',
            $fieldshortname . '_op' => user_profile_field::DATE_IS_IN_THE_PAST,
            $fieldshortname . '_op2' => user_profile_field::TIME_DAY];
        user_profile_field::create($rule3->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule3->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => (time() + 3600)]);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule3->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => (time() - 3600)]);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule3->id));

        // Check for value In the future (op = 4).
        $rule4 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '',
            $fieldshortname . '_op' => user_profile_field::DATE_IS_IN_THE_FUTURE,
            $fieldshortname . '_op2' => user_profile_field::TIME_DAY];
        user_profile_field::create($rule4->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule4->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => (time() + 3600)]);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule4->id));

        // Check for value Last 7 days (op = 5) (value = 7).
        $rule5 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '7',
            $fieldshortname . '_op' => user_profile_field::DATE_LAST_X_DAYS,
            $fieldshortname . '_op2' => user_profile_field::TIME_DAY];
        user_profile_field::create($rule5->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule5->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => (time() - (6 * DAYSECS))]);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule5->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => (time() - (8 * DAYSECS))]);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule5->id));

        // Check for value Next 7 days (op = 6) (value = 7).
        $rule6 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '7',
            $fieldshortname . '_op' => user_profile_field::DATE_NEXT_X_DAYS,
            $fieldshortname . '_op2' => user_profile_field::TIME_DAY];
        user_profile_field::create($rule6->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule6->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => (time() + (6 * DAYSECS))]);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule6->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => (time() + (8 * DAYSECS))]);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule6->id));

        // Check for value Current (op = 7) day (op2 = 1).
        $rule7 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '',
            $fieldshortname . '_op' => user_profile_field::DATE_CURRENT, $fieldshortname . '_op2' => 1];
        user_profile_field::create($rule7->id, $configdata);
        $beginofday = strtotime("today", time());
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule7->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => $beginofday + 3600]);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule7->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => ($beginofday + (2 * DAYSECS))]);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule7->id));

        // Check for value Previous (op = 8) week (op2 = 2).
        $rule8 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '',
            $fieldshortname . '_op' => user_profile_field::DATE_PREVIOUS,
            $fieldshortname . '_op2' => user_profile_field::TIME_WEEK];
        user_profile_field::create($rule8->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule8->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => ($beginofday - (7 * DAYSECS))]);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule8->id));
        $DB->update_record('user_info_data', (object)['id' => $id, 'data' => ($beginofday - (16 * DAYSECS))]);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule8->id));

        // Check for value Upcoming (op = 9) week (op2 = 2).
        $rule9 = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => '',
            $fieldshortname . '_op' => user_profile_field::DATE_UPCOMING,
            $fieldshortname . '_op2' => user_profile_field::TIME_WEEK];
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
        global $DB;

        $user0 = self::getDataGenerator()->create_user();
        $categoryid = $this->add_profile_category();

        $fieldshortname = 'somemenu';
        $fieldid = $DB->insert_record('user_info_field', (object)['shortname' => $fieldshortname, 'name' => 'Example field',
            'categoryid' => $categoryid, 'datatype' => 'menu', 'param1' => "OPTION1\nOPTION2\nOPTION3",
            'visible' => $this->visibility]);

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
        global $DB;

        $user0 = $this->getDataGenerator()->create_user();
        $categoryid = $this->add_profile_category();

        $fieldshortname = 'somemenu';
        $fieldid = $DB->insert_record('user_info_field', (object)['shortname' => $fieldshortname, 'name' => 'Example field',
            'categoryid' => $categoryid, 'datatype' => 'checkbox', 'visible' => $this->visibility]);

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

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit(): void {
        global $DB;

        $user0 = self::getDataGenerator()->create_user();
        $user1 = self::getDataGenerator()->create_user();

        // Create a PROFILE_VISIBLE_ALL user profile field.
        $categoryid = $this->add_profile_category();
        $fieldshortname = 'upf_field_visible_all';
        $this->add_profile_field('text', $categoryid, $fieldshortname, PROFILE_VISIBLE_ALL);

        $configdata0 = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'Hello',
            $fieldshortname . '_op' => user_profile_field::TEXT_STARTS_WITH];

        // Both users should not be able to edit this field.
        self::setUser($user0);
        $this->assertFalse(user_profile_field::instance()->user_can_edit($configdata0));
        self::setUser($user1);
        $this->assertFalse(user_profile_field::instance()->user_can_edit($configdata0));

        // We assign tool/tenant:browseusers capability to both users.
        $roleid = create_role('browse teanant users', 'browseusers', 'browse tenant users');
        $context = context_system::instance();
        assign_capability('tool/tenant:browseusers', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user0->id, $context->id);
        role_assign($roleid, $user1->id, $context->id);

        // We assign user:viewalldetails capability to user1 only.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        $context = context_system::instance();
        assign_capability('moodle/user:viewalldetails', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user1->id, $context->id);

        // Both users should be able to see this field.
        self::setUser($user0);
        $this->assertTrue(user_profile_field::instance()->user_can_edit($configdata0));
        self::setUser($user1);
        $this->assertTrue(user_profile_field::instance()->user_can_edit($configdata0));

        // Create a PROFILE_VISIBLE_PRIVATE user profile field.
        $fieldshortname = 'upf_field_visible_private';
        $this->add_profile_field('text', $categoryid, $fieldshortname, PROFILE_VISIBLE_PRIVATE);

        $configdata1 = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'Hello',
            $fieldshortname . '_op' => user_profile_field::TEXT_STARTS_WITH];

        // Only user1 should be able to see this field.
        self::setUser($user0);
        $this->assertFalse(user_profile_field::instance()->user_can_edit($configdata1));
        self::setUser($user1);
        $this->assertTrue(user_profile_field::instance()->user_can_edit($configdata1));

        // Create a PROFILE_VISIBLE_NONE user profile field.
        $fieldshortname = 'upf_field_visible_none';
        $this->add_profile_field('text', $categoryid, $fieldshortname, PROFILE_VISIBLE_NONE);

        $configdata2 = ['userprofilefield' => $fieldshortname, $fieldshortname . '_value' => 'Hello',
            $fieldshortname . '_op' => user_profile_field::TEXT_STARTS_WITH];

        // Only user1 should be able to see this field.
        self::setUser($user0);
        $this->assertFalse(user_profile_field::instance()->user_can_edit($configdata2));
        self::setUser($user1);
        $this->assertTrue(user_profile_field::instance()->user_can_edit($configdata2));
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add(): void {
        $user0 = self::getDataGenerator()->create_user();
        $user1 = self::getDataGenerator()->create_user();

        // Create a PROFILE_VISIBLE_NONE user profile field.
        $categoryid = $this->add_profile_category();
        $fieldshortname = 'upf_field_visible_none';
        $this->add_profile_field('text', $categoryid, $fieldshortname, PROFILE_VISIBLE_NONE);

        // Both users should not be able to add this field.
        self::setUser($user0);
        $this->assertFalse(user_profile_field::instance()->user_can_add());
        self::setUser($user1);
        $this->assertFalse(user_profile_field::instance()->user_can_add());

        // We assign tool/tenant:browseusers capability to both users.
        $roleid = create_role('browse teanant users', 'browseusers', 'browse tenant users');
        $context = context_system::instance();
        assign_capability('tool/tenant:browseusers', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user0->id, $context->id);
        role_assign($roleid, $user1->id, $context->id);

        // We assign user:viewalldetails capability to user1 only.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        $context = context_system::instance();
        assign_capability('moodle/user:viewalldetails', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user1->id, $context->id);

        // Only user1 should be able to add this condition.
        self::setUser($user0);
        $this->assertFalse(user_profile_field::instance()->user_can_add());
        self::setUser($user1);
        $this->assertTrue(user_profile_field::instance()->user_can_add());

        // Create a PROFILE_VISIBLE_PRIVATE user profile field.
        $fieldshortname = 'upf_field_visible_private';
        $this->add_profile_field('text', $categoryid, $fieldshortname, PROFILE_VISIBLE_PRIVATE);

        // Only user1 should be able to add this condition.
        self::setUser($user0);
        $this->assertFalse(user_profile_field::instance()->user_can_add());
        self::setUser($user1);
        $this->assertTrue(user_profile_field::instance()->user_can_add());

        // Create a PROFILE_VISIBLE_ALL user profile field.
        $fieldshortname = 'upf_field_visible_all';
        $this->add_profile_field('text', $categoryid, $fieldshortname, PROFILE_VISIBLE_ALL);

        // Both users should be able to add this condition. User0 can add because there is at least one (this) public field.
        self::setUser($user0);
        $this->assertTrue(user_profile_field::instance()->user_can_add());
        self::setUser($user1);
        $this->assertTrue(user_profile_field::instance()->user_can_add());

        // Create another PROFILE_VISIBLE_PRIVATE user profile field.
        $fieldshortname = 'upf_field_visible_private_2';
        $this->add_profile_field('text', $categoryid, $fieldshortname, PROFILE_VISIBLE_PRIVATE);

        // User0 can add because there is at least one public field (upf_field_visible_all).
        self::setUser($user0);
        $this->assertTrue(user_profile_field::instance()->user_can_add());
        self::setUser($user1);
        $this->assertTrue(user_profile_field::instance()->user_can_add());
    }

    /**
     * Add dummy profile category.
     *
     * @return int The ID of the profile category
     */
    private function add_profile_category(): int {
        global $DB;
        return $DB->insert_record('user_info_category', ['name' => 'Test category']);
    }

    /**
     * Add dummy profile field.
     *
     * @param string $datatype
     * @param int $categoryid
     * @param string $shortname
     * @param int $visible
     * @param mixed $param1
     * @param mixed $param2
     * @return bool|int
     * @throws dml_exception
     */
    private function add_profile_field(
            string $datatype, int $categoryid, string $shortname, int $visible, $param1 = null, $param2 = null) {
        global $DB;

        $params = [
            'shortname' => $shortname,
            'name' => "Example field {$shortname}",
            'categoryid' => $categoryid,
            'datatype' => $datatype,
            'visible' => $visible,
            'param1' => $param1,
            'param2' => $param2,
        ];

        return $DB->insert_record('user_info_field', (object)$params);
    }
}
