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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

declare(strict_types=1);

namespace tool_custompage\tool_custompage\audience;

use advanced_testcase;
use context_system;
use tool_custompage\local\helpers\audience;
use tool_custompage\permission;
use tool_custompage_generator;

/**
 * Cohort member audience type tests
 *
 * @package     tool_custompage
 * @covers      \tool_custompage\tool_custompage\audience\nonauthenticatedusers
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Odei Alba <odei.alba@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class nonauthenticatedusers_test extends advanced_testcase {

    /**
     * Test whether user can add this audience type
     */
    public function test_user_can_add(): void {
        global $DB;

        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My page', 'weight' => -1]);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $audience = nonauthenticatedusers::create($page->get('id'), []);
        $this->assertFalse($audience->user_can_add());

        // Grant required capability to use.
        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability('moodle/user:viewalldetails', CAP_ALLOW, $userrole, context_system::instance()->id);

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
        $page = $generator->create_page(['name' => 'My page', 'weight' => -1]);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $audience = nonauthenticatedusers::create($page->get('id'), []);
        $this->assertFalse($audience->user_can_edit());

        // Grant required capability to use.
        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability('moodle/user:viewalldetails', CAP_ALLOW, $userrole, context_system::instance()->id);

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
        $page = $generator->create_page(['name' => 'My page', 'weight' => -1]);

        $user = $this->getDataGenerator()->create_user();

        $audience = nonauthenticatedusers::create($page->get('id'), []);

        [$join, $where, $params] = $audience->get_sql('u');

        $users = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$join} WHERE {$where}", $params);

        $this->assertEmpty($users);
    }

    /**
     * Test access to view a page with this audience type
     */
    public function test_view_access(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');

        // Create a page with the "All authenticated users" audience.
        $page1 = $generator->create_page(['name' => 'My all page', 'weight' => -1]);
        $audienceall = allusers::create($page1->get('id'), []);

        // Create a page with the "Non-authenticated users" audience.
        $page2 = $generator->create_page(['name' => 'My nonauth page', 'weight' => -1]);
        $audiencenonauth = nonauthenticatedusers::create($page2->get('id'), []);

        // Create a user.
        $user = $this->getDataGenerator()->create_user();

        // Test access to the pages as a logged in user.
        $this->setUser($user);
        $this->assertTrue(permission::can_view_page($page1));
        $this->assertFalse(permission::can_view_page($page2));
        $this->assertEqualsCanonicalizing([$page1->get('id')], audience::get_allowed_pages());

        // Test access to the pages as a guest user.
        $this->setGuestUser();
        $this->assertFalse(permission::can_view_page($page1));
        $this->assertTrue(permission::can_view_page($page2));
        $this->assertEqualsCanonicalizing([$page2->get('id')], audience::get_allowed_pages());

        // Test access to the pages as a not logged in user.
        $this->setUser();
        $this->assertFalse(permission::can_view_page($page1));
        $this->assertTrue(permission::can_view_page($page2));
        $this->assertEqualsCanonicalizing([$page2->get('id')], audience::get_allowed_pages());

    }
}
