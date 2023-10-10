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
 * Upgrade scripts for dynamic rules.
 *
 * @package   tool_dynamicrule
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Ruslan Kabalin
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_dynamicrule\tool_dynamicrule\condition\user_profile_field;
use core_badges\badge;

/**
 * Remove orphaned rules that belong to tenants which no longer exists.
 */
function tool_dynamicrule_upgrade_remove_tenant_orphaned_rules() {
    global $DB;

    $sql = "SELECT r.*
              FROM {tool_dynamicrule} r
         LEFT JOIN {tool_tenant} t
                ON (r.tenantid = t.id)
             WHERE t.id IS NULL";
    $rules = $DB->get_records_sql($sql);
    foreach ($rules as $rule) {
        // Delete related records.
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('tool_dynamicrule', ['id' => $rule->id]);
        $DB->delete_records('tool_dynamicrule_condition', ['ruleid' => $rule->id]);
        $DB->delete_records('tool_dynamicrule_outcome', ['ruleid' => $rule->id]);
        $DB->delete_records('tool_dynamicrule_match', ['ruleid' => $rule->id]);
        $DB->commit_delegated_transaction($transaction);
    }
}

/**
 * Update previous DR conditions who uses custom profile fields.
 */
function tool_dynamicrule_upgrade_update_user_profile_fields() {
    global $DB;

    $conditions = $DB->get_records('tool_dynamicrule_condition',
        ['classname' => 'tool_dynamicrule\tool_dynamicrule\condition\user_profile_field']);

    foreach ($conditions as $condition) {
        $configdata = user_profile_field::instance($condition->id)->get_configdata();
        // Update config data for custom profile fields with prefix + shortname.
        $configdata = user_profile_field::update_custom_profile_field($configdata);

        $condition->configdata = json_encode($configdata);
        $DB->update_record('tool_dynamicrule_condition', $condition);
    }
}

/**
 * Fix missing manual issue record for badges.
 */
function tool_dynamicrule_fix_manual_issue_badges(): void {
    global $DB, $CFG;
    require_once($CFG->libdir . '/badgeslib.php');

    // Get badges that have manual criteria and have missing award record.
    $sql = "SELECT b.*
              FROM {badge_issued} b
              JOIN {badge_criteria} bc
                ON (bc.badgeid = b.badgeid AND bc.criteriatype = ?)
         LEFT JOIN {badge_manual_award} bma
                ON (bma.badgeid = b.badgeid AND bma.recipientid = b.userid)
             WHERE bma.id IS NULL";
    $issues = $DB->get_records_sql($sql, [BADGE_CRITERIA_TYPE_MANUAL]);
    $adminid = get_admin()->id;

    foreach ($issues as $issue) {
        // First check that manual issue criteria is the only one configured for the badge.
        // There could be cases where ANY condition set with more than one criteria, in this
        // case badge can be issued but manual condition is not met.
        $badge = new badge($issue->badgeid);
        if (count($badge->criteria) === 2 && isset($badge->criteria[BADGE_CRITERIA_TYPE_MANUAL])
                && isset($badge->criteria[BADGE_CRITERIA_TYPE_OVERALL])) {
            // Fix badge_criteria_met records.
            $obj = new stdClass();
            $obj->userid = $issue->userid;
            $obj->datemet = $issue->dateissued;
            $obj->issuedid = $issue->id;
            $obj->critid = $badge->criteria[BADGE_CRITERIA_TYPE_MANUAL]->id;
            if (!$DB->record_exists('badge_criteria_met', ['critid' => $obj->critid, 'userid' => $issue->userid])) {
                $DB->insert_record('badge_criteria_met', $obj);
            }
            $obj->critid = $badge->criteria[BADGE_CRITERIA_TYPE_OVERALL]->id;
            if (!$DB->record_exists('badge_criteria_met', ['critid' => $obj->critid, 'userid' => $issue->userid])) {
                $DB->insert_record('badge_criteria_met', $obj);
            }

            // Fix manual award record.
            $award = new stdClass();
            $award->badgeid = $issue->badgeid;
            $award->issuerid = $adminid;
            $award->issuerrole = array_key_first($badge->criteria[BADGE_CRITERIA_TYPE_MANUAL]->params);
            $award->recipientid = $issue->userid;
            $award->datemet = $issue->dateissued;
            $DB->insert_record('badge_manual_award', $award);
        }
    }
}
