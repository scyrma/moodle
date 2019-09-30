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
 * Javascript for course format workplace list.
 *
 * @package    format_wplist
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 <bas@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(
[
    'jquery',
    'core/sortable_list',
    'core/notification',
    'core/custom_interaction_events',
    'core/ajax',
    'core/str'
],
function(
    $,
    Sortablewplist,
    Notification,
    CustomEvents,
    Ajax,
    Str
) {

    var SELECTORS = {
        SECTION_CONTAINER: '[data-region="section-container"]',
        SECTION: '[data-region="section"]',
        MODULES_CONTAINER: '[data-region="course-modules"]',
        MODULE: '[data-region="module"]',
        COMPLETIONCHECKS: '[data-region="completioncheck"]',
        COMPLETION_ON: '[data-region="checkon"]',
        COMPLETION_OFF: '[data-region="checkoff"]',
        EXPAND_TOGGLE: '[data-toggle="collapse"]',
        EXPAND_SECTIONS: '[data-action="expand"]',
        EXPAND_SECTIONS_OPEN: '[data-region="collapsed-open"]',
        EXPAND_SECTIONS_CLOSED: '[data-region="collapsed-closed"]',
        EXPAND_SECTIONS_CONTENT: '[data-region="sectioncollapse"]',
        COMPLETION_CONTAINER: '[data-actions="availability"]',
        COMPLETION_INFO: '[data-region="availabilityinfo"]'

    };

    /**
     * Move a course section.
     *
     * @param {Object} args Arguments to pass to webservice
     *
     * Valid args are:t
     * int sectionnumber number of section to move
     * int sectiontarget number of section to position after
     * int courseid.     id of course this module belongs to
     *
     * @return {promise} Resolved with void or array of warnings
     */
    var moveSection = function(args) {
        var request = {
            methodname: 'format_wplist_move_section',
            args: args
        };
        var promise = Ajax.call([request])[0];
        promise.fail(Notification.exception);
        return promise;
    };

    /**
     * Move a course module.
     *
     * @param {Object} args Arguments to pass to webservice
     *
     * Valid args are:t
     * int moduleid      id of module to move
     * int moduletarget  id of module to position after
     * int sectionnumber number of section to move module to
     * int courseid.     id of course this section belongs to
     *
     * @return {promise} Resolved with void or array of warnings
     */
    var moveModule = function(args) {
        var request = {
            methodname: 'format_wplist_move_module',
            args: args
        };
        var promise = Ajax.call([request])[0];
        promise.fail(Notification.exception);
        return promise;
    };

    /**
     * Check the completion checkbox for self-completion.
     *
     * @param {Object} args Arguments to pass to webservice
     *
     * Valid args are:t
     * int moduleid      id of module to complet
     * int targetstate   set completion to on (1) or off (0)
     *
     * @return {promise} Resolved with void or array of warnings
     */
    var checkCompletion = function(args) {
        var request = {
            methodname: 'format_wplist_module_completion',
            args: args
        };
        var promise = Ajax.call([request])[0];
        promise.fail(Notification.exception);
        return promise;
    };

    /**
     * Toggle the completion icon for self completion.
     *
     * @param  {Object} checkbox Container checkbox dom element.
     * @param  {Boolean} checked
     */
    var checkCompletionIcon = function(checkbox, checked) {
        if (!checked) {
            checkbox.attr('data-checked', 1);
            checkbox.attr('data-targetstate', 0);
            checkbox.find(SELECTORS.COMPLETION_ON).removeClass('hidden');
            checkbox.find(SELECTORS.COMPLETION_OFF).addClass('hidden');
        } else {
            checkbox.attr('data-checked', 0);
            checkbox.attr('data-targetstate', 1);
            checkbox.find(SELECTORS.COMPLETION_ON).addClass('hidden');
            checkbox.find(SELECTORS.COMPLETION_OFF).removeClass('hidden');
        }
    };

    /**
     * Update the section completion progress bar.
     *
     * @param {Object} root The course format root container element.
     * @param {Number} sectionnumber Section ID.
     * @param {Number} targetstate New state.
     */
    var updateSectionCompletion = function(root, sectionnumber, targetstate) {
        var sectionprogress = root.find('#sectionprogress-' + sectionnumber);
        var completedmodules = sectionprogress.attr('data-completed-modules');
        var completionmodules = sectionprogress.attr('data-completion-modules');
        if (targetstate === 1) {
            completedmodules++;
        } else {
            completedmodules--;
        }
        sectionprogress.attr('data-completed-modules', completedmodules);
        var newCompletionPercentage = 100 * (completedmodules / completionmodules);
        sectionprogress.css('width', newCompletionPercentage + '%');
    };

    /**
     * Listen to, and handle events for the workplace list format.
     *
     * @param {Object} root The course format root container element.
     * @param {Number} contextid Course context ID.
     */
    var registerEventListeners = function(root, contextid) {
        CustomEvents.define(root, [
            CustomEvents.events.activate
        ]);

        root.on(CustomEvents.events.activate, SELECTORS.EXPAND_SECTIONS, function(e) {
            var expand = $(e.target).closest(SELECTORS.EXPAND_SECTIONS);
            var openmsg = expand.find(SELECTORS.EXPAND_SECTIONS_OPEN);
            var closedmsg = expand.find(SELECTORS.EXPAND_SECTIONS_CLOSED);
            var open = parseInt(expand.attr('data-expanded'));
            if (open === 0) {
                $(SELECTORS.EXPAND_SECTIONS_CONTENT).each(function() {
                    $(this).addClass('show');
                });
                $(SELECTORS.EXPAND_TOGGLE).each(function() {
                    $(this).removeClass('collapsed');
                });
                openmsg.removeClass('hidden');
                closedmsg.addClass('hidden');
                expand.attr('data-expanded', 1);
            } else {
                $(SELECTORS.EXPAND_SECTIONS_CONTENT).each(function() {
                    $(this).removeClass('show');
                });
                $(SELECTORS.EXPAND_TOGGLE).each(function() {
                    $(this).addClass('collapsed');
                });
                openmsg.addClass('hidden');
                closedmsg.removeClass('hidden');
                expand.attr('data-expanded', 0);
            }
        });

        $(SELECTORS.EXPAND_SECTIONS_CONTENT).on('hidden.bs.collapse', function() {
            var sectionid = $(this).attr('data-sectionid');
            var isaccordion = $(this).attr('data-isaccordion');
            storeSectionPreference(sectionid, isaccordion, false, contextid);
        });

        $(SELECTORS.EXPAND_SECTIONS_CONTENT).on('shown.bs.collapse', function() {
            var sectionid = $(this).attr('data-sectionid');
            var isaccordion = $(this).attr('data-isaccordion');
            storeSectionPreference(sectionid, isaccordion, true, contextid);
        });

        // Listen for changes on completion.
        root.on(CustomEvents.events.activate, SELECTORS.COMPLETIONCHECKS, function(e) {
            var cc = $(e.target).closest(SELECTORS.COMPLETIONCHECKS);
            var moduleid = parseInt(cc.attr('data-module'));
            var targetstate = parseInt(cc.attr('data-targetstate'));
            var courseid = parseInt(cc.attr('data-courseid'));
            var checked = parseInt(cc.attr('data-checked'));
            var sectionnumber = parseInt(cc.attr('data-sectionnumber'));

            var args = {
                moduleid: moduleid,
                targetstate: targetstate,
                courseid: courseid
            };

            checkCompletion(args).then(function() {
                checkCompletionIcon(cc, checked);
                updateSectionCompletion(root, sectionnumber, targetstate);
                return null;
            })
            .fail(Notification.exception);
        });

        // Variables for moving sections.
        var sectionsContainer = root.find(SELECTORS.SECTION_CONTAINER);

        var sections = root.find(SELECTORS.SECTION);

        var courseid = sectionsContainer.attr('data-courseid');

        var getSectionName = function(element) {
            return element.find('h3.sectionname .inplaceeditable').text();
        };
        var getModuleName = function(element) {
            return element.find('.cmname .inplaceeditable').text();
        };
        var findClosestSection = function(element) {
            return element.closest('[data-region="section"][data-sectionnumber]');
        };

        var sectionsSortable = new Sortablewplist(sectionsContainer, {moveHandlerSelector: '.movesection > [data-drag-type=move]'});
        sectionsSortable.getElementName = function(element) {
            return $.Deferred().resolve(getSectionName(element));
        };

        // Variables for moving modules.
        var modulesContainers = root.find(SELECTORS.MODULES_CONTAINER);

        var modulesSortable = new Sortablewplist(modulesContainers, {moveHandlerSelector: '.movemodule > [data-drag-type=move]'});
        modulesSortable.getElementName = function(element) {
            return $.Deferred().resolve(getModuleName(element));
        };
        modulesSortable.getDestinationName = function(parentElement, afterElement) {
            if (!afterElement.length) {
                return Str.get_string('totopofsection', 'moodle',
                        getSectionName(findClosestSection(parentElement)));
            } else {
                // We check that is not the empty section message.
                if ($(afterElement).find('.cmname').length === 0) {
                    return null;
                } else {
                    return Str.get_string('movecontentafter', 'moodle', getModuleName(afterElement));
                }
            }
        };

        sections.on(Sortablewplist.EVENTS.DROP, function(e, info) {
            e.stopPropagation();
            var sectionnumber,
                args;
            if (info.positionChanged) {
                if (info.element.attr('data-sectionnumber')) {
                    if (info.targetNextElement && info.targetNextElement.attr('data-sectionnumber') === "0") {
                        // Can not move before general section.
                        sectionsSortable.moveElement(info.sourceList, info.sourceNextElement);
                        return;
                    }
                    sectionnumber = info.element.attr('data-sectionnumber');
                    var sectiontarget = info.targetNextElement.attr('data-sectionnumber');
                    args = {
                        sectionnumber: sectionnumber,
                        sectiontarget: sectiontarget,
                        courseid: courseid
                    };
                    moveSection(args).then(function() {
                        info.element.attr('data-sectionnumber', sectiontarget);
                        info.targetNextElement.attr('data-sectionnumber', sectionnumber);
                        return null;
                    }).catch(Notification.exception);
                }
                if (info.element.attr('data-module')) {
                    var moduleid = info.element.attr('data-module');
                    var moduletarget = info.targetNextElement.attr('data-module');
                    sectionnumber = findClosestSection(info.targetList).attr('data-sectionnumber');
                    args = {
                        moduleid: moduleid,
                        moduletarget: moduletarget,
                        sectionnumber: sectionnumber,
                        courseid: courseid
                    };
                    if (typeof moduleid !== 'undefined' && moduleid !== 0) {
                        moveModule(args).catch(Notification.exception);
                    }
                }
            }
        });

        // Count the number of modules in each Modules container.
        var countmodules = function() {
            root.find(SELECTORS.SECTION).each(function() {
                var modulesContainer = $(this).find(SELECTORS.MODULES_CONTAINER);
                var nummodules = modulesContainer.children().length - 1;

                modulesContainer.attr('data-nummodules', nummodules);

                if (nummodules === 0) {
                    modulesContainer.addClass('nomodules');
                } else {
                    modulesContainer.removeClass('nomodules');
                }
            });
        };

        countmodules();

        sections.on(Sortablewplist.EVENTS.DRAG, function(e, info) {
            if (info.element.attr('data-module')) {
                root.find(SELECTORS.SECTION).each(function() {
                    $(this).removeClass('movemodule');
                });

                var oldSectionModules = findClosestSection(info.sourceList).find(SELECTORS.MODULES_CONTAINER);
                var numoldmodules = parseInt(oldSectionModules.attr('data-nummodules'));
                if (numoldmodules === 1) {
                    oldSectionModules.addClass('nomodules');
                }

                var newSection = findClosestSection(info.targetList);
                newSection.addClass('movemodule');
            }
        });

        sections.on(Sortablewplist.EVENTS.DRAGEND, function(e, info) {
            if (info.element.attr('data-module')) {
                countmodules();
            }
        });
    };

    /**
     * Store the user display preference for this section
     *
     * @param {Number} sectionid Section ID.
     * @param {Boolean} isaccordion
     * @param {Boolean} opened = true or closed = false.
     * @param {Number} contextid Course context ID.
     */
    var storeSectionPreference = function(sectionid, isaccordion, opened, contextid) {

        var requestget = {
            methodname: 'core_user_get_user_preferences',
            args: {
                name: 'format_wplist_opensections_' + contextid
            }
        };

        var sections = [];

        Ajax.call([requestget])[0]
            .then(function(p) {
                if (isaccordion) {
                    if (opened) {
                        sections = [sectionid];
                    } else {
                        sections = [];
                    }
                } else {
                    if (p.preferences.length && p.preferences[0].value) {
                        sections = JSON.parse(p.preferences[0].value);
                    }
                    var index = sections.indexOf(sectionid);
                    if (opened) {
                        if (index === -1) {
                            sections.push(sectionid);
                        }
                    } else {
                        if (index > -1) {
                            sections.splice(index, 1);
                        }
                    }
                }
                var requestset = {
                    methodname: 'core_user_update_user_preferences',
                    args: {
                        preferences: [{
                            type: 'format_wplist_opensections_' + contextid,
                            value: JSON.stringify(sections)
                        }]
                    }
                };
                return Ajax.call([requestset])[0];
            })
        .fail(Notification.exception);
    };

    /**
     * Initialise all of the modules for the workplace list course format.
     *
     * @param {object} root The root element for the workplace list course format.
     * @param {Number} contextid Course context ID.
     */
    var init = function(root, contextid) {
        root = $(root);
        registerEventListeners(root, contextid);
    };

    return {
        init: init
    };
});
