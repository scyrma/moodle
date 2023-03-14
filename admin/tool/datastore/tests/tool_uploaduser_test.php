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

namespace tool_datastore;

use advanced_testcase;
use completion_completion;
use tool_uploaduser\cli_helper;
use tool_uploaduser\local\text_progress_tracker;

/**
 * Test class
 *
 * @package     tool_datastore
 * @covers      \tool_datastore\tool_uploaduser
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_uploaduser_test extends advanced_testcase {

    /**
     * Load required test libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->dirroot}/{$CFG->admin}/tool/uploaduser/locallib.php");
        require_once("{$CFG->dirroot}/completion/completion_completion.php");
    }

    /**
     * Test uploading user completion
     */
    public function test_upload_user_completion(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $userone = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $usertwo = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Set completion data for first user as defined date. For second user it should be current time.
        $csv = <<<EOF
username,coursecompleted1,coursecompleteddate1
{$userone->username},{$course->shortname},2021-12-25
{$usertwo->username},{$course->shortname},
EOF;

        // Process upload.
        $timenow = time();
        $this->process_csv_upload($csv, ['--uutype=' . UU_USER_UPDATE]);

        // Assert the completion records were added.
        $useronecompletion = new completion_completion(['course' => $course->id, 'userid' => $userone->id]);
        $this->assertEquals(1640361600, $useronecompletion->timecompleted);

        $usertwocompletion = new completion_completion(['course' => $course->id, 'userid' => $usertwo->id]);

        // Setting delta to 5 seconds, as this causes frequent failures in Oracle.
        $this->assertEqualsWithDelta($timenow, $usertwocompletion->timecompleted, 5.0);
    }

    /**
     * Generate cli_helper and mock $_SERVER['argv']
     *
     * @param string $filecontent
     * @param array $mockargv
     * @return string
     */
    protected function process_csv_upload(string $filecontent, array $mockargv = []): string {
        $filepath = make_request_directory() . '/upload.csv';
        file_put_contents($filepath, $filecontent);
        $mockargv[] = "--file={$filepath}";

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
}
