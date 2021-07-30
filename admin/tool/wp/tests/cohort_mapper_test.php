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
 * File containing tests for export/import cohort mapper class
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\mapper;

use context_coursecat;
use tool_tenant\manager;
use tool_tenant\tenancy;
use tool_wp\local\exportimport\helper;

/**
 * Test class
 *
 * @package     tool_wp
 * @group       tool_wp
 * @category    test
 * @covers      \tool_wp\tool_wp\mapper\cohort
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class cohort_mapper_testcase extends \advanced_testcase {

    /**
     * Test mapper returns mapping data correctly for given cohort
     */
    public function test_get_mapping_data_for_workplace_export() {
        $this->resetAfterTest();

        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'My cohort', 'idnumber' => 'cohort101']);

        $mapper = helper::find_mapper_for_entity('cohort', helper::get_all_mappers());
        $this->assertInstanceOf(cohort::class, $mapper);

        $data = $mapper->get_mapping_data_for_workplace_export($cohort->id);
        $this->assertEquals([
            'id' => $cohort->id,
            'name' => $cohort->name,
            'idnumber' => $cohort->idnumber,
        ], $data);
    }

    /**
     * Data provider for testing matching cohorts
     *
     * @see test_locate_mapping_success
     *
     * @return array
     */
    public function locate_mapping_success_provider(): array {
        return [
            ['My cohort', 'cohort101', ['name' => 'My cohort']],
            ['My cohort', 'cohort101', ['idnumber' => 'cohort101']],
        ];
    }

    /**
     * Test the mapper class successfully locates existing cohorts
     *
     * @param string $name
     * @param string $idnumber
     * @param array $identifier
     *
     * @dataProvider locate_mapping_success_provider
     */
    public function test_locate_mapping_success(string $name, string $idnumber, array $identifier): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cohort = $this->getDataGenerator()->create_cohort(['name' => $name, 'idnumber' => $idnumber]);

        $mapping = $this->get_plugin_generator()->locate_mapping('cohort', $identifier);
        $this->assertEquals([$cohort->id, [], [], true], $mapping);
    }

    /**
     * Test the mapper class successfully locates existing cohorts where a cohort with the same name also exists in a category
     * that the user cannot access
     */
    public function test_locate_mapping_success_duplicate_name(): void {
        global $DB;

        $this->resetAfterTest();

        // Create a cohort in another category.
        $othercategory = $this->getDataGenerator()->create_category();
        $othercontext = context_coursecat::instance($othercategory->id);
        $othercohort = $this->getDataGenerator()->create_cohort(['name' => 'My cohort', 'contextid' => $othercontext->id]);

        // Create user, set them as manager for the default tenant category.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $category = $this->getDataGenerator()->create_category();
        (new manager())->update_tenant(tenancy::get_tenant_id(), (object) ['categoryid' => $category->id]);

        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        $context = context_coursecat::instance($category->id);
        $this->getDataGenerator()->role_assign($managerrole->id, $user->id, $context);

        // Create a cohort in the default tenant category.
        $cohort = $this->getDataGenerator()->create_cohort(['name' => $othercohort->name, 'contextid' => $context->id]);

        $mapping = $this->get_plugin_generator()->locate_mapping('cohort', ['name' => $cohort->name]);
        $this->assertEquals([$cohort->id, [], [], true], $mapping);
    }

    /**
     * Data provider for testing non-matching cohorts
     *
     * @see test_locate_mapping_error
     *
     * @return array
     */
    public function locate_mapping_error_provider(): array {
        return [
            ['My cohort', 'cohort101', ['name' => 'My other cohort']],
            ['My cohort', 'cohort101', ['name' => 'My other cohort', 'idnumber' => 'cohort202']],
            ['My cohort', 'cohort101', ['idnumber' => 'cohort202']],
            ['My cohort', 'cohort101', ['name' => 'My cohort'], false],
        ];
    }

    /**
     * Test mapper returns errors for non-matching cohorts
     *
     * @param string $name
     * @param string $idnumber
     * @param array $identifier
     * @param bool $adminuser
     *
     * @dataProvider locate_mapping_error_provider
     */
    public function test_locate_mapping_error(string $name, string $idnumber, array $identifier, bool $adminuser = true): void {
        $this->resetAfterTest();
        if ($adminuser) {
            $this->setAdminUser();
        }

        $this->getDataGenerator()->create_cohort(['name' => $name, 'idnumber' => $idnumber]);

        list($cohortid, $notices, $errors, $validated) = $this->get_plugin_generator()->locate_mapping('cohort', $identifier);
        $this->assertNull($cohortid);
        $this->assertEmpty($notices);
        $this->assertCount(1, $errors);
        if (!empty($identifier['name']) && !empty($identifier['idnumber'])) {
            $name = "'{$identifier['name']}' ('{$identifier['idnumber']}')";
        } else {
            $name = !empty($identifier['name']) ? "'{$identifier['name']}'" : "'{$identifier['idnumber']}'";
        }
        $this->assertEquals('Cohort ' . $name . ' was not found', reset($errors));
        $this->assertFalse($validated);
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
