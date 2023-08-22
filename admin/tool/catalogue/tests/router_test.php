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

namespace tool_catalogue;

use advanced_testcase;

/**
 * Router class tests.
 *
 * @covers     \tool_catalogue\router
 * @package    tool_catalogue
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class router_test extends advanced_testcase {

    /**
     * Test for build_program_url
     */
    public function test_build_program_url(): void {
        $url = router::build_program_url(123);

        $expected = 'https://www.example.com/moodle/my/courses.php/program/123';
        $this->assertEquals($expected, $url->out());
    }

    /**
     * Test for build_program_set_url
     */
    public function test_build_program_set_url(): void {
        $url = router::build_program_set_url(123, 456);

        $expected = 'https://www.example.com/moodle/my/courses.php/program/123/set/456';
        $this->assertEquals($expected, $url->out());
    }

    /**
     * Test for build_course_url
     */
    public function test_build_course_url(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $url = router::build_course_url((int) $course->id);

        $expected = 'https://www.example.com/moodle/course/view.php?id=' . $course->id;
        $this->assertEquals($expected, $url->out());
    }

    /**
     * Data provider for testing get_mycourses_url_params
     *
     * @return array
     */
    public function data_get_mycourses_url_params(): array {
        return [
            // Test for program url.
            [
                'pathinfo' => '/program/1',
                'expected' => ['program' => 1]
            ],
            // Test for program set url.
            [
                'pathinfo' => '/program/1/set/2',
                'expected' => ['program' => 1, 'set' => 2]
            ],
            // Test for unknown url.
            [
                'pathinfo' => '/unknown/4',
                'expected' => []
            ],
        ];
    }

    /**
     * Test for get_mycourses_url_params
     *
     * @dataProvider data_get_mycourses_url_params
     * @param string $pathinfo
     * @param array $expected
     */
    public function test_get_mycourses_url_params(string $pathinfo, array $expected): void {
        global $_SERVER;

        $_SERVER = [];
        $_SERVER['SERVER_SOFTWARE'] = 'Apache/2.2.22 (Unix)';
        $_SERVER['REQUEST_URI'] = '/my/courses.php';
        $_SERVER['PATH_INFO'] = $pathinfo;
        $this->assertEquals($expected, router::get_mycourses_url_params());
    }
}
