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

namespace tool_wp\reportbuilder\local\filters;

use advanced_testcase;
use core_reportbuilder\local\report\filter;
use lang_string;

/**
 * Unit tests for tags report filter
 *
 * @package     tool_wp
 * @covers      \tool_wp\reportbuilder\local\filters\tags
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tags_test extends advanced_testcase {

    /**
     * Test getting filter SQL
     */
    public function test_get_sql_filter(): void {
        global $DB;

        $this->resetAfterTest();

        $courseone = $this->getDataGenerator()->create_course(['tags' => ['cat', 'dog']]);

        $filter = new filter(
            tags::class,
            'tags',
            new lang_string('tags'),
            'testentity',
            'c.id'
        );

        // Create instance of our filter, passing ID of the 'cat' tag.
        $tagid = $DB->get_field('tag', 'id', ['name' => 'cat']);
        [$select, $params] = tags::create($filter)->get_sql_filter([
            $filter->get_unique_identifier() => [$tagid],
        ]);

        $courses = $DB->get_fieldset_sql("SELECT c.id FROM {course} c WHERE {$select}", $params);
        $this->assertEquals([$courseone->id], $courses);
    }
}
