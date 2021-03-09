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
 * Tests for the tool_tenant generator
 *
 * @package     tool_tenant
 * @category    test
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the tool_tenant generator
 *
 * @package    tool_tenant
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
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

    /**
     * Test for method create_tenant_and_users()
     */
    public function test_create_tenant_and_users() {
        global $DB;
        $this->resetAfterTest();

        list($tenant, $users) = $this->get_generator()->create_tenant_and_users(3);

        // Method returns a tenant that is not default tenant and 3 users.
        $this->assertEquals(3, count($users));
        $this->assertNotEquals(\tool_tenant\tenancy::get_default_tenant_id(), $tenant->id);
        $this->assertEquals(2, $DB->count_records('tool_tenant'));

        // All 3 users "belong" to the newly created tenant.
        $u0 = core_user::get_user($users[0]->id);
        $this->assertEquals($tenant->id, \tool_tenant\tenancy::get_tenant_id($u0->id));
        $u1 = core_user::get_user($users[1]->id);
        $this->assertEquals($tenant->id, \tool_tenant\tenancy::get_tenant_id($u1->id));
        $u2 = core_user::get_user($users[2]->id);
        $this->assertEquals($tenant->id, \tool_tenant\tenancy::get_tenant_id($u2->id));

        // The newly created tenant has 3 users.
        $where = \tool_tenant\tenancy::get_users_subquery(false, false, "u.id", $tenant->id);
        $tenantusers = $DB->get_fieldset_sql("SELECT u.id FROM {user} u WHERE $where ORDER BY id", []);
        $this->assertEquals([$users[0]->id, $users[1]->id, $users[2]->id], $tenantusers);
    }

    /**
     * Test for generator method create_user()
     */
    public function test_create_user() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/mnet/lib.php');

        $this->resetAfterTest();

        $tenant1 = $this->get_generator()->create_tenant();
        $tenant2 = $this->get_generator()->create_tenant();

        // Add a required, visible, unlocked custom field.
        $DB->insert_record('user_info_field', ['shortname' => 'house', 'name' => 'House', 'required' => 1,
            'visible' => 1, 'locked' => 0, 'categoryid' => 1, 'datatype' => 'text']);

        // Create some student accounts.
        $hermione = $this->get_generator()->create_user(['profile_field_house' => 'Gryffindor', 'tenantid' => $tenant1->id]);
        $harry = $this->get_generator()->create_user(['tenantid' => $tenant2->id]);
        $ron = $this->get_generator()->create_user();

        // Only students with required fields filled should be considered as fully set up.
        $this->assertFalse(user_not_fully_set_up($hermione));
        $this->assertTrue(user_not_fully_set_up($harry));

        // Test that the profile fields were actually set.
        $profilefields1 = profile_user_record($hermione->id);
        $this->assertEquals('Gryffindor', $profilefields1->house);

        $profilefields2 = profile_user_record($harry->id);
        $this->assertObjectHasAttribute('house', $profilefields2);
        $this->assertNull($profilefields2->house);

        // Assert user tenants.
        $this->assertEquals($tenant1->id, \tool_tenant\tenancy::get_tenant_id($hermione->id));
        $this->assertEquals($tenant2->id, \tool_tenant\tenancy::get_tenant_id($harry->id));
        $this->assertEquals(\tool_tenant\tenancy::get_default_tenant_id(), \tool_tenant\tenancy::get_tenant_id($ron->id));

        // Only one event "user_created" is triggered in create_user() function.
        $sink = $this->redirectEvents();
        $events = $sink->get_events();
        $drako = $this->get_generator()->create_user(['tenantid' => $tenant2->id]);
        $events = $sink->get_events();
        $sink->close();

        $this->assertEquals(1, count($events));
        $this->assertTrue($events[0] instanceof \core\event\user_created);
        $this->assertEquals($drako->id, $events[0]->objectid);

        // When we redirect events, the observers do not work :( and Drako is not allocated to the tenant.
        $this->assertEquals(\tool_tenant\tenancy::get_default_tenant_id(), \tool_tenant\tenancy::get_tenant_id($drako->id));
    }

    public function test_create_test_oauth2_issuer() {
        $this->resetAfterTest(true);
        $issuer = $this->get_generator()->create_test_oauth2_issuer(['name' => 'Test issuer']);
        $issuerid = $issuer->get('id');

        $this->assertNotEmpty($issuerid);
        $this->assertTrue($issuer->is_configured());
        $this->assertNotEmpty($issuer->get_endpoint_url('authorization'));
        $this->assertNotEmpty($issuer->get_endpoint_url('userinfo'));
        $this->assertNotEmpty($issuer->get_endpoint_url('token'));

        $wantsurl = '';
        $returnparams = ['wantsurl' => $wantsurl, 'sesskey' => sesskey(), 'id' => $issuerid];
        $returnurl = new moodle_url('/auth/oauth2/login.php', $returnparams);
        $client = \core\oauth2\api::get_user_oauth_client($issuer, $returnurl);

        // The get_userinfo_mapping method is protected. Use Reflection to call the method.
        $reflector = new ReflectionClass(\core\oauth2\client::class);
        $method = $reflector->getMethod('get_userinfo_mapping');
        $method->setAccessible(true);
        $mapping = $method->invokeArgs($client, []);
        $this->assertTrue(array_key_exists('email', $mapping));
    }
}
