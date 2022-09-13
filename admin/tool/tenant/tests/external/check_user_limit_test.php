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
 * File containing tests for tool_tenant\external\check_user_limit class.
 *
 * @package     tool_tenant
 * @category    test
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Hittesh Ahuja
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\external;

use advanced_testcase;
use external_api;
use tool_tenant_generator;

/**
 * Class tool_tenant_external_check_user_limit_testcase
 *
 * @package     tool_tenant
 * @category    test
 * @covers      \tool_tenant\external\check_user_limit
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Hittesh Ahuja
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class check_user_limit_test extends advanced_testcase {

    /** @var tool_tenant_generator */
    protected $generator;

    /**
     * Set up
     */
    protected function setUp(): void {
        $this->resetAfterTest();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Load required libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once("{$CFG->libdir}/externallib.php");
    }

    /**
     * Test method for test_check_user_limit().
     *
     */
    public function test_check_user_limit() {
        $this->resetAfterTest();
        $this->setAdminUser();
        // Nothing is set.
        $quota = external_api::clean_returnvalue(
            check_user_limit::execute_returns(),
            check_user_limit::execute()
        );
        $this->assertTrue($quota['result']);

        // Set site user limit to 1. Only admin user exists.
        set_config('userlimitenabled', 1);
        set_config('userlimit', 1);
        $quota = external_api::clean_returnvalue(
            check_user_limit::execute_returns(),
            check_user_limit::execute(\tool_tenant\tenancy::get_default_tenant_id(), 1)
        );
        $this->assertNotTrue($quota['result']);

        // Set limit to 2.
        set_config('userlimit', 2);
        $quota = external_api::clean_returnvalue(
            check_user_limit::execute_returns(),
            check_user_limit::execute(\tool_tenant\tenancy::get_default_tenant_id(), 1)
        );
        $this->assertTrue($quota['result']);
    }

    /**
     * Test method for test_check_user_limit() for tenant specific.
     *
     */
    public function test_tenant_check_user_limit() {
        $this->resetAfterTest();
        $this->setAdminUser();
        // Nothing is set. No limit reached.
        $limitreached = external_api::clean_returnvalue(
            check_user_limit::execute_returns(),
            check_user_limit::execute()
        );
        $this->assertTrue($limitreached['result']);

        // Create tenant and 2 users.
        [$tenant1, $users1] = $this->generator->create_tenant_and_users(2);
        // Set tenant user limit to 2.
        // Limit exhausted.
        set_config('tool_tenant_userlimitenabled', 1);
        set_config('tool_tenant_userlimit', 2);
        $limitreached = external_api::clean_returnvalue(
            check_user_limit::execute_returns(),
            check_user_limit::execute($tenant1->id, 1)
        );
        $this->assertNotTrue($limitreached['result']);

        // Test where tenant user limit is greater than site user limit.
        // In this case site user limit wins and we check limit against that.
        set_config('userlimitenabled', 1);
        set_config('userlimit', 2);
        set_config('tool_tenant_userlimit', 7);
        $limitreached = external_api::clean_returnvalue(
            check_user_limit::execute_returns(),
            check_user_limit::execute($tenant1->id, 1)
        );
        $this->assertNotTrue($limitreached['result']);

        // Test where tenant user limit is greater than site user limit.
        // Ignore tenant limit conditions.
        set_config('tool_tenant_userlimit', 7);
        $limitreached = external_api::clean_returnvalue(
            check_user_limit::execute_returns(),
            check_user_limit::execute($tenant1->id, 1)
        );
        $this->assertNotTrue($limitreached['result']);

    }
}

