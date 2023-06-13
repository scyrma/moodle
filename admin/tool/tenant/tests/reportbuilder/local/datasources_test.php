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

namespace tool_tenant\reportbuilder\local;

use core_badges\reportbuilder\datasource\users;
use core_badges_generator;
use core_comment\reportbuilder\datasource\comments;
use core_reportbuilder_generator;
use core_reportbuilder_testcase;
use tool_tenant_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->dirroot}/reportbuilder/tests/helpers.php");

/**
 * Test for tenant callbacks for core reportbuilder
 *
 * @package    tool_tenant
 * @covers     \tool_tenant\reportbuilder\local\callbacks
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class datasources_test extends core_reportbuilder_testcase {
    /** @var tool_tenant_generator */
    protected $generator;

    /**
     * Set up
     */
    protected function setUp(): void {
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Test for multitenancy callbacks in the user badges datasource
     */
    public function test_multitenancy_user_badges_datasource(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $tenant1 = $this->generator->create_tenant();
        $tenant2 = $this->generator->create_tenant();

        $user1a = $this->getDataGenerator()->create_user(['firstname' => 'Luna', 'lastname' => 'Sheep']);
        $this->generator->allocate_user((int) $user1a->id, $tenant1->id);

        $user1b = $this->getDataGenerator()->create_user(['firstname' => 'Zoe', 'lastname' => 'Zebra']);
        $this->generator->allocate_user((int) $user1b->id, $tenant1->id);

        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Kira', 'lastname' => 'Dog']);
        $this->generator->allocate_user((int) $user2->id, $tenant2->id);

        /** @var core_badges_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_badges');
        ($badgeonea = $generator->create_badge(['name' => 'Badge 1a', 'description' => 'My badge #1a']))
            ->issue($user1a->id, true);
        ($badgeoneb = $generator->create_badge(['name' => 'Badge 1b', 'description' => 'My badge #1b']))
            ->issue($user1b->id, true);
        ($badgetwo = $generator->create_badge(['name' => 'Badge 2', 'description' => 'My badge #2']))
            ->issue($user2->id, true);

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');

        $report = $generator->create_report(['name' => 'Badges', 'source' => users::class, 'default' => 1]);

        // User in tenant1 should only see badges for tenant1.
        $this->setUser($user1a);
        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(2, $content);

        [$userfullname, $badgename, $badgedescription, $badgeissued] = array_values($content[0]);
        $this->assertEquals(fullname($user1a), $userfullname);
        $this->assertEquals($badgeonea->name, $badgename);
        $this->assertEquals($badgeonea->description, $badgedescription);
        $this->assertNotEmpty($badgeissued);

        [$userfullname, $badgename, $badgedescription, $badgeissued] = array_values($content[1]);
        $this->assertEquals(fullname($user1b), $userfullname);
        $this->assertEquals($badgeoneb->name, $badgename);
        $this->assertEquals($badgeoneb->description, $badgedescription);
        $this->assertNotEmpty($badgeissued);

        // User in tenant2 should only see badges for tenant2.
        $this->setUser($user2);
        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);

        [$userfullname, $badgename, $badgedescription, $badgeissued] = array_values($content[0]);
        $this->assertEquals(fullname($user2), $userfullname);
        $this->assertEquals($badgetwo->name, $badgename);
        $this->assertEquals($badgetwo->description, $badgedescription);
        $this->assertNotEmpty($badgeissued);
    }

    /**
     * Test for multitenancy callbacks in the comments datasource
     */
    public function test_multitenancy_comments_datasource(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        $tenant1 = $this->generator->create_tenant();
        $tenant2 = $this->generator->create_tenant();

        $user1a = $this->getDataGenerator()->create_user(['firstname' => 'Luna', 'lastname' => 'Sheep']);
        $this->generator->allocate_user((int) $user1a->id, $tenant1->id);

        $user1b = $this->getDataGenerator()->create_user(['firstname' => 'Zoe', 'lastname' => 'Zebra']);
        $this->generator->allocate_user((int) $user1b->id, $tenant1->id);

        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Kira', 'lastname' => 'Dog']);
        $this->generator->allocate_user((int) $user2->id, $tenant2->id);

        /** @var \core_comment_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_comment');

        $this->setUser($user1a);
        $generator->create_comment([
            'context' => $coursecontext,
            'component' => 'block_comments',
            'area' => 'page_comments',
            'content' => 'Cool',
        ]);

        $this->setUser($user1b);
        $generator->create_comment([
            'context' => $coursecontext,
            'component' => 'block_comments',
            'area' => 'page_comments',
            'content' => 'Beans',
        ]);

        $this->setUser($user2);
        $generator->create_comment([
            'context' => $coursecontext,
            'component' => 'block_comments',
            'area' => 'page_comments',
            'content' => 'Awesome',
        ]);

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Blogs', 'source' => comments::class, 'default' => 1]);

        // User in tenant1 should only see badges for tenant1.
        $this->setUser($user1b);
        $content = $this->get_custom_report_content($report->get('id'));

        // Set consistent order by firstname.
        \core_collator::asort_array_of_arrays_by_key($content, 'c2_firstname');
        $content = array_values($content);

        $this->assertCount(2, $content);

        // Default columns are context, content, user, time created.
        [$contextname, $contenttext, $userfullname, $timecreated] = array_values($content[0]);
        $this->assertEquals($coursecontext->get_context_name(), $contextname);
        $this->assertEquals(format_text('Cool'), $contenttext);
        $this->assertEquals(fullname($user1a), $userfullname);
        $this->assertNotEmpty($timecreated);

        [$contextname, $contenttext, $userfullname, $timecreated] = array_values($content[1]);
        $this->assertEquals($coursecontext->get_context_name(), $contextname);
        $this->assertEquals(format_text('Beans'), $contenttext);
        $this->assertEquals(fullname($user1b), $userfullname);
        $this->assertNotEmpty($timecreated);

        // User in tenant2 should only see badges for tenant2.
        $this->setUser($user2);
        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);

        // Default columns are context, content, user, time created.
        [$contextname, $contenttext, $userfullname, $timecreated] = array_values($content[0]);
        $this->assertEquals($coursecontext->get_context_name(), $contextname);
        $this->assertEquals(format_text('Awesome'), $contenttext);
        $this->assertEquals(fullname($user2), $userfullname);
        $this->assertNotEmpty($timecreated);
    }
}
