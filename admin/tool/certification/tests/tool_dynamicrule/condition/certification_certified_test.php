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

namespace tool_certification\tool_dynamicrule\condition;

use advanced_testcase;
use tool_certification_generator;
use tool_dynamicrule_generator;
use tool_program_generator;
use tool_tenant_generator;
use ReflectionClass;
use core_collator;
use tool_certification\constants;
use tool_dynamicrule\api;
use tool_dynamicrule\condition_base;
use tool_dynamicrule\rule;
use tool_dynamicrule\tool_dynamicrule\outcome\notification;

/**
 * Unit tests for condition certification_certified class.
 *
 * @package    tool_certification
 * @group      tool_certification
 * @covers     \tool_certification\tool_dynamicrule\condition\certification_certified
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_certified_test extends advanced_testcase {

    /** @var tool_certification_generator */
    protected $generator;
    /** @var tool_tenant_generator */
    protected $tenantgenerator;
    /** @var tool_program_generator */
    protected $programgenerator;
    /** @var tool_dynamicrule_generator */
    protected $drgenerator;

    /**
     * Set up
     */
    public function setUp(): void {
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->drgenerator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        $this->resetAfterTest();
    }

    /**
     * tearDown.
     */
    public function tearDown(): void {
        if (extension_loaded('uopz')) {
            // Revert function overrides.
            uopz_unset_return('time');
            uopz_unset_return('strtotime');
        }
    }

    /**
     * Test supports_rule_types
     */
    public function test_supports_rule_types(): void {
        $condition = certification_certified::instance();
        $this->assertEquals(rule::TYPE_NORMAL + rule::TYPE_SHARED, $condition->supports_rule_types());
    }

    /**
     * Test get_title
     */
    public function test_get_title(): void {
        $condition = certification_certified::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $condition = certification_certified::instance();
        $this->assertEquals(get_string('pluginname', 'tool_certification'), $condition->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form(): void {
        $condition = certification_certified::instance();
        $configform = ['certificationid' => -2];
        $validationerrors = $condition->validate_config_form($configform);
        $this->assertArrayHasKey('certificationid', $validationerrors);
    }

    /**
     * Test get_config_attributes
     */
    public function test_get_config_attributes(): void {
        // The get_config_attributes method is protected. Use Reflection to call the method.
        $reflector = new ReflectionClass(certification_certified::class);
        $method = $reflector->getMethod('get_config_attributes');
        $method->setAccessible(true);

        $outcome = certification_certified::instance();
        $this->assertEmpty($method->invokeArgs($outcome, []));
    }

    /**
     * Test status certified condition matching
     */
    public function test_get_matching_users_given_certified_status(): void {
        [$tenant, [$user1, $user2, $user3]] = $this->tenantgenerator->create_tenant_and_users(3);
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user1->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        $certificationuser1 = \tool_certification\api::allocate_user($certification, $userdata);
        $this->generator->allocate_users_to_certification($certification->get('id'), [$user2->id, $user3->id]);

        // Certify user1.
        \tool_certification\api::set_user_as_certified($user1->id, $certification->get('id'));

        // Test users that are Certified with $certification.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id'), 'certificationstatusid' => 3];
        certification_certified::create($rule->id, $configdata);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule->id);
        $this->assertEquals([$user1->id], array_column($users, 'id'));

        // Test users that Certified $certification that are also Suspended should be shown as Certified if filtering by Certified.
        $certificationuser1->set('status', 0);
        $certificationuser1->update();

        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        certification_certified::create($rule->id, $configdata);

        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule->id);
        $this->assertEquals([$user1->id], array_column($users, 'id'));

        // Test users do not match if "on or after" date is set and they certified certification before.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $date = strtotime('+1 year');
        $configdata = [
            'certificationid' => $certification->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $date,
        ];
        certification_certified::create($rule->id, $configdata);
        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule->id));
        // Test users match if "on or after" date is set and they certified certification after.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $date = strtotime('-1 year');
        $configdata = [
            'certificationid' => $certification->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $date,
        ];
        certification_certified::create($rule->id, $configdata);
        $this->assertEquals(1, \tool_dynamicrule\api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEquals([$user1->id], array_column($users, 'id'));
    }

    /**
     * Test mutiple certified completed condition matching with all certification
     */
    public function test_get_matching_users_given_multiple_certified(): void {
        $user1 = self::getDataGenerator()->create_user();

        $tenant = $this->tenantgenerator->create_tenant();
        $this->tenantgenerator->allocate_user($user1->id, $tenant->id);

        $certification1 = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        $certification2 = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        $this->generator->allocate_user($user1->id, $certification1->get('id'));
        $this->generator->allocate_user($user1->id, $certification2->get('id'));

        // Certified certification1 AND certification2 for this user.
        \tool_certification\api::set_user_as_certified($user1->id, $certification1->get('id'));
        \tool_certification\api::set_user_as_certified($user1->id, $certification2->get('id'));

        // Test users that has certified in certification1 AND certification2.
        $ruleall = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdataall = ['certificationid' => [$certification1->get('id'), $certification2->get('id')],
            'criteria' => condition_base::CRITERIA_ALL];
        certification_certified::create($ruleall->id, $configdataall);
        $this->assertEquals(1, api::count_matching_users($ruleall->id));
        $users = api::get_matching_users($ruleall->id);
        $this->assertEqualsCanonicalizing([$user1->id], array_column($users, 'id'));
    }

    /**
     * Test mutiple certified completed condition matching with at least one certification
     */
    public function test_get_matching_users_given_least_one_certified(): void {
        $user = self::getDataGenerator()->create_user();
        $tenant = $this->tenantgenerator->create_tenant();
        $this->tenantgenerator->allocate_user($user->id, $tenant->id);

        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        $this->generator->allocate_user($user->id, $certification->get('id'));

        // Certified only one certification for user.
        \tool_certification\api::set_user_as_certified($user->id, $certification->get('id'));

        // Test users that has certified in at least one certification.
        $ruleone = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdataany = ['certificationid' => [$certification->get('id')],
            'criteria' => condition_base::CRITERIA_ANY];
        certification_certified::create($ruleone->id, $configdataany);
        $this->assertEquals(1, api::count_matching_users($ruleone->id));
        $users = api::get_matching_users($ruleone->id);
        $this->assertEqualsCanonicalizing([$user->id], array_column($users, 'id'));
    }

    /**
     * Test data that condition provides for the outcomes
     */
    public function test_data_for_outcome(): void {
        global $CFG;

        $user1 = self::getDataGenerator()->create_user(['firstname' => 'John', 'lastname' => 'Doe']);
        $user2 = self::getDataGenerator()->create_user(['firstname' => 'Anna', 'lastname' => 'Wilson']);
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user(['firstname' => 'Jack', 'lastname' => 'Sparrow']);

        $tenant = $this->tenantgenerator->create_tenant();
        $this->tenantgenerator->allocate_user($user1->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user3->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user4->id, $tenant->id);

        $program = \tool_program\api::create_program((object)['fullname' => 'MYPROG', 'program_tags' => [],
            'tenantid' => $tenant->id]);
        $baseset = $program->get_base_set();
        $course1 = $this->programgenerator->generate_course_with_completion_self();
        $course2 = $this->programgenerator->generate_course_with_completion_self();
        $this->programgenerator->add_course_to_set($course1->id, $baseset->get('id'), 1);
        $set1 = $this->programgenerator->generate_set((object) [
            'programid' => $program->get('id'),
            'parent' => $baseset->get('id'),
            'sortorder' => 2,
            'completioncriteria' => \tool_program\persistent\program_set::COMPLETION_AT_LEAST,
            'completionatleast' => 1,
        ]);
        $this->programgenerator->add_course_to_set($course2->id, $baseset->get('id'), 3);
        $course3 = $this->programgenerator->generate_course_with_completion_self();
        $course4 = $this->programgenerator->generate_course_with_completion_self();
        $this->programgenerator->add_course_to_set($course3->id, $set1->get('id'), 1);
        $this->programgenerator->add_course_to_set($course4->id, $set1->get('id'), 2);

        /*
         * Initial structure:
         * 1 Base set
         *      1 Course1
         *      2 Set1
         *          1 Course3
         *          2 Course4
         *      3 Course2
         */

        $expirydate = strtotime('03-03-2028');
        $certification = $this->generator->generate_certification([
            'fullname' => 'MYCERT',
            'program' => $program->get('id'),
            'expirydatetype' => constants::DATE_ABSOLUTE,
            'expirydateabsolute' => $expirydate,
            'requirerecertification' => 0,
            'tenantid' => $tenant->id,
        ]);
        $this->generator->allocate_users_to_certification($certification->get('id'),
            [$user1->id, $user2->id, $user3->id, $user4->id]);

        // Create rule to send notification on certification.
        $rule = $this->drgenerator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id'), 'criteria' => 'any', 'certificationstatusid' => 3];
        certification_certified::create($rule->id, $configdata);

        $configdata = ['subject' => 'You matched!',
            'body' => ['text' => "Congratulations {{userfullname}}," .
                " you completed {{certificationname}} ({{certificationid}})\non {{certificationdate}}\n".
                "by completing the program {{programname}} ({{programid}})\non {{programcompletiondate}}\n".
                "with courses:\n{{programcompletedcourses}}\nExpires: {{certificationexpirydate}}", 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule->id, $configdata);

        // Start collecting notification messages.
        $sink = $this->redirectMessages();

        $this->programgenerator->complete_courses([$course1->id, $course4->id, $course2->id], $user1->id);
        // Change global configuration 'courselistshortnames' to test if coursenames show correctly.
        $CFG->courselistshortnames = true;
        $this->programgenerator->complete_courses([$course1->id, $course3->id, $course4->id, $course2->id], $user2->id);

        // Analyze notification messages.
        $savedmessages = $sink->get_messages();
        $sink->close();

        // Only filter messages sent by dynamic rules plugin and sort them to avoid random test failures.
        $drmessages = array_filter($savedmessages, function($el) {
            return $el->component === 'tool_dynamicrule';
        });
        core_collator::asort_objects_by_property($drmessages, 'fullmessage');
        $drmessages = array_values($drmessages);

        $curdate = userdate(time(), get_string('strftimedatefullshort'));
        $this->assertEquals(2, count($drmessages));
        $this->assertEquals('You matched!', $drmessages[0]->subject);
        $this->assertEquals('Congratulations Anna Wilson,'.
            ' you completed MYCERT (' . $certification->get('id') . ")\non $curdate\nby completing the program MYPROG (".
            $program->get('id').")\non $curdate\nwith courses:\n\n	* tc_1 Test course 1\n	* tc_3 Test course 3\n	"
            . "* tc_4 Test course 4\n	* tc_2 Test course 2\n\n Expires: "
            . userdate($expirydate, get_string('strftimedatefullshort')),
            $drmessages[0]->fullmessage);
        $this->assertEquals('You matched!', $drmessages[1]->subject);
        $this->assertEquals('Congratulations John Doe,'.
            ' you completed MYCERT (' . $certification->get('id') . ")\non $curdate\nby completing the program MYPROG (".
            $program->get('id').")\non $curdate\nwith courses:\n\n	* Test course 1\n	* Test course 4\n	* Test course 2\n"
            . "\n Expires: " . userdate($expirydate, get_string('strftimedatefullshort')),
            $drmessages[1]->fullmessage);

        // Test user gets certified manually and does not complete any courses.
        // Start collecting notification messages.
        $sink = $this->redirectMessages();

        \tool_certification\api::set_user_as_certified($user4->id, $certification->get('id'));

        // Analyze notification messages.
        $savedmessages = $sink->get_messages();
        $sink->close();

        // Only filter messages sent by dynamic rules plugin and sort them to avoid random test failures.
        $drmessages = array_filter($savedmessages, function($el) {
            return $el->component === 'tool_dynamicrule';
        });
        core_collator::asort_objects_by_property($drmessages, 'fullmessage');
        $drmessages = array_values($drmessages);

        $curdate = userdate(time(), get_string('strftimedatefullshort'));
        $this->assertEquals(1, count($drmessages));
        $this->assertEquals('You matched!', $drmessages[0]->subject);
        $this->assertEquals('Congratulations Jack Sparrow,'.
            ' you completed MYCERT (' . $certification->get('id') . ")\non $curdate\nby completing the program MYPROG (".
            $program->get('id').")\non Not available\nwith courses:\nNo results\nExpires: "
            . userdate($expirydate, get_string('strftimedatefullshort')),
            $drmessages[0]->fullmessage);
    }

    /**
     * Test use of placeholder
     */
    public function test_use_of_placeholder(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        // Create a rule with just one certification to check that placeholders are sent.
        $rule = $this->drgenerator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata = [
            'certificationid' => $certification->get('id'),
            'criteria' => 'any',
            'certificationstatusid' => constants::STATUS_CERTIFIED
        ];
        $certificationcondition = certification_certified::create($rule->id, $configdata);
        $outcome = notification::instance();

        $this->assertEquals($certificationcondition->get_placeholders(),
            $certificationcondition->get_available_data_for_outcome($outcome));

        // Create a rule with multiple certifications to check that placeholders are not sent.
        $certification1 = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        $rule1 = $this->drgenerator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata1 = [
            'certificationid' => [$certification->get('id'), $certification1->get('id')],
            'criteria' => 'any',
            'certificationstatusid' => constants::STATUS_CERTIFIED
        ];
        $multiplecertificationcondition = certification_certified::create($rule1->id, $configdata1);
        $outcome1 = notification::instance();
        $this->assertEmpty($multiplecertificationcondition->get_available_data_for_outcome($outcome1));
    }

    /**
     * Test get_description
     */
    public function test_get_description(): void {
        $certification1 = $this->generator->generate_certification(['fullname' => 'Certification desc 1']);
        $certification2 = $this->generator->generate_certification(['fullname' => 'Certification desc 2']);

        $rule1 = $this->drgenerator->create_rule();
        $configdata = ['certificationid' => $certification1->get('id')];
        /** @var certification_certified $condition1 */
        $condition1 = certification_certified::create($rule1->id, $configdata);

        $postfix = '<br>' . get_string('conditioncertificationcertifieddescriptionstatusonly', 'tool_certification');
        $expectedstr = get_string('conditioncertificationcertifieddescription', 'tool_certification',
            ['fullname' => $certification1->get('fullname')]) . $postfix;
        $this->assertEquals($expectedstr, $condition1->get_description());

        // Description when date is enabled.
        $rule = $this->drgenerator->create_rule();
        $now = time();
        $configdata = [
            'certificationid' => $certification1->get('id'),
            'conditiondateenabled' => true,
            'conditiondate' => $now,
        ];
        /** @var certification_certified $condition */
        $condition = certification_certified::create($rule->id, $configdata);
        $strid = 'conditioncertificationcertifieddescriptionwithdate';
        $options = ['fullname' => $certification1->get('fullname')];
        $options['conditiondate'] = userdate($now, get_string('strftimedatefullshort'));
        $expected = get_string($strid, 'tool_certification', $options) . $postfix;
        $this->assertEquals($expected, $condition->get_description());

        // Test condition with multiple certifications and date enabled.
        $rule2 = $this->drgenerator->create_rule();
        $configdataany = [
            'certificationid' => [$certification1->get('id'), $certification2->get('id')],
            'criteria' => certification_certified::CRITERIA_ANY,
            'conditiondateenabled' => true,
            'conditiondate' => $now,
        ];

        /** @var certification_certified $conditionany */
        $conditionany = certification_certified::create($rule2->id, $configdataany);
        $stridany = 'conditioncertificationcertifieddescriptionanywithdate';
        $optionsany = ['fullname' => "{$certification1->get('fullname')}', '{$certification2->get('fullname')}"];
        $optionsany['conditiondate'] = userdate($now, get_string('strftimedatefullshort'));
        $expectedany = get_string($stridany, 'tool_certification', $optionsany) . $postfix;
        $this->assertEquals($expectedany, $conditionany->get_description());

        // Test condition with multiple certifications and date disabled.
        $rule3 = $this->drgenerator->create_rule();
        $configdataall = [
            'certificationid' => [$certification1->get('id'), $certification2->get('id')],
            'criteria' => certification_certified::CRITERIA_ALL,
        ];

        /** @var certification_certified $conditionall */
        $conditionall = certification_certified::create($rule3->id, $configdataall);
        $stridany = 'conditioncertificationcertifieddescriptionall';
        $optionsall = ['fullname' => "{$certification1->get('fullname')}', '{$certification2->get('fullname')}"];
        $expectedall = get_string($stridany, 'tool_certification', $optionsall) . $postfix;
        $this->assertEquals($expectedall, $conditionall->get_description());

    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);

        // Empty configuration.
        $condition = certification_certified::create($rule->id, []);
        $this->assertFalse($condition->is_configuration_valid());

        // Users in certification1.
        $configdata = ['certificationid' => $certification->get('id')];
        $condition = certification_certified::create($rule->id, $configdata);

        $this->assertTrue($condition->is_configuration_valid());

        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        $condition = certification_certified::create($rule->id, $configdata);

        // Test certification is archived.
        \tool_certification\api::archive_certification($certification->get('id'));
        $this->assertFalse($condition->is_configuration_valid());

        // Test certification is restored.
        \tool_certification\api::restore_certification($certification->get('id'));
        $this->assertTrue($condition->is_configuration_valid());

        // Archive the tenant and delete the tenant.
        $manager = new \tool_tenant\manager();
        $manager->archive_tenant($tenant->id);
        $manager->delete_tenant($tenant->id);
        $this->assertFalse($condition->is_configuration_valid());

        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        // Delete certification.
        \tool_certification\api::archive_certification($certification->get('id'));
        $certification = new \tool_certification\certification($certification->get('id'));
        \tool_certification\api::delete_certification($certification);
        $this->assertFalse($condition->is_configuration_valid());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        $user = self::getDataGenerator()->create_user(); // User in default tenant.
        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        self::setUser($user);

        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        certification_certified::create($rule1->id, $configdata);

        $this->assertFalse(certification_certified::instance()->user_can_add());

        $this->generator->assign_allocateuser_capability($user->id, $certification->get_context());
        $this->assertTrue(certification_certified::instance()->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit(): void {
        $tenant = $this->tenantgenerator->create_tenant();
        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        $user = self::getDataGenerator()->create_user(); // User in default tenant.
        self::setUser($user);

        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        certification_certified::create($rule1->id, $configdata);
        $this->assertFalse(certification_certified::instance()->user_can_edit($configdata));

        $this->generator->assign_allocateuser_capability($user->id, $certification->get_context());
        $this->assertFalse(certification_certified::instance()->user_can_edit($configdata));

        $this->tenantgenerator->allocate_user($user->id, $tenant->id);
        $this->assertTrue(certification_certified::instance()->user_can_edit($configdata));
    }

    /**
     * Test certification_certified condition matching on a shared certification
     */
    public function test_get_matching_users_shared_certification(): void {
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant, [$user11, $user12, $user13]] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22, $user23]] = $this->tenantgenerator->create_tenant_and_users(3);

        $program1 = $this->programgenerator->generate_program_with_course((object)['tenantid' => $sharedspaceid]);

        $certification = $this->generator->generate_certification([
            'tenantid' => $sharedspaceid, 'program' => $program1->get('id')]);

        $this->generator->allocate_users_to_certification($certification->get('id'),
            array_column([$user11, $user12, $user13], 'id'));
        $this->generator->allocate_users_to_certification($certification->get('id'),
            array_column([$user21, $user22, $user23], 'id'));

        // Complete program for one user from tenant1 and one user from tenant2.
        $this->programgenerator->complete_program($program1, $user11->id);
        $this->programgenerator->complete_program($program1, $user21->id);

        // Test users that matched the dynamic rule condition.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id')];
        certification_certified::create($rule->id, $configdata);
        $this->assertEquals(1, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([$user11->id], array_column($users, 'id'));
    }

    /**
     * Test shared certification certified condition matching in a shared rule
     */
    public function test_get_matching_users_shared_certification_shared_rule(): void {
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        [$tenant, [$user11, $user12, $user13]] = $this->tenantgenerator->create_tenant_and_users(3);
        [$tenant2, [$user21, $user22, $user23]] = $this->tenantgenerator->create_tenant_and_users(3);

        $program1 = $this->programgenerator->generate_program_with_course((object)['tenantid' => $sharedspaceid]);

        $certification = $this->generator->generate_certification([
            'tenantid' => $sharedspaceid, 'program' => $program1->get('id')]);

        $this->generator->allocate_users_to_certification($certification->get('id'),
            array_column([$user11, $user12, $user13], 'id'));
        $this->generator->allocate_users_to_certification($certification->get('id'),
            array_column([$user21, $user22, $user23], 'id'));

        // Complete program for one user from tenant1 and one user from tenant2.
        $this->programgenerator->complete_program($program1, $user11->id);
        $this->programgenerator->complete_program($program1, $user21->id);

        // Test users that matched the dynamic rule condition.
        $rule = $this->drgenerator->create_rule(['tenantid' => $sharedspaceid]);
        $configdata = ['certificationid' => $certification->get('id')];
        certification_certified::create($rule->id, $configdata);
        $this->assertEquals(2, api::count_matching_users($rule->id));
        $users = api::get_matching_users($rule->id);
        $this->assertEqualsCanonicalizing([$user11->id, $user21->id], array_column($users, 'id'));
    }

    /**
     * Test complete dynamic rule that sends notification on certification completion
     */
    public function test_completion_of_certification(): void {
        global $DB;
        $this->setAdminUser();
        [$tenant, [$user11]] = $this->tenantgenerator->create_tenant_and_users(1);

        $p1 = $this->programgenerator->generate_program_with_course((object)['tenantid' => $tenant->id]);
        $c1 = $this->generator->generate_certification(
            ['tenantid' => $tenant->id, 'program' => $p1->get('id'), 'fullname' => 'Cert1']);

        $this->generator->allocate_users_to_certification($c1->get('id'), array_column([$user11], 'id'));

        // Test users that matched the dynamic rule condition.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $configdata = ['certificationid' => $c1->get('id'), 'criteria' => 'any'];
        certification_certified::create($rule->id, $configdata);
        $configdata = [
            'subject' => 'TEST: {{certificationname}} completed',
            'body' => ['text' => 'Test body', 'format' => FORMAT_MOODLE],
        ];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();

        // Mark user as certified, a notification should be sent.
        \tool_certification\api::set_user_as_certified($user11->id, $c1->get('id'));

        $messages = $sink->get_messages();
        $subjects = array_map(function($m) {
            return $m->subject;
        }, $messages);
        $this->assertContains('TEST: Cert1 completed', $subjects);
        $sink->close();

        // Check matches record presence.
        $this->assertEquals(1, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule->id]));
    }

    /**
     * Test that completion of one certification does not trigger rule set on completion of another
     */
    public function test_completion_of_another_certification(): void {
        global $DB;
        $this->setAdminUser();
        [$tenant, [$user11]] = $this->tenantgenerator->create_tenant_and_users(1);

        $p1 = $this->programgenerator->generate_program_with_course((object)['tenantid' => $tenant->id]);
        $c1 = $this->generator->generate_certification(['tenantid' => $tenant->id, 'program' => $p1->get('id'),
            'fullname' => 'Cert1']);
        $p2 = $this->programgenerator->generate_program_with_course((object)['tenantid' => $tenant->id]);
        $c2 = $this->generator->generate_certification(['tenantid' => $tenant->id, 'program' => $p2->get('id'),
            'fullname' => 'Cert2']);

        $this->generator->allocate_users_to_certification($c1->get('id'), array_column([$user11], 'id'));
        $this->generator->allocate_users_to_certification($c2->get('id'), array_column([$user11], 'id'));

        // Test users that matched the dynamic rule condition.
        $rule = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $configdata = ['certificationid' => $c1->get('id')];
        certification_certified::create($rule->id, $configdata);
        $certificate = self::getDataGenerator()->get_plugin_generator('tool_certificate')
            ->create_template((object)['name' => 'Test template']);
        $configdata = ['certificate' => $certificate->get_id()];
        \tool_dynamicrule\tool_dynamicrule\outcome\certificate::create($rule->id, $configdata);

        // Prepare messages sink.
        $sink = $this->redirectMessages();

        // Mark user as certified.
        \tool_certification\api::set_user_as_certified($user11->id, $c1->get('id'));

        // Two messages sent - that certification is completed and that certificate is issued.
        $messages = $sink->get_messages();
        $this->assertEquals(2, count($messages));
        $subjects = [$messages[0]->subject, $messages[1]->subject];
        $this->assertEqualsCanonicalizing(
            ['Your certificate is available!', "Congratulations - 'Cert1' certification!"], $subjects);
        $sink->close();

        // There is a record in the certificate issue table.
        $this->assertEqualsCanonicalizing([$user11->id], array_column($DB->get_records('tool_certificate_issues'), 'userid'));

        // Complete another certification.

        // Prepare messages sink.
        $sink = $this->redirectMessages();

        // Mark user as certified.
        \tool_certification\api::set_user_as_certified($user11->id, $c2->get('id'));

        // There is one message that certification is completed.
        $messages = $sink->get_messages();
        $this->assertEquals(1, count($messages));
        $this->assertEquals("Congratulations - 'Cert2' certification!", $messages[0]->subject);
        $sink->close();

        // There is still only one issue in the certificate issues table.
        $this->assertEqualsCanonicalizing([$user11->id], array_column($DB->get_records('tool_certificate_issues'), 'userid'));
    }

    /**
     * Simple test for 'withrecert' config that does not require uopz (only testing the SQL)
     */
    public function test_recertification_simple(): void {
        $this->setAdminUser();
        [$tenant, [$user1]] = $this->tenantgenerator->create_tenant_and_users(1);

        $p1 = $this->programgenerator->generate_program_with_course((object)['tenantid' => $tenant->id]);
        $c1 = $this->generator->generate_certification(['tenantid' => $tenant->id, 'program' => $p1->get('id')], true);
        $this->generator->allocate_users_to_certification($c1->get('id'), [$user1->id]);

        // Create two rules, one for "status change" and one for "execute on recertification".
        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        $condition1 = certification_certified::create($rule1->id, ['certificationid' => $c1->get('id'), 'withrecert' => 1]);
        $this->drgenerator->create_outcome_donothing($rule1->id);

        $expectedstr = get_string('conditioncertificationcertifieddescription', 'tool_certification',
                ['fullname' => $c1->get('fullname')]) . '<br>' .
            get_string('conditioncertificationcertifieddescriptiononrecert', 'tool_certification');
        $this->assertEquals($expectedstr, $condition1->get_description());

        // User does not match the rules yet.
        $this->drgenerator->assert_user_did_not_match_rule($this, $user1->id, $rule1->id);

        // Mark user as certified.
        \tool_certification\api::set_user_as_certified($user1->id, $c1->get('id'));

        // User matches the rule now.
        $this->drgenerator->assert_user_matched_rule($this, $user1->id, $rule1->id);
    }

    /**
     * Test how rule triggers on recertification with and without 'withrecert' config
     */
    public function test_recertification(): void {

        // User completes the program.
        if (!extension_loaded('uopz')) {
            // Leave here if uopz is not loaded, as remaining test scenario depends on it.
            $this->markTestIncomplete('This test requires uopz php extension.');
        }

        $this->setAdminUser();
        [$tenant, [$user1]] = $this->tenantgenerator->create_tenant_and_users(1);

        $p1 = $this->programgenerator->generate_program_with_course((object)['tenantid' => $tenant->id]);
        $c1 = $this->generator->generate_certification([
            'tenantid' => $tenant->id,
            'program' => $p1->get('id'),
            'expirydatetype' => constants::DATE_AFTER_COMPLETION,
            'expirydaterelative' => '30 days',
            'recertexpirydatetype' => constants::RECERT_EXPIRY_DATE_AFTR_PREV_COMPL,
            'recertexpirydaterelative' => '30 days',
            'recertstartdaterelative' => '15 days',
        ], true);
        $this->generator->allocate_users_to_certification($c1->get('id'), [$user1->id]);

        // Create two rules, one for "status change" and one for "execute on recertification".
        $rule1 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        certification_certified::create($rule1->id, ['certificationid' => $c1->get('id')]);
        $this->drgenerator->create_outcome_donothing($rule1->id);

        $rule2 = $this->drgenerator->create_rule(['tenantid' => $tenant->id, 'enabled' => 1]);
        certification_certified::create($rule2->id, ['certificationid' => $c1->get('id'), 'withrecert' => 1]);
        $this->drgenerator->create_outcome_donothing($rule2->id);

        // User does not match either of the rules yet.
        $this->drgenerator->assert_user_did_not_match_rule($this, $user1->id, $rule1->id);
        $this->drgenerator->assert_user_did_not_match_rule($this, $user1->id, $rule2->id);

        // Mark user as certified.
        \tool_certification\api::set_user_as_certified($user1->id, $c1->get('id'));

        // User matches both rules now.
        $this->drgenerator->assert_user_matched_rule($this, $user1->id, $rule1->id);
        $this->drgenerator->assert_user_matched_rule($this, $user1->id, $rule2->id);

        // Time travel 20 days ahead. The recertification will be open and the second rule will unmatch.
        uopz_set_return('time', time() + 20 * DAYSECS);
        (new \tool_certification\task\recertification())->execute();
        (new \tool_dynamicrule\task\process_rules())->execute();
        $this->drgenerator->assert_user_matched_rule($this, $user1->id, $rule1->id);
        $this->drgenerator->assert_user_did_not_match_rule($this, $user1->id, $rule2->id, 1);

        // Complete the program again, user now matches both rules.
        \tool_certification\api::set_user_as_certified($user1->id, $c1->get('id'));
        $this->drgenerator->assert_user_matched_rule($this, $user1->id, $rule1->id);
        $this->drgenerator->assert_user_matched_rule($this, $user1->id, $rule2->id, 2);

        // Time travel 40 days ahead. The certification will now expire and both rules will unmatch.
        uopz_set_return('time', time() + 40 * DAYSECS);
        (new \tool_certification\task\recertification())->execute();
        (new \tool_dynamicrule\task\process_rules())->execute();
        $this->drgenerator->assert_user_did_not_match_rule($this, $user1->id, $rule1->id, 1);
        $this->drgenerator->assert_user_did_not_match_rule($this, $user1->id, $rule2->id, 2);

        // Complete the program again, user now matches both rules.
        \tool_certification\api::set_user_as_certified($user1->id, $c1->get('id'));
        $this->drgenerator->assert_user_matched_rule($this, $user1->id, $rule1->id, 2);
        $this->drgenerator->assert_user_matched_rule($this, $user1->id, $rule2->id, 3);
    }
}
