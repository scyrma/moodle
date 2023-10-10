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

declare(strict_types=1);

namespace tool_tenant\reportbuilder\local;

use core_badges_generator;
use tool_tenant_generator;
use core_reportbuilder_testcase;
use core_reportbuilder_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->dirroot}/reportbuilder/tests/helpers.php");

/**
 * Test for tenant callbacks for core reportbuilder
 *
 * @package    tool_tenant
 * @covers     \tool_tenant\reportbuilder\local\callbacks
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class datasources_test extends core_reportbuilder_testcase {

    /** @var tool_tenant_generator $generator */
    protected $generator;

    /** @var core_reportbuilder_generator $rbgenerator */
    protected $rbgenerator;

    /**
     * Set up
     */
    protected function setUp(): void {
        global $DB;

        // Remove initial capability to view all course categories from users.
        $userroleid = $DB->get_field('role', 'id', ['shortname' => 'user']);
        unassign_capability('moodle/category:viewcourselist', $userroleid);

        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
    }

    /**
     * Test for multitenancy callbacks in the courses datasource
     */
    public function test_multitenancy_courses_datasource(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $tenant1category = $this->getDataGenerator()->create_category(['name' => 'Tenant1 category']);
        $coursetenant1 = $this->getDataGenerator()->create_course(['category' => $tenant1category->id]);
        $tenant1 = $this->generator->create_tenant([
            'name' => 'Tenant one',
            'categoryid' => $tenant1category->id,
        ]);
        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'Luna', 'lastname' => 'Richie']);
        $this->generator->allocate_user((int) $user1->id, $tenant1->id);

        $tenant2category = $this->getDataGenerator()->create_category(['name' => 'Tenant2 category']);
        $coursetenant2 = $this->getDataGenerator()->create_course(['category' => $tenant2category->id]);
        $tenant2 = $this->generator->create_tenant([
            'name' => 'Tenant two',
            'categoryid' => $tenant2category->id,
        ]);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Kira', 'lastname' => 'Rogers']);
        $this->generator->allocate_user((int) $user2->id, $tenant2->id);

        $this->setUser($user1);

        $report = $this->generator->create_report([
            'name' => 'My report',
            'source' => \core_course\reportbuilder\datasource\courses::class,
        ], $tenant1->id);

        // User1 should only see tenant1's courses.
        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);
        [$coursecatname, $courseshortname, $coursefullname, $courseidnumber] = array_values($content[0]);
        $this->assertEquals($tenant1category->name, $coursecatname);
        $this->assertEquals($coursetenant1->shortname, $courseshortname);
        $this->assertEquals($coursetenant1->fullname, $coursefullname);
        $this->assertEquals($coursetenant1->idnumber, $courseidnumber);

        $this->setUser($user2);
        $report = $this->generator->create_report([
            'name' => 'My report',
            'source' => \core_course\reportbuilder\datasource\courses::class,
        ], $tenant2->id);

        // User2 should only see tenant2's courses.
        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);
        [$coursecatname, $courseshortname, $coursefullname, $courseidnumber] = array_values($content[0]);
        $this->assertEquals($tenant2category->name, $coursecatname);
        $this->assertEquals($coursetenant2->shortname, $courseshortname);
        $this->assertEquals($coursetenant2->fullname, $coursefullname);
        $this->assertEquals($coursetenant2->idnumber, $courseidnumber);
    }

    /**
     * Test for multitenancy callbacks in the participants datasource
     */
    public function test_multitenancy_participants_datasource(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a tenant with its own course category.
        $tenant1category = $this->getDataGenerator()->create_category(['name' => 'Tenant1 category']);
        $coursetenant1 = $this->getDataGenerator()->create_course(['category' => $tenant1category->id]);
        $tenant1 = $this->generator->create_tenant([
            'name' => 'Tenant one',
            'categoryid' => $tenant1category->id,
        ]);
        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'Luna', 'lastname' => 'Richie']);
        $this->generator->allocate_user((int) $user1->id, $tenant1->id);

        // Create another tenant also with its own course category.
        $tenant2category = $this->getDataGenerator()->create_category(['name' => 'Tenant2 category']);
        $coursetenant2 = $this->getDataGenerator()->create_course(['category' => $tenant2category->id]);
        $tenant2 = $this->generator->create_tenant([
            'name' => 'Tenant two',
            'categoryid' => $tenant2category->id,
        ]);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Kira', 'lastname' => 'Rogers']);
        $this->generator->allocate_user((int) $user2->id, $tenant2->id);

        $timestart = time() - DAYSECS;
        $timeend = $timestart + 3 * DAYSECS;
        $this->getDataGenerator()->enrol_user($user1->id, $coursetenant1->id, 'student',
            'manual', $timestart, $timeend, ENROL_USER_ACTIVE);
        $this->getDataGenerator()->enrol_user($user2->id, $coursetenant2->id, 'student',
            'manual', $timestart, $timeend, ENROL_USER_ACTIVE);

        // Create a report as user from tenant1.
        $this->setUser($user1);
        $report = $this->generator->create_report([
            'name' => 'My report',
            'source' => \core_course\reportbuilder\datasource\participants::class,
            'default' => false,
        ], $tenant1->id);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:firstname']);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'course:fullname']);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'course_category:name']);
        $this->rbgenerator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => 'enrolment:method']);

        // User1 should only see tenant1's courses.
        $content = $this->get_custom_report_content($report->get('id'), 30, [
            'enrolment:method_operator' => \core_reportbuilder\local\filters\select::EQUAL_TO,
            'enrolment:method_value' => 'manual',
        ]);
        $this->assertCount(1, $content);
        [$firstname, $coursefullname, $coursecategoryname] = array_values($content[0]);
        $this->assertEquals($user1->firstname, $firstname);
        $this->assertEquals($coursetenant1->fullname, $coursefullname);
        $this->assertEquals($tenant1category->name, $coursecategoryname);

        // Create a report as user from tenant2.
        $this->setUser($user2);

        $report = $this->generator->create_report([
            'name' => 'My report',
            'source' => \core_course\reportbuilder\datasource\participants::class,
            'default' => false,
        ], $tenant1->id);
        // Add two columns.
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:firstname']);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'course:fullname']);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'course_category:name']);
        $this->rbgenerator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => 'enrolment:method']);

        $content = $this->get_custom_report_content($report->get('id'), 30, [
            'enrolment:method_operator' => \core_reportbuilder\local\filters\select::EQUAL_TO,
            'enrolment:method_value' => 'manual',
        ]);
        $this->assertCount(1, $content);
        [$firstname, $coursefullname, $coursecategoryname] = array_values($content[0]);
        $this->assertEquals($user2->firstname, $firstname);
        $this->assertEquals($coursetenant2->fullname, $coursefullname);
        $this->assertEquals($tenant2category->name, $coursecategoryname);
    }

    /**
     * Test for multitenancy callbacks in the cohorts datasource
     */
    public function test_multitenancy_cohorts_datasource(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $tenant1category = $this->getDataGenerator()->create_category(['name' => 'Tenant1 category']);
        $coursetenant1 = $this->getDataGenerator()->create_course(['category' => $tenant1category->id]);
        $tenant1 = $this->generator->create_tenant([
            'name' => 'Tenant one',
            'categoryid' => $tenant1category->id,
        ]);
        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'Luna', 'lastname' => 'Richie']);
        $this->generator->allocate_user((int) $user1->id, $tenant1->id);
        $timestart = time() - DAYSECS;
        $timeend = $timestart + 3 * DAYSECS;
        $this->getDataGenerator()->enrol_user($user1->id, $coursetenant1->id, 'student',
            'manual', $timestart, $timeend, ENROL_USER_ACTIVE);

        $cohort01 = $this->getDataGenerator()->create_cohort(
            ['name' => 'Cohort01', 'contextid' => $tenant1category->get_context()->id]);
        cohort_add_member($cohort01->id, $user1->id);

        $tenant2category = $this->getDataGenerator()->create_category(['name' => 'Tenant2 category']);
        $coursetenant2 = $this->getDataGenerator()->create_course(['category' => $tenant2category->id]);
        $tenant2 = $this->generator->create_tenant([
            'name' => 'Tenant two',
            'categoryid' => $tenant2category->id,
        ]);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Kira', 'lastname' => 'Rogers']);
        $this->generator->allocate_user((int) $user2->id, $tenant2->id);
        $this->getDataGenerator()->enrol_user($user2->id, $coursetenant2->id, 'student',
            'manual', $timestart, $timeend, ENROL_USER_ACTIVE);

        $cohort02 = $this->getDataGenerator()->create_cohort(
            ['name' => 'Cohort02', 'contextid' => $tenant2category->get_context()->id]);
        cohort_add_member($cohort02->id, $user2->id);

        $cohortsys = $this->getDataGenerator()->create_cohort(['name' => 'CohortSys']);
        cohort_add_member($cohortsys->id, $user1->id);
        cohort_add_member($cohortsys->id, $user2->id);

        $cohortsysinvis = $this->getDataGenerator()->create_cohort(['name' => 'CohortSysInvis', 'visible' => false]);
        cohort_add_member($cohortsysinvis->id, $user1->id);
        cohort_add_member($cohortsysinvis->id, $user2->id);

        $this->setUser($user1);
        $report = $this->generator->create_report(['name' => 'Cohorts',
            'source' => \core_cohort\reportbuilder\datasource\cohorts::class, 'default' => 0]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'cohort:name',
            'sortenabled' => 1]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname']);

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertEquals([
            [$cohort01->name, fullname($user1)],
            [$cohortsys->name, fullname($user1)],
            [$cohortsys->name, ''],
        ], array_map('array_values', $content));

        $this->setUser($user2);
        $report = $this->generator->create_report(['name' => 'Cohorts',
            'source' => \core_cohort\reportbuilder\datasource\cohorts::class, 'default' => 0]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'cohort:name',
            'sortenabled' => 1]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname']);

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertEquals([
            [$cohort02->name, fullname($user2)],
            [$cohortsys->name, ''],
            [$cohortsys->name, fullname($user2)],
        ], array_map('array_values', $content));

        // Grant cohort view permissions at system context for user2, this should show invisible cohort.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid, SYSCONTEXTID);
        role_assign($roleid, $user2->id, (int) SYSCONTEXTID);
        \core_reportbuilder\manager::reset_caches();

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertEquals([
            [$cohort02->name, fullname($user2)],
            [$cohortsys->name, ''],
            [$cohortsys->name, fullname($user2)],
            [$cohortsysinvis->name, ''],
            [$cohortsysinvis->name, fullname($user2)],
        ], array_map('array_values', $content));
    }
}
