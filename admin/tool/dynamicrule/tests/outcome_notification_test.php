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
 * File contains the unit tests for outcome\notification class.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_dynamicrule\tool_dynamicrule\outcome\notification;

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * Unit tests for outcome\notification  class.
 *
 * @package    tool_dynamicrule
 * @group      tool_dynamicrule
 * @covers     \tool_dynamicrule\tool_dynamicrule\outcome\notification
 * @covers     \tool_dynamicrule\outcome_base
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_outcome_notification_testcase extends advanced_testcase {

    /**
     * Set up
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Get dynamic rule generator
     *
     * @return tool_dynamicrule_generator
     */
    protected function get_generator(): tool_dynamicrule_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Tenant generator
     *
     * @return tool_tenant_generator
     */
    protected function get_tenant_generator() : tool_tenant_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_tenant');
    }

    /**
     * Organisation generator
     *
     * @return tool_organisation_generator
     */
    protected function get_organisation_generator() : tool_organisation_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_organisation');
    }

    /**
     * Test get_title
     */
    public function test_get_title() {
        $outcome = notification::instance();
        $this->assertNotEmpty($outcome->get_title());
    }

    /**
     * Test get_category
     */
    public function test_get_category() {
        $outcome = notification::instance();
        $this->assertEquals(get_string('general', 'tool_dynamicrule'), $outcome->get_category());
    }

    /**
     * Test validate_config_form
     */
    public function test_validate_config_form() {

        $outcome = notification::instance();
        $configform = ['subject' => '', 'body' => ''];
        $this->assertArrayHasKey('body', $outcome->validate_config_form($configform));
        $this->assertArrayHasKey('subject', $outcome->validate_config_form($configform));

        $configform = ['subject' => 'The notification subject', 'body' => 'Here comes the message.'];
        $this->assertArrayNotHasKey('body', $outcome->validate_config_form($configform));
        $this->assertArrayNotHasKey('subject', $outcome->validate_config_form($configform));
    }

    /**
     * Test get_body filters html correctly.
     */
    public function test_get_body() {
        $rule0 = $this->get_generator()->create_rule();

        $configdata = ['subject' => 'You matched!',
            'body' => ['text' => 'This is <strong>html</strong> ' .
            'with an image <img src="https://some.image.com/url" title="Image title"> ' .
            'and a <a href="https://some.url.com/image">link</a>. ' .
            'It also has a nasty script <script type="text/javascript">alert("XSS");</script> ' .
            'and dangerous iframe <iframe src="https://m00dle.org"></iframe>', 'format' => FORMAT_HTML]];
        $outcome = notification::create($rule0->id, $configdata);

        // Override method visibility.
        $reflector = new ReflectionClass($outcome);
        $method = $reflector->getMethod('get_body');
        $method->setAccessible(true);
        $return = $method->invoke($outcome);

        // No scripts, no iframes.
        $this->assertNotRegExp('/\<script/', $return);
        $this->assertNotRegExp('/\<iframe/', $return);

        // But link and image are there.
        $this->assertRegExp('/\<strong/', $return);
        $this->assertRegExp('/\<img/', $return);
        $this->assertRegExp('/\<a href/', $return);
    }

    /**
     * Test trigger_rule_processing
     */
    public function test_trigger_rule_processing() {
        global $DB;

        // Users first.
        $tenant = $this->get_tenant_generator()->create_tenant();
        $users = [$this->getDataGenerator()->create_user(), $this->getDataGenerator()->create_user()];
        $this->getDataGenerator()->get_plugin_generator('tool_tenant')->allocate_user($users[0]->id, $tenant->id);
        $this->getDataGenerator()->get_plugin_generator('tool_tenant')->allocate_user($users[1]->id, $tenant->id);

        // Course with completion.
        $course1 = self::getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);

        // Rule.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $configdata = ['courseid' => $course1->id];
        $condition = \tool_dynamicrule\tool_dynamicrule\condition\course_not_completed::create($rule0->id, $configdata);
        $configdata = ['subject' => 'You matched!',
            'body' => ['text' => "Please {{userfullname}},\n" .
            "remember to complete course {{coursefullname}} on time", 'format' => FORMAT_HTML]];
        $outcome = notification::create($rule0->id, $configdata);

        // Create users and enrol.
        $this->getDataGenerator()->enrol_user($users[0]->id, $course1->id, 'student', 'manual');
        $this->getDataGenerator()->enrol_user($users[1]->id, $course1->id, 'student', 'manual');

        $sink = $this->redirectMessages();
        // Trigger rule.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $savedmessages = $sink->get_messages();
        $this->assertCount(2, $savedmessages);
        $sink->close();

        $expected = [
            'Please ' . fullname($users[0]). ",\nremember to complete course {$course1->fullname} on time",
            'Please ' . fullname($users[1]). ",\nremember to complete course {$course1->fullname} on time",
        ];

        $this->assertEqualsCanonicalizing($expected, [$savedmessages[0]->fullmessagehtml, $savedmessages[1]->fullmessagehtml]);
    }

    /**
     * Test notification outcome with user placeholders
     */
    public function test_trigger_rule_processing_user_placeholders(): void {
        $this->setAdminUser();

        $rule = $this->get_generator()->create_rule(['enabled' => 1]);
        $this->get_generator()->create_condition_alwaystrue($rule->id);

        notification::create($rule->id, [
            'subject' => 'Welcome',
            'body' => [
                'text' => 'Firstname: {{userfirstname}}; Fullname: {{userfullname}}',
                'format' => FORMAT_HTML,
            ],
        ]);

        $sink = $this->redirectMessages();

        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $message = reset($messages);

        // The admin is our only current user, and should have received the message.
        $user = core_user::get_user_by_username('admin');
        $this->assertEquals($user->id, $message->useridto);
        $this->assertEquals('Welcome', $message->subject);

        $expectedfullmessage = vsprintf('Firstname: %s; Fullname: %s', [
            $user->firstname,
            fullname($user),
        ]);
        $this->assertEquals($expectedfullmessage, $message->fullmessage);
    }

    /**
     * Test trigger_rule_processing with site placeholders.
     */
    public function test_trigger_rule_processing_site_placeholders() {
        global $SITE;
        $rule1 = $this->get_generator()->create_rule(['enabled' => 1]);
        $configdata = ['lastlogintype' => \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::LAST_LOGIN_TYPE_NEVER];
        \tool_dynamicrule\tool_dynamicrule\condition\user_last_login::create($rule1->id, $configdata);

        $configdata = ['subject' => 'You matched!',
            'body' => ['text' => "sitefullname: {{sitefullname}},\n" .
                "siteshortname: {{siteshortname}},\n" .
                "sitelink: {{sitelink}}", 'format' => FORMAT_HTML]];
        $outcome = notification::create($rule1->id, $configdata);

        $users = [$this->getDataGenerator()->create_user()];

        $sink = $this->redirectMessages();
        // Trigger rule.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule1);
        \tool_dynamicrule\api::process_rule($ruleinstance);

        $savedmessages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $savedmessages);

        $url = (new \moodle_url('/'))->out(false);
        $contextid = \context_system::instance()->id;
        $fullname = format_string($SITE->fullname, true, ['context' => $contextid, 'escape' => false]);
        $shortname = format_string($SITE->shortname, true, ['context' => $contextid, 'escape' => false]);
        $expected = [
            "sitefullname: $fullname,\n" .
            "siteshortname: $shortname,\n" .
            "sitelink: <a href=\"$url\" class=\"_blanktarget\">$url</a>",
        ];

        $this->assertEqualsCanonicalizing($expected, [$savedmessages[0]->fullmessagehtml]);
    }

    /**
     * Test apply_to_users with Department lead notifications activated.
     *
     * @uses \tool_dynamicrule\tool_dynamicrule\condition\course_completed
     */
    public function test_trigger_rule_processing_sendto_dptlead_and_manager() {
        $orggenerator = $this->get_organisation_generator();
        $tenant = $this->get_tenant_generator()->create_tenant();
        $users = [
            'user1' => $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id]),
            'user2' => $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id]),
            'user3' => $this->get_tenant_generator()->create_user(['tenantid' => $tenant->id]),
        ];
        // Create positions.
        $pf = $orggenerator->create_position(['tenantid' => $tenant->id]);
        $pa = $orggenerator->create_position(['parentid' => $pf->id, 'departmentmanager' => 1]);
        $pb = $orggenerator->create_position(['parentid' => $pf->id, 'globalmanager' => 1]);
        $pb1 = $orggenerator->create_position(['parentid' => $pb->id]);
        // Create departments.
        $df = $orggenerator->create_department(['tenantid' => $tenant->id]);
        $da = $orggenerator->create_department(['parentid' => $df->id]);

        // Assign jobs.
        $orggenerator->assign_job((object)['userid' => $users['user1']->id, 'positionid' => $pb1->id, 'departmentid' => $da->id]);
        // User2 is direct department leader for user1.
        $orggenerator->assign_job((object)['userid' => $users['user2']->id, 'positionid' => $pa->id, 'departmentid' => $da->id]);
        // User3 is direct manager for user1.
        $orggenerator->assign_job((object)['userid' => $users['user3']->id, 'positionid' => $pb->id, 'departmentid' => $da->id]);
        cache::make('tool_organisation', 'myjob')->purge();

        // Create new rule setting only direct department lead notifications.
        $rule0 = $this->get_generator()->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $this->get_generator()->create_condition_alwaystrue($rule0->id);

        $configdata = ['subject' => '{{userfullname}} matched!',
            'body' => ['text' => "{{userfullname}} matched!", 'format' => FORMAT_HTML],
            'sendto' => ['dptlead' => 1]];
        $outcome = notification::create($rule0->id, $configdata);

        $sink = $this->redirectMessages();
        // Process rule manually as if we enabled it.
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule0);
        \tool_dynamicrule\api::process_rule($ruleinstance, $users['user1']->id);
        $savedmessages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $savedmessages);
        // Check notification was sent to user2 (user1 direct dept lead).
        $this->assertEquals($users['user2']->id, $savedmessages[0]->useridto);
        // Check placeholder is using affected user (user1) information.
        $this->assertEquals(fullname($users['user1']) . ' matched!', $savedmessages[0]->fullmessagehtml);

        // Create new rule setting only direct manager notifications.
        $rule1 = $this->get_generator()->create_rule(['enabled' => 1, 'tenantid' => $tenant->id]);
        $this->get_generator()->create_condition_alwaystrue($rule1->id);

        $configdata = ['subject' => '{{userfullname}} matched!',
            'body' => ['text' => "{{userfullname}} matched!", 'format' => FORMAT_HTML],
            'sendto' => ['manager' => 1]];
        $outcome = notification::create($rule1->id, $configdata);

        $sink = $this->redirectMessages();
        $ruleinstance = new \tool_dynamicrule\rule(0, $rule1);
        \tool_dynamicrule\api::process_rule($ruleinstance, $users['user1']->id);
        $savedmessages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $savedmessages);
        // Check notification was sent to user3 (user1 direct manager).
        $this->assertEquals($users['user3']->id, $savedmessages[0]->useridto);
        // Check placeholder is using affected user (user1) information.
        $this->assertEquals(fullname($users['user1']) . ' matched!', $savedmessages[0]->fullmessagehtml);
    }

    /**
     * Test get_description.
     */
    public function test_get_description() {

        $rule0 = $this->get_generator()->create_rule();

        $subject = 'You matched!';
        $configdata = ['subject' => $subject,
            'body' => ['text' => 'Congratulations, you matched the condition', 'format' => FORMAT_MOODLE]];
        $outcome = notification::create($rule0->id, $configdata);

        $str = get_string('outcomenotificationdescription', 'tool_dynamicrule', $subject);
        $this->assertEquals($str, $outcome->get_description());
    }

    /**
     * Test after_update.
     *
     * @uses \tool_dynamicrule\api::get_rule
     */
    public function test_after_update() {
        $rule0 = $this->get_generator()->create_rule(['broken' => 1]);

        $subject = 'Test subject';
        $configdata = ['subject' => $subject, 'body' => ['text' => 'Test body', 'format' => FORMAT_MOODLE]];
        $outcome = notification::create($rule0->id, $configdata);

        $configdata = ['subject' => $subject, 'body' => ['text' => 'Test body updated', 'format' => FORMAT_MOODLE]];
        $outcome->update_configdata($configdata, true);

        $this->assertFalse($outcome->is_broken());
    }

    /**
     * Test user_can_add
     */
    public function test_user_can_add() {
        $rule0 = $this->get_generator()->create_rule();

        $subject = 'Test subject';
        $configdata = ['subject' => $subject, 'body' => ['text' => 'Test body', 'format' => FORMAT_MOODLE]];
        notification::create($rule0->id, $configdata);

        // Admin user.
        self::setAdminUser();
        $this->assertTrue(notification::instance()->user_can_add());

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertTrue(notification::instance()->user_can_add());
    }

    /**
     * Test user_can_edit
     */
    public function test_user_can_edit() {
        $rule0 = $this->get_generator()->create_rule();

        $subject = 'Test subject';
        $configdata = ['subject' => $subject, 'body' => ['text' => 'Test body', 'format' => FORMAT_MOODLE]];
        notification::create($rule0->id, $configdata);

        // Admin user.
        self::setAdminUser();
        $this->assertTrue(notification::instance()->user_can_edit($configdata));

        // Non-priveleged user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $this->assertTrue(notification::instance()->user_can_edit($configdata));
    }
}
