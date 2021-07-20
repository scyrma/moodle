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

/**
 * File containing tests for export/import user mapper class
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\mapper;

use tool_tenant\tenancy;
use tool_wp\local\exportimport\helper;

/**
 * Test class
 *
 * @package     tool_wp
 * @group       tool_wp
 * @category    test
 * @covers      \tool_wp\tool_wp\mapper\user
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_mapper_testcase extends \advanced_testcase {

    /**
     * Test mapper returns mapping data correctly for given user
     */
    public function test_get_mapping_data_for_workplace_export() {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user([
            'username' => 'myuser',
            'email' => 'myuser@example.com',
        ]);

        $mapper = helper::find_mapper_for_entity('user', helper::get_all_mappers());
        $this->assertInstanceOf(user::class, $mapper);

        $data = $mapper->get_mapping_data_for_workplace_export($user->id);
        $this->assertEquals([
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'tenantid' => tenancy::get_tenant_id($user->id),
        ], $data);
    }

    /**
     * Data provider for testing matching users
     *
     * @see test_locate_mapping_success
     *
     * @return array
     */
    public function locate_mapping_success_provider(): array {
        return [
            ['myuser', 'myuser@example.com', ['username' => 'myuser']],
            ['myuser', 'myuser@example.com', ['email' => 'myuser@example.com']],
        ];
    }

    /**
     * Test the mapper class successfully locates existing users
     *
     * @param string $username
     * @param string $email
     * @param array $identifier
     *
     * @dataProvider locate_mapping_success_provider
     */
    public function test_locate_mapping_success(string $username, string $email, array $identifier): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user([
            'username' => $username,
            'email' => $email,
        ]);

        $mapping = $this->get_plugin_generator()->locate_mapping('user', $identifier);
        $this->assertEquals([$user->id, [], [], true], $mapping);
    }

    /**
     * Test the mapper class successfully locates users in the current tenant where a matching user also exists in another tenant
     */
    public function test_locate_mapping_success_duplicate_identifier(): void {
        $this->resetAfterTest();

        list($tenant1, $users1) = $this->get_tenant_generator()->create_tenant_and_users(1);
        $tenant1user = reset($users1);

        list($tenant2, $users2) = $this->get_tenant_generator()->create_tenant_and_users(1);
        $tenant2user = reset($users2);

        $this->setUser($tenant2user);

        // Locate mapping using $tenant1 username (which user can't access) and $tenant2 email (which user can access).
        $this->assertEquals($tenant2user->id, $this->get_plugin_generator()->locate_mapping('user', [
            'username' => $tenant1user->username,
            'email' => $tenant2user->email,
        ])[0]);
    }

    /**
     * Tests the mapper class returns appropriate notice when locating a user by email, but username doesn't match
     */
    public function test_locate_mapping_notice() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user([
            'username' => 'myuser',
            'email' => 'myuser@example.com',
        ]);

        list($userid, $notices, $errors, $validated) = $this->get_plugin_generator()->locate_mapping('user', [
            'username' => 'myotheruser',
            'email' => $user->email,
        ]);
        $this->assertEquals($user->id, $userid);
        $this->assertCount(1, $notices);

        $userprofileurl = (new \moodle_url('/user/profile.php', ['id' => $userid]))->out();
        $this->assertEquals("A user with username 'myotheruser' was not found. <a href=\"{$userprofileurl}\">Another user</a>" .
            " with the email {$user->email} was found, but this user has a different username", reset($notices));

        $this->assertEmpty($errors);
        $this->assertTrue($validated);
    }

    /**
     * Data provider for testing non-matching users
     *
     * @see test_locate_mapping_error
     *
     * @return array
     */
    public function locate_mapping_error_provider(): array {
        return [
            ['myuser', 'myuser@example.com', ['username' => 'myotheruser']],
            ['myuser', 'myuser@example.com', ['username' => 'myotheruser', 'email' => 'myotheruser@example.com']],
            ['myuser', 'myuser@example.com', ['email' => 'myotheruser@example.com']],
        ];
    }

    /**
     * Test mapper returns errors for non-matching users
     *
     * @param string $username
     * @param string $email
     * @param array $identifier
     *
     * @dataProvider locate_mapping_error_provider
     */
    public function test_locate_mapping_error(string $username, string $email, array $identifier): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->getDataGenerator()->create_user([
            'username' => $username,
            'email' => $email,
        ]);

        list($userid, $notices, $errors, $validated) = $this->get_plugin_generator()->locate_mapping('user', $identifier);
        $this->assertNull($userid);
        $this->assertEmpty($notices);
        $this->assertCount(1, $errors);

        if (!empty($identifier['username']) && !empty($identifier['email'])) {
            $identifierstring = "'{$identifier['username']}' ('{$identifier['email']}')";
        } else {
            $identifierstring = !empty($identifier['username']) ? "'{$identifier['username']}'" : "'{$identifier['email']}'";
        }
        $this->assertEquals("Could not find user {$identifierstring} in current tenant", reset($errors));

        $this->assertFalse($validated);
    }

    /**
     * Returns the plugin generator
     *
     * @return \tool_wp_generator
     */
    protected function get_plugin_generator(): \tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }

    /**
     * Returns the tenant generator
     *
     * @return \tool_tenant_generator
     */
    protected function get_tenant_generator(): \tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }
}
