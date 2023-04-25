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

namespace tool_custompage\output;

use advanced_testcase;
use tool_custompage_generator;
use tool_custompage\permission_exception;

/**
 * Unit tests for the weight editable class
 *
 * @package     tool_custompage
 * @covers      \tool_custompage\output\weight_editable
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class weight_editable_test extends advanced_testcase {

    /**
     * Test update method
     */
    public function test_update(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My page', 'weight' => 0]);

        $editable = weight_editable::update($page->get('id'), '3');
        $result = $editable->export_for_template($PAGE->get_renderer('core'));
        $this->assertEquals(3, $result['value']);

        // Reload persistent, assert update.
        $this->assertEquals(3, $page->read()->get('weight'));
    }

    /**
     * Test update method for a user without permission to edit page
     */
    public function test_update_access_exception(): void {
        $this->resetAfterTest();

        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My page', 'weight' => 0]);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(permission_exception::class);
        $this->expectExceptionMessage('You cannot edit this page');
        weight_editable::update($page->get('id'), '3');
    }

    /**
     * Test update method via component callback
     *
     * @covers ::tool_custompage_inplace_editable
     */
    public function test_update_callback(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page = $generator->create_page(['name' => 'My page', 'weight' => 0]);

        $editable = component_callback('tool_custompage', 'inplace_editable', ['weight', $page->get('id'), '3']);
        $this->assertInstanceOf(weight_editable::class, $editable);

        // Reload persistent, assert update.
        $this->assertEquals(3, $page->read()->get('weight'));
    }
}
