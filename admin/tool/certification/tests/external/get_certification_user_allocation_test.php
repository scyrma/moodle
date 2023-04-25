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

namespace tool_certification\external;

use externallib_advanced_testcase;
use moodle_exception;
use tool_certification\constants;
use tool_certification_generator;
use tool_tenant\tenancy;
use tool_tenant_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the tool_certification get_certification_user_allocation external class.
 *
 * @package    tool_certification
 * @covers     \tool_certification\external\get_certification_user_allocation
 * @author     2022 Odei Alba <odei.alba@moodle.com>
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_certification_user_allocation_test extends externallib_advanced_testcase {

    /** @var tool_certification_generator */
    protected $generator;

    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    /**
     * Test get_certification_user_allocation.
     */
    public function test_execute(): void {
        $user = $this->tenantgenerator->create_user();
        $this->setUser($user);
        // Assign required permissions to the user.
        $this->generator->assign_allocateuser_capability($user->id, \context_system::instance());

        // Generate certification.
        $certparams = ['fullname' => 'Cert one', 'idnumber' => 'Cert1'];
        $certification1 = $this->generator->generate_certification($certparams);

        $status = constants::STATUS_OVERRIDE_DEFAULT;
        $usercertification1params = (object) [
            'userid' => $user->id,
            'certificationid' => $certification1->get('id'),
            'status' => $status
        ];
        $now = time();

        // Allocate users to the certification and mark it as certified.
        \tool_certification\api::allocate_user($certification1, $usercertification1params);
        \tool_certification\api::set_user_as_certified($user->id, $certification1->get('id'), null, $now, get_admin()->id);

        $response = get_certification_user_allocation::execute($certification1->get('id'), $user->id);
        $response = get_certification_user_allocation::clean_returnvalue(
            get_certification_user_allocation::execute_returns(),
            $response
        );

        $this->assertEquals($certification1->get('id'), $response['certificationid']);
        $this->assertEquals($user->id, $response['userid']);
        $completionsresponse = $response['completions'];
        $responsetimecertified = array_column($completionsresponse, 'timecertified');
        $this->assertEqualsCanonicalizing([$now], $responsetimecertified);
        $this->assertEquals($certification1->get('fullname'), $response['certificationfullname']);
        $this->assertEquals($certification1->get('idnumber'), $response['certificationidnumber']);
    }

    /**
     * Test get_certification_user_allocation error on no permission to access the tenant with certification.
     */
    public function test_execute_wrong_tenant_certification(): void {
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $this->setUser($user);
        // Assign required permissions to the user.
        $this->generator->assign_allocateuser_capability($user->id, \context_system::instance());
        $newtenantid = $tenant->id;
        $tenantid = tenancy::get_default_tenant_id();

        // Create user.
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $user = $tenantgenerator->create_user(['tenantid' => $tenantid]);

        // Generate certification in other tenant.
        $certification1 = $this->generator->generate_certification(['tenantid' => $newtenantid]);

        $this->expectException(moodle_exception::class);
        $str = get_string('errornopermissionviewreports', 'tool_certification');
        $this->expectExceptionMessage($str);

        get_certification_user_allocation::execute($certification1->get('id'), $user->id);
    }

    /**
     * Test get_certification_user_allocation error on no permission to access the tenant with user.
     */
    public function test_execute_wrong_tenant_user(): void {
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $this->setUser($user);
        // Assign required permissions to the user.
        $this->generator->assign_allocateuser_capability($user->id, \context_system::instance());
        $newtenantid = $tenant->id;
        $tenantid = tenancy::get_default_tenant_id();

        // Create user in other tenant.
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $user = $tenantgenerator->create_user(['tenantid' => $newtenantid]);

        // Generate certification.
        $certification1 = $this->generator->generate_certification(['tenantid' => $tenantid]);

        $this->expectException(moodle_exception::class);
        $str = get_string('errornopermissionmanagecertifications', 'tool_certification');
        $this->expectExceptionMessage($str);

        get_certification_user_allocation::execute($certification1->get('id'), $user->id);
    }

    /**
     * Test get_certification_user_allocation error on no permission.
     */
    public function test_execute_no_permissions(): void {
        $user = $this->tenantgenerator->create_user();
        $this->setUser($user);

        // Generate certification.
        $certification1 = $this->generator->generate_certification();

        $this->expectException(moodle_exception::class);
        $str = get_string('errornopermissionmanagecertifications', 'tool_certification');
        $this->expectExceptionMessage($str);
        get_certification_user_allocation::execute($certification1->get('id'), $user->id);
    }
}
