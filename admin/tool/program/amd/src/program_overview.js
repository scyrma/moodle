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
        ALL: '.dashboard-item',
        CERTIFICATIONNAME: '[data-region="certificationname"]',
        CONTROLREGION: '[data-region="dashboard-controls"]',
        COMPLETED: '.dashboard-item[data-completed="1"]',
        COURSES: '.dashboard-item[data-courses="1"]',
        COURSENAME: '[data-region="course-call-to-action"]',
        NOTCOMPLETED: '.dashboard-item:not([data-completed="1"])',
        PROGRAMS: '.dashboard-item[data-programs="1"]',
        PROGRAMDESCRIPTION: '.program-description',
        PROGRAMNAME: '[data-region="programname"]',
        PROGRAMSREGION: '[data-region="programs-overview-view"]',
        PROGRAMSTATUSDROPDOWN: '#programs-status-filter-dropdown',
        PROGRAMTAGS: '[data-region="programtags"]',
    };

    var PROGRAMSFILTER = {
        SHOW: 'filter-visible',
        HIDE: 'filter-hidden'
    };

    var SEARCHFILTER = {
        SHOW: 'search-visible',
        HIDE: 'search-hidden'
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
     * @param {bool} visiblity
     */
    var filterPrograms = function(selector, visiblity) {
        let elements = $(SELECTOR.PROGRAMSREGION + ' ' + selector);
        if (elements.length === 0) {
            $('.nothingtodisplay').show();
        } else {
            $('.nothingtodisplay').hide();
            elements.each(function() {
                $(this).removeClass(PROGRAMSFILTER.SHOW).removeClass(PROGRAMSFILTER.HIDE);
                $(this).addClass(visiblity);
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

    var checkNothingToDisplay = function() {
        let visibleElements = $(SELECTOR.PROGRAMSREGION).find(SELECTOR.ALL + ':visible');
        if (visibleElements.length === 0) {
            $('.nothingtodisplay').show();
        } else {
            $('.nothingtodisplay').hide();
        }
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
                showProgramsWithStatus(p.preferences[0].value || 'all');
                return null;
            })
            .fail(Notification.exception);
    };

    /**
     * Show programs matching a status
     *
     * @param  {String} programfilter
     */
    var showProgramsWithStatus = function(programfilter) {
        M.util.js_pending('tool_program_program_filterprograms'); // Tell Behat to wait.
        let programsList = $(SELECTOR.PROGRAMSREGION);
        programsList.hide();
        // We hide all programs and show the selected ones.
        filterPrograms(SELECTOR.ALL, PROGRAMSFILTER.HIDE);
        filterPrograms(SELECTOR[programfilter.toUpperCase()], PROGRAMSFILTER.SHOW);
        updateUserPreferences('tool_program_program_status_filter', programfilter);
        programsList.fadeIn('fast', function() {
            M.util.js_complete('tool_program_program_filterprograms');
        });
    };

    /**
     * Update program display
     *
     * @param {String} displaytype
     */
    var updateProgramsDisplay = function(displaytype) {
        $(SELECTOR.PROGRAMSREGION).removeClass('viewcards').removeClass('viewlist').addClass(displaytype);
        $(SELECTOR.CONTROLREGION).removeClass('viewcards').removeClass('viewlist').addClass(displaytype);
        updateUserPreferences('tool_program_program_view_filter', displaytype);
    };

    /**
     * Update program sorting.
     *
     * @param {String} sortorder
     */
    var updateProgramsSorting = function(sortorder) {
        let programs = $(SELECTOR.PROGRAMSREGION).find(SELECTOR.ALL);
        // Sort by element name.
        if (sortorder === 'programname') {
            programs.sort((a, b) => {
                let programNameA = $(a).find('[data-region="programname"]').text();
                let programNameB = $(b).find('[data-region="programname"]').text();
                return programNameA.localeCompare(programNameB);
            });
        // Sort by element near conclusion date.
        } else if (sortorder === 'duedate') {
            programs.sort((a, b) => {
               return $(a).data("lowestduedate") - $(b).data("lowestduedate");
            });
        // Sort by element last access date.
        } else if (sortorder === 'lastaccess') {
            programs.sort((a, b) => {
                // If lastaccess is the same order by name to be consistent with the app.
                if ($(b).data("lastaccess") === $(a).data("lastaccess")) {
                    const compareA = $(a).find('[data-region="programname"]').text().toLowerCase(),
                        compareB = $(b).find('[data-region="programname"]').text().toLowerCase();
                    return compareA.localeCompare(compareB);
                }
                return $(b).data("lastaccess") - $(a).data("lastaccess");
            });
        }
        // Update the elements list.
        $(SELECTOR.PROGRAMSREGION).remove(SELECTOR.ALL).prepend(programs);
        // Update the user preferences.
        updateUserPreferences('tool_program_program_sort_filter', sortorder);
        // Add a little visual affect to show something is happening.
        $(SELECTOR.PROGRAMSREGION).hide().fadeIn('fast');
    };

    /**
     * Show matching programs.
     *
     * @param {String} query string
     */
    var showMatchingPrograms = function(query) {
        $(SELECTOR.PROGRAMSREGION).find(SELECTOR.ALL).filter(function() {
            let program = $(this);
            let dashboardText = program.find(SELECTOR.PROGRAMNAME).text();
            dashboardText += ' ' + program.find(SELECTOR.COURSENAME).text();
            dashboardText += ' ' + program.find(SELECTOR.PROGRAMTAGS).text();
            dashboardText += ' ' + program.find(SELECTOR.CERTIFICATIONNAME).text();
            dashboardText += ' ' + program.find(SELECTOR.PROGRAMDESCRIPTION).text();
            if (dashboardText.toLowerCase().indexOf(query) > -1) {
                program.removeClass(SEARCHFILTER.HIDE).addClass(SEARCHFILTER.SHOW);
            } else {
                program.removeClass(SEARCHFILTER.SHOW).addClass(SEARCHFILTER.HIDE);
            }
            return true;
        });
    };

    return {
        /**
         * Initialises program overview.
         */
        init: function() {
            // Maincontent anchor hack to mantain accesibility.
            $("#maincontent").detach().prependTo('#region-main');

            // Dispatch resize event to correctly recalculate visible courses for recently accessed courses block.
            $('body').on('shown shown.bs.tab', function() {
                window.dispatchEvent(new Event('resize'));
            });

            // Load correct filter saved in user preferences.
            if ($(SELECTOR.PROGRAMSREGION).data('showfilters') === 1) {
                loadUserProgramStatusFilter();
            }

            $(".section_expand").click(function(e) {
                e.preventDefault();
                $(this).next('.content').toggle();
                $(this).find('[class$="-icon-container"]').toggle();
            });

            $(SELECTOR.PROGRAMSREGION).on('click', '.enrol_to_course', function(e) {
                e.preventDefault();
                enrolUserToCourse($(this).data('courseid'), $(this).data('programid'));
            });

            // Event listener for statusses.
            $('.programs-status-filter .dropdown-item').on('click', function(e) {
                e.preventDefault();
                showProgramsWithStatus($(this).data('value'));
                checkNothingToDisplay();
            });

            // Event listener for the view type.
            $('.programs-display .dropdown-item').on('click', function(e) {
                 e.preventDefault();
                 updateProgramsDisplay($(this).data('value'));
            });

            // Event listener for sorting programs.
            $('.programs-sorting .dropdown-item').on('click', function(e) {
                 e.preventDefault();
                 updateProgramsSorting($(this).data('value'));
            });

            // Event listener for searching.
            $('[data-region="programs-search-input"]').keyup(function() {
                var query = $(this).val().toLowerCase().trim();
                showMatchingPrograms(query);
                checkNothingToDisplay();
            });
        }
    };
});
