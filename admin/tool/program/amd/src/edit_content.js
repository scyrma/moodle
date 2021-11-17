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
 * This module instantiates the functionality for edit program contents
 *
 * @module     tool_program/edit_content
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Mitxel Moriana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define([
    'jquery',
    'core/ajax',
    'core/templates',
    'core/fragment',
    'core/notification',
    'core/pubsub',
    'core/str',
    'core/sortable_list',
    'tool_wp/modal_form',
    'tool_wp/processing',
    'tool_wp/tabs',
    'tool_wp/notification',
    'tool_wp/events'
], function($, ajax, templates, fragment, notification, pubSub, str,
    SortableList, ModalForm, Processing, Tabs, WpNotification, WpEvents) {
    "use strict";

    var SELECTOR = {
        addCourseBtn: '[data-action="add-course"][data-set-id]',
        addSetBtn: '[data-action="add-set"][data-set-id]',
        completionAtLeastInput: 'input[name="completionatleast"]',
        completionCriteriaSelect: 'select[name="completioncriteria"]',
        container: '.tool-wp-table-tree',
        deleteCourseBtn: '.delete-course',
        deleteSetBtn: '.delete-set',
        editProgramViewRegion: '.wptabs',
        editSetsAndCoursesRegion: '[data-region="edit-content"]',
        inputCourseId: 'input[name="course-id"]',
        inputSetName: 'input[name="set-name"]',
        programItem: '.program-item',
        programItemChildren: '.tool-wp-children',
    };
    var SERVICES = {
        deleteCourse: 'tool_program_delete_course',
        deleteSet: 'tool_program_delete_set',
        moveProgramItem: 'tool_program_move_program_item',
        updateCompletion: 'tool_program_submit_edit_program_set_completion_form'
    };

    /**
     * EditContent class
     *
     * @constructor
     */
    function EditContent() {
        this._typingTimers = [];
        this._typingTimeout = 1000;
        this._addSetModal = null;
        this._addCoursesModal = null;
        this._init();
    }

    /**
     * Initiliase
     *
     * @private
     */
    EditContent.prototype._init = function() {
        this._initEventHandlers();
        this._initSortableList();
        Processing.init(SELECTOR.container);
    };

    /**
     * Delete a set
     *
     * @param {Number} setid
     * @return {Promise}
     * @private
     */
    EditContent.prototype._deleteSet = function(setid) {
        return ajax.call([{
            methodname: SERVICES.deleteSet,
            args: {
                setid: setid
            }
        }])[0];
    };

    /**
     * Remove a course
     *
     * @param {Number} programcourseid
     * @return {Promise}
     * @private
     */
    EditContent.prototype._deleteCourse = function(programcourseid) {
        return ajax.call([{
            methodname: SERVICES.deleteCourse,
            args: {
                programcourseid: programcourseid
            }
        }])[0];
    };

    /**
     * Update set completion criteria
     *
     * @param {Object} formData
     * @return {Promise}
     * @private
     */
    EditContent.prototype._updateSetCompletionCriteria = function(formData) {
        return ajax.call([{
            methodname: SERVICES.updateCompletion,
            args: {
                contextid: $(SELECTOR.editProgramViewRegion).data('contextid'),
                jsonformdata: JSON.stringify(formData)
            }
        }])[0];
    };

    /**
     * Move an item
     *
     * @param {Number} programid
     * @param {Number} itemid
     * @param {Number} isset
     * @param {Number} sourcesetid
     * @param {Number} targetsetid
     * @param {Number} nextitemid
     * @param {Number} nextisset
     * @return {Promise}
     * @private
     */
    EditContent.prototype._moveItem = function(programid, itemid, isset, sourcesetid, targetsetid, nextitemid, nextisset) {
        return ajax.call([{
            methodname: SERVICES.moveProgramItem,
            args: {
                programid: programid,
                itemid: itemid,
                isset: isset,
                sourcesetid: sourcesetid,
                targetsetid: targetsetid,
                nextitemid: nextitemid,
                nextisset: nextisset
            }
        }])[0];
    };

    /**
     * Initialise sortable list
     *
     * @private
     */
    EditContent.prototype._initSortableList = function() {
        var sort = new SortableList($(SELECTOR.programItemChildren));
        // Override the function for retrieving element name.
        sort.getElementName = function(element) {
            return $.Deferred().resolve(element.data('name'));
        };
        // Override the function for the list of elements, ita should reflect hierarchical structure.
        sort.getDestinationName = function(parentElement, afterElement) {
            var name = parentElement.parent().data('name'),
                istop = parentElement.parent().parent().hasClass('tool-wp-table-tree');
            if (!afterElement.length) {
                if (!istop) {
                    return str.get_string('assfirstchildof', 'tool_program', name);
                } else {
                    return str.get_string('movecontenttothetop', 'moodle');
                }
            } else {
                return str.get_string('movecontentafter', 'moodle', afterElement.data('name'));
            }
        };
    };

    /**
     * Add set modal
     *
     * @param {Number} parentSetId
     * @param {$} triggerElement
     * @private
     */
    EditContent.prototype._showAddSetModal = function(parentSetId, triggerElement) {
        var contextid = $(SELECTOR.editProgramViewRegion).data('contextid');
        this._addSetModal = new ModalForm({
            formClass: 'tool_program\\form\\edit_program_add_set_form',
            modalConfig: {title: str.get_string('addset', 'tool_program'), scrollable: false},
            contextId: contextid,
            args: {parentsetid: parentSetId},
            triggerElement: triggerElement
        });
        this._addSetModal.onSubmitSuccess = this._refreshProgramContents.bind(this);
    };

    /**
     * Add course modal
     *
     * @param {Number} parentSetId
     * @param {$} triggerElement
     * @private
     */
    EditContent.prototype._showAddCoursesModal = function(parentSetId, triggerElement) {
        var contextid = $(SELECTOR.editProgramViewRegion).data('contextid');
        this._addCoursesModal = new ModalForm({
            formClass: 'tool_program\\form\\edit_program_add_courses_form',
            modalConfig: {title: str.get_string('addcourses', 'tool_program'), scrollable: false},
            contextId: contextid,
            args: {parentsetid: parentSetId},
            triggerElement: triggerElement
        });
        this._addCoursesModal.onSubmitSuccess = this._refreshProgramContents.bind(this);
    };

    /**
     * Refresh tab
     *
     * @private
     */
    EditContent.prototype._refreshProgramContents = function() {
        Tabs.loadTab();
    };

    /**
     * Handler for event 'change' on SELECTOR.completionCriteriaSelect
     *
     * @param {Event} e
     * @private
     */
    EditContent.prototype._onCompletionCriteriaChanged = function(e) {
        M.util.js_pending('tool_program_completion_criteria_changed'); // Tell Behat to wait.
        var $completionTypeSelector = $(e.currentTarget),
            $thisForm = $completionTypeSelector.closest('form'),
            formData = $thisForm.serialize();
        pubSub.publish(WpEvents.LOADER_START);
        this._updateSetCompletionCriteria(formData).done(function() {
            pubSub.publish(WpEvents.LOADER_STOP);
            M.util.js_complete('tool_program_completion_criteria_changed');
        })
        .catch(notification.exception);
    };

    /**
     * Handler for event 'click' on SELECTOR.addSetBtn
     *
     * @param {Event} e
     * @private
     */
    EditContent.prototype._onAddSetClick = function(e) {
        e.preventDefault();
        var $addSetBtn = $(e.currentTarget),
            parentSetId = $addSetBtn.data('set-id');
        this._showAddSetModal(parentSetId, $addSetBtn);
    };

    /**
     * Handler for 'click' event on  SELECTOR.addCourseBtn
     *
     * @param {Event} e
     * @private
     */
    EditContent.prototype._onAddCourseClick = function(e) {
        e.preventDefault();
        var $addCourseBtn = $(e.currentTarget),
            parentSetId = $addCourseBtn.data('set-id');
        this._showAddCoursesModal(parentSetId, $addCourseBtn);
    };

    /**
     * Confirmation for deleting a set
     *
     * @param {String} setname
     * @param {Function} yesCallback
     * @param {Function} noCallback
     * @return {Promise}
     * @private
     */
    EditContent.prototype._askForDeleteSetConfirmation = function(setname, yesCallback, noCallback) {
        return str.get_strings([
            {'key': 'confirm'},
            {'key': 'confirmdeleteset', component: 'tool_program', param: setname},
            {'key': 'delete'},
            {'key': 'cancel'}
        ]).then(function(s) {
            return notification.confirm(s[0], s[1], s[2], s[3], yesCallback, noCallback);
        }).catch(notification.exception);
    };

    /**
     * Confirmation for deleting a course
     *
     * @param {String} coursename
     * @param {Function} yesCallback
     * @param {Function} noCallback
     * @return {Promise}
     * @private
     */
    EditContent.prototype._askForDeleteCourseConfirmation = function(coursename, yesCallback, noCallback) {
        return str.get_strings([
            {'key': 'confirm'},
            {'key': 'confirmdeletecourse', component: 'tool_program', param: coursename},
            {'key': 'remove'},
            {'key': 'cancel'}
        ]).then(function(s) {
            return notification.confirm(s[0], s[1], s[2], s[3], yesCallback, noCallback);
        }).catch(notification.exception);
    };

    /**
     * Handler for event 'click' on SELECTOR.deleteSetBtn
     *
     * @param {Event} e
     * @private
     */
    EditContent.prototype._onDeleteSetClick = function(e) {
        e.preventDefault();
        var $deleteSetBtn = $(e.currentTarget),
            $setItem = $deleteSetBtn.closest(SELECTOR.programItem),
            setid = $setItem.data('id');
        var setname = $deleteSetBtn.closest('.tool-wp-node-name')
            .find('.col-name .inplaceeditable a').text().trim();
        this._askForDeleteSetConfirmation(setname, function onSetDeletionConfirmed() {
            pubSub.publish(WpEvents.LOADER_START);
            this._deleteSet(setid)
                .then(function() {
                    pubSub.publish(WpEvents.LOADER_STOP);
                    return $setItem.remove();
                })
                .catch(notification.exception);
        }.bind(this));
    };

    /**
     * Handler for event 'click' on SELECTOR.deleteCourseBtn
     *
     * @param {Event} e
     * @private
     */
    EditContent.prototype._onDeleteCourseCLick = function(e) {
        e.preventDefault();
        var $deleteCourseBtn = $(e.currentTarget),
            $courseItem = $deleteCourseBtn.closest(SELECTOR.programItem),
            programcourseid = $courseItem.data('id');
        var coursename = $deleteCourseBtn.closest('.tool-wp-node-name')
            .find('.col-name').text().trim();
        this._askForDeleteCourseConfirmation(coursename, function onCourseDeletionConfirmed() {
            pubSub.publish(WpEvents.LOADER_START);
            this._deleteCourse(programcourseid)
                .then(function() {
                    pubSub.publish(WpEvents.LOADER_STOP);
                    return $courseItem.remove();
                })
                .catch(notification.exception);
        }.bind(this));
    };

    /**
     * Handler for event when dragging an item was finished
     *
     * @param {Event} evt
     * @param {Object} info
     * @private
     */
    EditContent.prototype._onSortableListItemDrop = function(evt, info) {
        evt.stopPropagation(); // Important for nested lists to prevent multiple targets.

        if (!info.dropped || !info.positionChanged) {
            return;
        }
        pubSub.publish(WpEvents.LOADER_START);
        var programid = $(SELECTOR.editProgramViewRegion).data('id'),
            itemid = info.element.data('id'),
            isset = !!info.element.data('isset'),
            sourcesetid = info.sourceList.parent(SELECTOR.programItem).data('id'),
            targetsetid = info.targetList.parent(SELECTOR.programItem).data('id'),
            nextitemid = info.targetNextElement.data('id') || 0,
            nextisset = !!info.targetNextElement.data('isset');
        this._moveItem(programid, itemid, isset, sourcesetid, targetsetid, nextitemid, nextisset)
            .always(function() {
                pubSub.publish(WpEvents.LOADER_STOP);
            })
            .catch(function(err) {
                Tabs.loadTab();
                WpNotification.addNotification({
                    message: err.message,
                    type: 'error'
                });
            });
    };

    /**
     * Handler for 'keyup' on SELECTOR.completionAtLeastInput
     *
     * @param {Event} e
     * @private
     */
    EditContent.prototype._onCompletionAtLeastInputKeyUp = function(e) {
        var inputId = $(e.currentTarget).data('formid');
        clearTimeout(this._typingTimers[inputId]);
        this._typingTimers[inputId] = setTimeout(this._onCompletionCriteriaChanged.bind(this, e), this._typingTimeout);
    };

    /**
     * Handler for 'keydown' on SELECTOR.completionAtLeastInput
     *
     * @param {Event} e
     * @private
     */
    EditContent.prototype._onCompletionAtLeastInputKeyDown = function(e) {
        var inputId = $(e.currentTarget).data('formid');
        clearTimeout(this._typingTimers[inputId]);
    };

    /**
     * Initialise event handlers
     *
     * @private
     */
    EditContent.prototype._initEventHandlers = function() {
        $(SELECTOR.editSetsAndCoursesRegion)
            .on('click', SELECTOR.addSetBtn, this._onAddSetClick.bind(this))
            .on('change', SELECTOR.completionCriteriaSelect, this._onCompletionCriteriaChanged.bind(this))
            .on('keyup', SELECTOR.completionAtLeastInput, this._onCompletionAtLeastInputKeyUp.bind(this))
            .on('keydown', SELECTOR.completionAtLeastInput, this._onCompletionAtLeastInputKeyDown.bind(this))
            .on('click', SELECTOR.addCourseBtn, this._onAddCourseClick.bind(this))
            .on('click', SELECTOR.deleteSetBtn, this._onDeleteSetClick.bind(this))
            .on('click', SELECTOR.deleteCourseBtn, this._onDeleteCourseCLick.bind(this))
            .on('sortablelist-drop', SELECTOR.programItem, this._onSortableListItemDrop.bind(this));
    };

    return EditContent;
});
