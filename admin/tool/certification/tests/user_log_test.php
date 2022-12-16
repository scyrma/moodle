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
use tool_certification_generator;
use tool_program_generator;

/**
 * Class tool_certification/user_log tests
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @covers     \tool_certification\user_log
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_log_test extends advanced_testcase {
    /** @var tool_certification_generator */
    protected $generator;
    /** @var tool_program_generator */
    protected $programgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->resetAfterTest();
    }

    /**
     * Test that logs shows all information in order
     */
    public function test_get_user_log(): void {
        global $DB;
        $params = [
            'expirydatetype' => constants::DATE_NEVER,
            'duedatetype' => constants::DATE_AFTER_START_DATE,
            'duedaterelative' => '1 day',
            'startdatetype' => constants::DATE_RELATIVE_TO_ALLOCATION_DATE,
            'startdaterelative' => '0 day',
        ];
        $now = time();

        $certification = $this->generator->generate_certification($params, true);
        $certificationid = $certification->get('id');
        $user = self::getDataGenerator()->create_user();
        $certificationuser = $this->generator->allocate_user($user->id, $certificationid);

        // Check that log only contains user allocation message.
        $userlog = new user_log($user->id, $certification->get('id'));
        $log = $userlog->get_user_log();
        $this->assertCount(0, $log['log']);
        $this->assertEquals($certificationuser->get('timecreated'), $log['lastallocationdate']);

        // Certify user.
        api::set_user_as_certified($user->id, $certificationid, null, $now, 2);
        $userlog = new user_log($user->id, $certification->get('id'));
        $log = $userlog->get_user_log();
        $this->assertCount(1, $log['log']);
        $this->assertEquals('Manually certified '.fullname($user).' (Never expires)', $log['log'][0]['event']);
        $this->assertEquals(2, $log['log'][0]['user']['id']);
        $this->assertEquals($now, $log['log'][0]['date']);

        // Revoke certification.
        api::revoke_certification_from_user($user->id, $certificationid);
        $log = $userlog->get_user_log();
        $this->assertCount(2, $log['log']);
        $this->assertEquals('Revoked '.fullname($user).'\'s certification', $log['log'][1]['event']);
        $this->assertEquals(0, $log['log'][1]['user']['id']);
        $record = $DB->get_record('tool_certification_compltion', ['userid' => $user->id, 'certificationid' => $certificationid]);
        $this->assertEquals($record->timerevoked, $log['log'][1]['date']);

        // Certify user twice.
        $expirytime1 = strtotime('+1 days');
        $expirytime2 = strtotime('+2 days');
        api::set_user_as_certified($user->id, $certificationid, $expirytime1, $now, 2);
        api::allocate_recertification_users();
        api::set_user_as_certified($user->id, $certificationid, $expirytime2, $now, 2);

        // Relying on auto-increment for certification_compltion ID.
        $DB->update_record('tool_certification_compltion', (object)['id' => $record->id + 1, 'timecertified' => $now + 1600]);
        $DB->update_record('tool_certification_compltion', (object)['id' => $record->id + 2, 'timecertified' => $now + 3600]);

        $log = $userlog->get_user_log();
        $this->assertCount(4, $log['log']);
        $expirydate = userdate($expirytime1, get_string('strftimedatefullshort', 'langconfig'));
        $this->assertEquals('Manually certified '.fullname($user).' (expires on '.$expirydate.')', $log['log'][2]['event']);
        $expirydate = userdate($expirytime2, get_string('strftimedatefullshort', 'langconfig'));
        $this->assertEquals('Manually certified '.fullname($user).' (expires on '.$expirydate.')', $log['log'][3]['event']);

        // Test certification expired.
        $expirydate2 = strtotime('-1 days');
        $DB->update_record('tool_certification_compltion', (object)['id' => $record->id + 2, 'expirydate' => $expirydate2]);
        $log = $userlog->get_user_log();
        $this->assertCount(5, $log['log']);

        // Test user suspended.
        $certificationuser->set('timesuspended', time());
        $certificationuser->update();
        $log = $userlog->get_user_log();
        $this->assertCount(6, $log['log']);
    }

    /**
     * Test expiry dates are shown correctly in the log
     */
    public function test_get_user_log_expiry_dates(): void {
        $params = [
            'duedatetype' => constants::DATE_AFTER_START_DATE,
            'duedaterelative' => '1 day',
            'startdatetype' => constants::DATE_RELATIVE_TO_ALLOCATION_DATE,
            'startdaterelative' => '0 day',
        ];
        $time = strtotime("-1 year");

        $certification = $this->generator->generate_certification($params, true);
        $certificationid = $certification->get('id');
        $user = self::getDataGenerator()->create_user();
        $fullname = fullname($user);
        $this->generator->allocate_user($user->id, $certificationid);

        // Log should show "Certified" one year ago.
        $expirydate1 = $time + 6 * DAYSECS;
        api::set_user_as_certified($user->id, $certificationid, $expirydate1, $time, 2);
        api::allocate_recertification_users();

        // Log should show "Certified" again but no "expired" in between because user was certified before certification expired.
        $expirydate2 = $time + 3 * DAYSECS;
        api::set_user_as_certified($user->id, $certificationid, $expirydate2, $time + DAYSECS, 2);
        api::allocate_recertification_users();

        // Log should show "Expired" and then "Certified" again because user was certified after the certification expired.
        $expirydate3 = $time + 8 * DAYSECS;
        api::set_user_as_certified($user->id, $certificationid, $expirydate3, $time + 5 * DAYSECS, 2);
        api::allocate_recertification_users();

        // Log should show "Certified" again but no "expired" in between because user was certified before certification expired.
        $expirydate4 = $time + 10 * DAYSECS;
        api::set_user_as_certified($user->id, $certificationid, $expirydate4, $time + 7 * DAYSECS, 2);

        $userlog = new user_log($user->id, $certification->get('id'));
        $log = $userlog->get_user_log();

        // We should get 6 log records.
        $this->assertCount(6, $log['log']);

        // Log should show "Certified" one year ago.
        $expirydate = userdate($expirydate1, get_string('strftimedatefullshort', 'langconfig'));
        $this->assertEquals("Manually certified $fullname (expires on $expirydate)", $log['log'][0]['event']);

        // Log should show "Certified" again but no "expired" in between because user was certified before certification expired.
        $expirydate = userdate($expirydate2, get_string('strftimedatefullshort', 'langconfig'));
        $this->assertEquals("Manually certified $fullname (expires on $expirydate)", $log['log'][1]['event']);

        // Log should show "Expired" and then "Certified" again because user was certified after the certification expired.
        $this->assertEquals("Certification expired", $log['log'][2]['event']);
        $this->assertEquals($expirydate2, $log['log'][2]['date']);

        // Log should show "Certified" again but no "expired" in between because user was certified before certification expired.
        $expirydate = userdate($expirydate3, get_string('strftimedatefullshort', 'langconfig'));
        $this->assertEquals("Manually certified $fullname (expires on $expirydate)", $log['log'][3]['event']);

        // Log should show "Certified" again but no "expired" in between because user was certified before certification expired.
        $expirydate = userdate($expirydate4, get_string('strftimedatefullshort', 'langconfig'));
        $this->assertEquals("Manually certified $fullname (expires on $expirydate)", $log['log'][4]['event']);

        // Log should show the last expired.
        $this->assertEquals("Certification expired", $log['log'][5]['event']);
        $this->assertEquals($expirydate4, $log['log'][5]['date']);
    }

    /**
     * Test that initial certification dates are shown correctly in the log after grace period reached.
     */
    public function test_get_user_log_after_restore_initial_certification(): void {
        // Skipping this test if 'uopz' extension is not loaded.
        if (!extension_loaded('uopz')) {
            // Leave here if uopz is not loaded, as remaining test scenario depends on it.
            $this->markTestSkipped('This test requires uopz php extension.');
        }

        // Set configuration for certification that require recertification.
        $params = [
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '2 day',
            'duedaterelative' => '1 day',
            'duedatetype' => constants::DATE_AFTER_START_DATE,
            'recertstartdaterelative' => '1 day',
            'recertexpirydatetype' => constants::RECERT_EXPIRY_DATE_AFTR_PREV_COMPL,
            'recertexpirydaterelative' => '2 day',
            'recertgraceperiod' => '1 day',
        ];

        $certification = $this->generator->generate_certification($params, true);
        $user = self::getDataGenerator()->create_user();
        $this->generator->allocate_user($user->id, $certification->get('id'));

        // Check that user is allocated into initial program.
        $recordprogramuserinitial = (api::get_latest_programuser_allocation($user->id, $certification->get('id')))->to_record();
        $this->assertEquals($certification->get('program'), $recordprogramuserinitial->programid);

        // Certify user manually, current datetime.
        api::set_user_as_certified($user->id, $certification->get('id'));
        $this->assertTrue(api::is_user_certified($user->id, $certification->get('id')));

        // Set datetime to +1 day from the certification allocation to force the start of recertification period. We will add
        // a few seconds (MINSECS) more because in order that allocate_recertification_users query works well we need this time
        // to be higher than the time set in recertstartdaterelative (1 day).
        uopz_set_return('time', $recordprogramuserinitial->timecreated + DAYSECS + MINSECS);

        // Allocate user into recertification program.
        api::allocate_recertification_users();

        // Check that user is allocated into the recertification program.
        $recordprogramuser = api::get_latest_programuser_allocation($user->id, $certification->get('id'));
        $this->assertEquals($certification->get('recertificationprogram'), $recordprogramuser->get('programid'));

        // Get certification user log.
        $userlog = new user_log($user->id, $certification->get('id'));
        $log = $userlog->get_user_log();

        // We should get 2 log records.
        $this->assertCount(2, $log['log']);

        // Log should show the initial program completed and date as user was set as certified.
        $lastcompletion = api::get_last_completion_record($user->id, $certification->get('id'));
        $progamname = $recordprogramuser->get_program()->get_formatted_name();
        $this->assertEquals("Completed the program $progamname", $log['log'][0]['event']);
        $this->assertEquals($lastcompletion->get('timecertified'), $log['log'][0]['date']);

        // Log should show "Became certified" with expiration date +2 days after completion date as was configured.
        $expirydate = userdate($lastcompletion->get('expirydate') , get_string('strftimedatefullshort', 'langconfig'));
        $this->assertEquals("Became certified (expires on $expirydate)", $log['log'][1]['event']);

        // Move system datetime +1 day after previous certification expiry date as is configured in recertification grace period.
        uopz_set_return('time', $lastcompletion->get('expirydate') + DAYSECS);

        // The recertification period ended, so the deallocation process for not completed program starts.
        api::deallocate_users_after_grace_period_end(null, true, true);

        // Get certification user log.
        $userlog = new user_log($user->id, $certification->get('id'));
        $log = $userlog->get_user_log();

        // We should get 3 log records, certification is expired.
        $this->assertCount(3, $log['log']);

        // Log should still show the initial program completed and date as user was set as certified.
        $lastcompletion = api::get_last_completion_record($user->id, $certification->get('id'));
        $this->assertEquals($lastcompletion->get('timecertified'), $log['log'][0]['date']);

        // Log should still show "Became certified" with expiration date +2 days after completion date as was configured.
        $expirydate = userdate($lastcompletion->get('expirydate'), get_string('strftimedatefullshort', 'langconfig'));
        $this->assertEquals("Became certified (expires on $expirydate)", $log['log'][1]['event']);

        // Log should show "Certification expired" since the initial certification expired.
        $this->assertEquals("Certification expired", $log['log'][2]['event']);
        $this->assertEquals($lastcompletion->get('expirydate'), $log['log'][2]['date']);
    }
}
