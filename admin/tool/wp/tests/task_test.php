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

namespace tool_wp;
// TODO MDL-3102 should have namespace tool_wp\task and name cleanup_exports_imports_scheduled_task_test .

use advanced_testcase;

/**
 * Tests for functions for task cleanup_exports_imports_scheduled_task
 *
 * @package    tool_wp
 * @covers     \tool_wp\task\cleanup_exports_imports_scheduled_task
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class task_test extends advanced_testcase {

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
