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
 * This module instantiates the functionality to load form to edit details.
 *
 * @module     tool_program/edit_details
 * @package    tool_program
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

"use strict";

import ModalForm from 'tool_wp/modal_form';
import {get_string as getString} from 'core/str';

/** @type {Object} The list of selectors for the program area. */
const Selectors = {
    EditDetails: "[data-action='editdetails']",
    EditDetailsSwitch: "[data-action='editdetailsswitchtenant'][data-redirect]",
};

const init = () => {

    // Element only exist on current program tenants.
    const editDetailsElement = document.querySelector(Selectors.EditDetails);
    if (editDetailsElement) {
        editDetailsElement.addEventListener('click', (event) => {
            event.preventDefault();
            editDetailsHandler(event);
        });
    }

    // Element only exist on shared programs in other tenants. Handles edit details event for the 'Edit in Shared space' button.
    const EditDetailsSharedSpaceElement = document.querySelector(Selectors.EditDetailsSwitch);
    if (EditDetailsSharedSpaceElement) {
        EditDetailsSharedSpaceElement.addEventListener('click', (event) => {
            event.preventDefault();
            window.location.href = EditDetailsSharedSpaceElement.dataset.redirect;
        });
    }
};

/**
 * Popup to edit program details
 *
 * @param {Event} event
 * @param {Number} programid
 * @param {Promise} title
 * @return {ModalForm}
 * @private
 */
const showDetailsModal = (event, programid, title) => {
    return new ModalForm({
        formClass: 'tool_program\\form\\edit_program_details_form',
        args: {id: programid},
        modalConfig: {title: title},
        triggerElement: event.currentTarget,
        saveButtonText: getString('save')
    });
};

/**
 * Handles edit details event for the program 'Edit details' button
 *
 * @param {Event} event
 * @private
 */
const editDetailsHandler = (event) => {
    const programid = event.currentTarget.dataset.programid;
    const programname = event.currentTarget.dataset.programname;
    const title = getString('editprogram', 'tool_program', programname);
    const modal = showDetailsModal(event, programid, title);
    modal.onSubmitSuccess = () => {
        window.location.reload(false);
    };
};

export default {
    init: init
};