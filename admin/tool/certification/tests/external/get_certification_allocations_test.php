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
 * Tests for the tool_certification get_certification_allocations external class.
 *
 * @package    tool_certification
 * @covers     \tool_certification\external\get_certification_allocations
 * @author     2022 Odei Alba <odei.alba@moodle.com>
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_certification_allocations_test extends externallib_advanced_testcase {

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
     * Test get_certification_allocations.
     */
    public function test_execute(): void {
        $user = $this->tenantgenerator->create_user();
        $this->setUser($user);
        // Assign required permissions to the user.
        $this->generator->assign_edit_capability($user->id, \context_system::instance());
        $tenantid = tenancy::get_default_tenant_id();
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [, [$user3tenant2]] = $this->tenantgenerator->create_tenant_and_users(1);

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        // Generate certification.
        $cert1params = ['tenantid' => $tenantid, 'fullname' => 'Cert one', 'idnumber' => 'Cert1'];
        $cert2params = ['tenantid' => $sharedspaceid, 'fullname' => 'Cert two', 'idnumber' => 'Cert2'];
        $certification1 = $this->generator->generate_certification($cert1params, true);
        $certification2 = $this->generator->generate_certification($cert2params, true);

        $params = [
            'userid' => $user1->id,
            'certificationid' => $certification1->get('id'),
            'status' => constants::STATUS_OVERRIDE_DEFAULT
        ];
        $user1params = (object) $params;
        $params['userid'] = $user2->id;
        $user2params = (object) $params;
        $params['certificationid'] = $certification2->get('id');
        $user2paramsshared = (object) $params;
        $params['userid'] = $user1->id;
        $user1paramsshared = (object) $params;
        $params['userid'] = $user3tenant2->id;
        $user3paramsshared = (object) $params;

        $now = time();

        // Allocate users to the certification.
        \tool_certification\api::allocate_user($certification1, $user1params);
        \tool_certification\api::set_user_as_certified($user1->id, $certification1->get('id'), null, $now, get_admin()->id);
        \tool_certification\api::allocate_user($certification1, $user2params);
        \tool_certification\api::allocate_user($certification2, $user1paramsshared);
        \tool_certification\api::allocate_user($certification2, $user2paramsshared);
        \tool_certification\api::allocate_user($certification2, $user3paramsshared);
        \tool_certification\api::set_user_as_certified($user2->id, $certification2->get('id'), null, $now, get_admin()->id);

        $response = get_certification_allocations::execute($certification1->get('id'));
        $response = get_certification_allocations::clean_returnvalue(
            get_certification_allocations::execute_returns(),
            $response
        );

        $expecteduserids = [$user1->id, $user2->id];

        $userfullnames = [];
        $responseuserids = [];
        $certfullnames = [];
        $certidnumbers = [];
        foreach ($response as $item) {
            $userfullnames[] = $item['userfullname'];
            $certfullnames[] = $item['certificationfullname'];
            $certidnumbers[] = $item['certificationidnumber'];
            $userid = $item['userid'];
            $responseuserids[] = $userid;
            $completionsresponse = $item['completions'];
            if ($userid == $user1->id) {
                $responsetimecertified = array_column($completionsresponse, 'timecertified');
                $responsecertid = array_column($completionsresponse, 'certificationid');
                $this->assertEqualsCanonicalizing([$now], $responsetimecertified);
                $this->assertEqualsCanonicalizing([$certification1->get('id')], $responsecertid);
            } else if ($userid == $user2->id) {
                $this->assertEmpty($completionsresponse);
            }
        }

        $this->assertEqualsCanonicalizing([fullname($user1), fullname($user2)], $userfullnames);
        $this->assertEqualsCanonicalizing($expecteduserids, $responseuserids);
        $this->assertEquals([$certification1->get('fullname')], array_unique($certfullnames));
        $this->assertEquals([$certification1->get('idnumber')], array_unique($certidnumbers));

        // Get shared certification data.
        $this->setAdminUser();
        $responseshared = get_certification_allocations::execute($certification2->get('id'));
        $responseshared = get_certification_allocations::clean_returnvalue(
            get_certification_allocations::execute_returns(),
            $responseshared
        );

        $responseshareduserids = [];
        foreach ($responseshared as $certusershared) {
            $useridshared = $certusershared['userid'];
            $responseshareduserids[] = $useridshared;
            $completionsresponse = $certusershared['completions'];
            if ($useridshared == $user2->id) {
                $responsetimecertified = array_column($completionsresponse, 'timecertified');
                $responseuserid = array_column($completionsresponse, 'certificationid');
                $this->assertEqualsCanonicalizing([$now], $responsetimecertified);
                $this->assertEqualsCanonicalizing([$certification2->get('id')], $responseuserid);
            } else if ($useridshared == $user1->id) {
                $this->assertEmpty($completionsresponse);
            }
        }

        $this->assertEqualsCanonicalizing($expecteduserids, $responseshareduserids);
        $this->assertNotContains($user3tenant2->id, $responseshareduserids);
    }

    /**
     * Test get_certification_allocations error on no permission to access the tenant.
     */
    public function test_execute_wrong_tenant(): void {
        $user = $this->tenantgenerator->create_user();
        $this->setUser($user);
        $newtenant = $this->tenantgenerator->create_tenant();

        // Assign required permissions to the user.
        $this->generator->assign_allocateuser_capability($user->id, \context_system::instance());

        // Generate certification.
        $certification = $this->generator->generate_certification(['tenantid' => $newtenant->id]);

        $this->expectException(moodle_exception::class);
        $str = get_string('errorcantmanageusers', 'tool_certification');
        $this->expectExceptionMessage($str);

        get_certification_allocations::execute($certification->get('id'));
    }

    /**
     * Test get_certification_allocations error on no permission.
     */
    public function test_execute_no_permissions(): void {
        $user = $this->tenantgenerator->create_user();
        $this->setUser($user);

        // Generate certification.
        $certification1 = $this->generator->generate_certification();

        $this->expectException(moodle_exception::class);
        $str = get_string('errorcantmanageusers', 'tool_certification');
        $this->expectExceptionMessage($str);
        get_certification_allocations::execute($certification1->get('id'));
    }
}
