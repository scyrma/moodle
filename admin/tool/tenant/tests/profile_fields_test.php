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

namespace tool_tenant;

use tool_tenant_generator;
use core_user\fields;

/**
 * Class users_reports_test, tests for user profile fields
 *
 * @package     tool_tenant
 * @category    test
 * @covers      \tool_tenant\profile_manager
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 Marina glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class profile_fields_test extends \advanced_testcase {

    /** @var tool_tenant_generator */
    private $tenantgenator;

    /**
     * Setup test
     *
     */
    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->tenantgenator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    protected function tearDown(): void {
        \tool_tenant\config::pop_all();
    }

    public function test_configuring_profile_fields_categories() {

        $cat0 = $this->getDataGenerator()->create_custom_profile_field_category(['name' => 'Cat all']);
        $cat1 = $this->getDataGenerator()->create_custom_profile_field_category(['name' => 'Cat 1']);
        $cat2 = $this->getDataGenerator()->create_custom_profile_field_category(['name' => 'Cat 2']);

        // Add a custom field of textarea type.
        $f0 = $this->getDataGenerator()->create_custom_profile_field([
            'categoryid' => $cat0->id, 'shortname' => 'f0', 'name' => 'Field0',
            'datatype' => 'text', 'param2' => 200])->id;
        $f1 = $this->getDataGenerator()->create_custom_profile_field([
            'categoryid' => $cat1->id, 'shortname' => 'f1', 'name' => 'Field1',
            'datatype' => 'text', 'param2' => 200])->id;
        $f2 = $this->getDataGenerator()->create_custom_profile_field([
            'categoryid' => $cat2->id, 'shortname' => 'f2', 'name' => 'Field2',
            'datatype' => 'text', 'param2' => 200])->id;

        [$tenant1, [$user11, $user12]] = $this->tenantgenator->create_tenant_and_users(2);
        [$tenant2, [$user21, $user22]] = $this->tenantgenator->create_tenant_and_users(2);
        $tenant3 = $this->tenantgenator->create_tenant();

        profile_manager::save_category_config((object)[
            'id' => $cat1->id,
            profile_manager::AVAILABILITY => profile_manager::TENANT_ONLY,
            profile_manager::ONLYTENANTS => [$tenant1->id],
        ]);

        profile_manager::save_category_config((object)[
            'id' => $cat2->id,
            profile_manager::AVAILABILITY => profile_manager::TENANT_EXCEPT,
            profile_manager::EXCEPTTENANTS => [$tenant1->id, $tenant3->id],
        ]);

        // Default tenant.
        \tool_tenant\config::push_for_tenant(tenancy::get_default_tenant_id());
        $this->assertEqualsCanonicalizing([$f0, $f2],
            array_column(profile_get_user_fields_with_data(0), 'fieldid'));

        // Tenant 1.
        \tool_tenant\config::push_for_tenant($tenant1->id);
        $this->assertEqualsCanonicalizing([$f0, $f1],
            array_column(profile_get_user_fields_with_data(0), 'fieldid'));

        // Shared space.
        \tool_tenant\sharedspace::enable_shared_space();
        \tool_tenant\config::push_for_tenant(\tool_tenant\sharedspace::get_shared_space_id());
        $this->assertEqualsCanonicalizing([$f0, $f1, $f2],
            array_column(profile_get_user_fields_with_data(0), 'fieldid'));

        // Tenant 2.
        \tool_tenant\config::push_for_tenant($tenant2->id);
        $this->assertEqualsCanonicalizing([$f0, $f2],
            array_column(profile_get_user_fields_with_data(0), 'fieldid'));

        // Tenant 3.
        \tool_tenant\config::push_for_tenant($tenant3->id);
        $this->assertEqualsCanonicalizing([$f0],
            array_column(profile_get_user_fields_with_data(0), 'fieldid'));

        // Full config (0 tenant).
        \tool_tenant\config::push_for_tenant(0);
        $this->assertEqualsCanonicalizing([$f0, $f1, $f2],
            array_column(profile_get_user_fields_with_data(0), 'fieldid'));

        // User in tenant 2.
        \tool_tenant\config::push_for_user($user21->id);
        $this->assertEqualsCanonicalizing([$f0, $f2],
            array_column(profile_get_user_fields_with_data(0), 'fieldid'));
    }

    public function test_delete_category() {
        global $CFG;
        require_once($CFG->dirroot.'/user/profile/definelib.php');

        $cat1 = $this->getDataGenerator()->create_custom_profile_field_category(['name' => 'Cat 1']);
        $cat2 = $this->getDataGenerator()->create_custom_profile_field_category(['name' => 'Cat 2']);

        $tenant1 = $this->tenantgenator->create_tenant();
        $cat1config = [
            profile_manager::AVAILABILITY => profile_manager::TENANT_ONLY,
            profile_manager::ONLYTENANTS => [$tenant1->id],
        ];
        profile_manager::save_category_config((object)($cat1config + ['id' => $cat1->id]));

        \tool_tenant\config::push_for_tenant(0);
        $this->setAdminUser();

        $config = profile_manager::get_category_config($cat1->id);
        $expected = $cat1config + [profile_manager::EXCEPTTENANTS => []];
        $this->assertEquals($expected, $config);
        $this->assertTrue(isset($CFG->{'tool_tenant_user_info_category_'.$cat1->id}));

        // Delete category. All tenant configuration should be deleted.
        $this->assertTrue(profile_delete_category($cat1->id));

        $config = profile_manager::get_category_config($cat1->id);
        $this->assertEquals([
            profile_manager::AVAILABILITY => profile_manager::TENANT_ALL,
            profile_manager::ONLYTENANTS => [],
            profile_manager::EXCEPTTENANTS => [],
        ], $config);
        $this->assertFalse(isset($CFG->{'tool_tenant_user_info_category_'.$cat1->id}));
    }

    public function test_user_identity_fields() {
        global $CFG;

        // Set up - several profile field categories, several fields and several tenants.
        // Field 'f1' is only available for tenant 1.
        $cat0 = $this->getDataGenerator()->create_custom_profile_field_category(['name' => 'Cat all']);
        $cat1 = $this->getDataGenerator()->create_custom_profile_field_category(['name' => 'Cat 1']);
        $cat2 = $this->getDataGenerator()->create_custom_profile_field_category(['name' => 'Cat 2']);

        // Add a custom field of textarea type.
        $f0 = $this->getDataGenerator()->create_custom_profile_field([
            'categoryid' => $cat0->id, 'shortname' => 'f0', 'name' => 'Field0',
            'datatype' => 'text', 'param2' => 200])->id;
        $f1 = $this->getDataGenerator()->create_custom_profile_field([
            'categoryid' => $cat1->id, 'shortname' => 'f1', 'name' => 'Field1',
            'datatype' => 'text', 'param2' => 200])->id;

        [$tenant1, [$user11, $user12]] = $this->tenantgenator->create_tenant_and_users(2);
        [$tenant2, [$user21, $user22]] = $this->tenantgenator->create_tenant_and_users(2);

        profile_manager::save_category_config((object)[
            'id' => $cat1->id,
            profile_manager::AVAILABILITY => profile_manager::TENANT_ONLY,
            profile_manager::ONLYTENANTS => [$tenant1->id],
        ]);

        // Set the $CFG->showuseridentity to include both profile fields. Both will be visible for tenant 0 (config)
        // and tenant 1. Only one will be visible for tenant 2.
        $this->setAdminUser();
        \tool_tenant\config::push_for_tenant(0);

        set_config('showuseridentity', 'email,department,profile_field_f0,profile_field_f1');

        $fields = fields::for_identity(null);
        $this->assertEquals(['email', 'department', 'profile_field_f0', 'profile_field_f1'], $fields->get_required_fields());

        \tool_tenant\config::push_for_tenant($tenant1->id);

        $fields = fields::for_identity(null);
        $this->assertEquals(['email', 'department', 'profile_field_f0', 'profile_field_f1'], $fields->get_required_fields());

        \tool_tenant\config::push_for_tenant($tenant2->id);

        $this->assertEquals('email,department,profile_field_f0', $CFG->showuseridentity);
        $fields = fields::for_identity(null);
        $this->assertEquals(['email', 'department', 'profile_field_f0'], $fields->get_required_fields());

    }
}
