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

namespace tool_certification;

use advanced_testcase;
use coding_exception;
use moodle_exception;
use tool_certification_generator;
use tool_program_generator;
use tool_tenant_generator;
use tool_program\persistent\program;
use tool_program\persistent\program_user;

/**
 * Tests for certification persistent
 *
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_test extends advanced_testcase {
    /** @var tool_certification_generator */
    protected $generator;
    /** @var tool_program_generator */
    protected $programgenerator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    /**
     * Test for is_archived function.
     *
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function test_is_archived(): void {
        self::setAdminUser();
        $certificationdata = $this->generator->get_dummy_certificationdata();

        $certification = api::create_certification($certificationdata);
        $certificationid = $certification->get('id');
        $this->assertNotEmpty($certificationid);

        $this->assertFalse($certification->is_archived());

        api::archive_certification($certificationid);
        $certification = new certification($certificationid);
        $this->assertTrue($certification->is_archived());
    }

    public function test_get_certification_program(): void {
        $program = $this->programgenerator->generate_program((object)['idnumber' => '14', 'fullname' => 'A program fullname']);
        $certification = $this->generator->generate_certification(['program' => $program->get('id')]);

        $program = $certification->get_certification_program();
        $this->assertInstanceOf(program::class, $program);
        $this->assertEquals('14', $program->get('idnumber'));
        $this->assertEquals('A program fullname', $program->get('fullname'));
    }

    public function test_get_certification_users(): void {
        $user = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        $certification = $this->generator->generate_certification();
        $certusers = $certification->get_certification_users();
        $this->assertEmpty($certusers);

        $data = (object) [
            'userid' => $user->id,
            'certificationid' => $certification->get('id'),
        ];
        $cu = new certification_user(0, $data);
        $cu->create();

        $certusers = $certification->get_certification_users();
        $this->assertCount(1, $certusers);
        $this->assertContainsOnlyInstancesOf(certification_user::class, $certusers);
        $this->assertEquals($user->id, $certusers[0]->get('userid'));

        $data = (object) [
            'userid' => $user2->id,
            'certificationid' => $certification->get('id'),
        ];
        $cu = new certification_user(0, $data);
        $cu->create();

        $certusers = $certification->get_certification_users();
        $this->assertCount(2, $certusers);
        $this->assertContainsOnlyInstancesOf(certification_user::class, $certusers);
        $this->assertEqualsCanonicalizing([$user->id, $user2->id], [$certusers[0]->get('userid'), $certusers[1]->get('userid')]);
    }

    public function test_get_active_certifications_by_userid(): void {
        $certification = $this->generator->generate_certification();
        // We generate default tenant and user.
        // We create default tenant.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        // Create one user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->tenantgenerator->allocate_user($user->id, $defaulttenantid);

        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OPEN, $user->id);
        $this->assertEmpty($activecerts);

        $certification->set('tenantid', $defaulttenantid);
        $certification->update();

        $onedayless = strtotime(' -1 day');
        $onedaymore = strtotime(' +1 day');
        $twodaysmore = strtotime(' +2 day');

        $data = (object) [
            'userid' => $user->id,
            'certificationid' => $certification->get('id'),
            'startdate' => $onedaymore,
            'startdatelocked' => 1,
            'duedate' => $twodaysmore,
            'duedatelocked' => 1,
            'status' => 1,
        ];
        $certuser = api::allocate_user($certification, $data);
        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OPEN, $user->id);
        $this->assertEmpty($activecerts);

        $programuser = program_user::get_record(['certificationid' => $certification->get('id'), 'userid' => $user->id]);

        $programuser->set('startdate', $onedayless);
        $programuser->set('startdatelocked', 1);
        $programuser->set('duedate', $twodaysmore);
        $programuser->set('duedatelocked', 1);
        $programuser->update();

        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OPEN, $user->id);
        $this->assertCount(1, $activecerts);

        $programuser->set('duedate', $onedayless);
        $programuser->update();
        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OPEN, $user->id);
        $this->assertEmpty($activecerts);

        $programuser->set('duedate', $onedaymore);
        $programuser->update();
        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OPEN, $user->id);
        $this->assertCount(1, $activecerts);
        $this->assertArrayHasKey($certification->get('id'), $activecerts);

        api::set_user_as_certified($user->id, $certification->get('id'));

        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OPEN, $user->id);
        $this->assertEmpty($activecerts);
    }

    public function test_get_overdue_certifications_by_userid(): void {
        $certification = $this->generator->generate_certification();
        // We generate default tenant and user.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        // Create one user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->tenantgenerator->allocate_user($user->id, $defaulttenantid);

        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OVERDUE, $user->id);
        $this->assertEmpty($activecerts);

        $certification->set('tenantid', $defaulttenantid);
        $certification->update();

        $twodayless = strtotime(' -2 day');
        $onedayless = strtotime(' -1 day');
        $onedaymore = strtotime(' +1 day');

        $data = (object) [
            'userid' => $user->id,
            'certificationid' => $certification->get('id'),
            'startdate' => $onedaymore,
            'startdatelocked' => 1,
            'duedate' => $onedaymore,
            'duedatelocked' => 1,
            'status' => 1,
        ];
        $certuser = api::allocate_user($certification, $data);
        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OVERDUE, $user->id);
        $this->assertEmpty($activecerts);

        $programuser = program_user::get_record(['certificationid' => $certification->get('id'), 'userid' => $user->id]);

        $programuser->set('startdate', $twodayless);
        $programuser->set('duedate', $onedayless);
        $programuser->update();
        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OVERDUE, $user->id);
        $this->assertCount(1, $activecerts);

        $programuser->set('startdate', $onedaymore);
        $programuser->update();
        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OVERDUE, $user->id);
        $this->assertEmpty($activecerts);

        $programuser->set('startdate', $twodayless);
        $programuser->update();
        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OVERDUE, $user->id);
        $this->assertCount(1, $activecerts);
        $this->assertArrayHasKey($certification->get('id'), $activecerts);

        api::set_user_as_certified($user->id, $certification->get('id'));

        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OVERDUE, $user->id);
        $this->assertEmpty($activecerts);
    }

    public function test_get_expired_certifications_by_userid(): void {
        $certification = $this->generator->generate_certification();
        // We generate default tenant and user.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        // Create one user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->tenantgenerator->allocate_user($user->id, $defaulttenantid);

        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_EXPIRED, $user->id);
        $this->assertEmpty($activecerts);

        $certification->set('tenantid', $defaulttenantid);
        $certification->update();

        $twodayless = strtotime(' -2 day');
        $onedayless = strtotime(' -1 day');
        $onemonthmore = strtotime(' +1 month');

        $data = (object) [
            'userid' => $user->id,
            'certificationid' => $certification->get('id'),
            'startdate' => $twodayless,
            'startdatelocked' => 1,
            'duedate' => $onedayless,
            'duedatelocked' => 1,
            'expirydate' => $onemonthmore,
            'expirydatelocked' => 1,
            'status' => 1,
        ];
        api::allocate_user($certification, $data);
        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_EXPIRED, $user->id);
        $this->assertEmpty($activecerts);

        api::set_user_as_certified($user->id, $certification->get('id'));
        $certparams = ['userid' => $user->id, 'certificationid' => $certification->get('id')];
        $certcompletion = certification_completion::get_record($certparams);

        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_EXPIRED, $user->id);
        $this->assertEmpty($activecerts);

        $certcompletion->set('expirydate', $onedayless);
        $certcompletion->update();

        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_EXPIRED, $user->id);
        $this->assertCount(1, $activecerts);
        $this->assertArrayHasKey($certification->get('id'), $activecerts);
    }
}
