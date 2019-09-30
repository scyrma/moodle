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
 * File containing tests for position.
 *
 * @package     tool_organisation
 * @category    test
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The position test class.
 *
 * @package    tool_organisation
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_organisation_position_testcase extends advanced_testcase {

    /** @var \tool_organisation\position */
    protected $framework1;
    /** @var \tool_organisation\position */
    protected $framework2;
    /** @var \tool_organisation\position */
    protected $position11;
    /** @var \tool_organisation\position */
    protected $position12;
    /** @var \tool_organisation\position */
    protected $position111;
    /** @var \tool_organisation\position */
    protected $position112;
    /** @var \tool_organisation\position */
    protected $position113;
    /** @var \tool_organisation\position */
    protected $position121;
    /** @var \tool_organisation\position */
    protected $position122;
    /** @var \tool_organisation\position */
    protected $position123;

    /**
     * Test for \tool_organisation\manager::create_position*
     */
    public function test_create_position() {
        $this->resetAfterTest();
        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        $framework = (new \tool_organisation\position_manager())->create_position((object)['name' => 'F1']);
        $this->assertEquals(true, $framework->is_framework());
        $this->assertEquals($framework->get('id'), $framework->get_framework_id());
        $frameworkid = $framework->get('id');
        $this->assertEquals('/' . $frameworkid, $framework->get('path'));

        $position = (new \tool_organisation\position_manager())->create_position(
            (object)['name' => 'D1', 'parentid' => $framework->get('id')]);
        $this->assertEquals(false, $position->is_framework());
        $this->assertEquals($frameworkid, $position->get_framework_id());
        $this->assertEquals($frameworkid, $position->get('parentid'));
        $this->assertEquals('/' . $frameworkid . '/' . $position->get('id'), $position->get('path'));

        $position2 = (new \tool_organisation\position_manager())->create_position(
            (object)['name' => 'D2', 'parentid' => $position->get('id')]);
        $this->assertEquals(false, $position2->is_framework());
        $this->assertEquals($frameworkid, $position2->get_framework_id());
        $this->assertEquals($position->get('id'), $position2->get('parentid'));
        $this->assertEquals('/' . $frameworkid . '/' . $position->get('id') . '/' . $position2->get('id'),
            $position2->get('path'));
    }

    /**
     * Test for \tool_organisation\manager::update_position
     */
    public function test_update_position() {
        $this->resetAfterTest();
        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        $manager = new \tool_organisation\position_manager();
        $framework = $generator->create_position();
        $manager->update_position($framework->id, (object)['name' => 'New name']);
        $framework = new \tool_organisation\position($framework->id);
        $this->assertEquals('New name', $framework->get('name'));
    }

    /**
     * Generate test structure
     */
    protected function generate_structure() {
        $this->resetAfterTest();
        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        $this->framework1 = $generator->create_position();
        $this->framework2 = $generator->create_position();

        $this->position11 = $generator->create_position(['parentid' => $this->framework1->id]);
        $this->position12 = $generator->create_position(['parentid' => $this->framework1->id]);
        $this->position111 = $generator->create_position(['parentid' => $this->position11->id]);
        $this->position112 = $generator->create_position(['parentid' => $this->position11->id]);
        $this->position113 = $generator->create_position(['parentid' => $this->position11->id]);
        $this->position121 = $generator->create_position(['parentid' => $this->position12->id]);
        $this->position122 = $generator->create_position(['parentid' => $this->position12->id]);
        $this->position123 = $generator->create_position(['parentid' => $this->position12->id]);
    }

    /**
     * Tests for tool_organisation\manager::get_position_frameworks and tool_organisation\manager::get_position_structure
     */
    public function test_position_structure() {
        $this->generate_structure();

        $manager = new \tool_organisation\position_manager();
        $frameworks = $manager->get_position_frameworks();
        $this->assertEquals([$this->framework1->id, $this->framework2->id], array_keys($frameworks));

        $structure = $manager->get_position_structure($this->framework1->id);
        $this->assertEquals([$this->position11->id, $this->position12->id],
            array_keys($structure->get_children()));
        $this->assertEquals([$this->position111->id, $this->position112->id, $this->position113->id],
            array_keys($structure->get_children()[$this->position11->id]->get_children()));
        $this->assertEquals([$this->position121->id, $this->position122->id, $this->position123->id],
            array_keys($structure->get_children()[$this->position12->id]->get_children()));
        $temp = $structure->get_children()[$this->position12->id]->get_children();
        $this->assertEquals(0, $temp[$this->position121->id]->get('sortorder'));
        $this->assertEquals(1, $temp[$this->position122->id]->get('sortorder'));
        $this->assertEquals(2, $temp[$this->position123->id]->get('sortorder'));

        $this->assertEquals([], $manager->get_position_structure($this->framework2->id)->get_children());
    }

    /**
     * Tests for tool_organisation\manager::position_move
     */
    public function test_move_position() {
        $this->generate_structure();

        // Move without changing parent.
        $manager = new \tool_organisation\position_manager();
        $manager->move($this->position122->id, $this->position12->id,
            $this->position121->id);
        $structure = $manager->get_position_structure($this->framework1->id);
        $temp = $structure->get_children()[$this->position12->id]->get_children();
        $this->assertEquals([$this->position122->id, $this->position121->id, $this->position123->id],
            array_keys($temp));
        $this->assertEquals(0, $temp[$this->position122->id]->get('sortorder'));
        $this->assertEquals(1, $temp[$this->position121->id]->get('sortorder'));
        $this->assertEquals(2, $temp[$this->position123->id]->get('sortorder'));

        // Move with changing parent.
        $manager->move($this->position111->id, $this->position122->id);
        $structure = $manager->get_position_structure($this->framework1->id);
        $this->assertEquals([$this->position112->id, $this->position113->id],
            array_keys($structure->get_children()[$this->position11->id]->get_children()));
        $temp = $structure->get_children()[$this->position12->id]->get_children()[$this->position122->id];
        $this->assertEquals([$this->position111->id],
            array_keys($temp->get_children()));
        $this->assertEquals(4, $temp->get_children()[$this->position111->id]->get('pathlevel'));
        $temp2 = $temp->get_children()[$this->position111->id];
        $this->assertEquals($temp->get('path').'/'.$this->position111->id, $temp2->get('path'));
    }

    /**
     * Test fordelete_position
     */
    public function test_delete_position() {
        global $DB;
        $this->generate_structure();
        $manager = new \tool_organisation\position_manager();
        $originalids = $DB->get_fieldset_select('tool_organisation_position', 'id',  '1=1', []);
        $manager->delete_position($this->position11->id);
        $ids = $DB->get_fieldset_select('tool_organisation_position', 'id',  '1=1', []);

        $this->assertEquals([$this->position11->id, $this->position111->id, $this->position112->id,
            $this->position113->id],
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
        $d = $generator->create_department();
        $d1 = $generator->create_department(['parentid' => $d->id]);
        $generator->assign_job(['userid' => $u->id, 'positionid' => $this->position111->id,
            'departmentid' => $d1->id]);

        $manager = new \tool_organisation\position_manager();
        try {
            $manager->delete_position($this->position11->id);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertContains('there are jobs', $e->getMessage());
        }
    }
}
