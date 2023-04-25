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
 * Tests for the tool_certification get_certifications external class.
 *
 * @package    tool_certification
 * @covers     \tool_certification\external\get_certifications
 * @author     2022 Odei Alba <odei.alba@moodle.com>
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class get_certifications_test extends externallib_advanced_testcase {

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
     * Test get_certifications as admin with all permissions.
     */
    public function test_execute_admin(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $this->setAdminUser();
        $tenantid = tenancy::get_default_tenant_id();
        $newtenantid = $tenant->id;
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();

        $params = [
            'tenantid' => $tenantid,
            'startdatetype' => constants::DATE_RELATIVE_TO_ALLOCATION_DATE,
            'startdaterelative' => '0 day'
        ];

        // Generate certification.
        $certification1 = $this->generator->generate_certification($params, true);
        $params['startdaterelative'] = '1 day';
        $certification2 = $this->generator->generate_certification($params, true);
        $params['tenantid'] = $newtenantid;
        $certification3 = $this->generator->generate_certification($params, true);
        $params['tenantid'] = $sharedspaceid;
        $certification4 = $this->generator->generate_certification($params, true);

        $response = get_certifications::execute($tenantid);
        $response = get_certifications::clean_returnvalue(
            get_certifications::execute_returns(),
            $response
        );
        $expectedresult = [$certification1->get('id'), $certification2->get('id'), $certification4->get('id')];
        $actualresult = array_column($response, 'id');
        $this->assertEqualsCanonicalizing($expectedresult, $actualresult);

        $responsedefaulttenant = get_certifications::execute();
        $responsedefaulttenant = get_certifications::clean_returnvalue(
            get_certifications::execute_returns(),
            $responsedefaulttenant
        );
        $actualresultdefaulttenant = array_column($responsedefaulttenant, 'id');
        $this->assertEqualsCanonicalizing($expectedresult, $actualresultdefaulttenant);

        $responsenew = get_certifications::execute($newtenantid);
        $responsenew = get_certifications::clean_returnvalue(
            get_certifications::execute_returns(),
            $responsenew
        );
        $expectedresultnew = [$certification3->get('id'), $certification4->get('id')];
        $actualresultnew = array_column($responsenew, 'id');
        $this->assertEqualsCanonicalizing($expectedresultnew, $actualresultnew);

        $responseshared = get_certifications::execute($sharedspaceid);
        $responseshared = get_certifications::clean_returnvalue(
            get_certifications::execute_returns(),
            $responseshared
        );
        $expectedresultshared = [$certification4->get('id')];
        $actualresultshared = array_column($responseshared, 'id');
        $this->assertEqualsCanonicalizing($expectedresultshared, $actualresultshared);
    }

    /**
     * Test get_certifications all and not archived.
     */
    public function test_execute_archived_parameter(): void {
        $this->setAdminUser();
        $tenantid = tenancy::get_default_tenant_id();

        $params = [
            'tenantid' => $tenantid,
            'startdatetype' => constants::DATE_RELATIVE_TO_ALLOCATION_DATE,
            'startdaterelative' => '0 day'
        ];

        // Generate certification.
        $certification1 = $this->generator->generate_certification($params, true);
        $certification2 = $this->generator->generate_certification($params, true);
        $params['archived'] = 1;
        $certification3 = $this->generator->generate_certification($params, true);
        $certification4 = $this->generator->generate_certification($params, true);

        $response = get_certifications::execute($tenantid);
        $response = get_certifications::clean_returnvalue(
            get_certifications::execute_returns(),
            $response
        );
        $expectedresult = [$certification1->get('id'), $certification2->get('id')];
        $actualresult = array_column($response, 'id');
        $this->assertEqualsCanonicalizing($expectedresult, $actualresult);

        $responseall = get_certifications::execute($tenantid, true);
        $responseall = get_certifications::clean_returnvalue(
            get_certifications::execute_returns(),
            $responseall
        );
        $expectedresultall = [
            $certification1->get('id'),
            $certification2->get('id'),
            $certification3->get('id'),
            $certification4->get('id')
        ];
        $actualresultall = array_column($responseall, 'id');
        $this->assertEqualsCanonicalizing($expectedresultall, $actualresultall);

        $responseactive = get_certifications::execute($tenantid);
        $responseactive = get_certifications::clean_returnvalue(
            get_certifications::execute_returns(),
            $responseactive
        );
        $expectedresultactive = [
            $certification1->get('id'),
            $certification2->get('id')
        ];
        $actualresultactive = array_column($responseactive, 'id');
        $this->assertEqualsCanonicalizing($expectedresultactive, $actualresultactive);
    }

    /**
     * Test get_certifications error on no permission to access the tenant.
     */
    public function test_execute(): void {
        $user = $this->tenantgenerator->create_user();
        $this->setUser($user);
        // Assign required permissions to the user.
        $this->generator->assign_edit_capability($user->id, \context_system::instance());
        $tenantid = tenancy::get_default_tenant_id();
        $tenant = $this->tenantgenerator->create_tenant();
        $newtenantid = $tenant->id;

        $params = [
            'tenantid' => $tenantid,
            'startdatetype' => constants::DATE_RELATIVE_TO_ALLOCATION_DATE,
            'startdaterelative' => '0 day'
        ];

        // Generate certification.
        $certification1 = $this->generator->generate_certification($params, true);
        $params['startdaterelative'] = '1 day';
        $certification2 = $this->generator->generate_certification($params, true);
        $params['tenantid'] = $newtenantid;
        $this->generator->generate_certification($params, true);

        $response = get_certifications::execute($tenantid);
        $response = get_certifications::clean_returnvalue(
            get_certifications::execute_returns(),
            $response
        );
        $expectedresult = [$certification1->get('id'), $certification2->get('id')];
        $actualresult = array_column($response, 'id');
        $this->assertEqualsCanonicalizing($expectedresult, $actualresult);

        $this->expectException(moodle_exception::class);
        $managetenantstr = get_string('tenant:manage', 'tool_tenant');
        $str = get_string('nopermissions', 'error', $managetenantstr);
        $this->expectExceptionMessage($str);

        get_certifications::execute($newtenantid);
    }

    /**
     * Test get_certifications error on no permission.
     */
    public function test_execute_no_permissions(): void {
        $user = $this->tenantgenerator->create_user();
        $this->setUser($user);
        $tenantid = tenancy::get_default_tenant_id();

        $this->expectException(moodle_exception::class);
        $str = get_string('errornopermissionmanagecertifications', 'tool_certification');
        $this->expectExceptionMessage($str);

        get_certifications::execute($tenantid);
    }
}
