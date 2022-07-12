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
use context_system;
use core_course\external\course_summary_exporter;
use html_writer;
use tool_catalogue\manager;
use tool_catalogue\router;
use tool_certification\constants;
use tool_certification_generator;
use tool_program\persistent\program_set;
use tool_program\persistent\program_user;
use tool_program_generator;

/**
 * Unit tests for program exporter
 *
 * @package     tool_catalogue
 * @covers      \tool_catalogue\external\program_exporter
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_exporter_test extends advanced_testcase {

    /**
     * Test program export data
     */
    public function test_export(): void {
        global $PAGE, $USER, $OUTPUT;
        $this->resetAfterTest();
        $this->setAdminUser();

        $cfgenerator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        // Define one customfield.
        $params = [
            'component' => 'tool_program',
            'area' => 'program',
            'itemid' => 0,
            'contextid' => context_system::instance()->id
        ];
        $category = $cfgenerator->create_category($params);
        $cfgenerator->create_field(['categoryid' => $category->get('id'),
            'type' => 'text', 'shortname' => 'fld1']);

        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $course1 = $programgenerator->generate_course_with_completion_self();
        $course2 = $programgenerator->generate_course_with_completion_self();
        $program = $programgenerator->generate_program((object)[
            'customfield_fld1' => 'Hello123',
            'program_tags' => ['multiverse', 'recursion'],
        ]);
        $baseset = $program->get_base_set();
        $baseset->set('completioncriteria', program_set::COMPLETION_ALL_IN_ORDER);
        $baseset->update();

        $programgenerator->add_course_to_set((int) $course1->id, $baseset->get('id'), 1);
        $programgenerator->add_course_to_set((int) $course2->id, $baseset->get('id'), 2);
        $programgenerator->allocate_user_to_program($program->get('id'), (int) $USER->id);

        $related = [
            'context' => $program->get_context(),
            'allocations' => manager::get_user_allocations($program->get('id')),
        ];
        $exporter = new program_exporter($program, $related);
        $data = $exporter->export($PAGE->get_renderer('core'));

        $this->assertEquals($program->get('id'), $data->id);
        $this->assertEquals($program->get('fullname'), $data->fullname);
        $this->assertCount(1, $data->customfields);
        $this->assertEquals('Hello123', $data->customfields[0]->value);
        $this->assertTrue($data->hastags);
        $this->assertEqualsCanonicalizing(['multiverse', 'recursion'], $data->tags);
        $this->assertEquals(2, $data->numcourses);
        $this->assertEquals(0, $data->lastaccess);
        $this->assertFalse($data->hascertifications);
        $this->assertEmpty($data->certifications);

        // Base set.
        $this->assertTrue($data->programstructure->isset);
        $this->assertEquals($baseset->get('id'), $data->programstructure->setid);
        $this->assertEquals('Complete all in order', $data->programstructure->setcriteriastr);
        $this->assertFalse($data->programstructure->iscompleted);

        // Base set items.
        $image1 = course_summary_exporter::get_course_image($course1);
        if (!$image1) {
            $image1 = $OUTPUT->get_generated_image_for_id($course1->id);
        }
        $expectedcourseitem1 = (object)[
            'isset' => false,
            'courseid' => (int) $course1->id,
            'fullname' => $course1->fullname,
            'categoryname' => '',
            'iscompleted' => false,
            'progress' => 0,
            'islocked' => false,
            'ishidden' => false,
            'image' => $image1,
            'url' => router::build_course_url((int) $course1->id),
            'setid' => 0,
        ];

        $image2 = course_summary_exporter::get_course_image($course2);
        if (!$image2) {
            $image2 = $OUTPUT->get_generated_image_for_id($course2->id);
        }
        $link = html_writer::link(router::build_course_url((int) $course1->id), $course1->fullname);

        $expectedcourseitem2 = (object)[
            'isset' => false,
            'courseid' => (int) $course2->id,
            'fullname' => $course2->fullname,
            'categoryname' => '',
            'iscompleted' => false,
            'progress' => 0,
            'islocked' => true,
            'ishidden' => false,
            'image' => $image2,
            'url' => router::build_course_url((int) $course2->id),
            'setid' => 0,
            'unlockrequirement' => get_string('notavailableuntil', 'tool_catalogue', $link),
        ];
        $this->assertEquals([$expectedcourseitem1, $expectedcourseitem2], $data->programstructure->items);
    }

    /**
     * Test program export data about related certification messages
     */
    public function test_export_certification_messages(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $duedate = strtotime('+2 day');

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');

        $program = $programgenerator->generate_program();
        $programgenerator->allocate_user_to_program($program->get('id'), (int) $USER->id);

        /** @var tool_certification_generator $certificationgenerator */
        $certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');

        $certification1 = $certificationgenerator->generate_certification([
            'fullname' => 'Certification 1',
            'program' => $program->get('id'),
            'duedatetype' => constants::DATE_ABSOLUTE,
            'duedateabsolute' => $duedate,
        ]);
        $certificationgenerator->allocate_user((int) $USER->id, $certification1->get('id'));
        $certification2 = $certificationgenerator->generate_certification([
            'fullname' => 'Certification 2',
            'program' => $program->get('id'),
            'duedatetype' => constants::DATE_ABSOLUTE,
            'duedateabsolute' => $duedate,
        ]);
        $certificationgenerator->allocate_user((int) $USER->id, $certification2->get('id'));

        $related = [
            'context' => $program->get_context(),
            'allocations' => manager::get_user_allocations($program->get('id')),
        ];
        $exporter = new program_exporter($program, $related);
        $data = $exporter->export($PAGE->get_renderer('core'));

        $this->assertEquals($program->get('id'), $data->id);
        $this->assertTrue($data->hascertifications);

        $a1 = ['name' => 'Certification 1', 'date' => userdate($duedate, get_string('strftimedatefullshort', 'langconfig'))];
        $a2 = ['name' => 'Certification 2', 'date' => userdate($duedate, get_string('strftimedatefullshort', 'langconfig'))];

        $allocation1 = program_user::get_record([
            'certificationid' => $certification1->get('id'),
            'userid' => $USER->id,
            'programid' => $program->get('id'),
        ]);
        $allocation2 = program_user::get_record([
            'certificationid' => $certification2->get('id'),
            'userid' => $USER->id,
            'programid' => $program->get('id'),
        ]);

        $expectedcertificationmessages = [
            [
                'message' => get_string('certificationstatusopenwithdate', 'tool_catalogue', $a1),
                'type' => 'info',
                'hash' => md5('certificationstatusopenwithdate' . $allocation1->get('id')),
            ],
            [
                'message' => get_string('certificationstatusopenwithdate', 'tool_catalogue', $a2),
                'type' => 'info',
                'hash' => md5('certificationstatusopenwithdate' . $allocation2->get('id')),
            ]
        ];
        $this->assertEqualsCanonicalizing($expectedcertificationmessages, $data->certifications);
    }
}
