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

namespace tool_organisation\tool_custompage\audience;

use advanced_testcase;
use context_system;
use core_component;
use tool_custompage_generator;

/**
 * Manager audience type tests
 *
 * @package     tool_organisation
 * @covers      \tool_organisation\tool_custompage\audience\manager
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager_test extends advanced_testcase {

    /**
     * Test setup
     */
    public function setUp(): void {
        if (!core_component::get_component_directory('tool_custompage')) {
            $this->markTestSkipped('tool_custompage not present');
        }
    }

    /**
     * Test whether user can add this audience type
     */
    public function test_user_can_add(): void {
        global $DB;

        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My manager page', 'weight' => -1]);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $audience = manager::create($page->get('id'), ['permissions' => 'anymanager']);
        $this->assertFalse($audience->user_can_add());

        // Grant required capability to use.
        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability('tool/organisation:assignjobs', CAP_ALLOW, $userrole, context_system::instance()->id);

        $this->assertTrue($audience->user_can_add());
    }

    /**
     * Test whether user can edit this audience type
     */
    public function test_user_can_edit(): void {
        global $DB;

        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My manager page', 'weight' => -1]);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $audience = manager::create($page->get('id'), ['permissions' => 'anymanager']);
        $this->assertFalse($audience->user_can_edit());

        // Grant required capability to use.
        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability('tool/organisation:assignjobs', CAP_ALLOW, $userrole, context_system::instance()->id);

        $this->assertTrue($audience->user_can_edit());
    }

    /**
     * Test retrieving SQL from this audience type
     */
    public function test_get_sql(): void {
        global $DB;

        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My manager page', 'weight' => -1]);

        // Create user with only manager permissions.
        $managerpermission = ['globalmanager' => 1];
        [$managerposition, $managerdepartment] = $this->getDataGenerator()->get_plugin_generator('tool_organisation')
            ->create_position_and_department($managerpermission);

        $usermanager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('tool_organisation')->assign_job([
            'userid' => $usermanager->id,
            'departmentid' => $managerdepartment->id,
            'positionid' => $managerposition->id,
        ]);

        // Create user with only department lead permissions.
        $leadpermission = ['departmentmanager' => 1];
        [$leadposition, $leaddepartment] = $this->getDataGenerator()->get_plugin_generator('tool_organisation')
            ->create_position_and_department($leadpermission);

        $userdepartmentlead = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('tool_organisation')->assign_job([
            'userid' => $userdepartmentlead->id,
            'departmentid' => $leaddepartment->id,
            'positionid' => $leadposition->id,
        ]);

        // Create audience to any manager and retrieve users (managers and department leads).
        $anymanager = manager::create($page->get('id'), ['permissions' => 'anymanager']);
        [$join, $where, $params] = $anymanager->get_sql('u');
        $usersanymanager = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$join} WHERE {$where}", $params);

        $this->assertEqualsCanonicalizing([$usermanager->id, $userdepartmentlead->id], $usersanymanager);

        // Create audience to only manager and retrieve users (managers).
        $onlymanager = manager::create($page->get('id'), ['permissions' => 'globalmanager']);
        [$join, $where, $params] = $onlymanager->get_sql('u');
        $usersanymanager = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$join} WHERE {$where}", $params);

        $this->assertEqualsCanonicalizing([$usermanager->id], $usersanymanager);

        // Create audience to only department lead and retrieve users (department leads).
        $onlydepartmentlead = manager::create($page->get('id'), ['permissions' => 'departmentmanager']);
        [$join, $where, $params] = $onlydepartmentlead->get_sql('u');
        $usersanymanager = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$join} WHERE {$where}", $params);

        $this->assertEqualsCanonicalizing([$userdepartmentlead->id], $usersanymanager);
    }

    /**
     * Test retrieving description for this audience
     */
    public function test_get_description(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My manager page', 'weight' => -1]);

        // Description for Managers.
        $globalmanager = 'globalmanager';
        $audiencemanager = manager::create($page->get('id'), ['permissions' => $globalmanager]);
        $expectedglobal = get_string('audiencemanagerdescription', 'tool_organisation', [
            'permissions' => get_string($globalmanager, 'tool_organisation')
        ]);

        $this->assertEquals($expectedglobal, $audiencemanager->get_description());

        // Description for Department lead.
        $departmentlead = 'departmentmanager';
        $audiencedepartment = manager::create($page->get('id'), ['permissions' => $departmentlead]);
        $expectedlead = get_string('audiencemanagerdescription', 'tool_organisation', [
            'permissions' => get_string($departmentlead, 'tool_organisation')
        ]);

        $this->assertEquals($expectedlead, $audiencedepartment->get_description());

        // Description for Manager or department lead.
        $anymanager = 'anymanager';
        $audienceany = manager::create($page->get('id'), ['permissions' => $anymanager]);
        $expectedany = get_string('audiencemanagerdescription', 'tool_organisation', [
            'permissions' => get_string($anymanager, 'tool_organisation')
        ]);

        $this->assertEquals($expectedany, $audienceany->get_description());

    }
}
