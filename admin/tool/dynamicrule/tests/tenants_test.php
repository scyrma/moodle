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
 * Test dynamic rules tenants-related scenarios.
 *
 * @package   tool_dynamicrule
 * @category  test
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Ruslan Kabalin
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_dynamicrule\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Class tool_dynamicrule_tenants_testcase
 *
 * @package   tool_dynamicrule
 * @group     tool_dynamicrule
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Ruslan Kabalin
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_tenants_testcase extends advanced_testcase {
    /** @var tool_dynamicrule_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * Set up.
     */
    public function setUp() {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    /**
     * Test user enrolment works for tenants.
     *
     * @uses \tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled
     * @uses \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol
     * @uses \tool_dynamicrule\api::process_rule
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
     * @uses \tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled
     * @uses \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol
     * @uses \tool_dynamicrule\api::process_rule
     * @uses \tool_tenant\manager::archive_tenant
     * @uses \tool_tenant\manager::restore_tenant
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

        // Check tenantrule got broken as result of being run on archived tenant.
        $this->assertTrue((new \tool_dynamicrule\rule($tenantrule->id))->is_broken());

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
        \tool_dynamicrule\api::mark_rule_as_not_broken($tenantrule->id);
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
     * @uses \tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled
     * @uses \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol
     * @uses \tool_dynamicrule\api::process_rule
     * @uses \tool_dynamicrule\api::archive_rule
     * @uses \tool_dynamicrule\api::delete_rule
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
}
