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

/**
 * Test for upgrade scripts in tool_certification
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Marina Glancy
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class tool_certification_upgradelib_testcase
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Marina Glancy
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
    public function setUp(): void {
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
        $this->assertCount(2, $DB->get_records_select('event',
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
        $this->assertCount(2, $DB->get_records_select('event',
            'eventtype LIKE :eventtype AND instance = :instance',
            ['eventtype' => 'tool_certification%', 'instance' => $certificationid]));
        // TODO WP-1577 the number of events is not correct currently, for user who completed the certification there should not
        // be a "due date" event, for user who has not completed there should not be "expiries" event.
    }

    /**
     * Tests for function tool_certification_remove_certification_tenant_groups_to_nonexisting_certification()
     */
    public function test_remove_certification_tenant_groups_to_nonexisting_certification() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/certification/db/upgradelib.php');
        self::setAdminUser();

        // Sanity check.
        $this->assertCount(0, \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification'
        ]));

        $tenant = $this->tenantgenerator->create_tenant();
        $user1a = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1a->id, $tenant->id);

        // Program where we will test changes.
        $program1 = $this->programgenerator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups',
        ]);
        $program1id = $program1->get('id');
        $course = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset = $program1->get_base_set();
        $programcourse = $this->programgenerator->add_course_to_set($course->id, $baseset->get('id'));
        $programuser1a = $this->programgenerator->allocate_user_to_program($program1->get('id'), $user1a->id);
        $this->programgenerator->enrol_user_to_program_course($programcourse, $programuser1a);

        // Certification to check certification groups using program2.
        $certification1 = $this->generator->generate_certification([
            'program' => $program1->get('id'),
            'autocreategroups' => (\tool_program\api::GROUPS_CERTIFICATION + \tool_program\api::GROUPS_TENANT),
        ]);
        $certificationuser1a = $this->generator->allocate_user($user1a->id, $certification1->get('id'));
        $programusercert1a = \tool_program\persistent\program_user::get_record([
            'userid' => $user1a->id,
            'certificationid' => $certification1->get('id'),
        ]);
        $this->programgenerator->enrol_user_to_program_course($programcourse, $programusercert1a);
        $certification1id = $certification1->get('id');

        // There should be 1 tenant group for certification1 associated to one group.
        $tenantgroup = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
            'itemid' => $certification1id,
        ]);
        $this->assertCount(1, $tenantgroup);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup[0]->get('groupid')]));
        $groupmembers = $DB->get_records('groups_members', ['groupid' => $tenantgroup[0]->get('groupid')]);
        $this->assertCount(1, $groupmembers);
        $groupid = $tenantgroup[0]->get('groupid');

        // Delete certification without using API method.
        $certification1->delete();

        tool_certification_remove_certification_tenant_groups_to_nonexisting_certification();

        // There should be no tenant group and no groups for program1.
        $tenantgroup1 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
            'itemid' => $certification1id,
        ]);
        $this->assertCount(0, $tenantgroup1);
        $this->assertCount(0, $DB->get_records('groups', ['id' => $groupid]));
    }

    /**
     * Tests for function tool_certification_upgrade_remove_users_from_orphan_groups()
     *
     * Step #2.2b Remove users from certification component-specific tenant groups that are suspended in related certification.
     */
    public function test_upgrade_remove_users_from_orphaned_groups_2_2_b() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/certification/db/upgradelib.php');
        self::setAdminUser();

        // Sanity check.
        $this->assertCount(0, \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program'
        ]));

        $this->assertCount(0, \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification'
        ]));

        $tenant = $this->tenantgenerator->create_tenant();
        $user1a = self::getDataGenerator()->create_user();
        $user1b = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1a->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user1b->id, $tenant->id);

        $program2 = $this->programgenerator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups 2',
            'autocreategroups' => (\tool_program\api::GROUPS_PROGRAM + \tool_program\api::GROUPS_TENANT),
        ]);
        $course2 = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset2 = $program2->get_base_set();
        $programcourse2 = $this->programgenerator->add_course_to_set($course2->id, $baseset2->get('id'));

        // Certification to check certification groups using program2.
        $certification1 = $this->generator->generate_certification([
            'program' => $program2->get('id'),
            'autocreategroups' => (\tool_program\api::GROUPS_CERTIFICATION + \tool_program\api::GROUPS_TENANT),
        ]);
        $certificationuser1a = $this->generator->allocate_user($user1a->id, $certification1->get('id'));
        $certificationuser1b = $this->generator->allocate_user($user1b->id, $certification1->get('id'));
        $programusercert1a = \tool_program\persistent\program_user::get_record([
            'userid' => $user1a->id,
            'certificationid' => $certification1->get('id'),
        ]);
        $programusercert1b = \tool_program\persistent\program_user::get_record([
            'userid' => $user1b->id,
            'certificationid' => $certification1->get('id'),
        ]);
        $this->programgenerator->enrol_user_to_program_course($programcourse2, $programusercert1a);
        $this->programgenerator->enrol_user_to_program_course($programcourse2, $programusercert1b);

        // Generate a second program and certification.
        $program3 = $this->programgenerator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups 3',
            'autocreategroups' => (\tool_program\api::GROUPS_PROGRAM + \tool_program\api::GROUPS_TENANT),
        ]);
        $baseset3 = $program3->get_base_set();
        $programcourse2b = $this->programgenerator->add_course_to_set($course2->id, $baseset3->get('id'));

        // Certification to check certification groups using program3.
        $certification2 = $this->generator->generate_certification([
            'program' => $program3->get('id'),
            'autocreategroups' => (\tool_program\api::GROUPS_CERTIFICATION + \tool_program\api::GROUPS_TENANT),
        ]);
        $certificationuser2a = $this->generator->allocate_user($user1a->id, $certification2->get('id'));
        $certificationuser2b = $this->generator->allocate_user($user1b->id, $certification2->get('id'));
        $programusercert2a = \tool_program\persistent\program_user::get_record([
            'userid' => $user1a->id,
            'certificationid' => $certification2->get('id'),
        ]);
        $programusercert2b = \tool_program\persistent\program_user::get_record([
            'userid' => $user1b->id,
            'certificationid' => $certification2->get('id'),
        ]);
        $this->programgenerator->enrol_user_to_program_course($programcourse2b, $programusercert2a);
        $this->programgenerator->enrol_user_to_program_course($programcourse2b, $programusercert2b);

        // There should be a tenant group for certification1 associated to a group and with 2 group members.
        $tenantgroup3 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
            'itemid' => $certification1->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup3);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup3[0]->get('groupid')]));
        $groupmembers3 = $DB->get_records('groups_members', ['groupid' => $tenantgroup3[0]->get('groupid')]);
        $this->assertCount(2, $groupmembers3);
        $this->assertEqualsCanonicalizing([$user1a->id, $user1b->id], array_column($groupmembers3, 'userid'));

        // There should be a tenant group for certification1 associated to a group and with 2 group members.
        $tenantgroup4 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
            'itemid' => $certification2->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup4);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup4[0]->get('groupid')]));
        $groupmembers4 = $DB->get_records('groups_members', ['groupid' => $tenantgroup4[0]->get('groupid')]);
        $this->assertCount(2, $groupmembers4);
        $this->assertEqualsCanonicalizing([$user1a->id, $user1b->id], array_column($groupmembers4, 'userid'));

        // Suspend user2 allocation to program without using API.
        $certificationuser1b->set('status', \tool_certification\constants::STATUS_SUSPENDED);
        $certificationuser1b->update();
        $programusercert1b->set('status', \tool_program\constants::STATUS_SUSPENDED);
        $programusercert1b->update();

        tool_certification_upgrade_remove_users_from_orphan_groups();

        // There should be another tenant group for certification1 associated to another group and with 0 group members.
        $tenantgroup3 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
            'itemid' => $certification1->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup3);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup3[0]->get('groupid')]));
        $groupmembers3 = $DB->get_records('groups_members', ['groupid' => $tenantgroup3[0]->get('groupid')]);
        $this->assertCount(1, $groupmembers3);
        $this->assertEqualsCanonicalizing([$user1a->id], array_column($groupmembers3, 'userid'));

        // There should be another tenant group for certification2 associated to another group and with 2 group members.
        $tenantgroup4 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
            'itemid' => $certification2->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup4);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup4[0]->get('groupid')]));
        $groupmembers4 = $DB->get_records('groups_members', ['groupid' => $tenantgroup4[0]->get('groupid')]);
        $this->assertCount(2, $groupmembers4);
        $this->assertEqualsCanonicalizing([$user1a->id, $user1b->id], array_column($groupmembers4, 'userid'));
    }

    /**
     * Tests for function tool_certification_upgrade_remove_users_from_orphan_groups()
     *
     * Step #2.4 Remove users from certification component-specific tenant groups that are not allocated to related certification.
     */
    public function test_upgrade_remove_users_from_orphaned_groups_2_4() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/certification/db/upgradelib.php');
        self::setAdminUser();

        // Sanity check.
        $this->assertCount(0, \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program'
        ]));

        $this->assertCount(0, \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification'
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

        $program2 = $this->programgenerator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups 2',
            'autocreategroups' => (\tool_program\api::GROUPS_PROGRAM + \tool_program\api::GROUPS_TENANT),
        ]);
        $course2 = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset2 = $program2->get_base_set();
        $programcourse2 = $this->programgenerator->add_course_to_set($course2->id, $baseset2->get('id'));

        // Certification to check certification groups using program2.
        $certification1 = $this->generator->generate_certification([
            'program' => $program2->get('id'),
            'autocreategroups' => (\tool_program\api::GROUPS_CERTIFICATION + \tool_program\api::GROUPS_TENANT),
        ]);
        $certificationuser1a = $this->generator->allocate_user($user1a->id, $certification1->get('id'));
        $certificationuser1b = $this->generator->allocate_user($user1b->id, $certification1->get('id'));
        $programusercert1a = \tool_program\persistent\program_user::get_record([
            'userid' => $user1a->id,
            'certificationid' => $certification1->get('id'),
        ]);
        $programusercert1b = \tool_program\persistent\program_user::get_record([
            'userid' => $user1b->id,
            'certificationid' => $certification1->get('id'),
        ]);
        $this->programgenerator->enrol_user_to_program_course($programcourse2, $programusercert1a);
        $this->programgenerator->enrol_user_to_program_course($programcourse2, $programusercert1b);

        // There should be another tenant group for certification1 associated to another group and with 3 group members.
        $tenantgroup3 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
            'itemid' => $certification1->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup3);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup3[0]->get('groupid')]));
        $groupmembers3 = $DB->get_records('groups_members', ['groupid' => $tenantgroup3[0]->get('groupid')]);
        $this->assertCount(2, $groupmembers3);
        $this->assertEqualsCanonicalizing([$user1a->id, $user1b->id], array_column($groupmembers3, 'userid'));

        // Delete manually user1 allocation to certification without using API.
        $certificationuser1a->delete();
        $programusercert1a->delete();

        tool_certification_upgrade_remove_users_from_orphan_groups();

        // There should be another tenant group for certification1 associated to another group and with 0 group members.
        $tenantgroup3 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
            'itemid' => $certification1->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup3);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup3[0]->get('groupid')]));
        $groupmembers3 = $DB->get_records('groups_members', ['groupid' => $tenantgroup3[0]->get('groupid')]);
        $this->assertCount(1, $groupmembers3);
        $this->assertEqualsCanonicalizing([$user1b->id], array_column($groupmembers3, 'userid'));
    }

    /**
     * Tests for function tool_certification_upgrade_remove_users_from_orphan_groups()
     *
     * Step #1.5 Remove certification component-specific tenant groups that belong to archived certification.
     */
    public function test_upgrade_remove_users_from_orphaned_groups_1_5() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/certification/db/upgradelib.php');
        self::setAdminUser();

        // Sanity check.
        $this->assertCount(0, \tool_tenant\tenant_group::get_records([
            'component' => 'tool_program',
            'area' => 'tool_program',
        ]));

        $this->assertCount(0, \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
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

        $program2 = $this->programgenerator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups 2',
            'autocreategroups' => (\tool_program\api::GROUPS_PROGRAM + \tool_program\api::GROUPS_TENANT),
        ]);
        $course2 = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset2 = $program2->get_base_set();
        $programcourse2 = $this->programgenerator->add_course_to_set($course2->id, $baseset2->get('id'));

        // Certification to check certification groups using program2.
        $certification1 = $this->generator->generate_certification([
            'program' => $program2->get('id'),
            'autocreategroups' => (\tool_program\api::GROUPS_CERTIFICATION + \tool_program\api::GROUPS_TENANT),
        ]);
        $certificationuser1a = $this->generator->allocate_user($user1a->id, $certification1->get('id'));
        $certificationuser1b = $this->generator->allocate_user($user1b->id, $certification1->get('id'));
        $certificationuser1c = $this->generator->allocate_user($user1c->id, $certification1->get('id'));
        $programusercert1a = \tool_program\persistent\program_user::get_record([
            'userid' => $user1a->id,
            'certificationid' => $certification1->get('id'),
        ]);
        $programusercert1b = \tool_program\persistent\program_user::get_record([
            'userid' => $user1b->id,
            'certificationid' => $certification1->get('id'),
        ]);
        $programusercert1c = \tool_program\persistent\program_user::get_record([
            'userid' => $user1c->id,
            'certificationid' => $certification1->get('id'),
        ]);
        $this->programgenerator->enrol_user_to_program_course($programcourse2, $programusercert1a);
        $this->programgenerator->enrol_user_to_program_course($programcourse2, $programusercert1b);
        $this->programgenerator->enrol_user_to_program_course($programcourse2, $programusercert1c);

        // Create another program and certification to test a certification with GROUPS_AS_IN_PROGRAMS.
        $program3 = $this->programgenerator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant->id,
            'fullname' => 'Test program groups 3',
            'autocreategroups' => (\tool_program\api::GROUPS_PROGRAM + \tool_program\api::GROUPS_TENANT),
        ]);
        $course3 = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset3 = $program3->get_base_set();
        $programcourse3 = $this->programgenerator->add_course_to_set($course3->id, $baseset3->get('id'));

        // Certification to check certification groups using program3.
        $certification2 = $this->generator->generate_certification([
            'program' => $program3->get('id'),
            'autocreategroups' => (\tool_program\api::GROUPS_CERTIFICATION + \tool_program\api::GROUPS_TENANT),
        ]);
        $certificationuser2a = $this->generator->allocate_user($user1a->id, $certification2->get('id'));
        $certificationuser2b = $this->generator->allocate_user($user1b->id, $certification2->get('id'));
        $certificationuser2c = $this->generator->allocate_user($user1c->id, $certification2->get('id'));
        $programusercert2a = \tool_program\persistent\program_user::get_record([
            'userid' => $user1a->id,
            'certificationid' => $certification2->get('id'),
        ]);
        $programusercert2b = \tool_program\persistent\program_user::get_record([
            'userid' => $user1b->id,
            'certificationid' => $certification2->get('id'),
        ]);
        $programusercert2c = \tool_program\persistent\program_user::get_record([
            'userid' => $user1c->id,
            'certificationid' => $certification2->get('id'),
        ]);
        $this->programgenerator->enrol_user_to_program_course($programcourse3, $programusercert2a);
        $this->programgenerator->enrol_user_to_program_course($programcourse3, $programusercert2b);
        $this->programgenerator->enrol_user_to_program_course($programcourse3, $programusercert2c);

        // There should be a tenant group for certification1 associated to a group and with 3 group members.
        $tenantgroup3 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
            'itemid' => $certification1->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup3);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup3[0]->get('groupid')]));
        $groupmembers3 = $DB->get_records('groups_members', ['groupid' => $tenantgroup3[0]->get('groupid')]);
        $this->assertCount(3, $groupmembers3);
        $this->assertEqualsCanonicalizing([$user1a->id, $user1b->id, $user1c->id], array_column($groupmembers3, 'userid'));

        // There should be another tenant group for certification2 associated to another group and with 3 group members.
        $tenantgroup4 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
            'itemid' => $certification2->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup4);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup4[0]->get('groupid')]));
        $groupmembers4 = $DB->get_records('groups_members', ['groupid' => $tenantgroup4[0]->get('groupid')]);
        $this->assertCount(3, $groupmembers4);
        $this->assertEqualsCanonicalizing([$user1a->id, $user1b->id, $user1c->id], array_column($groupmembers4, 'userid'));

        // Set certification1 to be archived without using API.
        $certification1->set('archived', 1);
        $certification1->update();

        tool_certification_upgrade_remove_users_from_orphan_groups();

        // There should be another tenant group for certification1 associated to another group and with 0 group members.
        $tenantgroup3 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
            'itemid' => $certification1->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup3);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup3[0]->get('groupid')]));
        $groupmembers3 = $DB->get_records('groups_members', ['groupid' => $tenantgroup3[0]->get('groupid')]);
        $this->assertCount(0, $groupmembers3);

        // There should be another tenant group for certification2 associated to another group and with 3 group members.
        $tenantgroup4 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
            'itemid' => $certification2->get('id'),
        ]);
        $this->assertCount(1, $tenantgroup4);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup4[0]->get('groupid')]));
        $groupmembers4 = $DB->get_records('groups_members', ['groupid' => $tenantgroup4[0]->get('groupid')]);
        $this->assertCount(3, $groupmembers4);
        // We manually deallocated $user1a and suspended $user1b. Only user $user1c should remain in the group.
        $this->assertEqualsCanonicalizing([$user1a->id, $user1b->id, $user1c->id], array_column($groupmembers4, 'userid'));
    }
}
