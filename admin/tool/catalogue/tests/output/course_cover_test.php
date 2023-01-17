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

namespace tool_catalogue\output;

use advanced_testcase;

/**
 * Unit tests for course cover
 *
 * The course cover class returns all the course information.
 *
 * @package     tool_catalogue
 * @covers      \tool_catalogue\output\course_cover
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Odei Alba <odei.alba@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_cover_test extends advanced_testcase {

    /**
     * Test course_cover export_for_template
     */
    public function test_export_for_template(): void {
        global $PAGE, $CFG;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Generate Course.
        $course = self::getDataGenerator()->create_course((object) ['summary' => 'My description']);

        /** @var \tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');

        // Generate programs.
        $program1 = $programgenerator->generate_program();
        $program2 = $programgenerator->generate_program();
        $programgenerator->add_course_to_set((int) $course->id, $program1->get_base_set()->get('id'), 1);
        $programgenerator->add_course_to_set((int) $course->id, $program2->get_base_set()->get('id'), 1);
        $programgenerator->allocate_user_to_program($program1->get('id'), (int) $user->id);
        $programgenerator->allocate_user_to_program($program2->get('id'), (int) $user->id);

        // Add files to the course overview.
        $CFG->courseoverviewfileslimit = 2;
        $CFG->courseoverviewfilesext = '*';

        $fs = get_file_storage();
        $coursecontext = \context_course::instance($course->id);
        $coursefilerecord = [
            'contextid' => $coursecontext->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => '0',
            'filepath' => '/',
            'filename' => 'image.png'
        ];
        $fs->create_file_from_string($coursefilerecord, 'awesome image');
        $coursefilerecord['filename'] = 'blank.pdf';
        $fs->create_file_from_string($coursefilerecord, 'pdf contents');

        // Create trainer user.
        $traineruser = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($traineruser->id, $course->id, 'editingteacher');

        // Set the config to show dates.
        set_config('showcoursedates', true, 'tool_catalogue');

        $renderer = $PAGE->get_renderer('tool_catalogue');
        $coursecover = new course_cover($course);
        $coursecoverdata = $coursecover->export_for_template($renderer);

        $this->assertEquals((int) $course->id, $coursecoverdata->courseid);
        $this->assertEquals($user->id, $coursecoverdata->userid);
        $this->assertEquals(\tool_catalogue\router::build_course_url((int) $course->id), $coursecoverdata->courseurl);
        $this->assertEquals(format_text('My description'), $coursecoverdata->description);
        $this->assertFalse($coursecoverdata->pagesectiondescription->collapse);
        $this->assertTrue($coursecoverdata->hashelp);
        $this->assertEquals(new \moodle_url('/admin/tool/catalogue/pix/help_course.svg'), $coursecoverdata->helpimage);
        $this->assertEquals(get_string('coursecoverhelpmultiprogram', 'tool_catalogue'), $coursecoverdata->helptitle);
        $this->assertEquals(get_string('coursecoverhelptext', 'tool_catalogue'), $coursecoverdata->helptext);
        $this->assertTrue($coursecoverdata->multiprogram);

        $this->assertCount(2, $coursecoverdata->linkedprograms);
        $expectedlinkedprograms = [
            $program1->get('id'),
            $program2->get('id'),
        ];
        $actuallinkedprograms = [
            $coursecoverdata->linkedprograms[0]['id'],
            $coursecoverdata->linkedprograms[1]['id'],
        ];
        $this->assertEqualsCanonicalizing($expectedlinkedprograms, $actuallinkedprograms);

        $this->assertCount(2, $coursecoverdata->programlinks);
        $expectedprogramlinks = [
            \tool_catalogue\router::build_program_url($program1->get('id'))->out(),
            \tool_catalogue\router::build_program_url($program2->get('id'))->out(),
        ];
        $actualprogramlinks = [
            $coursecoverdata->programlinks[0]['url'],
            $coursecoverdata->programlinks[1]['url'],
        ];
        $this->assertEqualsCanonicalizing($expectedprogramlinks, $actualprogramlinks);

        $this->assertEquals(0, $coursecoverdata->nummore);
        $this->assertCount(1, $coursecoverdata->trainers);
        $this->assertEquals($traineruser->id, $coursecoverdata->trainers[0]['id']);
        $this->assertFalse($coursecoverdata->pagesectiontrainers->collapse);
        $this->assertCount(2, $coursecoverdata->coursefiles);
        $this->assertFalse($coursecoverdata->pagesectionfiles->collapse);
        $this->assertNotEmpty($coursecoverdata->pagesectiondates);
        $dateformat = get_string('strftimedatefullshort', 'langconfig');
        $this->assertEquals(userdate($course->startdate, $dateformat), $coursecoverdata->startdate);

        // Set the config to hide dates.
        set_config('showcoursedates', false, 'tool_catalogue');

        $coursecoverdata = $coursecover->export_for_template($renderer);
        $this->assertObjectNotHasAttribute('pagesectiondates', $coursecoverdata);
    }
}
