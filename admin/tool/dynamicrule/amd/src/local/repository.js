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
 * Module to handle plugin AJAX requests
 *
 * @module      tool_dynamicrule/local/repository
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Odei Alba <odei.alba@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import Ajax from 'core/ajax';

/**
 * Makes and AJAX call and lets behat to wait for the end of it
 *
 * @param {String} methodname
 * @param {Object} args
 * @returns {Promise}
 */
const callDynamicRuleWS = (methodname, args = {}) => Ajax.call([{methodname, args}])[0];

/**
 * Archive given rule.
 *
 * @param {Number} ruleid
 * @return {Promise}
 */
export const archiveRule = ruleid => callDynamicRuleWS('tool_dynamicrule_archive_rule', {id: ruleid});

/**
 * Unarchive given rule.
 *
 * @param {Number} ruleid
 * @return {Promise}
 */
export const unarchiveRule = ruleid => callDynamicRuleWS('tool_dynamicrule_unarchive_rule', {id: ruleid});

/**
 * Duplicate given rule.
 *
 * @param {Number} ruleid
 * @return {Promise}
 */
export const duplicateRule = ruleid => callDynamicRuleWS('tool_dynamicrule_duplicate_rule', {id: ruleid});

/**
 * Delete given rule.
 *
 * @param {Number} ruleid
 * @return {Promise}
 */
export const deleteRule = ruleid => callDynamicRuleWS('tool_dynamicrule_delete_rule', {id: ruleid});

/**
 * Enable given rule.
 *
 * @param {Number} ruleid
 * @return {Promise}
 */
export const enableRule = ruleid => callDynamicRuleWS('tool_dynamicrule_enable_rule', {id: ruleid});

/**
 * Disable given rule.
 *
 * @param {Number} ruleid
 * @return {Promise}
 */
export const disableRule = ruleid => callDynamicRuleWS('tool_dynamicrule_disable_rule', {id: ruleid});

/**
 * Count users that matched given rule.
 *
 * @param {Number} ruleid
 * @return {Promise}
 */
export const countMatchedUsers = ruleid => callDynamicRuleWS('tool_dynamicrule_count_matched_users', {id: ruleid});

/**
 * Count users matching given rule.
 *
 * @param {Number} ruleid
 * @return {Promise}
 */
export const countMatchingUsers = ruleid => callDynamicRuleWS('tool_dynamicrule_count_matching_users', {id: ruleid});
