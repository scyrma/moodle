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
 * File containing tests for helper class.
 *
 * @package   tool_reportbuilder
 * @category  test
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class tool_reportbuilder_helper_testcase
 *
 * @package   tool_reportbuilder
 * @copyright 2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_helper_testcase extends advanced_testcase {

    /**
     * Basic test get_reports_select method.
     */
    public function test_get_reports_select() {
        $this->resetAfterTest();
        /** @var tool_reportbuilder_generator $generator */
        $generator = $this->get_generator();

        $generator->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);
        $generator->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);
        $generator->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        $reports = \tool_reportbuilder\helper::get_reports_select();
        $this->assertCount(3, $reports);

        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        $reports = \tool_reportbuilder\helper::get_reports_select();
        $this->assertCount(3, $reports);

        $othertenantid = $this->get_tenant_generator()->create_tenant()->id;
        $this->get_tenant_generator()->allocate_user($user1->id, $othertenantid);

        $reports = \tool_reportbuilder\helper::get_reports_select();
        $this->assertCount(0, $reports);

        $generator->create_report([
            'source' => \tool_reportbuilder\test\mock_report::class
        ]);

        $reports = \tool_reportbuilder\helper::get_reports_select();
        $this->assertCount(1, $reports);
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