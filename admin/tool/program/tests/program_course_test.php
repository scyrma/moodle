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

namespace tool_program;

use advanced_testcase;
use stdClass;
use tool_program_generator;
use tool_program\persistent\program;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;

/**
 * Class program_course_test
 *
 * @covers     \tool_program\persistent\program_course
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_course_test extends advanced_testcase {
    /** @var tool_program_generator */
    protected $generator;
    /** @var stdClass */
    protected $course1;
    /** @var stdClass */
    protected $course2;
    /** @var program */
    protected $program1;
    /** @var program_set */
    protected $baseset;
    /** @var program_course */
    protected $programcourse1;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');

        $this->course1 = self::getDataGenerator()->create_course(); // Empty course.
        $this->program1 = $this->generator->generate_program();
        $this->baseset = $this->program1->get_base_set();
        $this->programcourse1 = $this->generator->add_course_to_set($this->course1->id, $this->baseset->get('id'));
    }

    /**
     * Test get program
     */
    public function test_get_program(): void {
        $prog1 = $this->programcourse1->get_program();
        $this->assertInstanceOf(program::class, $prog1);
        $this->assertEquals($this->program1->get('id'), $prog1->get('id'));
        $this->assertEquals($this->program1->get('fullname'), $prog1->get('fullname'));
        $this->assertEquals($this->program1->get('idnumber'), $prog1->get('idnumber'));
    }

    /**
     * Test get set
     */
    public function test_get_set(): void {
        $programset = $this->programcourse1->get_set();
        $this->assertInstanceOf(program_set::class, $programset);
        $this->assertEquals($this->program1->get('id'), $programset->get('programid'));
        $this->assertEquals($this->baseset->get('parent'), $programset->get('parent'));
    }

    /**
     * Test get course
     */
    public function test_get_course(): void {
        $course = $this->programcourse1->get_course();
        $this->assertEquals($this->course1->id, $course->id);
        $this->assertEquals($this->course1->fullname, $course->fullname);
    }
}
