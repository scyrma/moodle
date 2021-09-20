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
 * Class tool_certification/user_log tests
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class user_log tests.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_user_log_testcase extends advanced_testcase {
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

    public function test_get_user_log(): void {
        global $DB;
        $params = [
            'expirydatetype' => \tool_certification\constants::DATE_NEVER,
            'duedatetype' => \tool_certification\constants::DATE_AFTER_START_DATE,
            'duedaterelative' => '1 day',
            'startdatetype' => \tool_certification\constants::DATE_RELATIVE_TO_ALLOCATION_DATE,
            'startdaterelative' => '0 day',
        ];
        $now = time();

        $certification = $this->generator->generate_certification($params, true);
        $certificationid = $certification->get('id');
        $user = self::getDataGenerator()->create_user();
        $certificationuser = $this->generator->allocate_user($user->id, $certificationid);

        // Check that log only contains user allocation message.
        $userlog = new \tool_certification\user_log($user->id, $certification->get('id'));
        $log = $userlog->get_user_log();
        $this->assertCount(0, $log['log']);
        $this->assertEquals($certificationuser->get('timecreated'), $log['lastallocationdate']);

        // Certify user.
        \tool_certification\api::set_user_as_certified($user->id, $certificationid, null, $now, 2);
        $userlog = new \tool_certification\user_log($user->id, $certification->get('id'));
        $log = $userlog->get_user_log();
        $this->assertCount(1, $log['log']);
        $this->assertEquals('Manually certified '.fullname($user).' (Never expires)', $log['log'][0]['event']);
        $this->assertEquals(2, $log['log'][0]['user']['id']);
        $this->assertEquals($now, $log['log'][0]['date']);

        // Revoke certification.
        \tool_certification\api::revoke_certification_from_user($user->id, $certificationid);
        $log = $userlog->get_user_log();
        $this->assertCount(2, $log['log']);
        $this->assertEquals('Revoked '.fullname($user).'\'s certification', $log['log'][1]['event']);
        $this->assertEquals(0, $log['log'][1]['user']['id']);
        $record = $DB->get_record('tool_certification_compltion', ['userid' => $user->id, 'certificationid' => $certificationid]);
        $this->assertEquals($record->timerevoked, $log['log'][1]['date']);

        // Certify user twice.
        $expirytime1 = strtotime('+1 days');
        $expirytime2 = strtotime('+2 days');
        \tool_certification\api::set_user_as_certified($user->id, $certificationid, $expirytime1, $now, 2);
        \tool_certification\api::allocate_recertification_users();
        \tool_certification\api::set_user_as_certified($user->id, $certificationid, $expirytime2, $now, 2);

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
}
