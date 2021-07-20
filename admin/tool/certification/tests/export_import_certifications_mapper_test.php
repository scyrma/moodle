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
 * File containing tests for export/import certification mapper class
 *
 * @package     tool_certification
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\tool_wp\mapper;

defined('MOODLE_INTERNAL') || die;

use tool_wp\local\exportimport\helper;

/**
 * Test class
 *
 * @package     tool_certification
 * @group       tool_certification
 * @category    test
 * @covers      \tool_certification\tool_wp\mapper\tool_certification
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_mapper_testcase extends \advanced_testcase {

    /** @var \tool_wp_generator */
    protected $wpgenerator;
    /** @var \tool_certification_generator */
    protected $generator;
    /** @var \tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->wpgenerator = self::getDataGenerator()->get_plugin_generator('tool_wp');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    /**
     * Test mapper returns mapping data correctly for given certification
     */
    public function test_get_mapping_data_for_workplace_export() {
        $params = [
            'tenantid' => \tool_tenant\tenancy::get_default_tenant_id(),
            'fullname' => 'Certification one',
            'idnumber' => 'C1',
        ];
        $certification = $this->generator->generate_certification($params);

        $mapper = helper::find_mapper_for_entity('tool_certification', helper::get_all_mappers());
        $this->assertInstanceOf(tool_certification::class, $mapper);

        $data = $mapper->get_mapping_data_for_workplace_export($certification->get('id'));
        $this->assertEquals([
            'id' => $certification->get('id'),
            'idnumber' => $certification->get('idnumber'),
            'fullname' => $certification->get('fullname'),
            'tenantid' => $certification->get('tenantid'),
        ], $data);
    }

    /**
     * Test the mapper class successfully locates existing certification by idnumber
     */
    public function test_locate_mapping_by_idnumber(): void {
        [$tenant1, $users1] = $this->tenantgenerator->create_tenant_and_users(1);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(1);

        self::setUser($users1[0]);
        $params = [
            'tenantid' => $tenant1->id,
            'fullname' => 'Certification one',
            'idnumber' => 'IDNUMBER1',
        ];
        $certification = $this->generator->generate_certification($params);

        $mapping = $this->wpgenerator->locate_mapping('tool_certification', [
            'idnumber' => 'nonexistingidnumber',
            'id' => $certification->get('id'),
            'fullname' => $certification->get('fullname'),
            'tenantid' => 0,
        ]);
        $str = "Certification 'Certification one' ('nonexistingidnumber') was not found";
        $this->assertEquals([null, [], [$str], false], $mapping);

        $mapping = $this->wpgenerator->locate_mapping('tool_certification', [
            'idnumber' => $certification->get('idnumber'),
            'id' => 0,
            'fullname' => '',
            'tenantid' => 0,
        ]);
        $this->assertEquals([$certification->get('id'), [], [], true], $mapping);

        // Locating as user in another tenant.
        self::setUser($users2[0]);
        [$certificationid, $notices, $errors, $isvalidated] = $this->wpgenerator->locate_mapping('tool_certification',
            ['idnumber' => 'IDNUMBER1']);
        $this->assertNull($certificationid);
        $this->assertEmpty($notices);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('was not found', $errors[0]);
        $this->assertFalse($isvalidated);
    }

    /**
     * Test the mapper class successfully locates existing certification by name
     */
    public function test_locate_mapping_by_name(): void {
        self::setAdminUser();

        $params = [
            'tenantid' => \tool_tenant\tenancy::get_default_tenant_id(),
            'fullname' => 'Program one',
            'idnumber' => '',
        ];
        $certification = $this->generator->generate_certification($params);

        // Check the mapper class.
        $mapper = helper::find_mapper_for_entity('tool_certification', helper::get_all_mappers());
        $this->assertInstanceOf(\tool_certification\tool_wp\mapper\tool_certification::class, $mapper);

        $mapping = $this->wpgenerator->locate_mapping('tool_certification', ['fullname' => 'nonexistingname']);
        $this->assertEquals([null, [], ["Certification 'nonexistingname' was not found"], false], $mapping);

        $mapping = $this->wpgenerator->locate_mapping('tool_certification', [
            'idnumber' => '',
            'id' => 0,
            'fullname' => $certification->get('fullname'),
            'tenantid' => 0,
        ]);
        $str = get_string('mappingnoticenoidnumber', 'tool_certification');
        $this->assertEquals([$certification->get('id'), [$str], [], true], $mapping);
    }

    /**
     * Test the mapper class successfully locates existing certification by name on correct tenant
     */
    public function test_locate_mapping_certification_in_tenants(): void {
        self::setAdminUser();

        [$tenant1, $users1] = $this->tenantgenerator->create_tenant_and_users(1);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(1);
        self::setUser($users1[0]);

        $params = [
            'tenantid' => $tenant1->id,
            'fullname' => 'Same certification name',
            'idnumber' => 'SAMEIDNUMBER',
        ];
        $certification1 = $this->generator->generate_certification($params);

        $params = [
            'tenantid' => $tenant2->id,
            'fullname' => 'Same certification name',
            'idnumber' => 'SAMEIDNUMBER',
        ];
        $certification2 = $this->generator->generate_certification($params);

        // Test mapping with user in tenant1.
        $mapping = $this->wpgenerator->locate_mapping('tool_certification', ['fullname' => $certification1->get('fullname')]);
        $str = get_string('mappingnoticenoidnumber', 'tool_certification');
        $this->assertEquals([$certification1->get('id'), [$str], [], true], $mapping);

        // Test mapping with user in tenant2.
        self::setUser($users2[0]);
        $mapping = $this->wpgenerator->locate_mapping('tool_certification', ['fullname' => $certification2->get('fullname')]);
        $str = get_string('mappingnoticenoidnumber', 'tool_certification');
        $this->assertEquals([$certification2->get('id'), [$str], [], true], $mapping);
    }
}
