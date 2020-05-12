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
 * File containing tests for permission class
 *
 * @package     tool_dynamicrule
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_dynamicrule\permission;

/**
 * Test class
 *
 * @package     tool_dynamicrule
 * @group       tool_dynamicrule
 * @category    test
 * @covers      \tool_dynamicrule\permission
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_permission_testcase extends advanced_testcase {

    /**
     * Test setup
     *
     * @return void
     */
    public function setUp() {
        $this->resetAfterTest();
    }

    /**
     * Test can_create_rule method for user with capability to do so
     *
     * @return void
     */
    public function test_can_create_rule() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        role_change_permission($roleid, context_system::instance(), 'tool/dynamicrule:manage', CAP_ALLOW);

        $this->assertTrue(permission::can_create_rule());
    }

    /**
     * Test can_create_rule method for user without capability to do so
     *
     * @return void
     */
    public function test_can_create_rule_no_capability() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertFalse(permission::can_create_rule());
    }

    /**
     * Test can_create_rule method while observing site/tenant limits
     *
     * @return void
     */
    public function test_can_create_observe_limits() {
        global $DB, $CFG;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        role_change_permission($roleid, context_system::instance(), 'tool/dynamicrule:manage', CAP_ALLOW);

        $anothertenant = $this->get_tenant_generator()->create_tenant();

        // Test with site limit set to 0 and limits disabled.
        $CFG->tool_dynamicrule_limitsenabled = false;
        $CFG->tool_dynamicrule_sitelimit = 0;
        $this->assertTrue(permission::can_create_rule());

        // Enable limits.
        $CFG->tool_dynamicrule_limitsenabled = true;
        $this->assertFalse(permission::can_create_rule());

        // Test with limits ignored.
        $this->assertTrue(permission::can_create_rule(true));

        // Set site limit to two.
        $CFG->tool_dynamicrule_sitelimit = 2;
        $this->assertTrue(permission::can_create_rule());

        // Create a rule.
        $this->get_plugin_generator()->create_rule();
        $this->assertTrue(permission::can_create_rule());

        // Create a rule in another tenant.
        $this->get_plugin_generator()->create_rule(['tenantid' => $anothertenant->id]);
        $this->assertFalse(permission::can_create_rule());

        // Test with limits ignore.
        $this->assertTrue(permission::can_create_rule(true));

        // Test with tenant limit set to 0 and limits disabled.
        $CFG->tool_dynamicrule_limitsenabled = false;
        unset($CFG->tool_dynamicrule_sitelimit);
        $CFG->tool_dynamicrule_tenantlimit = 0;
        $this->assertTrue(permission::can_create_rule());

        // Enable limits.
        $CFG->tool_dynamicrule_limitsenabled = true;
        $this->assertFalse(permission::can_create_rule());

        // Test with limits ignored.
        $this->assertTrue(permission::can_create_rule(true));

        // Set tenant limit to 2.
        $CFG->tool_dynamicrule_tenantlimit = 2;

        // Current tenant only has one rule, so user should be able to create another.
        $this->assertTrue(permission::can_create_rule());

        // Create second rule and test.
        $this->get_plugin_generator()->create_rule();
        $this->assertFalse(permission::can_create_rule());

        // Test with limits ignore.
        $this->assertTrue(permission::can_create_rule(true));
    }

    /**
     * Get plugin test generator
     *
     * @return tool_dynamicrule_generator
     */
    private function get_plugin_generator() : tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Get tenant test generator
     *
     * @return tool_tenant_generator
     */
    private function get_tenant_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }
}