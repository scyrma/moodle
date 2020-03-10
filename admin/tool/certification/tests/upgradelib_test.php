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
 * Test for upgrade scripts in tool_certification
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_certification\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Class tool_certification_upgradelib_testcase
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_upgradelib_testcase extends advanced_testcase {
    /** @var tool_certification_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var tool_program_generator */
    protected $programgenerator;
    /** @var array */
    protected $tenants = [];
    /** @var stdClass */
    protected $course;

    /**
     * setUp.
     */
    public function setUp() {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();

        $this->course = $this->programgenerator->generate_course_with_completion_self();
        for ($i = 0; $i < 2; $i++) {
            $tenant = $this->tenantgenerator->create_tenant();
            $user1 = self::getDataGenerator()->create_user();
            $this->tenantgenerator->allocate_user($user1->id, $tenant->id);
            $user2 = self::getDataGenerator()->create_user();
            $this->tenantgenerator->allocate_user($user2->id, $tenant->id);

            $program1 = $this->programgenerator->generate_program((object)['tenantid' => $tenant->id]);
            $recertprogram1 = $this->programgenerator->generate_program((object)['tenantid' => $tenant->id]);
            $program2 = $this->programgenerator->generate_program((object)['tenantid' => $tenant->id]);
            $recertprogram2 = $this->programgenerator->generate_program((object)['tenantid' => $tenant->id]);
            $programcourse11 = $this->programgenerator->add_course_to_set($this->course->id, $program1->get_base_set()->get('id'));
            $programcourse21 = $this->programgenerator->add_course_to_set($this->course->id, $program2->get_base_set()->get('id'));

            $certification1 = $this->generator->generate_certification([
                'tenantid' => $tenant->id,
                'program' => $program1->get('id'),
                'recertificationprogram' => $recertprogram1->get('id'),
                'expirydatetype' => \tool_certification\constants::DATE_AFTER_COMPLETION,
                'expirydaterelative' => '1 day',
                'recertstartdaterelative' => '2 day'
            ], true);
            $this->generator->allocate_user($user1->id, $certification1->get('id'));
            $this->generator->allocate_user($user2->id, $certification1->get('id'));

            // User1 completes course1.
            \tool_program\api::enrol_in_program_course($program1->get('id'), $programcourse11->get_course(), $user1->id);
            \tool_program\api::enrol_in_program_course($program2->get('id'), $programcourse21->get_course(), $user1->id);
            $this->programgenerator->complete_courses([$this->course->id], $user1->id);

            // Generate stand-alone programs with user allocations.
            $programdata = $this->programgenerator->get_dummy_program_data();
            $programdata->tenantid = $tenant->id;
            $program = $this->programgenerator->generate_program($programdata);
            $baseset = $program->get_base_set();
            $programcourse = $this->programgenerator->add_course_to_set($this->course->id, $baseset->get('id'));
            $programuser1 = \tool_program\api::allocate_user($program, (object)['userid' => $user1->id,
                'allocationtype' => \tool_program\constants::ALLOCATION_MANUAL,
                'status' => \tool_program\constants::STATUS_OVERRIDE_DEFAULT]);
            $programuser2 = \tool_program\api::allocate_user($program, (object)['userid' => $user2->id,
                'allocationtype' => \tool_program\constants::ALLOCATION_MANUAL,
                'status' => \tool_program\constants::STATUS_OVERRIDE_DEFAULT]);
            $this->programgenerator->enrol_user_to_program_course($programcourse, $programuser1);
            $this->programgenerator->complete_courses([$this->course->id], $user1->id);

            $this->tenants[] = $tenant;
        }
    }

    /**
     * Tests for function tool_program_upgrade_remove_orphaned_programs()
     */
    public function test_upgrade_remove_orphaned_certifications() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/certification/db/upgradelib.php');

        // Make sure there is relevant data in certification tables.
        $certificationid =
            $DB->get_field('tool_certification', 'id', ['tenantid' => $this->tenants[0]->id]);
        $this->assertCount(1, $DB->get_records('tool_certification',
            ['id' => $certificationid]));
        $this->assertCount(2, $DB->get_records('tool_certification_users',
            ['certificationid' => $certificationid]));
        $this->assertCount(1, $DB->get_records('tool_certification_compltion',
            ['certificationid' => $certificationid]));
        $this->assertCount(2, $DB->get_records('tool_program_users',
            ['certificationid' => $certificationid]));
        $this->assertCount(8, $DB->get_records('tool_program_users')); // Total records.
        $this->assertCount(3, $DB->get_records_select('event',
            'eventtype LIKE :eventtype AND instance = :instance',
            ['eventtype' => 'tool_certification%', 'instance' => $certificationid]));

        // Delete a tenant without using any APIs. This will result in orphaned program.
        $DB->delete_records('tool_tenant', ['id' => $this->tenants[0]->id]);

        // Run upgrade script.
        tool_certification_upgrade_remove_orphaned_certifications();

        // Make sure there is no data left in certification tables.
        $this->assertCount(0, $DB->get_records('tool_certification',
            ['id' => $certificationid]));
        $this->assertCount(0, $DB->get_records('tool_certification_users',
            ['certificationid' => $certificationid]));
        $this->assertCount(0, $DB->get_records('tool_certification_compltion',
            ['certificationid' => $certificationid]));
        $this->assertCount(0, $DB->get_records('tool_program_users',
            ['certificationid' => $certificationid]));
        $this->assertCount(0, $DB->get_records_select('event',
            'eventtype LIKE :eventtype AND instance = :instance',
            ['eventtype' => 'tool_certification%', 'instance' => $certificationid]));

        // Second tenant and all its certifications and data are still there.
        $certificationid =
            $DB->get_field('tool_certification', 'id', ['tenantid' => $this->tenants[1]->id]);
        $this->assertCount(1, $DB->get_records('tool_certification',
            ['id' => $certificationid]));
        $this->assertCount(2, $DB->get_records('tool_certification_users',
            ['certificationid' => $certificationid]));
        $this->assertCount(1, $DB->get_records('tool_certification_compltion',
            ['certificationid' => $certificationid]));
        $this->assertCount(2, $DB->get_records('tool_program_users',
            ['certificationid' => $certificationid]));
        $this->assertCount(6, $DB->get_records('tool_program_users')); // Used to be 8 in total, 2 were removed.
        $this->assertCount(3, $DB->get_records_select('event',
            'eventtype LIKE :eventtype AND instance = :instance',
            ['eventtype' => 'tool_certification%', 'instance' => $certificationid]));
        // TODO WP-1577 the number of events is not correct currently, for user who completed the certification there should not
        // be a "due date" event, for user who has not completed there should not be "expiries" event.
    }

}