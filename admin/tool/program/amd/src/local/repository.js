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
 * @module      tool_program/local/repository
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Odei Alba <odei.alba@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import Ajax from 'core/ajax';

/**
 * Archive given program.
 *
 * @param {Number} programid
 * @return {Promise}
 */
export const archiveProgram = programid => {
    const request = {
        methodname: 'tool_program_archive_program',
        args: {programid}
    };

    return Ajax.call([request])[0];
};

/**
 * Restore given program.
 *
 * @param {Number} programid
 * @return {Promise}
 */
export const restoreProgram = programid => {
    const request = {
        methodname: 'tool_program_restore_program',
        args: {programid}
    };

    return Ajax.call([request])[0];
};

/**
 * Duplicate given program.
 *
 * @param {Number} programid
 * @return {Promise}
 */
export const duplicateProgram = programid => {
    const request = {
        methodname: 'tool_program_duplicate_program',
        args: {programid}
    };

    return Ajax.call([request])[0];
};

/**
 * Hide or show given program.
 *
 * @param {Number} programid
 * @param {Number} visibility
 * @return {Promise}
 */
export const updateProgramVisibility = (programid, visibility) => {
    const request = {
        methodname: 'tool_program_update_program_visibility',
        args: {programid, visibility}
    };

    return Ajax.call([request])[0];
};

/**
 * Delete given program.
 *
 * @param {Number} programid
 * @return {Promise}
 */
export const deleteProgram = programid => {
    const request = {
        methodname: 'tool_program_delete_program',
        args: {programid}
    };

    return Ajax.call([request])[0];
};
