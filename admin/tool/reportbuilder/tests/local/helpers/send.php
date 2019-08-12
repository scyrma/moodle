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
 * File containing tests for send helper class.
 *
 * @package   tool_reportbuilder
 * @category test
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Class tool_reportbuilder_send_testcase
 *
 * @package   tool_reportbuilder
 * @category test
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_send_testcase extends advanced_testcase {

    /**
     * Test sendemail function
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_sendemail() {
        $this->resetAfterTest();
        unset_config('noemailever');
        $sink = $this->redirectEmails();

        $generator = $this->get_generator();

        $audience = [];
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => $i + 1])->id;
            $this->get_tenant_generator()->allocate_user($users[$i], \tool_tenant\tenancy::get_default_tenant_id());
        }
        $audience['users'] = $users;
        $reportid = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id()
            ])->id;

        $schedule = $generator->create_schedule(['reportid' => $reportid, 'audience' => json_encode($audience)]);
        $sendhelper = new \tool_reportbuilder\local\helpers\send($schedule->id);
        $sendhelper->sendemail();
        $messages = $sink->get_messages();

        $this->assertEquals(5, count($messages));
    }

    /**
     * Test sendemail function with a department as audience
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_sendmail_with_a_deparment_as_audience() {
        $this->resetAfterTest();
        unset_config('noemailever');
        $sink = $this->redirectEmails();

        $generator = $this->get_generator();
        /** @var tool_organisation_generator $orggenerator */
        $orggenerator = advanced_testcase::getDataGenerator()->get_plugin_generator('tool_organisation');

        $pf = $orggenerator->create_position(['tenantid' => \tool_tenant\tenancy::get_default_tenant_id()]);
        $pa = $orggenerator->create_position(['parentid' => $pf->id]);
        $df = $orggenerator->create_department(['tenantid' => \tool_tenant\tenancy::get_default_tenant_id()]);
        $da = $orggenerator->create_department(['parentid' => $df->id]);

        for ($i = 0; $i < 3; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => $i + 1])->id;
            $this->get_tenant_generator()->allocate_user($users[$i], \tool_tenant\tenancy::get_default_tenant_id());
            $orggenerator->assign_job((object)['userid' => $users[$i],
                'positionid' => $pa->id, 'departmentid' => $da->id]);

        }
        $audience['departmentid'] = $da->id;
        $reportid = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id()
            ])->id;

        $schedule = $generator->create_schedule(['reportid' => $reportid, 'audience' => json_encode($audience)]);
        $sendhelper = new \tool_reportbuilder\local\helpers\send($schedule->id);
        $sendhelper->sendemail();
        $messages = $sink->get_messages();

        $this->assertEquals(3, count($messages));
    }

    /**
     * Test sendemail function with a position as audience
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_sendmail_with_a_position_as_audience() {
        $this->resetAfterTest();
        unset_config('noemailever');
        $sink = $this->redirectEmails();

        $generator = $this->get_generator();
        /** @var tool_organisation_generator $orggenerator */
        $orggenerator = advanced_testcase::getDataGenerator()->get_plugin_generator('tool_organisation');

        $pf = $orggenerator->create_position(['tenantid' => \tool_tenant\tenancy::get_default_tenant_id()]);
        $pa = $orggenerator->create_position(['parentid' => $pf->id]);
        $df = $orggenerator->create_department(['tenantid' => \tool_tenant\tenancy::get_default_tenant_id()]);
        $da = $orggenerator->create_department(['parentid' => $df->id]);

        for ($i = 0; $i < 10; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => $i + 1])->id;
            $this->get_tenant_generator()->allocate_user($users[$i], \tool_tenant\tenancy::get_default_tenant_id());
            $orggenerator->assign_job((object)['userid' => $users[$i],
                'positionid' => $pa->id, 'departmentid' => $da->id]);

        }
        $audience['positionid'] = $pa->id;
        $reportid = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id()
            ])->id;

        $schedule = $generator->create_schedule(['reportid' => $reportid, 'audience' => json_encode($audience)]);
        $sendhelper = new \tool_reportbuilder\local\helpers\send($schedule->id);
        $sendhelper->sendemail();
        $messages = $sink->get_messages();

        $this->assertEquals(10, count($messages));
    }

    /**
     * Test sendemail function with a department and position as audience
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_sendmail_with_a_department_and_position_as_audience() {
        $this->resetAfterTest();
        unset_config('noemailever');
        $sink = $this->redirectEmails();

        $generator = $this->get_generator();
        /** @var tool_organisation_generator $orggenerator */
        $orggenerator = advanced_testcase::getDataGenerator()->get_plugin_generator('tool_organisation');

        $pf = $orggenerator->create_position(['tenantid' => \tool_tenant\tenancy::get_default_tenant_id()]);
        $pa = $orggenerator->create_position(['parentid' => $pf->id]);
        $pa2 = $orggenerator->create_position(['parentid' => $pf->id]);
        $df = $orggenerator->create_department(['tenantid' => \tool_tenant\tenancy::get_default_tenant_id()]);
        $da = $orggenerator->create_department(['parentid' => $df->id]);

        for ($i = 0; $i < 10; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => $i + 1])->id;
            $this->get_tenant_generator()->allocate_user($users[$i], \tool_tenant\tenancy::get_default_tenant_id());
            $orggenerator->assign_job((object)['userid' => $users[$i],
                'positionid' => $pa->id, 'departmentid' => $da->id]);

        }
        for ($i = 0; $i < 2; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => $i + 1])->id;
            $this->get_tenant_generator()->allocate_user($users[$i], \tool_tenant\tenancy::get_default_tenant_id());
            $orggenerator->assign_job((object)['userid' => $users[$i],
                'positionid' => $pa2->id, 'departmentid' => $da->id]);

        }
        $audience['positionid'] = $pa->id;
        $audience['departmentid'] = $da->id;
        $reportid = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id()
            ])->id;

        $schedule = $generator->create_schedule(['reportid' => $reportid, 'audience' => json_encode($audience)]);
        $sendhelper = new \tool_reportbuilder\local\helpers\send($schedule->id);
        $sendhelper->sendemail();
        $messages = $sink->get_messages();

        $this->assertEquals(12, count($messages));
    }

    /**
     * Test sendemail function with custom emails as audience
     *
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_sendmail_with_custom_emails_as_audience() {
        $this->resetAfterTest();
        unset_config('noemailever');
        $sink = $this->redirectEmails();

        $generator = $this->get_generator();

        $audience['emails'] = ['usermail1@fake.com', 'usermails2@fake.com'];
        $reportid = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id()
            ])->id;

        $schedule = $generator->create_schedule(['reportid' => $reportid, 'audience' => json_encode($audience)]);
        $sendhelper = new \tool_reportbuilder\local\helpers\send($schedule->id);
        $sendhelper->sendemail();
        $messages = $sink->get_messages();

        $this->assertEquals(2, count($messages));
    }

    /**
     * Test get_users function
     *
     * @throws ReflectionException
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_get_users() {
        $this->resetAfterTest();
        $audience = [];
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(['firstname' => 'User', 'lastname' => $i + 1])->id;
            $this->get_tenant_generator()->allocate_user($users[$i], \tool_tenant\tenancy::get_default_tenant_id());
        }
        $audience['users'] = $users;
        $generator = $this->get_generator();
        $reportid = $generator->create_report(
            [
                'source' => \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list::class,
                'tenantid' => \tool_tenant\tenancy::get_default_tenant_id()
            ])->id;
        $schedule = $generator->create_schedule(['reportid' => $reportid, 'audience' => json_encode($audience)]);
        $manager = new \tool_reportbuilder\local\helpers\send($schedule->id);

        $rc = new \ReflectionClass(get_class($manager));
        $rcm = $rc->getMethod('get_users');
        $rcm->setAccessible(true);
        $result = $rcm->invokeArgs($manager, []);

        $this->assertCount(5, $result);
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     * @throws coding_exception
     */
    protected function get_generator(): tool_reportbuilder_generator {
        /** @var tool_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
        return $generator;
    }

    /**
     * Returns the tenant generator
     *
     * @return tool_tenant_generator
     * @throws coding_exception
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }
}