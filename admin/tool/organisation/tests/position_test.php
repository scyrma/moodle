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
 * File containing tests for position.
 *
 * @package     tool_organisation
 * @category    test
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The position test class.
 *
 * @package    tool_organisation
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_organisation_position_testcase extends advanced_testcase {

    /** @var \stdClass */
    protected $framework1;
    /** @var \stdClass */
    protected $framework2;
    /** @var \stdClass */
    protected $position11;
    /** @var \stdClass */
    protected $position12;
    /** @var \stdClass */
    protected $position111;
    /** @var \stdClass */
    protected $position112;
    /** @var \stdClass */
    protected $position113;
    /** @var \stdClass */
    protected $position121;
    /** @var \stdClass */
    protected $position122;
    /** @var \stdClass */
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
     * Tests for tool_organisation\manager::position_move
     */
    public function test_move_position_errors() {
        $this->generate_structure();
        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
        $position1111 = $generator->create_position(['parentid' => $this->position111->id]);

        // Move to its own child.
        $manager = new \tool_organisation\position_manager();
        try {
            $manager->move($this->position12->id, $this->position122->id);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString("An error occurred while moving", $e->getMessage());
        }

        // Move to its own grandchild.
        $manager = new \tool_organisation\position_manager();
        try {
            $manager->move($this->position11->id, $position1111->id);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString("An error occurred while moving", $e->getMessage());
        }

        // Move to another framework.
        $manager = new \tool_organisation\position_manager();
        try {
            $manager->move($this->position11->id, $this->framework2->id);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString("An error occurred while moving", $e->getMessage());
        }

        // Move framework under another position.
        $manager = new \tool_organisation\position_manager();
        try {
            $manager->move($this->framework2->id, $this->framework1->id);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString("An error occurred while moving", $e->getMessage());
        }
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

        $this->assertEqualsCanonicalizing([$this->position11->id, $this->position111->id, $this->position112->id,
            $this->position113->id],
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
        $d = $generator->create_department();
        $d1 = $generator->create_department(['parentid' => $d->id]);
        $generator->assign_job(['userid' => $u->id, 'positionid' => $this->position111->id,
            'departmentid' => $d1->id]);

        $manager = new \tool_organisation\position_manager();
        try {
            $manager->delete_position($this->position11->id);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString('there are jobs', $e->getMessage());
        }
    }

    /**
     * Test for get_potential_parents
     */
    public function test_get_potential_parents() {
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
        $this->generate_structure();
        $position21 = $generator->create_position(['parentid' => $this->framework2->id]);
        $position1231 = $generator->create_position(['name' => 'Pos1231', 'idnumber' => 'Pos1231 Test',
            'parentid' => $this->position123->id]);
        $position12311 = $generator->create_position(['name' => 'Pos12311', 'parentid' => $position1231->id]);
        $position123111 = $generator->create_position(['name' => 'Pos123111 Test', 'parentid' => $position12311->id]);

        /*
         * Current position structure:
         * framework1
         *      position11
         *          position111
         *          position112
         *          position113
         *      position12
         *          position121
         *          position122
         *          position123
         *              position1231
         *                  position12311
         *                      position123111
         * framework2
         *      position21
         */

        $manager = new \tool_organisation\position_manager();

        // Look for framework2 porential parents.
        $potentialparents = $manager->get_potential_parents('', $manager->get_position($this->framework1->id));
        $this->assertCount(0, $potentialparents);

        // Look for position11 potential parents without any search string.
        $potentialparents = $manager->get_potential_parents('', $manager->get_position($this->position11->id));
        $this->assertCount(7, $potentialparents);
        $this->assertArrayNotHasKey($this->framework1->id, $potentialparents);
        $this->assertArrayNotHasKey($position21->id, $potentialparents);
        $this->assertArrayNotHasKey($this->position111->id, $potentialparents);
        $this->assertEquals((object)[
            'id' => $position123111->id,
            'name' => 'Pos123111 Test',
            'path' => '(.../'.$this->position123->name.'/Pos1231/Pos12311)'
        ], $potentialparents[$position123111->id]);

        // Look for position11 potential parents with 'Test' search string.
        $potentialparents = $manager->get_potential_parents('Test', $manager->get_position($this->position11->id));
        $this->assertCount(2, $potentialparents);
        $this->assertArrayHasKey($position123111->id, $potentialparents);
        $this->assertArrayHasKey($position1231->id, $potentialparents);
    }

    /*
     * Test for get_all_children
     */
    public function test_get_all_children() {
        $this->generate_structure();
        /*
         * Current position structure:
         * framework1
         *      position11
         *          position111
         *          position112
         *          position113
         *      position12
         *          position121
         *          position122
         *          position123
         * framework2
         */

        $manager = new \tool_organisation\position_manager();

        $allchildren = $manager->get_all_children($manager->get_position($this->framework1->id));
        $this->assertCount(8, $allchildren);
        $this->assertEquals($manager->get_position($this->position11->id), $allchildren[$this->position11->id]);

        $allchildren = $manager->get_all_children($manager->get_position($this->framework2->id));
        $this->assertEmpty($allchildren);

        $allchildren = $manager->get_all_children($manager->get_position($this->position11->id));
        $this->assertCount(3, $allchildren);
    }
}
