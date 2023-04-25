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
use context_system;
use stdClass;
use tool_custompage_generator;
use tool_tenant\manager;
use tool_tenant_generator;

/**
 * Admin role audience type tests
 *
 * @package     tool_custompage
 * @covers      \tool_custompage\tool_custompage\audience\adminrole
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class adminrole_test extends advanced_testcase {

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

        $page = $generator->create_page(['name' => 'My page', 'weight' => -1, 'global' => 1]);
        $pagetenant = $generator->create_page([
            'name' => 'My tenant page',
            'weight' => -1,
            'tenantid' => $tenant->id,
            'global' => 0
        ]);
        $audienceintenant = adminrole::create($pagetenant->get('id'), []);

        // This audience is only available for global pages.
        $this->assertFalse($audienceintenant->user_can_add(false));

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $audience = adminrole::create($page->get('id'), []);
        $this->assertFalse($audience->user_can_add(true));

        // Grant required capability to use.
        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability('tool/custompage:editall', CAP_ALLOW, $userrole, context_system::instance()->id);
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

        $audience = adminrole::create($page->get('id'), ['tenants' => ['1'], 'criteria' => '1']);
        $this->assertFalse($audience->user_can_edit());

        // Grant required capability to use.
        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability('tool/custompage:editall', CAP_ALLOW, $userrole, context_system::instance()->id);
        role_assign($userrole, $user->id, context_system::instance()->id);
        $this->assertTrue($audience->user_can_edit());
    }

    /**
     * Test retrieving SQL from this audience type
     */
    public function test_get_sql(): void {
        global $DB, $CFG;

        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My adminrole page', 'weight' => -1, 'global' => 1]);

        $tenantadmin = $this->getDataGenerator()->create_user();

        // Grant required capability to use.
        $tenantadminrole = $DB->get_field('role', 'id', ['shortname' => 'tool_tenant_admin'], MUST_EXIST);
        role_assign($tenantadminrole, $tenantadmin->id, context_system::instance()->id);

        // Create two users and set them as a site admins.
        $admin1 = $this->getDataGenerator()->create_user();
        $admin2 = $this->getDataGenerator()->create_user();
        set_config('siteadmins', implode(',', [$admin1->id, $admin2->id]));

        // Get admin users.
        $adminusers = explode(',', $CFG->siteadmins);

        $audience = adminrole::create($page->get('id'), ['roles' => 1]);
        [$join, $where, $params] = $audience->get_sql('u');
        $usersadmins = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$join} WHERE {$where}", $params);
        $this->assertEquals($adminusers, $usersadmins);

        // Retrieve only tenant admins.
        $tenantamdinrole = new stdClass();
        $tenantamdinrole->id = manager::get_tenant_admin_role();
        $audience = adminrole::create($page->get('id'), ['roles' => $tenantamdinrole->id]);
        [$join, $where, $params] = $audience->get_sql('u');
        $userstenantadmin = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$join} WHERE {$where}", $params);
        $this->assertEquals([$tenantadmin->id], $userstenantadmin);

        // Retrieve all admins defined in audience.
        $audience = adminrole::create($page->get('id'), ['roles' => 0]);
        [$join, $where, $params] = $audience->get_sql('u');
        $usersall = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$join} WHERE {$where}", $params);
        $adminusers[] = $tenantadmin->id;
        $this->assertEqualsCanonicalizing($adminusers, $usersall);
    }

    /**
     * Test retrieving description for this audience
     */
    public function test_get_description(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My adminrole page', 'weight' => -1]);

        $audience = adminrole::create($page->get('id'), ['roles' => 0]);
        $this->assertEquals(get_string('allsiteadmin', 'tool_custompage'), $audience->get_description());

        $audience = adminrole::create($page->get('id'), ['roles' => 1]);
        $this->assertEquals(get_string('siteadministrators', 'role'), $audience->get_description());

        $audience = adminrole::create($page->get('id'), ['roles' => 2]);
        $this->assertEquals(get_string('tenantadmins', 'tool_tenant'), $audience->get_description());
    }
}
