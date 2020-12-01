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
 * tool_dynamicrule steps definitions.
 *
 * @package    tool_dynamicrule
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
     * Creates badges. TODO: move to plugin creatable entities scope, or create same in core.
     *
     * @Given /^I create the following badges:$/
     *
     * @param TableNode $data
     */
    public function i_create_the_following_badges(TableNode $data) {
        global $USER, $DB, $CFG;
        require_once($CFG->libdir . '/badgeslib.php');

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
            $fordb->version = OPEN_BADGES_V2;
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
            $fordb->status = BADGE_STATUS_ACTIVE;

            $fordb->id = $DB->insert_record('badge', (object) $fordb);

            // Add manual enrol criteria for Manager role.
            $overall = award_criteria::build(['criteriatype' => BADGE_CRITERIA_TYPE_OVERALL, 'badgeid' => $fordb->id]);
            $overall->save(['agg' => BADGE_CRITERIA_AGGREGATION_ALL]);
            $criteria = award_criteria::build([
                'badgeid' => $fordb->id,
                'criteriatype' => BADGE_CRITERIA_TYPE_MANUAL,
            ]);
            $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'tool_tenant_admin']);
            $params = [
                'role_' . $managerroleid => $managerroleid,
                'agg' => BADGE_CRITERIA_AGGREGATION_ANY
            ];
            $criteria->save($params);
        }
    }

    /**
     * Delete courses
     *
     * @Given /^I delete the following courses:$/
     *
     * @param TableNode $data
     */
    public function i_delete_the_following_courses(TableNode $data) {
        global $DB;
        foreach ($data->getHash() as $elementdata) {
            $course = $DB->get_record('course', ['shortname' => $elementdata['shortname']]);
            delete_course($course->id, false);
        }
    }

    /**
     * Modify the first condition from a rule to be invalid
     *
     * @Given /^I modify first condition class to be invalid:$/
     *
     * @param TableNode $data
     */
    public function i_modify_first_condition_class_to_be_invalid(TableNode $data) {
        global $DB;
        foreach ($data->getHash() as $elementdata) {
            $sql = '
                SELECT drc.*
                FROM {tool_dynamicrule_condition} drc
                JOIN {tool_dynamicrule} dr
                ON dr.id = drc.ruleid
                WHERE dr.name = :drname
            ';
            $params['drname'] = $elementdata['rule'];
            $conditions = $DB->get_records_sql($sql, $params);
            $condition = reset($conditions);
            $condition->classname = 'tool_dynamicrule\tool_dynamicrule\condition\invalid';
            $DB->update_record('tool_dynamicrule_condition', $condition);
        }
    }

    /**
     * Modify the first action from a rule to be invalid
     *
     * @Given /^I modify first action class to be invalid:$/
     *
     * @param TableNode $data
     */
    public function i_modify_first_action_class_to_be_invalid(TableNode $data) {
        global $DB;
        foreach ($data->getHash() as $elementdata) {
            $sql = '
                SELECT dro.*
                FROM {tool_dynamicrule_outcome} dro
                JOIN {tool_dynamicrule} dr
                ON dr.id = dro.ruleid
                WHERE dr.name = :drname
            ';
            $params['drname'] = $elementdata['rule'];
            $outcomes = $DB->get_records_sql($sql, $params);
            $outcome = reset($outcomes);
            $outcome->classname = 'tool_dynamicrule\tool_dynamicrule\outcome\invalid';
            $DB->update_record('tool_dynamicrule_outcome', $outcome);
        }
    }
}
