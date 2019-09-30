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
 * File that contains program progress testcase class.
 *
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019  Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_program\persistent\program_course;
use tool_program\persistent\program_set;
use tool_program\program_tree;

defined('MOODLE_INTERNAL') || die();

/**
 * Program progress tests.
 *
 * @covers    \tool_program\program_tree
 * @package    tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019  Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_program_tree_testcase extends advanced_testcase {
    /**
     * @var tool_program_generator
     */
    protected $generator;

    /**
     * setUp.
     */
    public function setUp() {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->resetAfterTest();
    }

    /**
     * Test build and recover the contents of a given program tree/content structure.
     */
    public function test_build_and_recover_program_tree(): void {
        // Check that it is not possible to build a tree when a program has no base set.
        $program = $this->generator->generate_program();
        try {
            new program_tree($program);
            $this->fail('Exception expected');
        } catch (Exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
        }

        // Build "correct" program.
        $program = $this->generator->generate_program_with_base_set();
        $baseset = $program->get_base_set(); // Has completion criteria "all in order".

        // Items within the base set.
        $course11 = self::getDataGenerator()->create_course();
        $programcourse11 = $this->generator->add_course_to_set($course11->id, $baseset->get('id'), 1);
        $childset12 = $this->generator->generate_set((object) [
            'name' => 'Child set 12',
            'programid' => $program->get('id'),
            'parent' => $baseset->get('id'),
            'completioncriteria' => program_set::COMPLETION_ALL_IN_ORDER,
            'completionatleast' => 1,
            'sortorder' => 2,
        ]);
        $course13 = self::getDataGenerator()->create_course();
        $programcourse13 = $this->generator->add_course_to_set($course13->id, $baseset->get('id'), 3);
        $childset21 = $this->generator->generate_set((object) [
            'name' => 'Child set 21',
            'programid' => $program->get('id'),
            'parent' => $childset12->get('id'),
            'completioncriteria' => program_set::COMPLETION_ALL_IN_ANY_ORDER,
            'completionatleast' => 1,
            'sortorder' => 1,
        ]);
        $programcourse22 = $this->generator->add_course_to_set($course13->id, $childset12->get('id'), 2);

        /*
         * Initial structure:
         * 1 Base set
         *      1 Programcourse11 / Course11
         *      2 Childset12
         *          1 Childset21
         *          2 Programcourse22 / Course13
         *      3 Programcourse13 / Course13
         */

        $programtree = new program_tree($program);
        $this->assertDebuggingNotCalled();

        // Check the tree has been correctly build (the structure is correctly recovered).
        $basesetitem = $programtree->get_baseset();

        // Check base set.
        $treebaseset = $basesetitem->get_persistent();
        $this->assertInstanceOf(program_set::class, $treebaseset);
        $this->assertEquals($baseset->to_record(), $treebaseset->to_record());

        // Check base set children.
        $this->assertCount(3, $basesetitem->items);

        $programcourse11item = $basesetitem->items[0];
        $treeprogramcourse11 = $programcourse11item->get_persistent();
        $this->assertInstanceOf(program_course::class, $treeprogramcourse11);
        $this->assertEquals($programcourse11->to_record(), $treeprogramcourse11->to_record());
        $this->assertEquals($course11->id, $programcourse11item->get_course()->id);

        $childset12item = $basesetitem->items[1];
        $treechildset12 = $childset12item->get_persistent();
        $this->assertInstanceOf(program_set::class, $treechildset12);
        $this->assertEquals($childset12->to_record(), $treechildset12->to_record());

        $programcourse13item = $basesetitem->items[2];
        $treeprogramcourse13 = $programcourse13item->get_persistent();
        $this->assertInstanceOf(program_course::class, $treeprogramcourse13);
        $this->assertEquals($programcourse13->to_record(), $treeprogramcourse13->to_record());
        $this->assertEquals($course13->id, $programcourse13item->get_course()->id);

        // Check Childset12 children.
        $this->assertCount(2, $childset12item->items);

        $childset21item = $childset12item->items[0];
        $treechildset21 = $childset21item->get_persistent();
        $this->assertInstanceOf(program_set::class, $treechildset21);
        $this->assertEquals($childset21->to_record(), $treechildset21->to_record());

        $programcourse22item = $childset12item->items[1];
        $treeprogramcourse22 = $programcourse22item->get_persistent();
        $this->assertInstanceOf(program_course::class, $treeprogramcourse22);
        $this->assertEquals($programcourse22->to_record(), $treeprogramcourse22->to_record());
        $this->assertEquals($course13->id, $programcourse22item->get_course()->id);

        // Check get items without base set returns the same structure than the one obtained with get base set.
        $this->assertEquals($programtree->get_baseset()->items, $programtree->get_baseset_children_items());

        // Check that get branch by parentsetid returns a set with the same structure than the set obtained with get base set.
        $this->assertEquals($programtree->get_baseset()->items[1], $programtree->get_branch_by_parentsetid($childset12->get('id')));

        // Check that get branch by parentsetid returns null when unkown child set id provided.
        $this->assertNull($programtree->get_branch_by_parentsetid(-1));

        // Check that to list method returns an ordered list with the expected contents.
        $itemslist = $programtree->to_list();
        $this->assertCount(6, $itemslist);

        // Check base set.
        $basesetitem = $itemslist[0];
        $treebaseset = $basesetitem->get_persistent();
        $this->assertInstanceOf(program_set::class, $treebaseset);
        $this->assertEquals($baseset->to_record(), $treebaseset->to_record());

        $programcourse11item = $itemslist[1];
        $treeprogramcourse11 = $programcourse11item->get_persistent();
        $this->assertInstanceOf(program_course::class, $treeprogramcourse11);
        $this->assertEquals($programcourse11->to_record(), $treeprogramcourse11->to_record());
        $this->assertEquals($course11->id, $programcourse11item->get_course()->id);

        $childset12item = $itemslist[2];
        $treechildset12 = $childset12item->get_persistent();
        $this->assertInstanceOf(program_set::class, $treechildset12);
        $this->assertEquals($childset12->to_record(), $treechildset12->to_record());

        $childset21item = $itemslist[3];
        $treechildset21 = $childset21item->get_persistent();
        $this->assertInstanceOf(program_set::class, $treechildset21);
        $this->assertEquals($childset21->to_record(), $treechildset21->to_record());

        $programcourse22item = $itemslist[4];
        $treeprogramcourse22 = $programcourse22item->get_persistent();
        $this->assertInstanceOf(program_course::class, $treeprogramcourse22);
        $this->assertEquals($programcourse22->to_record(), $treeprogramcourse22->to_record());
        $this->assertEquals($course13->id, $programcourse22item->get_course()->id);

        $programcourse13item = $itemslist[5];
        $treeprogramcourse13 = $programcourse13item->get_persistent();
        $this->assertInstanceOf(program_course::class, $treeprogramcourse13);
        $this->assertEquals($programcourse13->to_record(), $treeprogramcourse13->to_record());
        $this->assertEquals($course13->id, $programcourse13item->get_course()->id);

        // Check that get_first_program_course_item_by_courseid returns the expected program course.
        $programcourse = $programtree->get_first_program_course_item_by_courseid($course13->id);
        $this->assertNotNull($programcourse);
        $this->assertEquals($programcourse22->to_record(), $programcourse->get_persistent()->to_record());

        // Check that get_first_program_course_item_by_courseid returns null if unkown course id provided.
        $programcourse = $programtree->get_first_program_course_item_by_courseid(-1);
        $this->assertNull($programcourse);
    }
}
