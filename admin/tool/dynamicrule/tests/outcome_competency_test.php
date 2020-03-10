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
 * File contains the unit tests for outcome\competency class.
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
 * Unit tests for outcome\competency  class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     tool_dynamicrule\tool_dynamicrule\outcome\competency
 * @covers     \tool_dynamicrule\outcome_base
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_outcome_competency_testcase extends advanced_testcase {

    /**
     * Set up
     */
    public function setUp() {
        $this->resetAfterTest();
        $this->lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
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
        $outcome = new \tool_dynamicrule\tool_dynamicrule\outcome\competency();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $outcome = new \tool_dynamicrule\tool_dynamicrule\outcome\competency();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $outcome->get_category());
    }

    /**
     * Test apply_to_users
     *
     * @uses \testable_condition_alwaystrue
     */
    public function test_apply_to_users() {

        $this->setAdminUser();

        $rule0 = $this->get_generator()->create_rule();
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        $user = self::getDataGenerator()->create_user();
        $f1 = $this->lpg->create_framework();
        $c1 = $this->lpg->create_competency(array('competencyframeworkid' => $f1->get('id')));

        $configdata = ['competency' => $c1->get('id')];
        $outcome = \tool_dynamicrule\tool_dynamicrule\outcome\competency::create($rule0->id, $configdata);

        $userids = [$this->getDataGenerator()->create_user(), $this->getDataGenerator()->create_user()];
        $outcome->apply_to_users($userids);

        $this->assertEquals(2, \core_competency\user_competency::count_records());
    }

    /**
     * Test get_description.
     */
    public function test_get_description() {

        $shortname = 'Test competency 1';
        $f1 = $this->lpg->create_framework();
        $c1 = $this->lpg->create_competency(array('competencyframeworkid' => $f1->get('id'), 'shortname' => $shortname));

        $rule0 = $this->get_generator()->create_rule();

        $configdata = ['competency' => $c1->get('id')];
        $outcome = \tool_dynamicrule\tool_dynamicrule\outcome\competency::create($rule0->id, $configdata);

        $str = get_string('outcomecompetencydescription', 'tool_dynamicrule', $shortname);
        $this->assertEquals($str, $outcome->get_description());
    }
}
