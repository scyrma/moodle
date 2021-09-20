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
 * File contains the unit tests for condition certification_certified class.
 *
 * @package    tool_certification
 * @category   test
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_certification\constants;
use tool_certification\tool_dynamicrule\condition\certification_certified;
use tool_dynamicrule\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for condition certification_certified class.
 *
 * @package    tool_certification
 * @group      tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_certification_condition_certification_certified_testcase extends advanced_testcase {

    /** @var tool_certification_generator */
    public $generator;
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
        global $CFG;
        if (!file_exists("{$CFG->dirroot}/{$CFG->admin}/tool/dynamicrule/")) {
            $this->markTestSkipped('Can not find tool_dynamicrule');
        }
        $this->generator = self::getDataGenerator()->get_plugin_generator('tool_certification');
        $this->tenantgenerator = self::getDataGenerator()->get_plugin_generator('tool_tenant');
        $this->programgenerator = self::getDataGenerator()->get_plugin_generator('tool_program');
        $this->drgenerator = self::getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        $this->resetAfterTest();
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
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        $tenant = $this->tenantgenerator->create_tenant();
        $this->tenantgenerator->allocate_user($user1->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user2->id, $tenant->id);
        $this->tenantgenerator->allocate_user($user3->id, $tenant->id);

        $certification = $this->generator->generate_certification(['tenantid' => $tenant->id]);

        $userdata = (object) [
            'certificationid' => $certification->get('id'),
            'userid' => $user1->id,
            'status' => constants::STATUS_OVERRIDE_DEFAULT,
        ];
        $certificationuser1 = \tool_certification\api::allocate_user($certification, $userdata);
        $this->generator->allocate_user($user2->id, $certification->get('id'));
        $this->generator->allocate_user($user3->id, $certification->get('id'));

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
     * Test data that condition provides for the outcomes
     */
    public function test_data_for_outcome() {
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
        $this->generator->allocate_user($user1->id, $certification->get('id'));
        $this->generator->allocate_user($user2->id, $certification->get('id'));
        $this->generator->allocate_user($user3->id, $certification->get('id'));
        $this->generator->allocate_user($user4->id, $certification->get('id'));

        // Create rule to send notification on certification.
        $rule = $this->drgenerator->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata = ['certificationid' => $certification->get('id'), 'certificationstatusid' => 3];
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
     * Test get_description
     */
    public function test_get_description(): void {
        $certification1 = $this->generator->generate_certification();

        $rule1 = $this->drgenerator->create_rule();
        $statusstr = get_string('certified', 'tool_certification');
        $configdata = ['certificationid' => $certification1->get('id')];
        /** @var certification_certified $condition1 */
        $condition1 = certification_certified::create($rule1->id, $configdata);

        $expectedstr = get_string('conditioncertificationstatusdescription', 'tool_certification',
            ['fullname' => $certification1->get('fullname'), 'status' => $statusstr]);
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
        $options = ['fullname' => $certification1->get('fullname'), 'status' => $statusstr];
        $options['conditiondate'] = userdate($now, get_string('strftimedatetimeshort'));
        $expected = get_string($strid, 'tool_certification', $options);
        $this->assertEquals($expected, $condition->get_description());
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
}
