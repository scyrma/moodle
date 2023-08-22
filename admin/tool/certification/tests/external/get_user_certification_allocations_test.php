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
use tool_tenant_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the tool_certification get_user_certification_allocations external class.
 *
 * @package    tool_certification
 * @covers     \tool_certification\external\get_user_certification_allocations
 * @author     2022 Odei Alba <odei.alba@moodle.com>
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_user_certification_allocations_test extends externallib_advanced_testcase {

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
     * Test get_user_certification_allocations.
     */
    public function test_execute(): void {
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $this->setUser($user);
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        $newtenantid = $tenant->id;
        // Assign required permissions to the user.
        $this->generator->assign_allocateuser_capability($user->id, \context_system::instance());

        // Generate certification.
        $cert1params = ['tenantid' => $newtenantid, 'fullname' => 'Cert one', 'idnumber' => 'Cert1'];
        $cert2params = ['tenantid' => $sharedspaceid, 'fullname' => 'Cert two', 'idnumber' => 'Cert2'];
        $certification1 = $this->generator->generate_certification($cert1params);
        $certification2 = $this->generator->generate_certification($cert2params);

        // Allocate the user to the certifications.
        $status = constants::STATUS_OVERRIDE_DEFAULT;
        $usercertificationparams = ['userid' => $user->id, 'status' => $status];
        $usercertification1params = (object) ($usercertificationparams + ['certificationid' => $certification1->get('id')]);
        $usercertification2params = (object) ($usercertificationparams + ['certificationid' => $certification2->get('id')]);

        $now = time();

        \tool_certification\api::allocate_user($certification1, $usercertification1params);
        \tool_certification\api::set_user_as_certified($user->id, $certification1->get('id'), null, $now, get_admin()->id);
        \tool_certification\api::allocate_user($certification2, $usercertification2params);

        $response = get_user_certification_allocations::execute($user->id);
        $response = get_user_certification_allocations::clean_returnvalue(
            get_user_certification_allocations::execute_returns(),
            $response
        );

        $expectedids = [$certification1->get('id'), $certification2->get('id')];
        $actualids = array_column($response, 'certificationid');
        $this->assertEqualsCanonicalizing($expectedids, $actualids);
        $expectedfullnames = [$certification1->get('fullname'), $certification2->get('fullname')];
        $actualfullnames = array_column($response, 'certificationfullname');
        $this->assertEqualsCanonicalizing($expectedfullnames, $actualfullnames);
        $expectedidnumbers = [$certification1->get('idnumber'), $certification2->get('idnumber')];
        $actualidnumbers = array_column($response, 'certificationidnumber');
        $this->assertEqualsCanonicalizing($expectedidnumbers, $actualidnumbers);

        foreach ($response as $item) {
            $completionsresponse = $item['completions'];
            if ($item['certificationid'] == $certification1->get('id')) {
                $responsetimecertified = array_column($completionsresponse, 'timecertified');
                $responseuserid = array_column($completionsresponse, 'userid');
                $this->assertEqualsCanonicalizing([$now], $responsetimecertified);
                $this->assertEqualsCanonicalizing([$user->id], $responseuserid);
            } else if ($item['certificationid'] == $certification2->get('id')) {
                $this->assertEmpty($completionsresponse);
            }
        }
    }

    /**
     * Test get_user_certification_allocations error on no permission to access the tenant.
     */
    public function test_execute_wrong_tenant(): void {
        $user = $this->tenantgenerator->create_user();
        $this->setUser($user);
        // Assign required permissions to the user.
        $this->generator->assign_allocateuser_capability($user->id, \context_system::instance());
        $tenant = $this->tenantgenerator->create_tenant();
        $newtenantid = $tenant->id;

        $user = $this->tenantgenerator->create_user(['tenantid' => $newtenantid]);

        $this->expectException(moodle_exception::class);
        $str = get_string('errornopermissionviewreports', 'tool_certification');
        $this->expectExceptionMessage($str);

        get_user_certification_allocations::execute($user->id);
    }

    /**
     * Test get_user_certification_allocations error on no permission.
     */
    public function test_execute_no_permissions(): void {
        $user1 = $this->tenantgenerator->create_user();
        $user2 = $this->tenantgenerator->create_user();
        $this->setUser($user1);

        $this->expectException(moodle_exception::class);
        $str = get_string('errornopermissionviewreports', 'tool_certification');
        $this->expectExceptionMessage($str);
        get_user_certification_allocations::execute($user2->id);
    }
}
