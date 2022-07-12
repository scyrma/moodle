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

namespace tool_custompage;

use advanced_testcase;
use moodle_exception;
use tool_custompage_generator;
use tool_tenant_generator;

/**
 * Unit test for the tool_custompage lib class.
 *
 * @package    tool_custompage
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Carlos Castillo <carlos.castilo@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class lib_test extends advanced_testcase {

    /** @var tool_custompage_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_custompage');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    /**
     * Test for tool_custompage_potential_users_selector callback
     *
     * @covers ::tool_custompage_potential_users_selector()
     * @return void
     */
    public function test_tool_custompage_potential_users_selector(): void {
        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $custompage = $this->generator->create_page([
            'name' => 'Tenant page',
            'weight' => -1,
            'tenantid' => $tenant->id,
            'global' => false
        ]);

        // Area not passed.
        $res = tool_custompage_potential_users_selector('', $custompage->get('id'));
        $this->assertNull($res);

        // User can't edit custom page.
        $this->setUser($user);
        try {
            tool_custompage_potential_users_selector('custompage', $custompage->get('id'));
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
            $str = get_string('errorpageedit', 'tool_custompage');
            $this->assertStringContainsString($str, $e->getMessage());
        }

        $this->setAdminUser();
        $res = tool_custompage_potential_users_selector('custompage', $custompage->get('id'));
        $this->assertCount(3, $res);
    }
}
