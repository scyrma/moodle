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

namespace tool_dynamicrule;

use tool_dynamicrule\tool_dynamicrule\outcome\cohort;

/**
 * Unit tests for condition condition_base class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\outcome_base
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class outcome_test extends \advanced_testcase {

    /**
     * Get dynamic rule generator
     *
     * @return \tool_dynamicrule_generator
     */
    protected function get_generator(): \tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test get_displayed_description
     *
     * @uses tool_dynamicrule\tool_dynamicrule\outcome\cohort::get_description
     * @uses tool_dynamicrule\tool_dynamicrule\outcome\cohort::get_broken_description
     */
    public function test_get_displayed_description() {
        $context = \context_system::instance();
        $cohort1 = $this->getDataGenerator()->create_cohort();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['cohortid' => $cohort1->id];
        $outcome = cohort::create($rule1->id, $configdata);

        // Admin user.
        self::setAdminUser();
        $this->assertEquals($outcome->get_description(),
            $outcome->get_displayed_description());

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertEquals($outcome->get_uneditable_description(),
            $outcome->get_displayed_description());

        // Grant priveleges to user (moodle/cohort:assign).
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('moodle/cohort:assign', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        $this->assertEquals($outcome->get_description(),
            $outcome->get_displayed_description());
    }

    /**
     * Test get_displayed_description broken condition.
     *
     * @uses tool_dynamicrule\tool_dynamicrule\outcome\cohort::get_description
     * @uses tool_dynamicrule\tool_dynamicrule\outcome\cohort::get_broken_description
     */
    public function test_get_displayed_description_broken() {
        $context = \context_system::instance();
        $cohort1 = $this->getDataGenerator()->create_cohort();

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['cohortid' => $cohort1->id];
        $outcome = cohort::create($rule1->id, $configdata);
        $outcome->mark_as_broken();

        // Admin user.
        self::setAdminUser();
        $this->assertEquals($outcome->get_broken_description(),
            $outcome->get_displayed_description());

        // Non-priveleged user also see it as broken.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertEquals($outcome->get_broken_description(),
            $outcome->get_displayed_description());
    }
}
