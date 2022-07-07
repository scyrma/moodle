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
use context_course;
use html_writer;
use moodle_url;
use tool_catalogue\router;
use tool_program_generator;

/**
 * Unit tests for course exporter
 *
 * @package     tool_catalogue
 * @covers      \tool_catalogue\external\course_exporter
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_exporter_test extends advanced_testcase {

    /**
     * Test course export data
     */
    public function test_export(): void {
        global $PAGE, $OUTPUT;
        $this->resetAfterTest();

        $time = time() + (5 * DAYSECS) + HOURSECS;
        $category = self::getDataGenerator()->create_category();
        $course = self::getDataGenerator()->create_course(['category' => $category->id, 'enddate' => $time]);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');

        $related = [
            'context' => context_course::instance($course->id),
            'course' => $course,
        ];
        $exporter = new course_exporter(null, $related);
        $data = $exporter->export($PAGE->get_renderer('core'));

        $this->assertEquals($course->id, $data->course->id);
        $this->assertEquals($course->fullname, $data->course->fullname);
        $this->assertEquals($category->name, $data->course->coursecategory);
        $this->assertEquals($course->id, $data->course->id);
        $this->assertEquals($time, $data->duedate);
        $this->assertEquals('5 days left', $data->duedatebadgestr);
        $this->assertFalse($data->hasrestrictions);
        $this->assertEmpty($data->restrictions);
        $this->assertEmpty($data->linkedprograms);
        $expectedtrainers = [
            'fullname' => fullname($user),
            'profileurl' => new moodle_url('/user/profile.php', ['id' => $user->id]),
            'userpicture' => $OUTPUT->user_picture($user),
        ];
        $this->assertEquals([$expectedtrainers], $data->trainers);
    }

    /**
     * Test course export restriction data in a single program
     */
    public function test_export_restriction_data_single_program(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $category = self::getDataGenerator()->create_category();
        $course1 = self::getDataGenerator()->create_course(['category' => $category->id]);
        $course2 = self::getDataGenerator()->create_course(['category' => $category->id]);

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');

        $program1 = $programgenerator->generate_program();
        $programgenerator->add_course_to_set((int) $course1->id, $program1->get_base_set()->get('id'), 1);
        $programgenerator->add_course_to_set((int) $course2->id, $program1->get_base_set()->get('id'), 2);

        $programgenerator->allocate_user_to_program($program1->get('id'), (int) $USER->id);

        // Check restrictions for course1.
        $related = [
            'context' => context_course::instance($course1->id),
            'course' => $course1,
        ];
        $exporter = new course_exporter(null, $related);
        $data = $exporter->export($PAGE->get_renderer('core'));

        // Course is the first item in the program and is available.
        $this->assertEquals($course1->id, $data->course->id);
        $this->assertFalse($data->hasrestrictions);
        $this->assertEmpty($data->restrictions);
        $expectedlinkedprograms = [
            'name' => $program1->get_formatted_name(),
            'url' => router::build_program_url($program1->get('id')),
        ];
        $this->assertEquals([$expectedlinkedprograms], $data->linkedprograms);

        // Check restrictions for Course2.
        $related = [
            'context' => context_course::instance($course2->id),
            'course' => $course2,
        ];
        $exporter = new course_exporter(null, $related);
        $data = $exporter->export($PAGE->get_renderer('core'));

        // Course 2 is the second item in the program and is not yet available to the user.
        $this->assertEquals($course2->id, $data->course->id);
        $this->assertTrue($data->hasrestrictions);
        $link = html_writer::link(router::build_course_url((int) $course1->id), $course1->fullname);
        $this->assertEquals([get_string('notavailableuntil', 'tool_catalogue', $link)], $data->restrictions);
        $this->assertEquals([$expectedlinkedprograms], $data->linkedprograms);
    }

    /**
     * Test course export restriction data in several programs
     */
    public function test_export_restriction_data_several_programs(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $category = self::getDataGenerator()->create_category();
        $course1 = self::getDataGenerator()->create_course(['category' => $category->id]);
        $course2 = self::getDataGenerator()->create_course(['category' => $category->id]);

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');

        $program1 = $programgenerator->generate_program();
        $programgenerator->add_course_to_set((int) $course1->id, $program1->get_base_set()->get('id'), 1);
        $programgenerator->add_course_to_set((int) $course2->id, $program1->get_base_set()->get('id'), 2);

        $program2 = $programgenerator->generate_program();
        $programcourse = $programgenerator->add_course_to_set((int) $course1->id, $program2->get_base_set()->get('id'), 1);
        $programgenerator->add_course_to_set((int) $course2->id, $program2->get_base_set()->get('id'), 2);

        $programgenerator->allocate_user_to_program($program1->get('id'), (int) $USER->id);
        $programgenerator->allocate_user_to_program($program2->get('id'), (int) $USER->id);

        // Check restrictions for course1.
        $related = [
            'context' => context_course::instance($course1->id),
            'course' => $course1,
        ];
        $exporter = new course_exporter(null, $related);
        $data = $exporter->export($PAGE->get_renderer('core'));

        // Course is the first item in both programs and is available.
        $this->assertEquals($course1->id, $data->course->id);
        $this->assertFalse($data->hasrestrictions);
        $this->assertEmpty($data->restrictions);
        $expectedlinkedprograms = [
            [
                'name' => $program1->get_formatted_name(),
                'url' => router::build_program_url($program1->get('id')),
            ],
            [
                'name' => $program2->get_formatted_name(),
                'url' => router::build_program_url($program2->get('id')),
            ]
        ];
        $this->assertEqualsCanonicalizing($expectedlinkedprograms, $data->linkedprograms);

        // Check restrictions for Course2.
        $related = [
            'context' => context_course::instance($course2->id),
            'course' => $course2,
        ];
        $exporter = new course_exporter(null, $related);
        $data = $exporter->export($PAGE->get_renderer('core'));

        // Course 2 is the second item in both programs and is not yet available to the user.
        $this->assertEquals($course2->id, $data->course->id);
        $this->assertTrue($data->hasrestrictions);
        $link = html_writer::link(router::build_course_url((int) $course1->id), $course1->fullname);
        $this->assertEquals([
            get_string('notavailableuntil', 'tool_catalogue', $link),
            get_string('notavailableuntil', 'tool_catalogue', $link),
        ], $data->restrictions);
        $this->assertEqualsCanonicalizing($expectedlinkedprograms, $data->linkedprograms);

        // Change sortorder and place course1 as a second item in program2.
        $programcourse->set('sortorder', 3);
        $programcourse->update();

        // Check restrictions for Course2.
        $related = [
            'context' => context_course::instance($course2->id),
            'course' => $course2,
        ];
        $exporter = new course_exporter(null, $related);
        $data = $exporter->export($PAGE->get_renderer('core'));

        // Course2 now is the first item in one of the programs and is unlocked.
        $this->assertFalse($data->hasrestrictions);
        $this->assertEmpty($data->restrictions);
        $this->assertEqualsCanonicalizing($expectedlinkedprograms, $data->linkedprograms);
    }
}
