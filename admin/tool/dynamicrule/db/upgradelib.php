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
