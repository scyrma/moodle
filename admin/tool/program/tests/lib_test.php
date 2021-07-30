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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Tests for the tool_program lib class.
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use core\output\inplace_editable;
use core_user\output\myprofile\tree;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/admin/tool/program/lib.php');
require_once($CFG->libdir . '/externallib.php');

/**
 * Tests for the tool_program lib class.
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_lib_testcase extends advanced_testcase {

    /** @var tool_program_generator */
    protected $generator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->resetAfterTest();
    }

    /**
     * Test program plugin file helper.
     * @covers ::tool_program_pluginfile
     */
    public function test_tool_program_pluginfile(): void {
        // Check wrong context early returns.
        $course = new stdClass();
        $cm = new stdClass();
        $user = self::getDataGenerator()->create_user();
        $filearea = 'program_description';
        $context = context_user::instance($user->id);

        $result = tool_program_pluginfile($course, $cm, $context, $filearea, [], false);

        $this->assertEmpty($result);
        $this->assertDebuggingNotCalled(); // Did not even reach the login check.

        // Check wrong filearea early returns.
        $context = context_system::instance();
        $filearea = 'wrong_filearea';

        $result = tool_program_pluginfile($course, $cm, $context, $filearea, [], false);

        $this->assertEmpty($result);
        $this->assertDebuggingNotCalled(); // Did not even reach the login check.

        // Check user not authenticated error.
        $context = context_system::instance();
        $filearea = 'program_description';
        try {
            tool_program_pluginfile($course, $cm, $context, $filearea, [], false);
            $this->expectException('Expected auth error.');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
            $this->assertEquals('Unsupported redirect detected, script execution terminated', $e->getMessage());
        }

        // User authenticated, but no file found early returns.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $course = new stdClass();
        $cm = new stdClass();
        $context = context_system::instance();
        $filearea = 'program_description';

        $result = tool_program_pluginfile($course, $cm, $context, $filearea, [], false);

        $this->assertEmpty($result);
        $this->assertDebuggingNotCalled();

        // TODO SP-85: test send store file.
    }

    /**
     * Test get fontawesome icon map helper.
     * @covers ::tool_program_get_fontawesome_icon_map
     */
    public function test_tool_program_get_fontawesome_icon_map(): void {
        $expectediconsmap = [
            'tool_program:t/circle' => 'fa-circle',
        ];
        $this->assertEquals($expectediconsmap, tool_program_get_fontawesome_icon_map());
    }

    /**
     * Test programs inplace editable updates expected field values.
     * @covers ::tool_program_inplace_editable
     */
    public function test_tool_program_inplace_editable(): void {
        $context = context_system::instance();
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->generator->assign_edit_capability($user->id, $context);
        $program = $this->generator->generate_program((object) ['tenantid' => tenancy::get_default_tenant_id()]);
        $baseset = $program->get_base_set();

        // Check wrong item type.
        $itemtype = '';
        $itemid = 1;
        $newvalue = 'New value';

        try {
            tool_program_inplace_editable($itemtype, $itemid, $newvalue);
            $this->fail('Exception expected');
        } catch (coding_exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
            $this->assertStringContainsString('Unexpected tool_program inplace editable item type', $e->getMessage());
        }

        // Check "program name" item type.
        $newvalue = 'New program name';
        $itemtype = 'programname';
        $itemid = $program->get('id');
        $this->assertNotEquals($newvalue, $program->get('fullname'));

        $inplaceedit = tool_program_inplace_editable($itemtype, $itemid, $newvalue);
        $program = $program->read();

        $this->assertInstanceOf(inplace_editable::class, $inplaceedit);
        $this->assertEquals($newvalue, $program->get('fullname'));

        // Check "set name" item type.
        $newvalue = 'New set name';
        $itemtype = 'setname';
        $itemid = $baseset->get('id');
        $this->assertNotEquals($newvalue, $baseset->get('name'));

        $inplaceedit = tool_program_inplace_editable($itemtype, $itemid, $newvalue);
        $baseset = $baseset->read();

        $this->assertInstanceOf(inplace_editable::class, $inplaceedit);
        $this->assertEquals($newvalue, $baseset->get('name'));
    }

    /**
     * Test get potential users selector returns sql query params.
     * @covers ::tool_program_potential_users_selector
     */
    public function test_tool_program_potential_users_selector(): void {
        $context = context_system::instance();
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $program = $this->generator->generate_program();

        // Check if wrong area provided, returns null.
        $area = 'wrong area';
        $itemid = false;

        $result = tool_program_potential_users_selector($area, $itemid);
        $this->assertNull($result);

        // If item id provided and user does not have permission to allocate, check that error is thrown.
        $area = 'allocate';
        try {
            tool_program_potential_users_selector($area, $program->get('id'));
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
            $str = get_string('errorcantallocateusers', 'tool_program');
            $this->assertStringContainsString($str, $e->getMessage());
        }

        $this->generator->assign_allocateuser_capability($user->id, $context);
        $queryparams = tool_program_potential_users_selector($area, $program->get('id'));
        $this->assertCount(3, $queryparams);
    }

    /**
     * Test program my profile navigation generates the nodes/links to user programs.
     * @covers ::tool_program_myprofile_navigation
     */
    public function test_tool_program_myprofile_navigation(): void {
        $iscurrentuser = true;
        $course = new stdClass();
        $tree = new tree();
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $defaulttenantid = tenancy::get_tenant_id($user->id);
        $program1 = $this->generator->generate_program_with_course((object) ['tenantid' => $defaulttenantid]);
        $program2 = $this->generator->generate_program_with_course((object) ['tenantid' => $defaulttenantid]);
        $program3 = $this->generator->generate_program_with_course((object) ['tenantid' => $defaulttenantid]);
        // Program1 is active.
        $this->generator->allocate_user_to_program($program1->get('id'), $user->id);
        // Program2 is overdue.
        $programuser2 = $this->generator->allocate_user_to_program($program2->get('id'), $user->id);
        $programuser2->set('duedate', strtotime('-5 day'));
        $programuser2->update();
        // Program3 is completed.
        $this->generator->allocate_user_to_program($program3->get('id'), $user->id);
        $this->generator->complete_program($program3, $user->id);

        // Check the node tree is correct.
        tool_program_myprofile_navigation($tree, $user, $iscurrentuser, $course);

        $reflector = new ReflectionObject($tree);
        $categories = $reflector->getProperty('categories');
        $categories->setAccessible(true);
        $categoriesarray = $categories->getValue($tree);
        $this->assertArrayHasKey('learning', $categoriesarray);
        $this->assertArrayHasKey('activeprograms', $categoriesarray['learning']->nodes);
        $this->assertArrayHasKey('overdueprograms', $categoriesarray['learning']->nodes);
        $this->assertArrayHasKey('completedprograms', $categoriesarray['learning']->nodes);
    }

    /**
     * Test for registration of the program site info callback called from tool_wp\\registration::site_info
     * @covers \tool_program\registration::stats
     */
    public function test_registration_get_site_info() {
        global $CFG;
        $this->resetAfterTest(true);
        $origsiteinfo = $siteinfo = ['moodlerelease' => $CFG->release, 'url' => $CFG->wwwroot];
        component_class_callback('tool_wp\\registration', 'site_info', [&$siteinfo, false]);
        $appended = array_diff_key($siteinfo, $origsiteinfo);

        $this->assertNotNull($appended['wpprograms']);

        $siteinfo = $origsiteinfo;
        component_class_callback('tool_wp\\registration', 'site_info', [&$siteinfo, true]);
    }
}
