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

namespace tool_program\task;

use advanced_testcase;
use tool_program_generator;
use tool_tenant_generator;

/**
 * Class for refresh_program_calendar_events task
 *
 * @package   tool_program
 * @covers    \tool_program\task\refresh_program_calendar_events
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class refresh_program_calendar_events_test extends advanced_testcase {

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

    /**
     * Test for deallocate_from_previous_tenant_programs
     */
    public function test_deallocate_from_previous_tenant_programs(): void {
        global $DB;
        $this->resetAfterTest();
        self::setAdminUser();

        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        [$tenant2, [$user21, $user22]] = $this->tenantgenerator->create_tenant_and_users(2);

        $user11 = self::getDataGenerator()->create_user();
        $user12 = self::getDataGenerator()->create_user();
        $user13 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user11->id, $defaulttenantid);
        $this->tenantgenerator->allocate_user($user12->id, $defaulttenantid);
        $this->tenantgenerator->allocate_user($user13->id, $defaulttenantid);

        // Program on Default tenant.
        $program1 = $this->generator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $defaulttenantid,
        ]);
        $programuser11 = $this->generator->allocate_user_to_program($program1->get('id'), $user11->id);
        $programuser12 = $this->generator->allocate_user_to_program($program1->get('id'), $user12->id);

        // Program on Tenant2.
        $program2 = $this->generator->generate_program((object)[
            'archived' => 0,
            'tenantid' => $tenant2->id,
        ]);
        $programuser21 = $this->generator->allocate_user_to_program($program2->get('id'), $user21->id);
        $programuser22 = $this->generator->allocate_user_to_program($program2->get('id'), $user22->id);

        $this->assertCount(8, $DB->get_records('event', ['component' => 'tool_program']));

        $events = $DB->get_records('event', ['component' => 'tool_program', 'userid' => $user11->id]);
        $this->assertCount(2, $events);
        $dates = [$programuser11->get('duedate'), $programuser11->get('enddate')];
        $this->assertEqualsCanonicalizing($dates, array_column($events, 'timestart'));

        $events = $DB->get_records('event', ['component' => 'tool_program', 'userid' => $user21->id]);
        $this->assertCount(2, $events);
        $dates = [$programuser21->get('duedate'), $programuser21->get('enddate')];
        $this->assertEqualsCanonicalizing($dates, array_column($events, 'timestart'));

        // Set all userids to zero. This is the query used in MDL-67494 upgrade step.
        $DB->execute("UPDATE {event} SET userid = 0 WHERE eventtype <> 'user' OR priority <> 0");
        $this->assertCount(8, $DB->get_records('event', ['component' => 'tool_program', 'userid' => 0]));
        $this->assertCount(0, $DB->get_records('event', ['component' => 'tool_program', 'userid' => $user11->id]));
        $this->assertCount(0, $DB->get_records('event', ['component' => 'tool_program', 'userid' => $user21->id]));

        // The calendar events for user13 have correct userid on event table.
        $programuser13 = $this->generator->allocate_user_to_program($program1->get('id'), $user13->id);
        $events = $DB->get_records('event', ['component' => 'tool_program', 'userid' => $user13->id]);
        $this->assertCount(2, $events);
        $dates = [$programuser13->get('duedate'), $programuser13->get('enddate')];
        $this->assertEqualsCanonicalizing($dates, array_column($events, 'timestart'));

        (new \tool_program\task\refresh_program_calendar_events())->execute();

        $this->assertCount(10, $DB->get_records('event', ['component' => 'tool_program']));
        $this->assertCount(0, $DB->get_records('event', ['component' => 'tool_program', 'userid' => 0]));

        $events = $DB->get_records('event', ['component' => 'tool_program', 'userid' => $user11->id]);
        $this->assertCount(2, $events);
        $dates = [$programuser11->get('duedate'), $programuser11->get('enddate')];
        $this->assertEqualsCanonicalizing($dates, array_column($events, 'timestart'));

        $events = $DB->get_records('event', ['component' => 'tool_program', 'userid' => $user21->id]);
        $this->assertCount(2, $events);
        $dates = [$programuser21->get('duedate'), $programuser21->get('enddate')];
        $this->assertEqualsCanonicalizing($dates, array_column($events, 'timestart'));

        $events = $DB->get_records('event', ['component' => 'tool_program', 'userid' => $user13->id]);
        $this->assertCount(2, $events);
        $dates = [$programuser13->get('duedate'), $programuser13->get('enddate')];
        $this->assertEqualsCanonicalizing($dates, array_column($events, 'timestart'));
    }
}
