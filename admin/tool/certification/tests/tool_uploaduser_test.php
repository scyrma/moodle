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
use uu_progress_tracker;

/**
 * File containing tests for uploaduser tool integration
 *
 * @package     tool_certification
 * @category    test
 * @covers      \tool_certification\tool_uploaduser
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_uploaduser_test extends advanced_testcase {
    /** @var tool_certification_generator */
    protected $generator;
    /** @var \tool_program_generator */
    protected $programgenerator;
    /** @var \tool_tenant_generator */
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
     * Load required libraries (upload user progress tracker)
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->dirroot}/{$CFG->admin}/tool/uploaduser/locallib.php");
    }

    /**
     * Data provider for {{@see test_certification_certify}}
     *
     * @return array
     */
    public function certification_certify_provider(): array {
        return [
            'Do not certify' => [0, false],
            'Do certify' => [1, true],
        ];
    }

    /**
     * Test specifying the certificationcertify upload field
     *
     * @param int $certificationcertify
     * @param bool $expected
     *
     * @dataProvider certification_certify_provider
     */
    public function test_certification_certify(int $certificationcertify, bool $expected): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $certification = $this->generator->generate_certification([
            'idnumber' => 'mycert',
        ])->to_record();

        // We are going to allocate our user to the new certification, setting the "certify" field.
        $user->certification1 = $certification->idnumber;
        $user->certificationcertify1 = $certificationcertify;

        tool_uploaduser::process_new_user($user, ['certification1'], new uu_progress_tracker());
        $this->assertEquals($expected, api::is_user_certified($user->id, $certification->id));
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

    /**
     * Uploading certification and marking users as certified (no recertification)
     */
    public function test_uploaduser_certification_certified(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user11 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $user12 = $this->getDataGenerator()->create_user(['username' => 'user2']);
        $user13 = $this->getDataGenerator()->create_user(['username' => 'user3']);
        $user14 = $this->getDataGenerator()->create_user(['username' => 'user4']);
        // Create a certification without recertification with a fixed expiration date in the future.
        $c1 = $this->generator->generate_certification([
            'fullname' => 'Cert1',
            'idnumber' => 'testimport',
            'expirydateabsolute' => strtotime('2032-04-04'),
            'expirydatetype' => constants::DATE_ABSOLUTE,
            'expirydaterelative' => '',
        ]);
        $this->generator->allocate_user($user14->id, $c1->get('id'));

        // Upload 3 users who must be marked as certified. First one - default expiration date;
        // second one - expiration date in the future, third one - expiration date in the past.
        // Forth user is already allocated to the certification, we just want to mark him as certified.
        // @codingStandardsIgnoreStart
        $csv = <<<EOF
username,certification1,certificationstartdate1,certificationcertify1,certificationcertifytimecertified1,certificationcertifyexpires1
user1,testimport,2018-03-01,1,2019-09-05,
user2,testimport,2018-03-01,1,2019-09-05,2030-01-01
user3,testimport,2018-03-01,1,2019-09-05,2020-01-01
user4,testimport,2018-03-01,1,2019-09-05,
EOF;
        // @codingStandardsIgnoreEnd

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Assert that all users are certified.
        $this->assertEquals(constants::STATUS_CERTIFIED,
            api::get_user_allocation_status($c1->get('id'), $user11->id)[0]['statusint']);
        $this->assertEquals(constants::STATUS_CERTIFIED,
            api::get_user_allocation_status($c1->get('id'), $user12->id)[0]['statusint']);
        $this->assertEquals(constants::STATUS_EXPIRED,
            api::get_user_allocation_status($c1->get('id'), $user13->id)[0]['statusint']);
        $this->assertEquals(constants::STATUS_CERTIFIED,
            api::get_user_allocation_status($c1->get('id'), $user14->id)[0]['statusint']);

        // There is no recertification set, so users are not assigned to any program.
        $params = ['certificationid' => $c1->get('id')];
        $certuser = certification_user::get_record(['userid' => $user11->id] + $params);
        $this->assertEmpty($certuser->get('currentprogramid'));
        $this->assertEmpty($certuser->get('nextstartdate'));
        $certuser = certification_user::get_record(['userid' => $user12->id] + $params);
        $this->assertEmpty($certuser->get('currentprogramid'));
        $this->assertEmpty($certuser->get('nextstartdate'));
        $certuser = certification_user::get_record(['userid' => $user13->id] + $params);
        $this->assertEmpty($certuser->get('currentprogramid'));
        $this->assertEmpty($certuser->get('nextstartdate'));
        $certuser = certification_user::get_record(['userid' => $user14->id] + $params);
        $this->assertEmpty($certuser->get('currentprogramid'));
        $this->assertEmpty($certuser->get('nextstartdate'));

        // Check that completion data corresponds to the import data.
        $lastcompletion = api::get_last_completion_record($user11->id, $c1->get('id'));
        $this->assertEquals('2019-09-05', date('Y-m-d', $lastcompletion->get('timecertified')));
        $this->assertEquals('2032-04-04', date('Y-m-d', $lastcompletion->get('expirydate')));
        $lastcompletion = api::get_last_completion_record($user12->id, $c1->get('id'));
        $this->assertEquals('2019-09-05', date('Y-m-d', $lastcompletion->get('timecertified')));
        $this->assertEquals('2030-01-01', date('Y-m-d', $lastcompletion->get('expirydate')));
        $lastcompletion = api::get_last_completion_record($user13->id, $c1->get('id'));
        $this->assertEquals('2019-09-05', date('Y-m-d', $lastcompletion->get('timecertified')));
        $this->assertEquals('2020-01-01', date('Y-m-d', $lastcompletion->get('expirydate')));
        $lastcompletion = api::get_last_completion_record($user14->id, $c1->get('id'));
        $this->assertEquals('2019-09-05', date('Y-m-d', $lastcompletion->get('timecertified')));
        $this->assertEquals('2032-04-04', date('Y-m-d', $lastcompletion->get('expirydate')));
    }

    /**
     * Uploading certification and marking users as certified (with recertification)
     */
    public function test_uploaduser_recertification_certified(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user11 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $user12 = $this->getDataGenerator()->create_user(['username' => 'user2']);
        $user13 = $this->getDataGenerator()->create_user(['username' => 'user3']);
        $user14 = $this->getDataGenerator()->create_user(['username' => 'user4']);
        // Create a certification with recertification.
        $c1 = $this->generator->generate_certification([
            'fullname' => 'Cert1',
            'idnumber' => 'testimport',
            'expirydateabsolute' => strtotime('2032-04-04'),
            'expirydatetype' => constants::DATE_ABSOLUTE,
            'expirydaterelative' => '',
            'recertexpirydaterelative' => '1 year',
        ], true);
        $this->generator->allocate_user($user14->id, $c1->get('id'));

        // Upload 3 users who must be marked as certified. First one - default expiration date;
        // second one - expiration date in the future, third one - expiration date in the past.
        // Forth user is already allocated to the certification, we just want to mark him as certified.
        // @codingStandardsIgnoreStart
        $csv = <<<EOF
username,certification1,certificationstartdate1,certificationcertify1,certificationcertifytimecertified1,certificationcertifyexpires1
user1,testimport,2018-03-01,1,2019-09-05,
user2,testimport,2018-03-01,1,2019-09-05,2030-01-01
user3,testimport,2018-03-01,1,2019-09-05,2020-01-01
user4,testimport,2018-03-01,1,2019-09-05,
EOF;
        // @codingStandardsIgnoreEnd

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Assert that all users are certified.
        $this->assertEquals(constants::STATUS_CERTIFIED,
            api::get_user_allocation_status($c1->get('id'), $user11->id)[0]['statusint']);
        $this->assertEquals(constants::STATUS_CERTIFIED,
            api::get_user_allocation_status($c1->get('id'), $user12->id)[0]['statusint']);
        $this->assertEquals(constants::STATUS_EXPIRED,
            api::get_user_allocation_status($c1->get('id'), $user13->id)[0]['statusint']);
        $this->assertEquals(constants::STATUS_CERTIFIED,
            api::get_user_allocation_status($c1->get('id'), $user14->id)[0]['statusint']);

        // User1,user2,user4 are certified, next recert will start 7 day before the expiration date.
        // User3 certification has expired, they are allocated to the new program.
        $params = ['certificationid' => $c1->get('id')];
        $certuser = certification_user::get_record(['userid' => $user11->id] + $params);
        $this->assertEmpty($certuser->get('currentprogramid'));
        $this->assertEquals('2032-03-28', date('Y-m-d', $certuser->get('nextstartdate')));
        $certuser = certification_user::get_record(['userid' => $user12->id] + $params);
        $this->assertEmpty($certuser->get('currentprogramid'));
        $this->assertEquals('2029-12-25', date('Y-m-d', $certuser->get('nextstartdate')));
        $certuser = certification_user::get_record(['userid' => $user13->id] + $params);
        $this->assertEquals($c1->get('program'), $certuser->get('currentprogramid'));
        $this->assertEquals('2019-12-25', date('Y-m-d', $certuser->get('nextstartdate')));
        $certuser = certification_user::get_record(['userid' => $user14->id] + $params);
        $this->assertEmpty($certuser->get('currentprogramid'));
        $this->assertEquals('2032-03-28', date('Y-m-d', $certuser->get('nextstartdate')));

        // Check that completion data corresponds to the import data.
        $lastcompletion = api::get_last_completion_record($user11->id, $c1->get('id'));
        $this->assertEquals('2019-09-05', date('Y-m-d', $lastcompletion->get('timecertified')));
        $this->assertEquals('2032-04-04', date('Y-m-d', $lastcompletion->get('expirydate')));
        $lastcompletion = api::get_last_completion_record($user12->id, $c1->get('id'));
        $this->assertEquals('2019-09-05', date('Y-m-d', $lastcompletion->get('timecertified')));
        $this->assertEquals('2030-01-01', date('Y-m-d', $lastcompletion->get('expirydate')));
        $lastcompletion = api::get_last_completion_record($user13->id, $c1->get('id'));
        $this->assertEquals('2019-09-05', date('Y-m-d', $lastcompletion->get('timecertified')));
        $this->assertEquals('2020-01-01', date('Y-m-d', $lastcompletion->get('expirydate')));
        $lastcompletion = api::get_last_completion_record($user14->id, $c1->get('id'));
        $this->assertEquals('2019-09-05', date('Y-m-d', $lastcompletion->get('timecertified')));
        $this->assertEquals('2032-04-04', date('Y-m-d', $lastcompletion->get('expirydate')));
    }

    /**
     * Uploading multiple manual certifications for the same user and the same certification
     */
    public function test_uploaduser_multiple_certifications(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user11 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        // Create a certification with recertification.
        $c1 = $this->generator->generate_certification([
            'fullname' => 'Cert1',
            'idnumber' => 'testimport',
            'expirydateabsolute' => strtotime('2032-04-04'),
            'expirydatetype' => constants::DATE_ABSOLUTE,
            'expirydaterelative' => '',
            'recertexpirydaterelative' => '1 year',
        ], true);

        // Mark the same user as certified on the same certification three times.
        // @codingStandardsIgnoreStart
        $csv = <<<EOF
username,certification1,certificationcertify1,certificationcertifytimecertified1,certificationcertifyexpires1,certification2,certificationcertify2,certificationcertifytimecertified2,certificationcertifyexpires2,certification3,certificationcertify3,certificationcertifytimecertified3,certificationcertifyexpires3
user1,testimport,1,2010-02-01,2011-03-01,testimport,1,2011-02-01,2012-03-01,testimport,1,2012-02-01,2013-03-01
EOF;
        // @codingStandardsIgnoreEnd

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Assert that all users are certified but certification is expired.
        $this->assertEquals(constants::STATUS_EXPIRED,
            api::get_user_allocation_status($c1->get('id'), $user11->id)[0]['statusint']);

        // Assert upload has generated 3 completions for this user.
        $certcompletions = certification_completion::get_records([
            'userid' => $user11->id,
            'certificationid' => $c1->get('id'),
        ], 'id', 'ASC');
        $this->assertCount(3, $certcompletions);
        $this->assertEquals(strtotime('2010-02-01'), $certcompletions[0]->get('timecertified'));
        $this->assertEquals(strtotime('2011-03-01'), $certcompletions[0]->get('expirydate'));
        $this->assertEquals(0, $certcompletions[0]->get('islast'));
        $this->assertEquals(strtotime('2011-02-01'), $certcompletions[1]->get('timecertified'));
        $this->assertEquals(strtotime('2012-03-01'), $certcompletions[1]->get('expirydate'));
        $this->assertEquals(0, $certcompletions[1]->get('islast'));
        $this->assertEquals(strtotime('2012-02-01'), $certcompletions[2]->get('timecertified'));
        $this->assertEquals(strtotime('2013-03-01'), $certcompletions[2]->get('expirydate'));
        $this->assertEquals(1, $certcompletions[2]->get('islast'));

        // Add a fourth completion with expiration date in the future.
        // @codingStandardsIgnoreStart
        $csv = <<<EOF
username,certification1,certificationcertify1,certificationcertifytimecertified1,certificationcertifyexpires1
user1,testimport,1,2013-02-01,2033-03-01
EOF;
        // @codingStandardsIgnoreEnd

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Assert that the user is certified.
        $this->assertEquals(constants::STATUS_CERTIFIED,
            api::get_user_allocation_status($c1->get('id'), $user11->id)[0]['statusint']);
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

    public function test_allocate_user_with_dates(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $today = usergetmidnight(time());

        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $user2 = $this->getDataGenerator()->create_user(['username' => 'user2']);
        $c1 = $this->prepare_certification_with_recertification(['startdateabsolute' => $today]);

        // Allocate first user with specific start/due date, and the second user with default dates.
        $csv = <<<EOF
username,certification1,certificationstartdate1,certificationduedate1,certificationexpirydate1
user1,testimport,2020-01-01,2030-12-31,2031-12-31
user2,testimport,,,
EOF;

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Assert that user1 is allocated, start and due dates are set; expiry date is ignored.
        $this->assertEquals(constants::STATUS_OPEN,
            api::get_user_allocation_status($c1->get('id'), $user1->id)[0]['statusint']);
        $certuser = certification_user::get_record(['userid' => $user1->id, 'certificationid' => $c1->get('id')]);
        $programuser = program_user::get_record([
            'userid' => $certuser->get('userid'),
            'certificationid' => $c1->get('id'),
            'programid' => $certuser->get('currentprogramid'),
        ]);
        $this->assertEquals(strtotime('2020-01-01'), $programuser->get('startdate'));
        $this->assertEquals(strtotime('2030-12-31'), $programuser->get('duedate'));

        // Assert that user2 is allocated, start and due dates are taken from certification defaults.
        $this->assertEquals(constants::STATUS_OPEN,
            api::get_user_allocation_status($c1->get('id'), $user2->id)[0]['statusint']);
        $certuser = certification_user::get_record(['userid' => $user2->id, 'certificationid' => $c1->get('id')]);
        $programuser = program_user::get_record([
            'userid' => $certuser->get('userid'),
            'certificationid' => $c1->get('id'),
            'programid' => $certuser->get('currentprogramid'),
        ]);
        $this->assertEquals($today, $programuser->get('startdate'));
        $this->assertEquals(strtotime('+1 year', $today), $programuser->get('duedate'));
    }

    public function test_update_certification_dates_open_certification(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $today = usergetmidnight(time());

        $user2 = $this->getDataGenerator()->create_user(['username' => 'user2']);
        $user3 = $this->getDataGenerator()->create_user(['username' => 'user3']);
        // Create a certification with recertification.
        $c1 = $this->prepare_certification_with_recertification(['startdateabsolute' => $today]);

        // Users 2 and 3 are allocated but not certified.
        $this->generator->allocate_user($user2->id, $c1->get('id'));
        $this->generator->allocate_user($user3->id, $c1->get('id'));

        // Double check the set up.
        $this->assertEquals(constants::STATUS_OPEN,
            api::get_user_allocation_status($c1->get('id'), $user2->id)[0]['statusint']);

        $certuser = certification_user::get_record(['userid' => $user2->id, 'certificationid' => $c1->get('id')]);
        $this->assertEquals($c1->get('program'), $certuser->get('currentprogramid'));
        $programuser = api::get_latest_programuser_allocation($user2->id, $c1->get('id'));
        $this->assertEquals($today, $programuser->get('startdate'));
        $this->assertEquals(strtotime('+1 year', $today), $programuser->get('duedate'));

        // Mark the same user as certified on the same certification three times.
        $csv = <<<EOF
username,certification1,certificationstartdate1,certificationduedate1,certificationexpirydate1
user2,testimport,2020-01-01,2030-12-31,2031-12-31
user3,testimport,,,
EOF;

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Check that the dates were updated for user2 but were not updated for user3.
        $certuser = certification_user::get_record(['userid' => $user2->id, 'certificationid' => $c1->get('id')]);
        $this->assertEquals($c1->get('program'), $certuser->get('currentprogramid'));
        $programuser = program_user::get_record([
            'userid' => $certuser->get('userid'),
            'certificationid' => $c1->get('id'),
            'programid' => $certuser->get('currentprogramid'),
        ]);
        $this->assertEquals(strtotime('2020-01-01'), $programuser->get('startdate'));
        $this->assertEquals(strtotime('2030-12-31'), $programuser->get('duedate'));

        $certuser = certification_user::get_record(['userid' => $user3->id, 'certificationid' => $c1->get('id')]);
        $this->assertEquals($c1->get('program'), $certuser->get('currentprogramid'));
        $programuser = program_user::get_record([
            'userid' => $certuser->get('userid'),
            'certificationid' => $c1->get('id'),
            'programid' => $certuser->get('currentprogramid'),
        ]);
        $this->assertEquals($today, $programuser->get('startdate'));
        $this->assertEquals(strtotime('+1 year', $today), $programuser->get('duedate'));

    }

    public function test_update_certification_dates_certified(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $today = usergetmidnight(time());

        $user3 = $this->getDataGenerator()->create_user(['username' => 'user3']);
        $user4 = $this->getDataGenerator()->create_user(['username' => 'user4']);
        // Create a certification with recertification.
        $c1 = $this->prepare_certification_with_recertification(['startdateabsolute' => $today]);

        // Users 3 and 4 are allocated, has been certified, has not started the recertification.
        $this->generator->allocate_user($user3->id, $c1->get('id'));
        $this->generator->allocate_user($user4->id, $c1->get('id'));
        api::set_user_as_certified($user3->id, $c1->get('id'), strtotime('2032-01-01'));
        api::set_user_as_certified($user4->id, $c1->get('id'), strtotime('2032-01-01'));
        (new \tool_certification\task\recertification())->execute();

        // Double check the set up.
        $this->assertEquals(constants::STATUS_CERTIFIED,
            api::get_user_allocation_status($c1->get('id'), $user3->id)[0]['statusint']);

        $certuser = certification_user::get_record(['userid' => $user3->id, 'certificationid' => $c1->get('id')]);
        $this->assertEmpty($certuser->get('currentprogramid'));
        $programuser = program_user::get_record([
            'userid' => $certuser->get('userid'),
            'certificationid' => $c1->get('id'),
            'programid' => $c1->get('program'),
        ]);
        $this->assertEquals($today, $programuser->get('startdate'));
        $this->assertEquals(strtotime('+1 year', $today), $programuser->get('duedate'));
        $lastcompletion = api::get_last_completion_record($user3->id, $c1->get('id'));
        $this->assertEquals(strtotime('2032-01-01'), $lastcompletion->get('expirydate'));

        $csv = <<<EOF
username,certification1,certificationstartdate1,certificationduedate1,certificationexpirydate1
user3,testimport,2020-01-01,2030-12-31,2031-12-31
user4,testimport,,,
EOF;

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // User 3.
        $certuser = certification_user::get_record(['userid' => $user3->id, 'certificationid' => $c1->get('id')]);
        $this->assertEmpty($certuser->get('currentprogramid'));
        $programuser = program_user::get_record([
            'userid' => $certuser->get('userid'),
            'certificationid' => $c1->get('id'),
            'programid' => $c1->get('program'),
        ]);
        // Program start/due dates are not updated for the completed program.
        $this->assertEquals($today, $programuser->get('startdate'));
        $this->assertEquals(strtotime('+1 year', $today), $programuser->get('duedate'));
        // Expiry date is updated.
        $lastcompletion = api::get_last_completion_record($user3->id, $c1->get('id'));
        $this->assertEquals(strtotime('2031-12-31'), $lastcompletion->get('expirydate'));

        // User 4 (nothing is updated).
        $certuser = certification_user::get_record(['userid' => $user4->id, 'certificationid' => $c1->get('id')]);
        $this->assertEmpty($certuser->get('currentprogramid'));
        $programuser = program_user::get_record([
            'userid' => $certuser->get('userid'),
            'certificationid' => $c1->get('id'),
            'programid' => $c1->get('program'),
        ]);
        $this->assertEquals($today, $programuser->get('startdate'));
        $this->assertEquals(strtotime('+1 year', $today), $programuser->get('duedate'));
        $lastcompletion = api::get_last_completion_record($user4->id, $c1->get('id'));
        $this->assertEquals(strtotime('2032-01-01'), $lastcompletion->get('expirydate'));

    }

    public function test_update_certification_dates_recertification(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $today = usergetmidnight(time());

        $user4 = $this->getDataGenerator()->create_user(['username' => 'user4']);
        // Create a certification with recertification.
        $c1 = $this->prepare_certification_with_recertification(['startdateabsolute' => $today]);

        // User 4 is allocated, has been certified, has started recertification.
        $this->generator->allocate_user($user4->id, $c1->get('id'));
        api::set_user_as_certified($user4->id, $c1->get('id'), $today + 5 * DAYSECS);
        (new \tool_certification\task\recertification())->execute();

        // Double check the set up.
        $this->assertEquals(constants::STATUS_CERTIFIED,
            api::get_user_allocation_status($c1->get('id'), $user4->id)[0]['statusint']);

        $certuser = certification_user::get_record(['userid' => $user4->id, 'certificationid' => $c1->get('id')]);
        $this->assertEquals($c1->get('recertificationprogram'), $certuser->get('currentprogramid'));

        // Make sure updating dates works well using a manual allocation type.
        $certuser->set('allocationtype', constants::ALLOCATION_MANUAL);
        $certuser->update();

        // Mark the same user as certified on the same certification three times.
        $user4expdate = date('Y-m-d', $today + 4 * DAYSECS);
        $csv = <<<EOF
username,certification1,certificationstartdate1,certificationduedate1,certificationexpirydate1
user4,testimport,2020-01-01,2030-12-31,{$user4expdate}
EOF;

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // User 4.
        $certuser = certification_user::get_record(['userid' => $user4->id, 'certificationid' => $c1->get('id')]);
        $this->assertEquals($c1->get('recertificationprogram'), $certuser->get('currentprogramid'));
        $programuser = program_user::get_record([
            'userid' => $certuser->get('userid'),
            'certificationid' => $c1->get('id'),
            'programid' => $certuser->get('currentprogramid'),
        ]);
        // Program start/due dates for recertification are recalcualted based on the prev certification expiry date.
        // The dates from CSV are ignored.
        $this->assertEquals(strtotime('-2 days', $today), $programuser->get('startdate'));
        $this->assertEquals(strtotime('+4 days', $today), $programuser->get('duedate'));
        // Expiry date is updated.
        $lastcompletion = api::get_last_completion_record($user4->id, $c1->get('id'));
        $this->assertEquals(strtotime($user4expdate), $lastcompletion->get('expirydate'));

    }

    /**
     * Test to update user allocation expiry date for a dynamic user allocation
     */
    public function test_update_certification_expiry_date_dynamic_allocation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $today = usergetmidnight(time());

        $user4 = $this->getDataGenerator()->create_user(['username' => 'user4']);
        // Create a certification with recertification.
        $c1 = $this->prepare_certification_with_recertification(['startdateabsolute' => $today]);

        // User 4 is allocated, has been certified, has started recertification.
        $this->generator->allocate_user($user4->id, $c1->get('id'));
        api::set_user_as_certified($user4->id, $c1->get('id'), $today + 5 * DAYSECS);
        (new \tool_certification\task\recertification())->execute();

        // Double check the set up.
        $this->assertEquals(constants::STATUS_CERTIFIED,
            api::get_user_allocation_status($c1->get('id'), $user4->id)[0]['statusint']);

        $certuser = certification_user::get_record(['userid' => $user4->id, 'certificationid' => $c1->get('id')]);
        $this->assertEquals($c1->get('recertificationprogram'), $certuser->get('currentprogramid'));

        // Make sure updating dates works well using a dynamic allocation type.
        $certuser->set('allocationtype', constants::ALLOCATION_DYNAMIC);
        $certuser->update();

        // Update User4 expiry date to be 10 days from today.
        $user4expdate = date('Y-m-d', $today + 10 * DAYSECS);
        $csv = <<<EOF
username,certification1,certificationexpirydate1
user4,testimport,{$user4expdate}
EOF;

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Check that expiry date has been updated.
        $lastcompletion = api::get_last_completion_record($user4->id, $c1->get('id'));
        $this->assertEquals(strtotime($user4expdate), $lastcompletion->get('expirydate'));
    }

    /**
     * Uploading user and trying to mark user as certified twice with no recertification
     */
    public function test_uploaduser_certification_certified_in_past_no_recertification(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user11 = $this->getDataGenerator()->create_user(['username' => 'user11']);
        // Create a certification without recertification and no expiration date.
        $c1 = $this->generator->generate_certification([
            'fullname' => 'Cert1',
            'idnumber' => 'testimport',
            'expirydatetype' => constants::DATE_NEVER,
        ]);

        // Set an expiry date in the CSV file for this user when the certification has no recertifictaion enabled and expiry date
        // is set to never.
        // @codingStandardsIgnoreStart
        $csv = <<<EOF
username,certification1,certificationstartdate1,certificationcertify1,certificationcertifytimecertified1,certificationcertifyexpires1
user11,testimport,2019-09-02,1,2019-09-01,2020-09-02
EOF;
        // @codingStandardsIgnoreEnd

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype=' . UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Assert that user has the certification expired.
        $this->assertEquals(constants::STATUS_EXPIRED,
            api::get_user_allocation_status($c1->get('id'), $user11->id)[0]['statusint']);

        // Set an expiry date in the CSV file for this user when the certification has no recertifictaion enabled and expiry date
        // is set to never.
        // @codingStandardsIgnoreStart
        $csv = <<<EOF
username,certification1,certificationstartdate1,certificationcertify1,certificationcertifytimecertified1,certificationcertifyexpires1
user11,testimport,2020-09-02,1,2020-09-01,2021-09-02
EOF;
        // @codingStandardsIgnoreEnd

        // Process import again, make sure there is one error because user can not be certified twice in this certification.
        $output = $this->process_csv_upload($csv, ['--uutype=' . UU_USER_UPDATE]);
        $this->assertStringContainsString('This user can\'t be marked as certified', $output);
    }

    /**
     * Uploading user and trying to REcertify user more than once on a recertification set to never expire
     */
    public function test_uploaduser_recertification_set_to_never_expire(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user11 = $this->getDataGenerator()->create_user(['username' => 'user11']);
        // Create a certification with recertification.
        $c1 = $this->prepare_certification_with_recertification([
            'recertexpirydatetype' => constants::RECERT_EXPIRY_DATE_NEVER_DATE,
        ]);

        // @codingStandardsIgnoreStart
        $csv = <<<EOF
username,certification1,certificationstartdate1,certificationcertify1,certificationcertifytimecertified1,certificationcertifyexpires1
user11,testimport,2019-09-02,1,2021-09-01,2019-09-02
EOF;
        // @codingStandardsIgnoreEnd

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Assert that user has the certification expired.
        $this->assertEquals(constants::STATUS_EXPIRED,
            api::get_user_allocation_status($c1->get('id'), $user11->id)[0]['statusint']);

        // Recertify user. Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Process import again, make sure there is one error because user can not be REcertified more than once
        // in this certification.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringContainsString('This user can\'t be marked as certified', $output);
    }
}
