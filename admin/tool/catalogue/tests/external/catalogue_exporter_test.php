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
use stdClass;
use tool_catalogue\router;
use tool_program\constants;
use tool_program_generator;

/**
 * Unit tests for catalogue exporter
 *
 * @package     tool_catalogue
 * @covers      \tool_catalogue\external\catalogue_exporter
 * @covers      \tool_catalogue\external\program\course_exporter
 * @covers      \tool_catalogue\external\program\set_exporter
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class catalogue_exporter_test extends advanced_testcase {

    /**
     * Test catalogue export data
     */
    public function test_export(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        set_config('showcataloguecoursecategory', '1', 'tool_catalogue');
        $this->setAdminUser();

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');

        // Generate program and allocate user.
        $duedate1 = time() + (DAYSECS * 7) + HOURSECS;
        $program1 = $programgenerator->generate_program((object) [
            'fullname' => 'My program 1',
            'duedatetype' => constants::DATE_ABSOLUTE,
            'duedateabsolute' => $duedate1,
        ]);
        $programgenerator->allocate_user_to_program($program1->get('id'), (int) $USER->id);

        // Generate Course1 and allocate user.
        $category1 = self::getDataGenerator()->create_category(['name' => 'Cat 1']);
        $course1 = self::getDataGenerator()->create_course((object)['category' => $category1->id, 'fullname' => 'My course 1']);
        $category2 = self::getDataGenerator()->create_category(['name' => 'Cat 2']);
        $this->getDataGenerator()->enrol_user((int) $USER->id, $course1->id, 'student');

        // Generate Course2, allocate user and set course as completed for the user.
        $params = (object)['category' => $category2->id, 'fullname' => 'My course 2'];
        $course2 = $programgenerator->generate_course_with_completion_self($params);
        $this->getDataGenerator()->enrol_user((int) $USER->id, $course2->id, 'student');
        $programgenerator->complete_courses([$course2->id], (int) $USER->id);

        // Order results by name.
        $related = [
            'filter' => '',
            'sort' => 'name',
            'search' => '',
        ];
        // Call the 'my_courses' exporter with the related data.
        $exporter = new catalogue_exporter(null, $related);
        $data = $exporter->export($PAGE->get_renderer('core'));

        $this->assertCount(3, $data->listitems);

        // First item in the list should be Course1.
        $this->assertNotEmpty($data->listitems[0]->image);
        $this->assertEquals('My course 1', $data->listitems[0]->fullname);
        $this->assertEquals(constants::MAX_DATE, $data->listitems[0]->duedate);
        $this->assertEquals('', $data->listitems[0]->duedatebadgestr);
        $this->assertEquals('0.0', $data->listitems[0]->progress);
        $this->assertEquals('Cat 1', $data->listitems[0]->categoryname);
        $this->assertEquals(0, $data->listitems[0]->lastaccess);
        $this->assertEquals(router::build_course_url((int) $course1->id), $data->listitems[0]->url);
        $this->assertFalse($data->listitems[0]->isprogram);

        // Second item in the list should be Course2.
        $this->assertNotEmpty($data->listitems[1]->image);
        $this->assertEquals('My course 2', $data->listitems[1]->fullname);
        $this->assertEquals(constants::MAX_DATE, $data->listitems[1]->duedate);
        $this->assertEquals('', $data->listitems[1]->duedatebadgestr);
        $this->assertEquals('100', $data->listitems[1]->progress);
        $this->assertEquals('Cat 2', $data->listitems[1]->categoryname);
        $this->assertEquals(0, $data->listitems[1]->lastaccess);
        $this->assertEquals(router::build_course_url((int) $course2->id), $data->listitems[1]->url);
        $this->assertFalse($data->listitems[1]->isprogram);

        // Third item in the list should be Program1.
        $this->assertEquals('My program 1', $data->listitems[2]->fullname);
        $this->assertNotEmpty($data->listitems[2]->image);
        $this->assertEquals('0.0', $data->listitems[2]->progress);
        $this->assertEquals(0, $data->listitems[2]->numcourses);
        $this->assertEquals(0, $data->listitems[2]->lastaccess);
        $this->assertEquals($duedate1, $data->listitems[2]->duedate);
        $this->assertEquals('Due in 7 days', $data->listitems[2]->duedatebadgestr);
        $this->assertEquals(router::build_program_url($program1->get('id')), $data->listitems[2]->url);
        $this->assertTrue($data->listitems[2]->isprogram);
    }

    /**
     * Data provider for test_apply_parameters().
     *
     * @return array
     */
    public function parameters_provider(): array {
        return [
            // Sort by name.
            ['', 'name', '', ['Course A', 'Course B', 'Program A']],
            // Sort by due date.
            ['', 'duedate', '', ['Course A', 'Program A', 'Course B']],
            // Sort by last access.
            ['', 'lastaccess', '', ['Course B', 'Course A', 'Program A']],
            // Filter by courses.
            ['courses', '', '', ['Course B', 'Course A']], // Defaults to sorting by last accessed.
            // Filter by programs.
            ['programs', '', '', ['Program A']],
            // Filter by complete.
            ['complete', '', '', ['Program A']],
            // Filter by incomplete.
            ['incomplete', '', '', ['Course B', 'Course A']],
            // Search.
            ['', '', 'Course B', ['Course B']],
            // Filter by search and incomplete.
            ['incomplete', '', 'A', ['Course A']],
            // Filter by courses and sort by due date.
            ['courses', 'duedate', '', ['Course A', 'Course B']],
            // Filter by courses, sort by due date and search letter 'B'.
            ['courses', 'duedate', 'B', ['Course B']],
            // Sort by due date and search by letter 'A'.
            ['', 'duedate', 'A', ['Course A', 'Program A']],
        ];
    }

    /**
     * Test for applying parameters to the exporter
     *
     * Check different combinations for filter, sorting and the search parameters on the exporter.
     *
     * @dataProvider parameters_provider
     * @param string $filter
     * @param string $sort
     * @param string $search
     * @param array $expected
     * @return void
     */
    public function test_apply_parameters(string $filter, string $sort, string $search, array $expected): void {
        global $PAGE, $USER, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $duedate1 = time() + (DAYSECS * 7) + HOURSECS;
        $program1 = $programgenerator->generate_program_with_course((object) [
            'fullname' => 'Program A',
            'duedatetype' => constants::DATE_ABSOLUTE,
            'duedateabsolute' => $duedate1,
        ]);
        $duedate2 = time() + (DAYSECS * 4) + HOURSECS;
        $course1 = self::getDataGenerator()->create_course((object)['fullname' => 'Course A', 'enddate' => $duedate2]);
        $course2 = self::getDataGenerator()->create_course((object)['fullname' => 'Course B']); // No end/due date set.
        $programgenerator->allocate_user_to_program($program1->get('id'), (int) $USER->id);
        $this->getDataGenerator()->enrol_user((int) $USER->id, $course1->id, 'student');
        $this->getDataGenerator()->enrol_user((int) $USER->id, $course2->id, 'student');

        $programgenerator->complete_program($program1, (int) $USER->id);
        $DB->insert_record('user_lastaccess',
            ['userid' => $USER->id, 'courseid' => $course2->id, 'timeaccess' => time() - DAYSECS]);
        $DB->insert_record('user_lastaccess',
            ['userid' => $USER->id, 'courseid' => $course1->id, 'timeaccess' => time() - 2 * DAYSECS]);

        $related = [
            'filter' => $filter,
            'sort' => $sort,
            'search' => $search,
        ];
        // Call the 'catalogue' exporter with the related data.
        $exporter = new catalogue_exporter(null, $related);
        $data = $exporter->export($PAGE->get_renderer('core'));

        $names = array_map(static function(stdClass $item): string {
            return $item->fullname;
        }, $data->listitems);

        $this->assertEquals($expected, array_values($names));
    }

    /**
     * Test catalogue settings
     */
    public function test_catalogue_settings(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_program_generator $programgenerator */
        $programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');

        // Generate program and allocate user.
        $duedate1 = time() + (DAYSECS * 7) + HOURSECS;
        $program1 = $programgenerator->generate_program((object) [
            'fullname' => 'My program 1',
            'duedatetype' => constants::DATE_ABSOLUTE,
            'duedateabsolute' => $duedate1,
        ]);
        $programgenerator->allocate_user_to_program($program1->get('id'), (int) $USER->id);

        // Generate Course1 and allocate user.
        $category1 = self::getDataGenerator()->create_category(['name' => 'Cat 1']);
        $course1 = self::getDataGenerator()->create_course((object)['category' => $category1->id,
            'fullname' => 'My course 1', 'enddate' => $duedate1]);
        $this->getDataGenerator()->enrol_user((int) $USER->id, $course1->id, 'student');

        set_config('showcataloguecoursecategory', '0', 'tool_catalogue');
        set_config('coursedisplayduelimit', '0', 'tool_catalogue');
        set_config('programdisplayduelimit', '0', 'tool_catalogue');

        // Order results by name.
        $related = [
            'filter' => '',
            'sort' => 'name',
            'search' => '',
        ];
        // Call the 'my_courses' exporter with the related data.
        $exporter = new catalogue_exporter(null, $related);
        $data = $exporter->export($PAGE->get_renderer('tool_catalogue'));

        // First item in the list should be Course1.
        $this->assertEquals('', $data->listitems[0]->duedatebadgestr);
        $this->assertEquals('', $data->listitems[0]->categoryname);

        // Second item in the list should be Program1.
        $this->assertEquals('', $data->listitems[1]->duedatebadgestr);

        set_config('showcataloguecoursecategory', '1', 'tool_catalogue');
        set_config('coursedisplayduelimit', '20', 'tool_catalogue');
        set_config('programdisplayduelimit', '20', 'tool_catalogue');

        // Call the 'my_courses' exporter with the related data.
        $exporter = new catalogue_exporter(null, $related);
        $data = $exporter->export($PAGE->get_renderer('core'));

        // First item in the list should be Course1.
        $this->assertEquals('7 days left', $data->listitems[0]->duedatebadgestr);
        $this->assertEquals('Cat 1', $data->listitems[0]->categoryname);

        // Second item in the list should be Program1.
        $this->assertEquals('Due in 7 days', $data->listitems[1]->duedatebadgestr);
    }
}
