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
 * File contains the unit tests for condition cohort_not_member class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for condition cohort_not_member  class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @copyright  2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dynamicrule_condition_cohort_not_member_testcase extends advanced_testcase {

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
        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\cohort_not_member();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\cohort_not_member();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        $this->resetAfterTest();

        $condition = new \tool_dynamicrule\tool_dynamicrule\condition\cohort_not_member();
        $configform = ['cohortid' => 0];
        $this->assertArrayHasKey('cohortid', $condition->validate_config_form($configform));
    }

    /**
     * Test condition matching
     */
    public function test_get_matching_users() {

        $this->resetAfterTest();

        $cohort1 = $this->getDataGenerator()->create_cohort();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort1->id, $user2->id);

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['cohortid' => $cohort1->id];
        \tool_dynamicrule\tool_dynamicrule\condition\cohort_not_member::create($rule1->id, $configdata);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEquals([get_admin()->id, $user3->id], array_column($users, 'id'), '', 0, 10, true);
    }

    /**
     * Test get_description
     */
    public function test_get_description() {

        $this->resetAfterTest();

        $cohort1 = $this->getDataGenerator()->create_cohort();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['cohortid' => $cohort1->id];
        $condition1 = \tool_dynamicrule\tool_dynamicrule\condition\cohort_not_member::create($rule1->id, $configdata);

        $this->assertEquals(get_string('conditioncohortnotmemberdescription', 'tool_dynamicrule', $cohort1->name),
                            $condition1->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid() {
        global $DB;

        $this->resetAfterTest();

        $cohort1 = $this->getDataGenerator()->create_cohort();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['cohortid' => $cohort1->id];
        $condition1 = \tool_dynamicrule\tool_dynamicrule\condition\cohort_not_member::create($rule1->id, $configdata);

        $this->assertTrue($condition1->is_configuration_valid());

        $DB->delete_records('cohort', ['id' => $cohort1->id]);

        $this->assertFalse($condition1->is_configuration_valid());
    }

    /**
     * Test is_available.
     */
    public function test_is_available() {
        $this->resetAfterTest();

        $this->assertFalse(\tool_dynamicrule\tool_dynamicrule\condition\cohort_not_member::is_available());

        $this->getDataGenerator()->create_cohort();

        $this->assertTrue(\tool_dynamicrule\tool_dynamicrule\condition\cohort_not_member::is_available());
    }
}
