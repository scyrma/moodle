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
 * Class shared_space
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class shared_space
 *
 * @package    tool_certification
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_shared_space_testcase extends advanced_testcase {
    /** @var tool_certification_generator */
    protected $generator;
    /** @var tool_program_generator */
    protected $programgenerator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var tool_wp_generator */
    protected $wpgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->wpgenerator = self::getDataGenerator()->get_plugin_generator('tool_wp');
        $this->resetAfterTest();
    }

    /**
     * Test: When certification is created in shared space it has "shared==1", when in any other tenant it has "shared==0".
     *
     * @return void
     */
    public function test_create_certifications(): void {
        self::setAdminUser();

        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();
        $sharedprogram = $this->programgenerator->generate_program((object)['tenantid' => $sharedtenantid, 'idnumber' => 'PS']);

        // Create certification on shared space.
        $sharedcertification = \tool_certification\api::create_certification((object)[
            'fullname' => 'Shared',
            'program' => $sharedprogram->get('id'),
            'certification_tags' => ['hello', 'world'],
            'tenantid' => $sharedtenantid,
        ]);
        $this->assertEquals($sharedtenantid, $sharedcertification->get('tenantid'));
        $this->assertEquals(1, $sharedcertification->get('shared'));

        // Create certification on default tenant.
        $certification = \tool_certification\api::create_certification((object)[
            'fullname' => 'Certification1',
            'program' => $sharedprogram->get('id'),
            'certification_tags' => ['hello', 'world'],
            'tenantid' => $defaulttenantid,
        ]);
        $this->assertEquals($defaulttenantid, $certification->get('tenantid'));
        $this->assertEquals(0, $certification->get('shared'));
    }

    /**
     * Test: When shared certification is duplicated into a tenant it has "shared=0".
     *
     * @return void
     */
    public function test_duplicate_certifications(): void {
        self::setAdminUser();

        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $sharedtenantid = \tool_tenant\sharedspace::enable_shared_space();
        $sharedprogram = $this->programgenerator->generate_program((object)['tenantid' => $sharedtenantid, 'idnumber' => 'PS']);

        // Create certification on shared space.
        $sharedcertification = \tool_certification\api::create_certification((object)[
            'fullname' => 'Shared',
            'program' => $sharedprogram->get('id'),
            'certification_tags' => ['hello', 'world'],
            'tenantid' => $sharedtenantid,
        ]);
        $this->assertEquals($sharedtenantid, $sharedcertification->get('tenantid'));
        $this->assertEquals(1, $sharedcertification->get('shared'));

        // Duplicate shared certification into default tenant.
        $data = (object)[];
        $data->program = $sharedprogram->get('id');
        $data->fullname = 'Certification 2';
        $data->duplicatecertification = $sharedcertification->get('id');
        $data->tenantid = $defaulttenantid;
        $data->certification_tags = ['hello', 'world'];

        $certification = \tool_certification\api::create_certification((object)$data);
        $this->assertEquals($defaulttenantid, $certification->get('tenantid'));
        $this->assertEquals(0, $certification->get('shared'));
    }

    /**
     * Test: Given there is a shared program with default settings. Users from Tenant1 are allocated to the course group for
     * Tenant1 and users from Tenant2 are allocated to the course group for Tenant2.
     *
     * @return void
     */
    public function test_tenant_groups(): void {
        global $DB;

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant, [$user11, $user12, $user13]] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22, $user23]] = $this->tenantgenerator->create_tenant_and_users(3);

        $program = $this->programgenerator->generate_program_with_course((object)['tenantid' => $sharedspaceid]);
        $course = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset = $program->get_base_set();
        $programcourse = $this->programgenerator->add_course_to_set($course->id, $baseset->get('id'));
        $certification = $this->generator->generate_certification([
            'tenantid' => $sharedspaceid, 'program' => $program->get('id')]);

        $this->generator->allocate_users_to_certification($certification->get('id'),
            array_column([$user11, $user12, $user13], 'id'));
        $this->generator->allocate_users_to_certification($certification->get('id'),
            array_column([$user21, $user22, $user23], 'id'));

        $programuser11 = \tool_program\persistent\program_user::get_record([
            'certificationid' => $certification->get('id'), 'userid' => $user11->id]);
        $programuser12 = \tool_program\persistent\program_user::get_record([
            'certificationid' => $certification->get('id'), 'userid' => $user12->id]);
        $programuser13 = \tool_program\persistent\program_user::get_record([
            'certificationid' => $certification->get('id'), 'userid' => $user13->id]);
        $programuser21 = \tool_program\persistent\program_user::get_record([
            'certificationid' => $certification->get('id'), 'userid' => $user21->id]);
        $programuser22 = \tool_program\persistent\program_user::get_record([
            'certificationid' => $certification->get('id'), 'userid' => $user22->id]);
        $programuser23 = \tool_program\persistent\program_user::get_record([
            'certificationid' => $certification->get('id'), 'userid' => $user23->id]);
        $this->programgenerator->enrol_user_to_program_course($programcourse, $programuser11);
        $this->programgenerator->enrol_user_to_program_course($programcourse, $programuser12);
        $this->programgenerator->enrol_user_to_program_course($programcourse, $programuser13);
        $this->programgenerator->enrol_user_to_program_course($programcourse, $programuser21);
        $this->programgenerator->enrol_user_to_program_course($programcourse, $programuser22);
        $this->programgenerator->enrol_user_to_program_course($programcourse, $programuser23);

        $groups = $DB->get_records('groups');
        $this->assertCount(2, $groups);

        $tenantgroup1 = \tool_tenant\tenant_group::get_records([
            'component' => null,
            'area' => null,
            'itemid' => null,
            'tenantid' => $tenant->id,
            'courseid' => $course->id,
        ]);
        $this->assertCount(1, $tenantgroup1);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup1[0]->get('groupid')]));
        $groupmembers1 = $DB->get_records('groups_members', ['groupid' => $tenantgroup1[0]->get('groupid')]);
        $this->assertCount(3, $groupmembers1);
        $this->assertEqualsCanonicalizing([$user11->id, $user12->id, $user13->id], array_column($groupmembers1, 'userid'));

        $tenantgroup2 = \tool_tenant\tenant_group::get_records([
            'component' => null,
            'area' => null,
            'itemid' => null,
            'tenantid' => $tenant2->id,
            'courseid' => $course->id,
        ]);
        $this->assertCount(1, $tenantgroup2);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $tenantgroup2[0]->get('groupid')]));
        $groupmembers2 = $DB->get_records('groups_members', ['groupid' => $tenantgroup2[0]->get('groupid')]);
        $this->assertCount(3, $groupmembers2);
        $this->assertEqualsCanonicalizing([$user21->id, $user22->id, $user23->id], array_column($groupmembers2, 'userid'));
    }

    /**
     * Test: Given there is a shared certification that has "Create groups for this certification" users are allocated to the
     * course groups for "Certification-Tenant"
     *
     * @return void
     */
    public function test_certification_groups(): void {
        global $DB;

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant, [$user11, $user12, $user13]] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22, $user23]] = $this->tenantgenerator->create_tenant_and_users(3);

        $program = $this->programgenerator->generate_program_with_course((object)['tenantid' => $sharedspaceid]);
        $course = self::getDataGenerator()->create_course(['groupmode' => 1]);
        $baseset = $program->get_base_set();
        $programcourse = $this->programgenerator->add_course_to_set($course->id, $baseset->get('id'));
        $certification = $this->generator->generate_certification([
            'tenantid' => $sharedspaceid,
            'program' => $program->get('id'),
            'autocreategroups' => (\tool_program\api::GROUPS_CERTIFICATION + \tool_program\api::GROUPS_TENANT),
        ]);

        $this->generator->allocate_users_to_certification($certification->get('id'),
            array_column([$user11, $user12, $user13], 'id'));
        $this->generator->allocate_users_to_certification($certification->get('id'),
            array_column([$user21, $user22, $user23], 'id'));

        $programuser11 = \tool_program\persistent\program_user::get_record([
            'certificationid' => $certification->get('id'), 'userid' => $user11->id]);
        $programuser12 = \tool_program\persistent\program_user::get_record([
            'certificationid' => $certification->get('id'), 'userid' => $user12->id]);
        $programuser13 = \tool_program\persistent\program_user::get_record([
            'certificationid' => $certification->get('id'), 'userid' => $user13->id]);
        $programuser21 = \tool_program\persistent\program_user::get_record([
            'certificationid' => $certification->get('id'), 'userid' => $user21->id]);
        $programuser22 = \tool_program\persistent\program_user::get_record([
            'certificationid' => $certification->get('id'), 'userid' => $user22->id]);
        $programuser23 = \tool_program\persistent\program_user::get_record([
            'certificationid' => $certification->get('id'), 'userid' => $user23->id]);
        $this->programgenerator->enrol_user_to_program_course($programcourse, $programuser11);
        $this->programgenerator->enrol_user_to_program_course($programcourse, $programuser12);
        $this->programgenerator->enrol_user_to_program_course($programcourse, $programuser13);
        $this->programgenerator->enrol_user_to_program_course($programcourse, $programuser21);
        $this->programgenerator->enrol_user_to_program_course($programcourse, $programuser22);
        $this->programgenerator->enrol_user_to_program_course($programcourse, $programuser23);

        $groups = $DB->get_records('groups');
        $this->assertCount(2, $groups);

        $certificationgroup = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
            'itemid' => $certification->get('id'),
            'tenantid' => $tenant->id,
            'courseid' => $course->id,
        ]);

        $this->assertCount(1, $certificationgroup);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $certificationgroup[0]->get('groupid')]));
        $groupmembers = $DB->get_records('groups_members', ['groupid' => $certificationgroup[0]->get('groupid')]);
        $this->assertCount(3, $groupmembers);
        $this->assertEqualsCanonicalizing([$user11->id, $user12->id, $user13->id], array_column($groupmembers, 'userid'));

        $certificationgroup2 = \tool_tenant\tenant_group::get_records([
            'component' => 'tool_certification',
            'area' => 'tool_certification',
            'itemid' => $certification->get('id'),
            'tenantid' => $tenant2->id,
            'courseid' => $course->id,
        ]);
        $this->assertCount(1, $certificationgroup2);
        $this->assertCount(1, $DB->get_records('groups', ['id' => $certificationgroup2[0]->get('groupid')]));
        $groupmembers2 = $DB->get_records('groups_members', ['groupid' => $certificationgroup2[0]->get('groupid')]);
        $this->assertCount(3, $groupmembers2);
        $this->assertEqualsCanonicalizing([$user21->id, $user22->id, $user23->id], array_column($groupmembers2, 'userid'));
    }
}
