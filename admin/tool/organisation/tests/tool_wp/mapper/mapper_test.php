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

namespace tool_organisation\tool_wp\mapper;

use advanced_testcase;
use stdClass;
use tool_organisation_generator;
use tool_tenant_generator;
use tool_wp\local\exportimport\helper;
use tool_wp_generator;

/**
 * Tests for the export/import API.
 *
 * @package    tool_organisation
 * @covers     \tool_organisation\tool_wp\mapper\tool_organisation_department
 * @covers     \tool_organisation\tool_wp\mapper\tool_organisation_position
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class mapper_test extends advanced_testcase {

    /** @var stdClass */
    protected $pf;
    /** @var stdClass */
    protected $pfother;
    /** @var stdClass */
    protected $pftenantother;
    /** @var stdClass */
    protected $pa;
    /** @var stdClass */
    protected $pb;
    /** @var stdClass */
    protected $pa1;
    /** @var stdClass */
    protected $pa2;
    /** @var stdClass */
    protected $pb1;


    /** @var stdClass */
    protected $df;
    /** @var stdClass */
    protected $dfother;
    /** @var stdClass */
    protected $dftenantother;
    /** @var stdClass */
    protected $da;
    /** @var stdClass */
    protected $db;
    /** @var stdClass */
    protected $da1;
    /** @var stdClass */
    protected $da2;
    /** @var stdClass */
    protected $db1;

    /** @var array */
    protected $users = [];

    /** @var stdClass */
    protected $tenant;
    /** @var stdClass */
    protected $tenantother;

    /**
     * Tenant generator
     *
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Organisation generator
     *
     * @return tool_organisation_generator
     */
    protected function get_generator() : tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }

    /**
     * WP generator
     *
     * @return tool_wp_generator
     */
    protected function get_workplace_generator() : tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }

    /**
     * Sets the user as a tenant admin for his tenant
     *
     * @param int $userid
     */
    protected function make_user_tenant_admin(int $userid) {
        (new \tool_tenant\manager())->assign_tenant_admin_roles([$userid], \tool_tenant\tenancy::get_tenant_id($userid));
    }

    /**
     * Generates a user, allocates to the tenant and gives a job
     *
     * @param string $username
     * @param string|null $position
     * @param string|null $department
     * @return stdClass
     */
    protected function generate_user(string $username, string $position = null, string $department = null) : stdClass {
        if (!array_key_exists($username, $this->users)) {
            $user = $this->getDataGenerator()->create_user(['username' => $username]);
            $this->get_tenant_generator()->allocate_user($user->id, $this->tenant->id);
            $this->users[$username] = $user;
        }
        if ($position && $department) {
            $this->get_generator()->assign_job((object)['userid' => $this->users[$username]->id,
                'positionid' => $this->{$position}->id,
                'departmentid' => $this->{$department}->id]);
        }

        return $this->users[$username];
    }

    /**
     * Generate test structure
     */
    protected function generate_structure() {
        $this->resetAfterTest();
        $this->tenant = $this->get_tenant_generator()->create_tenant();
        $this->tenantother = $this->get_tenant_generator()->create_tenant();

        $generator = $this->get_generator();

        $this->pf = $generator->create_position(['tenantid' => $this->tenant->id]);
        $this->pfother = $generator->create_position(['tenantid' => $this->tenant->id, 'idnumber' => 'pfother']);
        $this->pftenantother = $generator->create_position(['tenantid' => $this->tenantother->id]);

        $this->pa = $generator->create_position(['parentid' => $this->pf->id, 'globalmanager' => 1,
            'globalpermissions' => \tool_organisation\organisation::PERM_ALLOCATE_PROGRAMS |
                \tool_organisation\organisation::PERM_VIEW_REPORTS,
            'idnumber' => 'pa']);
        $this->pb = $generator->create_position(['parentid' => $this->pf->id, 'departmentmanager' => 1]);
        $this->pa1 = $generator->create_position(['parentid' => $this->pa->id]);
        $this->pa2 = $generator->create_position(['parentid' => $this->pa->id]);
        $this->pb1 = $generator->create_position(['parentid' => $this->pb->id]);

        $this->df = $generator->create_department(['tenantid' => $this->tenant->id]);
        $this->dfother = $generator->create_department(['tenantid' => $this->tenant->id, 'idnumber' => 'dfother']);
        $this->dftenantother = $generator->create_department(['tenantid' => $this->tenantother->id]);

        $this->da = $generator->create_department(['parentid' => $this->df->id, 'idnumber' => 'da']);
        $this->db = $generator->create_department(['parentid' => $this->df->id]);
        $this->da1 = $generator->create_department(['parentid' => $this->da->id]);
        $this->da2 = $generator->create_department(['parentid' => $this->da->id]);
        $this->db1 = $generator->create_department(['parentid' => $this->db->id,
            'description' => '<img src="@@PLUGINFILE@@/cat.png">', 'descriptionformat' => FORMAT_HTML]);
        // Add files to the description.
        get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'tool_organisation',
            'filearea' => \tool_organisation\department_manager::get_description_filearea(),
            'itemid' => $this->db1->id,
            'filepath' => '/',
            'filename' => 'cat.png'
        ], 'cat');
    }

    /**
     * Test for department mapper
     */
    public function test_department_mapper() {
        $this->resetAfterTest();
        $this->generate_structure();
        $user = $this->generate_user('user1');
        $this->setUser($user);

        // Check the mapper class.
        $mapper = helper::find_mapper_for_entity('tool_organisation_department', helper::get_all_mappers());
        $this->assertInstanceOf(\tool_organisation\tool_wp\mapper\tool_organisation_department::class, $mapper);
        $data = $mapper->get_mapping_data_for_workplace_export($this->da->id);
        $this->assertEquals('da', $data['idnumber']);
        unset($data['id']);

        // Different kinds of valid mappings.
        $result = $this->get_workplace_generator()->locate_mapping('tool_organisation_department', $data);
        $this->assertEquals([$this->da->id, [], [], true], $result);

        $result = $this->get_workplace_generator()->locate_mapping('tool_organisation_department',
            ['idnumber' => 'da']);
        $this->assertEquals([$this->da->id, [], [], true], $result);

        $result = $this->get_workplace_generator()->locate_mapping('tool_organisation_department',
            ['idnumber' => 'da']);
        $this->assertEquals([$this->da->id, [], [], true], $result);

        // A notice when department was matched by name.
        list($entityid, $notices, $errors, $isvalidated) =
            $this->get_workplace_generator()->locate_mapping('tool_organisation_department',
                ['name' => $this->db->name]);
        $this->assertEquals($this->db->id, $entityid);
        $this->assertCount(1, $notices);
        $this->assertStringContainsString("The department was located by name", $notices[0]);
        $this->assertEmpty($errors);
        $this->assertTrue($isvalidated);

        // Non-existing department (by idnumber).
        list($entityid, $notices, $errors, $isvalidated) =
            $this->get_workplace_generator()->locate_mapping('tool_organisation_department', ['idnumber' => 'NONEXISTING']);
        $this->assertNull($entityid);
        $this->assertEmpty($notices);
        $this->assertCount(1, $errors);
        $this->assertEquals("A department NONEXISTING was not found", $errors[0]);
        $this->assertFalse($isvalidated);

        // Non-existing department (by name).
        list($entityid, $notices, $errors, $isvalidated) =
            $this->get_workplace_generator()->locate_mapping('tool_organisation_department', ['name' => 'NONEXISTING']);
        $this->assertNull($entityid);
        $this->assertEmpty($notices);
        $this->assertCount(1, $errors);
        $this->assertEquals("A department NONEXISTING was not found", $errors[0]);
        $this->assertFalse($isvalidated);
    }

    /**
     * Test for position mapper
     */
    public function test_position_mapper() {
        $this->resetAfterTest();
        $this->generate_structure();
        $user = $this->generate_user('user1');
        $this->setUser($user);

        // Check the mapper class.
        $mapper = helper::find_mapper_for_entity('tool_organisation_position', helper::get_all_mappers());
        $this->assertInstanceOf(\tool_organisation\tool_wp\mapper\tool_organisation_position::class, $mapper);
        $data = $mapper->get_mapping_data_for_workplace_export($this->pa->id);
        $this->assertEquals('pa', $data['idnumber']);
        unset($data['id']);

        // Different kinds of valid mappings.
        $result = $this->get_workplace_generator()->locate_mapping('tool_organisation_position', $data);
        $this->assertEquals([$this->pa->id, [], [], true], $result);

        $result = $this->get_workplace_generator()->locate_mapping('tool_organisation_position',
            ['idnumber' => 'pa']);
        $this->assertEquals([$this->pa->id, [], [], true], $result);

        $result = $this->get_workplace_generator()->locate_mapping('tool_organisation_position',
            ['idnumber' => 'pa']);
        $this->assertEquals([$this->pa->id, [], [], true], $result);

        // A notice when position was matched by name.
        list($entityid, $notices, $errors, $isvalidated) =
            $this->get_workplace_generator()->locate_mapping('tool_organisation_position',
                ['name' => $this->pb->name]);
        $this->assertEquals($this->pb->id, $entityid);
        $this->assertCount(1, $notices);
        $this->assertStringContainsString("The position was located by name", $notices[0]);
        $this->assertEmpty($errors);
        $this->assertTrue($isvalidated);

        // Non-existing position (by idnumber).
        list($entityid, $notices, $errors, $isvalidated) =
            $this->get_workplace_generator()->locate_mapping('tool_organisation_position', ['idnumber' => 'NONEXISTING']);
        $this->assertNull($entityid);
        $this->assertEmpty($notices);
        $this->assertCount(1, $errors);
        $this->assertEquals("A position NONEXISTING was not found", $errors[0]);
        $this->assertFalse($isvalidated);

        // Non-existing position (by name).
        list($entityid, $notices, $errors, $isvalidated) =
            $this->get_workplace_generator()->locate_mapping('tool_organisation_position', ['name' => 'NONEXISTING']);
        $this->assertNull($entityid);
        $this->assertEmpty($notices);
        $this->assertCount(1, $errors);
        $this->assertEquals("A position NONEXISTING was not found", $errors[0]);
        $this->assertFalse($isvalidated);
    }
}
