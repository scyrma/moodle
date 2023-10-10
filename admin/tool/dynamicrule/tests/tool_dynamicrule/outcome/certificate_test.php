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

namespace tool_dynamicrule\tool_dynamicrule\outcome;

use tool_certification\api as certification_manager;
use tool_certificate\certificate as certificate_manager;
use tool_certification\constants as certification_constants;
use tool_certification\tool_dynamicrule\condition\certification_certified;
use tool_dynamicrule\outcome;
use tool_dynamicrule\rule;
use tool_dynamicrule\tool_dynamicrule\condition\course_completed;
use tool_dynamicrule\tool_wp\exporter\rules as rules_exporter;
use tool_dynamicrule\tool_wp\importer\rules as rules_importer;
use tool_program\tool_dynamicrule\condition\program_completed;
use tool_wp\local\exportimport\import_manager;

/**
 * Unit tests for outcome\certificate  class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\outcome\certificate
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certificate_test extends \advanced_testcase {

    /** @var tool_certificate_generator */
    protected $certgenerator;

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->certgenerator = self::getDataGenerator()->get_plugin_generator('tool_certificate');
    }

    /**
     * Get dynamic rule generator
     *
     * @return tool_dynamicrule_generator
     */
    protected function get_generator(): \tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Get Workplace generator
     *
     * @return tool_wp_generator
     */
    protected function get_workplace_generator(): \tool_wp_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_wp');
    }

    /**
     * Test supports_rule_types
     */
    public function test_supports_rule_types(): void {
        $outcome = certificate::instance();
        $this->assertEquals(rule::TYPE_NORMAL + rule::TYPE_SHARED, $outcome->supports_rule_types());
    }

    /**
     * Test get_title
     */
    public function test_get_title() {
        $outcome = certificate::instance();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $outcome = certificate::instance();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $outcome->get_category());
    }

    /**
     * Test apply_to_user
     */
    public function test_apply_to_user() {
        global $DB;

        $rule0 = $this->get_generator()->create_rule(['enabled' => 1]);
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        $certificate = $this->certgenerator->create_template((object)['name' => 'Test template']);

        $configdata = ['certificate' => $certificate->get_id()];
        $outcome = certificate::create($rule0->id, $configdata);

        // Create two users in default tenant.
        $userids = [
            $this->getDataGenerator()->create_user()->id,
            $this->getDataGenerator()->create_user()->id,
        ];

        // Process rule manually as if we enabled it using default tenant.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Expect three affected users (admin user and those two we created).
        $userids[] = get_admin()->id;
        $this->assertEqualsCanonicalizing($userids, array_column($DB->get_records('tool_certificate_issues'), 'userid'));
    }

    /**
     * Test apply_to_user using non-current tenant
     */
    public function test_apply_to_user_in_other_tenant() {
        global $DB;

        $tenant = $this->get_tenant_generator()->create_tenant();
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        $certificate = $this->certgenerator->create_template((object)['name' => 'Test template']);

        $configdata = ['certificate' => $certificate->get_id()];
        $outcome = certificate::create($rule0->id, $configdata);

        // Create two users in tenant.
        $userids = [
            $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id])->id,
            $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id])->id,
        ];

        // Process rule manually as if we enabled it using default tenant.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Expect two tenant users to be affected.
        $this->assertEqualsCanonicalizing($userids, array_column($DB->get_records('tool_certificate_issues'), 'userid'));
    }

    /**
     * Test apply_to_user and setup_for_applying using shared tenant
     */
    public function test_apply_to_user_in_shared_tenant() {
        global $DB;

        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1, 'tenantid' => $sharedspaceid]);
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        $certificate = $this->certgenerator->create_template((object)['name' => 'Test template']);
        $configdata = ['certificate' => $certificate->get_id()];
        $outcome = certificate::create($rule0->id, $configdata);

        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        [$tenant, [$user1, $user2]] = $tenantgenerator->create_tenant_and_users(2);
        [$tenant2, [$user21, $user22]] = $tenantgenerator->create_tenant_and_users(2);

        // Process rule manually as if we enabled it using default tenant.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        // Expect all tenant users to be affected.
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id, $user21->id, $user22->id, get_admin()->id],
            array_column($DB->get_records('tool_certificate_issues'), 'userid'));
    }

    /**
     * Test apply_to_user and setup_for_applying using program completed condition.
     */
    public function test_apply_to_user_with_program_completed_condition(): void {
        global $DB, $CFG;
        require_once("{$CFG->libdir}/completionlib.php");

        // Create program+course (with self-completion enabled) and user.
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        [$tenant, [$user1]] = $tenantgenerator->create_tenant_and_users(1);
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('tool_program');
        $program1 = $programgenerator->generate_program_with_course((object) ['tenantid' => $tenant->id]);
        $programgenerator->allocate_user_to_program($program1->get('id'), $user1->id);

        // Create rule with program completed condition and certificate outcome.
        $rule1 = $this->get_generator()->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata = ['programid' => $program1->get('id'), 'criteria' => 'any', 'conditiondateenabled' => true,
            'conditiondate' => time() - 1];
        program_completed::create($rule1->id, $configdata);
        $certificate = $this->certgenerator->create_template((object)['name' => 'Test template']);
        $configdata = ['certificate' => $certificate->get_id()];
        certificate::create($rule1->id, $configdata);

        // Complete the program.
        $programgenerator->complete_program($program1, $user1->id);

        // Check user has been issued a certificate with program information in it.
        $issue = $DB->get_record('tool_certificate_issues', ['userid' => $user1->id]);
        $issuedata = @json_decode($issue->data, true);
        $this->assertEquals($program1->get('id'), $issuedata['programid']);
        $this->assertEquals($program1->get('fullname'), $issuedata['programname']);
    }

    /**
     * Test apply_to_user and setup_for_applying using course completed condition.
     */
    public function test_apply_to_user_with_course_completed_condition(): void {
        global $DB, $CFG;
        require_once("{$CFG->libdir}/completionlib.php");

        // Create course (with self-completion enabled) and user in default tenant.
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('tool_program');
        $course = $programgenerator->generate_course_with_completion_self();
        $user1 = $this->getDataGenerator()->create_and_enrol($course);

        // Create rule with course completed condition and certificate outcome in default tenant.
        $rule1 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['courseid' => $course->id];
        course_completed::create($rule1->id, $configdata);
        $certificate = $this->certgenerator->create_template((object)['name' => 'Test template']);
        $configdata = ['certificate' => $certificate->get_id()];
        certificate::create($rule1->id, $configdata);

        // Complete course.
        $programgenerator->complete_courses([$course->id], $user1->id);

        // Check user has been issued a certificate with course information in it.
        $issue = $DB->get_record('tool_certificate_issues', ['userid' => $user1->id]);
        $issuedata = @json_decode($issue->data, true);
        $this->assertEquals($course->id, $issuedata['courseid']);
        $this->assertEquals($course->shortname, $issuedata['courseshortname']);
        $this->assertEquals($course->fullname, $issuedata['coursefullname']);
    }

    /**
     * Test apply_to_user with expiry date absolute using certification certified condition.
     */
    public function test_apply_to_user_with_certification_condition_and_expiry_date(): void {
        global $DB;

        // Generate tenant and user.
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        [$tenant, [$user1]] = $tenantgenerator->create_tenant_and_users(1);
        // Generate program and certification.
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('tool_program');
        $program = $programgenerator->generate_program((object) ['tenantid' => $tenant->id]);
        $certificationgenerator = $this->getDataGenerator()->get_plugin_generator('tool_certification');
        $certification = $certificationgenerator->generate_certification([
            'tenantid' => $tenant->id,
            'fullname' => 'Certification Test',
            'program' => $program->get('id'),
            'expirydatetype' => certification_constants::DATE_ABSOLUTE,
            'expirydate' => strtotime('05-05-2028')
        ]);
        $certificationgenerator->allocate_users_to_certification($certification->get('id'), [$user1->id]);

        // Create rule with certification condition and certificate outcome.
        $rule = $this->get_generator()->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        certification_certified::create($rule->id, ['certificationid' => $certification->get('id')]);
        $expirydate = strtotime('04-04-2028');
        $certificate = $this->certgenerator->create_template((object)['name' => 'Test template']);
        // Add absolute expiry date to the outcome configuration.
        $configdata = [
            'certificate' => $certificate->get_id(),
            'expirydatetype' => certificate_manager::DATE_EXPIRATION_ABSOLUTE,
            'expirydateabsolute' => $expirydate
        ];
        certificate::create($rule->id, $configdata);

        // Certify user1.
        certification_manager::set_user_as_certified($user1->id, $certification->get('id'));

        // Check user has been issued a certificate with certificate information in it.
        $issue = $DB->get_record('tool_certificate_issues', ['userid' => $user1->id]);
        $issuedata = @json_decode($issue->data, true);
        $this->assertEquals($certification->get('fullname'), $issuedata['certificationname']);
        // Check certificate issue has certificate outcome setting exiry date (NOT certification condition expiry date).
        $this->assertEquals($expirydate, $issue->expires);
    }

    /**
     * Test apply_to_user with expiry date relative using certification certified condition.
     */
    public function test_apply_to_user_with_certification_condition_and_expiry_date_relative(): void {
        global $DB;

        // Generate tenant and user.
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        [$tenant, [$user1]] = $tenantgenerator->create_tenant_and_users(1);
        // Generate program and certification.
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('tool_program');
        $program = $programgenerator->generate_program((object) ['tenantid' => $tenant->id]);
        $certificationgenerator = $this->getDataGenerator()->get_plugin_generator('tool_certification');
        $certification = $certificationgenerator->generate_certification([
            'tenantid' => $tenant->id,
            'fullname' => 'Certification Test',
            'program' => $program->get('id'),
            'expirydatetype' => certification_constants::DATE_ABSOLUTE,
            'expirydate' => strtotime('05-05-2028')
        ]);
        $certificationgenerator->allocate_users_to_certification($certification->get('id'), [$user1->id]);

        // Create rule with certification condition and certificate outcome.
        $rule = $this->get_generator()->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        certification_certified::create($rule->id, ['certificationid' => $certification->get('id')]);
        $certificate = $this->certgenerator->create_template((object)['name' => 'Test template']);
        // Add relative expiry date to the outcome configuration.
        $configdata = [
            'certificate' => $certificate->get_id(),
            'expirydatetype' => certificate_manager::DATE_EXPIRATION_AFTER,
            'expirydaterelative' => DAYSECS
        ];
        certificate::create($rule->id, $configdata);

        // Certify user1.
        $timecertified = time();
        certification_manager::set_user_as_certified($user1->id, $certification->get('id'));

        // Check user has been issued a certificate with certificate information in it.
        $issue = $DB->get_record('tool_certificate_issues', ['userid' => $user1->id]);
        $issuedata = @json_decode($issue->data, true);
        $this->assertEquals($certification->get('fullname'), $issuedata['certificationname']);
        // Check certificate issue has certificate outcome setting exiry date (NOT certification condition expiry date).
        // The certificate expiration date is calculated as current timestamp plus offset. It may be slightly different
        // from the time when the user was actually certified.
        $this->assertGreaterThanOrEqual($timecertified + DAYSECS, $issue->expires);
        $this->assertLessThanOrEqual(time() + DAYSECS, $issue->expires);
    }

    /**
     * Test apply_to_user with expiry date never using certification certified condition with expiry date.
     */
    public function test_apply_to_user_with_certification_condition_without_expiry_date(): void {
        global $DB;

        // Generate tenant and user.
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        [$tenant, [$user1]] = $tenantgenerator->create_tenant_and_users(1);
        // Generate program and certification.
        $programgenerator = $this->getDataGenerator()->get_plugin_generator('tool_program');
        $program = $programgenerator->generate_program((object) ['tenantid' => $tenant->id]);
        $certificationgenerator = $this->getDataGenerator()->get_plugin_generator('tool_certification');
        $certification = $certificationgenerator->generate_certification([
            'tenantid' => $tenant->id,
            'fullname' => 'Certification Test',
            'program' => $program->get('id'),
            'expirydatetype' => certification_constants::DATE_ABSOLUTE,
            'expirydate' => strtotime('05-05-2028')
        ]);
        $certificationgenerator->allocate_users_to_certification($certification->get('id'), [$user1->id]);

        // Create rule with certification condition and certificate outcome.
        $rule = $this->get_generator()->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        certification_certified::create($rule->id, ['certificationid' => $certification->get('id')]);
        $certificate = $this->certgenerator->create_template((object)['name' => 'Test template']);
        $configdata = [
            'certificate' => $certificate->get_id(),
            'expirydatetype' => certificate_manager::DATE_EXPIRATION_NEVER,
        ];
        certificate::create($rule->id, $configdata);

        // Certify user1.
        certification_manager::set_user_as_certified($user1->id, $certification->get('id'));

        // Check user has been issued a certificate with certificate information in it.
        $issue = $DB->get_record('tool_certificate_issues', ['userid' => $user1->id]);
        $issuedata = @json_decode($issue->data, true);
        $this->assertEquals($certification->get('fullname'), $issuedata['certificationname']);
        // Check certificate issue has certification condition exiry date.
        $this->assertEquals($certification->get('expirydateabsolute'), $issue->expires);
    }

    /**
     * Test get_description.
     */
    public function test_get_description() {

        $rule0 = $this->get_generator()->create_rule();

        $name = 'Test certificate 1';
        $certificate = $this->certgenerator->create_template((object)['name' => $name]);

        $configdata = ['certificate' => $certificate->get_id()];
        $outcome = certificate::create($rule0->id, $configdata);

        $str = get_string('outcomecertificatedescription', 'tool_dynamicrule', $name);
        $this->assertEquals($str, $outcome->get_description());
    }

    /**
     * Test is_configuration_valid
     */
    public function test_is_configuration_valid() {
        $rule0 = $this->get_generator()->create_rule();
        $certificate = $this->certgenerator->create_template((object)['name' => 'Test template']);
        $configdata = ['certificate' => $certificate->get_id()];
        $outcome = certificate::create($rule0->id, $configdata);

        self::setAdminUser();
        $this->assertTrue($outcome->is_configuration_valid());

        // Delete certificate.
        $certificate->delete();
        $this->assertFalse($outcome->is_configuration_valid());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add() {
        $rule0 = $this->get_generator()->create_rule();
        $certificate = $this->certgenerator->create_template((object)['name' => 'Test template']);
        $configdata = ['certificate' => $certificate->get_id()];
        certificate::create($rule0->id, $configdata);

        // Admin user.
        self::setAdminUser();
        $this->assertTrue(certificate::instance()->user_can_add());

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse(certificate::instance()->user_can_add());

        // Grant priveleges to user.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        $context = \context_system::instance();
        assign_capability('tool/certificate:issue', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        $this->assertTrue(certificate::instance()->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit() {
        $rule0 = $this->get_generator()->create_rule();
        $certificate = $this->certgenerator->create_template((object)['name' => 'Test template']);
        $configdata = ['certificate' => $certificate->get_id()];
        certificate::create($rule0->id, $configdata);

        // Admin user.
        self::setAdminUser();
        $this->assertTrue(certificate::instance()->user_can_edit($configdata));

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertFalse(certificate::instance()->user_can_edit($configdata));

        // Grant priveleges to user.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('tool/certificate:issue', CAP_ALLOW, $roleid, $certificate->get_context()->id);
        role_assign($roleid, $user->id, $certificate->get_context());
        $this->assertTrue(certificate::instance()->user_can_edit($configdata));
    }

    /**
     * Test test_user_can_edit by tenant.
     */
    public function test_user_can_edit_tenant() {
        $tenant = $this->get_tenant_generator()->create_tenant();
        $tenantadmin = $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id, 'tenantadmin' => true]);

        $rule0 = $this->get_generator()->create_rule(['tenantid' => $tenant->id]);
        $certificate = $this->certgenerator->create_template((object)['name' => 'Test template']);
        $configdata = ['certificate' => $certificate->get_id()];
        certificate::create($rule0->id, $configdata);

        // Sanity check.
        self::setAdminUser();
        $this->assertTrue(certificate::instance()->user_can_edit($configdata));

        // Tenant admin can also access system context certificate.
        self::setUser($tenantadmin);
        $this->assertTrue(certificate::instance()->user_can_edit($configdata));
    }

    /**
     * Test that the certificate rule outcome class adds field mapping during export/import
     */
    public function test_certificate_rule_outcome_mapping(): void {
        $this->setAdminUser();

        $certificate = $this->certgenerator->create_template(['name' => 'My certificate']);

        $rule = $this->get_generator()->create_rule();
        $this->get_generator()->create_outcome(certificate::class, $rule->id,
            ['certificate' => $certificate->get_id()]);

        // Export our rule.
        $exportid = $this->get_workplace_generator()->perform_export(rules_exporter::class, [
            rules_exporter::EXPORT_CONTENT => 1,
            rules_exporter::EXPORT_INSTANCES => rules_exporter::EXPORT_INSTANCES_ALL,
        ]);

        // Now delete the original certificate, and create a new one with the same name.
        $originalcertificateid = $certificate->get_id();
        $originalcertifcatename = $certificate->get_name();
        $certificate->delete();

        $newcertificate = $this->certgenerator->create_template(['name' => $originalcertifcatename]);

        $importid = $this->get_workplace_generator()->perform_import_from_export_id($exportid, [
            rules_importer::IMPORT_CONTENT => 1,
            rules_importer::IMPORT_INSTANCES => rules_importer::IMPORT_INSTANCES_ALL,
        ]);

        // Confirm the certificate mapping data was added.
        $mappingdata = (new import_manager($importid))
            ->get_raw_mapping_from_workplace_export_file('tool_certificate_templates', $originalcertificateid);

        $this->assertIsArray($mappingdata);
        $this->assertEquals($originalcertificateid, $mappingdata['id']);

        $rules = rule::get_records([], 'id');
        $outcome = certificate::instance(0, outcome::get_record(['ruleid' => end($rules)->get('id')])->to_record());

        $this->assertEquals($newcertificate->get_id(), $outcome->get_certificateid());
    }

    /**
     * Return tenant generator
     *
     * @return \tool_tenant_generator
     */
    private function get_tenant_generator(): \tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {
        $category = $this->getDataGenerator()->create_category();
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $catcertificate = $this->certgenerator->create_template(
            (object)['name' => 'Test', 'categoryid' => $category->id]);
        $subcatcertificate = $this->certgenerator->create_template(
            (object)['name' => 'Test', 'categoryid' => $subcategory->id]);

        $syscategory = $this->getDataGenerator()->create_category();
        $syscertificate = $this->certgenerator->create_template(
            (object)['name' => 'Test', 'categoryid' => $syscategory->id]);

        $tenant0category = $this->getDataGenerator()->create_category();
        $tenant0subcategory = $this->getDataGenerator()->create_category(['parent' => $tenant0category->id]);
        $tenant0catcertificate = $this->certgenerator->create_template(
            (object)['name' => 'Test', 'categoryid' => $tenant0category->id]);
        $tenant0subcatcertificate = $this->certgenerator->create_template(
            (object)['name' => 'Test', 'categoryid' => $tenant0subcategory->id]);

        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenant0 = $tenantgenerator->create_tenant(['categoryid' => $tenant0category->id]);

        $tenant1category = $this->getDataGenerator()->create_category();
        $tenant1subcategory = $this->getDataGenerator()->create_category(['parent' => $tenant1category->id]);
        $tenant1catcertificate = $this->certgenerator->create_template(
            (object)['name' => 'Test', 'categoryid' => $tenant1category->id]);
        $tenant1subcatcertificate = $this->certgenerator->create_template(
            (object)['name' => 'Test', 'categoryid' => $tenant1subcategory->id]);
        $tenant1 = $tenantgenerator->create_tenant(['categoryid' => $tenant1category->id]);

        // Site admin in default tenant should see tenant cohorts.
        self::setAdminUser();
        $rule = $this->get_generator()->create_rule(['tenantid' => \tool_tenant\tenancy::get_tenant_id()]);
        $outcome = $this->get_generator()->create_outcome(certificate::class, $rule->id,
            ['certificate' => $syscertificate->get_id()]);
        $this->assertArrayNotHasKey('certificate', $outcome->validate_config_form(
            ['certificate' => $syscertificate->get_id()]));
        $this->assertArrayNotHasKey('certificate', $outcome->validate_config_form(
            ['certificate' => $catcertificate->get_id()]));
        $this->assertArrayNotHasKey('certificate', $outcome->validate_config_form(
            ['certificate' => $subcatcertificate->get_id()]));
        $this->assertArrayNotHasKey('certificate', $outcome->validate_config_form(
            ['certificate' => $tenant0catcertificate->get_id()]));
        $this->assertArrayNotHasKey('certificate', $outcome->validate_config_form(
            ['certificate' => $tenant0subcatcertificate->get_id()]));
        $this->assertArrayNotHasKey('certificate', $outcome->validate_config_form(
            ['certificate' => $tenant1catcertificate->get_id()]));
        $this->assertArrayNotHasKey('certificate', $outcome->validate_config_form(
            ['certificate' => $tenant1subcatcertificate->get_id()]));

        // Shared space should not see tenant cohorts.
        $sharedspaceid = \tool_tenant\sharedspace::enable_shared_space();
        \tool_tenant\tenancy::set_switched_tenant_id($sharedspaceid);
        $rule = $this->get_generator()->create_rule(['tenantid' => $sharedspaceid]);
        $outcome = $this->get_generator()->create_outcome(certificate::class, $rule->id,
            ['certificate' => $syscertificate->get_id()]);
        $this->assertArrayNotHasKey('certificate', $outcome->validate_config_form(
            ['certificate' => $syscertificate->get_id()]));
        $this->assertArrayNotHasKey('certificate', $outcome->validate_config_form(
            ['certificate' => $catcertificate->get_id()]));
        $this->assertArrayNotHasKey('certificate', $outcome->validate_config_form(
            ['certificate' => $subcatcertificate->get_id()]));
        $this->assertArrayHasKey('certificate', $outcome->validate_config_form(
            ['certificate' => $tenant0catcertificate->get_id()]));
        $this->assertArrayHasKey('certificate', $outcome->validate_config_form(
            ['certificate' => $tenant0subcatcertificate->get_id()]));
        $this->assertArrayHasKey('certificate', $outcome->validate_config_form(
            ['certificate' => $tenant1catcertificate->get_id()]));
        $this->assertArrayHasKey('certificate', $outcome->validate_config_form(
            ['certificate' => $tenant1subcatcertificate->get_id()]));
    }
}
