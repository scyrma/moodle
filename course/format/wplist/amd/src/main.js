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
 * Javascript for course format workplace list.
 *
 * @package    format_wplist
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 <bas@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(
[
    'jquery',
    'core/sortable_list',
    'core/notification',
    'core/custom_interaction_events',
    'core/ajax',
    'core/str',
    'core/templates'
],
function(
    $,
    Sortablewplist,
    Notification,
    CustomEvents,
    Ajax,
    Str,
    Templates
) {

    var SELECTORS = {
        SECTION_CONTAINER: '[data-region="section-container"]',
        SECTION: '[data-region="section"]',
        MODULES_CONTAINER: '[data-region="course-modules"]',
        MODULE: '[data-region="module"]',
        COMPLETIONCHECKS: '[data-region="completioncheck"]',
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
        var newCompletionPercentage = 100 * (completedmodules / completionmodules);
        Str.get_string('section_completion', 'format_wplist', newCompletionPercentage).done(function(s) {
            sectionprogress.attr('title', s);
            sectionprogress.attr('data-completed-modules', completedmodules);
            sectionprogress.css('width', newCompletionPercentage + '%');
        });
    };

    /**
     * Update the section collapse button.
     * @param  {Object} root The course format root container element.
     * @param  {Number} open Show state for all containers open.
     * @param  {Bool} auto Check if containers open.
     * @return {Bool} return early if auto and not all sections closed or open.
     */
    var updateSectionCollapse = function(root, open, auto) {
        if (auto) {
            if (root.find('.sectioncontent.collapse.show').length === 0) {
                open = 1;
            } else if (root.find('.sectioncontent.collapse:not(.show)').length === 0) {
                open = 0;
            } else {
                // Return early since not all sections are open or closed.
                return false;
            }
        }

        var expand = root.find(SELECTORS.EXPAND_SECTIONS);
        var openmsg = expand.find(SELECTORS.EXPAND_SECTIONS_OPEN);
        var closedmsg = expand.find(SELECTORS.EXPAND_SECTIONS_CLOSED);
        if (open === 0) {
            openmsg.removeClass('hidden');
            closedmsg.addClass('hidden');
            expand.attr('data-expanded', 1);
        } else {
            openmsg.addClass('hidden');
            closedmsg.removeClass('hidden');
            expand.attr('data-expanded', 0);
        }
        return true;
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
            var open = parseInt($(e.target).closest(SELECTORS.EXPAND_SECTIONS).attr('data-expanded'));
            if (open === 0) {
                $(SELECTORS.EXPAND_SECTIONS_CONTENT).each(function() {
                    $(this).addClass('show');
                });
                $(SELECTORS.EXPAND_TOGGLE).each(function() {
                    $(this).removeClass('collapsed');
                });
                updateSectionCollapse(root, 0, false);
                storeSectionPreference(root, 0, true, true, contextid);
            } else {
                $(SELECTORS.EXPAND_SECTIONS_CONTENT).each(function() {
                    $(this).removeClass('show');
                });
                $(SELECTORS.EXPAND_TOGGLE).each(function() {
                    $(this).addClass('collapsed');
                });
                updateSectionCollapse(root, 1, false);
                storeSectionPreference(root, 0, true, false, contextid);
            }
        });

        $(SELECTORS.EXPAND_SECTIONS_CONTENT).on('hidden.bs.collapse', function() {
            var sectionid = $(this).data('sectionid');
            var sectionnumber = $(this).data('sectionnumber');
            var isaccordion = $(this).data('isaccordion');
            var sectionname = $(this).data('sectionname');
            updateSectionCollapse(root, 1, true);
            Str.get_string('expandsection', 'format_wplist', sectionname).done(function(s) {
                $('.course-section-toggle[data-target="#sectioncontent-' + sectionnumber + '"]').attr('title', s);
                storeSectionPreference(root, sectionid, isaccordion, false, contextid);
            });
        });

        $(SELECTORS.EXPAND_SECTIONS_CONTENT).on('shown.bs.collapse', function() {
            var sectionid = $(this).data('sectionid');
            var sectionnumber = $(this).data('sectionnumber');
            var isaccordion = $(this).data('isaccordion');
            var sectionname = $(this).data('sectionname');
            updateSectionCollapse(root, 0, true);
            Str.get_string('collapsesection', 'format_wplist', sectionname).done(function(s) {
                $('.course-section-toggle[data-target="#sectioncontent-' + sectionnumber + '"]').attr('title', s);
                storeSectionPreference(root, sectionid, isaccordion, true, contextid);
            });
        });

        // Listen for changes on completion.
        root.on(CustomEvents.events.activate, SELECTORS.COMPLETIONCHECKS, function(e) {
            var cc = $(e.target).closest(SELECTORS.COMPLETIONCHECKS);
            var moduleid = parseInt(cc.attr('data-module'));
            var targetstate = parseInt(cc.attr('data-targetstate'));
            var courseid = parseInt(cc.attr('data-courseid'));
            var sectionnumber = parseInt(cc.attr('data-sectionnumber'));
            var reloadOnChange = parseInt(cc.attr('data-reloadonchange'));

            var args = {
                moduleid: moduleid,
                targetstate: targetstate,
                courseid: courseid
            };

            checkCompletion(args).then(function(html) {
                if (reloadOnChange) {
                    window.location.reload();
                }
                updateSectionCompletion(root, sectionnumber, targetstate);
                Templates.replaceNode(cc, html.completionicon, '');
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
                sections.each(function() {
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

                var newSection = findClosestSection(info.element);
                newSection.removeClass('movemodule');
            }
        });
    };

    /**
     * Store the user display preference for this section
     *
     * @param {Object} root The course format root container element.
     * @param {Number} sectionid Section ID.
     * @param {Boolean} isaccordion
     * @param {Boolean} opened = true or closed = false.
     * @param {Number} contextid Course context ID.
     */
    var storeSectionPreference = function(root, sectionid, isaccordion, opened, contextid) {

        var requestget = {
            methodname: 'core_user_get_user_preferences',
            args: {
                name: 'format_wplist_opensections_' + contextid
            }
        };

        var sections = [];

        Ajax.call([requestget])[0]
            .then(function(p) {
                if (sectionid === 0 && opened) {
                    sections = [];
                    root.find('.sectioncontent.collapse').each(function() {
                        var sectionid = $(this).data('sectionid');
                        sections.push(sectionid);
                    });
                } else if (isaccordion) {
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
