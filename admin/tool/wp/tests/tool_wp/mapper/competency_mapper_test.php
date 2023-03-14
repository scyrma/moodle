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
 * File containing tests for export/import competency mapper class
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\mapper;

use context;
use context_coursecat;
use stdClass;
use tool_tenant\manager;
use tool_tenant\tenancy;
use tool_wp\local\exportimport\helper;

/**
 * Test class
 *
 * @package     tool_wp
 * @group       tool_wp
 * @category    test
 * @covers      \tool_wp\tool_wp\mapper\competency
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class competency_mapper_test extends \advanced_testcase {

    /**
     * Test mapper returns mapping data correctly for given competency
     */
    public function test_get_mapping_data_for_workplace_export() {
        $this->resetAfterTest();

        $competency = $this->create_competency('My competency', 'comp101');

        $mapper = helper::find_mapper_for_entity('competency', helper::get_all_mappers());
        $this->assertInstanceOf(competency::class, $mapper);

        $data = $mapper->get_mapping_data_for_workplace_export($competency->id);
        $this->assertEquals([
            'id' => $competency->id,
            'shortname' => $competency->shortname,
            'idnumber' => $competency->idnumber,
        ], $data);
    }


    /**
     * Data provider for testing matching competencies
     *
     * @see test_locate_mapping_success
     *
     * @return array
     */
    public function locate_mapping_success_provider(): array {
        return [
            ['My competency', 'comp101', ['shortname' => 'My competency']],
            ['My competency', 'comp101', ['idnumber' => 'comp101']],
        ];
    }

    /**
     * Test the mapper class successfully locates existing competencies
     *
     * @param string $shortname
     * @param string $idnumber
     * @param array $identifier
     *
     * @dataProvider locate_mapping_success_provider
     */
    public function test_locate_mapping_success(string $shortname, string $idnumber, array $identifier): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $competency = $this->create_competency($shortname, $idnumber);

        $mapping = $this->get_plugin_generator()->locate_mapping('competency', $identifier);
        $this->assertEquals([$competency->id, [], [], true], $mapping);
    }

    /**
     * Test the mapper class successfully locates existing competencies where a competency with the same name also exists in a
     * category that the user cannot access
     */
    public function test_locate_mapping_success_duplicate_name(): void {
        global $DB;

        $this->resetAfterTest();

        // Create a competency in another category.
        $othercategory = $this->getDataGenerator()->create_category();
        $othercontext = context_coursecat::instance($othercategory->id);
        $othercompetency = $this->create_competency('My competency', 'comp101', $othercontext);

        // Create user, set them as manager for the default tenant category.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $category = $this->getDataGenerator()->create_category();
        (new manager())->update_tenant(tenancy::get_tenant_id(), (object) ['categoryid' => $category->id]);

        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        $context = context_coursecat::instance($category->id);
        $this->getDataGenerator()->role_assign($managerrole->id, $user->id, $context);

        // Create a competency in the default tenant category.
        $competency = $this->create_competency($othercompetency->shortname, 'comp202', $context);

        $mapping = $this->get_plugin_generator()->locate_mapping('competency', ['shortname' => $competency->shortname]);
        $this->assertEquals([$competency->id, [], [], true], $mapping);
    }

    /**
     * Data provider for testing non-matching competencies
     *
     * @see test_locate_mapping_error
     *
     * @return array
     */
    public function locate_mapping_error_provider(): array {
        return [
            ['My competency', 'comp101', ['shortname' => 'My other competency']],
            ['My competency', 'comp101', ['shortname' => 'My other competency', 'idnumber' => 'comp202']],
            ['My competency', 'comp101', ['idnumber' => 'comp202']],
            ['My competency', 'comp101', ['shortname' => 'My competency'], false],
        ];
    }

    /**
     * Test mapper returns errors for non-matching competencies
     *
     * @param string $name
     * @param string $idnumber
     * @param array $identifier
     * @param bool $adminuser
     *
     * @dataProvider locate_mapping_error_provider
     */
    public function test_locate_mapping_error(string $name, string $idnumber, array $identifier, bool $adminuser = true): void {
        $this->resetAfterTest();
        if ($adminuser) {
            $this->setAdminUser();
        }

        $this->create_competency($name, $idnumber);

        list($competencyid, $notices, $errors, $validated) =
            $this->get_plugin_generator()->locate_mapping('competency', $identifier);

        $this->assertNull($competencyid);
        $this->assertEmpty($notices);
        $this->assertCount(1, $errors);
        if (!empty($identifier['shortname']) && !empty($identifier['idnumber'])) {
            $name = "'{$identifier['shortname']}' ('{$identifier['idnumber']}')";
        } else {
            $name = !empty($identifier['shortname']) ? "'{$identifier['shortname']}'" : "'{$identifier['idnumber']}'";
        }
        $this->assertEquals('Competency ' . $name . ' was not found', reset($errors));
        $this->assertFalse($validated);
    }

    /**
     * Helper method to create a competency instance
     *
     * @param string $shortname
     * @param string $idnumber
     * @param context|null $context
     * @return stdClass
     */
    protected function create_competency(string $shortname, string $idnumber, ?context $context = null): stdClass {
        $params = $context ? ['contextid' => $context->id] : [];
        $framework = $this->get_competency_generator()->create_framework($params);

        return $this->get_competency_generator()->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => $shortname,
            'idnumber' => $idnumber,
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
