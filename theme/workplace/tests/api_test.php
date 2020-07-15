<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 *  Tests for theme_workplace api.
 *
 * @package   theme_workplace
 * @category   test
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use \theme_workplace\api;

/**
 * Unit tests for theme_workplace api
 *
 * @package    theme_workplace
 * @group      theme_workplace
 * @covers     \theme_workplace\api
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class theme_workplace_api_testcase extends advanced_testcase {

    /**
     * setUp.
     */
    public function setUp() {
        $this->resetAfterTest();
    }

    /**
     * Enrols user into a course as student, automatically creates enrolment method if missing
     *
     * @param int $userid
     * @param stdClass $course
     * @param string $enrol
     * @param int $timestart
     * @param int $timeend
     * @param int $status
     */
    protected function enrol_user(int $userid, stdClass $course, string $enrol = 'manual',
                                  int $timestart = 0, int $timeend = 0, int $status = ENROL_USER_ACTIVE) {
        global $DB;

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        if ($enrol !== 'manual') {
            // Make sure enrolment method exists in the course and is enabled.
            if (!$enrolplugin = enrol_get_plugin($enrol)) {
                throw new coding_exception('Enrolment plugin not found');
            }
            $instances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => $enrol]);
            if (!$instances) {
                $enrolplugin->add_instance($course, ['roleid' => $studentrole->id]);
            } else {
                $instance = reset($instances);
                if ($instance->status != ENROL_INSTANCE_ENABLED) {
                    $DB->update_record('enrol', ['id' => $instance->id, 'status' => ENROL_INSTANCE_ENABLED]);
                }
            }
        }

        $rv = $this->getDataGenerator()->enrol_user($userid, $course->id, $studentrole->id, $enrol,
            $timestart, $timeend, $status);

        if (!$rv) {
            throw new coding_exception('Could not enrol user');
        }
    }
}