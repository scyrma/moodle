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
 * Program overview module
 *
 * @module     tool_program/program_overview
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification'], function($, ajax, Notification) {

    /**
     * Enrol a user into a course
     *
     * @param {Number} courseid
     * @param {Number} programid
     */
    var enrolUserToCourse = function(courseid, programid) {
        var promises = ajax.call([
            {methodname: 'tool_program_enrol_user_to_course', args: {courseid: courseid, programid: programid}}
        ]);
        promises[0].done(function(response) {
            if (response.status && response.redirecturl) {
                window.location.href = response.redirecturl;
            }
        }).fail(Notification.exception);
    };

    return {
        /**
         * Initialises program overview
         */
        init: function() {
            $(".section_expand").click(function(e) {
                e.preventDefault();
                $(this).next('.content').toggle();
                $(this).find('[class$="-icon-container"]').toggle();
            });
            $(".enrol_to_course").click(function(e) {
                e.preventDefault();
                enrolUserToCourse($(this).data('courseid'), $(this).data('programid'));
            });
        }
    };
});
