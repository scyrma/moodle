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
use tool_certification\api;
use tool_certification\certification_user;
use tool_certification\constants;
use tool_certification_generator;
use tool_tenant_generator;
use tool_program\persistent\program_user;

/**
 * Test refresh_certification_calendar_events task
 *
 * @package   tool_certification
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @covers    \tool_certification\task\refresh_certification_calendar_events
 * @author    2021 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class refresh_certification_calendar_events_test extends advanced_testcase {

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

        $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
        [$tenant2, [$user21, $user22]] = $this->tenantgenerator->create_tenant_and_users(2);

        $user11 = self::getDataGenerator()->create_user();
        $user12 = self::getDataGenerator()->create_user();
        $this->tenantgenerator->allocate_user($user11->id, $defaulttenantid);
        $this->tenantgenerator->allocate_user($user12->id, $defaulttenantid);

        $expirydate = 1898722800;
        $certification1 = $this->generator->generate_certification([
            'archived' => 0,
            'tenantid' => $defaulttenantid,
            'expirydatetype' => constants::DATE_ABSOLUTE,
            'expirydateabsolute' => $expirydate,
        ]);
        $certificationuser11 = $this->generator->allocate_user($user11->id, $certification1->get('id'));
        $programuser11 = program_user::get_record(['certificationid' => $certification1->get('id'), 'userid' => $user11->id]);
        $certifieddate11 = 1614726000;
        api::set_user_as_certified($user11->id, $certification1->get('id'), null, $certifieddate11);
        $certificationuser12 = $this->generator->allocate_user($user12->id, $certification1->get('id'));
        $programuser12 = program_user::get_record(['certificationid' => $certification1->get('id'), 'userid' => $user12->id]);

        $certification2 = $this->generator->generate_certification([
            'archived' => 0,
            'tenantid' => $tenant2->id,
            'expirydatetype' => constants::DATE_ABSOLUTE,
            'expirydateabsolute' => $expirydate,
        ]);
        $certificationuser21 = $this->generator->allocate_user($user21->id, $certification2->get('id'));
        $programuser21 = program_user::get_record(['certificationid' => $certification2->get('id'), 'userid' => $user21->id]);
        $params = ['userid' => $user21->id, 'certificationid' => $certification2->get('id')];
        /** @var certification_user $certificationuser */
        $certificationuser = certification_user::get_record($params);
        api::set_user_as_certified($user21->id, $certification2->get('id'));
        $certificationuser22 = $this->generator->allocate_user($user22->id, $certification2->get('id'));

        // We should have 2 due date and 2 expiry date calendar events.
        $this->assertCount(4, $DB->get_records('event', ['component' => 'tool_certification']));

        $events = $DB->get_records('event', ['component' => 'tool_certification', 'userid' => $user11->id]);
        $this->assertCount(1, $events);
        $this->assertEquals($expirydate, reset($events)->timestart);

        $events = $DB->get_records('event', ['component' => 'tool_certification', 'userid' => $user12->id]);
        $this->assertCount(1, $events);
        $this->assertEquals($programuser12->get('duedate'), reset($events)->timestart);

        $events = $DB->get_records('event', ['component' => 'tool_certification', 'userid' => $user21->id]);
        $this->assertCount(1, $events);
        $this->assertEquals($expirydate, reset($events)->timestart);

        // Set all userids to zero. This is the query used in MDL-67494 upgrade step.
        $DB->execute("UPDATE {event} SET userid = 0 WHERE eventtype <> 'user' OR priority <> 0");
        $this->assertCount(4, $DB->get_records('event', ['component' => 'tool_certification']));
        $this->assertCount(4, $DB->get_records('event', ['component' => 'tool_certification', 'userid' => 0]));

        (new \tool_certification\task\refresh_certification_calendar_events())->execute();

        $events = $DB->get_records('event', ['component' => 'tool_certification']);
        $this->assertCount(4, $DB->get_records('event', ['component' => 'tool_certification']));
        $this->assertCount(0, $DB->get_records('event', ['component' => 'tool_certification', 'userid' => 0]));

        $events = $DB->get_records('event', ['component' => 'tool_certification', 'userid' => $user11->id]);
        $this->assertCount(1, $events);
        $this->assertEquals($expirydate, reset($events)->timestart);

        $events = $DB->get_records('event', ['component' => 'tool_certification', 'userid' => $user12->id]);
        $this->assertCount(1, $events);
        $this->assertEquals($programuser12->get('duedate'), reset($events)->timestart);

        $events = $DB->get_records('event', ['component' => 'tool_certification', 'userid' => $user21->id]);
        $this->assertCount(1, $events);
        $this->assertEquals($expirydate, reset($events)->timestart);
    }
}
