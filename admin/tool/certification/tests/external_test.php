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
 * Tests for the tool_certification external class.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the tool_certification external class.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_certification_external_testcase extends externallib_advanced_testcase {
    /**
     * setUp.
     */
    public function setUp() {
        $this->resetAfterTest();
    }

    /**
     * Returns the certification generator
     * @return tool_certification_generator
     */
    protected function get_generator(): tool_certification_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_certification');
    }

    public function test_archive_certification() {
        global $DB;
        // We generate default tenant and user.
        $data = $this->get_generator()->create_tenant_and_user();
        $this->setUser($data->user);
        $this->get_generator()->assign_edit_capability($data->user->id, context_system::instance());

        $certification = $this->get_generator()->generate_certification(['tenantid' => $data->defaulttenantid, 'archived' => 0]);
        $certificationid = $certification->get('id');

        // Certification is not archived.
        $record = $DB->get_record('tool_certification', ['id' => $certificationid]);
        $this->assertEquals(0, $record->archived);

        \tool_certification\external::archive_certification($certificationid);

        // Certification is archived.
        $record = $DB->get_record('tool_certification', ['id' => $certificationid]);
        $this->assertEquals(1, $record->archived);
    }

    public function test_restore_certification() {
        global $DB;
        // We generate default tenant and user.
        $data = $this->get_generator()->create_tenant_and_user();
        $this->setUser($data->user);
        $this->get_generator()->assign_edit_capability($data->user->id, context_system::instance());

        $certification = $this->get_generator()->generate_certification(['tenantid' => $data->defaulttenantid, 'archived' => 1]);
        $certificationid = $certification->get('id');

        // Certification is archived.
        $record = $DB->get_record('tool_certification', ['id' => $certificationid]);
        $this->assertEquals(1, $record->archived);

        \tool_certification\external::restore_certification($certificationid);

        // Certification is not archived.
        $record = $DB->get_record('tool_certification', ['id' => $certificationid]);
        $this->assertEquals(0, $record->archived);
    }

    public function test_delete_certification() {
        global $DB;
        // We generate default tenant and user.
        $data = $this->get_generator()->create_tenant_and_user();
        $this->setUser($data->user);
        $this->get_generator()->assign_edit_capability($data->user->id, context_system::instance());

        $certification = $this->get_generator()->generate_certification(['tenantid' => $data->defaulttenantid, 'archived' => 1]);
        $certificationid = $certification->get('id');

        // Certification exists.
        $record = $DB->record_exists('tool_certification', ['id' => $certificationid]);
        $this->assertTrue($record);

        \tool_certification\external::delete_certification($certificationid);

        // Certification does not exist.
        $record = $DB->record_exists('tool_certification', ['id' => $certificationid]);
        $this->assertFalse($record);
    }

    public function test_potential_program_selector() {
        $data = $this->get_generator()->create_tenant_and_user();
        $this->get_generator()->assign_edit_capability($data->user->id, context_system::instance());
        $this->setUser($data->user);

        // We create a dummy program.
        $params = ['fullname' => 'A program fullname', 'tenantid' => $data->defaulttenantid];
        $programdata = $this->get_generator()->get_dummy_program($params);
        $program1 = \tool_program\api::create_program($programdata);

        // We create another dummy program.
        $programdata->fullname = 'A program number two';
        $program2 = \tool_program\api::create_program($programdata);

        // We create another dummy program with different tenantid.
        $programdata->fullname = 'A program number three';
        $programdata->fullname = $data->othertenantid;
        $program3 = \tool_program\api::create_program($programdata);

        $progs = \tool_certification\external::potential_program_selector('program');
        $this->assertCount(2, $progs);
        $this->assertArrayHasKey($program1->get('id'), $progs);
        $this->assertArrayHasKey($program2->get('id'), $progs);
        $this->assertArrayNotHasKey($program3->get('id'), $progs);

        $progs = \tool_certification\external::potential_program_selector('number');
        $this->assertCount(1, $progs);
        $this->assertArrayNotHasKey($program1->get('id'), $progs);
        $this->assertArrayHasKey($program2->get('id'), $progs);
        $this->assertArrayNotHasKey($program3->get('id'), $progs);

        $progs = \tool_certification\external::potential_program_selector('dogs');
        $this->assertCount(0, $progs);
        $this->assertArrayNotHasKey($program1->get('id'), $progs);
        $this->assertArrayNotHasKey($program2->get('id'), $progs);
        $this->assertArrayNotHasKey($program3->get('id'), $progs);
    }

    public function test_deallocate_user() {
        global $DB;
        $context = context_system::instance();
        // We generate default tenant and user.
        $data = $this->get_generator()->create_tenant_and_user();
        $this->setUser($data->user);
        $this->get_generator()->assign_allocateuser_capability($data->user->id, $context);
        $this->assertEquals($data->defaulttenantid, \tool_tenant\tenancy::get_tenant_id());

        $certification = $this->get_generator()->generate_certification(['tenantid' => $data->defaulttenantid]);

        $params = [
            'certificationid' => $certification->get('id'),
            'userid' => $data->user->id,
        ];

        $record = $DB->record_exists('tool_certification_users', $params);
        $this->assertFalse($record);

        $certuser = $this->get_generator()->allocate_user($data->user->id, $certification->get('id'));

        $record = $DB->record_exists('tool_certification_users', $params);
        $this->assertTrue($record);
        $this->assertInstanceOf(\tool_certification\certification_user::class, $certuser);

        \tool_certification\external::deallocate_user($certification->get('id'), $data->user->id);
        $record = $DB->record_exists('tool_certification_users', $params);
        $this->assertFalse($record);
    }

    public function test_certify_user() {
        global $DB;
        $context = context_system::instance();
        // We generate default tenant and user.
        $data = $this->get_generator()->create_tenant_and_user();
        $this->setUser($data->user);
        $this->get_generator()->assign_allocateuser_capability($data->user->id, $context);
        $this->assertEquals($data->defaulttenantid, \tool_tenant\tenancy::get_tenant_id());

        $certification = $this->get_generator()->generate_certification(['tenantid' => $data->defaulttenantid]);

        $params = [
            'certificationid' => $certification->get('id'),
            'userid' => $data->user->id,
        ];

        $record = $DB->record_exists('tool_certification_users', $params);
        $this->assertFalse($record);

        $this->get_generator()->allocate_user($data->user->id, $certification->get('id'));

        $record = $DB->record_exists('tool_certification_compltion', $params);
        $this->assertFalse($record);

        \tool_certification\external::certify_user($data->user->id,  $certification->get('id'), false);

        $record = $DB->record_exists('tool_certification_compltion', $params);
        $this->assertTrue($record);
    }
}