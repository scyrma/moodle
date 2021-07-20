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
 * File containing tests for export/import tenant mapper class
 *
 * @package     tool_tenant
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\tool_wp\mapper;

use tool_wp\local\exportimport\helper;

/**
 * Test class
 *
 * @package     tool_tenant
 * @group       tool_tenant
 * @category    test
 * @covers      \tool_tenant\tool_wp\mapper\tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_import_mapper_testcase extends \advanced_testcase {

    /**
     * Test mapper returns mapping data correctly for given entity
     */
    public function test_get_mapping_data_for_workplace_export(): void {
        $this->resetAfterTest();

        $tenant = $this->get_plugin_generator()->create_tenant([
            'name' => 'My tenant',
            'idnumber' => 'mytenant',
        ]);

        $mapper = helper::find_mapper_for_entity('tool_tenant', helper::get_all_mappers());
        $this->assertInstanceOf(tool_tenant::class, $mapper);

        $data = $mapper->get_mapping_data_for_workplace_export($tenant->id);
        $this->assertEquals([
            'id' => $tenant->id,
            'idnumber' => $tenant->idnumber,
            'name' => $tenant->name,
            'archived' => $tenant->archived,
        ], $data);
    }

    /**
     * Test the mapper class successfully locates existing entity
     */
    public function test_locate_mapping_success(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $tenant = $this->get_plugin_generator()->create_tenant([
            'name' => 'My tenant',
            'idnumber' => 'mytenant',
        ]);

        $mapping = $this->get_workplace_generator()->locate_mapping('tool_tenant', ['idnumber' => $tenant->idnumber]);
        $this->assertEquals([$tenant->id, [], [], true], $mapping);
    }

    /**
     * Test the mapper class returns current tenant for user who can't switch tenants
     */
    public function test_locate_mapping_no_switch_tenant(): void {
        $this->resetAfterTest();

        [$tenant, $users] = $this->get_plugin_generator()->create_tenant_and_users(1, [
            'name' => 'My tenant',
            'idnumber' => 'mytenant',
        ]);
        $this->setUser($users[0]);

        $othertenant = $this->get_plugin_generator()->create_tenant([
            'name' => 'My other tenant',
            'idnumber' => 'myothertenant',
        ]);

        $mapping = $this->get_workplace_generator()->locate_mapping('tool_tenant', ['idnumber' => $othertenant->idnumber]);
        $this->assertEquals([$tenant->id, [], [], true], $mapping);
    }

    /**
     * Test mapper returns errors for non-matching entities
     */
    public function test_locate_mapping_error(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->get_plugin_generator()->create_tenant([
            'name' => 'My tenant',
            'idnumber' => 'mytenant',
        ]);

        $mapping = $this->get_workplace_generator()->locate_mapping('tool_tenant', ['idnumber' => 'myothertenant']);
        $this->assertEquals([
            null,
            [],
            ['Tenant \'myothertenant\' was not found'],
            false,
        ], $mapping);
    }

    /**
     * Returns the plugin generator
     *
     * @return \tool_tenant_generator
     */
    protected function get_plugin_generator(): \tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Returns the Workplace generator
     *
     * @return \tool_wp_generator
     */
    protected function get_workplace_generator(): \tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }
}
