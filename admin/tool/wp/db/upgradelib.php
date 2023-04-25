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
 * Upgrade scripts for wp plugin
 *
 * @package     tool_wp
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Update block positions from wplist course format to topics course format.
 * If a block instance has already a position defined for topics format in the same context it is not updated.
 */
function tool_wp_upgrade_update_wplist_block_positions() {
    global $DB;
    // We need to detect any block position record where pagetype is "course-view-wplist" where there also exists the same record,
    // (based on blockinstanceid, contextid, subpage, pagetype unique index) for another pagetype.
    // Any matching records will not be updated.
    $sql = "SELECT bp.id
                   FROM {block_positions} bp
                   JOIN (
                          SELECT blockinstanceid, contextid, subpage
                            FROM {block_positions}
                        GROUP BY blockinstanceid, contextid, subpage
                          HAVING COUNT(*) > 1
                       ) duplicates ON duplicates.blockinstanceid = bp.blockinstanceid
                                   AND duplicates.contextid = bp.contextid
                                   AND duplicates.subpage = bp.subpage
                  WHERE bp.pagetype = 'course-view-wplist'";

    $duplicates = $DB->get_fieldset_sql($sql);
    [$notinsql, $notinparams] = $DB->get_in_or_equal($duplicates, SQL_PARAMS_QM, 'param', false, null);

    // Now perform the update of page types, ignoring any records returned from the previous query.
    $DB->execute(
        "UPDATE {block_positions} SET pagetype = 'course-view-topics' WHERE pagetype = 'course-view-wplist' AND id $notinsql",
        $notinparams
    );
}
