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
 * File containing tests for task cleanup_exports_imports_scheduled_task
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for functions for task cleanup_exports_imports_scheduled_task
 *
 * @package    tool_wp
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_wp_task_testcase extends advanced_testcase {

    public function test_cleanup_exports_imports_scheduled_task(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();
        $CFG->wpexportimportexpiry = 20 * DAYSECS;

        $dataimport = (object) [
            'createdby' => $user->id,
            'tenantid' => 1,
            'status' => \tool_wp\local\exportimport\import_manager::DETAILTYPE_SUCCESS
        ];
        $importpersistent = new \tool_wp\local\exportimport\import_persistent(0, $dataimport);
        $importpersistent->create();

        $dataexport = (object) [
            'createdby' => $user->id,
            'tenantid' => 1,
            'exporter' => 'tool_program\tool_wp\exporter\programs'
        ];
        $exportpersistent = new \tool_wp\local\exportimport\export_persistent(0, $dataexport);
        $exportpersistent->create();

        $task = new \tool_wp\task\cleanup_exports_imports_scheduled_task();
        $task->execute();
        $this->assertTrue($DB->record_exists('tool_wp_import', ['id' => $importpersistent->get('id')]));
        $this->assertTrue($DB->record_exists('tool_wp_export', ['id' => $exportpersistent->get('id')]));

        $DB->set_field('tool_wp_import', 'timecreated', strtotime('-22 day'));
        $DB->set_field('tool_wp_export', 'timecreated', strtotime('-22 day'));
        $task->execute();
        $this->assertFalse($DB->record_exists('tool_wp_import', ['id' => $importpersistent->get('id')]));
        $this->assertFalse($DB->record_exists('tool_wp_export', ['id' => $exportpersistent->get('id')]));
    }
}
