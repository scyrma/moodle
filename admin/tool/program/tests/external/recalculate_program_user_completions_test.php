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

namespace tool_program\external;

use context_system;
use external_api;
use externallib_advanced_testcase;
use tool_program\persistent\program_set_completion;
use tool_tenant\sharedspace;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the tool_program recalculate_program_user_completions external class.
 *
 * @covers     \tool_program\external\recalculate_program_user_completions
 * @package    tool_program
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class recalculate_program_user_completions_test extends externallib_advanced_testcase {

    /**
     * Test execute method
     */
    public function test_execute(): void {
        global $DB;
        $this->resetAfterTest();
        $context = context_system::instance();
        $sharedspaceid = sharedspace::enable_shared_space();
        $generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');

        [$tenant, [$user11, $user12, $user13]] = $tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22]] = $tenantgenerator->create_tenant_and_users(2);

        $roleid = create_role('manage users role', 'manageusersrole', 'Role description');
        assign_capability('tool/tenant:manageusers', CAP_ALLOW, $roleid, $context->id);
        assign_capability('tool/program:allocateuser', CAP_ALLOW, $roleid, $context->id);
        $this->getDataGenerator()->role_assign($roleid, $user13->id);

        $this->setUser($user13);

        // Program created in a tenant is always not shared.
        $programdata = ['tenantid' => $sharedspaceid, 'shared' => 1] + (array)$generator->get_dummy_program_data();

        $program = $generator->generate_program((object)$programdata);
        $set = $generator->generate_set((object) [
            'programid' => $program->get('id'),
            'parent' => $program->get_base_set()->get('id'),
            'sortorder' => 1,
        ]);

        // Add course1 to program base set.
        $course1 = $this->getDataGenerator()->create_course();
        $generator->add_course_to_set($course1->id, $program->get_base_set()->get('id'));

        // Add course2 to program set.
        $course2 = $this->getDataGenerator()->create_course();
        $generator->add_course_to_set($course2->id, $set->get('id'));

        // Allocate user11 to the program and complete the program.
        $programuser11 = $generator->allocate_user_to_program($program->get('id'), $user11->id);
        $generator->complete_program($program, $user11->id);

        // Allocate user12 to the program.
        $programuser12 = $generator->allocate_user_to_program($program->get('id'), $user12->id);

        // Allocate user21 to the program and complete the program.
        $programuser21 = $generator->allocate_user_to_program($program->get('id'), $user21->id);
        $generator->complete_program($program, $user21->id);

        // Allocate user22 to the program.
        $programuser22 = $generator->allocate_user_to_program($program->get('id'), $user22->id);

        // Allocate user13 to the program.
        $programuser13 = $generator->allocate_user_to_program($program->get('id'), $user13->id);

        // Set custom completion date to ensure program recompletion keeps the stored dates.
        $yesterday = time() - DAYSECS;
        $DB->set_field('tool_program_set_completion', 'completeddate', $yesterday, ['userid' => $user11->id]);

        $completions = program_set_completion::get_records(['userid' => $user11->id]);
        $this->assertCount(2, $completions);
        $this->assertEquals($yesterday, $completions[0]->get('completeddate'));
        $this->assertEquals($yesterday, $completions[1]->get('completeddate'));

        $completions = program_set_completion::get_records(['userid' => $user12->id]);
        $this->assertCount(0, $completions);

        $completions = program_set_completion::get_records(['userid' => $user21->id]);
        $this->assertCount(2, $completions);

        $completions = program_set_completion::get_records(['userid' => $user22->id]);
        $this->assertCount(0, $completions);

        $completions = program_set_completion::get_records(['userid' => $user13->id]);
        $this->assertCount(0, $completions);

        // The program and the set are marked as completed. Let's add a new course inside the set so the recaulculation removes
        // the completion from both sets.
        $course3 = $this->getDataGenerator()->create_course();
        $generator->add_course_to_set($course3->id, $set->get('id'));

        $result = recalculate_program_user_completions::execute([$programuser11->get('id'), $programuser12->get('id'),
            $programuser13->get('id'), $programuser21->get('id'), $programuser22->get('id')]);
        $cleanresult = external_api::clean_returnvalue(recalculate_program_user_completions::execute_returns(), $result);
        $this->assertEquals(3, $cleanresult['successcount']);
        $this->assertEquals(2, $cleanresult['skippedcount']);

        // Both (program and set) completions have been removed. Course3 is now needed to be able to mark program as completed.
        $completions = program_set_completion::get_records(['userid' => $user11->id]);
        $this->assertCount(0, $completions);

        $completions = program_set_completion::get_records(['userid' => $user12->id]);
        $this->assertCount(0, $completions);

        $completions = program_set_completion::get_records(['userid' => $user21->id]);
        $this->assertCount(2, $completions);

        $completions = program_set_completion::get_records(['userid' => $user22->id]);
        $this->assertCount(0, $completions);

        $completions = program_set_completion::get_records(['userid' => $user13->id]);
        $this->assertCount(0, $completions);
    }
}
