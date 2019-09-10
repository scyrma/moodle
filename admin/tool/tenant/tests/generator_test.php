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
 * Tests for the tool_tenant generator
 *
 * @package     tool_tenant
 * @category    test
 * @copyright   2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the tool_tenant generator
 *
 * @package    tool_tenant
 * @copyright  2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_tenant_generator_testcase extends advanced_testcase {

    /**
     * Get tenant generator
     * @return tool_tenant_generator
     */
    protected function get_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Create tenant
     */
    public function test_create_tenant() {
        global $DB;
        $this->resetAfterTest();

        // As soon as we request anything from tenancy there is a default tenant.
        \tool_tenant\tenancy::get_default_tenant_id();
        $this->assertEquals(1, $DB->count_records('tool_tenant'));

        // Create new tenant.
        $tenant1 = $this->get_generator()->create_tenant();
        $this->assertEquals(2, $DB->count_records('tool_tenant'));

        // Create another tenant, it will have different name.
        $tenant2 = $this->get_generator()->create_tenant();
        $this->assertEquals(3, $DB->count_records('tool_tenant'));
        $this->assertNotEquals($tenant1->name, $tenant2->name);

        // Create a tenant with a given name.
        $tenant3 = $this->get_generator()->create_tenant(['name' => 'My favourite tenant']);
        $this->assertEquals('My favourite tenant', $tenant3->name);
        $this->assertEquals('My favourite tenant', $DB->get_field('tool_tenant', 'name', ['id' => $tenant3->id]));
    }
}
