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

namespace tool_tenant;

use advanced_testcase;
use context_system;
use tool_tenant_generator;

/**
 * Tests for the tool_tenant\role class methods.
 *
 * @package    tool_tenant
 * @covers     \tool_tenant\role
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class role_test extends advanced_testcase {

    /** @var tool_tenant_generator */
    protected $generator;

    /**
     * Set up
     */
    protected function setUp(): void {
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Provider for {@see self::test_get_exclude_tenant_roles_subquery}
     *
     * @return array[]
     */
    public function get_exclude_tenant_roles_subquery_provider() {
        $allroles = [
            ['user1', 'newrole', 'SYSTEM'],
            ['user1', 'tool_tenant_admin', 'SYSTEM'],
            ['user1', 'tool_tenant_manager', 'CATEGORY'],
            ['user1', 'tool_tenant_user', 'CATEGORY'],
            ['user2', 'tool_tenant_user', 'CATEGORY'],
            ['user3', 'tool_tenant_user', 'CATEGORY'],
        ];

        return [
            'All roles no subquery' => [
                '1=1',
                $allroles,
            ],
            'All roles without AND-postfix' => [
                \tool_tenant\role::get_exclude_tenant_roles_subquery([], 'ra.roleid', false),
                $allroles,
            ],
            'All roles with AND-postfix' => [
                \tool_tenant\role::get_exclude_tenant_roles_subquery([], 'ra.roleid', true) . '1=1',
                $allroles,
            ],
            'Exclude user role' => [
                \tool_tenant\role::get_exclude_tenant_roles_subquery(['user'], 'ra.roleid', false),
                [
                    ['user1', 'newrole', 'SYSTEM'],
                    ['user1', 'tool_tenant_admin', 'SYSTEM'],
                    ['user1', 'tool_tenant_manager', 'CATEGORY'],
                ]
            ],
            'Exclude user and admin role' => [
                \tool_tenant\role::get_exclude_tenant_roles_subquery(['user', 'admin'], 'ra.roleid', false),
                [
                    ['user1', 'newrole', 'SYSTEM'],
                    ['user1', 'tool_tenant_manager', 'CATEGORY'],
                ]
            ],
            'Exclude user and admin role without AND-postfix' => [
                \tool_tenant\role::get_exclude_tenant_roles_subquery(['user', 'admin'], 'ra.roleid', true) . '1=1',
                [
                    ['user1', 'newrole', 'SYSTEM'],
                    ['user1', 'tool_tenant_manager', 'CATEGORY'],
                ]
            ],
        ];
    }

    /**
     * Test function \tool_tenant\role::get_exclude_tenant_roles_subquery()
     *
     * @param string $where
     * @param array $expectedresult
     *
     * @dataProvider get_exclude_tenant_roles_subquery_provider
     */
    public function test_get_exclude_tenant_roles_subquery(string $where, array $expectedresult) {
        global $DB;
        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();
        $tenant1 = $this->generator->create_tenant(['categoryid' => $category->id]);
        $user1 = $this->generator->create_user(['tenantid' => $tenant1->id, 'tenantadmin' => true, 'username' => 'user1']);
        $user2 = $this->generator->create_user(['tenantid' => $tenant1->id, 'username' => 'user2']);
        $user3 = $this->generator->create_user(['tenantid' => $tenant1->id, 'username' => 'user3']);
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'newrole']);
        $this->getDataGenerator()->role_assign($roleid, $user1->id);

        $sql = "SELECT u.username, r.shortname, CASE WHEN ra.contextid = :syscontext THEN 'SYSTEM' ELSE 'CATEGORY' END as context
                      FROM {role_assignments} ra
                      JOIN {role} r ON r.id = ra.roleid
                      JOIN {user} u ON u.id = ra.userid
                           WHERE $where
                      ORDER BY username, shortname, context
                ";
        $res = [];
        $r = $DB->get_recordset_sql($sql, ['syscontext' => context_system::instance()->id]);
        foreach ($r as $record) {
            $res[] = array_values((array)$record);
        }
        $this->assertEquals($expectedresult, $res);
    }

    public function test_validate_assign_capability() {
        $this->resetAfterTest();

        try {
            assign_capability('moodle/site:config', CAP_ALLOW, manager::get_tenant_admin_role(), context_system::instance());
            $this->fail('Exception expected');
        } catch (\coding_exception $e) {
            $this->assertEquals('Coding error detected, it must be fixed by a programmer: '.
                'Capability "moodle/site:config" is not allowed in "Tenant administrator" role', $e->getMessage());
        }
    }
}
