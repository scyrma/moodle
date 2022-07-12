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

namespace tool_custompage\local\helpers;

use advanced_testcase;
use core\navigation\views\primary;
use tool_custompage\tool_custompage\audience\allusers;
use tool_custompage\tool_custompage\audience\manual;
use tool_custompage_generator;

/**
 * Unit tests for the navigation helper
 *
 * @package     tool_custompage
 * @covers      \tool_custompage\local\helpers\navigation
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class navigation_test extends advanced_testcase {

    /**
     * Test add_primary_nodes callback
     */
    public function test_add_primary_nodes(): void {
        global $PAGE;
        $PAGE->set_url("/");
        $this->resetAfterTest();

        // Create users.
        $user1 = $this->getDataGenerator()->create_user();

        // Create custom pages and audiences.
        /** @var tool_custompage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_custompage');
        $page1 = $generator->create_page(['name' => 'My page', 'weight' => 0, 'global' => 1]);
        $page2 = $generator->create_page(['name' => 'My page', 'weight' => 1, 'global' => 1]);
        $generator->create_audience([
            'pageid' => $page1->get('id'), 'classname' => manual::class, 'configdata' => ['users' => [$user1->id]],
        ]);
        $generator->create_audience([
            'pageid' => $page2->get('id'), 'classname' => allusers::class, 'configdata' => [],
        ]);

        $this->setUser($user1);

        // Initialise the primary navigation. This will call add_primary_nodes callback.
        $primarynav = new primary($PAGE);
        $primarynav->initialise();

        // Check custom pages nodes were added.
        $links = $primarynav->get_children_key_list();
        $expected = [
            'home',
            'myhome',
            'mycourses',
            'tool_custompage-' . $page1->get('id'),
            'tool_custompage-' . $page2->get('id')
        ];
        $this->assertEquals($expected, $links);

        $this->setAdminUser();

        // Initialise the primary navigation. This will call add_primary_nodes callback.
        $primarynav = new primary($PAGE);
        $primarynav->initialise();

        // Check custom page 2 node was added.
        $links = $primarynav->get_children_key_list();
        $expected = [
            'home',
            'myhome',
            'mycourses',
            'tool_custompage-' . $page2->get('id')
        ];
        $this->assertEquals($expected, $links);
    }
}
