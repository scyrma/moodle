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
 * Tests for the tool_certification lib class.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/admin/tool/certification/lib.php');
require_once($CFG->libdir . '/externallib.php');

/**
 * Tests for the tool_certification lib class.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_lib_testcase extends advanced_testcase {

    /** @var tool_certification_generator */
    protected $generator;

    /**
     * setUp.
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->resetAfterTest();
    }

    public function test_tool_certification_inplace_editable() {
        global $DB;
        $context = context_system::instance();

        // We generate default tenant and user.
        $data = $this->generator->create_tenant_and_user();
        $this->setUser($data->user);

        $itemtype = '';
        $itemid = 1;
        $newvalue = 'New text';

        try {
            tool_certification_inplace_editable($itemtype, $itemid, $newvalue);
            $this->fail('Exception expected');
        } catch (coding_exception $e) {
            $this->assertInstanceOf('coding_exception', $e);
            $this->assertStringContainsString('Unexpected tool_certification inplace editable item type', $e->getMessage());
        }

        $this->generator->assign_edit_capability($data->user->id, $context);
        $certification = $this->generator->generate_certification(['tenantid' => $data->defaulttenantid]);
        $itemtype = 'certificationname';
        $itemid = $certification->get('id');

        $record = $DB->get_record('tool_certification', ['id' => $itemid]);
        $this->assertNotEmpty($record);
        $this->assertEquals('A certification fullname', $record->fullname);

        $inplaceedit = tool_certification_inplace_editable($itemtype, $itemid, $newvalue);

        $this->assertInstanceOf(core\output\inplace_editable::class, $inplaceedit);
        $certification = new \tool_certification\certification($itemid);
        $this->assertEquals($newvalue, $certification->get('fullname'));

        $record = $DB->get_record('tool_certification', ['id' => $itemid], '*', MUST_EXIST);
        $this->assertEquals($newvalue, $record->fullname);
    }

    public function test_tool_certification_potential_users_selector() {
        $context = context_system::instance();

        // We generate default tenant and user.
        $data = $this->generator->create_tenant_and_user();
        $this->setUser($data->user);
        $certification = $this->generator->generate_certification(['tenantid' => $data->defaulttenantid]);

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
            $this->assertStringContainsString($str, $e->getMessage());
        }

        $this->generator->assign_allocateuser_capability($data->user->id, $context);
        $res = tool_certification_potential_users_selector('allocate', $certification->get('id'));
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
