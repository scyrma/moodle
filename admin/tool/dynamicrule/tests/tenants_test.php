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
 * Test dynamic rules tenants-related scenarios.
 *
 * @package   tool_dynamicrule
 * @category  test
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Ruslan Kabalin
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule;

use advanced_testcase;
use tool_dynamicrule_generator;
use tool_tenant_generator;

/**
 * Class tool_dynamicrule_tenants_testcase
 *
 * @package   tool_dynamicrule
 * @group     tool_dynamicrule
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Ruslan Kabalin
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tenants_test extends advanced_testcase {
    /** @var tool_dynamicrule_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * Set up.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    /**
     * Test user enrolment works for tenants.
     *
     * @covers \tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled
     * @covers \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol
     * @covers \tool_dynamicrule\api::process_rule
     */
    public function test_tenant_enrolment() {
        global $DB;

        // Tenant and users.
        $tenant = $this->tenantgenerator->create_tenant();
        $tenantuser0 = self::getDataGenerator()->create_user();
        $tenantuser1 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($tenantuser0->id, $tenant->id);
        $this->tenantgenerator->allocate_user($tenantuser1->id, $tenant->id);

        // Courses.
        $course0 = $this->getDataGenerator()->create_course();
        $tenantcourse = $this->getDataGenerator()->create_course();

        // Create default tenant rule with Course0 not enrolled conditon and Course0 enrol outcome.
        $rule = $this->generator->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $course0->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled::create($rule->id, $configdata);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $configdata = ['coursetoenrol' => $course0->id, 'role' => $roleid];
        \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($rule->id, $configdata);

        // Create tenant rule with TenantCourse not enrolled conditon and TenantCourse enrol outcome.
        $tenantrule = $this->generator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata = ['courseid' => $tenantcourse->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled::create($tenantrule->id, $configdata);
        $configdata = ['coursetoenrol' => $tenantcourse->id, 'role' => $roleid];
        \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($tenantrule->id, $configdata);

        // Trigger rules.
        $task = new \tool_dynamicrule\task\process_rules();
        $task->execute();

        // Check matches record presence.
        $this->assertEquals(3, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule->id]));
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $tenantrule->id]));

        // Check enrolments.
        $sql = "SELECT ue.userid
          FROM {user_enrolments} ue
          JOIN {enrol} e
            ON (e.id = ue.enrolid AND e.courseid = :courseid AND e.status = 0
           AND e.enrol = 'dynamicrule' AND e.customint1 = :ruleid)
         WHERE ue.status = 0";

        // Admin is supposed to be enrolled into Course0.
        $params = ['courseid' => $course0->id, 'ruleid' => $rule->id];
        $enrolments = $DB->get_records_sql($sql, $params);
        $this->assertCount(1, $enrolments);
        $this->assertEquals([get_admin()->id], array_keys($enrolments));

        // Tenant users are supposed to be enrolled into TenantCourse.
        $params = ['courseid' => $tenantcourse->id, 'ruleid' => $tenantrule->id];
        $enrolments = $DB->get_records_sql($sql, $params);
        $this->assertCount(2, $enrolments);
        $this->assertEqualsCanonicalizing([$tenantuser0->id, $tenantuser1->id], array_keys($enrolments));
    }

    /**
     * Test user enrolment works for archived tenants.
     *
     * @covers \tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled
     * @covers \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol
     * @covers \tool_dynamicrule\api::process_rule
     * @covers \tool_tenant\manager::archive_tenant
     * @covers \tool_tenant\manager::restore_tenant
     */
    public function test_archived_tenant_enrolment() {
        global $DB;

        // Tenant and users.
        $tenant = $this->tenantgenerator->create_tenant();
        $tenantuser0 = self::getDataGenerator()->create_user();
        $tenantuser1 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($tenantuser0->id, $tenant->id);
        $this->tenantgenerator->allocate_user($tenantuser1->id, $tenant->id);

        // Promote tenantuser0 to become tenant admin.
        $manager = new \tool_tenant\manager();
        $manager->assign_tenant_admin_roles([$tenantuser0->id], $tenant->id);

        // Courses.
        $course0 = $this->getDataGenerator()->create_course();
        $tenantcourse = $this->getDataGenerator()->create_course();

        // Create default tenant rule with Course0 not enrolled conditon and Course0 enrol outcome.
        $rule = $this->generator->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $course0->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled::create($rule->id, $configdata);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $configdata = ['coursetoenrol' => $course0->id, 'role' => $roleid];
        \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($rule->id, $configdata);

        // Create tenant rule with TenantCourse not enrolled conditon and TenantCourse enrol outcome.
        $tenantrule = $this->generator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata = ['courseid' => $tenantcourse->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled::create($tenantrule->id, $configdata);
        $configdata = ['coursetoenrol' => $tenantcourse->id, 'role' => $roleid];
        \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($tenantrule->id, $configdata);

        // Archive the tenant.
        $manager->archive_tenant($tenant->id);

        // Trigger rules.
        $task = new \tool_dynamicrule\task\process_rules();
        $task->execute();

        // Check matches record presence.
        $this->assertEquals(3, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(3, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule->id]));
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $tenantrule->id]));

        // Check enrolments.
        $sql = "SELECT ue.userid
          FROM {user_enrolments} ue
          JOIN {enrol} e
            ON (e.id = ue.enrolid AND e.courseid = :courseid AND e.status = 0
           AND e.enrol = 'dynamicrule' AND e.customint1 = :ruleid)
         WHERE ue.status = 0";

        // Admin and "abandoned" users are supposed to be enrolled into Course0.
        $params = ['courseid' => $course0->id, 'ruleid' => $rule->id];
        $enrolments = $DB->get_records_sql($sql, $params);
        $this->assertCount(3, $enrolments);
        $this->assertEqualsCanonicalizing([get_admin()->id, $tenantuser0->id, $tenantuser1->id], array_keys($enrolments));

        // No enrolments to TenantCourse.
        $params = ['courseid' => $tenantcourse->id, 'ruleid' => $tenantrule->id];
        $enrolments = $DB->get_records_sql($sql, $params);
        $this->assertCount(0, $enrolments);

        // Restore the tenant.
        $manager->restore_tenant($tenant->id);

        // Fix rule, we need to become tenant admin to do this.
        self::setUser($tenantuser0);
        \tool_dynamicrule\api::enable_rule($tenantrule->id);
        self::setAdminUser();

        // Trigger rules.
        $task->execute();

        // Check matches record presence.
        $this->assertEquals(5, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(3, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule->id]));
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $tenantrule->id]));

        // Check enrolments.
        $sql = "SELECT ue.userid
          FROM {user_enrolments} ue
          JOIN {enrol} e
            ON (e.id = ue.enrolid AND e.courseid = :courseid AND e.status = 0
           AND e.enrol = 'dynamicrule' AND e.customint1 = :ruleid)
         WHERE ue.status = 0";

        // Tenant users are supposed to be enrolled into TenantCourse.
        $params = ['courseid' => $tenantcourse->id, 'ruleid' => $tenantrule->id];
        $enrolments = $DB->get_records_sql($sql, $params);
        $this->assertCount(2, $enrolments);
        $this->assertEqualsCanonicalizing([$tenantuser0->id, $tenantuser1->id], array_keys($enrolments));
    }

    /**
     * Test tenant rule deletions removes all related data.
     *
     * @covers \tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled
     * @covers \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol
     * @covers \tool_dynamicrule\api::process_rule
     * @covers \tool_dynamicrule\api::archive_rule
     * @covers \tool_dynamicrule\api::delete_rule
     */
    public function test_delete_rule() {
        global $DB;

        // Tenant and users.
        $tenant = $this->tenantgenerator->create_tenant();
        $tenantuser0 = self::getDataGenerator()->create_user();
        $tenantuser1 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($tenantuser0->id, $tenant->id);
        $this->tenantgenerator->allocate_user($tenantuser1->id, $tenant->id);

        // Promote tenantuser0 to become tenant admin.
        $manager = new \tool_tenant\manager();
        $manager->assign_tenant_admin_roles([$tenantuser0->id], $tenant->id);

        // Courses.
        $tenantcourse = $this->getDataGenerator()->create_course();

        // Create tenant rule with TenantCourse not enrolled conditon and TenantCourse enrol outcome.
        $tenantrule = $this->generator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata = ['courseid' => $tenantcourse->id, 'enrol' => 'manual'];
        $condition = \tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled::create($tenantrule->id, $configdata);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $configdata = ['coursetoenrol' => $tenantcourse->id, 'role' => $roleid];
        $outcome = \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($tenantrule->id, $configdata);

        // Trigger rules.
        $task = new \tool_dynamicrule\task\process_rules();
        $task->execute();

        // Check matches record presence.
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $tenantrule->id]));

        // Delete tenantrule.
        self::setUser($tenantuser0);
        \tool_dynamicrule\api::archive_rule($tenantrule->id);
        \tool_dynamicrule\api::delete_rule($tenantrule->id);
        self::setAdminUser();

        // Check records no longer there.
        $this->assertFalse(\tool_dynamicrule\rule::record_exists($tenantrule->id));
        $this->assertFalse(\tool_dynamicrule\condition::record_exists($condition->get_id()));
        $this->assertFalse(\tool_dynamicrule\outcome::record_exists($outcome->get_id()));
        $this->assertFalse($DB->record_exists('tool_dynamicrule_match', ['ruleid' => $tenantrule->id]));
    }

    /**
     * Test  event based conditions and tenant allocations.
     *
     * @covers \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled
     * @covers \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol
     */
    public function test_tenant_allocations() {
        global $DB;

        // Tenant and users.
        $tenant0 = $this->tenantgenerator->create_tenant();
        $tenant1 = $this->tenantgenerator->create_tenant();
        $tenantuser0 = self::getDataGenerator()->create_user();
        $tenantuser1 = self::getDataGenerator()->create_user();

        // Assign users to different tenants.
        $this->tenantgenerator->allocate_user($tenantuser0->id, $tenant0->id);
        $this->tenantgenerator->allocate_user($tenantuser1->id, $tenant1->id);

        // Courses.
        $course0 = $this->getDataGenerator()->create_course();
        $tenantcourse = $this->getDataGenerator()->create_course();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);

        // Create tenant rule with TenantCourse user enrolled conditon and course enrol outcome.
        $tenantrule = $this->generator->create_rule(['enabled' => 1, 'tenantid' => $tenant0->id]);
        $configdata = ['courseid' => $course0->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($tenantrule->id, $configdata);
        $configdata = ['coursetoenrol' => $tenantcourse->id, 'role' => $roleid];
        \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($tenantrule->id, $configdata);

        // Enrol users to course0.
        // These enrol_user calls will trigger rule as it is enabled already.
        $this->getDataGenerator()->enrol_user($tenantuser0->id, $course0->id, 'student');
        $this->getDataGenerator()->enrol_user($tenantuser1->id, $course0->id, 'student');

        // Check matches record presence. There is only one user on this tenant.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $tenantrule->id]));

        // Check enrolments.
        $sql = "SELECT ue.userid
          FROM {user_enrolments} ue
          JOIN {enrol} e
            ON (e.id = ue.enrolid AND e.courseid = :courseid AND e.status = 0
           AND e.enrol = 'dynamicrule' AND e.customint1 = :ruleid)
         WHERE ue.status = 0";

        // Tenant user is supposed to be enrolled into TenantCourse.
        $params = ['courseid' => $tenantcourse->id, 'ruleid' => $tenantrule->id];
        $enrolments = $DB->get_records_sql($sql, $params);
        $this->assertCount(1, $enrolments);
        $this->assertEqualsCanonicalizing([$tenantuser0->id], array_keys($enrolments));

        // Allocate second user ($tenantuser1) to first tenant.
        // This should trigger event and call tenant_user_updated in our observer.
        $this->tenantgenerator->allocate_user($tenantuser1->id, $tenant0->id);

        // Check matches record presence.
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $tenantrule->id]));

        // Check enrolments.
        $sql = "SELECT ue.userid
          FROM {user_enrolments} ue
          JOIN {enrol} e
            ON (e.id = ue.enrolid AND e.courseid = :courseid AND e.status = 0
           AND e.enrol = 'dynamicrule' AND e.customint1 = :ruleid)
         WHERE ue.status = 0";

        // Boths users are supposed to be enrolled into TenantCourse.
        $params = ['courseid' => $tenantcourse->id, 'ruleid' => $tenantrule->id];
        $enrolments = $DB->get_records_sql($sql, $params);
        $this->assertCount(2, $enrolments);
        $this->assertEqualsCanonicalizing([$tenantuser0->id, $tenantuser1->id], array_keys($enrolments));
    }

    /**
     * Test event based conditions and tenant restore.
     *
     * @covers \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled
     * @covers \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol
     */
    public function test_tenant_restore_and_allocations() {
        global $DB;

        // Tenant and users.
        $tenant0 = $this->tenantgenerator->create_tenant();
        $tenantuser0 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($tenantuser0->id, $tenant0->id);

        // Courses.
        $course0 = $this->getDataGenerator()->create_course();
        $tenantcourse = $this->getDataGenerator()->create_course();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);

        // Create tenant rule with TenantCourse user enrolled conditon and course enrol outcome.
        $tenantrule = $this->generator->create_rule(['enabled' => 0, 'tenantid' => $tenant0->id]);
        $configdata = ['courseid' => $course0->id, 'enrol' => 'manual'];
        \tool_dynamicrule\tool_dynamicrule\condition\user_enrolled::create($tenantrule->id, $configdata);
        $configdata = ['coursetoenrol' => $tenantcourse->id, 'role' => $roleid];
        \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($tenantrule->id, $configdata);

        $manager = new \tool_tenant\manager();
        $manager->assign_tenant_admin_role($tenant0->id, [$tenantuser0->id]);
        $this->setUser($tenantuser0);

        \tool_dynamicrule\api::enable_rule($tenantrule->id);
        $this->runAdhocTasks();

        // Check matches record presence. There is only one user on this tenant.
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $tenantrule->id]));

        // Check enrolments.
        $sql = "SELECT ue.userid
          FROM {user_enrolments} ue
          JOIN {enrol} e
            ON (e.id = ue.enrolid AND e.courseid = :courseid AND e.status = 0
           AND e.enrol = 'dynamicrule' AND e.customint1 = :ruleid)
         WHERE ue.status = 0";

        // Tenant user is not enrolled into TenantCourse.
        $params = ['courseid' => $tenantcourse->id, 'ruleid' => $tenantrule->id];
        $enrolments = $DB->get_records_sql($sql, $params);
        $this->assertCount(0, $enrolments);

        // Archive the tenant.
        $manager = new \tool_tenant\manager();
        $manager->archive_tenant($tenant0->id);

        // Enrol users to course0. When tenant gets restored will trigger an event which will result in dynamic rule processing.
        $plugin = enrol_get_plugin('manual');
        $plugin->add_instance($course0);
        $this->getDataGenerator()->enrol_user($tenantuser0->id, $course0->id, 'student', 'manual');

        // Check matches record presence.
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(0, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $tenantrule->id]));

        $manager->restore_tenant($tenant0->id);

        // Trigger rules running Adhoc tasks.
        $this->expectOutputRegex("/^Processing dynamic rule with id \d+/ms");
        $this->runAdhocTasks();

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match'));
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $tenantrule->id]));

        // Check enrolments.
        $sql = "SELECT ue.userid
          FROM {user_enrolments} ue
          JOIN {enrol} e
            ON (e.id = ue.enrolid AND e.courseid = :courseid AND e.status = 0
           AND e.enrol = 'dynamicrule' AND e.customint1 = :ruleid)
         WHERE ue.status = 0";

        // User is  supposed to be enrolled into TenantCourse.
        $params = ['courseid' => $tenantcourse->id, 'ruleid' => $tenantrule->id];
        $enrolments = $DB->get_records_sql($sql, $params);
        $this->assertCount(1, $enrolments);
        $this->assertEqualsCanonicalizing([$tenantuser0->id], array_keys($enrolments));
    }
}
