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

/**
 * File containing tests for export/import badge mapper class
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\mapper;

defined('MOODLE_INTERNAL') || die;

use stdClass;
use tool_wp\local\exportimport\helper;

global $CFG;
require_once("{$CFG->libdir}/badgeslib.php");

/**
 * Test class
 *
 * @package     tool_wp
 * @group       tool_wp
 * @category    test
 * @covers      \tool_wp\tool_wp\mapper\badge
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class badge_mapper_test extends \advanced_testcase {

    /**
     * Test mapper returns mapping data correctly for given badge
     */
    public function test_get_mapping_data_for_workplace_export() {
        $this->resetAfterTest();

        $badge = $this->create_badge('My new badge', BADGE_TYPE_SITE, BADGE_STATUS_ACTIVE);

        $mapper = helper::find_mapper_for_entity('badge', helper::get_all_mappers());
        $this->assertInstanceOf(badge::class, $mapper);

        $data = $mapper->get_mapping_data_for_workplace_export($badge->id);
        $this->assertEquals([
            'id' => $badge->id,
            'name' => $badge->name,
        ], $data);
    }

    /**
     * Test the mapper class successfully locates existing badge
     */
    public function test_locate_mapping_success(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $badge = $this->create_badge('My new badge', BADGE_TYPE_SITE, BADGE_STATUS_ACTIVE);

        $mapping = $this->get_plugin_generator()->locate_mapping('badge', ['name' => $badge->name]);
        $this->assertEquals([$badge->id, [], [], true], $mapping);
    }

    /**
     * Data provider for testing non-matching badges
     *
     * @see test_locate_mapping_error
     *
     * @return array
     */
    public function locate_mapping_error_provider(): array {
        return [
            ['My badge', BADGE_TYPE_SITE, BADGE_STATUS_ACTIVE, 'My other badge'],
            ['My badge', BADGE_TYPE_COURSE, BADGE_STATUS_ACTIVE],
            ['My badge', BADGE_TYPE_SITE, BADGE_STATUS_ARCHIVED],
            ['My badge', BADGE_TYPE_SITE, BADGE_STATUS_ACTIVE, 'My badge', false],
        ];
    }

    /**
     * Test mapper returns errors for non-matching badges
     *
     * @param string $name
     * @param int $type
     * @param int $status
     * @param string $namelocate
     * @param bool $adminuser
     *
     * @dataProvider locate_mapping_error_provider
     */
    public function test_locate_mapping_error(string $name, int $type, int $status, string $namelocate = '',
            bool $adminuser = true): void {

        $this->resetAfterTest();
        if ($adminuser) {
            $this->setAdminUser();
        }

        $badge = $this->create_badge($name, $type, $status);

        // Default to locating the actual badge name.
        $namelocate = $namelocate ?: $badge->name;
        list($badgeid, $notices, $errors, $validated) =
            $this->get_plugin_generator()->locate_mapping('badge', ['name' => $namelocate]);

        $this->assertNull($badgeid);
        $this->assertEmpty($notices);
        $this->assertCount(1, $errors);
        $this->assertEquals("Badge '{$namelocate}' was not found", reset($errors));
        $this->assertFalse($validated);
    }

    /**
     * Helper method to create a badge instance
     *
     * @param string $name
     * @param int $type
     * @param int $status
     * @return stdClass
     */
    protected function create_badge(string $name, int $type, int $status): stdClass {
        global $DB;

        $time = time();
        $user = get_admin();

        $badge = (object) [
            'name' => $name,
            'description' => 'Testing course badges',
            'type' => $type,
            'courseid' => SITEID,
            'timecreated' => $time,
            'timemodified' => $time,
            'usercreated' => $user->id,
            'usermodified' => $user->id,
            'issuername' => 'Test issuer',
            'issuerurl' => 'http://issuer-url.domain.co.nz',
            'issuercontact' => 'issuer@example.com',
            'expiredate' => null,
            'expireperiod' => null,
            'messagesubject' => 'Test message subject for badge',
            'message' => 'Test message body for badge',
            'attachment' => 1,
            'notification' => 0,
            'status' => $status,
            'version' => '1',
            'language' => 'en',
            'imageauthorname' => 'Image author',
            'imageauthoremail' => 'imageauthor@example.com',
            'imageauthorurl' => 'http://image-author-url.domain.co.nz',
            'imagecaption' => 'Caption',
        ];

        $badge->id = $DB->insert_record('badge', $badge);

        return $badge;
    }

    /**
     * Returns the plugin generator
     *
     * @return \tool_wp_generator
     */
    protected function get_plugin_generator(): \tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }
}
