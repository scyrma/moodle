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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_tenant;

use advanced_testcase;
use tool_uploaduser\cli_helper;
use tool_uploaduser\local\text_progress_tracker;

/**
 * File containing tests for uploaduser tool integration
 *
 * @package     tool_tenant
 * @category    test
 * @covers      \tool_tenant\tool_uploaduser
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 David Carrillo <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_uploaduser_test extends advanced_testcase {

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
     * Create new users to the tenant and assign tenantadmin role
     */
    public function test_create_users(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $tenantid = tenancy::get_tenant_id();
        // Set an idnumber for the tenant.
        $DB->set_field('tool_tenant', 'idnumber', 'deftenant', ['id' => $tenantid]);

        $csv = <<<EOF
username,firstname,lastname,email,tenant,istenantadmin
student1,Student,One,s1@example.com,deftenant,1
student2,Student,Two,s2@example.com,deftenant,0
student3,Student,Three,s3@example.com,deftenant,
EOF;

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_ADDNEW]);
        $this->assertStringNotContainsString('Error', $output);

        // Confirm that user and tenant user records exist and that the tenant admin role is assigned for 'student1'.
        $student1 = $DB->get_record('user', ['username' => 'student1']);
        $tenantuser1 = tenant_user::get_record(['userid' => $student1->id]);
        $this->assertTrue(\tool_tenant\manager::is_tenant_admin($tenantid, $tenantuser1->get('userid')));

        // Confirm that user and tenant user records exist and that the tenant admin role is not assigned for 'student2'.
        $student2 = $DB->get_record('user', ['username' => 'student2']);
        $tenantuser2 = tenant_user::get_record(['userid' => $student2->id]);
        $this->assertFalse(\tool_tenant\manager::is_tenant_admin($tenantid, $tenantuser2->get('userid')));

        // Confirm that user and tenant user records exist and that the tenant admin role is not assigned for 'student3'.
        $student3 = $DB->get_record('user', ['username' => 'student3']);
        $tenantuser3 = tenant_user::get_record(['userid' => $student3->id]);
        $this->assertFalse(\tool_tenant\manager::is_tenant_admin($tenantid, $tenantuser3->get('userid')));
    }

    /**
     * Update user allocations to the tenant and assign/unassign tenantadmin role
     */
    public function test_update_users(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $tenantid = tenancy::get_tenant_id();
        // Set an idnumber for the tenant.
        $DB->set_field('tool_tenant', 'idnumber', 'deftenant', ['id' => $tenantid]);

        $student1 = $this->getDataGenerator()->create_user([
            'username' => 'student1',
            'firstname' => 'Student',
            'lastname' => 'One',
        ]);
        $student2 = $this->getDataGenerator()->create_user([
            'username' => 'student2',
            'firstname' => 'Student',
            'lastname' => 'Two',
        ]);
        $student3 = $this->getDataGenerator()->create_user([
            'username' => 'student3',
            'firstname' => 'Student',
            'lastname' => 'Three',
        ]);

        $csv = <<<EOF
username,firstname,lastname,email,tenant,istenantadmin
student1,Student,One,s1@example.com,deftenant,1
student2,Student,Two,s2@example.com,deftenant,0
student3,Student,Three,s3@example.com,deftenant,1
EOF;

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Confirm that tenant user records exist and that tenant admin role is assigned for 'student1'.
        $tenantuser1 = tenant_user::get_record(['userid' => $student1->id]);
        $this->assertTrue(\tool_tenant\manager::is_tenant_admin($tenantid, $tenantuser1->get('userid')));

        // Confirm that tenant user records exist and that tenant admin role is not assigned for 'student2'.
        $tenantuser2 = tenant_user::get_record(['userid' => $student2->id]);
        $this->assertFalse(\tool_tenant\manager::is_tenant_admin($tenantid, $tenantuser2->get('userid')));

        // Confirm that tenant user records exist and that tenant admin role is assigned for 'student3'.
        $tenantuser3 = tenant_user::get_record(['userid' => $student3->id]);
        $this->assertTrue(\tool_tenant\manager::is_tenant_admin($tenantid, $tenantuser3->get('userid')));

        $csv = <<<EOF
username,firstname,lastname,email,tenant,istenantadmin
student1,Student,One,s1@example.com,deftenant,0
student2,Student,Two,s2@example.com,deftenant,
student3,Student,Three,s3@example.com,deftenant,
EOF;

        // Process import, make sure there are no errors.
        $output = $this->process_csv_upload($csv, ['--uutype='.UU_USER_UPDATE]);
        $this->assertStringNotContainsString('Error', $output);

        // Confirm that tenant admin role has been updated and is not assigned for 'student1' anymore.
        $this->assertFalse(\tool_tenant\manager::is_tenant_admin($tenantid, $tenantuser1->get('userid')));

        // Confirm that tenant admin role is still not assigned for 'student2' (the 'istenantadmin1' value was empty).
        $this->assertFalse(\tool_tenant\manager::is_tenant_admin($tenantid, $tenantuser2->get('userid')));

        // Confirm that tenant admin role is still assigned for 'student3' (the 'istenantadmin1' value was empty).
        $this->assertTrue(\tool_tenant\manager::is_tenant_admin($tenantid, $tenantuser3->get('userid')));
    }
}
