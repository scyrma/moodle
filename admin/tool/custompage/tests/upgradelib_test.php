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

namespace tool_custompage;

use advanced_testcase;
use tool_custompage\local\helpers\page as helper;
use tool_custompage\local\models\page;

/**
 * Class tool_custompage_upgradelib_testcase
 *
 * @package   tool_custompage
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class upgradelib_test extends advanced_testcase {


    /**
     * The methods specified here is called before each test.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/custompage/db/upgradelib.php');
    }

    /**
     * Test script executed on install/upgrade to create My teams custom page
     *
     * @covers ::tool_custompage_create_myteams_page
     */
    public function test_tool_custompage_create_myteams_page(): void {
        global $DB;
        $this->resetAfterTest();

        // If audience manager class does not exist, we skip the test.
        $manageraudience = \tool_organisation\tool_custompage\audience\manager::class;
        if (!class_exists($manageraudience)) {
            $this->markTestSkipped('Audience manager class does not exists, skipping');
        }

        // Run install/upgrade script to create My teams page and related settings.
        tool_custompage_create_myteams_page();

        // Check if My teams custom page was created.
        $myteampagename = get_string('myteamspagename', 'tool_custompage');
        $myteampage = $DB->get_record('tool_custompage', ['name' => $myteampagename, 'global' => 1], '*', MUST_EXIST);

        // Assert if manager audience is added to previous My teams custom page.
        $myteamaudience = $DB->get_record('tool_custompage_audience', ['pageid' => $myteampage->id]);
        $this->assertEquals($manageraudience, $myteamaudience->classname);
        $this->assertEquals(['permissions' => 'anymanager'], json_decode($myteamaudience->configdata, true));

        // Check if My teams block was created into My teams custom page and assert some block data.
        $blockinstances = helper::get_page_blocks(new page($myteampage->id), true);
        $this->assertCount(1, $blockinstances);

        $blockinstance = reset($blockinstances);
        $this->assertInstanceOf(\block_myteams::class, $blockinstance);
        $this->assertEquals('myteams', $blockinstance->instance->blockname);
        $this->assertEquals('admin-tool-custompage', $blockinstance->instance->pagetypepattern);
        $this->assertEquals($myteampage->id, $blockinstance->instance->subpagepattern);
        $this->assertEquals('content', $blockinstance->instance->region);
    }
}
