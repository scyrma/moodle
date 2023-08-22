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

namespace tool_organisation;

use advanced_testcase;
use moodle_exception;
use tool_organisation_generator;

/**
 * The department test class.
 *
 * @package    tool_organisation
 * @covers     \tool_organisation\department_manager
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class department_test extends advanced_testcase {

    /** @var \stdClass */
    protected $framework1;
    /** @var \stdClass */
    protected $framework2;
    /** @var \stdClass */
    protected $department11;
    /** @var \stdClass */
    protected $department12;
    /** @var \stdClass */
    protected $department111;
    /** @var \stdClass */
    protected $department112;
    /** @var \stdClass */
    protected $department113;
    /** @var \stdClass */
    protected $department121;
    /** @var \stdClass */
    protected $department122;
    /** @var \stdClass */
    protected $department123;
    /**
     * Test for \tool_organisation\manager::create_department*
     */
    public function test_create_department() {
        $this->resetAfterTest();
        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        $framework = (new \tool_organisation\department_manager())->create_department((object)['name' => 'F1']);
        $this->assertEquals(true, $framework->is_framework());
        $this->assertEquals($framework->get('id'), $framework->get_framework_id());
        $frameworkid = $framework->get('id');
        $this->assertEquals('/' . $frameworkid, $framework->get('path'));

        $department = (new \tool_organisation\department_manager())->create_department(
            (object)['name' => 'D1', 'parentid' => $framework->get('id')]);
        $this->assertEquals(false, $department->is_framework());
        $this->assertEquals($frameworkid, $department->get_framework_id());
        $this->assertEquals($frameworkid, $department->get('parentid'));
        $this->assertEquals('/' . $frameworkid . '/' . $department->get('id'), $department->get('path'));

        $department2 = (new \tool_organisation\department_manager())->create_department(
            (object)['name' => 'D2', 'parentid' => $department->get('id')]);
        $this->assertEquals(false, $department2->is_framework());
        $this->assertEquals($frameworkid, $department2->get_framework_id());
        $this->assertEquals($department->get('id'), $department2->get('parentid'));
        $this->assertEquals('/' . $frameworkid . '/' . $department->get('id') . '/' . $department2->get('id'),
            $department2->get('path'));
    }

    /**
     * Test for \tool_organisation\manager::update_department
     */
    public function test_update_department() {
        $this->resetAfterTest();
        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        $manager = new \tool_organisation\department_manager();
        $framework = $generator->create_department();
        $manager->update_department($framework->id, (object)['name' => 'New name']);
        $framework = new \tool_organisation\department($framework->id);
        $this->assertEquals('New name', $framework->get('name'));
    }

    /**
     * Generate test structure
     */
    protected function generate_structure() {
        $this->resetAfterTest();
        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        $this->framework1 = $generator->create_department();
        $this->framework2 = $generator->create_department();

        $this->department11 = $generator->create_department(['parentid' => $this->framework1->id]);
        $this->department12 = $generator->create_department(['parentid' => $this->framework1->id]);
        $this->department111 = $generator->create_department(['parentid' => $this->department11->id]);
        $this->department112 = $generator->create_department(['parentid' => $this->department11->id]);
        $this->department113 = $generator->create_department(['parentid' => $this->department11->id]);
        $this->department121 = $generator->create_department(['parentid' => $this->department12->id]);
        $this->department122 = $generator->create_department(['parentid' => $this->department12->id]);
        $this->department123 = $generator->create_department(['parentid' => $this->department12->id]);
    }

    /**
     * Tests for tool_organisation\manager::get_department_frameworks and tool_organisation\manager::get_department_structure
     */
    public function test_department_structure() {
        $this->generate_structure();

        $manager = new \tool_organisation\department_manager();
        $frameworks = $manager->get_department_frameworks();
        $this->assertEquals([$this->framework1->id, $this->framework2->id], array_keys($frameworks));

        $structure = $manager->get_department_structure($this->framework1->id);
        $this->assertEquals([$this->department11->id, $this->department12->id],
            array_keys($structure->get_children()));
        $this->assertEquals([$this->department111->id, $this->department112->id, $this->department113->id],
            array_keys($structure->get_children()[$this->department11->id]->get_children()));
        $this->assertEquals([$this->department121->id, $this->department122->id, $this->department123->id],
            array_keys($structure->get_children()[$this->department12->id]->get_children()));
        $temp = $structure->get_children()[$this->department12->id]->get_children();
        $this->assertEquals(0, $temp[$this->department121->id]->get('sortorder'));
        $this->assertEquals(1, $temp[$this->department122->id]->get('sortorder'));
        $this->assertEquals(2, $temp[$this->department123->id]->get('sortorder'));

        $this->assertEquals([], $manager->get_department_structure($this->framework2->id)->get_children());
    }

    /**
     * Tests for tool_organisation\manager::department_move
     */
    public function test_move_department() {
        $this->generate_structure();

        // Move without changing parent.
        $manager = new \tool_organisation\department_manager();
        $manager->move($this->department122->id, $this->department12->id,
            $this->department121->id);
        $structure = $manager->get_department_structure($this->framework1->id);
        $temp = $structure->get_children()[$this->department12->id]->get_children();
        $this->assertEquals([$this->department122->id, $this->department121->id, $this->department123->id],
            array_keys($temp));
        $this->assertEquals(0, $temp[$this->department122->id]->get('sortorder'));
        $this->assertEquals(1, $temp[$this->department121->id]->get('sortorder'));
        $this->assertEquals(2, $temp[$this->department123->id]->get('sortorder'));

        // Move with changing parent.
        $manager->move($this->department111->id, $this->department122->id);
        $structure = $manager->get_department_structure($this->framework1->id);
        $this->assertEquals([$this->department112->id, $this->department113->id],
            array_keys($structure->get_children()[$this->department11->id]->get_children()));
        $temp = $structure->get_children()[$this->department12->id]->get_children()[$this->department122->id];
        $this->assertEquals([$this->department111->id],
            array_keys($temp->get_children()));
        $this->assertEquals(4, $temp->get_children()[$this->department111->id]->get('pathlevel'));
        $temp2 = $temp->get_children()[$this->department111->id];
        $this->assertEquals($temp->get('path').'/'.$this->department111->id, $temp2->get('path'));
    }

    /**
     * Tests for tool_organisation\manager::department_move
     */
    public function test_move_department_errors() {
        $this->generate_structure();
        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
        $department1111 = $generator->create_department(['parentid' => $this->department111->id]);

        // Move to its own child.
        $manager = new \tool_organisation\department_manager();
        try {
            $manager->move($this->department12->id, $this->department122->id);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString("An error occurred while moving", $e->getMessage());
        }

        // Move to its own grandchild.
        $manager = new \tool_organisation\department_manager();
        try {
            $manager->move($this->department11->id, $department1111->id);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString("An error occurred while moving", $e->getMessage());
        }

        // Move to another framework.
        $manager = new \tool_organisation\department_manager();
        try {
            $manager->move($this->department11->id, $this->framework2->id);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString("An error occurred while moving", $e->getMessage());
        }

        // Move framework under another department.
        $manager = new \tool_organisation\department_manager();
        try {
            $manager->move($this->framework2->id, $this->framework1->id);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString("An error occurred while moving", $e->getMessage());
        }
    }

    /**
     * Test for delete_position
     */
    public function test_delete_position() {
        global $DB;
        $this->generate_structure();
        $manager = new \tool_organisation\department_manager();
        $originalids = $DB->get_fieldset_select('tool_organisation_department', 'id',  '1=1', []);
        $manager->delete_department($this->department11->id);
        $ids = $DB->get_fieldset_select('tool_organisation_department', 'id',  '1=1', []);

        $this->assertEqualsCanonicalizing([$this->department11->id, $this->department111->id,
                $this->department112->id, $this->department113->id],
            array_diff($originalids, $ids));
    }

    /**
     * Test for delete_position with exception
     */
    public function test_delete_position_with_jobs() {
        global $DB;
        $this->generate_structure();
        $u = $this->getDataGenerator()->create_user();
        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
        $p = $generator->create_position();
        $p1 = $generator->create_position(['parentid' => $p->id]);
        $generator->assign_job(['userid' => $u->id, 'positionid' => $p1->id,
            'departmentid' => $this->department11->id]);

        $manager = new \tool_organisation\department_manager();
        try {
            $manager->delete_department($this->department11->id);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString('there are jobs', $e->getMessage());
        }
    }

    /*
     * Test for get_potential_parents
     */
    public function test_get_potential_parents() {
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
        $this->generate_structure();
        $department21 = $generator->create_department(['parentid' => $this->framework2->id]);
        $department1231 = $generator->create_department(['name' => 'Dept1231', 'idnumber' => 'Dept1231 Test',
            'parentid' => $this->department123->id]);
        $department12311 = $generator->create_department(['name' => 'Dept12311', 'parentid' => $department1231->id]);
        $department123111 = $generator->create_department(['name' => 'Dept123111 Test', 'parentid' => $department12311->id]);

        /*
         * Current department structure:
         * framework1
         *      department11
         *          department111
         *          department112
         *          department113
         *      department12
         *          department121
         *          department122
         *          department123
         *              department1231
         *                  department12311
         *                      department123111
         * framework2
         *      department21
         */

        $manager = new \tool_organisation\department_manager();
        $department11 = $manager->get_department($this->department11->id);
        $framework1 = $manager->get_department($this->framework1->id);

        // When adding a new department, all the options should be shown.
        $potentialparents = $manager->get_potential_parents('', null, $framework1);
        $this->assertCount(12, $potentialparents);

        // Look for department11 potential parents without any search string.
        $potentialparents = $manager->get_potential_parents('', $department11, $framework1);
        // The framework itself should also be a potential parent.
        $this->assertCount(7 + 1, $potentialparents);
        $this->assertArrayHasKey($this->framework1->id, $potentialparents);
        $this->assertArrayNotHasKey($department21->id, $potentialparents);
        $this->assertArrayNotHasKey($this->department111->id, $potentialparents);
        $this->assertEquals((object)[
            'id' => $department123111->id,
            'name' => 'Dept123111 Test',
            'path' => '(.../'.$this->department123->name.'/Dept1231/Dept12311)'
        ], $potentialparents[$department123111->id]);
        $this->assertEquals((object)[
            'id' => $this->framework1->id,
            'name' => 'Top',
            'path' => ''
        ], $potentialparents[$this->framework1->id]);

        // Look for department11 potential parents with 'Test' search string.
        $potentialparents = $manager->get_potential_parents('Test', $department11);
        $this->assertCount(2, $potentialparents);
        $this->assertArrayHasKey($department123111->id, $potentialparents);
        $this->assertArrayHasKey($department1231->id, $potentialparents);
        // Look for department21 potential parents with 'Top' and 'New department 2' search string (Framework 2).
        $potentialparents = $manager->get_potential_parents('Top', $manager->get_department($department21->id));
        $this->assertCount(1, $potentialparents);
        $this->assertArrayHasKey($this->framework2->id, $potentialparents);
        $potentialparents = $manager->get_potential_parents('New department 2', $manager->get_department($department21->id));
        $this->assertCount(1, $potentialparents);
        $this->assertArrayHasKey($this->framework2->id, $potentialparents);
    }

    /*
     * Test for get_all_children
     */
    public function test_get_all_children() {
        $this->generate_structure();
        /*
         * Current department structure:
         * framework1
         *      department11
         *          department111
         *          department112
         *          department113
         *      department12
         *          department121
         *          department122
         *          department123
         * framework2
         */

        $manager = new \tool_organisation\department_manager();

        $allchildren = $manager->get_all_children($manager->get_department($this->framework1->id));
        $this->assertCount(8, $allchildren);
        $this->assertEquals($manager->get_department($this->department11->id), $allchildren[$this->department11->id]);

        $allchildren = $manager->get_all_children($manager->get_department($this->framework2->id));
        $this->assertEmpty($allchildren);

        $allchildren = $manager->get_all_children($manager->get_department($this->department11->id));
        $this->assertCount(3, $allchildren);
    }
}
