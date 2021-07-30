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
 * File containing tests for report access list class
 *
 * @package     tool_reportbuilder
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\audience_base;
use tool_reportbuilder\local\helpers\audience;
use tool_reportbuilder\test\mock_report;
use tool_reportbuilder\tool_reportbuilder\audiences\manual;

/**
 * Test class
 *
 * @package     tool_reportbuilder
 * @group       tool_reportbuilder
 * @category    test
 * @covers      \tool_reportbuilder\local\helpers\audience
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_helper_audience_testcase extends advanced_testcase {

    /** @var stdClass $user */
    protected $user;

    /**
     * Test setup
     */
    public function setUp(): void {
        $this->resetAfterTest();

        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);
    }

    /**
     * Test reports list is empty for a normal user without any audience records configured
     */
    public function test_reports_list_no_access(): void {
        $reports = audience::user_reports_list();
        $this->assertEmpty($reports);
    }

    /**
     * Test get_base_records()
     */
    public function test_get_base_records(): void {
        // Report with no audiences.
        $report = $this->get_plugin_generator()->create_report([
            'source' => mock_report::class,
        ]);
        $baserecords = audience::get_base_records($report->get_id());
        $this->assertEmpty($baserecords);

        // Create a couple of manual audience types.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'classname' => manual::class,
            'configdata' => ['users' => [$user1->id, $user2->id]],
        ]);
        $user3 = $this->getDataGenerator()->create_user();
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report->get_id(),
            'classname' => manual::class,
            'configdata' => ['users' => [$user3->id]],
        ]);

        $baserecords = audience::get_base_records($report->get_id());
        $this->assertCount(2, $baserecords);
        $this->assertInstanceOf(manual::class, $baserecords[0]);
        $this->assertInstanceOf(manual::class, $baserecords[1]);
    }

    /**
     * Test get_allowed_reports()
     */
    public function test_get_allowed_reports(): void {
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        self::setUser($user1);

        // No reports.
        $reports = audience::get_allowed_reports();
        $this->assertEmpty($reports);

        $report1 = $this->get_plugin_generator()->create_report(['source' => mock_report::class]);
        $report2 = $this->get_plugin_generator()->create_report(['source' => mock_report::class]);
        $report3 = $this->get_plugin_generator()->create_report(['source' => mock_report::class]);

        // Reports with no audiences set.
        $reports = audience::get_allowed_reports();
        $this->assertEmpty($reports);

        $this->get_plugin_generator()->create_audience([
            'reportid' => $report1->get_id(),
            'classname' => manual::class,
            'configdata' => ['users' => [$user1->id, $user2->id]],
        ]);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report2->get_id(),
            'classname' => manual::class,
            'configdata' => ['users' => [$user2->id]],
        ]);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report3->get_id(),
            'classname' => manual::class,
            'configdata' => ['users' => [$user1->id]],
        ]);

        $reports = audience::get_allowed_reports();
        $this->assertEqualsCanonicalizing([$report1->get_id(), $report3->get_id()], $reports);

        // User2 can access report1 and report2.
        self::setUser($user2);
        $reports = audience::get_allowed_reports();
        $this->assertEqualsCanonicalizing([$report1->get_id(), $report2->get_id()], $reports);

        // User2 calling the method for user1.
        $reports = audience::get_allowed_reports($user1->id);
        $this->assertEqualsCanonicalizing([$report1->get_id(), $report3->get_id()], $reports);
    }

    /**
     * Test user_reports_list()
     *
     * @covers \tool_reportbuilder\local\helpers\audience::user_reports_list_sql
     */
    public function test_user_reports_list(): void {
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        self::setUser($user1);

        $reports = audience::user_reports_list();
        $this->assertEmpty($reports);

        $report1 = $this->get_plugin_generator()->create_report(['source' => mock_report::class]);
        $report2 = $this->get_plugin_generator()->create_report(['source' => mock_report::class]);
        $report3 = $this->get_plugin_generator()->create_report(['source' => mock_report::class]);

        $this->get_plugin_generator()->create_audience([
            'reportid' => $report1->get_id(),
            'classname' => manual::class,
            'configdata' => ['users' => [$user1->id, $user2->id]],
        ]);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report2->get_id(),
            'classname' => manual::class,
            'configdata' => ['users' => [$user2->id]],
        ]);
        $this->get_plugin_generator()->create_audience([
            'reportid' => $report3->get_id(),
            'classname' => manual::class,
            'configdata' => ['users' => [$user1->id]],
        ]);

        // User1 can access report1 and report3.
        $reports = audience::user_reports_list();
        $this->assertEqualsCanonicalizing([$report1->get_id(), $report3->get_id()], $reports);

        // User2 can access report1 and report2.
        self::setUser($user2);
        $reports = audience::user_reports_list();
        $this->assertEqualsCanonicalizing([$report1->get_id(), $report2->get_id()], $reports);

        // User3 can not access any report.
        self::setUser($user3);
        $reports = audience::user_reports_list();
        $this->assertEmpty($reports);
    }

    /**
     * Test get_all_audience_instances()
     */
    public function test_get_all_audience_instances_by_category(): void {
        $user1 = $this->getDataGenerator()->create_user();
        self::setUser($user1);
        $audiences = audience::get_all_audience_types_by_category();
        $this->assertEquals([[], []], $audiences);

        self::setAdminUser();
        [$categories, $categorynames] = audience::get_all_audience_types_by_category();
        $this->assertArrayHasKey('general', $categorynames);
        $this->assertCount(3, $categories['general']);
    }

    /**
     * Test get_all_audience_types()
     */
    public function test_get_all_audience_types(): void {
        $audiencetypes = audience::get_all_audience_types();
        $this->assertNotEmpty($audiencetypes);
        foreach ($audiencetypes as $audiencetype) {
            $this->assertInstanceOf(audience_base::class, $audiencetype);
        }
    }

    /**
     * Get report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    protected function get_plugin_generator() : tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }
}
