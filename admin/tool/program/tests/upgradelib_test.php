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
 * Test for upgrade scripts in tool_program
 *
 * @package   tool_program
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_program\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Class tool_program_upgradelib_testcase
 *
 * @package   tool_program
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_upgradelib_testcase extends advanced_testcase {
    /** @var tool_program_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var array */
    protected $tenants = [];
    /** @var stdClass */
    protected $course;

    /**
     * setUp.
     */
    public function setUp() {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();

        $this->course = $this->generator->generate_course_with_completion_self();
        for ($i = 0; $i < 2; $i++) {
            $tenant = $this->tenantgenerator->create_tenant();
            $user1 = self::getDataGenerator()->create_user();
            $user2 = self::getDataGenerator()->create_user();
            $this->tenantgenerator->allocate_user($user1->id, $tenant->id);
            $this->tenantgenerator->allocate_user($user2->id, $tenant->id);

            $programdata = $this->generator->get_dummy_program_data();
            $programdata->tenantid = $tenant->id;
            $program = $this->generator->generate_program($programdata);
            $baseset = $program->get_base_set();
            $programcourse = $this->generator->add_course_to_set($this->course->id, $baseset->get('id'));
            $programuser1 = api::allocate_user($program, (object)['userid' => $user1->id,
                'allocationtype' => \tool_program\constants::ALLOCATION_MANUAL,
                'status' => \tool_program\constants::STATUS_OVERRIDE_DEFAULT]);
            $programuser2 = api::allocate_user($program, (object)['userid' => $user2->id,
                'allocationtype' => \tool_program\constants::ALLOCATION_MANUAL,
                'status' => \tool_program\constants::STATUS_OVERRIDE_DEFAULT]);
            $this->generator->enrol_user_to_program_course($programcourse, $programuser1);
            $this->generator->complete_courses([$this->course->id], $user1->id);

            $this->tenants[] = $tenant;
        }
    }

    /**
     * Tests for function tool_program_upgrade_remove_orphaned_programs()
     */
    public function test_upgrade_remove_orphaned_programs() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/program/db/upgradelib.php');

        // Make sure the program exists in the first tenant and also sets, users and completion.
        $programid = $DB->get_field('tool_program', 'id', ['tenantid' => $this->tenants[0]->id]);
        $this->assertNotEmpty($programid);
        $programsets = $DB->get_fieldset_select('tool_program_sets', 'id', 'programid = :programid',
            ['programid' => $programid]);
        list($setsql, $setparams) = $DB->get_in_or_equal($programsets);
        $this->assertCount(1, $DB->get_records('tool_program', ['id' => $programid]));
        $this->assertCount(1, $DB->get_records('tool_program_sets', ['programid' => $programid]));
        $this->assertCount(2, $DB->get_records('tool_program_users', ['programid' => $programid]));
        $this->assertCount(1, $DB->get_records_select('tool_program_courses', 'setid' . $setsql, $setparams));
        $this->assertCount(1, $DB->get_records_select('tool_program_set_completion', 'setid' . $setsql, $setparams));
        $this->assertCount(4, $DB->get_records_select('event',
            'eventtype LIKE :eventtype AND instance = :instance',
            ['eventtype' => 'tool_program%', 'instance' => $programid]));

        // Delete a tenant without using any APIs. This will result in orphaned program.
        $DB->delete_records('tool_tenant', ['id' => $this->tenants[0]->id]);

        // Run upgrade script.
        tool_program_upgrade_remove_orphaned_programs();

        // Make sure there is no data left in program tables.
        $this->assertCount(0, $DB->get_records('tool_program', ['id' => $programid]));
        $this->assertCount(0, $DB->get_records('tool_program_sets', ['programid' => $programid]));
        $this->assertCount(0, $DB->get_records('tool_program_users', ['programid' => $programid]));
        $this->assertCount(0, $DB->get_records_select('tool_program_courses', 'setid' . $setsql, $setparams));
        $this->assertCount(0, $DB->get_records_select('tool_program_set_completion', 'setid' . $setsql, $setparams));
        $this->assertCount(0, $DB->get_records_select('event',
            'eventtype LIKE :eventtype AND instance = :instance',
            ['eventtype' => 'tool_program%', 'instance' => $programid]));

        // Second tenant and all its programs and data are still there.
        $programid = $DB->get_field('tool_program', 'id', ['tenantid' => $this->tenants[1]->id]);
        $programsets = $DB->get_fieldset_select('tool_program_sets', 'id', 'programid = :programid',
            ['programid' => $programid]);
        list($setsql, $setparams) = $DB->get_in_or_equal($programsets);
        $this->assertCount(1, $DB->get_records('tool_program', ['id' => $programid]));
        $this->assertCount(1, $DB->get_records('tool_program_sets', ['programid' => $programid]));
        $this->assertCount(2, $DB->get_records('tool_program_users', ['programid' => $programid]));
        $this->assertCount(1, $DB->get_records_select('tool_program_courses', 'setid' . $setsql, $setparams));
        $this->assertCount(1, $DB->get_records_select('tool_program_set_completion', 'setid' . $setsql, $setparams));
        $this->assertCount(4, $DB->get_records_select('event',
            'eventtype LIKE :eventtype AND instance = :instance',
            ['eventtype' => 'tool_program%', 'instance' => $programid]));
        // TODO WP-1577 the number of events is not correct currently, for user who completed the program there should be no
        // events in the calendar. This test should be changed when this is fixed, remove this comment then.
    }

    /**
     * Tests for function tool_program_upgrade_suspend_enrolments_in_archived_programs()
     */
    public function test_upgrade_suspend_enrolments_in_archived_programs() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/program/db/upgradelib.php');

        // Both enrolment methods are active.
        $enrolinstances = $DB->get_fieldset_sql('SELECT status FROM {enrol}
            WHERE courseid = :courseid AND enrol = :enrol ORDER BY customint1',
            ['courseid' => $this->course->id, 'enrol' => 'program'], 'id');
        $this->assertEquals([ENROL_INSTANCE_ENABLED, ENROL_INSTANCE_ENABLED], $enrolinstances);

        // Archive a program without using any APIs.
        $programid = $DB->get_field('tool_program', 'id', ['tenantid' => $this->tenants[0]->id]);
        $DB->update_record('tool_program', ['id' => $programid, 'archived' => 1]);

        // Run upgrade script.
        tool_program_upgrade_suspend_enrolments_in_archived_programs();

        // Enrolment method for the first program is now disabled.
        $enrolinstances = $DB->get_fieldset_sql('SELECT status FROM {enrol}
            WHERE courseid = :courseid AND enrol = :enrol ORDER BY customint1',
            ['courseid' => $this->course->id, 'enrol' => 'program'], 'id');
        $this->assertEquals([ENROL_INSTANCE_DISABLED, ENROL_INSTANCE_ENABLED], $enrolinstances);
    }
}