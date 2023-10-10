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
 * File contains the unit tests for condition program_completed class.
 *
 * @package    tool_program
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\tool_dynamicrule\condition;

use advanced_testcase;
use tool_dynamicrule_generator;
use tool_program_generator;
use tool_dynamicrule\api;
use tool_dynamicrule\rule;
use tool_dynamicrule\condition_base;
use tool_dynamicrule\tool_dynamicrule\outcome\notification;
use tool_tenant_generator;
use core_collator;

/**
 * Unit tests for condition program_completed class.
 *
 * @covers     \tool_program\tool_dynamicrule\condition\program_completed
 * @package    tool_program
 * @group      tool_program
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_completed_test extends advanced_testcase {

    /** @var tool_program_generator */
    protected $generator;

    /** @var tool_tenant_generator */
    protected $tenantgenerator;

    /** @var tool_dynamicrule_generator */
    protected $drgenerator;

    /**
     * Set up
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->drgenerator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        $this->resetAfterTest();
    }

    /**
     * Test supports_rule_types
     */
    public function test_supports_rule_types(): void {
        $condition = program_completed::instance();
        $this->assertEquals(rule::TYPE_NORMAL + rule::TYPE_SHARED, $condition->supports_rule_types());
    }

    /**
     * Test get_title
     */
    public function test_get_title(): void {
        $condition = program_completed::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $condition = program_completed::instance();
        $this->assertEquals(get_string('pluginname', 'tool_program'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $condition = program_completed::instance();
        $configform = ['programid' => -2];
        $validationerrors = $condition->validate_config_form($configform);
        $this->assertArrayHasKey('programid', $validationerrors);
    }

    /**
     * Test program completed condition matching
     */
    public function test_get_matching_users_given_program_completed(): void {
        [$tenant, [$user1, $user2, $user3]] = $this->tenantgenerator->create_tenant_and_users(3);

        $program1 = $this->generator->generate_program_with_course((object)['tenantid' => $tenant->id]);
        $programuser1 = $this->generator->allocate_user_to_program($program1->get('id'), $user1->id);
        $this->generator->allocate_users_to_program($program1->get('id'), [$user2->id, $user3->id]);

        // Complete program for this user.
        $this->generator->complete_program($program1, $user1->id);

        // Test users that completed program1.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['programid' => $program1->get('id')];
        program_completed::create($rule->id, $configdata);
        $this->assertEquals(1, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([$user1->id], array_column($users, 'id'));

        // Test users that Completed program1 that are also Suspended should be shown as Completed if filtering by Completed.
        $programuser1->set('status', 0);
        $programuser1->update();

        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['programid' => $program1->get('id')];
        program_completed::create($rule->id, $configdata);

        $this->assertEquals(1, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([$user1->id], array_column($users, 'id'));

        // Test users do not match if "on or after" date is set and they completed program before.
        $rule = $this->drgenerator->create_rule();
        $date = strtotime('+1 year');
        $configdata = [
            'programid' => $program1->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $date,
        ];
        program_completed::create($rule->id, $configdata);

        $this->assertEquals(0, api::count_matching_users($rule->id));

        // Test users match if "on or after" date is set and they completed program after.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $date = strtotime('-1 year');
        $configdata = [
            'programid' => $program1->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $date,
        ];
        program_completed::create($rule->id, $configdata);

        $this->assertEquals(1, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([$user1->id], array_column($users, 'id'));
    }

    /**
     * Test multiple program completed condition matching
     */
    public function test_get_matching_users_given_multiple_program_completed(): void {
        [$tenant, [$user1]] = $this->tenantgenerator->create_tenant_and_users(1);
        [$tenant2, [$user2]] = $this->tenantgenerator->create_tenant_and_users(1);

        $program1 = $this->generator->generate_program_with_course((object)['tenantid' => $tenant->id]);
        $program2 = $this->generator->generate_program_with_course((object)['tenantid' => $tenant->id]);
        $program3 = $this->generator->generate_program_with_course((object)['tenantid' => $tenant2->id]);
        $program4 = $this->generator->generate_program_with_course((object)['tenantid' => $tenant2->id]);
        $this->generator->allocate_user_to_program($program1->get('id'), $user1->id);
        $this->generator->allocate_user_to_program($program2->get('id'), $user1->id);
        $this->generator->allocate_user_to_program($program4->get('id'), $user2->id);

        // Complete program1 AND program2 for this user.
        $this->generator->complete_program($program1, $user1->id);
        $this->generator->complete_program($program2, $user1->id);

        // Test users that completed program1 AND program2.
        $ruleall = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdataall = ['programid' => [$program1->get('id'), $program2->get('id')],
            'criteria' => condition_base::CRITERIA_ALL];
        program_completed::create($ruleall->id, $configdataall);
        $this->assertEquals(1, api::count_matching_users($ruleall->id));
        $users = api::get_matching_users($ruleall->id);
        $this->assertEqualsCanonicalizing([$user1->id], array_column($users, 'id'));

        // Complete only program4 for user2.
        $this->generator->complete_program($program4, $user2->id);

        // Test users that completed at least one program in condition.
        $ruleany = $this->drgenerator->create_rule(['tenantid' => $tenant2->id]);
        $configdataany = ['programid' => [$program3->get('id'), $program4->get('id')],
            'criteria' => condition_base::CRITERIA_ANY];
        $conditionany = program_completed::create($ruleany->id, $configdataany);
        $this->assertEquals(1, api::count_matching_users($ruleany->id));
        $users = api::get_matching_users($ruleany->id);
        $this->assertEqualsCanonicalizing([$user2->id], array_column($users, 'id'));
    }

    /**
     * Test shared program completed condition matching
     */
    public function test_get_matching_users_shared_program(): void {
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant, [$user1, $user2, $user3]] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22, $user23]] = $this->tenantgenerator->create_tenant_and_users(3);

        $program1 = $this->generator->generate_program_with_course((object)['tenantid' => $sharedspaceid]);
        $this->generator->allocate_users_to_program($program1->get('id'), [$user1->id, $user2->id, $user3->id]);
        $this->generator->allocate_users_to_program($program1->get('id'), [$user21->id, $user22->id, $user23->id]);

        // Complete program for one user from tenant1 and one user from tenant2.
        $this->generator->complete_program($program1, $user1->id);
        $this->generator->complete_program($program1, $user21->id);

        // Test users that matched the dynamic rule condition.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['programid' => $program1->get('id')];
        program_completed::create($rule->id, $configdata);
        $this->assertEquals(1, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([$user1->id], array_column($users, 'id'));
    }

    /**
     * Test shared program completed condition matching in shared rule
     */
    public function test_get_matching_users_shared_program_shared_rule(): void {
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant, [$user1, $user2, $user3]] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22, $user23]] = $this->tenantgenerator->create_tenant_and_users(3);

        $program1 = $this->generator->generate_program_with_course((object)['tenantid' => $sharedspaceid]);
        $this->generator->allocate_users_to_program($program1->get('id'), [$user1->id, $user2->id, $user3->id]);
        $this->generator->allocate_users_to_program($program1->get('id'), [$user21->id, $user22->id, $user23->id]);

        // Complete program for one user from tenant1 and one user from tenant2.
        $this->generator->complete_program($program1, $user1->id);
        $this->generator->complete_program($program1, $user21->id);

        // Test users that matched the dynamic rule condition.
        $rule = $this->drgenerator->create_rule(['tenantid' => $sharedspaceid]);
        $configdata = ['programid' => $program1->get('id')];
        program_completed::create($rule->id, $configdata);
        $this->assertEquals(2, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user21->id], array_column($users, 'id'));
    }

    /**
     * Test data that condition provides for the outcomes
     */
    public function test_data_for_outcome(): void {
        global $CFG;

        $user1 = self::getDataGenerator()->create_user(['firstname' => 'John', 'lastname' => 'Doe']);
        $user2 = self::getDataGenerator()->create_user(['firstname' => 'Anna', 'lastname' => 'Wilson']);
        $user3 = self::getDataGenerator()->create_user();

        $tenant = $this->tenantgenerator->create_tenant();
        $this->tenantgenerator->allocate_user($user1->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user3->id, $tenant->id);

        $program1 = $this->generator->generate_program((object)['fullname' => 'MYPROG', 'tenantid' => $tenant->id]);
        $baseset = $program1->get_base_set();

        $course1 = $this->generator->generate_course_with_completion_self();
        $course2 = $this->generator->generate_course_with_completion_self();
        $this->generator->add_course_to_set($course1->id, $baseset->get('id'), 1);
        $set1 = $this->generator->generate_set((object) [
                'programid' => $program1->get('id'),
                'parent' => $baseset->get('id'),
                'sortorder' => 2,
                'completioncriteria' => \tool_program\persistent\program_set::COMPLETION_AT_LEAST,
                'completionatleast' => 1
        ]);
        $this->generator->add_course_to_set($course2->id, $baseset->get('id'), 3);
        $course3 = $this->generator->generate_course_with_completion_self();
        $course4 = $this->generator->generate_course_with_completion_self();
        $this->generator->add_course_to_set($course3->id, $set1->get('id'), 1);
        $this->generator->add_course_to_set($course4->id, $set1->get('id'), 2);

        /*
         * Initial structure:
         * 1 Base set
         *      1 Course1
         *      2 Set1
         *          1 Course3
         *          2 Course4
         *      3 Course2
         */

        $this->generator->allocate_user_to_program($program1->get('id'), $user1->id);
        $this->generator->allocate_user_to_program($program1->get('id'), $user2->id);

        // Create rule to send notification on program completion.
        $rule = $this->drgenerator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata = ['programid' => $program1->get('id'), 'criteria' => 'any', 'conditiondateenabled' => true,
            'conditiondate' => time() - 1];
        program_completed::create($rule->id, $configdata);

        $configdata = ['subject' => 'You matched!',
            'body' => ['text' => "Congratulations {{userfullname}}," .
                " you completed the program {{programname}} ({{programid}})\non {{programcompletiondate}}\n".
                "with courses:\n{{programcompletedcourses}}", 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule->id, $configdata);

        // Start collecting notification messages.
        $sink = $this->redirectMessages();

        $this->generator->complete_courses([$course1->id, $course4->id, $course2->id], $user1->id);
        // Change global configuration 'courselistshortnames' to test if coursenames show correctly.

        $CFG->courselistshortnames = true;
        $this->generator->complete_courses([$course1->id, $course3->id, $course4->id, $course2->id], $user2->id);

        // Analyze notification messages.
        $savedmessages = $sink->get_messages();
        $sink->close();

        // Only filter messages sent by dynamic rules plugin and sort them to avoid random test failures.
        $drmessages = array_filter($savedmessages, function($el) {
            return $el->component === 'tool_dynamicrule';
        });
        core_collator::asort_objects_by_property($drmessages, 'fullmessage');
        $drmessages = array_values($drmessages);

        $this->assertCount(2, $drmessages);

        $curdate = userdate(time(), get_string('strftimedatefullshort'));
        $this->assertEquals('You matched!', $drmessages[0]->subject);
        $this->assertEquals('Congratulations Anna Wilson,'.
            ' you completed the program MYPROG (' . $program1->get('id') .
            ")\non $curdate\nwith courses:\n\n	* tc_1 Test course 1\n	* tc_3 Test course 3\n	* tc_4 Test course 4\n" .
            "	* tc_2 Test course 2\n\n", $drmessages[0]->fullmessage);
        $this->assertEquals('You matched!', $drmessages[1]->subject);
        $this->assertEquals('Congratulations John Doe,'.
            ' you completed the program MYPROG (' . $program1->get('id') .
            ")\non $curdate\nwith courses:\n\n	* Test course 1\n	* Test course 4\n" .
            "	* Test course 2\n\n", $drmessages[1]->fullmessage);
    }

    /**
     * Test use of placeholder
     */
    public function test_use_of_placeholder(): void {
        $tenant = $this->tenantgenerator->create_tenant();

        $program1 = $this->generator->generate_program_with_course((object)['tenantid' => $tenant->id]);
        $program2 = $this->generator->generate_program_with_course((object)['tenantid' => $tenant->id]);

        // Create a rule with just one program to check that placeholders are sent.
        $rule = $this->drgenerator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata = [
            'programid' => $program1->get('id'),
            'criteria' => 'any',
            'conditiondateenabled' => true,
            'conditiondate' => time() - 1
        ];
        $programcondition = program_completed::create($rule->id, $configdata);
        $outcome = notification::instance();

        $this->assertEquals($programcondition->get_placeholders(), $programcondition->get_available_data_for_outcome($outcome));

        // Create a rule with multiple programs to check that placeholders are not sent.
        $rule1 = $this->drgenerator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata1 = [
            'programid' => [$program1->get('id'), $program2->get('id')],
            'criteria' => 'any',
            'conditiondateenabled' => true,
            'conditiondate' => time() - 1
        ];
        $multipleprogramcondition = program_completed::create($rule1->id, $configdata1);
        $outcome1 = notification::instance();
        $this->assertEmpty($multipleprogramcondition->get_available_data_for_outcome($outcome1));
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {
        $program1 = $this->generator->generate_program();
        $program2 = $this->generator->generate_program();

        // One program completed.
        $rule = $this->drgenerator->create_rule();
        $configdata = ['programid' => $program1->get('id')];
        /** @var program_completed $condition */
        $condition = program_completed::create($rule->id, $configdata);
        $options = $program1->get('fullname');
        $expectedstr = get_string('conditionprogramcompleteddescription', 'tool_program',
            $options);
        $this->assertEquals($expectedstr, $condition->get_description());

        // One program completed with description when date is enabled.
        $rule = $this->drgenerator->create_rule();
        $now = time();
        $configdata = [
            'programid' => $program1->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $now,
        ];
        /** @var program_completed $condition */
        $condition = program_completed::create($rule->id, $configdata);
        $options = ['programname' => $program1->get('fullname'), 'conditiondate' =>
            userdate($now, get_string('strftimedatefullshort'))];
        $expected = get_string('conditionprogramcompleteddescriptionwithdate', 'tool_program', $options);
        $this->assertEquals($expected, $condition->get_description());

        // All Programs completed.
        $ruleall = $this->drgenerator->create_rule();
        $configdataall = ['programid' => [$program1->get('id'), $program2->get('id')],
            'criteria' => condition_base::CRITERIA_ALL];
        /** @var program_completed $conditionall */
        $conditionall = program_completed::create($ruleall->id, $configdataall);
        $optionsall = ['programname' => "{$program1->get('fullname')}', '{$program2->get('fullname')}"];
        $expectedstr = get_string('conditionprogramcompletedalldescription', 'tool_program',
            $optionsall);
        $this->assertEquals($expectedstr, $conditionall->get_description());

        // All program completed with description when date is enabled.
        $ruleallwithdate = $this->drgenerator->create_rule();
        $now = time();
        $configdataallwithdate = [
            'programid' => [$program1->get('id'), $program2->get('id')],
            'conditiondateenabled' => true,
            'conditiondate' => $now,
            'criteria' => condition_base::CRITERIA_ALL
        ];
        /** @var program_completed $conditionallwithdate */
        $conditionallwithdate = program_completed::create($ruleallwithdate->id, $configdataallwithdate);
        $optionsallwithdate = ['programname' => "{$program1->get('fullname')}', '{$program2->get('fullname')}", 'conditiondate' =>
            userdate($now, get_string('strftimedatefullshort'))];
        $expected = get_string('conditionprogramcompletedalldescriptionwithdate', 'tool_program', $optionsallwithdate);
        $this->assertEquals($expected, $conditionallwithdate->get_description());

        // Any Programs completed.
        $ruleany = $this->drgenerator->create_rule();
        $configdataany = ['programid' => [$program1->get('id'), $program2->get('id')],
            'criteria' => condition_base::CRITERIA_ANY];
        /** @var program_completed $conditionany */
        $conditionany = program_completed::create($ruleany->id, $configdataany);
        $optionsany = ['programname' => "{$program1->get('fullname')}', '{$program2->get('fullname')}"];
        $expectedstr = get_string('conditionprogramcompletedanydescription', 'tool_program',
            $optionsany);
        $this->assertEquals($expectedstr, $conditionany->get_description());

        // Any program completed with description when date is enabled.
        $ruleanywithdate = $this->drgenerator->create_rule();
        $now = time();
        $configdataanywithdate = [
            'programid' => [$program1->get('id'), $program2->get('id')],
            'conditiondateenabled' => true,
            'conditiondate' => $now,
            'criteria' => condition_base::CRITERIA_ANY
        ];
        /** @var program_completed $conditionanywithdate */
        $conditionanywithdate = program_completed::create($ruleanywithdate->id, $configdataanywithdate);
        $optionsanywithdate = ['programname' => "{$program1->get('fullname')}', '{$program2->get('fullname')}", 'conditiondate' =>
            userdate($now, get_string('strftimedatefullshort'))];
        $expected = get_string('conditionprogramcompletedanydescriptionwithdate', 'tool_program', $optionsanywithdate);
        $this->assertEquals($expected, $conditionanywithdate->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $program1 = $this->generator->generate_program((object)['tenantid' => $tenant->id]);
        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);

        // Empty configuration.
        $condition1 = program_completed::create($rule1->id, []);
        $this->assertFalse($condition1->is_configuration_valid());

        // Users in program1.
        $configdata = ['programid' => $program1->get('id')];
        $condition1 = program_completed::create($rule1->id, $configdata);

        $this->assertTrue($condition1->is_configuration_valid());

        // Test program is archived.
        \tool_program\api::archive_program($program1);
        $this->assertFalse($condition1->is_configuration_valid());

        // Restore program.
        \tool_program\api::restore_program($program1);
        $this->assertTrue($condition1->is_configuration_valid());

        // Delete program.
        \tool_program\api::archive_program($program1);
        \tool_program\api::delete_program($program1);
        $this->assertFalse($condition1->is_configuration_valid());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add(): void {
        $tenant = $this->tenantgenerator->create_tenant();

        $program1 = $this->generator->generate_program((object)['tenantid' => $tenant->id]);

        $user = self::getDataGenerator()->create_user(); // User in default tenant.
        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        self::setUser($user);

        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['programid' => $program1->get('id')];
        program_completed::create($rule1->id, $configdata);
        $this->assertFalse(program_completed::instance()->user_can_add());

        $this->generator->assign_allocateuser_capability($user->id, $program1->get_context());
        $this->assertTrue(program_completed::instance()->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $program1 = $this->generator->generate_program((object)['tenantid' => $tenant->id]);

        $user = self::getDataGenerator()->create_user(); // User in default tenant.
        self::setUser($user);

        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['programid' => $program1->get('id')];
        program_completed::create($rule1->id, $configdata);
        $this->assertFalse(program_completed::instance()->user_can_edit($configdata));

        $this->generator->assign_allocateuser_capability($user->id, $program1->get_context());
        $this->assertFalse(program_completed::instance()->user_can_edit($configdata));

        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        $this->assertTrue(program_completed::instance()->user_can_edit($configdata));
    }

    /**
     * Test program completed condition checks completeddate instead of timecreated
     */
    public function test_get_matching_users_given_completion_dates(): void {
        global $DB;

        [$tenant, [$user1, $user2]] = $this->tenantgenerator->create_tenant_and_users(2);
        $program1 = $this->generator->generate_program_with_course((object)['tenantid' => $tenant->id]);
        $this->generator->allocate_user_to_program($program1->get('id'), $user1->id);
        $this->generator->allocate_user_to_program($program1->get('id'), $user2->id);

        // Complete program for this user.
        $now = time();
        $yesterday = $now - DAYSECS;
        $this->generator->complete_program($program1, $user1->id, $yesterday);
        // Change completeddate to be different than timecreated date.
        $DB->set_field('tool_program_set_completion', 'completeddate', $yesterday);

        // Assert that completeddate is different that timecreated in database.
        $record = $DB->get_record('tool_program_set_completion', ['userid' => $user1->id]);
        $this->assertEquals($yesterday, $record->completeddate);
        $this->assertNotEquals($yesterday, $record->timecreated);

        // Test that there are no users with a program completed on or after $now date.
        $rule = $this->drgenerator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata = [
            'programid' => $program1->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $now,
        ];
        program_completed::create($rule->id, $configdata);

        $this->assertEquals(0, api::count_matching_users($rule->id));

        // Test that there is one user with a program completed on or after $yesterday date.
        $rule = $this->drgenerator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata = [
            'programid' => $program1->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $yesterday,
        ];
        program_completed::create($rule->id, $configdata);

        $this->assertEquals(1, api::count_matching_users($rule->id));
    }
}
