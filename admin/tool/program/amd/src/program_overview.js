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
 * Program overview module.
 *
 * @module     tool_program/program_overview
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(['jquery', 'core/ajax', 'core/notification'], function($, ajax, Notification) {

    var SELECTOR = {
        PROGRAMSREGION: '[data-region="programs-overview-view"]',
        PROGRAMSVIEW: '.programs-view',
        PROGRAMSTATUSDROPDOWN: '#programs-status-filter-dropdown',
        ALL: '.program-item',
        COMPLETED: '.program-item[data-programcompleted="1"]',
        NOTCOMPLETED: '.program-item:not([data-programcompleted="1"])',
        WITHDUEDATE: '.program-item[data-programwithduedate="1"]',
        CLOSETODUEDATE: '.program-item[data-programclosetoduedate="1"]',
    };
    var PROGRAMSFILTER = {
        SHOW: '',
        HIDE: 'none'
    };
    /**
     * Enrol a user into a course.
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
    /**
     * Filter for programs.
     *
     * @param {String} selector
     * @param {bool} visibility
     */
    var filterPrograms = function(selector, visibility) {
        let elements = document.querySelectorAll(SELECTOR.PROGRAMSREGION + ' ' + selector);
        if (elements.length === 0) {
            $('.nothingtodisplay').show();
        } else {
            $('.nothingtodisplay').hide();
            elements.forEach((program) => {
                program.style.display = visibility;
            });
        }
    };
    /**
     * Update user preferences.
     *
     * @param {String} type
     * @param {String} value
     */
    var updateUserPreferences = function(type, value) {
        var request = {
            methodname: 'core_user_update_user_preferences',
            args: {
                preferences: [{
                    type: type,
                    value: value,
                }]
            }
        };

        ajax.call([request])[0]
            .fail(Notification.exception);
    };
    /**
     * Get the user's preferences.
     */
    var loadUserProgramStatusFilter = function() {
        var request = {
            methodname: 'core_user_get_user_preferences',
            args: {
                name: 'tool_program_program_status_filter',
            }
        };
        ajax.call([request])[0]
            .then(function(p) {
                showProgramsFiltered(p.preferences[0].value || 'all');
                return null;
            })
            .fail(Notification.exception);
    };
    var showProgramsFiltered = function(programfilter) {
        let programsList = $(SELECTOR.PROGRAMSREGION);
        programsList.hide();
        // We hide all programs and show the selected ones.
        filterPrograms(SELECTOR.ALL, PROGRAMSFILTER.HIDE);
        filterPrograms(SELECTOR[programfilter.toUpperCase()], PROGRAMSFILTER.SHOW);
        updateUserPreferences('tool_program_program_status_filter', programfilter);
        programsList.fadeIn('fast');
    };

    return {
        /**
         * Initialises program overview.
         */
        init: function() {
            // Maincontent anchor hack to mantain accesibility.
            $("#maincontent").detach().prependTo('#region-main');

            // Load correct filter saved in user preferences.
            if ($(SELECTOR.PROGRAMSVIEW).data('showfilters') === 1) {
                loadUserProgramStatusFilter();
            }

            $(".section_expand").click(function(e) {
                e.preventDefault();
                $(this).next('.content').toggle();
                $(this).find('[class$="-icon-container"]').toggle();
            });
            $(".enrol_to_course").click(function(e) {
                e.preventDefault();
                enrolUserToCourse($(this).data('courseid'), $(this).data('programid'));
            });
            $('.programs-status-filter .dropdown-item').on('click', function(e) {
                e.preventDefault();
                showProgramsFiltered($(this).data('value'));
            });
        }
    };
});
