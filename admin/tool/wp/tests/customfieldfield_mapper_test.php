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
 * File containing tests for export/import customfield mapper class
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\mapper;

defined('MOODLE_INTERNAL') || die;

use tool_wp\local\exportimport\helper;

/**
 * Test class
 *
 * @package     tool_wp
 * @group       tool_wp
 * @category    test
 * @covers      \tool_wp\tool_wp\mapper\customfield_field
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class customfieldfield_mapper_testcase extends \advanced_testcase {

    /** @var \core_customfield_generator */
    protected $customfieldgenerator;
    /** @var \tool_wp_generator */
    protected $wpgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->customfieldgenerator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $this->wpgenerator = self::getDataGenerator()->get_plugin_generator('tool_wp');
        $this->resetAfterTest();
    }

    /**
     * Test mapper returns mapping data correctly for given customfield
     */
    public function test_get_mapping_data_for_workplace_export() {
        $this->resetAfterTest();

        // Define one customfield.
        $params = [
            'component' => 'tool_program',
            'area' => 'program',
            'itemid' => 0,
            'contextid' => \context_system::instance()->id
        ];
        $category = $this->customfieldgenerator->create_category($params);
        $field = $this->customfieldgenerator->create_field(['categoryid' => $category->get('id'),
            'type' => 'text', 'shortname' => 'fld1']);

        $mapper = helper::find_mapper_for_entity('customfield_field', helper::get_all_mappers());
        $this->assertInstanceOf(customfield_field::class, $mapper);

        $data = $mapper->get_mapping_data_for_workplace_export($field->get('id'));
        $this->assertEquals([
            'id' => $field->get('id'),
            'shortname' => $field->get('shortname'),
            'type' => 'text',
            'component' => 'tool_program',
            'area' => 'program',
            'itemid' => 0,
        ], $data);
    }

    /**
     * Test the mapper class successfully locates existing customfield
     */
    public function test_locate_mapping_default(): void {
        $this->resetAfterTest();
        self::setAdminUser();

        $mapping = $this->wpgenerator->locate_mapping('customfield_field', [
            'shortname' => 'nonexistingshortname',
            'type' => 'text',
            'component' => 'tool_program',
            'area' => 'program',
        ]);
        $this->assertEquals([null, [], ["Custom field 'nonexistingshortname' not found"], false], $mapping);

        // Create custom fields for tool_program.
        $params = [
            'component' => 'tool_program',
            'area' => 'program',
            'itemid' => 0,
            'contextid' => \context_system::instance()->id
        ];
        $category = $this->customfieldgenerator->create_category($params);
        $field = $this->customfieldgenerator->create_field(['categoryid' => $category->get('id'),
            'type' => 'text', 'shortname' => 'fld1']);

        // Create custom fields for tool_certification with same shortname.
        $params = [
            'component' => 'tool_certification',
            'area' => 'certification',
            'itemid' => 0,
            'contextid' => \context_system::instance()->id
        ];
        $category2 = $this->customfieldgenerator->create_category($params);
        $field2 = $this->customfieldgenerator->create_field(['categoryid' => $category2->get('id'),
            'type' => 'text', 'shortname' => 'fld1']);

        $mapping = $this->wpgenerator->locate_mapping('customfield_field', [
            'shortname' => $field->get('shortname'),
            'type' => $field->get('type'),
            'component' => 'tool_program',
            'area' => 'program',
        ]);
        $this->assertEquals([$field->get('id'), [], [], true], $mapping);
    }
}
