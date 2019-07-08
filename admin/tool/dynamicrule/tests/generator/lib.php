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
 * tool_dynamicrule data generator.
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * tool_dynamicrule data generator class.
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dynamicrule_generator extends component_generator_base {

    /**
     * Number of rule instances created
     * @var int
     */
    protected $ruleinstancecount = 0;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->ruleinstancecount = 0;
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
    public function create_condition($conditionclass, int $ruleid, array $configdata = []) {
        return \tool_dynamicrule\api::create_rule_condition($ruleid, $conditionclass, $configdata);
    }

    /**
     * Creates testable condition "alwaystrue".
     *
     * @param int $ruleid
     * @return condition_base
     */
    public function create_condition_alwaystrue(int $ruleid) {
        require_once(__DIR__.'/../fixtures/testable_condition_alwaystrue.php');
        return \tool_dynamicrule\api::create_rule_condition($ruleid, 'testable_condition_alwaystrue');
    }

    /**
     * Creates testable condition "alwaysfalse".
     *
     * @param int $ruleid
     * @return condition_base
     */
    public function create_condition_alwaysfalse(int $ruleid) {
        require_once(__DIR__.'/../fixtures/testable_condition_alwaysfalse.php');
        return \tool_dynamicrule\api::create_rule_condition($ruleid, 'testable_condition_alwaysfalse');
    }

    /**
     * Creates a new outcome.
     *
     * @param string $outcomeclass
     * @param int $ruleid
     * @param array $configdata
     * @return condition_base
     */
    public function create_outcome($outcomeclass, int $ruleid, array $configdata = []) {
        return \tool_dynamicrule\api::create_rule_outcome($ruleid, $outcomeclass, $configdata);
    }

    /**
     * Creates a test badge.
     *
     * @param int $userid
     * @param int $status
     * @return bool|int
     * @throws dml_exception
     */
    public function create_badge(int $userid, int $status = 0) {
        global $DB;

        $now = time();
        // Mock up a badge.
        $badge = new stdClass();
        $badge->id = null;
        $badge->name = "Test badge 1";
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
        $badge->type = 1;
        $badge->courseid = null;
        $badge->messagesubject = "Test message subject for badge 1";
        $badge->message = "Test message body for badge 1";
        $badge->attachment = 1;
        $badge->notification = 0;
        $badge->status = $status;
        $badge->version = "Version 1";
        $badge->language = "en";
        $badge->imagecaption = "Image caption 1";
        $badge->imageauthorname = "Image author's name 1";
        $badge->imageauthoremail = "author1@example.com";
        $badge->imageauthorname = "Image author's name 1";

        return $DB->insert_record('badge', (object) $badge);
    }
}
