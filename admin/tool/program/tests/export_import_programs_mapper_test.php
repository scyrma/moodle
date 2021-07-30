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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * File containing tests for export/import program mapper class
 *
 * @package     tool_program
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\tool_wp\mapper;

defined('MOODLE_INTERNAL') || die;

use tool_wp\local\exportimport\helper;

/**
 * Test class
 *
 * @package     tool_program
 * @group       tool_program
 * @category    test
 * @covers      \tool_program\tool_wp\mapper\tool_program
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_mapper_testcase extends \advanced_testcase {

    /** @var \tool_wp_generator */
    protected $wpgenerator;
    /** @var \tool_program_generator */
    protected $generator;
    /** @var \tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->wpgenerator = self::getDataGenerator()->get_plugin_generator('tool_wp');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    /**
     * Test mapper returns mapping data correctly for given program
     */
    public function test_get_mapping_data_for_workplace_export() {
        $params = [
            'tenantid' => \tool_tenant\tenancy::get_default_tenant_id(),
            'fullname' => 'Program one',
            'idnumber' => 'P1',
        ];
        $program = $this->generator->generate_program((object)$params);

        $mapper = helper::find_mapper_for_entity('tool_program', helper::get_all_mappers());
        $this->assertInstanceOf(tool_program::class, $mapper);

        $data = $mapper->get_mapping_data_for_workplace_export($program->get('id'));
        $this->assertEquals([
            'id' => $program->get('id'),
            'idnumber' => $program->get('idnumber'),
            'fullname' => $program->get('fullname'),
            'tenantid' => $program->get('tenantid'),
        ], $data);
    }

    /**
     * Test the mapper class successfully locates existing program by idnumber
     */
    public function test_locate_mapping_by_idnumber(): void {
        [$tenant1, $users1] = $this->tenantgenerator->create_tenant_and_users(1);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(1);

        self::setUser($users1[0]);
        $params = [
            'tenantid' => $tenant1->id,
            'fullname' => 'Program one',
            'idnumber' => 'IDNUMBER1',
        ];
        $program = $this->generator->generate_program((object)$params);

        $mapping = $this->wpgenerator->locate_mapping('tool_program', [
            'idnumber' => 'nonexistingidnumber',
            'id' => $program->get('id'),
            'fullname' => $program->get('fullname'),
            'tenantid' => 0,
        ]);
        $this->assertEquals([null, [], ["Program 'Program one' ('nonexistingidnumber') was not found"], false], $mapping);

        $mapping = $this->wpgenerator->locate_mapping('tool_program', [
            'idnumber' => $program->get('idnumber'),
            'id' => 0,
            'fullname' => '',
            'tenantid' => 0,
        ]);
        $this->assertEquals([$program->get('id'), [], [], true], $mapping);

        // Locating as user in another tenant.
        self::setUser($users2[0]);
        [$programid, $notices, $errors, $isvalidated] = $this->wpgenerator->locate_mapping('tool_program',
            ['idnumber' => 'IDNUMBER1']);
        $this->assertNull($programid);
        $this->assertEmpty($notices);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('was not found', $errors[0]);
        $this->assertFalse($isvalidated);
    }

    /**
     * Test the mapper class successfully locates existing program by name
     */
    public function test_locate_mapping_by_name(): void {
        self::setAdminUser();

        $params = [
            'tenantid' => \tool_tenant\tenancy::get_default_tenant_id(),
            'fullname' => 'Program one',
            'idnumber' => '',
        ];
        $program = $this->generator->generate_program((object)$params);

        // Check the mapper class.
        $mapper = helper::find_mapper_for_entity('tool_program', helper::get_all_mappers());
        $this->assertInstanceOf(\tool_program\tool_wp\mapper\tool_program::class, $mapper);

        $mapping = $this->wpgenerator->locate_mapping('tool_program', ['fullname' => 'nonexistingname']);
        $this->assertEquals([null, [], ["Program 'nonexistingname' was not found"], false], $mapping);

        $mapping = $this->wpgenerator->locate_mapping('tool_program', [
            'idnumber' => '',
            'id' => 0,
            'fullname' => $program->get('fullname'),
            'tenantid' => 0,
        ]);
        $str = get_string('mappingnoticenoidnumber', 'tool_program');
        $this->assertEquals([$program->get('id'), [$str], [], true], $mapping);
    }

    /**
     * Test the mapper class successfully locates existing program by name on correct tenant
     */
    public function test_locate_mapping_program_in_tenants(): void {
        self::setAdminUser();

        [$tenant1, $users1] = $this->tenantgenerator->create_tenant_and_users(1);
        [$tenant2, $users2] = $this->tenantgenerator->create_tenant_and_users(1);
        self::setUser($users1[0]);

        $params = [
            'tenantid' => $tenant1->id,
            'fullname' => 'Same program name',
            'idnumber' => 'SAMEIDNUMBER',
        ];
        $program1 = $this->generator->generate_program((object)$params);

        $params = [
            'tenantid' => $tenant2->id,
            'fullname' => 'Same program name',
            'idnumber' => 'SAMEIDNUMBER',
        ];
        $program2 = $this->generator->generate_program((object)$params);

        // Test mapping with user in tenant1.
        $mapping = $this->wpgenerator->locate_mapping('tool_program', ['fullname' => $program1->get('fullname')]);
        $str = get_string('mappingnoticenoidnumber', 'tool_program');
        $this->assertEquals([$program1->get('id'), [$str], [], true], $mapping);

        // Test mapping with user in tenant2.
        self::setUser($users2[0]);
        $mapping = $this->wpgenerator->locate_mapping('tool_program', ['fullname' => $program2->get('fullname')]);
        $str = get_string('mappingnoticenoidnumber', 'tool_program');
        $this->assertEquals([$program2->get('id'), [$str], [], true], $mapping);
    }
}
