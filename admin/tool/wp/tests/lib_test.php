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
 * File containing tests for functions in lib.php
 *
 * @package     tool_wp
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for functions in lib.php
 *
 * @package    tool_wp
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_wp_lib_testcase extends advanced_testcase {

    /**
     * Test calling core functions that inside itself call callback from this method. No assertion, only look for errors
     */
    public function test_get_site_info() {
        $this->resetAfterTest(true);
        $siteinfo = \core\hub\registration::get_site_info();
        \core\hub\registration::get_stats_summary($siteinfo);
    }

    /**
     * Test for callback 'wp_registration_stats'
     *
     * @uses \tool_wp\registration::site_info()
     */
    public function test_registration_get_site_info() {
        global $CFG;
        $this->resetAfterTest(true);
        $origsiteinfo = $siteinfo = ['moodlerelease' => $CFG->release, 'url' => $CFG->wwwroot];
        component_class_callback('tool_wp\\registration', 'site_info', [&$siteinfo, false]);
        $appended = array_diff_key($siteinfo, $origsiteinfo);

        $this->assertNotEmpty($appended['moodleproduct']);
        $this->assertNotNull($appended['wpactiveusers']);
        $this->assertNotNull($appended['wpparticipantnumberaverage']);
        $this->assertNotNull($appended['wpplugins']);
        if (\core_component::get_component_directory('tool_certificate')) {
            $this->assertNotNull($appended['wpcertificates']);
            $this->assertNotNull($appended['wpcertificatesissues']);
        }

        $siteinfo = $origsiteinfo;
        component_class_callback('tool_wp\\registration', 'site_info', [&$siteinfo, true]);
    }

    /**
     * Various scenarios to display state notification.
     *
     * @return array
     * @throws moodle_exception
     */
    public function state_data(): array {
        return [
            'Site in production state, not registered and viewing as non-admin' =>
                [1, false, false, []],
            'Site in production state, not registered and viewing as admin' =>
                [1, false, true,
                    ['message' => new \lang_string('registrationwarning', 'admin'),
                        'url' => new \moodle_url('/admin/registration/index.php'),
                        'label' => new \lang_string('register', 'admin')]],
                'Site in production state, registered and viewing as admin' => [1, true, true, []],
                'Site in production state, registered and viewing as non-admin' => [1, true, false, []],
                'Site in non-production state and viewing as admin' =>
                [0, false, true, ['message' => new \lang_string('nonproductionsitemessage', 'tool_wp'),
                    'url' => new \moodle_url('/admin/settings.php',
                        array('section' => 'productionstate')),
                    'label' => new \lang_string('change', 'tool_wp')]],
                'Site in non-production state and viewing as non-admin' =>
                [0, false, false, ['message' => new \lang_string('nonproductionsitemessage', 'tool_wp')]]
        ];
    }

    /**
     * Test Production state notification output.
     *
     * @dataProvider state_data
     * @param int $productionstate
     * @param bool $isregistered
     * @param bool $isadmin
     * @param array $expectedoutput
     */
    public function test_production_state(int $productionstate, bool $isregistered, bool $isadmin, array $expectedoutput) {
        global $DB;
        $this->resetAfterTest();
        $user1 = $this->getDataGenerator()->create_user();
        set_config('workplaceproductionstate', $productionstate);
        $isadmin ? $this->setAdminUser() : $this->setUser($user1);
        if ($isregistered) {
            // Site is registered.
            $hub = new stdClass();
            $hub->token = get_site_identifier();
            $hub->secret = $hub->token;
            $hub->huburl = HUB_MOODLEORGHUBURL;
            $hub->hubname = 'moodle';
            $hub->confirmed = 1;
            $hub->timemodified = time();
            $hub->id = $DB->insert_record('registration_hubs', $hub);
        }
        $result = tool_wp\workplace::get_production_state_context();
        $this->assertEqualsCanonicalizing($expectedoutput, $result);
    }
}
