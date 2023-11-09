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

namespace tool_catalogue\local\helpers;

/**
 * Tests for filters class in learning catalogue
 *
 * @covers      \tool_catalogue\local\helpers\filters
 * @package     tool_catalogue
 * @category    test
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class filters_test extends \advanced_testcase {

    /**
     * Reset the $_GET after each test
     */
    public function tearDown(): void {
        $_GET = [];
    }

    /**
     * Test converting empty filters to json and back (frontpage and main catalogue page)
     */
    public function test_to_json_no_filters(): void {
        $filters = new filters();
        $this->assertEquals(null, $filters->get_search_string());
        $this->assertEquals(0, $filters->get_categoryid());

        $json = $filters->export_as_json();
        $this->assertEquals('{"categoryid":0,"searchstring":null}', $json);

        $newfilters = filters::create_from_json($json);
        $this->assertEquals(null, $newfilters->get_search_string());
        $this->assertEquals(0, $newfilters->get_categoryid());

        $newfilters = filters::create_from_json('[]');
        $this->assertEquals(null, $newfilters->get_search_string());
        $this->assertEquals(0, $newfilters->get_categoryid());
    }

    /**
     * Test converting categoryid filter to json and back
     */
    public function test_to_json_course_index_page(): void {
        $_GET['categoryid'] = 123;
        $filters = filters::create_from_page_course_index();
        $this->assertEquals(null, $filters->get_search_string());
        $this->assertEquals(123, $filters->get_categoryid());

        $json = $filters->export_as_json();
        $this->assertEquals('{"categoryid":123,"searchstring":null}', $json);

        $newfilters = filters::create_from_json($json);
        $this->assertEquals(null, $newfilters->get_search_string());
        $this->assertEquals(123, $newfilters->get_categoryid());
    }

    /**
     * Test converting searchstring filter to json and back
     */
    public function test_to_json_course_search_page(): void {
        $_GET['q'] = 'test';
        $filters = filters::create_from_page_course_search();
        $this->assertEquals('test', $filters->get_search_string());
        $this->assertEquals(0, $filters->get_categoryid());

        $json = $filters->export_as_json();
        $this->assertEquals('{"categoryid":0,"searchstring":"test"}', $json);

        $newfilters = filters::create_from_json($json);
        $this->assertEquals('test', $newfilters->get_search_string());
        $this->assertEquals(0, $newfilters->get_categoryid());
    }
}
