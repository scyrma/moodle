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
 * File containing tests for rule condition class field mapping during export/import
 *
 * @package     tool_organisation
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\tool_dynamicrule\condition;

use tool_dynamicrule\condition;
use tool_dynamicrule\condition_base;
use tool_dynamicrule\rule;
use tool_dynamicrule\tool_wp\exporter\rules as exporter;
use tool_dynamicrule\tool_wp\importer\rules as importer;

/**
 * Test class
 *
 * TODO WP-3102 split/move into tests for individual classes
 *
 * @package     tool_organisation
 * @group       tool_organisation
 * @category    test
 * @covers      \tool_organisation\tool_dynamicrule\condition\user_department
 * @covers      \tool_organisation\tool_dynamicrule\condition\user_not_in_department
 * @covers      \tool_organisation\tool_dynamicrule\condition\user_position
 * @covers      \tool_organisation\tool_dynamicrule\condition\user_without_position
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class mapping_test extends \advanced_testcase {

    /**
     * Data provider to return all rule department condition classes for this plugin
     *
     * @return string[]
     */
    public function department_condition_provider(): array {
        return [
            [user_department::class],
            [user_not_in_department::class],
        ];
    }

    /**
     * Test that rule department condition instance adds field mappings during export/import
     *
     * @param string $conditionclass
     *
     * @dataProvider department_condition_provider
     */
    public function test_rule_department_condition_mapping(string $conditionclass): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $department = $this->get_plugin_generator()->create_department(['name' => 'My department']);

        // Create rule containing given condition, pointing to the department we just created.
        $rule = $this->get_rule_generator()->create_rule();
        $this->get_rule_generator()->create_condition($conditionclass, $rule->id, ['departmentid' => [$department->id]]);

        // Export our rule.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_CONTENT => 1,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
        ]);

        // Now delete the original department, and create a new one with the same name.
        $originalid = $department->id;
        $originalname = $department->name;

        (new \tool_organisation\department_manager)->delete_department($originalid);

        $newdepartment = $this->get_plugin_generator()->create_department(['name' => $originalname]);

        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, [
            importer::IMPORT_CONTENT => 1,
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        // Confirm the department mapping data was added.
        $mappingdata = (new \tool_wp\local\exportimport\import_manager($importid))
            ->get_raw_mapping_from_workplace_export_file('tool_organisation_department', $originalid);

        $this->assertIsArray($mappingdata);
        $this->assertEquals($originalid, $mappingdata['id']);

        // The imported condition field should be mapped to the new department.
        $rules = rule::get_records([], 'id');

        /** @var condition_base $condition */
        $condition = $conditionclass::instance(0, condition::get_record(['ruleid' => end($rules)->get('id')])->to_record());

        $this->assertEqualsCanonicalizing([$newdepartment->id], $condition->get_configdata()['departmentid']);
        $this->assertTrue($condition->is_configuration_valid());
    }

    /**
     * Data provider to return all rule position condition classes for this plugin
     *
     * @return string[]
     */
    public function position_condition_provider(): array {
        return [
            [user_position::class],
            [user_without_position::class],
        ];
    }

    /**
     * Test that rule position condition instance adds field mappings during export/import
     *
     * @param string $conditionclass
     *
     * @dataProvider position_condition_provider
     */
    public function test_rule_position_condition_mapping(string $conditionclass): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $position = $this->get_plugin_generator()->create_position(['name' => 'My position']);

        // Create rule containing given condition, pointing to the position we just created.
        $rule = $this->get_rule_generator()->create_rule();
        $this->get_rule_generator()->create_condition($conditionclass, $rule->id, ['positionid' => [$position->id]]);

        // Export our rule.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_CONTENT => 1,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
        ]);

        // Now delete the original position, and create a new one with the same name.
        $originalid = $position->id;
        $originalname = $position->name;

        (new \tool_organisation\position_manager)->delete_position($originalid);

        $newposition = $this->get_plugin_generator()->create_position(['name' => $originalname]);

        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, [
            importer::IMPORT_CONTENT => 1,
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        // Confirm the position mapping data was added.
        $mappingdata = (new \tool_wp\local\exportimport\import_manager($importid))
            ->get_raw_mapping_from_workplace_export_file('tool_organisation_position', $originalid);

        $this->assertIsArray($mappingdata);
        $this->assertEquals($originalid, $mappingdata['id']);

        // The imported condition field should be mapped to the new position.
        $rules = rule::get_records([], 'id');

        /** @var condition_base $condition */
        $condition = $conditionclass::instance(0, condition::get_record(['ruleid' => end($rules)->get('id')])->to_record());

        $this->assertEqualsCanonicalizing([$newposition->id], $condition->get_configdata()['positionid']);
        $this->assertTrue($condition->is_configuration_valid());
    }

    /**
     * Return plugin generator
     *
     * @return \tool_organisation_generator
     */
    protected function get_plugin_generator(): \tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }

    /**
     * Return dynamic rule generator
     *
     * @return \tool_dynamicrule_generator
     */
    protected function get_rule_generator(): \tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Return Workplace generator
     *
     * @return \tool_wp_generator
     */
    protected function get_workplace_generator(): \tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }
}
