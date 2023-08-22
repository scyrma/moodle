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

use context_system;
use external_api;
use externallib_advanced_testcase;
use tool_certification_generator;
use tool_tenant_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the tool_certification external class.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class external_test extends externallib_advanced_testcase {

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

    public function test_archive_certification(): void {
        global $DB;
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $this->setUser($user);
        $this->generator->assign_edit_capability($user->id, context_system::instance());

        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id, 'archived' => 0]);
        $certificationid = $certification->get('id');

        // Certification is not archived.
        $this->assertEquals(0, $DB->get_field('tool_certification', 'archived', ['id' => $certificationid]));

        $result = external::archive_certification($certificationid);
        $this->assertTrue($result['result']);

        // Certification is archived.
        $this->assertEquals(1, $DB->get_field('tool_certification', 'archived', ['id' => $certificationid]));
    }

    public function test_archive_certification_no_permission(): void {
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $this->setUser($user);
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id, 'archived' => 0]);

        $this->expectExceptionMessage('No permission to manage certifications');
        $this->expectException('moodle_exception');
        external::archive_certification($certification->get('id'));
    }

    public function test_restore_certification(): void {
        global $DB;
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $this->setUser($user);
        $this->generator->assign_edit_capability($user->id, context_system::instance());

        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id, 'archived' => 1]);
        $certificationid = $certification->get('id');

        // Certification is archived.
        $this->assertEquals(1, $DB->get_field('tool_certification', 'archived', ['id' => $certificationid]));

        $result = external::restore_certification($certificationid);
        $this->assertTrue($result['result']);

        // Certification is not archived.
        $this->assertEquals(0, $DB->get_field('tool_certification', 'archived', ['id' => $certificationid]));
    }

    public function test_restore_certification_no_permission(): void {
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $this->setUser($user);

        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id, 'archived' => 1]);

        $this->expectExceptionMessage('Can\'t restore certification');
        $this->expectException('moodle_exception');
        external::restore_certification($certification->get('id'));
    }

    public function test_delete_certification(): void {
        global $DB;
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $this->setUser($user);
        $this->generator->assign_edit_capability($user->id, context_system::instance());

        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id, 'archived' => 1]);
        $certificationid = $certification->get('id');

        // Certification exists.
        $record = $DB->record_exists('tool_certification', ['id' => $certificationid]);
        $this->assertTrue($record);

        $result = external::delete_certification($certificationid);
        $this->assertTrue($result['result']);

        // Certification does not exist.
        $record = $DB->record_exists('tool_certification', ['id' => $certificationid]);
        $this->assertFalse($record);
    }

    public function test_delete_certification_no_permission(): void {
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $this->setUser($user);

        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id, 'archived' => 1]);

        $this->expectExceptionMessage('Can\'t delete certification');
        $this->expectException('moodle_exception');
        external::delete_certification($certification->get('id'));
    }

    public function test_deallocate_user(): void {
        global $DB;
        $context = context_system::instance();
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $this->setUser($user);
        $this->generator->assign_allocateuser_capability($user->id, $context);

        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        $params = [
            'certificationid' => $certification->get('id'),
            'userid' => $user->id,
        ];

        $record = $DB->record_exists('tool_certification_users', $params);
        $this->assertFalse($record);

        $this->generator->allocate_user($user->id, $certification->get('id'));

        $record = $DB->record_exists('tool_certification_users', $params);
        $this->assertTrue($record);

        external::deallocate_user($certification->get('id'), $user->id);
        $record = $DB->record_exists('tool_certification_users', $params);
        $this->assertFalse($record);
    }

    public function test_deallocate_user_no_permission(): void {
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $this->setUser($user);

        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        $this->generator->allocate_user($user->id, $certification->get('id'));

        $this->expectExceptionMessage('Can\'t manage users');
        $this->expectException('moodle_exception');
        external::deallocate_user($certification->get('id'), $user->id);
    }

    public function test_certify_user(): void {
        global $DB;
        $context = context_system::instance();
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $this->setUser($user);
        $this->generator->assign_allocateuser_capability($user->id, $context);

        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        $params = [
            'certificationid' => $certification->get('id'),
            'userid' => $user->id,
        ];

        $record = $DB->record_exists('tool_certification_users', $params);
        $this->assertFalse($record);

        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        api::allocate_user($certification, $userdata);

        $record = $DB->record_exists('tool_certification_compltion', $params);
        $this->assertFalse($record);

        $result = external::certify_user($user->id,  $certification->get('id'), false);
        $this->assertTrue($result['result']);

        $record = $DB->record_exists('tool_certification_compltion', $params);
        $this->assertTrue($record);
    }

    public function test_certify_user_no_permission(): void {
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $this->setUser($user);

        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        api::allocate_user($certification, $userdata);

        $this->expectExceptionMessage('This user can\'t be marked as certified because either they are already marked' .
            ' as certified or you don\'t have permission to perform this action');
        $this->expectException('moodle_exception');
        external::certify_user($user->id,  $certification->get('id'), false);
    }

    public function test_get_certification_user_log(): void {
        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);
        $this->generator->assign_allocateuser_capability($user1->id, context_system::instance());
        $this->setUser($user1);

        $params = [
            'tenantid' => $tenant->id,
            'expirydatetype' => constants::DATE_NEVER,
            'duedatetype' => constants::DATE_AFTER_START_DATE,
            'duedaterelative' => '1 day',
            'startdatetype' => constants::DATE_RELATIVE_TO_ALLOCATION_DATE,
            'startdaterelative' => '0 day'
        ];
        $now = time();
        // Generate certification.
        $certification = $this->generator->generate_certification($params, true);
        $certificationid = $certification->get('id');
        // Allocate user.
        $certificationuser = $this->generator->allocate_user($user2->id, $certificationid);
        // Certify.
        api::set_user_as_certified($user2->id, $certificationid, null, $now, get_admin()->id);
        // Revoke.
        api::revoke_certification_from_user($user2->id, $certificationid);

        $response = external::get_certification_user_log($certificationid, $user2->id);
        $response = external::clean_returnvalue(
            external::get_certification_user_log_returns(),
            $response
        );

        $certificationcompletion = certification_completion::get_record([
            'certificationid' => $certificationid,
            'userid' => $user2->id,
        ]);

        $this->assertCount(2, $response['log']);
        $this->assertEquals('Manually certified ' . fullname($user2) . ' (Never expires)', $response['log'][0]['event']);
        $this->assertEquals(get_admin()->id, $response['log'][0]['user']['id']);
        $this->assertEquals($certificationcompletion->get('timecertified'), $response['log'][0]['date']);
        $this->assertEquals(fullname(get_admin()), $response['log'][0]['user']['fullname']);
        $this->assertNotEmpty($response['log'][0]['user']['picture']);
        $profileurl = (new \moodle_url('/user/profile.php', array('id' => get_admin()->id)))->out(false);
        $this->assertEquals($profileurl, $response['log'][0]['user']['profileurl']);

        $this->assertEquals($certificationuser->get('timecreated'), $response['lastallocationdate']);
        $this->assertNotEmpty($response['downloadform']);
    }

    public function test_get_certification_user_log_no_permission(): void {
        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);
        $this->setUser($user1);

        $params = [
            'tenantid' => $tenant->id,
            'expirydatetype' => constants::DATE_NEVER,
            'duedatetype' => constants::DATE_AFTER_START_DATE,
            'duedaterelative' => '1 day',
            'startdatetype' => constants::DATE_RELATIVE_TO_ALLOCATION_DATE,
            'startdaterelative' => '0 day'
        ];
        $now = time();
        // Generate certification.
        $certification = $this->generator->generate_certification($params, true);
        $certificationid = $certification->get('id');
        // Allocate user.
        $this->generator->allocate_user($user2->id, $certificationid);
        // Certify.
        api::set_user_as_certified($user2->id, $certificationid, null, $now, get_admin()->id);
        // Revoke.
        api::revoke_certification_from_user($user2->id, $certificationid);

        $this->expectExceptionMessage('No permission to view reports');
        $this->expectException('moodle_exception');
        external::get_certification_user_log($certificationid, $user2->id);
    }

    public function test_bulk_deallocate_user(): void {
        global $DB;
        [$tenant, [$user0, $user1, $user2, $user3, $user4, $user5, $user6]] = $this->tenantgenerator->create_tenant_and_users(7);
        $this->generator->assign_allocateuser_capability($user0->id, context_system::instance());
        $this->setUser($user0);
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        $certificationusers = $this->generator->allocate_users_to_certification($certification->get('id'),
            [$user1->id, $user2->id, $user3->id, $user4->id, $user5->id, $user6->id]);

        $this->assertEquals(6, certification_user::count_records());

        // Set allocation type dynamic for user6.
        $DB->set_field('tool_certification_users', 'allocationtype', constants::ALLOCATION_DYNAMIC,
            ['userid' => $user6->id, 'certificationid' => $certification->get('id')]);

        $result = external::bulk_deallocate_user([
            $certificationusers[$user1->id]->get('id'),
            $certificationusers[$user3->id]->get('id'),
            $certificationusers[$user4->id]->get('id'),
            $certificationusers[$user6->id]->get('id'),
        ]);
        $cleanresult = external_api::clean_returnvalue(external::bulk_deallocate_user_returns(), $result);

        $this->assertEquals(3, $cleanresult['successcount']);
        // User6 with dynamic allocation cannot be de-allocated.
        $this->assertEquals(1, $cleanresult['skippedcount']);

        $allocateduserids = array_map(static function($cuser) {
            return $cuser->get('userid');
        }, certification_user::get_records());
        $this->assertEqualsCanonicalizing([$user2->id, $user5->id, $user6->id], $allocateduserids);

        // Set a current user with no permission to deallocate.
        $this->setUser($user1);
        $result = external::bulk_deallocate_user([
            $certificationusers[$user2->id]->get('id'),
            $certificationusers[$user5->id]->get('id'),
        ]);
        $cleanresult = external_api::clean_returnvalue(external::bulk_deallocate_user_returns(), $result);

        $this->assertEquals(0, $cleanresult['successcount']);
        $this->assertEquals(2, $cleanresult['skippedcount']);

        $allocateduserids = array_map(static function($cuser) {
            return $cuser->get('userid');
        }, certification_user::get_records());
        $this->assertEqualsCanonicalizing([$user2->id, $user5->id, $user6->id], $allocateduserids);
    }
}
