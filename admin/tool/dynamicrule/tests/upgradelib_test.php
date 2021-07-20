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
 * Test dynamic rules upgrade scripts.
 *
 * @package   tool_dynamicrule
 * @category  test
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Ruslan Kabalin
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_dynamicrule\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Class tool_dynamicrule_upgradelib_testcase
 *
 * @package   tool_dynamicrule
 * @group     tool_dynamicrule
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Ruslan Kabalin
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_upgradelib_testcase extends advanced_testcase {
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
     * Test tool_dynamicrule_upgrade_remove_tenant_orphaned_rules upgrade script.
     *
     * @uses \tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled
     * @uses \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol
     * @uses \tool_dynamicrule\api::process_rule
     */
    public function test_tool_dynamicrule_upgrade_remove_tenant_orphaned_rules() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/dynamicrule/db/upgradelib.php');

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
        $condition = \tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled::create($rule->id, $configdata);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $configdata = ['coursetoenrol' => $course0->id, 'role' => $roleid];
        $outcome = \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($rule->id, $configdata);

        // Create tenant rule with TenantCourse not enrolled conditon and TenantCourse enrol outcome.
        $tenantrule = $this->generator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata = ['courseid' => $tenantcourse->id, 'enrol' => 'manual'];
        $tenantcondition = \tool_dynamicrule\tool_dynamicrule\condition\user_not_enrolled::create($tenantrule->id, $configdata);
        $configdata = ['coursetoenrol' => $tenantcourse->id, 'role' => $roleid];
        $tenantoutcome = \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol::create($tenantrule->id, $configdata);

        // Trigger rules.
        $task = new \tool_dynamicrule\task\process_rules();
        $task->execute();

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule->id]));
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $tenantrule->id]));

        // Delete tenant dirty way (as if it no longer existed).
        $DB->delete_records('tool_tenant', ['id' => $tenant->id]);
        $DB->delete_records('tool_tenant_user', ['tenantid' => $tenant->id]);
        $DB->delete_records('tool_tenant_group', ['tenantid' => $tenant->id]);

        // Run upgrade script.
        tool_dynamicrule_upgrade_remove_tenant_orphaned_rules();

        // Check tenantrule records no longer there.
        $this->assertFalse(\tool_dynamicrule\rule::record_exists($tenantrule->id));
        $this->assertFalse(\tool_dynamicrule\condition::record_exists($tenantcondition->get_id()));
        $this->assertFalse(\tool_dynamicrule\outcome::record_exists($tenantoutcome->get_id()));
        $this->assertFalse($DB->record_exists('tool_dynamicrule_match', ['ruleid' => $tenantrule->id]));

        // Check default tenant rule records are not affected.
        $this->assertTrue(\tool_dynamicrule\rule::record_exists($rule->id));
        $this->assertTrue(\tool_dynamicrule\condition::record_exists($condition->get_id()));
        $this->assertTrue(\tool_dynamicrule\outcome::record_exists($outcome->get_id()));
        $this->assertTrue($DB->record_exists('tool_dynamicrule_match', ['ruleid' => $rule->id]));
    }
}
