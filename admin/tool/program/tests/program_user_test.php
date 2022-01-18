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
 * Tests for program_user
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program;

use advanced_testcase;
use tool_program_generator;
use tool_program\persistent\program_user;
use tool_certification\certification;
use tool_wp_external;

/**
 * Class program_user_test
 *
 * @covers    \tool_program\persistent\program_user
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_user_test extends advanced_testcase {

    /** @var tool_program_generator */
    protected $generator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->resetAfterTest();
    }

    /**
     * Test get program
     */
    public function test_get_program(): void {
        self::setAdminUser();
        $program = $this->generator->generate_program();

        // We allocate a new user.
        $user1 = self::getDataGenerator()->create_user();
        $userdata = [
            'programid' => $program->get('id'),
            'userid' => $user1->id,
        ];
        $programuser = new program_user(0, (object) $userdata);
        $programuser->create();

        $userprogram = $programuser->get_program();

        $this->assertEquals($program->get('id'), $userprogram->get('id'));
        $this->assertEquals($program->get('fullname'), $userprogram->get('fullname'));
        $this->assertEquals($program->get('idnumber'), $userprogram->get('idnumber'));
    }

    /**
     * Test get user
     */
    public function test_get_user(): void {
        self::setAdminUser();
        // We allocate a new user.
        $user1 = self::getDataGenerator()->create_user();
        $userdata = [
            'programid' => '1',
            'userid' => $user1->id,
        ];
        $programuser = new program_user(0, (object) $userdata);
        $programuser->create();

        $user2 = $programuser->get_user();

        $this->assertEquals($user1, $user2);
    }

    /**
     * Test get certification
     */
    public function test_get_certification(): void {
        self::setAdminUser();
        global $CFG;

        if (!file_exists("{$CFG->dirroot}/{$CFG->admin}/tool/certification/")) {
            $this->markTestSkipped('Can not find certification plugin');
        }

        $certification = new certification(0, (object) [
            'fullname' => 'cert full name',
            'idnumber' => 2,
        ]);
        $certification->create();
        $certificationid = $certification->get('id');

        // We create one program.
        $programdata = $this->generator->get_dummy_program_data();
        $programdata->certificationid = $certificationid;
        $program1 = $this->generator->generate_program($programdata);

        // We create one user.
        $user1 = self::getDataGenerator()->create_user();

        // We allocate user into the program.
        $insertdata = (object) [
            'userid' => $user1->id,
            'certificationid' => $certificationid,
        ];
        $programuser = api::allocate_user($program1, $insertdata);

        $certresponse = $programuser->get_certification();
        $this->assertEquals($certresponse->get('fullname'), $certification->get('fullname'));
        $this->assertEquals($certresponse->get('idnumber'), $certification->get('idnumber'));
    }

    /**
     * Test callback for the user selector.
     */
    public function test_user_selector(): void {
        self::setAdminUser();

        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();

        $program1 = $this->generator->generate_program((object)['tenantid' => $defaulttenantid]);
        $user1 = self::getDataGenerator()->create_user(['firstname' => 'xxzz']);
        $user2 = self::getDataGenerator()->create_user(['firstname' => 'zz']);

        // Both users are potential users for an existing program.
        $results = tool_wp_external::potential_users_selector('zz', 'tool_program', 'allocate', $program1->get('id'));
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], array_keys($results));

        // Allocate one of the users to the program.
        api::allocate_user($program1, (object) ['userid' => $user1->id, 'certificationid' => 0]);

        // Only another user is now a potential user for this program.
        $results = tool_wp_external::potential_users_selector('zz', 'tool_program', 'allocate', $program1->get('id'));
        $this->assertEqualsCanonicalizing([$user2->id], array_keys($results));
    }
}
