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
        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        global $DB;

        $this->resetAfterTest();

        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field();
        $configform = ['userprofilefield' => 'invalidfield'];
        $this->assertArrayHasKey('userprofilefield', $condition->validate_config_form($configform));

        // Add a custom field of text type.
        $categoryid = $DB->insert_record('user_info_category', ['name' => 't']);
        $DB->insert_record('user_info_field', array(
                'shortname' => 'frogdesc', 'name' => 'Description of frog', 'categoryid' => $categoryid,
                'datatype' => 'text'));

        $configform = ['userprofilefield' => 'frogdesc'];
        $this->assertArrayNotHasKey('userprofilefield', $condition->validate_config_form($configform));
    }

    /**
     * Test get_description
     */
    public function test_get_description() {
        global $DB;

        $this->resetAfterTest();

        $rule1 = $this->get_generator()->create_rule();
        $configdata1 = ['userprofilefield' => 'city', 'userprofilefieldvalue' => 'Perth'];
        $condition1 = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule1->id, $configdata1);

        $a = (object)['fieldname' => get_string('city'), 'fieldvalue' => 'Perth'];
        $this->assertEquals(get_string('conditionuserprofilefielddescription', 'tool_dynamicrule', $a),
                            $condition1->get_description());

        // Add a custom field of text type.
        $fieldshortname = 'someinfo';
        $fieldname = 'Example field';
        $categoryid = $DB->insert_record('user_info_category', ['name' => 't']);
        $fieldid = $DB->insert_record('user_info_field', array('shortname' => $fieldshortname, 'name' => $fieldname,
                                                               'categoryid' => $categoryid, 'datatype' => 'text'));

        $rule3 = $this->get_generator()->create_rule();
        $configdata2 = ['userprofilefield' => $fieldshortname, 'userprofilefieldvalue' => 'Example value'];
        $condition3 = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule3->id, $configdata2);

        $a = (object)['fieldname' => $fieldname, 'fieldvalue' => 'Example value'];
        $this->assertEquals(get_string('conditionuserprofilefielddescription', 'tool_dynamicrule', $a),
                            $condition3->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid() {
        global $DB;

        $this->resetAfterTest();

        $fieldshortname = 'someinfo';
        $categoryid = $DB->insert_record('user_info_category', ['name' => 't']);
        $fieldid = $DB->insert_record('user_info_field', array('shortname' => $fieldshortname, 'name' => 'Example field',
                                                               'categoryid' => $categoryid, 'datatype' => 'text'));

        $rule = $this->get_generator()->create_rule();
        $configdata = ['userprofilefield' => $fieldshortname, 'userprofilefieldvalue' => 'Example value'];
        $condition = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule->id, $configdata);

        $this->assertTrue($condition->is_configuration_valid());

        $DB->delete_records('user_info_field', ['id' => $fieldid]);

        $this->assertFalse($condition->is_configuration_valid());

        $configdata = [];
        $condition = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule->id, $configdata);

        $this->assertFalse($condition->is_configuration_valid());
        $this->assertNull($condition->get_userprofilefieldvalue());

        $configdata = ['userprofilefield' => 'invalid'];
        $condition = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule->id, $configdata);

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
        $configdata = ['userprofilefield' => 'city', 'userprofilefieldvalue' => 'Perth'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rule0->id, $configdata);
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
}
