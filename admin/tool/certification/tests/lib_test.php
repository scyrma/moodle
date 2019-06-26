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
 * Tests for the tool_certification lib class.
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/admin/tool/certification/lib.php');
require_once($CFG->libdir . '/externallib.php');

/**
 * Tests for the tool_certification lib class.
 *
 * @package    tool_certification
 * @copyright  2019 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_certification_lib_testcase extends advanced_testcase {
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

    public function test_tool_certification_output_fragment_certifications_manager_users_list() {
        global $PAGE, $CFG;
        $PAGE->set_url($CFG->dirroot . '/admin/tool/certification/index.php');
        $context = context_system::instance();
        // We generate default tenant and user.
        $data = $this->get_generator()->create_tenant_and_user();
        $this->setUser($data->user);
        $this->get_generator()->assign_allocateuser_capability($data->user->id, $context);
        $this->assertEquals($data->defaulttenantid, \tool_tenant\tenancy::get_tenant_id());

        $certification = $this->get_generator()->generate_certification(['tenantid' => $data->defaulttenantid]);
        $this->get_generator()->allocate_user($data->user->id, $certification->get('id'));

        $params = ['context' => $context, 'id' => $certification->get('id')];
        $res = tool_certification_output_fragment_certifications_manager_users_list($params);

        $this->assertContains($data->user->id, $res);
        $this->assertContains($data->user->firstname, $res);
    }

    public function test_tool_certification_inplace_editable() {
        global $DB;
        $context = context_system::instance();

        // We generate default tenant and user.
        $data = $this->get_generator()->create_tenant_and_user();
        $this->setUser($data->user);

        $itemtype = '';
        $itemid = 1;
        $newvalue = 'New text';

        try {
            tool_certification_inplace_editable($itemtype, $itemid, $newvalue);
            $this->fail('Exception expected');
        } catch (coding_exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
            $this->assertContains('Unexpected tool_certification inplace editable item type', $e->getMessage());
        }

        $this->get_generator()->assign_edit_capability($data->user->id, $context);
        $certification = $this->get_generator()->generate_certification(['tenantid' => $data->defaulttenantid]);
        $itemtype = 'certificationname';
        $itemid = $certification->get('id');

        $record = $DB->get_record('tool_certification', ['id' => $itemid]);
        $this->assertNotEmpty($record);
        $this->assertEquals('A certification fullname', $record->fullname);

        $inplaceedit = tool_certification_inplace_editable($itemtype, $itemid, $newvalue);

        $this->assertInstanceOf(core\output\inplace_editable::class, $inplaceedit);
        $certification = new \tool_certification\certification($itemid);
        $this->assertEquals($newvalue, $certification->get('fullname'));

        $record = $DB->get_record('tool_certification', ['id' => $itemid]);
        $this->assertNotEmpty($record);
        $this->assertEquals($newvalue, $record->fullname);
    }

    public function test_tool_certification_potential_users_selector() {
        $context = context_system::instance();

        // We generate default tenant and user.
        $data = $this->get_generator()->create_tenant_and_user();
        $this->setUser($data->user);
        $certification = $this->get_generator()->generate_certification(['tenantid' => $data->defaulttenantid]);

        // Area not passed.
        $res = tool_certification_potential_users_selector('', $certification->get('id'));
        $this->assertNull($res);

        // User has no allocate user permission.
        try {
            tool_certification_potential_users_selector('allocate', $certification->get('id'));
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertInstanceOf('moodle_exception', $e);
            $str = get_string('errorcantmanageusers', 'tool_certification');
            $this->assertContains($str, $e->getMessage());
        }

        $this->get_generator()->assign_allocateuser_capability($data->user->id, $context);
        $res = tool_certification_potential_users_selector('allocate', $certification->get('id'));
        $this->assertNotEmpty($res);
        $this->assertCount(3, $res);
    }

    /**
     * Test for callback 'wp_registration_stats'
     */
    public function test_registration_get_site_info() {
        global $CFG;
        $this->resetAfterTest(true);
        $origsiteinfo = $siteinfo = ['moodlerelease' => $CFG->release, 'url' => $CFG->wwwroot];
        component_class_callback('tool_wp\\registration', 'site_info', [&$siteinfo, false]);
        $appended = array_diff_key($siteinfo, $origsiteinfo);

        $this->assertNotNull($appended['wpcertifications']);

        $siteinfo = $origsiteinfo;
        component_class_callback('tool_wp\\registration', 'site_info', [&$siteinfo, true]);
    }
}