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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

/**
 * Module to handle plugin AJAX requests
 *
 * @module      tool_custompage/local/repository
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import Ajax from 'core/ajax';

/**
 * Delete given page
 *
 * @param {Number} pageid
 * @return {Promise}
 */
export const deletePage = pageid => {
    const request = {
        methodname: 'tool_custompage_page_delete',
        args: {pageid}
    };

    return Ajax.call([request])[0];
};

/**
 * Duplicate given page
 *
 * @param {Number} pageid
 * @param {Boolean} amendglobal
 * @param {Boolean} newglobal
 * @return {Promise}
 */
export const duplicatePage = (pageid, amendglobal, newglobal) => {
    const request = {
        methodname: 'tool_custompage_page_duplicate',
        args: {pageid, amendglobal, newglobal}
    };

    return Ajax.call([request])[0];
};

/**
 * Delete given page audience
 *
 * @param {Number} pageid
 * @param {Number} instanceid
 * @return {Promise}
 */
export const deleteAudience = (pageid, instanceid) => {
    const request = {
        methodname: 'tool_custompage_audience_delete',
        args: {pageid, instanceid}
    };

    return Ajax.call([request])[0];
};
