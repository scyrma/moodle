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
 * Program overview module.
 *
 * @module     tool_program/program_overview
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 David Matamoros <davidmc@moodle.com>
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
     * @param {Element} region
     * @param {String} selector
     * @param {bool} visiblity
     */
    var filterPrograms = function(region, selector, visiblity) {
        let elements = region.find(selector);
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

    /**
     * Show/Hide 'Nothing to display' content.
     *
     * @param {String} regionSelector
     */
    var checkNothingToDisplay = function(regionSelector) {
        const learningRegion = $(regionSelector + ' ' + SELECTOR.PROGRAMSREGION);
        let visibleElements = learningRegion.find(SELECTOR.ALL + ':visible');
        if (visibleElements.length === 0) {
            learningRegion.find('.nothingtodisplay').show();
        } else {
            learningRegion.find('.nothingtodisplay').hide();
        }
    };

    /**
     * Get the user's preferences.
     *
     * @param {String} regionSelector
     */
    var loadUserProgramStatusFilter = function(regionSelector) {
        var request = {
            methodname: 'core_user_get_user_preferences',
            args: {
                name: 'tool_program_program_status_filter',
            }
        };
        ajax.call([request])[0]
            .then(function(p) {
                showProgramsWithStatus(regionSelector, p.preferences[0].value || 'all');
                return null;
            })
            .fail(Notification.exception);
    };

    /**
     * Show programs matching a status
     *
     * @param {String} regionSelector
     * @param {String} programfilter
     */
    var showProgramsWithStatus = function(regionSelector, programfilter) {
        M.util.js_pending('tool_program_program_filterprograms'); // Tell Behat to wait.
        const learningRegion = $(regionSelector + ' ' + SELECTOR.PROGRAMSREGION);
        learningRegion.hide();
        // We hide all programs and show the selected ones.
        filterPrograms(learningRegion, SELECTOR.ALL, PROGRAMSFILTER.HIDE);
        filterPrograms(learningRegion, SELECTOR[programfilter.toUpperCase()], PROGRAMSFILTER.SHOW);
        updateUserPreferences('tool_program_program_status_filter', programfilter);
        learningRegion.fadeIn('fast', function() {
            M.util.js_complete('tool_program_program_filterprograms');
        });
    };

    /**
     * Update program display
     *
     * @param {String} regionSelector
     * @param {String} displaytype
     */
    var updateProgramsDisplay = function(regionSelector, displaytype) {
        const learningRegion = $(regionSelector + ' ' + SELECTOR.PROGRAMSREGION);
        learningRegion.removeClass('viewcards').removeClass('viewlist').addClass(displaytype);
        learningRegion.removeClass('viewcards').removeClass('viewlist').addClass(displaytype);
        updateUserPreferences('tool_program_program_view_filter', displaytype);
    };

    /**
     * Update program sorting.
     *
     * @param {String} regionSelector
     * @param {String} sortorder
     */
    var updateProgramsSorting = function(regionSelector, sortorder) {
        const learningRegion = $(regionSelector + ' ' + SELECTOR.PROGRAMSREGION);
        let programs = learningRegion.find(SELECTOR.ALL);
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
        learningRegion.remove(SELECTOR.ALL).prepend(programs);
        // Update the user preferences.
        updateUserPreferences('tool_program_program_sort_filter', sortorder);
        // Add a little visual affect to show something is happening.
        learningRegion.hide().fadeIn('fast');
    };

    /**
     * Show matching programs.
     *
     * @param {String} regionSelector
     * @param {String} query string
     */
    var showMatchingPrograms = function(regionSelector, query) {
        const learningRegion = $(regionSelector + ' ' + SELECTOR.PROGRAMSREGION);
        learningRegion.find(SELECTOR.ALL).filter(function() {
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
         *
         * @param {string} regionSelector
         */
        init: function(regionSelector) {
            const learningRegion = $(regionSelector);

            // Maincontent anchor hack to mantain accesibility.
            $("#maincontent").detach().prependTo('#region-main');

            // Dispatch resize event to correctly recalculate visible courses for recently accessed courses block.
            $('body').on('shown shown.bs.tab', function() {
                window.dispatchEvent(new Event('resize'));
            });

            // Load correct filter saved in user preferences.
            if ($(SELECTOR.PROGRAMSREGION).data('showfilters') === 1) {
                loadUserProgramStatusFilter(regionSelector);
            }

            $(".section_expand").click(function(e) {
                e.preventDefault();
                $(this).next('.content').toggle();
                $(this).find('[class$="-icon-container"]').toggle();
            });

            learningRegion
                .on('click', '.enrol_to_course', function(e) {
                    e.preventDefault();
                    enrolUserToCourse($(this).data('courseid'), $(this).data('programid'));
                })
                // Event listener for statusses.
                .on('click', '.programs-status-filter .dropdown-item', function(e) {
                    e.preventDefault();
                    showProgramsWithStatus(regionSelector, $(this).data('value'));
                    checkNothingToDisplay(regionSelector);
                })
                // Event listener for the view type.
                .on('click', '.programs-display .dropdown-item', function(e) {
                     e.preventDefault();
                     updateProgramsDisplay(regionSelector, $(this).data('value'));
                })
                // Event listener for sorting programs.
                .on('click', '.programs-sorting .dropdown-item', function(e) {
                     e.preventDefault();
                     updateProgramsSorting(regionSelector, $(this).data('value'));
                })
                .on('keyup', '[data-region="programs-search-input"]', function() {
                    var query = $(this).val().toLowerCase().trim();
                    showMatchingPrograms(regionSelector, query);
                    checkNothingToDisplay(regionSelector);
                });
        }
    };
});
