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
 * This module instantiates the functionality for report builder audiences.
 *
 * @module     tool_reportbuilder/reportbuilder_audience
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

"use strict";

import Templates from 'core/templates';
import Notification from 'core/notification';
import Pending from 'core/pending';
import {get_strings as getStrings} from 'core/str';
import DynamicForm from 'core_form/dynamicform';
import Ajax from 'core/ajax';

const SELECTORS = {
    AUDIENCES_MENU_ITEMS: '#audiences-menu .list-group-item[data-configclass]',
    AUDIENCES_CONTAINER: '#audiences-container',
    EDIT_INSTANCE: '[data-action="edit-instance"]',
    DELETE_INSTANCE: '[data-action="delete-instance"]',
    SCROLLER: '#scroller',
    SCROLLER_INNER: '#scroller-inner',
    EMPTY_MESSAGE: '[data-region=no-instances-message]',
    NOT_SAVED_LABEL: '#form-instance-notsaved-label',
    CARD: '.card0',
    DESCRIPTION_CONTAINER: '.description-container',
    FORM_CONTAINER: '.form-container',
    FORM_REGION: '[data-region="audience-form"]'
};

let reportid = 0;

/**
 * Add configuration instance form.
 *
 * @param {Element} menuItemNode
 */
const addInstanceForm = (menuItemNode) => {
    const pendingPromise = new Pending('tool_reportbuilder/addInstanceForm');

    // Ensure our menuItemNode is the actual menu item itself, and not a child node.
    menuItemNode = menuItemNode.closest(SELECTORS.AUDIENCES_MENU_ITEMS);

    let params = {
        classname: menuItemNode.dataset.configclass,
        reportid: reportid,
    };

    let container = document.querySelector(SELECTORS.AUDIENCES_CONTAINER),
        wrapper = '',
        notsavedlabel = '';
    getStrings([
        {'key': 'delete', component: 'moodle'},
        {'key': 'edit', component: 'moodle'},
    ])
        .then(([deletestr, editstr]) => {
            let numberofcards = document.querySelectorAll('.instance-card').length;
            // Put together context and render the template.
            params.title = menuItemNode.title;
            params.form = '';
            params.elementid = Math.random().toString().substr(2, 10);
            params.instanceid = 0;
            params.deletelabel = deletestr;
            params.editlabel = editstr;
            params.notsavedlabel = 'notsaved';
            params.canedit = true;
            params.candelete = true;
            params.showormessage = (numberofcards > 0);
            return Templates.render('tool_reportbuilder/audience_form_instance', params);
        })
        .then((html) => {
            let cardNodefull = new DOMParser().parseFromString(html, "text/html");
            let cardNode = cardNodefull.querySelector(SELECTORS.CARD);
            // Disable edit button and add instance to container.
            cardNode.querySelector(SELECTORS.EDIT_INSTANCE).classList.add('disabled');
            container.appendChild(cardNode);
            wrapper = cardNode.querySelector(SELECTORS.FORM_CONTAINER);
            // Store label as form will overwrite wrapper content.
            notsavedlabel = wrapper.querySelector(SELECTORS.NOT_SAVED_LABEL);
            let form = initInstanceForm(cardNode);
            document.querySelector(SELECTORS.EMPTY_MESSAGE).classList.add('hidden');
            return form.load(params);
        })
        .then(() => {
            // Add label and show form.
            wrapper.prepend(notsavedlabel);
            wrapper.style.display = 'block';

            // Add scrolling if needed.
            let scroller = container.closest(SELECTORS.SCROLLER);
            let scrollerInner = container.closest(SELECTORS.SCROLLER_INNER);
            if (container.offsetHeight > scrollerInner.offsetHeight) {
                scroller.animate({scrollTop: container.offsetHeight}, 200);
            }
            pendingPromise.resolve();
            return null;
        })
        .catch(Notification.exception);
};

/**
 * Get instance of ajax_form
 *
 * @param {Element} cardNode
 * @return {DynamicForm}
 */
const initInstanceForm = (cardNode) => {
    const formClass = 'tool_reportbuilder\\form\\audience_instance';
    const wrapper = cardNode.querySelector(SELECTORS.FORM_CONTAINER);
    let form = new DynamicForm(wrapper, formClass);
    form.addEventListener(form.events.FORM_SUBMITTED, (data) => {
        submitInstanceForm(cardNode, data.detail);
    });
    form.addEventListener(form.events.FORM_CANCELLED, () => {
        cancelInstanceForm(cardNode);
    });
    return form;
};

/**
 * Close card.
 *
 * @param {Element} cardNode
 */
const closeCard = (cardNode) => {
    // Remove the form-container (with all the event listeners attached to it), and create it again.
    cardNode.querySelector(SELECTORS.FORM_CONTAINER).remove();
    let div = document.createElement('div');
    div.className = 'form-container';
    cardNode.querySelector(SELECTORS.FORM_REGION).appendChild(div);
    cardNode.querySelector(SELECTORS.DESCRIPTION_CONTAINER).style.display = 'block';
    cardNode.querySelector(SELECTORS.EDIT_INSTANCE).classList.remove('disabled');
};

/**
 * Submit form for given instance type.
 *
 * @param {Element} cardNode
 * @param {Object} data data returned from form process() method
 */
const submitInstanceForm = (cardNode, data) => {
    if (cardNode.dataset.instanceid == 0) {
        // This is new instance. Set data attribute.
        cardNode.dataset.instanceid = data.instanceid;
    }
    cardNode.querySelector(SELECTORS.DESCRIPTION_CONTAINER).innerHTML = data.description;
    closeCard(cardNode);
};

/**
 * Cancel form for given instance type.
 *
 * @param {Element} cardNode
 */
const cancelInstanceForm = (cardNode) => {
    const instanceid = cardNode.dataset.instanceid;
    if (instanceid > 0) {
        closeCard(cardNode);
    } else {
        // Removing new unsaved from instance.
        removeCardNode(cardNode);
    }
};

/**
 * Show configuration instance form.
 *
 * @param {Element} cardNode
 */
const showInstanceForm = (cardNode) => {
    const pendingPromise = new Pending('tool_reportbuilder/reportbuilder_audience:showInstanceForm');
    const formdata = {
        reportid: reportid,
        id: cardNode.dataset.instanceid,
        classname: cardNode.dataset.classname
    };
    let wrapper = cardNode.querySelector(SELECTORS.FORM_CONTAINER);
    let form = initInstanceForm(cardNode);

    cardNode.querySelector(SELECTORS.EDIT_INSTANCE).classList.add('disabled');
    form.load(formdata)
        .then(() => {
            cardNode.querySelector(SELECTORS.DESCRIPTION_CONTAINER).style.display = 'none';
            wrapper.style.display = 'block';
            pendingPromise.resolve();
            return null;
        })
        .catch(Notification.exception);
};

/**
 * Delete given instance type.
 *
 * @param {Element} cardNode
 */
const deleteInstance = (cardNode) => {
    let instanceid = cardNode.dataset.instanceid;
    if (instanceid > 0) {
        // If we have instance id, perform deletion.
        const pendingPromise = new Pending('tool_reportbuilder/reportbuilder_audience:deleteInstance');
        getStrings([
            {key: 'confirm', component: 'moodle'},
            {key: 'deleteaudience', component: 'tool_reportbuilder', param: cardNode.dataset.title},
            {key: 'delete', component: 'moodle'},
            {key: 'cancel', component: 'moodle'}
        ]).then(([confirmstr, deletemsg, deletestr, cancelstr]) => {
            Notification.confirm(confirmstr, deletemsg, deletestr, cancelstr, () => {
                Ajax.call([
                    {methodname: 'tool_reportbuilder_delete_audience', args: {instanceid: instanceid}}
                ])[0]
                    .then(() => {
                        // Remove the form element with a visual effect.
                        removeCardNode(cardNode);
                        return null;
                    })
                    .catch(Notification.exception);
            });
            pendingPromise.resolve();
            return null;
        }).catch(Notification.exception);
    } else {
        // Removing new unsaved from instance.
        removeCardNode(cardNode);
    }
};

/**
 * Removes the node
 *
 * @param {Element} cardNode
 */
const removeCardNode = (cardNode) => {
    cardNode.remove();
    const instancecard = document.querySelector(SELECTORS.AUDIENCES_CONTAINER + ' .instance-card');
    if (!instancecard) {
        document.querySelector(SELECTORS.EMPTY_MESSAGE).classList.remove('hidden');
    }

    // Always check that the first card on the list does not contain the OR separator.
    const allcards = document.querySelectorAll('.instance-card');
    if (allcards.length > 0) {
        const orseparator = allcards[0].querySelector('.audience-separator');
        if (orseparator) {
            orseparator.remove();
        }
    }
};

let initialized = false;

/**
 * Initialise audiences tab.
 *
 * @param {Number} id
 */
const init = (id) => {
    reportid = id;

    if (initialized) {
        // We already added the event listeners (can be called multiple times by mustache template).
        return;
    }

    document.addEventListener('click', (event) => {
        // Show instance form - edit icon.
        const showInstance = event.target.closest(SELECTORS.EDIT_INSTANCE);
        if (showInstance) {
            event.preventDefault();
            showInstanceForm(event.target.closest(SELECTORS.CARD));
        }

        // Add instance form.
        const addInstance = event.target.closest(SELECTORS.AUDIENCES_MENU_ITEMS);
        if (addInstance) {
            event.preventDefault();
            addInstanceForm(event.target);
        }

        // Delete instance - delete icon.
        const deleteform = event.target.closest(SELECTORS.DELETE_INSTANCE);
        if (deleteform) {
            event.preventDefault();
            deleteInstance(event.target.closest(SELECTORS.CARD));
        }
    });

    initialized = true;
};

export default {
    init: init
};
