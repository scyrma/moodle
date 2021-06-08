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
 * File contains the unit tests for condition competency class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

use tool_dynamicrule\condition;
use tool_dynamicrule\rule;
use tool_dynamicrule\tool_wp\exporter\rules as exporter;
use tool_dynamicrule\tool_wp\importer\rules as importer;

/**
 * Unit tests for condition competency class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\condition\competency
 * @covers     \tool_dynamicrule\condition_base
 * @covers     \tool_dynamicrule\condition_sql
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class condition_competency_testcase extends \advanced_testcase {

    /** @var \core_competency_generator $lpg */
    protected $lpg;

    /** @var \tool_dynamicrule_generator $dynamicrulegenerator */
    protected $dynamicrulegenerator;

    /** @var \tool_wp_generator $wpgenerator */
    protected $wpgenerator;

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->dynamicrulegenerator = $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
        $this->lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $this->wpgenerator = $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }

    /**
     * Test get_title
     */
    public function test_get_title(): void {
        $condition = competency::instance();
        $this->assertNotEmpty($condition->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category(): void {
        $condition = competency::instance();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $condition->get_category());
    }

    /**
     * Test condition matching
     *
     * @uses \tool_dynamicrule\api::get_matching_users
     */
    public function test_get_matching_users(): void {
        $this->setAdminUser();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        // Set a custom scale for our competency framework.
        $scale1 = $this->getDataGenerator()->create_scale(array("scale" => "value1, value2, value3"));
        $scaleconfiguration1 = '[{"scaleid":"'.$scale1->id.'"},' .
            '{"name":"value1","id":1,"scaledefault":1,"proficient":0},' .
            '{"name":"value2","id":2,"scaledefault":0,"proficient":0},' .
            '{"name":"value3","id":3,"scaledefault":0,"proficient":1}]';

        // Create competency framework.
        $cf = $this->lpg->create_framework(['scaleid' => $scale1->id, 'scaleconfiguration' => $scaleconfiguration1]);
        // Create competency.
        $c1 = $this->lpg->create_competency(['competencyframeworkid' => $cf->get('id')]);

        // Create a learning plan template and lik it to competency1.
        $lptemplate = $this->lpg->create_template();
        $params = ['templateid' => $lptemplate->get('id'), 'competencyid' => $c1->get('id')];
        $lpcomp1 = $this->lpg->create_template_competency($params);

        // Create Learning plan for User1.
        $planu1 = $this->lpg->create_plan(['userid' => $user1->id, 'templateid' => $lptemplate->get('id'), 'name' => 'Plan_u1']);
        // Create Learning plan for User2.
        $planu2 = $this->lpg->create_plan(['userid' => $user2->id, 'templateid' => $lptemplate->get('id'), 'name' => 'Plan_u2']);
        // Create Learning plan for User3.
        $planu3 = $this->lpg->create_plan(['userid' => $user3->id, 'templateid' => $lptemplate->get('id'), 'name' => 'Plan_u3']);

        $rule1 = $this->dynamicrulegenerator->create_rule();
        $configdata = ['competencyid' => $c1->get('id')];
        competency::create($rule1->id, $configdata);

        $this->assertEquals(0, \tool_dynamicrule\api::count_matching_users($rule1->id));

        // Grade competency as proficient for user1.
        \core_competency\api::grade_competency($user1->id, $c1->get('id'), 3);
        // Grade competency as NOT proficient for user2.
        \core_competency\api::grade_competency($user2->id, $c1->get('id'), 2);
        // Grade competency as proficient for user3.
        \core_competency\api::grade_competency($user3->id, $c1->get('id'), 3);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));
        $users = \tool_dynamicrule\api::get_matching_users($rule1->id);
        $this->assertEqualsCanonicalizing([$user1->id, $user3->id], array_column($users, 'id'));
    }

    /**
     * Test get_description
     */
    public function test_get_description() {
        // Create competency framework.
        $cf = $this->lpg->create_framework();
        // Create competency.
        $c1 = $this->lpg->create_competency(['competencyframeworkid' => $cf->get('id')]);

        $rule1 = $this->dynamicrulegenerator->create_rule();
        $configdata = ['competencyid' => $c1->get('id')];
        $condition1 = competency::create($rule1->id, $configdata);

        $this->assertEquals(get_string('conditioncompetencydescription', 'tool_dynamicrule', $c1->get('shortname')),
            $condition1->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid() {
        global $DB;

        // Create competency framework.
        $cf = $this->lpg->create_framework();
        // Create competency.
        $c1 = $this->lpg->create_competency(['competencyframeworkid' => $cf->get('id')]);

        $rule1 = $this->dynamicrulegenerator->create_rule();
        $configdata = ['competencyid' => $c1->get('id')];
        $condition1 = competency::create($rule1->id, $configdata);

        // Test valid configuration.
        $this->assertTrue($condition1->is_configuration_valid());

        // Delete competency.
        $DB->delete_records('competency', ['id' => $c1->get('id')]);
        $this->assertFalse($condition1->is_configuration_valid());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add() {
        // Create competency framework.
        $cf = $this->lpg->create_framework();
        // Create competency.
        $c1 = $this->lpg->create_competency(['competencyframeworkid' => $cf->get('id')]);

        $context = $c1->get_context();

        // Admin user.
        self::setAdminUser();
        $this->assertTrue(competency::instance()->user_can_add());

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertTrue(competency::instance()->user_can_add());

        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('moodle/competency:competencyview', CAP_PROHIBIT, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        $this->assertFalse(competency::instance()->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit() {
        // Create competency framework.
        $cf = $this->lpg->create_framework();
        // Create competency.
        $c1 = $this->lpg->create_competency(['competencyframeworkid' => $cf->get('id')]);

        $configform = ['competencyid' => $c1->get('id')];
        $context = $c1->get_context();

        // Admin user.
        self::setAdminUser();
        $this->assertTrue(competency::instance()->user_can_edit($configform));

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertTrue(competency::instance()->user_can_edit($configform));

        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('moodle/competency:competencyview', CAP_PROHIBIT, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        $this->assertFalse(competency::instance()->user_can_edit($configform));
    }

    /**
     * Test is_available.
     */
    public function test_is_available() {
        self::setAdminUser();

        // Sanity check.
        $this->assertFalse(competency::is_available());

        // Create competency framework.
        $cf = $this->lpg->create_framework();
        // Create competency.
        $c1 = $this->lpg->create_competency(['competencyframeworkid' => $cf->get('id')]);
        $this->assertTrue(competency::is_available());
    }

    /**
     * Test trigger_rule_processing.
     */
    public function test_trigger_rule_processing() {
        global $DB;
        $this->setAdminUser();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        // Set a custom scale for our competency framework.
        $scale1 = $this->getDataGenerator()->create_scale(array("scale" => "value1, value2, value3"));
        $scaleconfiguration1 = '[{"scaleid":"'.$scale1->id.'"},' .
            '{"name":"value1","id":1,"scaledefault":1,"proficient":0},' .
            '{"name":"value2","id":2,"scaledefault":0,"proficient":0},' .
            '{"name":"value3","id":3,"scaledefault":0,"proficient":1}]';

        // Create competency framework.
        $cf = $this->lpg->create_framework(['scaleid' => $scale1->id, 'scaleconfiguration' => $scaleconfiguration1]);
        // Create competency.
        $c1 = $this->lpg->create_competency(['competencyframeworkid' => $cf->get('id')]);

        // Create a learning plan template and lik it to competency1.
        $lptemplate = $this->lpg->create_template();
        $params = ['templateid' => $lptemplate->get('id'), 'competencyid' => $c1->get('id')];
        $lpcomp1 = $this->lpg->create_template_competency($params);

        // Create Learning plan for User1.
        $planu1 = $this->lpg->create_plan(['userid' => $user1->id, 'templateid' => $lptemplate->get('id'), 'name' => 'Plan_u1']);
        // Create Learning plan for User2.
        $planu2 = $this->lpg->create_plan(['userid' => $user2->id, 'templateid' => $lptemplate->get('id'), 'name' => 'Plan_u2']);
        // Create Learning plan for User3.
        $planu3 = $this->lpg->create_plan(['userid' => $user3->id, 'templateid' => $lptemplate->get('id'), 'name' => 'Plan_u3']);

        $rule1 = $this->dynamicrulegenerator->create_rule(['enabled' => 1]);
        $configdata = ['competencyid' => $c1->get('id')];
        competency::create($rule1->id, $configdata);

        $configdata = ['subject' => 'Competency achieved',
            'body' => ['text' => 'Dear {{userfullname}}, you have achieved a new competency.', 'format' => FORMAT_MOODLE]];
        \tool_dynamicrule\tool_dynamicrule\outcome\notification::create($rule1->id, $configdata);

        // Grade competency as proficient for user1.
        \core_competency\api::grade_competency($user1->id, $c1->get('id'), 3);
        // Grade competency as NOT proficient for user2.
        \core_competency\api::grade_competency($user2->id, $c1->get('id'), 2);
        // Grade competency as proficient for user3.
        \core_competency\api::grade_competency($user3->id, $c1->get('id'), 3);

        $this->assertEquals(2, \tool_dynamicrule\api::count_matching_users($rule1->id));

        // Prepare messages sink.
        $sink = $this->redirectMessages();

        // Process rule manually as if we enabled it.
        $ruleinstance = new rule(0, $rule1);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $messages = $sink->get_messages();
        $sink->close();

        // Check outcomes (note that notification ordering is undefined so we just assert the users received the messages).
        $this->assertCount(2, $messages);

        $userids = array_column($messages, 'useridto');
        $this->assertEqualsCanonicalizing([$user1->id, $user3->id], $userids);

        $subjects = array_column($messages, 'subject');
        $this->assertEquals(array_fill(0, 2, 'Competency achieved'), $subjects);

        // Check matches record presence.
        $this->assertEquals(2, $DB->count_records('tool_dynamicrule_match', ['ruleid' => $rule1->id]));
    }

    /**
     * Test field mapping during export/import
     */
    public function test_field_mapping(): void {
        global $DB;
        $this->setAdminUser();

        // Create competency framework.
        $cf = $this->lpg->create_framework();
        // Create competency.
        $c1 = $this->lpg->create_competency(['competencyframeworkid' => $cf->get('id'), 'shortname' => 'COMP1']);

        // Create rule containing condition, pointing to the course we just created.
        $rule = $this->dynamicrulegenerator->create_rule();
        $this->dynamicrulegenerator->create_condition(competency::class, $rule->id, ['competencyid' => $c1->get('id')]);

        // Export our rule.
        $exportid = $this->wpgenerator->perform_export(exporter::class, [
            exporter::EXPORT_CONTENT => 1,
            exporter::EXPORT_INSTANCES => exporter::EXPORT_INSTANCES_ALL,
        ]);

        // Now delete the original course, and create a new one with the same name.
        $originalid = $c1->get('id');
        $originalname = $c1->get('shortname');

        $DB->delete_records('competency');
        $DB->delete_records('competency_framework');

        // Create competency framework.
        $cf = $this->lpg->create_framework();
        // Create competency.
        $c1 = $this->lpg->create_competency(['competencyframeworkid' => $cf->get('id'), 'shortname' => $originalname]);

        $importid = $this->wpgenerator->perform_import_from_export_id($exportid, [
            importer::IMPORT_CONTENT => 1,
            importer::IMPORT_INSTANCES => importer::IMPORT_INSTANCES_ALL,
        ]);

        // Confirm the course mapping data was added.
        $mappingdata = (new \tool_wp\local\exportimport\import_manager($importid))
            ->get_raw_mapping_from_workplace_export_file('competency', $originalid);

        $this->assertIsArray($mappingdata);
        $this->assertEquals($originalid, $mappingdata['id']);

        // The imported condition field should be mapped to the new competency.
        $rules = rule::get_records([], 'id');

        $condition = competency::instance(0, condition::get_record(['ruleid' => end($rules)->get('id')])->to_record());

        $this->assertEquals($c1->get('id'), $condition->get_configdata()['competencyid']);
        $this->assertTrue($condition->is_configuration_valid());
    }
}
