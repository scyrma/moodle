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

namespace tool_custompage\tool_custompage\audience;

use advanced_testcase;
use context_coursecat;
use context_system;
use tool_custompage_generator;
use tool_tenant\tenancy;
use tool_tenant_generator;

/**
 * Category role audience type tests
 *
 * @package     tool_custompage
 * @covers      \tool_custompage\tool_custompage\audience\categoryrole
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class categoryrole_test extends advanced_testcase {

    /**
     * Test whether user can add this audience type
     */
    public function test_user_can_add(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // We need to create a category and relate it with a new tenant.
        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $cat = $this->getDataGenerator()->create_category(['name' => 'Tenant category']);
        [$tenant, [$user]] = $tenantgenerator->create_tenant_and_users(1, ['categoryid' => $cat->id]);

        // Create another tenant without any category assigned.
        [$tenant2, [$user2]] = $tenantgenerator->create_tenant_and_users(1);

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');

        // Create tenant pages with valid and not assigned category in tenant.
        $pagetenantwithoutcat = $generator->create_page([
            'name' => 'My tenant page 1',
            'weight' => -1,
            'global' => 0,
            'tenantid' => $tenant2->id
        ]);
        $pagetenant = $generator->create_page([
            'name' => 'My tenant page 2',
            'weight' => -1,
            'global' => 0,
            'tenantid' => $tenant->id
        ]);

        // Create global page.
        $page = $generator->create_page(['name' => 'My global page', 'weight' => -1, 'global' => 1]);

        // Create audience instances for each page to be tested.
        $audience = categoryrole::create($page->get('id'), []);
        $audiencetenant = categoryrole::create($pagetenant->get('id'), []);
        $audiencetenantwithoutcat = categoryrole::create($pagetenantwithoutcat->get('id'), []);

        // Global pages can't add this audience.
        $this->assertFalse($audience->user_can_add(true));

        // Switch to tenant without category assigned.
        tenancy::set_switched_tenant_id($tenant2->id);
        $this->setUser($user2);

        // User can't add category role audience in current tenant.
        $this->assertFalse($audiencetenantwithoutcat->user_can_add());

        // Switch to tenant with valid category assigned.
        tenancy::set_switched_tenant_id($tenant->id);
        $this->setUser($user);

        // User can't add audience because doesn't have required capability.
        $this->assertFalse($audiencetenant->user_can_add());

        // Assign role with required capability to user in order to allow add this audience.
        $managerrole = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerrole, $user->id, context_system::instance()->id);

        $this->assertTrue($audiencetenant->user_can_add());
    }

    /**
     * Test whether user can edit this audience type
     */
    public function test_user_can_edit(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // We need to create a category and relate it with a new tenant.
        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $cat = $this->getDataGenerator()->create_category(['name' => 'Tenant category']);
        [$tenant, [$user]] = $generator->create_tenant_and_users(1, ['categoryid' => $cat->id]);

        // Create page.
        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My page', 'weight' => -1, 'tenantid' => $tenant->id]);

        // Switch to tenant create above and set the user in that tenant.
        tenancy::set_switched_tenant_id($tenant->id);
        $managerrole = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        $audience = categoryrole::create($page->get('id'), ['roles' => [$managerrole]]);
        $this->setUser($user);

        $this->assertFalse($audience->user_can_edit());

        // Grant required capability to add this audience.
        role_assign($managerrole, $user->id, context_system::instance()->id);

        $this->assertTrue($audience->user_can_edit());
    }

    /**
     * Test retrieving SQL from this audience type
     */
    public function test_get_sql(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // We need to create a category and relate it with a new tenant.
        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $cat = $this->getDataGenerator()->create_category(['name' => 'Tenant category']);
        $subcat = $this->getDataGenerator()->create_category(['name' => 'Tenant sub category', 'parent' => $cat->id]);
        [$tenant, [$user1, $user2]] = $generator->create_tenant_and_users(2, ['categoryid' => $cat->id]);

        // Assign a valid role inside tenant category (top) previously created.
        $categorycontext = context_coursecat::instance($tenant->categoryid);
        $assignablerole = get_assignable_roles($categorycontext, ROLENAME_SHORT);
        $validrole = $DB->get_field('role', 'id', ['shortname' => reset($assignablerole)], MUST_EXIST);
        role_assign($validrole, $user1->id, $categorycontext->id);

        // Get a valid role inside tenant category (child) previously created.
        $categorychildontext = context_coursecat::instance($subcat->id);
        $assignablerolechild = get_assignable_roles($categorychildontext, ROLENAME_SHORT);
        $validchildrole = $DB->get_field('role', 'id', ['shortname' => reset($assignablerolechild)], MUST_EXIST);

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $pagewhitoutcat = $generator->create_page(['name' => 'My page without tenant cat', 'weight' => -1]);
        $pagewithcat = $generator->create_page([
            'name' => 'My page with tenant cat',
            'weight' => -1,
            'global' => 0,
            'tenantid' => $tenant->id
        ]);

        // Custom tenant pages without valid tenant category, return empty.
        $audienceempty = categoryrole::create($pagewhitoutcat->get('id'), ['roles' => [$validrole]]);
        [$join, $where, $params] = $audienceempty->get_sql('u');
        $usersempty = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$join} WHERE {$where}", $params);
        $this->assertEmpty($usersempty);

        // Return users with a valid tenant category(top) role assigned.
        $audience = categoryrole::create($pagewithcat->get('id'), ['roles' => [$validrole]]);
        [$join, $where, $params] = $audience->get_sql('u');
        $users = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$join} WHERE {$where}", $params);
        $this->assertEquals([$user1->id], $users);

        // Return users with a valid tenant category(top and child) role assigned.
        role_assign($validchildrole, $user2->id, $categorychildontext->id);
        $users = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$join} WHERE {$where}", $params);
        $this->assertEquals([$user1->id, $user2->id], $users);
    }

    /**
     * Test retrieving description for this audience
     */
    public function test_get_description(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_tenant_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $cat = $this->getDataGenerator()->create_category(['name' => 'Tenant category']);
        [$tenant, [$user1, $user2]] = $generator->create_tenant_and_users(2, ['categoryid' => $cat->id]);

        // Switch to tenant with valid category assigned.
        tenancy::set_switched_tenant_id($tenant->id);

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My page', 'weight' => -1, 'tenantid' => $tenant->id]);

        $managerrole = $DB->get_record('role', ['shortname' => 'manager'], '*', MUST_EXIST);

        $audience = categoryrole::create($page->get('id'), ['roles' => [$managerrole->id]]);
        $this->assertEquals(role_get_name($managerrole, context_system::instance()), $audience->get_description());
    }
}
