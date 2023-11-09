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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

declare(strict_types=1);

namespace tool_catalogue\external;

use advanced_testcase;
use core_course\customfield\course_handler;
use tool_catalogue\configuration;
use tool_catalogue\local\helpers\filters;

/**
 * Tests for catalogue_search_exporter class.
 *
 * @package    tool_catalogue
 * @covers     \tool_catalogue\external\catalogue_search_exporter
 * @category   test
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 David Carrillo <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class catalogue_search_exporter_test extends advanced_testcase {

    /**
     * This method is called after the last test of this test class is run.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void {
        course_handler::create()->delete_all();
    }

    /**
     * Test catalogue export data
     */
    public function test_export(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var \core_customfield_generator $cfgenerator */
        $cfgenerator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        // Define course customfields.
        $categoryid = $cfgenerator->create_category(['component' => 'core_course', 'area' => 'course'])->get('id');
        $cf1 = $cfgenerator->create_field(['categoryid' => $categoryid, 'name' => 'Nickname',
            'type' => 'text', 'shortname' => 'fld1', 'configdata' => [], ]);
        $cf2 = $cfgenerator->create_field(['categoryid' => $categoryid, 'name' => 'Favourite color',
            'type' => 'text', 'shortname' => 'fld2', 'configdata' => [], ]);

        // Change fields order: Tags, summary, contacts, fld2, category, fld1.
        $fields = [
            configuration::FIELD_TAGS,
            configuration::FIELD_SUMMARY,
            configuration::FIELD_CONTACTS,
            configuration::FIELD_PREFIX_CUSTOM_FIELDS . 'fld2',
            configuration::FIELD_CATEGORY,
            configuration::FIELD_PREFIX_CUSTOM_FIELDS . 'fld1',
        ];
        set_config(configuration::SETTING_DISPLAYFIELDS_LIST, join(',', $fields), 'tool_catalogue');

        // Categories.
        $category1 = $this->getDataGenerator()->create_category(['name' => 'Category 1']);
        $category1course = $this->getDataGenerator()->create_course(['category' => $category1->id, 'shortname' => 'tc1',
            'fullname' => 'test course 1', 'customfield_fld1' => 'Han Solo', 'customfield_fld2' => 'Blue',
            'tags' => ['dog'], ]);
        $subcategory1 = $this->getDataGenerator()->create_category(['parent' => $category1->id, 'name' => 'SubCategory 1A']);
        $subcategory1course = $this->getDataGenerator()->create_course(['category' => $subcategory1->id, 'shortname' => 'tc1a',
            'fullname' => 'test course 1A', 'customfield_fld1' => 'C3PO', 'customfield_fld2' => 'Yellow', ]);
        $category2 = $this->getDataGenerator()->create_category(['name' => 'Category 2']);
        $category2course = $this->getDataGenerator()->create_course(['category' => $category2->id, 'shortname' => 'tc2',
            'fullname' => 'test course 2', 'customfield_fld1' => 'Mandalorian', 'customfield_fld2' => 'Green', ]);

        $teacher1 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher1->id, $category1course->id, 'editingteacher');
        $teacher2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher2->id, $category2course->id, 'editingteacher');

        $filters = filters::create_from_json(json_encode(['categoryid' => (int)$category1->id]));
        $relateddata = [
            'filters' => $filters,
            'limit' => 5,
        ];
        $exporter = new catalogue_search_exporter(null, $relateddata);
        $data = $exporter->export($PAGE->get_renderer('tool_catalogue'));

        $this->assertCount(2, $data->catalogueitems);

        // Check the first course.
        $course1data = array_filter($data->catalogueitems, static function(array $course) use ($category1course): bool {
            return $course['id'] === (int) $category1course->id;
        });
        $course1data = reset($course1data);
        $this->assertEquals($category1course->fullname, $course1data['title']);
        $output = $PAGE->get_renderer('core');
        $courseimage = $output->get_generated_image_for_id($category1course->id);
        $this->assertEquals($courseimage, $course1data['image']);
        $this->assertEquals((new \moodle_url('/course/view.php', ['id' => $category1course->id]))->out(false), $course1data['url']);

        $this->assertCount(6, $course1data['fields']);

        $course1data['fields'][0]['content'] = trim(strip_tags($course1data['fields'][0]['content']));
        $this->assertEquals([
            'fieldname' => configuration::FIELD_TAGS,
            'content' => 'dog',
            'label' => get_string('tags'),
            'iscustomfield' => 0,
            'isempty' => 0,
            'showlabel' => 0,
            'iscoursesummary' => 0,
            'istags' => 1,
            'iscategory' => 0,
            'iscoursecontacts' => 0,
        ], $course1data['fields'][0]);

        \context_helper::preload_from_record($category1course);
        $context = \context_course::instance($category1course->id);
        $summary = file_rewrite_pluginfile_urls($category1course->summary, 'pluginfile.php', $context->id,
            'course', 'summary', null);
        $summary = strip_tags(format_text($summary, $category1course->summaryformat, ['context' => $context]));
        $truncate = configuration::get_truncate_summary();
        if ($truncate > 0) {
            $summary = shorten_text($summary, $truncate);
        }
        $this->assertEquals([
            'fieldname' => configuration::FIELD_SUMMARY,
            'content' => ($summary),
            'label' => get_string('coursesummary', 'moodle'),
            'iscustomfield' => 0,
            'isempty' => 0,
            'showlabel' => 0,
            'iscoursesummary' => 1,
            'istags' => 0,
            'iscategory' => 0,
            'iscoursecontacts' => 0,
        ], $course1data['fields'][1]);

        $course1data['fields'][2]['content'] = trim(strip_tags($course1data['fields'][2]['content']));
        $this->assertEquals([
            'fieldname' => configuration::FIELD_CONTACTS,
            'content' => fullname($teacher1),
            'label' => 'Teacher',
            'iscustomfield' => 0,
            'isempty' => 0,
            'showlabel' => 1,
            'iscoursesummary' => 0,
            'istags' => 0,
            'iscategory' => 0,
            'iscoursecontacts' => 1,
        ], $course1data['fields'][2]);

        $this->assertEquals([
            'fieldname' => configuration::FIELD_PREFIX_CUSTOM_FIELDS . $cf2->get('shortname'),
            'content' => 'Blue',
            'label' => $cf2->get_formatted_name(),
            'iscustomfield' => 1,
            'isempty' => 0,
            'showlabel' => 1,
            'iscoursesummary' => 0,
            'istags' => 0,
            'iscategory' => 0,
            'iscoursecontacts' => 0,
            'customfieldtype' => \customfield_text\field_controller::TYPE,
        ], $course1data['fields'][3]);

        $this->assertEquals([
            'fieldname' => configuration::FIELD_CATEGORY,
            'content' => 'Category 1',
            'label' => get_string('coursecategory', 'moodle'),
            'iscustomfield' => 0,
            'isempty' => 0,
            'showlabel' => 1,
            'iscoursesummary' => 0,
            'istags' => 0,
            'iscategory' => 1,
            'iscoursecontacts' => 0,
        ], $course1data['fields'][4]);

        $this->assertEquals([
            'fieldname' => configuration::FIELD_PREFIX_CUSTOM_FIELDS . $cf1->get('shortname'),
            'content' => 'Han Solo',
            'label' => $cf1->get_formatted_name(),
            'iscustomfield' => 1,
            'isempty' => 0,
            'showlabel' => 1,
            'iscoursesummary' => 0,
            'istags' => 0,
            'iscategory' => 0,
            'iscoursecontacts' => 0,
            'customfieldtype' => \customfield_text\field_controller::TYPE,
        ], $course1data['fields'][5]);

        // Check the subcategory course.
        $subcat1coursedata = array_filter($data->catalogueitems, static function(array $course) use ($subcategory1course): bool {
            return $course['id'] === (int) $subcategory1course->id;
        });
        $subcat1coursedata = reset($subcat1coursedata);
        $this->assertEquals($subcategory1course->fullname, $subcat1coursedata['title']);
        $output = $PAGE->get_renderer('core');
        $courseimage = $output->get_generated_image_for_id($subcategory1course->id);
        $this->assertEquals($courseimage, $subcat1coursedata['image']);
        $this->assertEquals((new \moodle_url('/course/view.php', ['id' => $subcategory1course->id]))->out(false),
            $subcat1coursedata['url']);

        $this->assertCount(6, $subcat1coursedata['fields']);
    }

    /**
     * Test catalogue export data with field settings disabled
     */
    public function test_export_fields_disabled(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var \core_customfield_generator $cfgenerator */
        $cfgenerator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        // Define course customfields.
        $categoryid = $cfgenerator->create_category(['component' => 'core_course', 'area' => 'course'])->get('id');
        $cfgenerator->create_field(['categoryid' => $categoryid, 'name' => 'Nickname',
            'type' => 'text', 'shortname' => 'fld1', ]);

        // Disable all fields.
        set_config(configuration::SETTING_DISPLAYFIELDS_LIST, '', 'tool_catalogue');

        // Categories.
        $category1 = $this->getDataGenerator()->create_category(['name' => 'Category 1']);
        $category1course = $this->getDataGenerator()->create_course(['category' => $category1->id, 'shortname' => 'tc1',
            'fullname' => 'test course 1', 'customfield_fld1' => 'Han Solo', 'customfield_fld2' => 'Blue',
            'tags' => ['dog'], ]);

        $teacher1 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher1->id, $category1course->id, 'editingteacher');

        $filters = filters::create_from_json(json_encode(['categoryid' => (int)$category1->id]));
        $relateddata = [
            'filters' => $filters,
            'limit' => 5,
        ];
        $exporter = new catalogue_search_exporter(null, $relateddata);
        $data = $exporter->export($PAGE->get_renderer('tool_catalogue'));

        $this->assertCount(1, $data->catalogueitems);

        // Check the first course.
        $course1data = array_filter($data->catalogueitems, static function(array $course) use ($category1course): bool {
            return $course['id'] === (int) $category1course->id;
        });
        $course1data = reset($course1data);
        $this->assertEquals($category1course->fullname, $course1data['title']);
        $output = $PAGE->get_renderer('core');
        $courseimage = $output->get_generated_image_for_id($category1course->id);
        $this->assertEquals($courseimage, $course1data['image']);
        $this->assertEquals((new \moodle_url('/course/view.php', ['id' => $category1course->id]))->out(false), $course1data['url']);

        $this->assertCount(0, $course1data['fields']);
    }

    /**
     * Test catalogue export data with pagination
     */
    public function test_export_pagination(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();

        $this->setAdminUser();

        // Categories.
        $category1 = $this->getDataGenerator()->create_category(['name' => 'Category 1']);
        $category1course = $this->getDataGenerator()->create_course(['category' => $category1->id, 'shortname' => 'tc1',
            'fullname' => 'Mathematics', ]);
        $subcategory1 = $this->getDataGenerator()->create_category(['parent' => $category1->id, 'name' => 'SubCategory 1A']);
        $subcategory1course = $this->getDataGenerator()->create_course(['category' => $subcategory1->id, 'shortname' => 'tc1a',
            'fullname' => 'Astronomy', ]);
        $category2 = $this->getDataGenerator()->create_category(['name' => 'Category 2']);
        $category2course = $this->getDataGenerator()->create_course(['category' => $category2->id, 'shortname' => 'tc2',
            'fullname' => 'Laws of Physics', ]);

        // Set pagination in main page to 2 courses per page.
        $relateddata = [
            'filters' => new filters(),
            'offset' => 2,
            'limit' => 2,
        ];
        $exporter = new catalogue_search_exporter(null, $relateddata);
        $data = $exporter->export($PAGE->get_renderer('tool_catalogue'));

        // We set to show 2 courses per page. After requesting the second page we should be getting only $category2course.
        $this->assertCount(1, $data->catalogueitems);
        $coursedata = reset($data->catalogueitems);
        $this->assertEquals($category2course->id, $coursedata['id']);

        // Set pagination in main page to 1 course per page.
        $relateddata = [
            'filters' => new filters(),
            'offset' => 1,
            'limit' => 1,
        ];
        $exporter = new catalogue_search_exporter(null, $relateddata);
        $data = $exporter->export($PAGE->get_renderer('tool_catalogue'));

        // We set to show 1 course per page. After requesting the second page we should be getting only $subcategory1course.
        $this->assertCount(1, $data->catalogueitems);
        $coursedata = reset($data->catalogueitems);
        $this->assertEquals($subcategory1course->id, $coursedata['id']);

        // Set pagination on search page to 5 courses per page.
        $filters = filters::create_from_json(json_encode(['searchstring' => 'Astronomy']));
        $relateddata = [
            'filters' => $filters,
            'limit' => 5,
        ];
        $exporter = new catalogue_search_exporter(null, $relateddata);
        $data = $exporter->export($PAGE->get_renderer('tool_catalogue'));

        // We set to show 3 course per page. After searching for 'Astronomy' we should be getting only $subcategory1course.
        $this->assertCount(1, $data->catalogueitems);
        $coursedata = reset($data->catalogueitems);
        $this->assertEquals($subcategory1course->id, $coursedata['id']);
    }

    /**
     * Test catalogue export data with invalid parameters
     */
    public function test_export_invalid_parameters(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Categories.
        $category1 = $this->getDataGenerator()->create_category(['name' => 'Category 1']);
        $category1course = $this->getDataGenerator()->create_course(['category' => $category1->id, 'shortname' => 'tc1',
            'fullname' => 'Mathematics', ]);
        $subcategory1 = $this->getDataGenerator()->create_category(['parent' => $category1->id, 'name' => 'SubCategory 1A']);
        $subcategory1course = $this->getDataGenerator()->create_course(['category' => $subcategory1->id, 'shortname' => 'tc1a',
            'fullname' => 'Astronomy', ]);
        $category2 = $this->getDataGenerator()->create_category(['name' => 'Category 2']);
        $category2course = $this->getDataGenerator()->create_course(['category' => $category2->id, 'shortname' => 'tc2',
            'fullname' => 'Laws of Physics', ]);

        // Wrong categoryid and searchstrings combination.
        $filters = filters::create_from_json(json_encode(['categoryid' => (int)$category1->id, 'searchstring' => 'Astronomy']));
        $relateddata = [
            'filters' => $filters,
            'limit' => 5,
        ];
        $exporter = new catalogue_search_exporter(null, $relateddata);

        try {
            $data = $exporter->export($PAGE->get_renderer('tool_catalogue'));
            $this->fail('Expected exception not thrown');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('Invalid parameters', $e->getMessage());
        }
    }
}
