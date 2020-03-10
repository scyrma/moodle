<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * File containing tests for department.
 *
 * @package     tool_organisation
 * @category    test
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The department test class.
 *
 * @package    tool_organisation
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_organisation_department_testcase extends advanced_testcase {

    /** @var \tool_organisation\department */
    protected $framework1;
    /** @var \tool_organisation\department */
    protected $framework2;
    /** @var \tool_organisation\department */
    protected $department11;
    /** @var \tool_organisation\department */
    protected $department12;
    /** @var \tool_organisation\department */
    protected $department111;
    /** @var \tool_organisation\department */
    protected $department112;
    /** @var \tool_organisation\department */
    protected $department113;
    /** @var \tool_organisation\department */
    protected $department121;
    /** @var \tool_organisation\department */
    protected $department122;
    /** @var \tool_organisation\department */
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
     * Test for delete_position
     */
    public function test_delete_position() {
        global $DB;
        $this->generate_structure();
        $manager = new \tool_organisation\department_manager();
        $originalids = $DB->get_fieldset_select('tool_organisation_department', 'id',  '1=1', []);
        $manager->delete_department($this->department11->id);
        $ids = $DB->get_fieldset_select('tool_organisation_department', 'id',  '1=1', []);

        $this->assertEquals([$this->department11->id, $this->department111->id,
                $this->department112->id, $this->department113->id],
            array_diff($originalids, $ids), '', 0, 10, true);
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
            $this->assertContains('there are jobs', $e->getMessage());
        }
    }
}
