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
 * tool_dynamicrule data generator.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy <marina@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_dynamicrule\condition_base;
use tool_dynamicrule\outcome_base;

/**
 * tool_dynamicrule data generator class.
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_dynamicrule_generator extends component_generator_base {

    /**
     * Number of rule instances created
     * @var int
     */
    protected $ruleinstancecount = 0;

    /**
     * Number of badge instances created
     * @var int
     */
    protected $badgeinstancecount = 0;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->ruleinstancecount = 0;
        $this->badgeinstancecount = 0;
    }

    /**
     * Creates new rule
     *
     * @param array|stdClass $record
     * @return stdClass rule object (record from db)
     */
    public function create_rule($record = []) : stdClass {
        $record = (array)$record;
        if (!array_key_exists('name', $record)) {
            $record['name'] = 'New rule ' . (++$this->ruleinstancecount);
        }
        $rule = \tool_dynamicrule\api::create_rule((object)$record);
        return $rule->to_record();
    }

    /**
     * Creates a new condition.
     *
     * @param string $conditionclass
     * @param int $ruleid
     * @param array $configdata
     * @return condition_base
     */
    public function create_condition($conditionclass, int $ruleid, array $configdata = []): condition_base {
        return \tool_dynamicrule\api::create_rule_condition($ruleid, $conditionclass, $configdata);
    }

    /**
     * Creates testable condition "alwaystrue".
     *
     * @param int $ruleid
     * @return condition_base
     */
    public function create_condition_alwaystrue(int $ruleid): condition_base {
        require_once(__DIR__.'/../fixtures/testable_condition_alwaystrue.php');
        $class = '\tool_dynamicrule\tool_dynamicrule\condition\testable_condition_alwaystrue';
        return \tool_dynamicrule\api::create_rule_condition($ruleid, $class, ['always' => true], true);
    }

    /**
     * Reset static values in fixture condition "alwaystrue".
     */
    public function reset_condition_alwaystrue() {
        require_once(__DIR__.'/../fixtures/testable_condition_alwaystrue.php');
        \tool_dynamicrule\tool_dynamicrule\condition\testable_condition_alwaystrue::reset();
    }

    /**
     * Creates testable condition "alwaysfalse".
     *
     * @param int $ruleid
     * @return condition_base
     */
    public function create_condition_alwaysfalse(int $ruleid): condition_base {
        require_once(__DIR__.'/../fixtures/testable_condition_alwaysfalse.php');
        $class = '\tool_dynamicrule\tool_dynamicrule\condition\testable_condition_alwaysfalse';
        return \tool_dynamicrule\api::create_rule_condition($ruleid, $class, ['always' => false], true);
    }

    /**
     * Creates testable outcome "donothing".
     *
     * @param int $ruleid
     * @param bool $throwerror
     * @return outcome_base
     */
    public function create_outcome_donothing(int $ruleid, bool $throwerror = false) {
        require_once(__DIR__.'/../fixtures/testable_outcome_donothing.php');
        $class = '\tool_dynamicrule\tool_dynamicrule\outcome\testable_outcome_donothing';
        return \tool_dynamicrule\api::create_rule_outcome($ruleid, $class, ['do' => 0, 'error' => $throwerror], true);
    }

    /**
     * Reset static values in fixture outcome "donothing".
     */
    public function reset_outcome_donothing() {
        require_once(__DIR__.'/../fixtures/testable_outcome_donothing.php');
        \tool_dynamicrule\tool_dynamicrule\outcome\testable_outcome_donothing::reset();
    }

    /**
     * Creates a new outcome.
     *
     * @param string $outcomeclass
     * @param int $ruleid
     * @param array $configdata
     * @return outcome_base
     */
    public function create_outcome($outcomeclass, int $ruleid, array $configdata = []): outcome_base {
        return \tool_dynamicrule\api::create_rule_outcome($ruleid, $outcomeclass, $configdata);
    }

    /**
     * Creates a test badge.
     *
     * @param int $userid
     * @param int $status
     * @param string $name
     * @return int
     */
    public function create_badge(int $userid, int $status = 0, string $name = ''): badge {
        global $DB;

        $now = time();

        // Mock up a badge.
        if (empty($name)) {
            $name = 'Test badge ' . (++$this->badgeinstancecount);
        }

        $badge = new stdClass();
        $badge->id = null;
        $badge->name = $name;
        $badge->description = "Testing badges 1";
        $badge->timecreated = $now - 12;
        $badge->timemodified = $now - 12;
        $badge->usercreated = $userid;
        $badge->usermodified = $userid;
        $badge->issuername = "Test issuer";
        $badge->issuerurl = "http://issuer-url.domain.co.nz";
        $badge->issuercontact = "issuer@example.com";
        $badge->expiredate = null;
        $badge->expireperiod = null;
        $badge->type = BADGE_TYPE_SITE;
        $badge->courseid = null;
        $badge->messagesubject = "Test message subject for badge 1";
        $badge->message = "Test message body for badge 1";
        $badge->attachment = 1;
        $badge->notification = 0;
        $badge->status = $status;
        $badge->version = OPEN_BADGES_V2;
        $badge->language = "en";
        $badge->imagecaption = "Image caption 1";
        $badge->imageauthorname = "Image author's name 1";
        $badge->imageauthoremail = "author1@example.com";
        $badge->imageauthorname = "Image author's name 1";

        $badge->id = $DB->insert_record('badge', (object) $badge);

        // Add manual enrol criteria for Manager role.
        $overall = award_criteria::build(['criteriatype' => BADGE_CRITERIA_TYPE_OVERALL, 'badgeid' => $badge->id]);
        $overall->save(['agg' => BADGE_CRITERIA_AGGREGATION_ALL]);
        $criteria = award_criteria::build([
            'badgeid' => $badge->id,
            'criteriatype' => BADGE_CRITERIA_TYPE_MANUAL,
        ]);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        $params = [
            'role_' . $managerroleid => $managerroleid,
            'agg' => BADGE_CRITERIA_AGGREGATION_ANY
        ];
        $criteria->save($params);

        return new badge($badge->id);
    }

    /**
     * Helper function that allows to find users that will be matching the rule
     * conditions that did not match before with all restrictions applied. Basically
     * these are steps made in process_rule() function to fetch the the list of
     * users that is passed for outcomes applying, but unlike in above named
     * function, no DB changes are made here.
     *
     * Developer is advised to use this function to fetch users exactly in a way
     * they are retrieved during rule processing.
     *
     * @param int $ruleid
     * @return \stdClass[] list of users
     */
    public function get_matching_users_for_outcomes(int $ruleid): array {
        global $DB;

        if (!\tool_dynamicrule\condition::count_records(['ruleid' => $ruleid])) {
            // No conditions in this rule.
            return [];
        }

        if (!\tool_dynamicrule\api::static_conditions_are_matching($ruleid)) {
            return [];
        }

        // Find all users that match the rule who have not matched before.
        $sql = ' FROM {user} u ';
        list($join, $where, $params) = \tool_dynamicrule\api::get_matching_join_sql($ruleid);
        list($condjoin, $condwhere, $condparams) = \tool_dynamicrule\api::get_rule_conditions_sql($ruleid);

        $rule = \tool_dynamicrule\api::get_rule($ruleid, true);
        list($restrictionjoin, $groupbyhaving, $restrictionparams) = \tool_dynamicrule\api::get_rule_restriction_sql($rule);

        $sql = $sql . $join . $condjoin . $restrictionjoin .
            '  WHERE ' . $where . $condwhere . ' AND mlastmatch.id IS NULL ' .
            $groupbyhaving;
        $params = $params + $condparams + $restrictionparams;

        return $DB->get_records_sql('SELECT DISTINCT u.id' . $sql, $params);
    }
}
