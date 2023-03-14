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

namespace tool_dynamicrule\tool_dynamicrule\outcome;

use tool_dynamicrule\outcome;
use tool_dynamicrule\rule;
use tool_dynamicrule\tool_wp\exporter\rules as exporter;
use tool_dynamicrule\tool_wp\importer\rules as importer;

/**
 * Unit tests for outcome\badge  class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\outcome\badge
 * @covers     \tool_dynamicrule\outcome_base
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class badge_test extends \advanced_testcase {

    /**
     * Load required libraries for test
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->libdir . '/badgeslib.php');
    }

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Get dynamic rule generator
     *
     * @return \tool_dynamicrule_generator
     */
    protected function get_generator(): \tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Get workplace generator
     *
     * @return \tool_wp_generator
     */
    protected function get_workplace_generator(): \tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }

    /**
     * Test supports_rule_types
     */
    public function test_supports_rule_types(): void {
        $outcome = badge::instance();
        $this->assertEquals(rule::TYPE_NORMAL + rule::TYPE_SHARED, $outcome->supports_rule_types());
    }

    /**
     * Test get_title
     */
    public function test_get_title() {
        $outcome = badge::instance();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $outcome = badge::instance();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $outcome->get_category());
    }

    /**
     * Test apply_to_user and setup_for_applying
     */
    public function test_apply_to_user() {
        global $DB;

        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        $user = self::getDataGenerator()->create_user();
        $badge = $this->get_generator()->create_badge($user->id, BADGE_STATUS_ACTIVE, 'My lovely badge');

        $configdata = ['badge' => $badge->id, 'nobake' => true];
        $outcome = badge::create($rule0->id, $configdata);

        // Create two users in default tenant.
        $userids = [
            $this->getDataGenerator()->create_user()->id,
            $this->getDataGenerator()->create_user()->id,
        ];

        // Process rule manually as if we enabled it using default tenant.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Expect three affected users (admin user and those two we created).
        $userids[] = get_admin()->id;
        $this->assertEqualsCanonicalizing($userids, array_column($DB->get_records('badge_issued'), 'userid'));
    }

    /**
     * Test apply_to_user and setup_for_applying using non-current tenant
     */
    public function test_apply_to_user_in_other_tenant() {
        global $DB;

        $tenant = $this->get_tenant_generator()->create_tenant();
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        $user = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'tenantadmin' => true]);
        $badge = $this->get_generator()->create_badge($user->id, BADGE_STATUS_ACTIVE, 'My lovely badge');

        $configdata = ['badge' => $badge->id, 'nobake' => true];
        $outcome = badge::create($rule0->id, $configdata);

        // Create two users in tenant.
        $userids = [
            $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id])->id,
            $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id])->id,
        ];

        // Process rule manually as if we enabled it using default tenant.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Expect two tenant users to be affected.
        $this->assertEqualsCanonicalizing($userids, array_column($DB->get_records('badge_issued'), 'userid'));
    }

    /**
     * Test apply_to_user and setup_for_applying using shared tenant
     */
    public function test_apply_to_user_in_shared_tenant() {
        global $DB;

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1, 'tenantid' => $sharedspaceid]);
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        $badge = $this->get_generator()->create_badge(get_admin()->id, BADGE_STATUS_ACTIVE, 'My lovely badge');
        $configdata = ['badge' => $badge->id, 'nobake' => true];
        $outcome = badge::create($rule0->id, $configdata);

        [$tenant, [$user1, $user2]] = $this->get_tenant_generator()->create_tenant_and_users(2);
        [$tenant2, [$user21, $user22]] = $this->get_tenant_generator()->create_tenant_and_users(2);

        // Process rule manually as if we enabled it using default tenant.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Expect all tenant users to be affected.
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user21->id, $user22->id, get_admin()->id],
            array_column($DB->get_records('badge_issued'), 'userid'));
    }

    /**
     * Test get_displayed_description.
     *
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\badge::get_description
     * @uses \tool_dynamicrule\tool_dynamicrule\outcome\badge::get_uneditable_description
     */
    public function test_get_displayed_description() {
        global $DB;

        $rule0 = $this->get_generator()->create_rule();
        $user = self::getDataGenerator()->create_user();
        $badge = $this->get_generator()->create_badge($user->id);

        $configdata = ['badge' => $badge->id];
        $outcome = badge::create($rule0->id, $configdata);

        // Admin user.
        self::setAdminUser();
        $str = get_string('outcomebadgedescription', 'tool_dynamicrule', 'Test badge 1');
        $this->assertEquals($str, $outcome->get_displayed_description());

        // User with user role has required permission.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertEquals($str, $outcome->get_displayed_description());

        // Revoke permission to view badge.
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'user']);
        role_change_permission($roleid, $badge->get_context(), 'moodle/badges:viewbadges', CAP_PROHIBIT);
        $strunedit = get_string('uneditabledescription', 'tool_dynamicrule', $outcome->get_title());
        $this->assertEquals($strunedit, $outcome->get_displayed_description());
    }

    /**
     * Test outcome field mapping during export/import
     */
    public function test_field_mapping(): void {
        global $DB;

        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $badgename = 'My badge';
        $badgeid = $this->get_generator()->create_badge($user->id, BADGE_STATUS_ACTIVE, $badgename)->id;

        // Create rule containing outcome, pointing to the badge we just created.
        $rule = $this->get_generator()->create_rule();
        $this->get_generator()->create_outcome(badge::class, $rule->id, ['badge' => $badgeid]);

        // Export our rule.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_CONTENT => 1,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
        ]);

        // Now delete the original badge, and create a new one with the same name.
        $DB->set_field('badge', 'status', BADGE_STATUS_ARCHIVED, ['id' => $badgeid]);
        $newbadgeid = $this->get_generator()->create_badge($user->id, BADGE_STATUS_ACTIVE, $badgename)->id;

        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, [
            importer::IMPORT_CONTENT => 1,
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        // Confirm the badge mapping data was added.
        $mappingdata = (new \tool_wp\local\exportimport\import_manager($importid))
            ->get_raw_mapping_from_workplace_export_file('badge', $badgeid);

        $this->assertIsArray($mappingdata);
        $this->assertEquals($badgeid, $mappingdata['id']);

        // The imported outcome field should be mapped to the new badge.
        $rules = rule::get_records([], 'id');

        $outcome = badge::instance(0, outcome::get_record(['ruleid' => end($rules)->get('id')])->to_record());

        $this->assertEquals($newbadgeid, $outcome->get_configdata()['badge']);
        $this->assertTrue($outcome->is_configuration_valid());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add() {
        $rule0 = $this->get_generator()->create_rule();
        $user = self::getDataGenerator()->create_user();
        $badge = $this->get_generator()->create_badge($user->id);

        $configdata = ['badge' => $badge->id];
        badge::create($rule0->id, $configdata);

        // Admin user.
        self::setAdminUser();
        $this->assertTrue(badge::instance()->user_can_add());

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse(badge::instance()->user_can_add());

        // Grant priveleges to user.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        $context = \context_system::instance();
        assign_capability('moodle/badges:awardbadge', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        $this->assertTrue(badge::instance()->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit() {
        $rule0 = $this->get_generator()->create_rule();
        $user = self::getDataGenerator()->create_user();
        $badge = $this->get_generator()->create_badge($user->id, BADGE_STATUS_ACTIVE);

        $configdata = ['badge' => $badge->id];
        badge::create($rule0->id, $configdata);

        // Admin user.
        self::setAdminUser();
        $this->assertTrue(badge::instance()->user_can_edit($configdata));

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse(badge::instance()->user_can_edit($configdata));

        // Grant priveleges to user.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('moodle/badges:awardbadge', CAP_ALLOW, $roleid, $badge->get_context()->id);
        role_assign($roleid, $user->id, $badge->get_context());
        $this->assertTrue(badge::instance()->user_can_edit($configdata));
    }

    /**
     * Test test_user_can_edit by tenant.
     */
    public function test_user_can_edit_tenant() {
        $user = $this->getDataGenerator()->create_user();
        $badge = $this->get_generator()->create_badge($user->id, BADGE_STATUS_ACTIVE);

        $tenant = $this->get_tenant_generator()->create_tenant();
        $tenantadmin = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'tenantadmin' => true]);

        $rule0 = $this->get_generator()->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['badge' => $badge->id];
        badge::create($rule0->id, $configdata);

        // Sanity check.
        self::setAdminUser();
        $this->assertTrue(badge::instance()->user_can_edit($configdata));

        // Tenant admin can also access system context badge.
        self::setUser($tenantadmin);
        $this->assertTrue(badge::instance()->user_can_edit($configdata));
    }

    /**
     * Test get_broken_description
     */
    public function test_get_broken_description() {
        $user = self::getDataGenerator()->create_user();
        $badge = $this->get_generator()->create_badge($user->id);

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['badge' => $badge->id];
        $outcome = badge::create($rule1->id, $configdata);

        $this->assertNotEmpty($outcome->get_broken_description());
    }

    /**
     * Test get_get_not_available_label
     */
    public function test_get_not_available_label() {
        $user = self::getDataGenerator()->create_user();
        $badge = $this->get_generator()->create_badge($user->id);

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['badge' => $badge->id];
        $outcome = badge::create($rule1->id, $configdata);

        $this->assertNotEmpty($outcome->get_not_available_label());
    }

    /**
     * Test is_available
     */
    public function test_is_available() {
        $user = self::getDataGenerator()->create_user();
        $badge = $this->get_generator()->create_badge($user->id, BADGE_STATUS_ARCHIVED);

        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['badge' => $badge->id];
        $outcome = badge::create($rule1->id, $configdata);

        self::setAdminUser();
        $this->assertFalse($outcome::is_available());

        $badge->set_status(BADGE_STATUS_ACTIVE);
        $this->assertTrue($outcome::is_available());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid() {
        global $DB;
        $user = self::getDataGenerator()->create_user();
        $badge = $this->get_generator()->create_badge($user->id, BADGE_STATUS_ACTIVE);

        // Create rule with a badge.
        $rule1 = $this->get_generator()->create_rule();
        $configdata = ['badge' => $badge->id];
        $outcome = badge::create($rule1->id, $configdata);

        self::setAdminUser();
        $this->assertTrue($outcome->is_configuration_valid());

        // Make badge archived.
        $badge->set_status(BADGE_STATUS_ARCHIVED);
        $this->assertFalse($outcome->is_configuration_valid());

        // Delete badge.
        $DB->delete_records('badge', ['id' => $badge->id]);
        $this->assertFalse($outcome->is_configuration_valid());
    }

    /**
     * Return tenant generator
     *
     * @return \tool_tenant_generator
     */
    private function get_tenant_generator(): \tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }
}
