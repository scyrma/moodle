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

/**
 * File containing tests for export/import user profile fields mapper class
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\mapper;

use stdClass;
use tool_wp\local\exportimport\helper;

/**
 * Test class
 *
 * @package     tool_wp
 * @group       tool_wp
 * @category    test
 * @covers      \tool_wp\tool_wp\mapper\userfield
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class userfield_mapper_testcase extends \advanced_testcase {

    /**
     * Test mapper returns mapping data correctly for given entity
     */
    public function test_get_mapping_data_for_workplace_export(): void {
        $this->resetAfterTest();

        $userfield = $this->create_user_profile_field('myfield', 'My field');

        $mapper = helper::find_mapper_for_entity('userfield', helper::get_all_mappers());
        $this->assertInstanceOf(userfield::class, $mapper);

        $data = $mapper->get_mapping_data_for_workplace_export($userfield->id);
        $this->assertEquals([
            'id' => $userfield->id,
            'shortname' => $userfield->shortname,
            'name' => $userfield->name,
        ], $data);
    }

    /**
     * Test the mapper class successfully locates existing entity
     */
    public function test_locate_mapping_success(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $userfield = $this->create_user_profile_field('myfield', 'My field');

        $mapping = $this->get_plugin_generator()->locate_mapping('userfield', ['shortname' => $userfield->shortname]);
        $this->assertEquals([$userfield->id, [], [], true], $mapping);
    }

    /**
     * Test mapper returns errors for non-matching entities
     */
    public function test_locate_mapping_error(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->create_user_profile_field('myfield', 'My field');

        $mapping = $this->get_plugin_generator()->locate_mapping('userfield', ['shortname' => 'myotherfield']);
        $this->assertEquals([
            null,
            [],
            ['User profile field \'myotherfield\' was not found'],
            false,
        ], $mapping);
    }

    /**
     * Helper method to create a new user profile field
     *
     * @param string $shortname
     * @param string $name
     * @return stdClass
     */
    private function create_user_profile_field(string $shortname, string $name): stdClass {
        global $DB;

        // Create a category for our field.
        $categoryid = $DB->insert_record('user_info_category', (object) [
            'name' => 'My category',
            'sortorder' => 1,
        ]);

        $field = (object) [
            'categoryid' => $categoryid,
            'shortname' => $shortname,
            'name' => $name,
            'datatype' => 'text',
        ];

        $field->id = $DB->insert_record('user_info_field', $field);

        return $field;
    }

    /**
     * Returns the plugin generator
     *
     * @return \tool_wp_generator
     */
    protected function get_plugin_generator(): \tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }
}
