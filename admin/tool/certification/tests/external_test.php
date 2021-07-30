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
 * Tests for the tool_certification external class.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_certification\api;
use tool_certification\certification_completion;
use tool_certification\constants;

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
class tool_certification_external_testcase extends externallib_advanced_testcase {

    /** @var tool_certification_generator */
    protected $generator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->resetAfterTest();
    }

    public function test_archive_certification() {
        global $DB;
        // We generate default tenant and user.
        $data = $this->generator->create_tenant_and_user();
        $this->setUser($data->user);
        $this->generator->assign_edit_capability($data->user->id, context_system::instance());

        $certification = $this->generator->generate_certification(['tenantid' => $data->defaulttenantid, 'archived' => 0]);
        $certificationid = $certification->get('id');

        // Certification is not archived.
        $record = $DB->get_record('tool_certification', ['id' => $certificationid]);
        $this->assertEquals(0, $record->archived);

        \tool_certification\external::archive_certification($certificationid);

        // Certification is archived.
        $record = $DB->get_record('tool_certification', ['id' => $certificationid]);
        $this->assertEquals(1, $record->archived);
    }

    public function test_restore_certification() {
        global $DB;
        // We generate default tenant and user.
        $data = $this->generator->create_tenant_and_user();
        $this->setUser($data->user);
        $this->generator->assign_edit_capability($data->user->id, context_system::instance());

        $certification = $this->generator->generate_certification(['tenantid' => $data->defaulttenantid, 'archived' => 1]);
        $certificationid = $certification->get('id');

        // Certification is archived.
        $record = $DB->get_record('tool_certification', ['id' => $certificationid]);
        $this->assertEquals(1, $record->archived);

        \tool_certification\external::restore_certification($certificationid);

        // Certification is not archived.
        $record = $DB->get_record('tool_certification', ['id' => $certificationid]);
        $this->assertEquals(0, $record->archived);
    }

    public function test_delete_certification() {
        global $DB;
        // We generate default tenant and user.
        $data = $this->generator->create_tenant_and_user();
        $this->setUser($data->user);
        $this->generator->assign_edit_capability($data->user->id, context_system::instance());

        $certification = $this->generator->generate_certification(['tenantid' => $data->defaulttenantid, 'archived' => 1]);
        $certificationid = $certification->get('id');

        // Certification exists.
        $record = $DB->record_exists('tool_certification', ['id' => $certificationid]);
        $this->assertTrue($record);

        \tool_certification\external::delete_certification($certificationid);

        // Certification does not exist.
        $record = $DB->record_exists('tool_certification', ['id' => $certificationid]);
        $this->assertFalse($record);
    }

    public function test_deallocate_user() {
        global $DB;
        $context = context_system::instance();
        // We generate default tenant and user.
        $data = $this->generator->create_tenant_and_user();
        $this->setUser($data->user);
        $this->generator->assign_allocateuser_capability($data->user->id, $context);
        $this->assertEquals($data->defaulttenantid, \tool_tenant\tenancy::get_tenant_id());

        $certification = $this->generator->generate_certification(['tenantid' => $data->defaulttenantid]);

        $params = [
            'certificationid' => $certification->get('id'),
            'userid' => $data->user->id,
        ];

        $record = $DB->record_exists('tool_certification_users', $params);
        $this->assertFalse($record);

        $certuser = $this->generator->allocate_user($data->user->id, $certification->get('id'));

        $record = $DB->record_exists('tool_certification_users', $params);
        $this->assertTrue($record);
        $this->assertInstanceOf(\tool_certification\certification_user::class, $certuser);

        \tool_certification\external::deallocate_user($certification->get('id'), $data->user->id);
        $record = $DB->record_exists('tool_certification_users', $params);
        $this->assertFalse($record);
    }

    public function test_certify_user() {
        global $DB;
        $context = context_system::instance();
        // We generate default tenant and user.
        $data = $this->generator->create_tenant_and_user();
        $this->setUser($data->user);
        $this->generator->assign_allocateuser_capability($data->user->id, $context);
        $this->assertEquals($data->defaulttenantid, \tool_tenant\tenancy::get_tenant_id());

        $certification = $this->generator->generate_certification(['tenantid' => $data->defaulttenantid]);

        $params = [
            'certificationid' => $certification->get('id'),
            'userid' => $data->user->id,
        ];

        $record = $DB->record_exists('tool_certification_users', $params);
        $this->assertFalse($record);

        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $data->user->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        $certificationuser = api::allocate_user($certification, $userdata);

        $record = $DB->record_exists('tool_certification_compltion', $params);
        $this->assertFalse($record);

        \tool_certification\external::certify_user($data->user->id,  $certification->get('id'), false);

        $record = $DB->record_exists('tool_certification_compltion', $params);
        $this->assertTrue($record);
    }

    public function test_get_certification_user_log() {
        $data = $this->generator->create_tenant_and_user();
        $this->setUser($data->user);

        $params = [
            'expirydatetype' => \tool_certification\constants::DATE_NEVER,
            'duedatetype' => \tool_certification\constants::DATE_AFTER_START_DATE,
            'duedaterelative' => '1 day',
            'startdatetype' => \tool_certification\constants::DATE_RELATIVE_TO_ALLOCATION_DATE,
            'startdaterelative' => '0 day'
        ];
        $now = time();
        // Generate certification.
        $certification = $this->generator->generate_certification($params, true);
        $certificationid = $certification->get('id');
        $user = self::getDataGenerator()->create_user();
        // Allocate user.
        $certificationuser = $this->generator->allocate_user($user->id, $certificationid);
        // Certify.
        \tool_certification\api::set_user_as_certified($user->id, $certificationid, null, $now, get_admin()->id);
        // Revoke.
        \tool_certification\api::revoke_certification_from_user($user->id, $certificationid);

        $response = \tool_certification\external::get_certification_user_log($certificationid, $user->id);
        $response = \tool_certification\external::clean_returnvalue(
            \tool_certification\external::get_certification_user_log_returns(),
            $response
        );

        $certificationcompletion = certification_completion::get_record([
            'certificationid' => $certificationid,
            'userid' => $user->id,
        ]);

        $this->assertCount(2, $response['log']);
        $this->assertEquals('Manually certified ' . fullname($user) . ' (Never expires)', $response['log'][0]['event']);
        $this->assertEquals(get_admin()->id, $response['log'][0]['user']['id']);
        $this->assertEquals($certificationcompletion->get('timecertified'), $response['log'][0]['date']);
        $this->assertEquals(fullname(get_admin()), $response['log'][0]['user']['fullname']);
        $this->assertNotEmpty($response['log'][0]['user']['picture']);
        $profileurl = (new \moodle_url('/user/profile.php', array('id' => get_admin()->id)))->out(false);
        $this->assertEquals($profileurl, $response['log'][0]['user']['profileurl']);

        $this->assertEquals($certificationuser->get('timecreated'), $response['lastallocationdate']);
        $this->assertNotEmpty($response['downloadform']);
    }

    public function test_bulk_deallocate_user(): void {
        $data = $this->generator->create_tenant_and_user();
        $this->setUser($data->user);
        $certification = $this->generator->generate_certification(['tenantid' => $data->defaulttenantid]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();
        $user5 = $this->getDataGenerator()->create_user();
        $certificationusers = $this->generator->allocate_users_to_certification($certification->get('id'),
            [$user1->id, $user2->id, $user3->id, $user4->id, $user5->id]);

        $this->assertEquals(5, \tool_certification\certification_user::count_records());

        $this->setAdminUser();
        $result = \tool_certification\external::bulk_deallocate_user([
            $certificationusers[$user1->id]->get('id'),
            $certificationusers[$user3->id]->get('id'),
            $certificationusers[$user4->id]->get('id'),
        ]);
        $cleanresult = external_api::clean_returnvalue(\tool_certification\external::bulk_deallocate_user_returns(), $result);

        $this->assertEquals(3, $cleanresult['successcount']);
        $this->assertEquals(0, $cleanresult['skippedcount']);

        $allocateduserids = array_map(static function($cuser) {
            return $cuser->get('userid');
        }, \tool_certification\certification_user::get_records());
        $this->assertEqualsCanonicalizing([$user2->id, $user5->id], $allocateduserids);

        // Set a current user with no permission to deallocate.
        $this->setUser($user1);
        $result = \tool_certification\external::bulk_deallocate_user([
            $certificationusers[$user2->id]->get('id'),
            $certificationusers[$user5->id]->get('id'),
        ]);
        $cleanresult = external_api::clean_returnvalue(\tool_certification\external::bulk_deallocate_user_returns(), $result);

        $this->assertEquals(0, $cleanresult['successcount']);
        $this->assertEquals(2, $cleanresult['skippedcount']);

        $allocateduserids = array_map(static function($cuser) {
            return $cuser->get('userid');
        }, \tool_certification\certification_user::get_records());
        $this->assertEqualsCanonicalizing([$user2->id, $user5->id], $allocateduserids);
    }
}
