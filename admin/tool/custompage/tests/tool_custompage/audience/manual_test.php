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
use tool_custompage_generator;

/**
 * Manual users audience type tests
 *
 * @package     tool_custompage
 * @covers      \tool_custompage\tool_custompage\audience\manual
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manual_test extends advanced_testcase {

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

        $audience = manual::create($page->get('id'), []);
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

        $audience = manual::create($page->get('id'), ['users' => [$user->id]]);
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

        $userone = $this->getDataGenerator()->create_user();
        $usertwo = $this->getDataGenerator()->create_user();
        $userthree = $this->getDataGenerator()->create_user();

        $audience = manual::create($page->get('id'), ['users' => [$userone->id, $usertwo->id]]);

        [$join, $where, $params] = $audience->get_sql('u');
        $users = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$join} WHERE {$where}", $params);

        $this->assertEqualsCanonicalizing([$userone->id, $usertwo->id], $users);
    }

    /**
     * Test retrieving description for this audience
     */
    public function test_get_description(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My page', 'weight' => -1]);

        $userone = $this->getDataGenerator()->create_user(['firstname' => 'Adam', 'lastname' => 'Apple']);
        $usertwo = $this->getDataGenerator()->create_user(['firstname' => 'Brenda', 'lastname' => 'Banana']);
        $userthree = $this->getDataGenerator()->create_user(['firstname' => 'Charlie', 'lastname' => 'Carrot']);

        $audience = manual::create($page->get('id'), ['users' => [$userone->id, $usertwo->id]]);
        $this->assertEquals(fullname($userone) . ', ' . fullname($usertwo), $audience->get_description());
    }
}
