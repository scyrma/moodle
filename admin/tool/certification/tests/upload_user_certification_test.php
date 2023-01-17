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
use tool_program\persistent\program_user;
use tool_uploaduser\cli_helper;

/**
 * Class upload_user_certification_test
 *
 * @package    tool_certification
 * @author     2021 Odei Alba
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class upload_user_certification_test extends advanced_testcase {
    /** @var tool_certification_generator */
    protected $generator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
    }

    /**
     * Load required libraries (upload user progress tracker)
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->dirroot}/{$CFG->admin}/tool/uploaduser/locallib.php");
    }

    /**
     * Generate cli_helper and mock $_SERVER['argv']
     *
     * @param string $filecontent
     * @param array $mockargv
     * @return string - CSV import output
     */
    protected function process_csv_upload(string $filecontent, array $mockargv = []): string {
        $filepath = make_request_directory(false) . '/' . rand();
        file_put_contents($filepath, $filecontent);
        $mockargv[] = "--file=$filepath";

        if (array_key_exists('argv', $_SERVER)) {
            $oldservervars = $_SERVER['argv'];
        }
        $_SERVER['argv'] = array_merge([''], $mockargv);
        $clihelper = new cli_helper(\tool_uploaduser\local\text_progress_tracker::class);
        if (isset($oldservervars)) {
            $_SERVER['argv'] = $oldservervars;
        } else {
            unset($_SERVER['argv']);
        }

        ob_start();
        $clihelper->process();
        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }

    /*
     * Create a certification, allocate user to it.
     * Make sure the start and due dates were updated.
     */
    public function test_update_start_date_and_due_date(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $today = usergetmidnight(time());

        // Create user.
        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        // Create a certification.
        $c1 = $this->prepare_certification_with_recertification(['startdateabsolute' => $today]);

        // User 1 is allocated.
        $this->generator->allocate_user($user1->id, $c1->get('id'));

        // Double check the set up.
        $this->assertEquals($today, $c1->get('startdateabsolute'));
        $this->assertNotEquals(strtotime('2030-12-31'), $c1->get('duedateabsolute'));

        $certuser = certification_user::get_record(['userid' => $user1->id, 'certificationid' => $c1->get('id')]);
        $programuser = program_user::get_record([
            'userid' => $certuser->get('userid'),
            'certificationid' => $c1->get('id'),
            'programid' => $certuser->get('currentprogramid'),
        ]);
        $this->assertEquals($today, $programuser->get('startdate'));
        $this->assertNotEquals(strtotime('2030-12-31'), $programuser->get('duedate'));

        // Prepare the CSV file.
        $csv = <<<EOF
username,certification1,certificationstartdate1,certificationduedate1
user1,testimport,2020-01-01,2030-12-31
EOF;

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Check that dates correspond to the import data.
        $certuser = certification_user::get_record(['userid' => $user1->id, 'certificationid' => $c1->get('id')]);
        $programuser = program_user::get_record([
            'userid' => $certuser->get('userid'),
            'certificationid' => $c1->get('id'),
            'programid' => $certuser->get('currentprogramid'),
        ]);
        $this->assertEquals(strtotime('2020-01-01'), $programuser->get('startdate'));
        $this->assertEquals(strtotime('2030-12-31'), $programuser->get('duedate'));
    }

    /*
     * Create a certification, allocate user to it and certify the user.
     * Make sure the expiration date of the last certification was updated.
     */
    public function test_update_certification_date_and_last_certification(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $today = usergetmidnight(time());
        $expirationdate = date('Y-m-d', strtotime(' +1 year'));

        // Create user.
        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        // Create a certification.
        $c1 = $this->prepare_certification_with_recertification();

        // User 1 is allocated and certified.
        $this->generator->allocate_user($user1->id, $c1->get('id'));
        api::set_user_as_certified($user1->id, $c1->get('id'), $today + 5 * DAYSECS);

        // Double check the set up.
        $this->assertEquals(constants::STATUS_CERTIFIED,
            api::get_user_allocation_status($c1->get('id'), $user1->id)[0]['statusint']);
        $lastcompletion = api::get_last_completion_record($user1->id, $c1->get('id'));
        $this->assertEquals($today + 5 * DAYSECS, $lastcompletion->get('expirydate'));

        // Prepare the CSV file.
        $csv = <<<EOF
username,certification1,certificationexpirydate1
user1,testimport,{$expirationdate}
EOF;

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Check that dates correspond to the import data.
        $lastcompletion = api::get_last_completion_record($user1->id, $c1->get('id'));
        $this->assertEquals($expirationdate, date('Y-m-d', $lastcompletion->get('expirydate')));
    }

    /*
     * Create a certification, allocate user to it.
     * Make sure the user is marked as certified.
     */
    public function test_certify_user(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create users.
        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $user2 = $this->getDataGenerator()->create_user(['username' => 'user2']);
        // Create a certification.
        $c1 = $this->prepare_certification_with_recertification();

        // User 1 is allocated.
        $this->generator->allocate_user($user1->id, $c1->get('id'));

        // Double check the set up.
        $this->assertNotEquals(constants::STATUS_CERTIFIED,
            api::get_user_allocation_status($c1->get('id'), $user1->id)[0]['statusint']);

        // Prepare the CSV file.
        $csv = <<<EOF
username,certification1,certificationcertify1,certificationcertifytimecertified1,certificationcertifyexpires1
user1,testimport,1,2019-09-05,
user2,testimport,1,2020-08-01,
EOF;

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Check that dates correspond to the import data.
        $this->assertEquals(constants::STATUS_CERTIFIED,
            api::get_user_allocation_status($c1->get('id'), $user1->id)[0]['statusint']);
        $user1lastcompletion = api::get_last_completion_record($user1->id, $c1->get('id'));
        $this->assertEquals('2019-09-05', date('Y-m-d', $user1lastcompletion->get('timecertified')));
        $this->assertEquals(constants::STATUS_CERTIFIED,
            api::get_user_allocation_status($c1->get('id'), $user2->id)[0]['statusint']);
        $user2lastcompletion = api::get_last_completion_record($user2->id, $c1->get('id'));
        $this->assertEquals('2020-08-01', date('Y-m-d', $user2lastcompletion->get('timecertified')));
    }

    /*
     * Create a certification, allocate user to it.
     * Make sure the user is marked as certified and the current program was removed.
     */
    public function test_certification_user_remove_program(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $today = usergetmidnight(time());

        // Create user.
        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        // Create a certification.
        $c1 = $this->prepare_certification_with_recertification(['startdateabsolute' => $today]);

        // User 1 is allocated.
        $this->generator->allocate_user($user1->id, $c1->get('id'));

        // Double check the set up.
        $this->assertEquals($today, $c1->get('startdateabsolute'));
        $this->assertNotEquals(constants::STATUS_CERTIFIED,
            api::get_user_allocation_status($c1->get('id'), $user1->id)[0]['statusint']);

        $certuser = certification_user::get_record(['userid' => $user1->id, 'certificationid' => $c1->get('id')]);
        $programuser = program_user::get_record([
            'userid' => $certuser->get('userid'),
            'certificationid' => $c1->get('id'),
            'programid' => $certuser->get('currentprogramid'),
        ]);
        $this->assertEquals($today, $programuser->get('startdate'));

        // Prepare the CSV file.
        // @codingStandardsIgnoreStart
        $csv = <<<EOF
username,certification1,certificationstartdate1,certificationcertify1,certificationcertifyexpires1,certificationcertifytimecertified1
user1,testimport,2018-03-01,1,,2019-09-05
EOF;
        // @codingStandardsIgnoreEnd

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Check that dates correspond to the import data.
        $this->assertEquals(constants::STATUS_CERTIFIED,
            api::get_user_allocation_status($c1->get('id'), $user1->id)[0]['statusint']);
        $lastcompletion = api::get_last_completion_record($user1->id, $c1->get('id'));
        $this->assertEquals('2019-09-05', date('Y-m-d', $lastcompletion->get('timecertified')));
        // Value of certificationstartdate1 was ignored because there is no current program allocation.
        $certuser = certification_user::get_record(['userid' => $user1->id, 'certificationid' => $c1->get('id')]);
        $this->assertNull($certuser->get('currentprogramid'));
    }

    /**
     * Helper method to prepare a certification with recertification
     *
     * @param array $params
     * @return certification
     */
    protected function prepare_certification_with_recertification(array $params = []): certification {
        // Create a certification with recertification.
        $c1 = $this->generator->generate_certification($params + [
                'fullname' => 'Cert1',
                'idnumber' => 'testimport',
                'startdatetype' => constants::DATE_ABSOLUTE,
                'startdateabsolute' => usergetmidnight(time()),
                'duedatetype' => constants::DATE_AFTER_START_DATE,
                'duedaterelative' => '1 year',
                'expirydateabsolute' => strtotime('2032-04-04'),
                'expirydatetype' => constants::DATE_ABSOLUTE,
                'expirydaterelative' => '',
                'recertexpirydatetype' => constants::RECERT_EXPIRY_DATE_AFTR_PREV_COMPL,
                'recertexpirydaterelative' => '1 year', // Recertification expires 1 year after completion.
                'recertstartdaterelative' => '1 week', // Recertification starts 1 week before certification expiry.
            ], true);
        return $c1;
    }
}
