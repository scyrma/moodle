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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_dynamicrule\external;

use tool_tenant\tenancy;
use core_external\external_api;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for duplicate_rule_validation external class.
 *
 * @covers     \tool_dynamicrule\external\duplicate_rule_validation
 * @package    tool_dynamicrule
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class duplicate_rule_validation_test extends \externallib_advanced_testcase {

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
        self::setAdminUser();
    }

    /**
     * Get dynamic rule generator
     *
     * @return \tool_dynamicrule_generator
     */
    protected function get_generator(): \tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Test webservice in shared to tenant duplication
     */
    public function test_execute_shared_to_tenant(): void {
        global $DB;

        // Create shared rule with condition and action applicable to shared space only.
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        $rulesharedtenant = $this->get_generator()->create_rule(['tenantid' => $sharedspaceid]);
        $configdata = ['userprofilefield' => 'city', 'city_value' => 'Barcelona', 'city_op' => 2];
        $condition = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rulesharedtenant->id,
            $configdata);
        $sharedcondition = $this->get_generator()->create_condition_sharedonly($rulesharedtenant->id);
        $outcome = $this->get_generator()->create_outcome_donothing($rulesharedtenant->id);
        $sharedoutcome = $this->get_generator()->create_outcome_sharedonly($rulesharedtenant->id);

        // Sanity check.
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_condition', ['ruleid' => $rulesharedtenant->id]));
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_outcome', ['ruleid' => $rulesharedtenant->id]));

        // Validate rule duplication.
        $result = duplicate_rule_validation::execute($rulesharedtenant->id);
        $cleanresult = external_api::clean_returnvalue(duplicate_rule_validation::execute_returns(), $result);

        // We expect to see some conflicting items in the validation output.
        $this->assertStringContainsString($sharedcondition->get_title(), $cleanresult);
        $this->assertStringContainsString($sharedoutcome->get_title(), $cleanresult);
        $this->assertStringNotContainsString($condition->get_title(), $cleanresult);
        $this->assertStringNotContainsString($outcome->get_title(), $cleanresult);
    }

    /**
     * Test webservice in shared to shared duplication
     */
    public function test_execute_shared_to_shared(): void {
        global $DB;

        // Create shared rule with condition and action applicable to shared space only.
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        $rulesharedtenant = $this->get_generator()->create_rule(['tenantid' => $sharedspaceid]);
        $configdata = ['userprofilefield' => 'city', 'city_value' => 'Barcelona', 'city_op' => 2];
        $condition = \tool_dynamicrule\tool_dynamicrule\condition\user_profile_field::create($rulesharedtenant->id,
            $configdata);
        $sharedcondition = $this->get_generator()->create_condition_sharedonly($rulesharedtenant->id);
        $outcome = $this->get_generator()->create_outcome_donothing($rulesharedtenant->id);
        $sharedoutcome = $this->get_generator()->create_outcome_sharedonly($rulesharedtenant->id);

        // Sanity check.
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_condition', ['ruleid' => $rulesharedtenant->id]));
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_outcome', ['ruleid' => $rulesharedtenant->id]));

        // Validate rule duplication.
        tenancy::set_switched_tenant_id($sharedspaceid);
        $result = duplicate_rule_validation::execute($rulesharedtenant->id);
        $cleanresult = external_api::clean_returnvalue(duplicate_rule_validation::execute_returns(), $result);

        // We do not expect to see conflicting items in the validation output.
        $this->assertStringNotContainsString($sharedcondition->get_title(), $cleanresult);
        $this->assertStringNotContainsString($sharedoutcome->get_title(), $cleanresult);
        $this->assertStringNotContainsString($condition->get_title(), $cleanresult);
        $this->assertStringNotContainsString($outcome->get_title(), $cleanresult);
    }
}
