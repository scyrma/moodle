<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * tool_dynamicrule steps definitions.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use \Behat\Gherkin\Node\TableNode;

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Steps definitions for tool_dynamicrule.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_tool_dynamicrule extends behat_base {

    /**
     * Returns the dynamicrule generator
     * @return tool_dynamicrule_generator
     */
    protected function get_generator() : tool_dynamicrule_generator {
        $datagenerator = testing_util::get_data_generator();
        return $datagenerator->get_plugin_generator('tool_dynamicrule');
    }

    /**
     * Generates dynamic rules
     *
     * @Given /^the following dynamic rules exist:$/
     *
     * @param TableNode $data
     */
    public function the_following_dynamic_rules_exist(TableNode $data) {
        $generator = $this->get_generator();

        foreach ($data->getHash() as $elementdata) {
            $this->lookup_tenant($elementdata);
            $generator->create_rule($elementdata);
        }
    }

    /**
     * Looks up tenant id
     *
     * @param array $elementdata
     */
    protected function lookup_tenant(array &$elementdata) {
        global $DB;
        if (array_key_exists('tenant', $elementdata)) {
            if (empty($elementdata['tenant'])) {
                // Shared for all tenants.
                $elementdata['tenantid'] = 0;
            } else {
                // Lookup tenant id by tenant name.
                $elementdata['tenantid'] = $DB->get_field('tool_tenant', 'id',
                    ['name' => $elementdata['tenant']], MUST_EXIST);
            }
            unset($elementdata['tenant']);
        } else {
            // Otherwise assume default tenant.
            $elementdata['tenantid'] = \tool_tenant\tenancy::get_default_tenant_id();
        }
    }

    /**
     * Creates badges.
     *
     * @Given /^the following badges exists:$/
     *
     * @param TableNode $data
     */
    public function the_following_badges_exists(TableNode $data) {
        global $USER, $DB, $CFG;
        require_once(__DIR__ . '/../../../../../lib/badgeslib.php');

        foreach ($data->getHash() as $elementdata) {
            $fordb = new stdClass();
            $fordb->id = null;
            $fordb->name = $elementdata['name'];
            $fordb->description = "Testing badges";
            $fordb->timecreated = time();
            $fordb->timemodified = time();
            $fordb->usercreated = $USER->id;
            $fordb->usermodified = $USER->id;
            $fordb->issuername = "Test issuer";
            $fordb->issuerurl = "http://issuer-url.domain.co.nz";
            $fordb->issuercontact = "issuer@example.com";
            $fordb->expiredate = null;
            $fordb->expireperiod = null;
            $fordb->type = BADGE_TYPE_SITE;
            $fordb->version = 1;
            $fordb->language = 'en';
            $fordb->courseid = null;
            $fordb->messagesubject = "Test message subject";
            $fordb->message = "Test message body";
            $fordb->attachment = 1;
            $fordb->notification = 0;
            $fordb->imageauthorname = "Image Author 1";
            $fordb->imageauthoremail = "author@example.com";
            $fordb->imageauthorurl = "http://author-url.example.com";
            $fordb->imagecaption = "Test caption image";
            $fordb->status = BADGE_STATUS_INACTIVE;

            $badgeid = $DB->insert_record('badge', $fordb, true);
        }
    }
}
