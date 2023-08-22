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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

/**
 * Test dynamic rules upgrade scripts.
 *
 * @package   tool_dynamicrule
 * @category  test
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Ruslan Kabalin
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule;

use advanced_testcase;
use tool_dynamicrule\tool_dynamicrule\condition\user_profile_field;
use tool_dynamicrule_generator;
use tool_tenant_generator;

/**
 * Class tool_dynamicrule_upgradelib_testcase
 *
 * @package   tool_dynamicrule
 * @group     tool_dynamicrule
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Ruslan Kabalin
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class upgradelib_test extends advanced_testcase {
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
     * @covers ::tool_dynamicrule_upgrade_remove_tenant_orphaned_rules
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

    /**
     * Test test_tool_dynamicrule_upgrade_update_user_profile_files upgrade script.
     *
     * @covers \tool_dynamicrule\task\update_user_profile_fields
     * @uses \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field
     */
    public function test_tool_dynamicrule_upgrade_update_user_profile_files() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/dynamicrule/db/upgradelib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');

        // Add a custom field of text type.
        $categoryid = $DB->insert_record('user_info_category', ['name' => 'Upgrade test category']);
        $fieldshortname = 'countrycode';
        $DB->insert_record('user_info_field', (object)['shortname' => $fieldshortname, 'name' => 'Custom country code',
            'categoryid' => $categoryid, 'datatype' => 'text', 'visible' => PROFILE_VISIBLE_ALL]);

        // Create new rule with the previous custom user profile field added.
        $rule = $this->generator->create_rule(['enabled' => 1]);
        $configform = [
            "userprofilefield" => $fieldshortname,
            "{$fieldshortname}_value" => "61",
            "{$fieldshortname}_op" => user_profile_field::TEXT_IS_EQUAL_TO
        ];
        $condition = user_profile_field::create($rule->id, $configform);
        $fieldshortnamenew = user_profile_field::PREFIX_PROFILE_FIELD.$fieldshortname;
        $configdata = $condition->get_configdata();
        $this->assertEquals($fieldshortname, $configdata['userprofilefield']);
        $this->assertNotEquals($fieldshortnamenew, $configdata['userprofilefield']);

        // Run update_user_profile_fields adhoc task.
        (new \tool_dynamicrule\task\update_user_profile_fields())->execute();

        $conditionupdated = user_profile_field::instance($condition->get_id());
        $configdataupdated = $conditionupdated->get_configdata();
        $this->assertNotEquals($fieldshortname, $configdataupdated['userprofilefield']);
        $this->assertEquals($fieldshortnamenew, $configdataupdated['userprofilefield']);
    }

    /**
     * Test tool_dynamicrule_fix_manual_issue_badges upgrade script.
     *
     * @covers ::tool_dynamicrule_fix_manual_issue_badges
     */
    public function test_tool_dynamicrule_fix_manual_issue_badges() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/badges/lib/awardlib.php');
        require_once($CFG->libdir . '/badgeslib.php');
        require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/dynamicrule/db/upgradelib.php');

        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_badges');
        $managerrole = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        $user0 = $this->getDataGenerator()->create_user();
        $user1 = $this->getDataGenerator()->create_user();

        // Create four badges with manual criteria.
        $badge0 = $generator->create_badge(['image' => 'lib/tests/fixtures/gd-logo.png']);
        $generator->create_criteria(['badgeid' => $badge0->id, 'roleid' => $managerrole]);
        $badge1 = $generator->create_badge(['image' => 'lib/tests/fixtures/gd-logo.png']);
        $generator->create_criteria(['badgeid' => $badge1->id, 'roleid' => $managerrole]);
        $badge2 = $generator->create_badge(['image' => 'lib/tests/fixtures/gd-logo.png']);
        $generator->create_criteria(['badgeid' => $badge2->id, 'roleid' => $managerrole]);
        $badge3 = $generator->create_badge(['image' => 'lib/tests/fixtures/gd-logo.png']);
        $generator->create_criteria(['badgeid' => $badge3->id, 'roleid' => $managerrole]);

        // Create two more badges, each with one additional criteria.
        $badge4 = $generator->create_badge(['image' => 'lib/tests/fixtures/gd-logo.png']);
        $generator->create_criteria(['badgeid' => $badge4->id, 'roleid' => $managerrole]);
        \award_criteria::build([
            'badgeid' => $badge4->id,
            'criteriatype' => BADGE_CRITERIA_TYPE_PROFILE,
        ])->save([
            'field' => 'city',
            'description' => '',
        ]);
        $badge5 = $generator->create_badge(['image' => 'lib/tests/fixtures/gd-logo.png']);
        $generator->create_criteria(['badgeid' => $badge5->id, 'roleid' => $managerrole]);
        \award_criteria::build([
            'badgeid' => $badge5->id,
            'criteriatype' => BADGE_CRITERIA_TYPE_PROFILE,
        ])->save([
            'field' => 'city',
            'description' => '',
        ]);

        // Badges 0 and 1 are issued correctly.
        if (process_manual_award($user0->id, get_admin()->id, $managerrole, $badge0->id)) {
            $data = new \stdClass();
            $data->crit = $badge0->get_criteria()[BADGE_CRITERIA_TYPE_MANUAL];
            $data->userid = $user0->id;
            badges_award_handle_manual_criteria_review($data);
        }
        if (process_manual_award($user0->id, get_admin()->id, $managerrole, $badge1->id)) {
            $data = new \stdClass();
            $data->crit = $badge1->get_criteria()[BADGE_CRITERIA_TYPE_MANUAL];
            $data->userid = $user0->id;
            badges_award_handle_manual_criteria_review($data);
        }
        // Badges 2 and 3 are issued directly like we did before WP-4296.
        $badge2->issue($user1->id);
        $badge3->issue($user1->id);
        // Badges 4 and 5 are not issued, we use them to check that manual award record is not added.

        // Sanity check.
        $this->assertTrue($DB->record_exists('badge_manual_award', ['badgeid' => $badge0->id]));
        $this->assertTrue($DB->record_exists('badge_manual_award', ['badgeid' => $badge1->id]));
        $this->assertFalse($DB->record_exists('badge_manual_award', ['badgeid' => $badge2->id]));
        $this->assertFalse($DB->record_exists('badge_manual_award', ['badgeid' => $badge3->id]));
        $this->assertFalse($DB->record_exists('badge_manual_award', ['badgeid' => $badge4->id]));
        $this->assertFalse($DB->record_exists('badge_manual_award', ['badgeid' => $badge5->id]));

        // Criteria met check.
        $this->assertCount(4, $DB->get_records('badge_criteria_met', ['userid' => $user0->id]));
        $this->assertCount(0, $DB->get_records('badge_criteria_met', ['userid' => $user1->id]));
        foreach ([BADGE_CRITERIA_TYPE_MANUAL, BADGE_CRITERIA_TYPE_OVERALL] as $crit) {
            $this->assertTrue($DB->record_exists('badge_criteria_met', ['critid' => $badge0->get_criteria()[$crit]->id,
                'userid' => $user0->id]));
            $this->assertTrue($DB->record_exists('badge_criteria_met', ['critid' => $badge1->get_criteria()[$crit]->id,
                'userid' => $user0->id]));
            $this->assertFalse($DB->record_exists('badge_criteria_met', ['critid' => $badge2->get_criteria()[$crit]->id,
                'userid' => $user1->id]));
            $this->assertFalse($DB->record_exists('badge_criteria_met', ['critid' => $badge3->get_criteria()[$crit]->id,
                'userid' => $user1->id]));
        }

        // Run upgrade script.
        tool_dynamicrule_fix_manual_issue_badges();

        // Validate manual award records.
        $this->assertTrue($DB->record_exists('badge_manual_award', ['badgeid' => $badge0->id]));
        $this->assertTrue($DB->record_exists('badge_manual_award', ['badgeid' => $badge1->id]));
        $this->assertTrue($DB->record_exists('badge_manual_award', ['badgeid' => $badge2->id]));
        $this->assertTrue($DB->record_exists('badge_manual_award', ['badgeid' => $badge3->id]));
        $this->assertFalse($DB->record_exists('badge_manual_award', ['badgeid' => $badge4->id]));
        $this->assertFalse($DB->record_exists('badge_manual_award', ['badgeid' => $badge5->id]));

        // Criteria met check.
        $this->assertCount(4, $DB->get_records('badge_criteria_met', ['userid' => $user0->id]));
        $this->assertCount(4, $DB->get_records('badge_criteria_met', ['userid' => $user1->id]));
        foreach ([BADGE_CRITERIA_TYPE_MANUAL, BADGE_CRITERIA_TYPE_OVERALL] as $crit) {
            $this->assertTrue($DB->record_exists('badge_criteria_met', ['critid' => $badge0->get_criteria()[$crit]->id,
                'userid' => $user0->id]));
            $this->assertTrue($DB->record_exists('badge_criteria_met', ['critid' => $badge1->get_criteria()[$crit]->id,
                'userid' => $user0->id]));
            $this->assertTrue($DB->record_exists('badge_criteria_met', ['critid' => $badge2->get_criteria()[$crit]->id,
                'userid' => $user1->id]));
            $this->assertTrue($DB->record_exists('badge_criteria_met', ['critid' => $badge3->get_criteria()[$crit]->id,
                'userid' => $user1->id]));
        }

        // Idempotency check.
        tool_dynamicrule_fix_manual_issue_badges();

        // Validate manual award records.
        $this->assertTrue($DB->record_exists('badge_manual_award', ['badgeid' => $badge0->id]));
        $this->assertTrue($DB->record_exists('badge_manual_award', ['badgeid' => $badge1->id]));
        $this->assertTrue($DB->record_exists('badge_manual_award', ['badgeid' => $badge2->id]));
        $this->assertTrue($DB->record_exists('badge_manual_award', ['badgeid' => $badge3->id]));
        $this->assertFalse($DB->record_exists('badge_manual_award', ['badgeid' => $badge4->id]));
        $this->assertFalse($DB->record_exists('badge_manual_award', ['badgeid' => $badge5->id]));

        // Criteria met check.
        $this->assertCount(4, $DB->get_records('badge_criteria_met', ['userid' => $user0->id]));
        $this->assertCount(4, $DB->get_records('badge_criteria_met', ['userid' => $user1->id]));
        foreach ([BADGE_CRITERIA_TYPE_MANUAL, BADGE_CRITERIA_TYPE_OVERALL] as $crit) {
            $this->assertTrue($DB->record_exists('badge_criteria_met', ['critid' => $badge0->get_criteria()[$crit]->id,
                'userid' => $user0->id]));
            $this->assertTrue($DB->record_exists('badge_criteria_met', ['critid' => $badge1->get_criteria()[$crit]->id,
                'userid' => $user0->id]));
            $this->assertTrue($DB->record_exists('badge_criteria_met', ['critid' => $badge2->get_criteria()[$crit]->id,
                'userid' => $user1->id]));
            $this->assertTrue($DB->record_exists('badge_criteria_met', ['critid' => $badge3->get_criteria()[$crit]->id,
                'userid' => $user1->id]));
        }
    }
}
