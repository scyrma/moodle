<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * File containing tests for jobs.
 *
 * @package     tool_organisation
 * @category    test
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The job test class.
 *
 * @package    tool_organisation
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @covers     \tool_organisation\job_manager
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_organisation_job_testcase extends advanced_testcase {

    /** @var stdClass */
    protected $pf;
    /** @var stdClass */
    protected $pfother;
    /** @var stdClass */
    protected $pa;
    /** @var stdClass */
    protected $pb;
    /** @var stdClass */
    protected $pa1;
    /** @var stdClass */
    protected $pa2;
    /** @var stdClass */
    protected $pb1;


    /** @var stdClass */
    protected $df;
    /** @var stdClass */
    protected $dfother;
    /** @var stdClass */
    protected $da;
    /** @var stdClass */
    protected $db;
    /** @var stdClass */
    protected $da1;
    /** @var stdClass */
    protected $da2;

    /** @var stdClass */
    protected $tenant;

    /**
     * Tenant generator
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Generate test structure
     */
    protected function generate_structure() {
        $this->resetAfterTest();
        $this->tenant = $this->get_tenant_generator()->create_tenant();

        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');

        $this->pf = $generator->create_position(['tenantid' => $this->tenant->id]);
        $this->pfother = $generator->create_position(['tenantid' => $this->tenant->id]);

        $this->pa = $generator->create_position(['parentid' => $this->pf->id]);
        $this->pb = $generator->create_position(['parentid' => $this->pf->id]);
        $this->pa1 = $generator->create_position(['parentid' => $this->pa->id]);
        $this->pa2 = $generator->create_position(['parentid' => $this->pa->id]);
        $this->pb1 = $generator->create_position(['parentid' => $this->pb->id]);

        $this->df = $generator->create_department(['tenantid' => $this->tenant->id]);
        $this->dfother = $generator->create_department(['tenantid' => $this->tenant->id]);

        $this->da = $generator->create_department(['parentid' => $this->df->id]);
        $this->db = $generator->create_department(['parentid' => $this->df->id]);
        $this->da1 = $generator->create_department(['parentid' => $this->da->id]);
        $this->da2 = $generator->create_department(['parentid' => $this->da->id]);
    }

    /**
     * Test for \tool_organisation\job_manager::create_job, update_job, get_job
     */
    public function test_generator_assignment() {
        global $DB;
        $this->generate_structure();

        $user = $this->getDataGenerator()->create_user();
        $this->get_tenant_generator()->allocate_user($user->id, $this->tenant->id);
        $this->setUser($user);

        $this->assertEquals(0, $DB->count_records('tool_organisation_job'));
        $manager = new \tool_organisation\job_manager();
        $job = $manager->create_job((object)['userid' => $user->id,
            'positionid' => $this->pa1->id, 'departmentid' => $this->da1->id, 'startdate' => 1262304000]);

        $record = $DB->get_record('tool_organisation_job', ['id' => $job->get('id')]);
        $this->assertEquals($user->id, $record->userid);
        $this->assertEquals($this->da1->id, $record->departmentid);
        $this->assertEquals($this->pa1->id, $record->positionid);
        $this->assertEquals($this->tenant->id, $record->tenantid);
        $this->assertEquals(1262304000, $record->startdate);

        $manager->update_job($job->get('id'), (object)['startdate' => 1263513600]);
        $record = $DB->get_record('tool_organisation_job', ['id' => $job->get('id')]);
        $this->assertEquals(1263513600, $record->startdate);
    }

    /**
     * Basic test for the jobs list class.
     */
    public function test_jobs_list() {
        global $DB, $PAGE;
        $this->generate_structure();

        $user = $this->getDataGenerator()->create_user();
        $this->get_tenant_generator()->allocate_user($user->id, $this->tenant->id);
        $this->setUser($user);

        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
        $generator->assign_job((object)['userid' => $user->id,
            'positionid' => $this->pa1->id, 'departmentid' => $this->da1->id]);

        $report = \tool_reportbuilder\system_report_factory::create(\tool_organisation\jobs_list::class);
        $this->assertEquals(tool_organisation\jobs_list::class, get_class($report));
        $reportid = $report->get_id();
        $dbcolumns = $DB->get_records('tool_reportbuilder_column', ['reportid' => $reportid]);
        $this->assertEquals(5, count($dbcolumns));

        // If we initiate the same report again the id will be the same.
        $report2 = \tool_reportbuilder\system_report_factory::create(\tool_organisation\jobs_list::class);
        $this->assertEquals($report->get_id(), $report2->get_id());

        // Try to render the report only to make sure that there are no debugging and other errors.
        // The contents of the report is checked in behat test.
        $PAGE->set_url('/');
        (new \tool_reportbuilder\output\system_report($report))->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Test for delete job
     */
    public function test_delete() {
        global $DB;
        $this->generate_structure();

        $user = $this->getDataGenerator()->create_user();
        $this->get_tenant_generator()->allocate_user($user->id, $this->tenant->id);
        $this->setUser($user);

        /** @var tool_organisation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_organisation');
        $generator->assign_job((object)['userid' => $user->id,
            'positionid' => $this->pa1->id, 'departmentid' => $this->da1->id]);

        $jobid = $DB->get_field('tool_organisation_job', 'id', ['userid' => $user->id]);
        $this->assertNotEmpty($jobid);
        $manager = new \tool_organisation\job_manager();
        $manager->delete_job($jobid);
        $jobid = $DB->get_field('tool_organisation_job', 'id', ['userid' => $user->id]);
        $this->assertEmpty($jobid);
    }

    /**
     * Test event is triggered for create job.
     *
     * @covers \tool_organisation\event\job_created
     */
    public function test_create_job_triggers_event(): void {
        global $USER;

        $this->generate_structure();

        $user = $this->getDataGenerator()->create_user();
        $this->get_tenant_generator()->allocate_user($user->id, $this->tenant->id);
        $this->setUser($user);

        $sink = $this->redirectEvents();

        // Create job, this will trigger event.
        $manager = new \tool_organisation\job_manager();
        $job = $manager->create_job((object)['userid' => $user->id,
            'positionid' => $this->pa1->id, 'departmentid' => $this->da1->id, 'startdate' => 1262304000]);

        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(\tool_organisation\event\job_created::class, $event);

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($job->get('id'), $event->objectid);
        $this->assertEquals($user->id, $event->relateduserid);

        // Test event get_name().
        $eventname = get_string('eventjobcreated', 'tool_organisation');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '" . $USER->id . "' created the job with id '" . $job->get('id') . "' " .
            "for user with the id '" . $user->id . "'";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $this->assertEquals(\tool_organisation\job_manager::get_job_url(), $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(\core\event\base::NOT_MAPPED, $event::get_objectid_mapping());

        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Test event is triggered for job update.
     *
     * @covers \tool_organisation\event\job_updated
     */
    public function test_update_job_triggers_event(): void {
        global $USER;

        $this->generate_structure();

        $user = $this->getDataGenerator()->create_user();
        $this->get_tenant_generator()->allocate_user($user->id, $this->tenant->id);
        $this->setUser($user);

        // Create job.
        $manager = new \tool_organisation\job_manager();
        $job = $manager->create_job((object)['userid' => $user->id,
            'positionid' => $this->pa1->id, 'departmentid' => $this->da1->id, 'startdate' => 1262304000]);

        $sink = $this->redirectEvents();

        // Update job, this will trigger event.
        $manager->update_job($job->get('id'), (object)['startdate' => 1263513600]);

        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(\tool_organisation\event\job_updated::class, $event);

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($job->get('id'), $event->objectid);
        $this->assertEquals($user->id, $event->relateduserid);

        // Test event get_name().
        $eventname = get_string('eventjobupdated', 'tool_organisation');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '" . $USER->id . "' updated the job with id '" . $job->get('id') . "' " .
            "for user with the id '" . $user->id . "'";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $this->assertEquals(\tool_organisation\job_manager::get_job_url(), $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(\core\event\base::NOT_MAPPED, $event::get_objectid_mapping());

        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Test event is triggered for job deletion.
     *
     * @covers \tool_organisation\event\job_deleted
     */
    public function test_delete_job_triggers_event(): void {
        global $USER;

        $this->generate_structure();

        $user = $this->getDataGenerator()->create_user();
        $this->get_tenant_generator()->allocate_user($user->id, $this->tenant->id);
        $this->setUser($user);

        // Create job.
        $manager = new \tool_organisation\job_manager();
        $job = $manager->create_job((object)['userid' => $user->id,
            'positionid' => $this->pa1->id, 'departmentid' => $this->da1->id, 'startdate' => 1262304000]);

        $sink = $this->redirectEvents();

        // Delete job, this will trigger event.
        $manager->delete_job($job->get('id'));

        $events = $sink->get_events();
        $sink->close();

        $event = array_pop($events);
        $this->assertInstanceOf(\tool_organisation\event\job_deleted::class, $event);

        // Check that the event data is valid.
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertEquals($job->get('id'), $event->objectid);
        $this->assertEquals($user->id, $event->relateduserid);

        // Test event get_name().
        $eventname = get_string('eventjobdeleted', 'tool_organisation');
        $this->assertEquals($eventname, $event::get_name());

        // Test event get_description().
        $eventdescription = "The user with id '" . $USER->id . "' deleted the job with id '" . $job->get('id') . "' " .
            "for user with the id '" . $user->id . "'";
        $this->assertEquals($eventdescription, $event->get_description());

        // Test event get_url().
        $this->assertEquals(\tool_organisation\job_manager::get_job_url(), $event->get_url());

        // Test event get_objectid_mapping().
        $this->assertEquals(\core\event\base::NOT_MAPPED, $event::get_objectid_mapping());

        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }
}
