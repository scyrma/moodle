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
 * File containing tests for rule class field mapping during export/import
 *
 * @package     tool_program
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_dynamicrule\api;
use tool_dynamicrule\condition;
use tool_dynamicrule\condition_base;
use tool_dynamicrule\outcome;
use tool_dynamicrule\outcome_base;
use tool_dynamicrule\rule;
use tool_dynamicrule\tool_wp\exporter\rules as exporter;
use tool_dynamicrule\tool_wp\importer\rules as importer;

/**
 * Test class
 *
 * @package     tool_program
 * @group       tool_program
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_program_export_import_rule_mapping_testcase extends advanced_testcase {

    /**
     * Determine whether given instance is within this plugin's namespace
     *
     * @param condition_base|outcome_base $instance
     * @return bool
     */
    protected static function filter_instance_by_plugin($instance): bool {
        $namespace = explode('\\', get_class($instance));

        return (strcmp($namespace[0], 'tool_program') == 0);
    }

    /**
     * Data provider to return all rule condition instances for this plugin
     *
     * @return condition_base[][]
     */
    public function condition_provider(): array {
        $instances = [];

        /** @var condition_base[] $conditions */
        $conditions = array_filter(api::get_conditions(), [$this, 'filter_instance_by_plugin']);
        foreach ($conditions as $condition) {
            $instances[$condition->get_title()] = [$condition];
        }

        return $instances;
    }

    /**
     * Test that rule condition instance adds field mappings during export/import
     *
     * @param condition_base $condition
     *
     * @dataProvider condition_provider
     */
    public function test_rule_condition_mapping(condition_base $condition): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $conditionclass = get_class($condition);

        $program = $this->get_plugin_generator()->generate_program((object) ['fullname' => 'My program']);

        // Create rule containing given condition, pointing to the program we just created.
        $rule = $this->get_rule_generator()->create_rule();
        $this->get_rule_generator()->create_condition($conditionclass, $rule->id, ['programid' => $program->get('id')]);

        // Export our rule.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_CONTENT => 1,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
        ]);

        // Now delete the original program, and create a new one with the same name.
        $originalprogramid = $program->get('id');
        $originalprogramname = $program->get('fullname');

        tool_program\api::archive_program($program);
        tool_program\api::delete_program($program);

        $newprogram = $this->get_plugin_generator()->generate_program((object) ['fullname' => $originalprogramname]);

        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, [
            importer::IMPORT_CONTENT => 1,
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        // Confirm the program mapping data was added.
        $mappingdata = (new \tool_wp\local\exportimport\import_manager($importid))
            ->get_raw_mapping_from_workplace_export_file('tool_program', $originalprogramid);

        $this->assertIsArray($mappingdata);
        $this->assertEquals($originalprogramid, $mappingdata['id']);

        // The imported condition 'programid' field should be mapped to the new program (TODO: use API method to create).
        $rules = rule::get_records([], 'id');

        /** @var condition_base $condition */
        $condition = $conditionclass::instance(0, condition::get_record(['ruleid' => end($rules)->get('id')])->to_record());

        $programid = $condition->get_configdata()['programid'];
        $newprogramid = $newprogram->get('id');
        $assertion = 'assertEquals';

        // If condition class is program_completed then we need to cast data to test based on new multi select.
        if ($conditionclass === 'tool_program\tool_dynamicrule\condition\program_completed') {
            $programid = (array) $programid;
            $newprogramid = [$newprogramid];
            $assertion .= 'Canonicalizing';
        }

        $this->$assertion($newprogramid, $programid);
        $this->assertTrue($condition->is_configuration_valid());
    }

    /**
     * Data provider to return all rule outcome instances for this plugin
     *
     * @return outcome_base[][]
     */
    public function outcome_provider(): array {
        $instances = [];

        /** @var outcome_base[] $outcomes */
        $outcomes = array_filter(api::get_outcomes(), [$this, 'filter_instance_by_plugin']);
        foreach ($outcomes as $outcome) {
            $instances[$outcome->get_title()] = [$outcome];
        }

        return $instances;
    }

    /**
     * Test that rule outcome instance adds field mappings during export/import
     *
     * @param outcome_base $outcome
     *
     * @dataProvider outcome_provider
     */
    public function test_rule_outcome_mapping(outcome_base $outcome): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $outcomeclass = get_class($outcome);

        $program = $this->get_plugin_generator()->generate_program((object) ['fullname' => 'My program']);

        // Create rule containing given outcome, pointing to the program we just created.
        $rule = $this->get_rule_generator()->create_rule();
        $this->get_rule_generator()->create_outcome($outcomeclass, $rule->id, ['programid' => $program->get('id')]);

        // Export our rule.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_CONTENT => 1,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
        ]);

        // Now delete the original program, and create a new one with the same name.
        $originalprogramid = $program->get('id');
        $originalprogramname = $program->get('fullname');

        tool_program\api::archive_program($program);
        tool_program\api::delete_program($program);

        $newprogram = $this->get_plugin_generator()->generate_program((object) ['fullname' => $originalprogramname]);

        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, [
            importer::IMPORT_CONTENT => 1,
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        // Confirm the program mapping data was added.
        $mappingdata = (new \tool_wp\local\exportimport\import_manager($importid))
            ->get_raw_mapping_from_workplace_export_file('tool_program', $originalprogramid);

        $this->assertIsArray($mappingdata);
        $this->assertEquals($originalprogramid, $mappingdata['id']);

        // The imported outcome 'programid' field should be mapped to the new program (TODO: use API method to create).
        $rules = rule::get_records([], 'id');

        /** @var outcome_base $outcome */
        $outcome = $outcomeclass::instance(0, outcome::get_record(['ruleid' => end($rules)->get('id')])->to_record());

        $this->assertEquals($newprogram->get('id'), $outcome->get_configdata()['programid']);
        $this->assertTrue($outcome->is_configuration_valid());
    }

    /**
     * Return program generator
     *
     * @return tool_program_generator
     */
    protected function get_plugin_generator(): tool_program_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_program');
    }

    /**
     * Return dynamic rule generator
     *
     * @return tool_dynamicrule_generator
     */
    protected function get_rule_generator(): tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Return Workplace generator
     *
     * @return tool_wp_generator
     */
    protected function get_workplace_generator(): tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }
}
