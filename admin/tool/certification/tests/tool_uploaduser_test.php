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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * File containing tests for uploaduser tool integration
 *
 * @package     tool_certification
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification;

use advanced_testcase;
use tool_certification_generator;
use uu_progress_tracker;

/**
 * Test class
 *
 * @package     tool_certification
 * @category    test
 * @covers      \tool_certification\tool_uploaduser
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_uploaduser_testcase extends advanced_testcase {

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
        $certification = $this->get_plugin_generator()->generate_certification([
            'idnumber' => 'mycert',
        ])->to_record();

        // We are going to allocate our user to the new certification, setting the "certify" field.
        $user->certification1 = $certification->idnumber;
        $user->certificationcertify1 = $certificationcertify;

        tool_uploaduser::process_new_user($user, ['certification1'], new uu_progress_tracker());
        $this->assertEquals($expected, api::is_user_certified($user->id, $certification->id));
    }

    /**
     * Get certification generator
     *
     * @return tool_certification_generator
     */
    protected function get_plugin_generator(): tool_certification_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_certification');
    }
}
