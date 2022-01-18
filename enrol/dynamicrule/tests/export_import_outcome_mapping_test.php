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
 * File containing tests for rule outcome class field mapping during export/import
 *
 * @package     enrol_dynamicrule
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace enrol_dynamicrule\tool_dynamicrule\outcome;

defined('MOODLE_INTERNAL') || die();

use tool_dynamicrule\outcome;
use tool_dynamicrule\outcome_base;
use tool_dynamicrule\rule;
use tool_dynamicrule\tool_wp\exporter\rules as exporter;
use tool_dynamicrule\tool_wp\importer\rules as importer;

/**
 * Test class
 *
 * @package     enrol_dynamicrule
 * @group       enrol_dynamicrule
 * @category    test
 * @covers      \enrol_dynamicrule\tool_dynamicrule\outcome\course_enrol
 * @covers      \enrol_dynamicrule\tool_dynamicrule\outcome\course_unenrol
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_import_outcome_mapping_testcase extends \advanced_testcase {

    /**
     * Data provider to return all rule outcome classes for this plugin
     *
     * @return array
     */
    public function outcome_provider(): array {
        global $DB;

        return [
            [course_enrol::class, 'coursetoenrol', ['role' => $DB->get_field('role', 'id', ['shortname' => 'student'])]],
            [course_unenrol::class, 'coursetounenrol']
        ];
    }

    /**
     * Test that rule outcome instance adds field mappings during export/import
     *
     * @param string $outcomeclass
     * @param string $fieldname
     * @param array $extraconfig
     *
     * @dataProvider outcome_provider
     */
    public function test_rule_outcome_mapping(string $outcomeclass, string $fieldname, array $extraconfig = []): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['shortname' => 'course101']);

        // Create rule containing given outcome, pointing to the course we just created.
        $rule = $this->get_rule_generator()->create_rule();
        $this->get_rule_generator()->create_outcome($outcomeclass, $rule->id,
            array_merge([$fieldname => $course->id], $extraconfig));

        // Export our rule.
        $exportid = $this->get_workplace_generator()->perform_export(exporter::class, [
            exporter::EXPORT_CONTENT => 1,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
        ]);

        // Now delete the original course, and create a new one with the same name.
        $originalcourseid = $course->id;
        $originalshortname = $course->shortname;

        delete_course($course->id, false);

        $newcourse = $this->getDataGenerator()->create_course(['shortname' => $originalshortname]);

        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, [
            importer::IMPORT_CONTENT => 1,
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        // Confirm the course mapping data was added.
        $mappingdata = (new \tool_wp\local\exportimport\import_manager($importid))
            ->get_raw_mapping_from_workplace_export_file('course', $originalcourseid);

        $this->assertIsArray($mappingdata);
        $this->assertEquals($originalcourseid, $mappingdata['id']);

        // The imported outcome field should be mapped to the new course.
        $rules = rule::get_records([], 'id');

        /** @var outcome_base $outcome */
        $outcome = $outcomeclass::instance(0, outcome::get_record(['ruleid' => end($rules)->get('id')])->to_record());

        $this->assertEquals($newcourse->id, $outcome->get_configdata()[$fieldname]);
        $this->assertTrue($outcome->is_configuration_valid());
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
