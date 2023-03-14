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

use external_api;
use externallib_advanced_testcase;
use tool_custompage_generator;
use tool_custompage\permission_exception;
use tool_custompage\local\models\page;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->dirroot}/webservice/tests/helpers.php");

/**
 * Unit tests of external class for getting page info
 *
 * @package     tool_custompage
 * @covers      \tool_custompage\external\page\delete
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_test extends externallib_advanced_testcase {

    /**
     * Test execute method
     */
    public function test_execute(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');

        $page = $generator->create_page(['name' => 'Page one', 'weight' => 0]);

        // Create a couple of blocks, ensuring the HTML block is configured (otherwise it's invisible).
        $blockhtml = $generator->create_page_block(['pageid' => $page->get('id'), 'blockname' => 'html']);
        block_instance($blockhtml->blockname, $blockhtml)->instance_config_save((object) [
            'title' => 'My cool thing',
            'text' => [
                'itemid' => 19,
                'text' => 'So cool',
                'format' => FORMAT_MOODLE,
            ],
        ]);

        $generator->create_page_block(['pageid' => $page->get('id'), 'blockname' => 'calendar_month', 'region' => 'content']);

        $result = get::execute($page->get('id'));
        $result = external_api::clean_returnvalue(get::execute_returns(), $result);

        $this->assertEquals($page->get('name'), $result['name']);
        $this->assertEquals($page->get('title'), $result['title']);

        // Assert we got back the expected blocks.
        $this->assertEquals(['html', 'calendar_month'], array_column($result['blocks'], 'name'));

        // Assert structure of the first block (HTML).
        $block = $result['blocks'][0];
        $this->assertEquals($blockhtml->id, $block['instanceid']);
        $this->assertTrue($block['visible']);
        $this->assertEquals('My cool thing', $block['contents']['title']);
        $this->assertEquals('So cool', $block['contents']['content']);
        $this->assertNotEmpty($block['configs']);
    }

    /**
     * Test execute method for a user without permission
     */
    public function test_execute_access_exception(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'Page one', 'weight' => 0]);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(permission_exception::class);
        $this->expectExceptionMessage('You cannot view this page');
        get::execute($page->get('id'));
    }
}
