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

namespace tool_catalogue\external;

use advanced_testcase;
use html_writer;
use tool_catalogue\router;
use tool_program\persistent\program_set;
use tool_program\program_tree_progress;
use tool_program_generator;

/**
 * Unit tests for program content exporter
 *
 * The program content exporter returns the structure of the program content (sets and courses) with all
 * the information needed.
 *
 * @package     tool_catalogue
 * @covers      \tool_catalogue\external\program_content_exporter
 * @covers      \tool_catalogue\external\program\set_exporter
 * @covers      \tool_catalogue\external\program\course_exporter
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_content_exporter_test extends advanced_testcase {

    /**
     * Test program export data
     */
    public function test_export(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $course1 = $programgenerator->generate_course_with_completion_self();
        $course2 = $programgenerator->generate_course_with_completion_self();
        $course3 = $programgenerator->generate_course_with_completion_self();
        $course4 = $programgenerator->generate_course_with_completion_self();
        $program = $programgenerator->generate_program();
        $baseset = $program->get_base_set();
        $baseset->set('completioncriteria', program_set::COMPLETION_AT_LEAST);
        $baseset->set('completionatleast', 1);
        $baseset->update();
        $set1 = $programgenerator->generate_set((object) [
            'name' => 'My child set 1',
            'programid' => $program->get('id'),
            'parent' => $baseset->get('id'),
            'sortorder' => 1,
            'completioncriteria' => program_set::COMPLETION_ALL_IN_ORDER,
        ]);

        $programgenerator->add_course_to_set((int) $course1->id, $baseset->get('id'), 2);
        $programgenerator->add_course_to_set((int) $course2->id, $baseset->get('id'), 3);
        $programgenerator->add_course_to_set((int) $course3->id, $set1->get('id'), 1);
        $programgenerator->add_course_to_set((int) $course4->id, $set1->get('id'), 2);
        $programgenerator->allocate_user_to_program($program->get('id'), (int) $USER->id);

        // Fetch the program structure with all related data for the current user.
        $treeprogress = new program_tree_progress($program, (int) $USER->id);
        $exporter = new program_content_exporter(null, [
            'context' => $program->get_context(),
            'treeprogress' => $treeprogress,
            'courses' => $program->get_courses(),
        ]);
        $data = $exporter->export($PAGE->get_renderer('core'));

        // Base set.
        $this->assertTrue($data->baseset->isset);
        $this->assertEquals($baseset->get('id'), $data->baseset->setid);
        $this->assertEquals(get_string('completeatleast', 'tool_catalogue', '1'), $data->baseset->setcriteriastr);
        $this->assertFalse($data->baseset->iscompleted);

        $basesetitems = $data->baseset->items;
        $this->assertCount(3, $basesetitems);

        // Assert base set contains Set1.
        $this->assertEquals('My child set 1', $basesetitems[0]->fullname);
        $this->assertFalse($basesetitems[0]->iscompleted);
        $this->assertEquals(0, $basesetitems[0]->progress);
        $this->assertFalse($basesetitems[0]->islocked);
        $this->assertEquals($set1->get('id'), $basesetitems[0]->setid);
        $this->assertEquals(get_string('completeallinorder', 'tool_program'), $basesetitems[0]->setcriteriastr);
        $this->assertEquals(0, $basesetitems[0]->completeditems);
        $this->assertNotEmpty($basesetitems[0]->items);
        $this->assertCount(2, $basesetitems[0]->mosaicimages);

        // Assert base set contains Course1.
        $this->assertEquals($course1->id, $basesetitems[1]->courseid);
        $this->assertEquals($course1->fullname, $basesetitems[1]->fullname);
        $this->assertFalse($basesetitems[1]->iscompleted);
        $this->assertEquals(0, $basesetitems[1]->progress);
        $this->assertFalse($basesetitems[1]->islocked);
        $this->assertFalse($basesetitems[1]->ishidden);

        // Assert base set contains Course2.
        $this->assertEquals($course2->id, $basesetitems[2]->courseid);
        $this->assertEquals($course2->fullname, $basesetitems[2]->fullname);
        $this->assertFalse($basesetitems[2]->iscompleted);
        $this->assertEquals(0, $basesetitems[2]->progress);
        $this->assertFalse($basesetitems[2]->islocked);
        $this->assertFalse($basesetitems[2]->ishidden);

        $setitems = $basesetitems[0]->items;
        $this->assertCount(2, $setitems);

        // Assert Set1 contains Course3.
        $this->assertEquals($course3->id, $setitems[0]->courseid);
        $this->assertEquals($course3->fullname, $setitems[0]->fullname);
        $this->assertFalse($setitems[0]->iscompleted);
        $this->assertEquals(0, $setitems[0]->progress);
        $this->assertFalse($setitems[0]->islocked);
        $this->assertFalse($setitems[0]->ishidden);

        // Assert Set1 contains Course4.
        $this->assertEquals($course4->id, $setitems[1]->courseid);
        $this->assertEquals($course4->fullname, $setitems[1]->fullname);
        $this->assertFalse($setitems[1]->iscompleted);
        $this->assertEquals(0, $setitems[1]->progress);
        $this->assertTrue($setitems[1]->islocked);
        $this->assertFalse($setitems[1]->ishidden);
        // This course is locked. Course3 has to be completed before being able to unlock this one.
        $link = html_writer::link(router::build_course_url((int) $course3->id), $course3->fullname);
        $expectedstr = get_string('notavailableuntil', 'tool_catalogue', $link);
        $this->assertEquals($expectedstr, $setitems[1]->unlockrequirement);
    }

    /**
     * Test program export data has correct unlock requirements
     */
    public function test_export_unlock_requirements(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $course1 = $programgenerator->generate_course_with_completion_self();
        $course2 = $programgenerator->generate_course_with_completion_self();
        $course3 = $programgenerator->generate_course_with_completion_self();
        $course4 = $programgenerator->generate_course_with_completion_self();
        $program = $programgenerator->generate_program();
        $baseset = $program->get_base_set();
        $baseset->set('completioncriteria', program_set::COMPLETION_ALL_IN_ORDER);
        $baseset->update();
        $set1 = $programgenerator->generate_set((object) [
            'name' => 'My child set 1',
            'programid' => $program->get('id'),
            'parent' => $baseset->get('id'),
            'sortorder' => 1,
            'completioncriteria' => program_set::COMPLETION_ALL_IN_ORDER,
        ]);

        $programgenerator->add_course_to_set((int) $course1->id, $baseset->get('id'), 2);
        $programgenerator->add_course_to_set((int) $course2->id, $baseset->get('id'), 3);
        $programgenerator->add_course_to_set((int) $course3->id, $set1->get('id'), 1);
        $programgenerator->add_course_to_set((int) $course4->id, $set1->get('id'), 2);
        $programgenerator->allocate_user_to_program($program->get('id'), (int) $USER->id);

        // Fetch the program structure with all related data for the current user.
        $treeprogress = new program_tree_progress($program, (int) $USER->id);
        $exporter = new program_content_exporter(null, [
            'context' => $program->get_context(),
            'treeprogress' => $treeprogress,
            'courses' => $program->get_courses(),
        ]);
        $data = $exporter->export($PAGE->get_renderer('core'));

        // Base set.
        $this->assertTrue($data->baseset->isset);
        $this->assertEquals($baseset->get('id'), $data->baseset->setid);
        $this->assertEquals(get_string('completeallinorder', 'tool_program'), $data->baseset->setcriteriastr);
        $this->assertFalse($data->baseset->iscompleted);

        $basesetitems = $data->baseset->items;
        $this->assertCount(3, $basesetitems);

        // Assert base set contains Set1.
        $this->assertEquals('My child set 1', $basesetitems[0]->fullname);
        $this->assertFalse($basesetitems[0]->iscompleted);
        $this->assertEquals(0, $basesetitems[0]->progress);
        $this->assertFalse($basesetitems[0]->islocked);
        $this->assertEquals($set1->get('id'), $basesetitems[0]->setid);
        $this->assertEquals(get_string('completeallinorder', 'tool_program'), $basesetitems[0]->setcriteriastr);
        $this->assertEquals(0, $basesetitems[0]->completeditems);
        $this->assertNotEmpty($basesetitems[0]->items);
        // This set is the first element and is unlocked.

        // Assert base set contains Course1.
        $this->assertEquals($course1->id, $basesetitems[1]->courseid);
        $this->assertEquals($course1->fullname, $basesetitems[1]->fullname);
        $this->assertFalse($basesetitems[1]->iscompleted);
        $this->assertEquals(0, $basesetitems[1]->progress);
        $this->assertTrue($basesetitems[1]->islocked);
        $this->assertFalse($basesetitems[1]->ishidden);
        // This course is locked. Set has to be completed before being able to unlcok this course.
        $link = html_writer::link(router::build_program_set_url($program->get('id'), $set1->get('id')), $set1->get('name'));
        $expectedstr = get_string('notavailableuntil', 'tool_catalogue', $link);
        $this->assertEquals($expectedstr, $basesetitems[1]->unlockrequirement);

        // Assert base set contains Course2.
        $this->assertEquals($course2->id, $basesetitems[2]->courseid);
        $this->assertEquals($course2->fullname, $basesetitems[2]->fullname);
        $this->assertFalse($basesetitems[2]->iscompleted);
        $this->assertEquals(0, $basesetitems[2]->progress);
        $this->assertTrue($basesetitems[2]->islocked);
        $this->assertFalse($basesetitems[2]->ishidden);
        // This course is locked. Course1 has to be completed before being able to unlcok this one.
        $link = html_writer::link(router::build_course_url((int) $course1->id), $course1->fullname);
        $expectedstr = get_string('notavailableuntil', 'tool_catalogue', $link);
        $this->assertEquals($expectedstr, $basesetitems[2]->unlockrequirement);

        $setitems = $basesetitems[0]->items;
        $this->assertCount(2, $setitems);

        // Assert Set1 contains Course3.
        $this->assertEquals($course3->id, $setitems[0]->courseid);
        $this->assertEquals($course3->fullname, $setitems[0]->fullname);
        $this->assertFalse($setitems[0]->iscompleted);
        $this->assertEquals(0, $setitems[0]->progress);
        $this->assertFalse($setitems[0]->islocked);
        $this->assertFalse($setitems[0]->ishidden);
        // This course is the first element of the first set in the program base and is unlocked.

        // Assert Set1 contains Course4.
        $this->assertEquals($course4->id, $setitems[1]->courseid);
        $this->assertEquals($course4->fullname, $setitems[1]->fullname);
        $this->assertFalse($setitems[1]->iscompleted);
        $this->assertEquals(0, $setitems[1]->progress);
        $this->assertTrue($setitems[1]->islocked);
        $this->assertFalse($setitems[1]->ishidden);
        // This course is locked. Course3 has to be completed before being able to unlcok this one.
        $link = html_writer::link(router::build_course_url((int) $course3->id), $course3->fullname);
        $expectedstr = get_string('notavailableuntil', 'tool_catalogue', $link);
        $this->assertEquals($expectedstr, $setitems[1]->unlockrequirement);
    }
}
