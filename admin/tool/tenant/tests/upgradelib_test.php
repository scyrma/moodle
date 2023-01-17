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

namespace tool_tenant;

use advanced_testcase;
use context_system;
use core_user\reportbuilder\datasource\users;
use tool_tenant_generator;

/**
 * Class tool_tenant_upgradelib_testcase
 *
 * @package   tool_tenant
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 Odei Alba
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class upgradelib_test extends advanced_testcase {
    /** @var tool_tenant_generator */
    protected $generator;

    /**
     * Set up
     */
    protected function setUp(): void {
        $this->resetAfterTest();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Setup to ensure that tool_tenant_upgrade_remove_orphaned_files is loaded.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once($CFG->dirroot . '/'.$CFG->admin.'/tool/tenant/db/upgradelib.php');
    }

    /**
     * Tests for function tool_tenant_upgrade_remove_orphaned_files()
     * @covers    ::tool_tenant_upgrade_remove_orphaned_files
     */
    public function test_upgrade_remove_orphaned_files(): void {
        global $DB;

        $tenantone = $this->generator->create_tenant();
        $tenanttwo = $this->generator->create_tenant();
        $twoid = $tenanttwo->id;

        $tenanttodelete = new tenant($twoid);
        $tenanttodelete->delete();

        $this->assertNotEquals(0, $DB->count_records('files', ['component' => 'tool_tenant', 'itemid' => $tenantone->id]));
        $this->assertNotEquals(0, $DB->count_records('files', ['component' => 'tool_tenant', 'itemid' => $twoid]));

        // Run upgrade script.
        tool_tenant_upgrade_remove_orphaned_files();

        // Make sure there is no file left for empty tenant.
        $this->assertNotEquals(0, $DB->count_records('files', ['component' => 'tool_tenant', 'itemid' => $tenantone->id]));
        $this->assertEquals(0, $DB->count_records('files', ['component' => 'tool_tenant', 'itemid' => $twoid]));
    }

    /**
     * Tests for function tool_tenant_upgrade_migrate_old_tenant_colours()
     * @covers ::tool_tenant_upgrade_migrate_old_tenant_colours
     */
    public function test_upgrade_migrate_old_tenant_colours(): void {
        global $DB;

        // Create tenantone with tenant colours and customcss.
        $tenantone = $this->generator->create_tenant(
            ['cssconfig' => json_encode([
                'primary' => '#ff8e84',
                'brand' => '#2b2b2b',
                'button' => '#f39519',
                'drawer' => '#ffffee',
                'footer' => '#ffffff',
                'customcss' => '$somevarible: blue;',
            ])]
        );
        // Create tenanttwo with empty colours configuration.
        $tenanttwo = $this->generator->create_tenant(
            ['cssconfig' => json_encode(['customcss' => '$somevarible: red;'])]
        );
        // Create tenantthree with no configuration.
        $tenantthree = $this->generator->create_tenant();

        // Sanity check.
        $tenants = $DB->get_records('tool_tenant');
        $t1cssconfig = json_decode($tenants[$tenantone->id]->cssconfig, true);
        $this->assertEquals('#ff8e84', $t1cssconfig['primary']);
        $this->assertEquals('#2b2b2b', $t1cssconfig['brand']);
        $this->assertNotEmpty($t1cssconfig['drawer']);
        $this->assertEquals('$somevarible: blue;', $t1cssconfig['customcss']);
        $t2cssconfig = json_decode($tenants[$tenanttwo->id]->cssconfig, true);
        $this->assertArrayNotHasKey('brand', $t2cssconfig);
        $this->assertEquals('$somevarible: red;', $t2cssconfig['customcss']);
        $t3cssconfig = json_decode($tenants[$tenantthree->id]->cssconfig ?? '', true);
        $this->assertNull($t3cssconfig);

        // Run upgrade script.
        tool_tenant_upgrade_migrate_old_tenant_colours();

        // Check tenantone new css config.
        $tenants = $DB->get_records('tool_tenant');
        $t1cssconfig = json_decode($tenants[$tenantone->id]->cssconfig, true);
        $this->assertEquals('#ff8e84', $t1cssconfig['brand']);
        $this->assertArrayNotHasKey('primary', $t1cssconfig);
        $this->assertArrayNotHasKey('drawer', $t1cssconfig);
        $this->assertStringContainsString('$navcolour: #2b2b2b;', $t1cssconfig['customcss']);
        $this->assertStringContainsString('$somevarible: blue;', $t1cssconfig['customcss']);
        $this->assertStringContainsString('End of automatically generated SCSS for color-yiq function', $t1cssconfig['customcss']);
        $this->assertStringContainsString('End of automatically generated SCSS for navbar', $t1cssconfig['customcss']);
        $this->assertStringContainsString('End of automatically generated SCSS for buttons', $t1cssconfig['customcss']);
        // Check mobile variables are generated.
        $this->assertStringContainsString('--wp_tool_tenant_config_button: #f39519;', $t1cssconfig['customcss']);
        $this->assertStringContainsString('--wp_tool_tenant_config_drawer: #ffffee;', $t1cssconfig['customcss']);

        // Check tenanttwo css config has no changes.
        $t2cssconfig = json_decode($tenants[$tenanttwo->id]->cssconfig, true);
        $this->assertArrayNotHasKey('brand', $t2cssconfig);
        $this->assertEquals('$somevarible: red;', $t2cssconfig['customcss']);

        // Check tenantthree css config has no changes.
        $t3cssconfig = json_decode($tenants[$tenantthree->id]->cssconfig ?? '', true);
        $this->assertNull($t3cssconfig);
    }
    /**
     * Tests for function tool_tenant_upgrade_replace_default_logo()
     * @covers ::tool_tenant_upgrade_replace_default_logo
     */
    public function test_upgrade_replace_default_logo(): void {
        global $CFG;

        $tenantone = $this->generator->create_tenant();
        $tenanttwo = $this->generator->create_tenant();
        $tenantthree = $this->generator->create_tenant();

        $fs = \get_file_storage();

        // Set old default header logo in tenantone (so it will need to be updated).
        $fs->delete_area_files(context_system::instance()->id, 'tool_tenant', 'headerlogo', $tenantone->id);
        $fs->create_file_from_pathname(['contextid' => \context_system::instance()->id, 'component' => 'tool_tenant',
            'userid' => get_admin()->id, 'filearea' => 'headerlogo', 'itemid' => $tenantone->id, 'filepath' => '/',
            'filename' => 'workplacelogo-small.png'],
            $CFG->dirroot . '/' . $CFG->admin . '/tool/tenant/pix/workplacelogo-small.png');

        // Set custom header logo in tenanttwo.
        $fs->delete_area_files(context_system::instance()->id, 'tool_tenant', 'headerlogo', $tenanttwo->id);
        $fs->create_file_from_string(['contextid' => \context_system::instance()->id, 'component' => 'tool_tenant',
            'userid' => get_admin()->id, 'filearea' => 'headerlogo', 'itemid' => $tenanttwo->id, 'filepath' => '/',
            'filename' => 'cat.jpg'],
            'pictureofacat');

        // Delete header logo in tenantthree.
        $fs->delete_area_files(context_system::instance()->id, 'tool_tenant', 'headerlogo', $tenantthree->id);

        // Sanity check.
        $files = $fs->get_area_files(context_system::instance()->id, 'tool_tenant', 'headerlogo',
            $tenantone->id, '', false);
        $headerlogo = reset($files);
        $this->assertEquals('workplacelogo-small.png', $headerlogo->get_filename());
        $files = $fs->get_area_files(context_system::instance()->id, 'tool_tenant', 'headerlogo',
            $tenantthree->id, '', false);
        $headerlogo = reset($files);
        $this->assertFalse($headerlogo);
        $files = $fs->get_area_files(context_system::instance()->id, 'tool_tenant', 'headerlogo',
            $tenanttwo->id, '', false);
        $headerlogo = reset($files);
        $this->assertEquals('cat.jpg', $headerlogo->get_filename());

        // Run upgrade script.
        tool_tenant_upgrade_replace_default_logo();

        // Check header logo was replaced in tenantone.
        $files = $fs->get_area_files(context_system::instance()->id, 'tool_tenant', 'headerlogo',
            $tenantone->id, '', false);
        $headerlogo = reset($files);
        $this->assertEquals('workplacelogo.png', $headerlogo->get_filename());

        // Check header logo was not replaced in tenanttwo.
        $files = $fs->get_area_files(context_system::instance()->id, 'tool_tenant', 'headerlogo',
            $tenanttwo->id, '', false);
        $headerlogo = reset($files);
        $this->assertEquals('cat.jpg', $headerlogo->get_filename());

        // Check header logo is still empty in tenantthree.
        $files = $fs->get_area_files(context_system::instance()->id, 'tool_tenant', 'headerlogo',
            $tenantthree->id, '', false);
        $headerlogo = reset($files);
        $this->assertFalse($headerlogo);
    }

    /**
     * Tests for function tool_tenant_upgrade_custom_reports()
     * @covers ::tool_tenant_upgrade_custom_reports
     */
    public function test_upgrade_custom_reports() {
        global $DB;
        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');

        $reportid = $rbgenerator->create_report(['name' => 'My report', 'source' => users::class, 'default' => false])
            ->get('id');
        $reportid2 = $rbgenerator->create_report(['name' => 'MCorusey report', 'source' => users::class, 'component' => 'user'])
            ->get('id');
        $this->assertEmpty($DB->get_field('reportbuilder_report', 'component', ['id' => $reportid]));

        tool_tenant_upgrade_custom_reports();

        $this->assertEquals('tool_tenant', $DB->get_field('reportbuilder_report', 'component', ['id' => $reportid]));
        $this->assertEquals('user', $DB->get_field('reportbuilder_report', 'component', ['id' => $reportid2]));
    }
}
