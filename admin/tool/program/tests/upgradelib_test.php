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
use stdClass;
use tool_program_generator;
use tool_tenant_generator;

/**
 * Test for upgrade scripts in tool_program
 *
 * @package   tool_program
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Marina Glancy
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class upgradelib_test extends advanced_testcase {
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
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();

        $this->course = $this->generator->generate_course_with_completion_self();
        for ($i = 0; $i < 2; $i++) {
            [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);

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
     * Load our required libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/program/db/upgradelib.php');
    }

    /**
     * Tests for function tool_program_upgrade_remove_orphaned_programs()
     *
     * @covers ::tool_program_upgrade_remove_orphaned_programs()
     */
    public function test_upgrade_remove_orphaned_programs(): void {
        global $DB;

        // Make sure the program exists in the first tenant and also sets, users and completion.
        $programid = $DB->get_field('tool_program', 'id', ['tenantid' => $this->tenants[0]->id]);
        $this->assertNotEmpty($programid);
        $programsets = $DB->get_fieldset_select('tool_program_sets', 'id', 'programid = :programid',
            ['programid' => $programid]);
        [$setsql, $setparams] = $DB->get_in_or_equal($programsets);
        $this->assertCount(1, $DB->get_records('tool_program', ['id' => $programid]));
        $this->assertCount(1, $DB->get_records('tool_program_sets', ['programid' => $programid]));
        $this->assertCount(2, $DB->get_records('tool_program_users', ['programid' => $programid]));
        $this->assertCount(1, $DB->get_records_select('tool_program_courses', 'setid' . $setsql, $setparams));
        $this->assertCount(1, $DB->get_records_select('tool_program_set_completion', 'setid' . $setsql, $setparams));
        $this->assertCount(3, $DB->get_records_select('event',
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
        [$setsql, $setparams] = $DB->get_in_or_equal($programsets);
        $this->assertCount(1, $DB->get_records('tool_program', ['id' => $programid]));
        $this->assertCount(1, $DB->get_records('tool_program_sets', ['programid' => $programid]));
        $this->assertCount(2, $DB->get_records('tool_program_users', ['programid' => $programid]));
        $this->assertCount(1, $DB->get_records_select('tool_program_courses', 'setid' . $setsql, $setparams));
        $this->assertCount(1, $DB->get_records_select('tool_program_set_completion', 'setid' . $setsql, $setparams));
        $this->assertCount(3, $DB->get_records_select('event',
            'eventtype LIKE :eventtype AND instance = :instance',
            ['eventtype' => 'tool_program%', 'instance' => $programid]));
        // TODO WP-1577 the number of events is not correct currently, for user who completed the program there should be no
        // events in the calendar. This test should be changed when this is fixed, remove this comment then.
    }

    /**
     * Tests for function tool_program_upgrade_suspend_enrolments_in_archived_programs()
     *
     * @covers ::tool_program_upgrade_suspend_enrolments_in_archived_programs()
     */
    public function test_upgrade_suspend_enrolments_in_archived_programs(): void {
        global $DB;

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

    /**
     * Tests for function tool_program_upgrade_remove_users_from_orphan_groups()
     *
     * Step #2.1 Remove users from program component-specific tenant groups that are not allocated to related program.
     *
     * @covers ::tool_program_upgrade_remove_users_from_orphan_groups()
     */
    public function test_upgrade_remove_users_from_orphaned_groups_2_1(): void {
        global $DB;
        self::setAdminUser();

        // Sanity check.
        $this->assertCount(0, \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program'
        ]));

        $tenant = $this->tenantgenerator->create_tenant();
        $user1a = self::getDataGenerator()->create_user();
        $user1b = self::getDataGenerator()->create_user();
        $user2a = self::getDataGenerator()->create_user();
        $user2b = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1a->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user1b->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2a->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2b->id, $tenant->id);

        // Program where we will test changes.
        $program1 = $this->generator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups',
            'autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT),
        ]);
        $course = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset = $program1->get_base_set();
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));
        $programuser1a = $this->generator->allocate_user_to_program($program1->get('id'), $user1a->id);
        $programuser1b = $this->generator->allocate_user_to_program($program1->get('id'), $user1b->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1a);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1b);

        // Safe program where group members should not be modified.
        $program2 = $this->generator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups 2',
            'autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT),
        ]);
        $course2 = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset2 = $program2->get_base_set();
        $programcourse2 = $this->generator->add_course_to_set($course2->id, $baseset2->get('id'));
        $programuser2a = $this->generator->allocate_user_to_program($program2->get('id'), $user2a->id);
        $programuser2b = $this->generator->allocate_user_to_program($program2->get('id'), $user2b->id);
        $this->generator->enrol_user_to_program_course($programcourse2, $programuser2a);
        $this->generator->enrol_user_to_program_course($programcourse2, $programuser2b);

        // There should be 1 tenant group for program1 associated to one group and with 2 group members.
        $tenantgroup1 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program1->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup1);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup1[0]->get('groupid')]));
        $groupmembers = $DB->get_records('groups_members', ['groupid' => $tenantgroup1[0]->get('groupid')]);
        $this->assertCount(2, $groupmembers);
        $this->assertEqualsCanonicalizing([$user1a->id, $user1b->id], array_column($groupmembers, 'userid'));

        // There should be another tenant group for program2 associated to another group and with 2 group members.
        $tenantgroup2 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program2->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup2);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup2[0]->get('groupid')]));
        $groupmembers2 = $DB->get_records('groups_members', ['groupid' => $tenantgroup2[0]->get('groupid')]);
        $this->assertCount(2, $groupmembers2);
        $this->assertEqualsCanonicalizing([$user2a->id, $user2b->id], array_column($groupmembers2, 'userid'));

        // Assert they belog to different groups.
        $this->assertNotEquals($tenantgroup1[0]->get('groupid'), $tenantgroup2[0]->get('groupid'));

        // Delete manually user1 allocation to program without using API.
        $programuser1a->delete();

        tool_program_upgrade_remove_users_from_orphan_groups();

        // There should be 1 tenant group for program1 associated to one group and with 1 group member.
        $tenantgroup1 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program1->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup1);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup1[0]->get('groupid')]));
        $groupmembers = $DB->get_records('groups_members', ['groupid' => $tenantgroup1[0]->get('groupid')]);
        $this->assertCount(1, $groupmembers);
        $this->assertEqualsCanonicalizing([$user1b->id], array_column($groupmembers, 'userid'));

        // There should be another tenant group for program2 associated to one group and with same 2 group members.
        $tenantgroup2 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program2->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup2);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup2[0]->get('groupid')]));
        $groupmembers2 = $DB->get_records('groups_members', ['groupid' => $tenantgroup2[0]->get('groupid')]);
        $this->assertCount(2, $groupmembers2);
        $this->assertEqualsCanonicalizing([$user2a->id, $user2b->id], array_column($groupmembers2, 'userid'));
    }

    /**
     * Tests for function tool_program_upgrade_remove_users_from_orphan_groups()
     *
     * Step #2.2 Remove users from program component-specific tenant groups that are suspended in related program.
     *
     * @covers ::tool_program_upgrade_remove_users_from_orphan_groups()
     */
    public function test_upgrade_remove_users_from_orphaned_groups_2_2(): void {
        global $DB;
        self::setAdminUser();

        // Sanity check.
        $this->assertCount(0, \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program'
        ]));

        $tenant = $this->tenantgenerator->create_tenant();
        $user1a = self::getDataGenerator()->create_user();
        $user1b = self::getDataGenerator()->create_user();
        $user2a = self::getDataGenerator()->create_user();
        $user2b = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1a->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user1b->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2a->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2b->id, $tenant->id);

        // Program where we will test changes.
        $program1 = $this->generator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups',
            'autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT),
        ]);
        $course = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset = $program1->get_base_set();
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));
        $programuser1a = $this->generator->allocate_user_to_program($program1->get('id'), $user1a->id);
        $programuser1b = $this->generator->allocate_user_to_program($program1->get('id'), $user1b->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1a);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1b);

        // Safe program where group members should not be modified.
        $program2 = $this->generator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups 2',
            'autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT),
        ]);
        $course2 = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset2 = $program2->get_base_set();
        $programcourse2 = $this->generator->add_course_to_set($course2->id, $baseset2->get('id'));
        $programuser2a = $this->generator->allocate_user_to_program($program2->get('id'), $user2a->id);
        $programuser2b = $this->generator->allocate_user_to_program($program2->get('id'), $user2b->id);
        $this->generator->enrol_user_to_program_course($programcourse2, $programuser2a);
        $this->generator->enrol_user_to_program_course($programcourse2, $programuser2b);

        // There should be 1 tenant group for program1 associated to one group and with 2 group members.
        $tenantgroup1 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program1->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup1);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup1[0]->get('groupid')]));
        $groupmembers = $DB->get_records('groups_members', ['groupid' => $tenantgroup1[0]->get('groupid')]);
        $this->assertCount(2, $groupmembers);
        $this->assertEqualsCanonicalizing([$user1a->id, $user1b->id], array_column($groupmembers, 'userid'));

        // There should be another tenant group for program2 associated to another group and with 2 group members.
        $tenantgroup2 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program2->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup2);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup2[0]->get('groupid')]));
        $groupmembers2 = $DB->get_records('groups_members', ['groupid' => $tenantgroup2[0]->get('groupid')]);
        $this->assertCount(2, $groupmembers2);
        $this->assertEqualsCanonicalizing([$user2a->id, $user2b->id], array_column($groupmembers2, 'userid'));

        // Assert they belog to different groups.
        $this->assertNotEquals($tenantgroup1[0]->get('groupid'), $tenantgroup2[0]->get('groupid'));

        // Suspend user2 allocation to program without using API.
        $programuser1b->set('status', \tool_program\constants::STATUS_SUSPENDED);
        $programuser1b->update();

        tool_program_upgrade_remove_users_from_orphan_groups();

        // There should be 1 tenant group for program1 associated to one group and with 1 group members.
        $tenantgroup1 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program1->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup1);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup1[0]->get('groupid')]));
        $groupmembers = $DB->get_records('groups_members', ['groupid' => $tenantgroup1[0]->get('groupid')]);
        $this->assertCount(1, $groupmembers);
        $this->assertEqualsCanonicalizing([$user1a->id], array_column($groupmembers, 'userid'));

        // There should be another tenant group for program2 associated to one group and with same 2 group members.
        $tenantgroup2 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program2->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup2);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup2[0]->get('groupid')]));
        $groupmembers2 = $DB->get_records('groups_members', ['groupid' => $tenantgroup2[0]->get('groupid')]);
        $this->assertCount(2, $groupmembers2);
        $this->assertEqualsCanonicalizing([$user2a->id, $user2b->id], array_column($groupmembers2, 'userid'));
    }

    /**
     * Tests for function tool_program_upgrade_remove_users_from_orphan_groups()
     *
     * Step #1.4 Remove program component-specific tenant groups that belong to archived program.
     *
     * @covers ::tool_program_upgrade_remove_users_from_orphan_groups()
     */
    public function test_upgrade_remove_users_from_orphaned_groups_1_4(): void {
        global $DB;
        self::setAdminUser();

        // Sanity check.
        $this->assertCount(0, \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program'
        ]));

        $tenant = $this->tenantgenerator->create_tenant();
        $user1a = self::getDataGenerator()->create_user();
        $user1b = self::getDataGenerator()->create_user();
        $user1c = self::getDataGenerator()->create_user();
        $user2a = self::getDataGenerator()->create_user();
        $user2b = self::getDataGenerator()->create_user();
        $user2c = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1a->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user1b->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user1c->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2a->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2b->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2c->id, $tenant->id);

        // Program where we will test changes.
        $program1 = $this->generator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups',
            'autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT),
        ]);
        $course = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset = $program1->get_base_set();
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));
        $programuser1a = $this->generator->allocate_user_to_program($program1->get('id'), $user1a->id);
        $programuser1b = $this->generator->allocate_user_to_program($program1->get('id'), $user1b->id);
        $programuser1c = $this->generator->allocate_user_to_program($program1->get('id'), $user1c->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1a);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1b);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1c);

        // Safe program where group members should not be modified.
        $program2 = $this->generator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups 2',
            'autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT),
        ]);
        $course2 = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset2 = $program2->get_base_set();
        $programcourse2 = $this->generator->add_course_to_set($course2->id, $baseset2->get('id'));
        $programuser2a = $this->generator->allocate_user_to_program($program2->get('id'), $user2a->id);
        $programuser2b = $this->generator->allocate_user_to_program($program2->get('id'), $user2b->id);
        $programuser2c = $this->generator->allocate_user_to_program($program2->get('id'), $user2c->id);
        $this->generator->enrol_user_to_program_course($programcourse2, $programuser2a);
        $this->generator->enrol_user_to_program_course($programcourse2, $programuser2b);
        $this->generator->enrol_user_to_program_course($programcourse2, $programuser2c);

        // There should be 1 tenant group for program1 associated to one group and with 3 group members.
        $tenantgroup1 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program1->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup1);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup1[0]->get('groupid')]));
        $groupmembers = $DB->get_records('groups_members', ['groupid' => $tenantgroup1[0]->get('groupid')]);
        $this->assertCount(3, $groupmembers);
        $this->assertEqualsCanonicalizing([$user1a->id, $user1b->id, $user1c->id], array_column($groupmembers, 'userid'));

        // There should be another tenant group for program2 associated to another group and with 3 group members.
        $tenantgroup2 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program2->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup2);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup2[0]->get('groupid')]));
        $groupmembers2 = $DB->get_records('groups_members', ['groupid' => $tenantgroup2[0]->get('groupid')]);
        $this->assertCount(3, $groupmembers2);
        $this->assertEqualsCanonicalizing([$user2a->id, $user2b->id, $user2c->id], array_column($groupmembers2, 'userid'));

        // Assert they belog to different groups.
        $this->assertNotEquals($tenantgroup1[0]->get('groupid'), $tenantgroup2[0]->get('groupid'));

        // Set program1 to be archived without using API.
        $program1->set('archived', 1);
        $program1->update();

        tool_program_upgrade_remove_users_from_orphan_groups();

        // There should be 1 tenant group for program1 associated to one group and with 0 group members.
        $tenantgroup1 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program1->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup1);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup1[0]->get('groupid')]));
        $groupmembers = $DB->get_records('groups_members', ['groupid' => $tenantgroup1[0]->get('groupid')]);
        $this->assertCount(0, $groupmembers);

        // There should be another tenant group for program2 associated to one group and with same 3 group members.
        $tenantgroup2 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program2->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup2);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup2[0]->get('groupid')]));
        $groupmembers2 = $DB->get_records('groups_members', ['groupid' => $tenantgroup2[0]->get('groupid')]);
        $this->assertCount(3, $groupmembers2);
        $this->assertEqualsCanonicalizing([$user2a->id, $user2b->id, $user2c->id], array_column($groupmembers2, 'userid'));
    }

    /**
     * Tests for function tool_program_remove_program_tenant_groups_to_nonexisting_program()
     *
     * @covers ::tool_program_remove_program_tenant_groups_to_nonexisting_program
     */
    public function test_remove_program_tenant_groups_to_nonexisting_program(): void {
        global $DB;
        self::setAdminUser();

        // Sanity check.
        $this->assertCount(0, \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program'
        ]));

        $tenant = $this->tenantgenerator->create_tenant();
        $user1a = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1a->id, $tenant->id);

        // Program where we will test changes.
        $program1 = $this->generator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups',
            'autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT),
        ]);
        $program1id = $program1->get('id');
        $course = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset = $program1->get_base_set();
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));
        $programuser1a = $this->generator->allocate_user_to_program($program1->get('id'), $user1a->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1a);

        // Program that should remain without changes.
        $program2 = $this->generator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups 2',
            'autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT),
        ]);
        $program2id = $program2->get('id');
        $course2 = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset2 = $program2->get_base_set();
        $programcourse2 = $this->generator->add_course_to_set($course2->id, $baseset2->get('id'));
        $programuser2a = $this->generator->allocate_user_to_program($program2->get('id'), $user1a->id);
        $this->generator->enrol_user_to_program_course($programcourse2, $programuser2a);

        // There should be 1 tenant group for program1 associated to one group and with 3 group members.
        $tenantgroup = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program1id,
        ]);
        $this->assertCount(1, $tenantgroup);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup[0]->get('groupid')]));
        $groupmembers = $DB->get_records('groups_members', ['groupid' => $tenantgroup[0]->get('groupid')]);
        $this->assertCount(1, $groupmembers);
        $groupid = $tenantgroup[0]->get('groupid');

        // There should be 1 tenant group for program2 associated to one group and with 3 group members.
        $tenantgroup2 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program2id,
        ]);
        $this->assertCount(1, $tenantgroup2);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup2[0]->get('groupid')]));
        $groupmembers2 = $DB->get_records('groups_members', ['groupid' => $tenantgroup2[0]->get('groupid')]);
        $this->assertCount(1, $groupmembers2);

        // Delete program without using API method.
        $program1->delete();

        tool_program_remove_program_tenant_groups_to_nonexisting_program();

        // There should be no tenant group and no groups for program1.
        $tenantgroup1 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program1id,
        ]);
        $this->assertCount(0, $tenantgroup1);
        $this->assertCount(0, $DB->get_records('groups', ['id' => $groupid]));

        // There should be 1 tenant group for program2 associated to one group.
        $tenantgroup2 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
            'itemid' => $program2id,
        ]);
        $this->assertCount(1, $tenantgroup2);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup2[0]->get('groupid')]));
    }

    /**
     * Tests for function tool_program_remove_enrolments_to_non_existing_programs()
     *
     * @covers ::tool_program_remove_enrolments_to_non_existing_programs
     */
    public function test_remove_enrolments_to_non_existing_programs(): void {
        global $DB;
        self::setAdminUser();

        // Sanity check.
        $this->assertCount(0, \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program'
        ]));

        $tenant = $this->tenantgenerator->create_tenant();
        $user1a = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1a->id, $tenant->id);

        // Program where we will test changes.
        $program1 = $this->generator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups',
            'autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT),
        ]);
        $course = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset = $program1->get_base_set();
        $programcourse = $this->generator->add_course_to_set($course->id, $baseset->get('id'));
        $programuser1a = $this->generator->allocate_user_to_program($program1->get('id'), $user1a->id);
        $this->generator->enrol_user_to_program_course($programcourse, $programuser1a);

        $program2 = $this->generator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups 2',
            'autocreategroups' => (api::GROUPS_PROGRAM + api::GROUPS_TENANT),
        ]);
        $course2 = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset2 = $program2->get_base_set();
        $programcourse2 = $this->generator->add_course_to_set($course2->id, $baseset2->get('id'));
        $programuser2a = $this->generator->allocate_user_to_program($program2->get('id'), $user1a->id);
        $this->generator->enrol_user_to_program_course($programcourse2, $programuser2a);

        // Both enrolment methods are active.
        $this->assertCount(1, $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'program']));
        $this->assertCount(1, $DB->get_records('enrol', ['courseid' => $course2->id, 'enrol' => 'program']));

        $program1->delete();

        tool_program_remove_enrolments_to_non_existing_programs();

        // Enrolment for program1 has been deleted.
        $this->assertCount(0, $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'program']));
        $this->assertCount(1, $DB->get_records('enrol', ['courseid' => $course2->id, 'enrol' => 'program']));
    }
}
