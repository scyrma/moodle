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
 * File containing tests for functions in class auth_manager
 *
 * @package     tool_tenant
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_tenant\config;
use tool_tenant\auth_manager;
use tool_tenant\local\auth\issuer_helper;
use tool_tenant\local\auth\oauth2\manager as oauth2_manager;
use tool_tenant\local\auth\saml2\manager as saml2_manager;

/**
 * Tests for functions in class auth_manager
 *
 * @package    tool_tenant
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_tenant_auth_testcase extends advanced_testcase {

    /** @var tool_tenant_generator */
    protected $generator;

    /**
     * Set up
     */
    protected function setUp(): void {
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    protected function tearDown(): void {
        config::pop_all();
    }

    public function test_is_multitenant_auth_plugin() {
        $this->assertTrue(auth_manager::is_multitenant_auth_plugin('auth_email'));
        $this->assertTrue(auth_manager::is_multitenant_auth_plugin('auth_manual'));
        $this->assertTrue(auth_manager::is_multitenant_auth_plugin('auth_oauth2'));
        $this->assertTrue(auth_manager::is_multitenant_auth_plugin('auth_saml2'));
        $this->assertFalse(auth_manager::is_multitenant_auth_plugin('auth_mnet'));
        $this->assertFalse(auth_manager::is_multitenant_auth_plugin('mod_forum'));
        $this->assertFalse(auth_manager::is_multitenant_auth_plugin('nonexisting'));
    }

    /**
     * Provider to test auth status change
     *
     * @return array[]
     */
    public function auth_status_provider() {
        return [
            'Disabled -> Disabled, available ; Enabled' => [
                'initialauth' => 'lti',
                'defaultmodifications' => ['email' => auth_manager::STATUS_DISABLEDAVAILABLE],
                'tenantmodifications' => ['email' => auth_manager::STATUS_ENABLED],
                'expectedauthcore' => 'lti,email',
                'expectedauthdefault' => 'lti',
                'expectedauthtenant' => 'lti,email',
            ],
            'Enabled -> Disabled, available ; Enabled' => [
                'initialauth' => 'email,lti',
                'defaultmodifications' => ['email' => auth_manager::STATUS_DISABLEDAVAILABLE],
                'tenantmodifications' => ['email' => auth_manager::STATUS_ENABLED],
                'expectedauthcore' => 'email,lti',
                'expectedauthdefault' => 'lti',
                'expectedauthtenant' => 'email,lti',
            ],
            'Enabled -> Disabled, available ; Disabled' => [
                'initialauth' => 'lti',
                'defaultmodifications' => ['email' => auth_manager::STATUS_DISABLEDAVAILABLE],
                'tenantmodifications' => ['email' => auth_manager::STATUS_DISABLED],
                'expectedauthcore' => 'lti,email',
                'expectedauthdefault' => 'lti',
                'expectedauthtenant' => 'lti',
            ],
            'Enabled -> Enabled ; Disabled' => [
                'initialauth' => 'lti,email',
                'defaultmodifications' => ['email' => auth_manager::STATUS_ENABLED],
                'tenantmodifications' => ['email' => auth_manager::STATUS_DISABLED],
                'expectedauthcore' => 'lti,email',
                'expectedauthdefault' => 'lti,email',
                'expectedauthtenant' => 'lti,email', // The tenant-level status (disabled) is ignored here.
            ],
            'Disabled ; Enabled' => [
                'initialauth' => 'lti,email',
                'defaultmodifications' => ['email' => auth_manager::STATUS_DISABLED],
                'tenantmodifications' => ['email' => auth_manager::STATUS_ENABLED],
                'expectedauthcore' => 'lti',
                'expectedauthdefault' => 'lti',
                'expectedauthtenant' => 'lti', // The tenant-level status (enabled) is ignored here.
            ],
            'Enabled -> Enabled, optional ; Enabled' => [
                'initialauth' => 'email,lti',
                'defaultmodifications' => ['email' => auth_manager::STATUS_ENABLEDOPTIONAL],
                'tenantmodifications' => ['email' => auth_manager::STATUS_ENABLED],
                'expectedauthcore' => 'email,lti',
                'expectedauthdefault' => 'email,lti',
                'expectedauthtenant' => 'email,lti',
            ],
            'Enabled -> Enabled, optional ; Disabled' => [
                'initialauth' => 'email,lti',
                'defaultmodifications' => ['email' => auth_manager::STATUS_ENABLEDOPTIONAL],
                'tenantmodifications' => ['email' => auth_manager::STATUS_DISABLED],
                'expectedauthcore' => 'email,lti',
                'expectedauthdefault' => 'email,lti',
                'expectedauthtenant' => 'lti',
            ],
        ];
    }

    /**
     * Test for changing the authentication method in the site admin and for the tenant
     *
     * @dataProvider auth_status_provider
     * @param string $initialauth initial value of $CFG->auth
     * @param array $defaultmodifications modifications for the default tenant (i.e. "enable auth method X")
     * @param array $tenantmodifications modifications for a tenant on top of it
     * @param string $expectedauthcore expected value of $CFG->auth (as it is stored in the database)
     * @param string $expectedauthdefault expected value for the "default tenant"
     * @param string $expectedauthtenant expected value for the tenant
     */
    public function test_auth_status(string $initialauth, array $defaultmodifications, array $tenantmodifications,
                                     string $expectedauthcore, string $expectedauthdefault, string $expectedauthtenant) {
        global $CFG;
        $tenant = $this->generator->create_tenant();
        set_config('auth', $initialauth);
        foreach ($defaultmodifications as $auth => $value) {
            auth_manager::change_default_auth_status($auth, $value);
        }
        foreach ($tenantmodifications as $auth => $value) {
            auth_manager::change_tenant_auth_status($auth, $tenant->id, $value);
        }

        $this->assertEquals($expectedauthcore, config::get_config_default('core', 'auth'));
        config::push_for_tenant(0);
        $this->assertEquals($expectedauthcore, $CFG->auth);
        config::push_for_tenant(\tool_tenant\tenancy::get_default_tenant_id());
        $this->assertEquals($expectedauthdefault, $CFG->auth);
        config::push_for_tenant($tenant->id);
        $this->assertEquals($expectedauthtenant, $CFG->auth);
    }

    public function test_change_default_status_registerauth() {
        global $CFG;
        auth_manager::change_default_auth_status('email', auth_manager::STATUS_ENABLEDOPTIONAL);
        set_config('registerauth', 'email');
        $this->assertEquals('email', $CFG->registerauth);

        auth_manager::change_default_auth_status('email', auth_manager::STATUS_DISABLEDAVAILABLE);
        $this->assertEquals('email', $CFG->registerauth);

        // Disabling the auth_email results in resetting registerauth.
        auth_manager::change_default_auth_status('email', auth_manager::STATUS_DISABLED);
        $this->assertEquals('', $CFG->registerauth);
    }

    public function test_get_default_auth_plugins() {
        global $CFG;
        $CFG->passwordpolicy = 0;
        [$tenant, $users] = $this->generator->create_tenant_and_users(2);
        $users[0]->auth = 'oauth2';
        user_update_user($users[0]);

        set_config('auth', 'webservice');
        auth_manager::change_default_auth_status('lti', auth_manager::STATUS_ENABLED);
        auth_manager::change_default_auth_status('email', auth_manager::STATUS_ENABLEDOPTIONAL);
        auth_manager::change_default_auth_status('oauth2', auth_manager::STATUS_DISABLEDAVAILABLE);
        auth_manager::change_tenant_auth_status('oauth2', $tenant->id, auth_manager::STATUS_ENABLED);
        // Not possible to change status of 'manual'.
        $this->assertNull(auth_manager::change_default_auth_status('manual', auth_manager::STATUS_DISABLED));
        $this->assertNull(auth_manager::change_tenant_auth_status('manual', $tenant->id, auth_manager::STATUS_DISABLED));
        // Invalid status is ignored.
        $this->assertNull(auth_manager::change_default_auth_status('webservice', 50));
        $this->assertNull(auth_manager::change_tenant_auth_status('webservice', $tenant->id, 60));

        // Check the results of the function get_default_auth_plugins.
        $authplugins = auth_manager::get_default_auth_plugins();
        $authsavailable = array_keys(\core_component::get_plugin_list('auth'));
        $this->assertEqualsCanonicalizing($authsavailable, array_keys($authplugins));
        $laststatus = 1;
        foreach ($authplugins as $auth => $authplugin) {
            // Enabled plugins are always in the beginning.
            $this->assertTrue((int)(bool)$authplugin['status'] <= (int)(bool)$laststatus);
            $laststatus = $authplugin['status'];
            // Expected plugin status.
            if (in_array($auth, ['lti', 'manual', 'nologin', 'webservice'])) {
                $this->assertEquals(auth_manager::STATUS_ENABLED, $authplugin['status']);
            } else if ($auth === 'email') {
                $this->assertEquals(auth_manager::STATUS_ENABLEDOPTIONAL, $authplugin['status']);
            } else if (in_array($auth, ['oauth2'])) {
                $this->assertEquals(auth_manager::STATUS_DISABLEDAVAILABLE, $authplugin['status']);
            } else {
                $this->assertEquals(0, $authplugin['status']);
            }
        }
        // User count.
        $usercounts = array_filter(array_column($authplugins, 'users', 'auth'));
        $this->assertEquals(['manual' => 3, 'oauth2' => 1], $usercounts);

        // Check the results of the function get_plugins_enabled_anywhere.
        $this->assertEqualsCanonicalizing(['manual', 'nologin', 'webservice', 'lti', 'email', 'oauth2'],
            auth_manager::get_plugins_enabled_anywhere());

        // Check the results of the function get_tenant_auth_plugins. Default tenant.
        $authpluginstenant = auth_manager::get_tenant_auth_plugins(\tool_tenant\tenancy::get_default_tenant_id());
        $statuses = array_column($authpluginstenant, 'status', 'auth');
        $this->assertEquals(['manual' => 1, 'nologin' => 1, 'webservice' => 1, 'lti' => 1, 'email' => 1, 'oauth2' => 0],
            $statuses);
        $usercounts = array_filter(array_column($authpluginstenant, 'users', 'auth'));
        $this->assertEquals(['manual' => 1], $usercounts); // Only admin.

        // Check the results of the function get_tenant_auth_plugins. Tenant.
        $authpluginstenant = auth_manager::get_tenant_auth_plugins($tenant->id);
        $statuses = array_column($authpluginstenant, 'status', 'auth');
        $this->assertEquals(['manual' => 1, 'nologin' => 1, 'webservice' => 1, 'lti' => 1, 'email' => 1, 'oauth2' => 1],
            $statuses);
        $usercounts = array_filter(array_column($authpluginstenant, 'users', 'auth'));
        $this->assertEquals(['manual' => 1, 'oauth2' => 1], $usercounts);

        // Function get_default_auth_plugins can automatically correct value of $CFG->auth.
        config::push_for_tenant(0);
        $authplugins = array_keys(auth_manager::get_default_auth_plugins());
        $this->assertEquals('webservice,lti,email,oauth2', $CFG->auth);
        set_config('auth', 'webservice,lti,email,oauth2,nonexisting');
        $authplugins2 = array_keys(auth_manager::get_default_auth_plugins());
        $this->assertEquals($authplugins, $authplugins2);
        $this->assertEquals('webservice,lti,email,oauth2', $CFG->auth);
    }

    public function test_get_default_auth_plugins_with_saml2() {
        global $CFG;
        if (!exists_auth_plugin('saml2')) {
            $this->markTestSkipped('Skipping due to SAML2 auth plugin unavailability');
        }

        $CFG->passwordpolicy = 0;
        [$tenant, $users] = $this->generator->create_tenant_and_users(3);
        $users[0]->auth = 'oauth2';
        $users[1]->auth = 'saml2';
        user_update_user($users[0]);
        user_update_user($users[1]);

        set_config('auth', 'webservice');
        auth_manager::change_default_auth_status('lti', auth_manager::STATUS_ENABLED);
        auth_manager::change_default_auth_status('email', auth_manager::STATUS_ENABLEDOPTIONAL);
        auth_manager::change_default_auth_status('oauth2', auth_manager::STATUS_DISABLEDAVAILABLE);
        auth_manager::change_tenant_auth_status('oauth2', $tenant->id, auth_manager::STATUS_ENABLED);
        auth_manager::change_default_auth_status('saml2', auth_manager::STATUS_DISABLEDAVAILABLE);
        auth_manager::change_tenant_auth_status('saml2', $tenant->id, auth_manager::STATUS_ENABLED);
        // Not possible to change status of 'manual'.
        $this->assertNull(auth_manager::change_default_auth_status('manual', auth_manager::STATUS_DISABLED));
        $this->assertNull(auth_manager::change_tenant_auth_status('manual', $tenant->id, auth_manager::STATUS_DISABLED));
        // Invalid status is ignored.
        $this->assertNull(auth_manager::change_default_auth_status('webservice', 50));
        $this->assertNull(auth_manager::change_tenant_auth_status('webservice', $tenant->id, 60));

        // Check the results of the function get_default_auth_plugins.
        $authplugins = auth_manager::get_default_auth_plugins();
        $authsavailable = array_keys(\core_component::get_plugin_list('auth'));
        $this->assertEqualsCanonicalizing($authsavailable, array_keys($authplugins));
        $laststatus = 1;
        foreach ($authplugins as $auth => $authplugin) {
            // Enabled plugins are always in the beginning.
            $this->assertTrue((int)(bool)$authplugin['status'] <= (int)(bool)$laststatus);
            $laststatus = $authplugin['status'];
            // Expected plugin status.
            if (in_array($auth, ['lti', 'manual', 'nologin', 'webservice'])) {
                $this->assertEquals(auth_manager::STATUS_ENABLED, $authplugin['status']);
            } else if ($auth === 'email') {
                $this->assertEquals(auth_manager::STATUS_ENABLEDOPTIONAL, $authplugin['status']);
            } else if (in_array($auth, ['oauth2', 'saml2'])) {
                $this->assertEquals(auth_manager::STATUS_DISABLEDAVAILABLE, $authplugin['status']);
            } else {
                $this->assertEquals(0, $authplugin['status']);
            }
        }
        // User count.
        $usercounts = array_filter(array_column($authplugins, 'users', 'auth'));
        $this->assertEquals(['manual' => 3, 'oauth2' => 1, 'saml2' => 1], $usercounts);

        // Check the results of the function get_tenant_auth_plugins. Default tenant.
        $authpluginstenant = auth_manager::get_tenant_auth_plugins(\tool_tenant\tenancy::get_default_tenant_id());
        $statuses = array_column($authpluginstenant, 'status', 'auth');
        $this->assertEquals(['manual' => 1, 'nologin' => 1, 'webservice' => 1, 'lti' => 1,
            'email' => 1, 'oauth2' => 0, 'saml2' => 0], $statuses);
        $usercounts = array_filter(array_column($authpluginstenant, 'users', 'auth'));
        $this->assertEquals(['manual' => 1], $usercounts); // Only admin.

        // Check the results of the function get_tenant_auth_plugins. Tenant.
        $authpluginstenant = auth_manager::get_tenant_auth_plugins($tenant->id);
        $statuses = array_column($authpluginstenant, 'status', 'auth');
        $this->assertEquals(['manual' => 1, 'nologin' => 1, 'webservice' => 1, 'lti' => 1,
            'email' => 1, 'oauth2' => 1, 'saml2' => 1], $statuses);
        $usercounts = array_filter(array_column($authpluginstenant, 'users', 'auth'));
        $this->assertEquals(['manual' => 1, 'oauth2' => 1, 'saml2' => 1], $usercounts);

        // Function get_default_auth_plugins can automatically correct value of $CFG->auth.
        config::push_for_tenant(0);
        $authplugins = array_keys(auth_manager::get_default_auth_plugins());
        $this->assertEquals('webservice,lti,email,oauth2,saml2', $CFG->auth);
        set_config('auth', 'webservice,lti,email,oauth2,saml2,nonexisting');
        $authplugins2 = array_keys(auth_manager::get_default_auth_plugins());
        $this->assertEquals($authplugins, $authplugins2);
        $this->assertEquals('webservice,lti,email,oauth2,saml2', $CFG->auth);
    }

    public function test_oauth2_issuers_availability() {
        $this->setAdminUser();

        $issuer1 = \core\oauth2\api::create_standard_issuer('google');
        $issuer2 = \core\oauth2\api::create_standard_issuer('microsoft');
        $issuer3 = \core\oauth2\api::create_standard_issuer('facebook');

        $tenant1 = $this->generator->create_tenant();
        $tenant2 = $this->generator->create_tenant();
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();

        issuer_helper::save_issuer_availability($issuer1->get('id'),
            issuer_helper::ISSUER_AVAILABLE_ALWAYS, [], 'oauth2');
        $this->assertEquals([issuer_helper::ISSUER_AVAILABLE_ALWAYS, []],
            issuer_helper::get_issuer_availability($issuer1->get('id'), 'oauth2'));

        issuer_helper::save_issuer_availability($issuer2->get('id'),
            issuer_helper::ISSUER_AVAILABLE_SELECTED, [$tenant1->id], 'oauth2');
        $this->assertEquals([issuer_helper::ISSUER_AVAILABLE_SELECTED, [$tenant1->id]],
            issuer_helper::get_issuer_availability($issuer2->get('id'), 'oauth2'));

        issuer_helper::save_issuer_availability($issuer3->get('id'),
            issuer_helper::ISSUER_AVAILABLE_EXCEPT, [$tenant2->id], 'oauth2');
        $this->assertEquals([issuer_helper::ISSUER_AVAILABLE_EXCEPT, [$tenant2->id]],
            issuer_helper::get_issuer_availability($issuer3->get('id'), 'oauth2'));

        // Issuer 1 should be available to: default tenant, tenant1 and tenant2.
        // Issuer 2 should be available to: tenant1.
        // Issuer 3 should be available to: default tenant, tenant1.
        $allissuers = \core\oauth2\api::get_all_issuers();
        $expected = [
            $issuer1->get('id') => [$defaulttenantid, $tenant1->id, $tenant2->id],
            $issuer2->get('id') => [$tenant1->id],
            $issuer3->get('id') => [$defaulttenantid, $tenant1->id],
        ];
        $actual = [];
        foreach ($allissuers as $issuer) {
            $actual[$issuer->get('id')] = array_filter(array_keys(\tool_tenant\tenancy::get_tenants()),
                function($tenantid) use ($issuer) {
                    return issuer_helper::issuer_available($issuer->get('id'), $tenantid, 'oauth2');
                });
        }
        $this->assertEqualsCanonicalizing($expected, $actual);

        issuer_helper::require_issuer_available($issuer2->get('id'), $tenant1->id, 'oauth2');

        try {
            issuer_helper::require_issuer_available($issuer2->get('id'), $tenant2->id, 'oauth2');
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('This issuer can not be used to login', $e->getMessage());
        }
    }

    public function test_saml2_issuers_availability() {
        if (!exists_auth_plugin('saml2')) {
            $this->markTestSkipped('Skipping due to SAML2 auth plugin unavailability');
        }

        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('auth_saml2');
        $entity1 = $generator->create_idp_entity();
        $entity1->md5entityid = md5($entity1->entityid);
        $entity2 = $generator->create_idp_entity();
        $entity2->md5entityid = md5($entity2->entityid);
        $entity3 = $generator->create_idp_entity();
        $entity3->md5entityid = md5($entity3->entityid);
        $auth = get_auth_plugin('saml2');

        $tenant1 = $this->generator->create_tenant();
        $tenant2 = $this->generator->create_tenant();
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();

        issuer_helper::save_issuer_availability($entity1->md5entityid,
            issuer_helper::ISSUER_AVAILABLE_ALWAYS, [], 'saml2');
        $this->assertEquals([issuer_helper::ISSUER_AVAILABLE_ALWAYS, []],
            issuer_helper::get_issuer_availability($entity1->md5entityid, 'saml2'));

        issuer_helper::save_issuer_availability($entity2->md5entityid,
            issuer_helper::ISSUER_AVAILABLE_SELECTED, [$tenant1->id], 'saml2');
        $this->assertEquals([issuer_helper::ISSUER_AVAILABLE_SELECTED, [$tenant1->id]],
            issuer_helper::get_issuer_availability($entity2->md5entityid, 'saml2'));

        issuer_helper::save_issuer_availability($entity3->md5entityid,
            issuer_helper::ISSUER_AVAILABLE_EXCEPT, [$tenant2->id], 'saml2');
        $this->assertEquals([issuer_helper::ISSUER_AVAILABLE_EXCEPT, [$tenant2->id]],
            issuer_helper::get_issuer_availability($entity3->md5entityid, 'saml2'));

        // Entity 1 should be available to: default tenant, tenant1 and tenant2.
        // Entity 2 should be available to: tenant1.
        // Entity 3 should be available to: default tenant, tenant1.
        $providers = auth_saml2_get_idps(false, true);
        // Flattern $providers one level down.
        $providers = array_merge(...array_values($providers));
        $expected = [
            $entity1->md5entityid => [$defaulttenantid, $tenant1->id, $tenant2->id],
            $entity2->md5entityid => [$tenant1->id],
            $entity3->md5entityid => [$defaulttenantid, $tenant1->id],
        ];
        $actual = [];
        foreach ($providers as $provider) {
            $actual[$provider['md5entityid']] = array_filter(array_keys(\tool_tenant\tenancy::get_tenants()),
                function($tenantid) use ($provider) {
                    return issuer_helper::issuer_available($provider['md5entityid'], $tenantid, 'saml2');
                });
        }
        $this->assertEqualsCanonicalizing($expected, $actual);

        issuer_helper::require_issuer_available($entity2->md5entityid, $tenant1->id, 'saml2');

        try {
            issuer_helper::require_issuer_available($entity2->md5entityid, $tenant2->id, 'saml2');
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals('This issuer can not be used to login', $e->getMessage());
        }
    }
}
