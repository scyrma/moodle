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

namespace tool_certification;

use advanced_testcase;
use coding_exception;
use context_system;
use core\output\inplace_editable;
use moodle_exception;
use tool_certification_generator;
use tool_certificate\customfield\issue_handler;
use tool_dynamicrule\tool_dynamicrule\outcome\dummy;
use tool_certification\tool_dynamicrule\condition\certification_certified;
use tool_tenant_generator;

/**
 * Tests for the tool_certification lib class.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class lib_test extends advanced_testcase {

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
     * Load required test libraries
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/certification/lib.php');
    }

    public function test_tool_certification_inplace_editable(): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/externallib.php');
        $context = context_system::instance();

        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $this->setUser($user);

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

        $this->generator->assign_edit_capability($user->id, $context);
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        $itemtype = 'certificationname';
        $itemid = $certification->get('id');

        $record = $DB->get_record('tool_certification', ['id' => $itemid]);
        $this->assertNotEmpty($record);
        $this->assertEquals('A certification fullname', $record->fullname);

        $inplaceedit = tool_certification_inplace_editable($itemtype, $itemid, $newvalue);

        $this->assertInstanceOf(inplace_editable::class, $inplaceedit);
        $certification = new certification($itemid);
        $this->assertEquals($newvalue, $certification->get('fullname'));

        $record = $DB->get_record('tool_certification', ['id' => $itemid], '*', MUST_EXIST);
        $this->assertEquals($newvalue, $record->fullname);
    }

    public function test_tool_certification_potential_users_selector(): void {
        $context = context_system::instance();

        [$tenant, [$user]] = $this->tenantgenerator->create_tenant_and_users(1);
        $this->setUser($user);
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

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

        $this->generator->assign_allocateuser_capability($user->id, $context);
        $res = tool_certification_potential_users_selector('allocate', $certification->get('id'));
        $this->assertCount(3, $res);
    }

    /**
     * Test for callback 'wp_registration_stats'
     */
    public function test_registration_get_site_info(): void {
        global $CFG;
        $this->resetAfterTest(true);
        $origsiteinfo = $siteinfo = ['moodlerelease' => $CFG->release, 'url' => $CFG->wwwroot];
        component_class_callback('tool_wp\\registration', 'site_info', [&$siteinfo, false]);
        $appended = array_diff_key($siteinfo, $origsiteinfo);

        $this->assertNotNull($appended['wpcertifications']);

        $siteinfo = $origsiteinfo;
        component_class_callback('tool_wp\\registration', 'site_info', [&$siteinfo, true]);
    }

    /**
     * Data provider for test_tool_certification_tool_certificate_fields
     *
     * @return array
     */
    public function tool_certificate_fields_provider(): array {
        return [
            ['certificationid'],
            ['certificationname'],
            ['certificationdate'],
            ['certificationexpirydate'],
            ['expirydatetimestamp'],
        ];
    }

    /**
     * Test for callback 'tool_certification_tool_certificate_fields'
     *
     * @covers ::tool_certification_tool_certificate_fields
     *
     * @param string $fieldshortname
     * @dataProvider tool_certificate_fields_provider
     */
    public function test_tool_certification_tool_certificate_fields(string $fieldshortname): void {
        $condition = certification_certified::instance();
        $availabledata = $condition->get_available_data_for_outcome(dummy::instance());

        // Function get_all_fields_shortnames calls tool_certification_tool_certificate_fields, we don't need to call it manually.

        $handler = issue_handler::create();

        // Check that certificate field exists in issue_handler.
        $this->assertContains($fieldshortname, $handler->get_all_fields_shortnames());
        // Check that certificate field added by certification exists in available_data_for_outcome.
        $this->assertArrayHasKey($fieldshortname, $availabledata);
    }
}
