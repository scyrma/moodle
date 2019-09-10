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
 * Schedules external services tests.
 *
 * @package   tool_reportbuilder
 * @category test
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Class tool_reportbuilder_external_schedule_testcase
 *
 * @package   tool_reportbuilder
 * @covers    \tool_reportbuilder\external\schedule
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_external_schedule_testcase extends externallib_advanced_testcase {

    /**
     * Test for funciton get_tenant_id()
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws invalid_response_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public function test_delete_schedule() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        /** @var tool_reportbuilder_generator $generator */
        $generator = $this->get_generator();

        // Test with user with permissions.
        $newschedule = $generator->create_schedule([]);
        $this->assertEquals(1, $DB->count_records('tool_reportbuilder_scheduled'));
        $result = tool_reportbuilder\external\schedule::delete_schedule($newschedule->id);
        external_api::clean_returnvalue(tool_reportbuilder\external\schedule::delete_schedule_returns(), $result);
        $this->assertEquals(0, $DB->count_records('tool_reportbuilder_scheduled'));

        // Test with user without permissions.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $newschedule = $generator->create_schedule([]);
        $this->assertEquals(1, $DB->count_records('tool_reportbuilder_scheduled'));
        $this->expectException(moodle_exception::class);
        tool_reportbuilder\external\schedule::delete_schedule($newschedule->id);

        // Try delete a schedule of other tenant.
        $tenant1 = $this->get_tenant_generator()->create_tenant();
        $tenant2 = $this->get_tenant_generator()->create_tenant();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->get_tenant_generator()->allocate_user($user1->id, $tenant1->id);
        $this->get_tenant_generator()->allocate_user($user2->id, $tenant2->id);

        $this->setUser($user1->id);
        $newschedule = $generator->create_schedule([]);
        $this->setUser($user2->id);

        $this->expectException(moodle_exception::class);
        tool_reportbuilder\external\schedule::delete_schedule($newschedule->id);
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     * @throws coding_exception
     */
    protected function get_generator(): tool_reportbuilder_generator {
        /** @var tool_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
        return $generator;
    }

    /**
     * Get tenant generator
     *
     * @return tool_tenant_generator
     * @throws coding_exception
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        return $generator;
    }
}