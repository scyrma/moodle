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
 * File containing tests for programs tags manager class.
 *
 * @package   tool_program
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\tags_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * File containing tests for programs tags manager class.
 *
 * @covers     \tool_program\tags_manager
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_tags_manager_testcase extends advanced_testcase {

    /** @var tool_program_generator */
    private $generator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->resetAfterTest();
    }

    public function test_get_tagged_certifications(): void {
        $programdata = $this->generator->get_dummy_program_data();
        $program = $this->generator->generate_program($programdata);
        $programdata->fullname = 'Program example two';
        $programdata->program_tags = ['bye', 'dog'];
        $program2 = $this->generator->generate_program($programdata);
        $programdata->fullname = 'Program example three';
        $programdata->program_tags = ['bye', 'cat'];
        $program3 = $this->generator->generate_program($programdata);

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
