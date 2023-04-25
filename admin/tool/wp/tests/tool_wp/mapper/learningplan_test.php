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

namespace tool_wp\tool_wp\mapper;

use stdClass;
use tool_wp\local\exportimport\helper;

/**
 * Learning plan mapper test class
 *
 * @package     tool_wp
 * @group       tool_wp
 * @category    test
 * @covers      \tool_wp\tool_wp\mapper\learningplan
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class learningplan_test extends \advanced_testcase {

    /**
     * Test mapper returns mapping data correctly for given learning plan template
     */
    public function test_get_mapping_data_for_workplace_export(): void {
        $this->resetAfterTest();

        $lp = $this->create_lp('My learning plan');

        $mapper = helper::find_mapper_for_entity('learningplan', helper::get_all_mappers());
        $this->assertInstanceOf(learningplan::class, $mapper);

        $data = $mapper->get_mapping_data_for_workplace_export($lp->id);
        $this->assertEquals([
            'id' => $lp->id,
            'shortname' => $lp->shortname,
        ], $data);
    }

    /**
     * Data provider for testing matching learning plan templates
     *
     * @see test_locate_mapping_success
     *
     * @return array
     */
    public function locate_mapping_success_provider(): array {
        return [
            ['My lp', ['shortname' => 'My lp']],
            ['My lp1', ['shortname' => 'My lp1']],
        ];
    }

    /**
     * Test the mapper class successfully locates existing learning plan templates
     *
     * @param string $shortname
     * @param array $identifier
     *
     * @dataProvider locate_mapping_success_provider
     */
    public function test_locate_mapping_success(string $shortname, array $identifier): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $lp = $this->create_lp($shortname);

        $mapping = $this->get_plugin_generator()->locate_mapping('learningplan', $identifier);
        $this->assertEquals([$lp->id, [], [], true], $mapping);
    }

    /**
     * Data provider for testing non-matching learning plan templates
     *
     * @see test_locate_mapping_error
     *
     * @return array
     */
    public function locate_mapping_error_provider(): array {
        return [
            ['My lp', ['shortname' => 'My lp1']],
            ['My lp1', ['shortname' => 'My lp'], false],
        ];
    }

    /**
     * Test mapper returns errors for non-matching learning plan templates
     *
     * @param string $name
     * @param array $identifier
     * @param bool $adminuser
     *
     * @dataProvider locate_mapping_error_provider
     */
    public function test_locate_mapping_error_lp(string $name, array $identifier, bool $adminuser = true): void {
        $this->resetAfterTest();
        if ($adminuser) {
            $this->setAdminUser();
        }

        $this->create_lp($name);

        [$lpid, $notices, $errors, $validated] = $this->get_plugin_generator()->locate_mapping('learningplan', $identifier);

        $this->assertNull($lpid);
        $this->assertEmpty($notices);
        $this->assertCount(1, $errors);

        $this->assertEquals("Learning plan template '{$identifier['shortname']}' was not found", reset($errors));
        $this->assertFalse($validated);
    }

    /**
     * Helper method to create a learning plan instance
     *
     * @param string $shortname
     * @return stdClass
     */
    protected function create_lp(string $shortname): stdClass {
        return $this->get_competency_generator()->create_template([
            'shortname' => $shortname,
            'contextid' => \context_system::instance()->id
        ])->to_record();
    }

    /**
     * Returns the plugin generator
     *
     * @return \tool_wp_generator
     */
    protected function get_plugin_generator(): \tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }

    /**
     * Returns the competency generator
     *
     * @return \core_competency_generator
     */
    protected function get_competency_generator(): \core_competency_generator {
        return $this->getDataGenerator()->get_plugin_generator('core_competency');
    }
}
