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

/**
 * Unit tests for category selector exporter
 *
 * @package     tool_catalogue
 * @covers      \tool_catalogue\external\category_selector_exporter
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Mohamed A. Shehata <mohamed.shehata@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class category_selector_exporter_test extends advanced_testcase {
    /**
     * @var \core_course_category[] $categories
     */
    private array $categories;
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', '1', 'tool_catalogue');

        /*
         * The following category structure.
         * - Category1
         * -- Category1-1
         * --- Category1-1-1
         * -- Category1-2
         * - Category2
         * -- Category2-1
         * --- Category2-1-1
         * --- Category2-1-2
         * -- Category2-2
         */

        // Create 2 categories.
        $this->categories['category1'] = self::getDataGenerator()->create_category(["name" => "Cat 1"]);
        $this->categories['category2'] = self::getDataGenerator()->create_category(["name" => "Cat 2"]);

        // Create 2 sub-categories for category 1.
        $this->categories['subcategory1-1'] = self::getDataGenerator()->create_category([
            "name" => "Cat 1-1",
            "parent" => $this->categories['category1']->id,
        ]);
        $this->categories['subcategory1-2'] = self::getDataGenerator()->create_category([
            "name" => "Cat 1-2",
            "parent" => $this->categories['category1']->id,
        ]);

        // Create 1 sub-sub-category for sub-category 1-1.
        $this->categories['subsubcategory1-1-1'] = self::getDataGenerator()->create_category([
            "name" => "Cat 1-1-1",
            "parent" => $this->categories['subcategory1-1']->id,
        ]);

        // Create 2 sub-categories for category 2.
        $this->categories['subcategory2-1'] = self::getDataGenerator()->create_category([
            "name" => "Cat 2-1",
            "parent" => $this->categories['category2']->id,
        ]);
        $this->categories['subcategory2-2'] = self::getDataGenerator()->create_category([
            "name" => "Cat 2-2",
            "parent" => $this->categories['category2']->id,
        ]);

        // Create 2 sub-sub-category for sub-category 2-1.
        $this->categories['subsubcategory2-1-1'] = self::getDataGenerator()->create_category([
            "name" => "Cat 2-1-1",
            "parent" => $this->categories['subcategory2-1']->id,
        ]);
        $this->categories['subsubcategory2-1-2'] = self::getDataGenerator()->create_category([
            "name" => "Cat 2-1-2",
            "parent" => $this->categories['subcategory2-1']->id,
        ]);
    }
    /**
     * Test catalogue export data
     */
    public function test_categories_structure(): void {
        global $PAGE;

        // Call the 'category_selector_exporter' exporter.
        $exporter = new category_selector_exporter([]);
        $data = $exporter->export($PAGE->get_renderer('tool_catalogue'));
        $this->assertCount(3, $data->categories);

        // First item in the list should be Cat 1.
        $exportedcategory1 = $data->categories[1];
        $this->assertEquals($this->categories['category1']->id, $exportedcategory1['id']);
        $this->assertEquals($this->categories['category1']->name, $exportedcategory1['name']);
        $this->assertEquals(
            new \moodle_url('/course/index.php', ['categoryid' => $this->categories['category1']->id]),
            $exportedcategory1['url']
        );
        $this->assertTrue($exportedcategory1['haschildren']);
        $this->assertCount(2, $exportedcategory1['subcategories']);

        // Second level assertions Cat 1-1.
        $exportedsubcategory1 = $exportedcategory1['subcategories'][0];
        $this->assertEquals($this->categories['subcategory1-1']->id, $exportedsubcategory1['id']);
        $this->assertEquals($this->categories['subcategory1-1']->name, $exportedsubcategory1['name']);
        $this->assertEquals(
            new \moodle_url('/course/index.php', ['categoryid' => $this->categories['subcategory1-1']->id]),
            $exportedsubcategory1['url']
        );
        $this->assertTrue($exportedsubcategory1['haschildren']);
        $this->assertCount(1, $exportedsubcategory1['subcategories']);

        // Second level assertions Cat 1-2.
        $exportedsubcategory2 = $exportedcategory1['subcategories'][1];
        $this->assertEquals($this->categories['subcategory1-2']->id, $exportedsubcategory2['id']);
        $this->assertEquals($this->categories['subcategory1-2']->name, $exportedsubcategory2['name']);
        $this->assertEquals(
            new \moodle_url('/course/index.php', ['categoryid' => $this->categories['subcategory1-2']->id]),
            $exportedsubcategory2['url']
        );
        $this->assertFalse($exportedsubcategory2['haschildren']);
        $this->assertEmpty($exportedsubcategory2['subcategories']);

        // Third level assertions Cat 1-1-1.
        $exportedsubsubcategory1 = $exportedsubcategory1['subcategories'][0];
        $this->assertEquals($this->categories['subsubcategory1-1-1']->id, $exportedsubsubcategory1['id']);
        $this->assertEquals($this->categories['subsubcategory1-1-1']->name, $exportedsubsubcategory1['name']);
        $this->assertEquals(
            new \moodle_url('/course/index.php', ['categoryid' => $this->categories['subsubcategory1-1-1']->id]),
            $exportedsubsubcategory1['url']
        );
        $this->assertFalse($exportedsubsubcategory1['haschildren']);
        $this->assertEmpty($exportedsubsubcategory1['subcategories']);
    }

    /**
     * Test catalogue export not permitted categories structure.
     */
    public function test_not_permitted_categories_structure(): void {
        global $DB, $PAGE;
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);

        // Capability not allowed on system context.
        $context = \context_system::instance();
        unassign_capability('moodle/category:viewcourselist', $userrole, $context->id);

        // Allow users viewing categories (1, 2-1).
        $cat1context = \context_coursecat::instance($this->categories['category1']->id);
        assign_capability('moodle/category:viewcourselist', CAP_ALLOW, $userrole, $cat1context->id, true);

        $cat21context = \context_coursecat::instance($this->categories['subcategory2-1']->id);
        assign_capability('moodle/category:viewcourselist', CAP_ALLOW, $userrole, $cat21context->id, true);

        // Call the 'category_selector_exporter' exporter.
        $exporter = new category_selector_exporter([]);
        $data = $exporter->export($PAGE->get_renderer('tool_catalogue'));

        /*
         * Assert the following category structure.
         * - Category1
         * -- Category1-1
         * --- Category1-1-1
         * -- Category1-2
         * - Category2-1
         * -- Category2-1-1
         * -- Category2-1-2
         */

        // First item in the list should be Cat 1.
        $exportedcategory1 = $data->categories[0];
        $this->assertEquals($this->categories['category1']->id, $exportedcategory1['id']);
        $this->assertEquals($this->categories['category1']->name, $exportedcategory1['name']);
        $this->assertEquals(
            new \moodle_url('/course/index.php', ['categoryid' => $this->categories['category1']->id]),
            $exportedcategory1['url']
        );
        $this->assertTrue($exportedcategory1['haschildren']);
        $this->assertCount(2, $exportedcategory1['subcategories']);

        // Second level assertions Cat 1-1.
        $exportedsubcategory1 = $exportedcategory1['subcategories'][0];
        $this->assertEquals($this->categories['subcategory1-1']->id, $exportedsubcategory1['id']);
        $this->assertEquals($this->categories['subcategory1-1']->name, $exportedsubcategory1['name']);
        $this->assertEquals(
            new \moodle_url('/course/index.php', ['categoryid' => $this->categories['subcategory1-1']->id]),
            $exportedsubcategory1['url']
        );
        $this->assertTrue($exportedsubcategory1['haschildren']);
        $this->assertCount(1, $exportedsubcategory1['subcategories']);

        // Second level assertions Cat 1-2.
        $exportedsubcategory2 = $exportedcategory1['subcategories'][1];
        $this->assertEquals($this->categories['subcategory1-2']->id, $exportedsubcategory2['id']);
        $this->assertEquals($this->categories['subcategory1-2']->name, $exportedsubcategory2['name']);
        $this->assertEquals(
            new \moodle_url('/course/index.php', ['categoryid' => $this->categories['subcategory1-2']->id]),
            $exportedsubcategory2['url']
        );
        $this->assertFalse($exportedsubcategory2['haschildren']);
        $this->assertEmpty($exportedsubcategory2['subcategories']);

        // Third level assertions Cat 1-1-1.
        $exportedsubsubcategory1 = $exportedsubcategory1['subcategories'][0];
        $this->assertEquals($this->categories['subsubcategory1-1-1']->id, $exportedsubsubcategory1['id']);
        $this->assertEquals($this->categories['subsubcategory1-1-1']->name, $exportedsubsubcategory1['name']);
        $this->assertEquals(
            new \moodle_url('/course/index.php', ['categoryid' => $this->categories['subsubcategory1-1-1']->id]),
            $exportedsubsubcategory1['url']
        );
        $this->assertFalse($exportedsubsubcategory1['haschildren']);
        $this->assertEmpty($exportedsubsubcategory1['subcategories']);

        // Second item in the list should be Cat 2-1.
        $exportedsubcategory21 = $data->categories[1];
        $this->assertEquals($this->categories['subcategory2-1']->id, $exportedsubcategory21['id']);
        $this->assertEquals($this->categories['subcategory2-1']->name, $exportedsubcategory21['name']);
        $this->assertEquals(
            new \moodle_url('/course/index.php', ['categoryid' => $this->categories['subcategory2-1']->id]),
            $exportedsubcategory21['url']
        );
        $this->assertTrue($exportedsubcategory21['haschildren']);
        $this->assertCount(2, $exportedsubcategory21['subcategories']);

        // Second level assertions Cat 2-1-1.
        $exportedsubsubcategory211 = $exportedsubcategory21['subcategories'][0];
        $this->assertEquals($this->categories['subsubcategory2-1-1']->id, $exportedsubsubcategory211['id']);
        $this->assertEquals($this->categories['subsubcategory2-1-1']->name, $exportedsubsubcategory211['name']);
        $this->assertEquals(
            new \moodle_url('/course/index.php', ['categoryid' => $this->categories['subsubcategory2-1-1']->id]),
            $exportedsubsubcategory211['url']
        );
        $this->assertFalse($exportedsubsubcategory211['haschildren']);
        $this->assertEmpty($exportedsubsubcategory1['subcategories']);

        // Second level assertions Cat 2-1-2.
        $exportedsubsubcategory212 = $exportedsubcategory21['subcategories'][1];
        $this->assertEquals($this->categories['subsubcategory2-1-2']->id, $exportedsubsubcategory212['id']);
        $this->assertEquals($this->categories['subsubcategory2-1-2']->name, $exportedsubsubcategory212['name']);
        $this->assertEquals(
            new \moodle_url('/course/index.php', ['categoryid' => $this->categories['subsubcategory2-1-2']->id]),
            $exportedsubsubcategory212['url']
        );
        $this->assertFalse($exportedsubsubcategory212['haschildren']);
        $this->assertEmpty($exportedsubsubcategory212['subcategories']);
    }
}
