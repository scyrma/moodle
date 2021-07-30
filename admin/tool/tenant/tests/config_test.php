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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * File containing tests for functions in class config
 *
 * @package     tool_tenant
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_tenant\config;

/**
 * Tests for functions in class config
 *
 * @package    tool_tenant
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_tenant_config_testcase extends advanced_testcase {

    /** @var tool_tenant_generator */
    protected $generator;

    /**
     * Set up
     */
    protected function setUp(): void {
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    protected function tearDown(): void {
        config::pop_all();
    }

    /**
     * Set a current user and switch the config to this user's tenant
     *
     * @param stdClass|null $user
     */
    public static function set_user_with_config(?stdClass $user = null) {
        self::setUser($user);
        if ($user) {
            config::push_for_user($user->id);
        } else {
            config::push_for_tenant(0);
        }
    }

    /**
     * Core config settings (such as auth_instructions) can be overridden for tenants
     */
    public function test_overrides_core() {
        global $CFG;
        $this->resetAfterTest();
        set_config('auth_instructions', 'default instructions');
        $tenant = $this->generator->create_tenant();

        config::set_config_tenant_override($tenant->id, 'auth_instructions', 'Hello!');
        $this->assertEquals('Hello!', config::get_config_tenant_override($tenant->id, 'core', 'auth_instructions'));

        // When we don't have any overrides (in default tenant) the default values are returned.
        $this->assertEquals('default instructions', get_config('core', 'auth_instructions'));
        $this->assertEquals('default instructions', $CFG->auth_instructions);
        $this->assertEquals('default instructions', config::get_config_default('core', 'auth_instructions'));

        // When we are inside another tenant the values for that tenant are returned.
        config::push_for_tenant($tenant->id);
        $this->assertEquals('Hello!', get_config('core', 'auth_instructions'));
        $this->assertEquals('Hello!', $CFG->auth_instructions);
        $this->assertEquals('default instructions', config::get_config_default('core', 'auth_instructions'));
        config::pop();

        // Remove override.
        config::set_config_tenant_override($tenant->id, 'auth_instructions', null);
        $this->assertEquals('default instructions', get_config('core', 'auth_instructions'));
        $this->assertEquals('default instructions', $CFG->auth_instructions);
        $this->assertEquals('default instructions', config::get_config_default('core', 'auth_instructions'));
    }

    /**
     * Core config settings can be overridden for tenants, when setting a user their config is retrieved
     */
    public function test_overrides_core_for_user() {
        global $CFG;
        $this->resetAfterTest();
        set_config('auth_instructions', 'default instructions');
        [$tenant, $users] = $this->generator->create_tenant_and_users(2);

        config::set_config_tenant_override($tenant->id, 'auth_instructions', 'Hello!');
        $this->assertEquals('Hello!', config::get_config_tenant_override($tenant->id, 'core', 'auth_instructions'));

        // Admin is in the default tenant, they see the default values.
        $this->setAdminUser();
        $this->assertEquals('default instructions', get_config('core', 'auth_instructions'));
        $this->assertEquals('default instructions', $CFG->auth_instructions);
        $this->assertEquals('default instructions', config::get_config_default('core', 'auth_instructions'));

        // User is in the tenant, they see their tenant's values.
        $this->set_user_with_config($users[0]);
        $this->assertEquals('Hello!', get_config('core', 'auth_instructions'));
        $this->assertEquals('Hello!', $CFG->auth_instructions);
        $this->assertEquals('default instructions', config::get_config_default('core', 'auth_instructions'));

        // No user.
        config::push_for_user(0);
        $this->assertEquals('Hello!', get_config('core', 'auth_instructions'));
    }

    /**
     * Settings can be forced in core and in this case tenant overrides are ignored
     */
    public function test_overrides_forced() {
        global $CFG;
        $this->resetAfterTest();
        set_config('auth_instructions', 'default instructions');
        $tenant = $this->generator->create_tenant();

        // When setting is forced for all tenants, the tenant overrides do not work.
        config::set_config_tenant_override($tenant->id, 'auth_instructions', 'Hello!');
        set_config('auth_instructions_wforce', 1);
        $this->assertEquals('Hello!', config::get_config_tenant_override($tenant->id, 'core', 'auth_instructions'));

        config::push_for_tenant($tenant->id);
        $this->assertEquals('default instructions', get_config('core', 'auth_instructions'));
        $this->assertEquals('default instructions', $CFG->auth_instructions);
        $this->assertEquals('default instructions', config::get_config_default('core', 'auth_instructions'));
    }

    /**
     * Settings for plugins can be overridden for individual tenants and forced for all
     */
    public function test_override_plugin() {
        $this->resetAfterTest();
        // Values for first and lastname lock are set, for middlename - not.
        set_config('field_lock_firstname', 'locked', 'auth_email');
        set_config('field_lock_lastname', 'locked', 'auth_email');
        $tenant = $this->generator->create_tenant();

        // Values for first and lastname lock are overridden for a tenant but the lastname is forced for the site.
        config::set_config_tenant_override($tenant->id, 'field_lock_firstname', 'onlogin', 'auth_email');
        config::set_config_tenant_override($tenant->id, 'field_lock_lastname', 'onlogin', 'auth_email');
        set_config('field_lock_lastname_wforce', 1, 'auth_email');
        $this->assertEquals('onlogin', config::get_config_tenant_override($tenant->id, 'auth_email', 'field_lock_firstname'));
        $this->assertEquals('onlogin', config::get_config_tenant_override($tenant->id, 'auth_email', 'field_lock_lastname'));
        // Test setting the tenant override again with and without changes.
        $this->assertNull(config::get_config_tenant_override($tenant->id, 'auth_email', 'field_lock_middlename'));
        config::set_config_tenant_override($tenant->id, 'field_lock_firstname', 'never', 'auth_email');
        $this->assertEquals('never', config::get_config_tenant_override($tenant->id, 'auth_email', 'field_lock_firstname'));
        config::set_config_tenant_override($tenant->id, 'field_lock_firstname', 'never', 'auth_email');
        $this->assertEquals('never', config::get_config_tenant_override($tenant->id, 'auth_email', 'field_lock_firstname'));

        // Default tenant.
        $this->assertEquals('locked', get_config('auth_email', 'field_lock_firstname'));
        $this->assertEquals('locked', get_config('auth_email')->field_lock_firstname);
        $this->assertEquals('locked', get_config('auth_email', 'field_lock_lastname'));
        $this->assertEquals('locked', get_config('auth_email')->field_lock_lastname);
        $this->assertEquals('unlocked', get_config('auth_email', 'field_lock_middlename'));
        $this->assertEquals('unlocked', get_config('auth_email')->field_lock_middlename);

        // Tenant where values are overridden. Firstname returns the overridden value, lastname and middlename - site values.
        config::push_for_tenant($tenant->id);
        $this->assertEquals('never', get_config('auth_email', 'field_lock_firstname'));
        $this->assertEquals('never', get_config('auth_email')->field_lock_firstname);
        $this->assertEquals('locked', get_config('auth_email', 'field_lock_lastname'));
        $this->assertEquals('locked', get_config('auth_email')->field_lock_lastname);
        $this->assertEquals('unlocked', get_config('auth_email', 'field_lock_middlename'));
        $this->assertEquals('unlocked', get_config('auth_email')->field_lock_middlename);
    }
}
