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
 * Report builder card view settings management
 *
 * @module      tool_reportbuilder/reportbuilder_cardsettings
 * @package     tool_reportbuilder
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @author      2021 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

import DynamicForm from 'core_form/dynamicform';
import * as Toast from 'core/toast';
import {get_string as getString} from "core/str";
import {subscribe as subscribe} from 'core/pubsub';
import Selectors from "./local/report/selectors";
import Events from "./reportbuilder_events";
import $ from 'jquery';

const reportSelectors = {
    reportCardView: '[data-region="report-cardview"]',
    visibility: '[name="visibility"]'
};

/**
 * Initialise module
 */
export const init = () => {
    const cardViewFormContainer = document.querySelector(reportSelectors.reportCardView);
    const cardViewForm = new DynamicForm(cardViewFormContainer, '\\tool_reportbuilder\\form\\cardview');

    cardViewForm.addEventListener(cardViewForm.events.FORM_SUBMITTED, (e) => {
        e.preventDefault();
        getString('changessaved')
            .then(message => {
                $(Selectors.tableRegion).trigger(Events.RELOADTABLEWITHOUTPAGINATION);
                Toast.add(message, {type: 'success'});
                return null;
            })
            .catch(null);
    });

    // Update visibility dropdown values each time a column is added to the custom report.
    subscribe('reportbuilder:tablecolumnadded', () => {
        const dropdown = cardViewFormContainer.querySelector(reportSelectors.visibility);
        const element = document.createElement("option");
        const newValue = dropdown.options.length + 1;
        element.text = newValue;
        element.value = newValue;
        dropdown.append(element);
    });

    // Update visibility dropdown values each time a column is removed from the custom report.
    subscribe('reportbuilder:tablecolumnremoved', () => {
        const dropdown = cardViewFormContainer.querySelector(reportSelectors.visibility);
        const newValue = dropdown.options.length - 1;
        dropdown.remove(newValue);
        dropdown.value = newValue;
    });
};
