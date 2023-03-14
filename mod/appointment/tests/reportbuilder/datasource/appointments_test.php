<?php
// This file is part of the mod_appointment plugin for Moodle - http://moodle.org/
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

declare(strict_types=1);

namespace mod_appointment\reportbuilder\datasource;

use core_reportbuilder_generator;
use core_reportbuilder_testcase;
use mod_appointment_generator;
use stdClass;
use tool_tenant_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->dirroot}/reportbuilder/tests/helpers.php");

/**
 * Appointments datasource tests.
 *
 * @covers     \mod_appointment\reportbuilder\datasource\appointments
 * @package    mod_appointment
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class appointments_test extends core_reportbuilder_testcase {

    /** @var core_reportbuilder_generator */
    protected $rbgenerator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var mod_appointment_generator */
    protected $generator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->rbgenerator = self::getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->generator = self::getDataGenerator()->get_plugin_generator('mod_appointment');
    }

    /**
     * Test appointments datasource
     */
    public function test_appointments_datasource(): void {
        $this->resetAfterTest();
        self::setAdminUser();

        $coursecategory = self::getDataGenerator()->create_category(['name' => 'tenant category']);
        $coursecategory2 = self::getDataGenerator()->create_category(['parent' => $coursecategory->id]);
        $tenant1 = $this->tenantgenerator->create_tenant(['categoryid' => $coursecategory->id]);
        $course = self::getDataGenerator()->create_course(['category' => $coursecategory2->id, 'fullname' => 'Test course']);
        $student = self::getDataGenerator()->create_and_enrol($course);
        $this->tenantgenerator->allocate_user((int) $student->id, $tenant1->id);
        $this->tenantgenerator->allocate_user((int) get_admin()->id, $tenant1->id);

        // Add appointment to course.
        $appointment = self::getDataGenerator()->create_module('appointment',
            ['course' => $course->id, 'name' => 'My appointment']);

        // Add session.
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + 3600;
        $session = $this->generator->create_session(['appointment' => $appointment->id, 'capacity' => 1], [], [$date]);

        appointment_user_signup($session, $appointment, $course, MOD_APPOINTMENT_STATUS_BOOKED, (int) $student->id);

        $report = $this->rbgenerator->create_report([
            'name' => 'Appointments',
            'source' => appointments::class,
            'default' => false,
        ]);

        // Add course fullname column to the report.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'course:fullname']);
        // Add appointment name column to the report.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'appointment:name']);
        // Add session date start column to the report.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'session_date:datestart']);
        // Add user fullname column to the report.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname']);

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);

        $contentrow = array_values(reset($content));
        $dateformat = get_string('strftimedaydate', 'core_langconfig');
        $this->assertEquals([
            'Test course', // Course fullname.
            'My appointment', // Appointment name.
            userdate((int) $date->timestart, $dateformat), // Session start date.
            fullname($student), // User full name.
        ], $contentrow);
    }

    /**
     * Stress test datasource
     */
    public function test_stress_datasource(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $category = $this->getDataGenerator()->create_category();
        $tenant = $this->tenantgenerator->create_tenant(['categoryid' => $category->id]);

        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->tenantgenerator->allocate_user((int) $user->id, $tenant->id);
        $this->tenantgenerator->allocate_user((int) get_admin()->id, $tenant->id);

        $appointment = $this->getDataGenerator()->create_module('appointment', ['course' => $course->id, 'name' => 'Welcome']);

        // Add session.
        $date = new stdClass();
        $date->timestart = strtotime('+1 day');
        $date->timefinish = $date->timestart + HOURSECS;

        $session = $this->generator->create_session(['appointment' => $appointment->id, 'capacity' => 1], [], [$date]);
        appointment_user_signup($session, $appointment, $course, MOD_APPOINTMENT_STATUS_BOOKED, (int) $user->id);

        $this->datasource_stress_test_columns(appointments::class);
        $this->datasource_stress_test_columns_aggregation(appointments::class);
        $this->datasource_stress_test_conditions(appointments::class, 'course:shortname');
    }
}
