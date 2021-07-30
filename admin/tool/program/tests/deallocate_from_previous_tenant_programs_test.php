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
 * Test deallocate_from_previous_tenant_programs task
 *
 * @package   tool_program
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class tool_program_deallocate_from_previous_tenant_programs_testcase
 *
 * @package   tool_program
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_deallocate_from_previous_tenant_programs_testcase extends advanced_testcase {
    /** @var tool_program_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    public function test_deallocate_from_previous_tenant_programs() {
        global $DB;
        $this->resetAfterTest();
        self::setAdminUser();

        // We retrieve default tenant.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        $tenant2 = $this->tenantgenerator->create_tenant();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user1->id, $tenant2->id);
        $this->tenantgenerator->allocate_user($user2->id, $tenant2->id);
        $this->tenantgenerator->allocate_user($user3->id, $tenant2->id);
        $this->tenantgenerator->allocate_user($user4->id, $tenant2->id);

        $program1 = $this->generator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant2->id,
        ]);
        $programuser1 = $this->generator->allocate_user_to_program($program1->get('id'), $user1->id);
        $programuser2 = $this->generator->allocate_user_to_program($program1->get('id'), $user2->id);
        $programuser3 = $this->generator->allocate_user_to_program($program1->get('id'), $user3->id);
        $programuser4 = $this->generator->allocate_user_to_program($program1->get('id'), $user4->id);

        $programusers = $DB->get_records('tool_program_users', ['programid' => $program1->get('id')]);
        $this->assertCount(4, $programusers);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id, $user4->id], array_column($programusers, 'userid'));

        $DB->set_field_select('tool_tenant_user', 'tenantid', $defaulttenantid, 'userid=:userid', ['userid' => $user1->id]);
        $DB->set_field_select('tool_tenant_user', 'tenantid', $defaulttenantid, 'userid=:userid', ['userid' => $user3->id]);

        (new \tool_program\task\deallocate_from_previous_tenant_programs())->execute();

        $programusers = $DB->get_records('tool_program_users', ['programid' => $program1->get('id')]);
        $this->assertCount(2, $programusers);
        $this->assertEqualsCanonicalizing([$user2->id, $user4->id], array_column($programusers, 'userid'));
    }
}
