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
 * Tests for certification_user.
 *
 * @package   tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_certification\api;
use tool_certification\constants;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Class tool_certification_user_testcase
 * @package   tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_certification_user_testcase extends advanced_testcase {

    /**
     * @var \tool_certification_generator
     */
    public $generator;

    /**
     * setUp.
     */
    public function setUp() {
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_certification');
        $this->resetAfterTest();
    }

    /**
     * Test callback for the user selector.
     */
    public function test_user_selector() {
        self::setAdminUser();
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();

        $certification1 = $this->generator->generate_certification(['tenantid' => $defaulttenantid]);
        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'xxzz']);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'zz']);

        // Both users are potential users for a new certification.
        $results = tool_wp_external::potential_users_selector('zz', 'tool_certification', 'allocate', 0);
        $this->assertEquals([$user1->id, $user2->id], array_keys($results), '', 0, 10, true);
        // Both users are potential users for an existing certification.
        $results = tool_wp_external::potential_users_selector('zz', 'tool_certification', 'allocate', $certification1->get('id'));
        $this->assertEquals([$user1->id, $user2->id], array_keys($results), '', 0, 10, true);
        // Allocate one of the users to the certification.
        $params = (object)[
            'userid' => $user1->id,
            'certificationid' => $certification1->get('id'),
            'allocationtype' => constants::ALLOCATION_MANUAL,
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];
        api::allocate_user($certification1, $params);
        // Only another user is now a potential user for this certification.
        $results = tool_wp_external::potential_users_selector('zz', 'tool_certification', 'allocate', $certification1->get('id'));
        $this->assertEquals([$user2->id], array_keys($results), '', 0, 10, true);
    }

    public function test_get_certification() {
        $user = $this->getDataGenerator()->create_user();
        $certification = $this->generator->generate_certification();

        $data = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user->id
        ];
        $certuser = new \tool_certification\certification_user(0, $data);
        $certuser->create();

        $cert1 = $certuser->get_certification();
        $this->assertInstanceOf('tool_certification\certification', $cert1);
        $this->assertEquals($certification->get('id'), $cert1->get('id'));
    }
}
