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
 * Course carousel events
 *
 * @module     tool_catalogue/coursecarousel/events
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

/**
 * Events for the Course carousel
 *
 * @constant
 * @property {String} carouselReload See {@link event:carouselReload}
 */
export default {
    /**
     * Trigger carousel reloading
     *
     * @event carouselReload
     * @type {CustomEvent}
     * @property {object} detail
     *
     * @example <caption>Triggering carousel reload</caption>
     * import {dispatchEvent} from 'core/event_dispatcher';
     * import * as carouselEvents from 'tool_catalogue/coursecarousel/events';
     *
     * dispatchEvent(carouselEvents.carouselReload, {}, document.querySelector(...));
     */

    carouselReload: 'tool_catalogue_carousel_reload',
    /**
     * Trigger carousel filtering
     *
     * @event carouselFilterBy
     * @type {CustomEvent}
     * @property {object} detail
     * @property {String} detail.filter
     *
     * @example <caption>Triggering carousel filtering</caption>
     * import {dispatchEvent} from 'core/event_dispatcher';
     * import * as carouselEvents from 'tool_catalogue/coursecarousel/events';
     *
     * dispatchEvent(carouselEvents.carouselFilterBy, {filter: 'visible'}, document.querySelector(...));
     */

    carouselFilterBy: 'tool_catalogue_carousel_filter_by',
    /**
     * Trigger carousel set card filter
     *
     * @event carouselSetCardFilter
     * @type {CustomEvent}
     * @property {object} detail
     * @property {Number} detail.courseid
     * @property {String} detail.filter
     * @property {Boolean} detail.enabled
     *
     * @example <caption>Triggering carousel set card filter</caption>
     * import {dispatchEvent} from 'core/event_dispatcher';
     * import * as carouselEvents from 'tool_catalogue/coursecarousel/events';
     *
     * dispatchEvent(carouselEvents.carouselSetCardFilter, {courseid: 1, filter: 'visible', enabled: false},
     *      document.querySelector(...));
     */
    carouselSetCardFilter: 'tool_catalogue_carousel_set_card_filter',
};
