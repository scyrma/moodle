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

namespace tool_certification\task;

use advanced_testcase;
use tool_certification_generator;
use tool_tenant_generator;

/**
 * Test deallocate_from_previous_tenant_programs task
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class deallocate_from_previous_tenant_certifications_test extends advanced_testcase {
    /** @var tool_certification_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->resetAfterTest();
    }

    /**
     * Test deallocate_from_previous_tenant_programs
     */
    public function test_deallocate_from_previous_tenant_programs(): void {
        global $DB;
        self::setAdminUser();

        // We retrieve default tenant.
        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        [$tenant2, [$user1, $user2, $user3, $user4]] = $this->tenantgenerator->create_tenant_and_users(4);

        $certification = $this->generator->generate_certification([
            'archived' => 0,
            'tenantid' => $tenant2->id,
        ]);
        $this->generator->allocate_users_to_certification($certification->get('id'), [$user1->id, $user2->id, $user3->id]);
        $certificationuser4 = $this->generator->allocate_user($user4->id, $certification->get('id'));

        $allocations = $DB->get_records('tool_certification_users', ['certificationid' => $certification->get('id')]);
        $this->assertCount(4, $allocations);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user3->id, $user4->id], array_column($allocations, 'userid'));

        $DB->set_field_select('tool_tenant_user', 'tenantid', $defaulttenantid, 'userid=:userid', ['userid' => $user1->id]);
        $DB->set_field_select('tool_tenant_user', 'tenantid', $defaulttenantid, 'userid=:userid', ['userid' => $user3->id]);

        (new \tool_certification\task\deallocate_from_previous_tenant_certifications())->execute();

        $allocations = $DB->get_records('tool_certification_users', ['certificationid' => $certification->get('id')]);
        $this->assertCount(2, $allocations);
        $this->assertEqualsCanonicalizing([$user2->id, $user4->id], array_column($allocations, 'userid'));

        // Test that orphan program user allocation gets deleted.
        $certificationuser4->delete();
        $allocations = $DB->get_records('tool_program_users', ['userid' => $user4->id]);
        $this->assertCount(1, $allocations);

        (new \tool_certification\task\deallocate_from_previous_tenant_certifications())->execute();

        $allocations = $DB->get_records('tool_program_users', ['userid' => $user4->id]);
        $this->assertCount(0, $allocations);
    }
}
