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
 * Tests for certification class.
 *
 * @package   tool_certification
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_certification\api;
use tool_certification\certification;
use tool_certification\certification_user;
use tool_certification\constants;
use tool_program\persistent\program;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Certification tests.
 *
 * @package    tool_certification
 * @copyright  2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_certification_testcase extends advanced_testcase {
    /**
     * @var tool_certification_generator
     */
    public $generator;

    /**
     * setUp.
     */
    public function setUp() {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
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

        $isarchived = $certification->is_archived();
        $this->assertFalse($isarchived);

        api::archive_certification($certificationid);
        $certification = new certification($certificationid);
        $isarchived = $certification->is_archived();
        $this->assertTrue($isarchived);
    }

    public function test_get_certification_program(): void {
        $certification = $this->generator->generate_certification();

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
            'certificationid' => $certification->get('id')
        ];
        $cu = new certification_user(0, $data);
        $cu->create();

        $certusers = certification_user::get_records(['certificationid' => $certification->get('id')]);
        $this->assertCount(1, $certusers);

        $certusers = $certification->get_certification_users();
        $this->assertNotEmpty($certusers);

        $data = (object) [
            'userid' => $user2->id,
            'certificationid' => $certification->get('id')
        ];
        $cu = new certification_user(0, $data);
        $cu->create();

        $certusers = certification_user::get_records(['certificationid' => $certification->get('id')]);
        $this->assertCount(2, $certusers);

        $certusers = $certification->get_certification_users();
        $this->assertCount(2, $certusers);
        $this->assertInstanceOf(certification_user::class, $certusers[0]);
        $this->assertInstanceOf(certification_user::class, $certusers[1]);
        $this->assertEquals($user->id, $certusers[0]->get('userid'));
    }

    public function test_get_active_certifications_by_userid(): void {
        $certification = $this->generator->generate_certification();
        // We generate default tenant and user.
        // We create default tenant.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        // Create one user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenantgenerator->allocate_user($user->id, $defaulttenantid);

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
            'status' => 1
        ];
        $certuser = api::allocate_user($certification, $data);
        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OPEN, $user->id);
        $this->assertEmpty($activecerts);

        $certuser->set('startdate', $onedayless);
        $certuser->update();
        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OPEN, $user->id);
        $this->assertNotEmpty($activecerts);
        $this->assertCount(1, $activecerts);

        $certuser->set('duedate', $onedayless);
        $certuser->update();
        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OPEN, $user->id);
        $this->assertEmpty($activecerts);

        $certuser->set('duedate', $onedaymore);
        $certuser->update();
        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OPEN, $user->id);
        $this->assertNotEmpty($activecerts);
        $this->assertCount(1, $activecerts);
        $this->assertArrayHasKey($certification->get('id'), $activecerts);

        $this->generator->complete_certification($certification, $user->id);

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
        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenantgenerator->allocate_user($user->id, $defaulttenantid);

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
            'status' => 1
        ];
        $certuser = api::allocate_user($certification, $data);
        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OVERDUE, $user->id);
        $this->assertEmpty($activecerts);

        $certuser->set('startdate', $twodayless);
        $certuser->set('duedate', $onedayless);
        $certuser->update();
        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OVERDUE, $user->id);
        $this->assertNotEmpty($activecerts);
        $this->assertCount(1, $activecerts);

        $certuser->set('startdate', $onedaymore);
        $certuser->update();
        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OVERDUE, $user->id);
        $this->assertEmpty($activecerts);

        $certuser->set('startdate', $twodayless);
        $certuser->update();
        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_OVERDUE, $user->id);
        $this->assertNotEmpty($activecerts);
        $this->assertCount(1, $activecerts);
        $this->assertArrayHasKey($certification->get('id'), $activecerts);

        $this->generator->complete_certification($certification, $user->id);

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
        /** @var tool_tenant_generator $tenantgenerator */
        $tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenantgenerator->allocate_user($user->id, $defaulttenantid);

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
            'status' => 1
        ];
        api::allocate_user($certification, $data);
        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_EXPIRED, $user->id);
        $this->assertEmpty($activecerts);

        $certcompletion = $this->generator->complete_certification($certification, $user->id);

        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_EXPIRED, $user->id);
        $this->assertEmpty($activecerts);

        $certcompletion->set('expirydate', $onedayless);
        $certcompletion->update();

        $activecerts = api::get_certifications_by_status_and_userid(constants::STATUS_EXPIRED, $user->id);
        $this->assertNotEmpty($activecerts);
        $this->assertCount(1, $activecerts);
        $this->assertArrayHasKey($certification->get('id'), $activecerts);
    }
}
