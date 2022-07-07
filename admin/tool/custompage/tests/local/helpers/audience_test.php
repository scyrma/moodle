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

namespace tool_custompage\local\helpers;

use advanced_testcase;
use context_system;
use tool_custompage\local\models\page;
use tool_custompage_generator;
use tool_custompage\tool_custompage\audience\{manual, systemrole, allusers};
use tool_tenant_generator;

/**
 * Unit tests for the audience helper
 *
 * @package     tool_custompage
 * @covers      \tool_custompage\local\helpers\audience
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class audience_test extends advanced_testcase {

    /**
     * Test getting audiences for given page
     */
    public function test_get_audiences_for_page(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My page', 'weight' => -1]);

        $user = $this->getDataGenerator()->create_user();
        $audiencemanual = $generator->create_audience([
            'pageid' => $page->get('id'), 'classname' => manual::class, 'configdata' => ['users' => [$user->id]],
        ]);

        $role = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        $audiencerole = $generator->create_audience([
            'pageid' => $page->get('id'), 'classname' => systemrole::class, 'configdata' => ['roles' => [$role]]
        ]);

        $audiences = audience::get_audiences_for_page($page->get('id'));
        $this->assertEquals([
            [
                'instanceid' => $audiencemanual->get_persistent()->get('id'),
                'description' => $audiencemanual->get_description(),
                'heading' => $audiencemanual->get_name(),
                'headingeditable' => $audiencemanual->get_name(),
                'canedit' => true,
                'candelete' => true,
                'showormessage' => false,
            ],
            [
                'instanceid' => $audiencerole->get_persistent()->get('id'),
                'description' => $audiencerole->get_description(),
                'heading' => $audiencerole->get_name(),
                'headingeditable' => $audiencerole->get_name(),
                'canedit' => true,
                'candelete' => true,
                'showormessage' => true,
            ],
        ], $audiences);
    }

    /**
     * Test retrieving audience SQL for multiple audiences
     */
    public function test_user_audience_sql(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My page', 'weight' => -1]);

        $manualuser = $this->getDataGenerator()->create_user();
        $audiencemanual = $generator->create_audience([
            'pageid' => $page->get('id'), 'classname' => manual::class, 'configdata' => ['users' => [$manualuser->id]],
        ]);

        $role = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        $roleuser = $this->getDataGenerator()->create_user();
        role_assign($role, $roleuser->id, context_system::instance()->id);

        $audiencerole = $generator->create_audience([
            'pageid' => $page->get('id'), 'classname' => systemrole::class, 'configdata' => ['roles' => [$role]]
        ]);

        [$wheres, $params] = audience::user_audience_sql([
            $audiencemanual->get_persistent(),
            $audiencerole->get_persistent(),
        ], 'u');
        $where = '(' . implode(') OR (', $wheres) . ')';

        $users = $DB->get_fieldset_sql("SELECT u.id FROM {user} u WHERE {$where}", $params);
        $this->assertEqualsCanonicalizing([$manualuser->id, $roleuser->id], $users);
    }

    /**
     * Test get_allowed_pages()
     */
    public function test_get_allowed_pages(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        self::setUser($user1);

        // No pages.
        $pages = audience::get_allowed_pages();
        $this->assertEmpty($pages);

        $page1 = $generator->create_page(['name' => 'My page', 'weight' => -1]);
        $page2 = $generator->create_page(['name' => 'My page', 'weight' => -1]);
        $page3 = $generator->create_page(['name' => 'My page', 'weight' => -1]);

        // Pages with no audiences set.
        $pages = audience::get_allowed_pages();
        $this->assertEmpty($pages);

        $generator->create_audience([
            'pageid' => $page1->get('id'), 'classname' => manual::class, 'configdata' => ['users' => [$user1->id, $user2->id]],
        ]);
        $generator->create_audience([
            'pageid' => $page2->get('id'), 'classname' => manual::class, 'configdata' => ['users' => [$user2->id]],
        ]);
        $generator->create_audience([
            'pageid' => $page3->get('id'), 'classname' => manual::class, 'configdata' => ['users' => [$user1->id]],
        ]);

        // User1 can access page1 and page3.
        $pages = audience::get_allowed_pages();
        $this->assertEqualsCanonicalizing([$page1->get('id'), $page3->get('id')], $pages);

        // User2 can access page1 and page2 .
        $pages = audience::get_allowed_pages((int) $user2->id);
        $this->assertEqualsCanonicalizing([$page1->get('id'), $page2->get('id')], $pages);
    }

    /**
     * Test user_pages_list()
     */
    public function test_user_pages_list(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $pagegenerator */
        $pagegenerator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');

        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');

        [$tenant1, [$user1]] = $tenantgenerator->create_tenant_and_users(1);
        [$tenant2, [$user2]] = $tenantgenerator->create_tenant_and_users(1);
        $user3 = $this->getDataGenerator()->create_user();
        self::setUser($user1);

        $pages = audience::user_pages_list();
        $this->assertEmpty($pages);

        $page1 = $pagegenerator->create_page(['name' => 'My page', 'weight' => -1, 'global' => 1]);
        $page2 = $pagegenerator->create_page(['name' => 'My page', 'weight' => -1, 'global' => 1]);
        $page3 = $pagegenerator->create_page(['name' => 'My page', 'weight' => -1, 'global' => 1]);
        $page4 = $pagegenerator->create_page(['name' => 'My page', 'weight' => -1, 'tenantid' => $tenant1->id, 'global' => 0]);
        $page5 = $pagegenerator->create_page(['name' => 'My page', 'weight' => -1, 'tenantid' => $tenant2->id, 'global' => 0]);

        $pagegenerator->create_audience([
            'pageid' => $page1->get('id'), 'classname' => manual::class, 'configdata' => ['users' => [$user1->id, $user2->id]],
        ]);
        $pagegenerator->create_audience([
            'pageid' => $page2->get('id'), 'classname' => manual::class, 'configdata' => ['users' => [$user2->id]],
        ]);
        $pagegenerator->create_audience([
            'pageid' => $page3->get('id'), 'classname' => manual::class, 'configdata' => ['users' => [$user1->id]],
        ]);
        $pagegenerator->create_audience([
            'pageid' => $page4->get('id'), 'classname' => allusers::class, 'configdata' => [],
        ]);

        // User1 can access page1, page3 and page4 (tenant page).
        $pages = audience::user_pages_list();
        $this->assertContainsOnlyInstancesOf(page::class, $pages);
        $pages = array_map(static function(page $page): int {
            return $page->get('id');
        }, $pages);
        $this->assertEqualsCanonicalizing([$page1->get('id'), $page3->get('id'), $page4->get('id')], $pages);

        // User2 can access page1 and page2.
        $pages = audience::user_pages_list((int) $user2->id);
        $this->assertContainsOnlyInstancesOf(page::class, $pages);
        $pages = array_map(static function(page $page): int {
            return $page->get('id');
        }, $pages);
        $this->assertEquals([$page1->get('id'), $page2->get('id')], $pages);

        // User3 can not access any page.
        $pages = audience::user_pages_list((int) $user3->id);
        $this->assertEmpty($pages);
    }
}
