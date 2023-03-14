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

namespace tool_custompage\external\page;

use context_system;
use external_api;
use externallib_advanced_testcase;
use tool_custompage\local\helpers\audience;
use tool_custompage\tool_custompage\audience\cohortmember;
use tool_custompage\tool_custompage\audience\manual;
use tool_custompage_generator;
use tool_custompage\permission_exception;
use tool_custompage\local\models\page;
use tool_tenant\manager;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->dirroot}/webservice/tests/helpers.php");

/**
 * Unit tests of external class for duplicating pages
 *
 * @package     tool_custompage
 * @covers      \tool_custompage\external\page\duplicate
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class duplicate_test extends externallib_advanced_testcase {

    /**
     * Data provider for {@see test_execute}
     *
     * @return array[]
     */
    public function execute_provider(): array {
        return [
            'Global to global' => [true, false, true],
            'Tenant to tenant' => [false, false, false],
            'Global to tenant' => [true, true, false],
            'Tenant to global' => [false, true, true],
        ];
    }

    /**
     * Test execute method
     *
     * @param bool $global
     * @param bool $amendglobal
     * @param bool $newglobal
     *
     * @dataProvider execute_provider
     */
    public function test_execute(bool $global, bool $amendglobal, bool $newglobal = false): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $pageone = $generator->create_page(['name' => 'Page one', 'weight' => 0, 'global' => $global]);

        $result = duplicate::execute($pageone->get('id'), $amendglobal, $newglobal);
        $result = external_api::clean_returnvalue(duplicate::execute_returns(), $result);

        $this->assertNotEquals($pageone->get('id'), $result);

        $duplicatedpage = new page($result);
        $this->assertEquals('Page one (copy)', $duplicatedpage->get('name'));
        $this->assertEquals($newglobal, $duplicatedpage->get('global'));
    }

    /**
     * Test execute method for user who cannot duplicate global pages
     */
    public function test_execute_cannot_duplicate_global(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Being able to edit a tenant page does not allow a user to create global pages.
        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user']);
        assign_capability('tool/custompage:edit', CAP_ALLOW, $userrole, context_system::instance());

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $pageone = $generator->create_page(['name' => 'Page one', 'weight' => 0, 'global' => 1]);
        $generator->create_audience(['pageid' => $pageone->get('id'), 'configdata' => []]);

        $this->expectException(permission_exception::class);
        $this->expectExceptionMessage('You cannot create new pages');
        duplicate::execute($pageone->get('id'), false);
    }

    /**
     * Test execute method for user who cannot duplicate to a global page
     */
    public function test_execute_cannot_duplicate_to_global(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Being able to edit a tenant page does not allow a user to create global pages.
        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user']);
        assign_capability('tool/custompage:edit', CAP_ALLOW, $userrole, context_system::instance());

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $pageone = $generator->create_page(['name' => 'Page one', 'weight' => 0, 'global' => 0]);

        $this->expectException(permission_exception::class);
        $this->expectExceptionMessage('You cannot create new pages');
        duplicate::execute($pageone->get('id'), true, true);
    }

    /**
     * Test execute method for a user without permission to duplicate the page
     */
    public function test_execute_access_exception(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $pageone = $generator->create_page(['name' => 'Page one', 'weight' => 0]);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(permission_exception::class);
        $this->expectExceptionMessage('You cannot preview this page');
        duplicate::execute($pageone->get('id'), false);
    }

    /**
     * Test execute method for a user with/without permission to view/edit audiences
     */
    public function test_execute_duplicate_audience_permission(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $pageone = $generator->create_page(['name' => 'Page one', 'weight' => 0]);

        // Create tenant admin in current tenant.
        $tenantadmin = $this->getDataGenerator()->create_user();
        $manager = new manager();
        $manager->assign_tenant_admin_role(tenancy::get_tenant_id(), [$tenantadmin->id]);

        // Create user to be used in some audiences.
        $user = $this->getDataGenerator()->create_user();

        // Create manual user audience, selecting the previous one user.
        $audiencemanual = $generator->create_audience([
            'pageid' => $pageone->get('id'), 'classname' => manual::class, 'configdata' => ['users' => [$user->id]]
        ]);

        // Create cohort audience.
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $user->id);
        $generator->create_audience([
            'pageid' => $pageone->get('id'), 'classname' => cohortmember::class, 'configdata' => ['cohorts' => [$cohort->id]]
        ]);

        $audiencesmainpage = audience::get_audiences_for_page($pageone->get('id'));

        // Actually should exist two audiences.
        $this->assertCount(2, $audiencesmainpage);

        // Execute duplicate action.
        $result = duplicate::execute($pageone->get('id'), false);
        $result = external_api::clean_returnvalue(duplicate::execute_returns(), $result);
        $duplicatedpage = new page($result);

        $audiencesincopy = audience::get_audiences_for_page($duplicatedpage->get('id'));

        // Current user can edit all audiences, so they keep in the new copy page.
        $this->assertCount(2, $audiencesincopy);

        // Set tenant admin as current user.
        self::setUser($tenantadmin);

        // Execute duplicate action.
        $result = duplicate::execute($pageone->get('id'), false);
        $result = external_api::clean_returnvalue(duplicate::execute_returns(), $result);
        $duplicatedpage = new page($result);

        $audiencesincopy = audience::get_audiences_for_page($duplicatedpage->get('id'));

        // Tenant admin can't edit some audiences, so only manual audience is copied to the new page.
        $this->assertCount(1, $audiencesincopy);
        $this->assertEquals($audiencemanual->get_name(), $audiencesincopy[0]['heading']);
    }
}
