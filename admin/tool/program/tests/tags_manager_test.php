<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * File containing tests for programs tags manager class.
 *
 * @package   tool_program
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_program\api;
use tool_program\tags_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * File containing tests for programs tags manager class.
 *
 * @covers     \tool_program\tags_manager
 * @package    tool_program
 * @copyright  2019 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_program_tags_manager_testcase extends advanced_testcase {
    /**
     * @var tool_program_generator
     */
    private $generator;

    /**
     * setUp.
     */
    public function setUp() {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->resetAfterTest();
    }

    public function test_get_tagged_certifications(): void {
        $programdata = $this->generator->get_dummy_program_data();
        $this->generator->add_dummy_program_tags($programdata);
        $program = api::create_program($programdata);
        $programdata->fullname = 'Program example two';
        $programdata->program_tags = ['bye', 'dog'];
        $program2 = api::create_program($programdata);
        $programdata->fullname = 'Program example three';
        $programdata->program_tags = ['bye', 'cat'];
        $program3 = api::create_program($programdata);

        $this->setAdminUser();

        $res = tags_manager::get_tagged_programs(core_tag_tag::get_by_name(0, 'hello'));
        $this->assertRegExp('/' . $program->get('fullname') . '/', $res->content);
        $this->assertNotRegExp('/' . $program2->get('fullname') . '/', $res->content);
        $this->assertNotRegExp('/' . $program3->get('fullname') . '/', $res->content);

        $res = tags_manager::get_tagged_programs(core_tag_tag::get_by_name(0, 'bye'));
        $this->assertNotRegExp('/' . $program->get('fullname') . '/', $res->content);
        $this->assertRegExp('/' . $program2->get('fullname') . '/', $res->content);
        $this->assertRegExp('/' . $program3->get('fullname') . '/', $res->content);

        $res = tags_manager::get_tagged_programs(core_tag_tag::get_by_name(0, 'dog'));
        $this->assertNotRegExp('/' . $program->get('fullname') . '/', $res->content);
        $this->assertRegExp('/' . $program2->get('fullname') . '/', $res->content);
        $this->assertNotRegExp('/' . $program3->get('fullname') . '/', $res->content);

        // Not context system.
        $course = self::getDataGenerator()->create_course();
        $othercontext = context_course::instance($course->id)->id;
        $res = tags_manager::get_tagged_programs(core_tag_tag::get_by_name(0, 'bye'), false, $othercontext, $othercontext);

        $this->assertNotRegExp('/' . $program->get('fullname') . '/', $res->content);
        $this->assertNotRegExp('/' . $program2->get('fullname') . '/', $res->content);
        $this->assertNotRegExp('/' . $program3->get('fullname') . '/', $res->content);
    }
}
