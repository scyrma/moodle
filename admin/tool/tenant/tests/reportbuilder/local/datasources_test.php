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
use core_blog\reportbuilder\datasource\blogs;
use core_comment\reportbuilder\datasource\comments;
use core_files\reportbuilder\datasource\files;
use core_group\reportbuilder\datasource\groups;
use core_notes\reportbuilder\datasource\notes;

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
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
    }

    /**
     * Test for multitenancy callbacks in the comments datasource
     */
    public function test_multitenancy_comments_datasource(): void {
        global $CFG;
        require_once("{$CFG->dirroot}/comment/lib.php");

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        $tenant1 = $this->generator->create_tenant();
        $tenant2 = $this->generator->create_tenant();

        $user1a = $this->getDataGenerator()->create_user(['firstname' => 'Luna', 'lastname' => 'Sheep']);
        $this->generator->allocate_user((int) $user1a->id, $tenant1->id);

        $user1b = $this->getDataGenerator()->create_user(['firstname' => 'Zoe', 'lastname' => 'Zebra']);
        $this->generator->allocate_user((int) $user1b->id, $tenant1->id);

        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Kira', 'lastname' => 'Dog']);
        $this->generator->allocate_user((int) $user2->id, $tenant2->id);

        $this->setUser($user1a);
        $comment = new \comment((object) [
            'context' => $coursecontext,
            'component' => 'block_comments',
            'area' => 'page_comments',
        ]);
        $comment->add('Cool');

        $this->setUser($user1b);
        $comment = new \comment((object) [
            'context' => $coursecontext,
            'component' => 'block_comments',
            'area' => 'page_comments',
        ]);
        $comment->add('Beans');

        $this->setUser($user2);
        $comment = new \comment((object) [
            'context' => $coursecontext,
            'component' => 'block_comments',
            'area' => 'page_comments',
        ]);
        $comment->add('Awesome');

        $report = $this->rbgenerator->create_report(['name' => 'Comments', 'source' => comments::class, 'default' => 1]);

        // User in tenant1 should only see comments for tenant1.
        $this->setUser($user1b);
        $content = $this->get_custom_report_content($report->get('id'));

        // Set consistent order by firstname.
        \core_collator::asort_array_of_arrays_by_key($content, 'c2_firstname');
        $content = array_values($content);

        $this->assertCount(2, $content);

        // Default columns are context, content, user, time created.
        [$contextname, $contenttext, $userfullname, $timecreated] = array_values($content[0]);
        $this->assertEquals($coursecontext->get_context_name(), $contextname);
        $this->assertEquals(format_text('Cool'), $contenttext);
        $this->assertEquals(fullname($user1a), $userfullname);
        $this->assertNotEmpty($timecreated);

        [$contextname, $contenttext, $userfullname, $timecreated] = array_values($content[1]);
        $this->assertEquals($coursecontext->get_context_name(), $contextname);
        $this->assertEquals(format_text('Beans'), $contenttext);
        $this->assertEquals(fullname($user1b), $userfullname);
        $this->assertNotEmpty($timecreated);

        // User in tenant2 should only see comments for tenant2.
        $this->setUser($user2);
        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);

        // Default columns are context, content, user, time created.
        [$contextname, $contenttext, $userfullname, $timecreated] = array_values($content[0]);
        $this->assertEquals($coursecontext->get_context_name(), $contextname);
        $this->assertEquals(format_text('Awesome'), $contenttext);
        $this->assertEquals(fullname($user2), $userfullname);
        $this->assertNotEmpty($timecreated);
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
     * Test for multitenancy callbacks in the badges datasource
     */
    public function test_multitenancy_badges_datasource(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var core_badges_generator $badgesgenerator */
        $badgesgenerator = $this->getDataGenerator()->get_plugin_generator('core_badges');

        $tenant1category = $this->getDataGenerator()->create_category(['name' => 'Tenant1 category']);
        $coursetenant1 = $this->getDataGenerator()->create_course(['category' => $tenant1category->id]);
        $coursebadge1 = $badgesgenerator->create_badge(['name' => 'Badge 1', 'type' => BADGE_TYPE_COURSE,
            'courseid' => $coursetenant1->id]);
        $tenant1 = $this->generator->create_tenant([
            'name' => 'Tenant one',
            'categoryid' => $tenant1category->id,
        ]);
        $user1 = $this->generator->create_user(['firstname' => 'Luna', 'lastname' => 'Richie', 'tenantid' => $tenant1->id]);
        $coursebadge1->issue($user1->id, true);

        $tenant2category = $this->getDataGenerator()->create_category(['name' => 'Tenant2 category']);
        $coursetenant2 = $this->getDataGenerator()->create_course(['category' => $tenant2category->id]);
        $coursebadge2 = $badgesgenerator->create_badge(['name' => 'Badge 2', 'type' => BADGE_TYPE_COURSE,
            'courseid' => $coursetenant2->id]);
        $tenant2 = $this->generator->create_tenant([
            'name' => 'Tenant two',
            'categoryid' => $tenant2category->id,
        ]);
        $user2 = $this->generator->create_user(['firstname' => 'Kira', 'lastname' => 'Rogers', 'tenantid' => $tenant2->id]);
        $coursebadge2->issue($user2->id, true);

        $this->setUser($user1);

        $report = $this->generator->create_report([
            'name' => 'My report',
            'source' => \core_badges\reportbuilder\datasource\badges::class,
            'default' => false,
        ], $tenant1->id);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'course:fullname',
            'sortenabled' => 1]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'badge:name']);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname']);

        // User1 should only see their own details.
        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertEquals([
            [$coursetenant1->fullname, $coursebadge1->name, fullname($user1)],
            [$coursetenant2->fullname, $coursebadge2->name, ''],
        ], array_map('array_values', $content));

        $this->setUser($user2);

        $report = $this->generator->create_report([
            'name' => 'My report',
            'source' => \core_badges\reportbuilder\datasource\badges::class,
            'default' => false,
        ], $tenant2->id);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'course:fullname',
            'sortenabled' => 1]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'badge:name']);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname']);

        // User2 should only see their own details.
        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertEquals([
            [$coursetenant1->fullname, $coursebadge1->name, ''],
            [$coursetenant2->fullname, $coursebadge2->name, fullname($user2)],
        ], array_map('array_values', $content));
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
     * Test for multitenancy callbacks in the blogs datasource
     */
    public function test_multitenancy_blogs_datasource(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var \core_blog_generator $blogsgenerator */
        $blogsgenerator = $this->getDataGenerator()->get_plugin_generator('core_blog');

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

        $courseblog = $blogsgenerator->create_entry(['publishstate' => 'site', 'userid' => $user1->id,
        'subject' => 'Course', 'summary' => 'Course summary', 'courseid' => $coursetenant1->id]);

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
        $courseblog2 = $blogsgenerator->create_entry(['publishstate' => 'site', 'userid' => $user2->id,
            'subject' => 'Course', 'summary' => 'Course summary', 'courseid' => $coursetenant2->id]);

        $this->setUser($user1);
        $report = $this->generator->create_report(['name' => 'Blogs', 'source' => blogs::class, 'default' => 1]);

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);
        [$userfullname, $coursefullname, $subject, $created] = array_values($content[0]);
        $this->assertEquals(fullname($user1), $userfullname);
        $this->assertEquals($coursetenant1->fullname, $coursefullname);
        $this->assertEquals($courseblog->subject, $subject);
        $this->assertEquals(userdate($courseblog->created), $created);

        $this->setUser($user2);
        $report = $this->generator->create_report(['name' => 'Blogs', 'source' => blogs::class, 'default' => 1]);

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);
        [$userfullname, $coursefullname, $subject, $created] = array_values($content[0]);
        $this->assertEquals(fullname($user2), $userfullname);
        $this->assertEquals($coursetenant2->fullname, $coursefullname);
        $this->assertEquals($courseblog2->subject, $subject);
        $this->assertEquals(userdate($courseblog2->created), $created);
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

        $cohort01 = $this->getDataGenerator()->create_cohort(['name' => 'Cohort01']);
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

        $cohort02 = $this->getDataGenerator()->create_cohort(['name' => 'Cohort02']);
        cohort_add_member($cohort02->id, $user2->id);

        $this->setUser($user1);
        $report = $this->generator->create_report(['name' => 'Cohorts',
            'source' => \core_cohort\reportbuilder\datasource\cohorts::class, 'default' => 0]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'cohort:name',
            'sortenabled' => 1]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname']);

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertEquals([
            [$cohort01->name, fullname($user1)],
            [$cohort02->name, ''],
        ], array_map('array_values', $content));

        $this->setUser($user2);
        $report = $this->generator->create_report(['name' => 'Cohorts',
            'source' => \core_cohort\reportbuilder\datasource\cohorts::class, 'default' => 0]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'cohort:name',
            'sortenabled' => 1]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname']);

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertEquals([
            [$cohort01->name, ''],
            [$cohort02->name, fullname($user2)],
        ], array_map('array_values', $content));
    }

    /**
     * Test for multitenancy callbacks in the files datasource
     */
    public function test_multitenancy_files_datasource(): void {
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
        $coursecontext1 = \context_course::instance($coursetenant1->id);

        $tenant2category = $this->getDataGenerator()->create_category(['name' => 'Tenant2 category']);
        $coursetenant2 = $this->getDataGenerator()->create_course(['category' => $tenant2category->id]);
        $tenant2 = $this->generator->create_tenant([
            'name' => 'Tenant two',
            'categoryid' => $tenant2category->id,
        ]);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Kira', 'lastname' => 'Rogers']);
        $this->generator->allocate_user((int) $user2->id, $tenant2->id);
        $coursecontext2 = \context_course::instance($coursetenant2->id);

        $this->setUser($user1);
        $this->generate_test_files($coursecontext1);
        $report = $this->generator->create_report(['name' => 'Files', 'source' => files::class, 'default' => 0]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'file:context']);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'file:name']);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname']);
        $this->rbgenerator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => 'file:directory']);

        // User1 should only see tenant1's courses.
        $content = $this->get_custom_report_content($report->get('id'), 30, [
            'file:directory_operator' => \core_reportbuilder\local\filters\boolean_select::NOT_CHECKED,
        ]);
        $content = array_filter($content, static function(array $row): bool {
            return stripos($row['c0_contextid'], 'System') === false;
        });
        \core_collator::asort_array_of_arrays_by_key($content, 'c0_contextid');
        $content = array_values($content);

        $this->assertCount(2, $content);
        [$context, $filename, $userfullname] = array_values($content[0]);
        $this->assertEquals('Hello.txt', $filename);
        $this->assertEquals(fullname($user1), $userfullname);
        [$context, $filename, $userfullname] = array_values($content[1]);
        $this->assertEquals('Hello.txt', $filename);
        $this->assertEquals(fullname($user1), $userfullname);

        $this->setUser($user2);
        $this->generate_test_files($coursecontext2);
        $report = $this->generator->create_report(['name' => 'Files', 'source' => files::class, 'default' => 0]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'file:context']);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'file:name']);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname']);
        $this->rbgenerator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => 'file:directory']);

        // User1 should only see tenant1's courses.
        $content = $this->get_custom_report_content($report->get('id'), 30, [
            'file:directory_operator' => \core_reportbuilder\local\filters\boolean_select::NOT_CHECKED,
        ]);
        $content = array_filter($content, static function(array $row): bool {
            return stripos($row['c0_contextid'], 'System') === false;
        });
        \core_collator::asort_array_of_arrays_by_key($content, 'c0_contextid');
        $content = array_values($content);

        $this->assertCount(2, $content);
        [$context, $filename, $userfullname] = array_values($content[0]);
        $this->assertEquals('Hello.txt', $filename);
        $this->assertEquals(fullname($user2), $userfullname);
        [$context, $filename, $userfullname] = array_values($content[1]);
        $this->assertEquals('Hello.txt', $filename);
        $this->assertEquals(fullname($user2), $userfullname);
    }

    /**
     * Helper method to generate some test files for reporting on
     *
     * @param \context_course $context
     * @return int Draft item ID
     */
    protected function generate_test_files(\context_course $context): int {
        global $USER;

        $draftitemid = file_get_unused_draft_itemid();

        // Populate user draft.
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'userid' => $USER->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'Hello.txt',
        ], 'Hello');

        // Save draft to course summary file area.
        file_save_draft_area_files($draftitemid, $context->id, 'course', 'summary', 0);

        return $draftitemid;
    }

    /**
     * Test for multitenancy callbacks in the groups datasource
     */
    public function test_multitenancy_groups_datasource(): void {
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
        $coursecontext1 = \context_course::instance($coursetenant1->id);

        $group1 = $this->getDataGenerator()->create_group(['courseid' => $coursetenant1->id]);
        $this->getDataGenerator()->create_group_member(['userid' => $user1->id, 'groupid' => $group1->id]);

        $tenant2category = $this->getDataGenerator()->create_category(['name' => 'Tenant2 category']);
        $coursetenant2 = $this->getDataGenerator()->create_course(['category' => $tenant2category->id]);
        $tenant2 = $this->generator->create_tenant([
            'name' => 'Tenant two',
            'categoryid' => $tenant2category->id,
        ]);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Kira', 'lastname' => 'Rogers']);
        $this->generator->allocate_user((int) $user2->id, $tenant2->id);
        $coursecontext2 = \context_course::instance($coursetenant2->id);

        $group2 = $this->getDataGenerator()->create_group(['courseid' => $coursetenant2->id]);
        $this->getDataGenerator()->create_group_member(['userid' => $user2->id, 'groupid' => $group2->id]);

        // User1 should only see tenant1's courses.
        $this->setUser($user1);
        $this->generate_test_files($coursecontext1);
        $report = $this->generator->create_report(['name' => 'Groups', 'source' => groups::class, 'default' => 0]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'course:fullname']);

        $content = $this->get_custom_report_content($report->get('id'), 30);
        $this->assertCount(1, $content);
        [$coursefullname] = array_values($content[0]);
        $this->assertEquals($coursetenant1->fullname, $coursefullname);

        // User2 should only see tenant2's courses.
        $this->setUser($user2);
        $this->generate_test_files($coursecontext2);
        $report = $this->generator->create_report(['name' => 'Groups', 'source' => groups::class, 'default' => 0]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'course:fullname']);

        $content = $this->get_custom_report_content($report->get('id'), 30);
        $this->assertCount(1, $content);
        [$coursefullname] = array_values($content[0]);
        $this->assertEquals($coursetenant2->fullname, $coursefullname);
    }

    /**
     * Test for multitenancy callbacks in the notes datasource
     */
    public function test_multitenancy_notes_datasource(): void {
        global $CFG;
        require_once("{$CFG->dirroot}/notes/lib.php");
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var \core_notes_generator $notesgenerator */
        $notesgenerator = $this->getDataGenerator()->get_plugin_generator('core_notes');

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
        $notesgenerator->create_instance(['courseid' => $coursetenant1->id, 'userid' => $user1->id, 'content' => 'Course',
            'publishstate' => NOTES_STATE_PUBLIC]);

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
        $notesgenerator->create_instance(['courseid' => $coursetenant2->id, 'userid' => $user2->id, 'content' => 'Course',
            'publishstate' => NOTES_STATE_PUBLIC]);

        // User1 should only see tenant1's courses.
        $this->setUser($user1);
        $report = $this->generator->create_report(['name' => 'Notes', 'source' => notes::class, 'default' => 0]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'course:fullname']);

        $content = $this->get_custom_report_content($report->get('id'), 30);
        $this->assertCount(1, $content);
        [$coursefullname] = array_values($content[0]);
        $this->assertEquals($coursetenant1->fullname, $coursefullname);

        // User2 should only see tenant2's courses.
        $this->setUser($user2);
        $report = $this->generator->create_report(['name' => 'Notes', 'source' => notes::class, 'default' => 0]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'course:fullname']);

        $content = $this->get_custom_report_content($report->get('id'), 30);
        $this->assertCount(1, $content);
        [$coursefullname] = array_values($content[0]);
        $this->assertEquals($coursetenant2->fullname, $coursefullname);
    }

    /**
     * Test for multitenancy callbacks in the tags datasource
     */
    public function test_multitenancy_tags_datasource(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $tenant1 = $this->generator->create_tenant(['name' => 'Tenant one']);
        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'Luna', 'lastname' => 'Richie', 'interests' => ['Skate']]);
        $this->generator->allocate_user((int) $user1->id, $tenant1->id);
        // Tag has been created as admin, we need to set manually userid.
        $DB->set_field('tag', 'userid', $user1->id, ['rawname' => 'Skate']);

        $tenant2 = $this->generator->create_tenant(['name' => 'Tenant two']);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Kira', 'lastname' => 'Rogers', 'interests' => ['Dogs']]);
        $this->generator->allocate_user((int) $user2->id, $tenant2->id);
        // Tag has been created as admin, we need to set manually userid.
        $DB->set_field('tag', 'userid', $user2->id, ['rawname' => 'Dogs']);

        // User1 should only see their own details.
        $this->setUser($user1);
        $report = $this->generator->create_report(['name' => 'Tags', 'source' => \core_tag\reportbuilder\datasource\tags::class,
            'default' => 0]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname',
            'sortenabled' => 1]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'tag:name']);

        $content = $this->get_custom_report_content($report->get('id'), 30);
        $this->assertEquals([
            ['', 'Dogs'],
            [fullname($user1), 'Skate'],
        ], array_map('array_values', $content));

        // User2 should only see their own details.
        $this->setUser($user2);
        $report = $this->generator->create_report(['name' => 'Tags', 'source' => \core_tag\reportbuilder\datasource\tags::class,
            'default' => 0]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname',
            'sortenabled' => 1]);
        $this->rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'tag:name']);

        $content = $this->get_custom_report_content($report->get('id'), 30);
        $this->assertEquals([
            ['', 'Skate'],
            [fullname($user2), 'Dogs'],
        ], array_map('array_values', $content));
    }
}
