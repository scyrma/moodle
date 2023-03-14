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

namespace tool_program;

use advanced_testcase;
use tool_certification_generator;
use tool_program\persistent\program_user;
use tool_program_generator;
use tool_tenant_generator;
use tool_uploaduser\cli_helper;
use tool_uploaduser\local\text_progress_tracker;

/**
 * File containing tests for uploaduser tool integration
 *
 * @package     tool_program
 * @category    test
 * @covers      \tool_program\tool_uploaduser
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_uploaduser_test extends advanced_testcase {

    /** @var tool_certification_generator */
    protected $certificationgenerator;
    /** @var tool_program_generator */
    protected $programgenerator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->certificationgenerator = self::getDataGenerator()->get_plugin_generator('tool_certification');
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
        $clihelper = new cli_helper(text_progress_tracker::class);
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
     * Allocate one user with custom dates and one user with default dates into a program
     */
    public function test_allocate_user_with_dates(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $startdate = strtotime('-7 day');
        $duedate = strtotime('+7 day');
        $enddate = strtotime('+9 day');

        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $user2 = $this->getDataGenerator()->create_user(['username' => 'user2']);
        $params = [
            'idnumber' => 'prog1',
            'startdatetype' => constants::DATE_ABSOLUTE,
            'startdateabsolute' => $startdate,
            'startdaterelative' => null,
            'enddatetype' => constants::DATE_ABSOLUTE,
            'enddateabsolute' => $enddate,
            'enddaterelative' => null,
            'duedatetype' => constants::DATE_ABSOLUTE,
            'duedateabsolute' => $duedate,
            'duedaterelative' => null,
        ];
        $program = $this->programgenerator->generate_program((object)$params);

        // Allocate first user with specific start/due date, and the second user with default dates.
        $csv = <<<EOF
username,program1,programstartdate1,programduedate1,programenddate1
user1,prog1,2021-03-03,2030-12-31,2031-12-31
user2,prog1,,,
EOF;

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Assert that user1 is allocated and dates are set.
        $programuser1 = program_user::get_record(['programid' => $program->get('id'), 'userid' => $user1->id]);
        $this->assertEquals(strtotime('2021-03-03'), $programuser1->get('startdate'));
        $this->assertEquals(strtotime('2030-12-31'), $programuser1->get('duedate'));
        $this->assertEquals(strtotime('2031-12-31'), $programuser1->get('enddate'));
        $this->assertEquals(constants::STATUS_OPEN,
            api::get_user_allocation_statuses($program->get('id'), $user1->id, 0)[0]['status']);

        // Assert that user2 is allocated and default dates are set.
        $programuser2 = program_user::get_record(['programid' => $program->get('id'), 'userid' => $user2->id]);
        $this->assertEquals($startdate, $programuser2->get('startdate'));
        $this->assertEquals($duedate, $programuser2->get('duedate'));
        $this->assertEquals($enddate, $programuser2->get('enddate'));
        $this->assertEquals(constants::STATUS_OPEN,
            api::get_user_allocation_statuses($program->get('id'), $user2->id, 0)[0]['status']);
    }

    /**
     * Test for updating dates on manual user allocations
     */
    public function test_update_allocation_dates_manual_allocation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $program = $this->programgenerator->generate_program((object)['idnumber' => 'prog1']);
        $this->programgenerator->allocate_user_to_program($program->get('id'), $user1->id, 0,
            ['allocationtype' => constants::ALLOCATION_MANUAL]);

        // Update user allocation dates.
        $csv = <<<EOF
username,program1,programstartdate1,programduedate1,programenddate1
user1,prog1,2021-09-07,2030-09-09,2031-09-09
EOF;

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Assert that user1 is allocated and dates are set.
        $programuser1 = program_user::get_record(['programid' => $program->get('id'), 'userid' => $user1->id]);
        $this->assertEquals(constants::ALLOCATION_MANUAL, $programuser1->get('allocationtype'));
        $this->assertEquals(strtotime('2021-09-07'), $programuser1->get('startdate'));
        $this->assertEquals(strtotime('2030-09-09'), $programuser1->get('duedate'));
        $this->assertEquals(strtotime('2031-09-09'), $programuser1->get('enddate'));
    }

    /**
     * Test for updating dates on dynamic user allocations
     */
    public function test_update_allocation_dates_dynamic_allocation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user1 = $this->getDataGenerator()->create_user(['username' => 'user1']);
        $program = $this->programgenerator->generate_program((object)['idnumber' => 'prog1']);
        $this->programgenerator->allocate_user_to_program($program->get('id'), $user1->id, 0,
            ['allocationtype' => constants::ALLOCATION_DYNAMIC]);

        // Update user allocation dates.
        $csv = <<<EOF
username,program1,programstartdate1,programduedate1,programenddate1
user1,prog1,2021-09-07,2030-09-09,2031-09-09
EOF;

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Assert that user1 is allocated and dates are set.
        $programuser1 = program_user::get_record(['programid' => $program->get('id'), 'userid' => $user1->id]);
        $this->assertEquals(constants::ALLOCATION_DYNAMIC, $programuser1->get('allocationtype'));
        $this->assertEquals(strtotime('2021-09-07'), $programuser1->get('startdate'));
        $this->assertEquals(strtotime('2030-09-09'), $programuser1->get('duedate'));
        $this->assertEquals(strtotime('2031-09-09'), $programuser1->get('enddate'));
    }
}
