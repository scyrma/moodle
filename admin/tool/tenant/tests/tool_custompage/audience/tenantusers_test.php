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

declare(strict_types=1);

namespace tool_tenant\tool_custompage\audience;

use advanced_testcase;
use context_system;
use core_reportbuilder\local\helpers\database;
use tool_custompage_generator;
use tool_tenant\tenancy;
use tool_tenant_generator;

/**
 * Specific tenant users audience tests
 *
 * @package     tool_tenant
 * @covers      \tool_tenant\tool_custompage\audience\tenantusers
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tenantusers_test extends advanced_testcase {

    /**
     * Test whether user can add this audience type
     */
    public function test_user_can_add(): void {
        global $DB;

        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenant = $tenantgenerator->create_tenant();

        $page = $generator->create_page(['name' => 'My page', 'weight' => -1, 'global' => true]);
        $pagetenant = $generator->create_page([
            'name' => 'My tenant page',
            'weight' => -1,
            'tenantid' => $tenant->id,
            'global' => false
        ]);
        $audienceintenant = tenantusers::create($pagetenant->get('id'), []);

        // This audience is only available for global pages.
        $this->assertFalse($audienceintenant->user_can_add(false));

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $audience = tenantusers::create($page->get('id'), []);
        $this->assertFalse($audience->user_can_add(true));

        // Grant required capability to use.
        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability('tool/tenant:manage', CAP_ALLOW, $userrole, context_system::instance()->id);
        role_assign($userrole, $user->id, context_system::instance()->id);

        $this->assertTrue($audience->user_can_add(true));
    }

    /**
     * Test whether user can edit this audience type
     */
    public function test_user_can_edit(): void {
        global $DB;

        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My page', 'weight' => -1]);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $audience = tenantusers::create($page->get('id'), [
            'criteria' => tenantusers::TENANT_ALL,
            'onlytenants' => [],
            'excepttenants' => []
        ]);
        $this->assertFalse($audience->user_can_edit());

        // Grant required capability to use.
        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability('tool/tenant:manage', CAP_ALLOW, $userrole, context_system::instance()->id);
        role_assign($userrole, $user->id, context_system::instance()->id);
        $this->assertTrue($audience->user_can_edit());
    }

    /**
     * Test retrieving SQL from this audience type
     */
    public function test_get_sql_users_all_tenants(): void {
        global $DB, $CFG;

        $this->resetAfterTest();

        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $defaulttenantid = tenancy::get_default_tenant_id();

        [$tenant1, $usersintenant1] = $tenantgenerator->create_tenant_and_users(5);
        [$tenant2, $usersintenant2] = $tenantgenerator->create_tenant_and_users(10);
        $userdefault = $this->getDataGenerator()->create_user();
        $guestuser = database::generate_param_name();
        $whereinativeusers = " AND u.suspended = 0 AND u.deleted = 0 AND u.id <> :{$guestuser}";

        // Userdefault is allocated to the default tenant.
        $tenantgenerator->allocate_user((int)$userdefault->id, $defaulttenantid);

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My page', 'weight' => -1]);

        // Create tenant audience with all tenants criteria and get users.
        $audiencealltenants = tenantusers::create($page->get('id'), [
            'criteria' => tenantusers::TENANT_ALL,
            'onlytenants' => [],
            'excepttenants' => []
        ]);
        [$join, $where, $params] = $audiencealltenants->get_sql('u');
        // Exclude not functional users for all audiences.
        $where .= $whereinativeusers;
        $params = $params + [$guestuser => $CFG->siteguest];
        $users = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$join} WHERE {$where}", $params);
        $usersinall = array_column(array_merge($usersintenant1, $usersintenant2), 'id');
        $usersinall[] = get_admin()->id;
        $usersinall[] = $userdefault->id;
        $this->assertEqualsCanonicalizing($usersinall, $users);
    }

    /**
     * Test retrieving SQL from this audience type
     */
    public function test_get_sql_users_specific_tenants(): void {
        global $DB;

        $this->resetAfterTest();

        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $defaulttenantid = tenancy::get_default_tenant_id();

        [$tenant1, $usersintenant1] = $tenantgenerator->create_tenant_and_users(5);
        [$tenant2, $usersintenant2] = $tenantgenerator->create_tenant_and_users(10);
        $userdefault = $this->getDataGenerator()->create_user();
        $tenantids = [$tenant1->id, $tenant2->id];
        $guestuser = database::generate_param_name();

        // Userdefault is allocated to the default tenant.
        $tenantgenerator->allocate_user((int)$userdefault->id, $defaulttenantid);

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My page', 'weight' => -1]);

        // Create tenant audience with tenant1 selected and get users in that tenant only.
        $audiencetenant1 = tenantusers::create($page->get('id'), [
            'criteria' => tenantusers::TENANT_ONLY,
            'onlytenants' => [$tenant1->id],
            'excepttenants' => []
        ]);
        [$join, $where, $params] = $audiencetenant1->get_sql('u');
        $users = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$join} WHERE {$where}", $params);
        $this->assertEqualsCanonicalizing(array_column($usersintenant1, 'id'), $users);

        // Create tenant audience with two tenants selected and get users in both tenant.
        $audiencetenant2 = tenantusers::create($page->get('id'), [
            'criteria' => tenantusers::TENANT_ONLY,
            'onlytenants' => $tenantids,
            'excepttenants' => []
        ]);
        [$join, $where, $params] = $audiencetenant2->get_sql('u');
        $usersinbothtenants = array_merge(array_column($usersintenant1, 'id'), array_column($usersintenant2, 'id'));
        $users = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$join} WHERE {$where}", $params);
        $this->assertEqualsCanonicalizing($usersinbothtenants, $users);
    }

    /**
     * Test retrieving SQL from this audience type
     */
    public function test_get_sql_users_except_specific_tenanct(): void {
        global $DB, $CFG;

        $this->resetAfterTest();

        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $defaulttenantid = tenancy::get_default_tenant_id();

        [$tenant1, $usersintenant1] = $tenantgenerator->create_tenant_and_users(5);
        [$tenant2, $usersintenant2] = $tenantgenerator->create_tenant_and_users(10);
        $userdefault = $this->getDataGenerator()->create_user();
        $tenantids = [$tenant1->id, $tenant2->id];
        $guestuser = database::generate_param_name();
        $whereinativeusers = " AND u.suspended = 0 AND u.deleted = 0 AND u.id <> :{$guestuser}";

        // Userdefault is allocated to the default tenant.
        $tenantgenerator->allocate_user((int)$userdefault->id, $defaulttenantid);

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My page', 'weight' => -1]);

        // Create tenant audience with all except tenant1 selected and get users of all others tenant.
        $audiencewithouttenant1 = tenantusers::create($page->get('id'), [
            'criteria' => tenantusers::TENANT_EXCEPT,
            'onlytenants' => [],
            'excepttenants' => [$tenant1->id]
        ]);
        [$join, $where, $params] = $audiencewithouttenant1->get_sql('u');
        // Exclude not functional users for all audiences.
        $where .= $whereinativeusers;
        $params = $params + [$guestuser => $CFG->siteguest];
        $users = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$join} WHERE {$where}", $params);
        $usernotintenant1 = array_column($usersintenant2, 'id');
        $usernotintenant1[] = get_admin()->id;
        $usernotintenant1[] = $userdefault->id;
        $this->assertEqualsCanonicalizing($usernotintenant1, $users);

        // Create tenant audience with all except tenant1 and tenant2 selected (just return users in default tenant).
        $audiencewithouttenant2 = tenantusers::create($page->get('id'), [
            'criteria' => tenantusers::TENANT_EXCEPT,
            'onlytenants' => [],
            'excepttenants' => $tenantids
        ]);
        [$join, $where, $params] = $audiencewithouttenant2->get_sql('u');
        // Exclude not functional users for all audiences.
        $where .= $whereinativeusers;
        $params = $params + [$guestuser => $CFG->siteguest];
        $users = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$join} WHERE {$where}", $params);
        $this->assertEqualsCanonicalizing([get_admin()->id, $userdefault->id], $users);
    }

    /**
     * Test retrieving description in all tenants for this audience
     */
    public function test_get_description_all_tenants(): void {

        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My page', 'weight' => -1]);

        // Create tenant audience for all tenants and get description.
        $audience = tenantusers::create($page->get('id'), [
            'criteria' => tenantusers::TENANT_ALL,
            'onlytenants' => [],
            'excepttenants' => []
        ]);
        $this->assertEquals(get_string('alltenantsselected', 'tool_tenant'), $audience->get_description());
    }

    /**
     * Test retrieving description for specific tenants in this audience
     */
    public function test_get_description_specific_tenants(): void {

        $this->resetAfterTest();

        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        [$tenant1] = $tenantgenerator->create_tenant_and_users(0);
        [$tenant2] = $tenantgenerator->create_tenant_and_users(0);

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My page', 'weight' => -1]);

        // Create tenant audience with one selected tenant and get description.
        $audience1 = tenantusers::create($page->get('id'), [
            'criteria' => tenantusers::TENANT_ONLY,
            'onlytenants' => [$tenant1->id],
            'excepttenants' => []
        ]);
        $this->assertEquals(get_string('tenantsselecteddesc', 'tool_tenant', $tenant1->name), $audience1->get_description());

        $tenantids = array($tenant1->id, $tenant2->id);
        $tenantnames = implode(", ", array_map(function(string $tenantid) {
            return tenancy::get_tenant_name_from_id((int) $tenantid);
        }, $tenantids));

        // Create tenant audience with group of selected tenant and get description.
        $audience3 = tenantusers::create($page->get('id'), [
            'criteria' => tenantusers::TENANT_ONLY,
            'onlytenants' => $tenantids,
            'excepttenants' => []
        ]);
        $this->assertEquals(get_string('tenantsselecteddesc', 'tool_tenant', $tenantnames), $audience3->get_description());
    }

    /**
     * Test retrieving description for this audience
     */
    public function test_get_description_except_specific_tenants(): void {

        $this->resetAfterTest();

        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        [$tenant1] = $tenantgenerator->create_tenant_and_users(0);
        [$tenant2] = $tenantgenerator->create_tenant_and_users(0);

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My page', 'weight' => -1]);

        // Create tenant audience with all except selected tenant and get description.
        $audience2 = tenantusers::create($page->get('id'), [
            'criteria' => tenantusers::TENANT_EXCEPT,
            'onlytenants' => [],
            'excepttenants' => [$tenant2->id]
        ]);
        $this->assertEquals(get_string('tenantsexceptselecteddesc', 'tool_tenant', $tenant2->name), $audience2->get_description());

        $tenantids = array($tenant1->id, $tenant2->id);
        $tenantnames = implode(", ", array_map(function(string $tenantid) {
            return tenancy::get_tenant_name_from_id((int) $tenantid);
        }, $tenantids));

        // Create tenant audience with all except a group of selected tenant and get description.
        $audience4 = tenantusers::create($page->get('id'), [
            'criteria' => tenantusers::TENANT_EXCEPT,
            'onlytenants' => [],
            'excepttenants' => $tenantids
        ]);
        $this->assertEquals(get_string('tenantsexceptselecteddesc', 'tool_tenant', $tenantnames), $audience4->get_description());

    }
}
