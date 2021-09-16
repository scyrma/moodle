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
 * Allow searchinging in a list-group.
 *
 * @module     tool_wp/accordion_search
 * @package    tool_wp
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020, Bas Brands <bas@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
define(
[
    'jquery'
],
function(
    $
) {

    var SELECTORS = {
        COLLAPSED_CONTAINER: '.collapse',
        COLLAPSED_CONTAINER_WRAPPER: '.card',
        LIST_CONTAINER: '.card',
        LIST_ITEM: '.list-group-item',
        SEARCH_INPUT: '[data-region="aside-search-input"]',
        VISIBLE_LIST_ITEM: '.list-group-item.d-flex'
    };

    var CSSCLASS = {
        HIDDEN: 'd-none',
        VISIBLE: 'd-flex'
    };

    /**
     * Get the list of items to search.
     *
     * @param  {Object} root Aside container element.
     * @return {Object} Search input container.
     */
    var searchList = function(root) {
        return root.find(SELECTORS.LIST_ITEM);
    };

    /**
     * Search all containers for visible list items, expand them if any are
     * found. Hide them if none are found.
     *
     * @param  {Object} root Aside container element.
     */
    var showActiveContainers = function(root) {
        root.find(SELECTORS.COLLAPSED_CONTAINER).each(function() {
            var collapse = $(this);
            var container = collapse.closest(SELECTORS.COLLAPSED_CONTAINER_WRAPPER);
            if (collapse.find(SELECTORS.VISIBLE_LIST_ITEM).length) {
                collapse.collapse('show');
                container.removeClass(CSSCLASS.HIDDEN);
            } else {
                container.addClass(CSSCLASS.HIDDEN);
            }
        });
    };

    /**
     * Reset all Collapsible containers to be visible again.
     *
     * @param  {Object} root Aside container element.
     */
    var resetContainers = function(root) {
        root.find(SELECTORS.COLLAPSED_CONTAINER).each(function() {
            var collapse = $(this);
            var container = collapse.closest(SELECTORS.COLLAPSED_CONTAINER_WRAPPER);
            container.removeClass(CSSCLASS.HIDDEN);
            collapse.collapse('show');
        });
    };

    /**
     * Listen to and handle events for searching.
     *
     * @param {Object} root Aside container element.
     */
    var registerEventListeners = function(root) {
        var searchInput = root.find(SELECTORS.SEARCH_INPUT);

        var searchEventHandler = function() {
            var searchText = searchInput.val().toLowerCase().trim();
            var listItems = searchList(root);
            if (searchText !== '') {

                listItems.filter(function() {
                    var listItem = $(this);
                    var value = listItem.text().toLowerCase();
                    if (value.indexOf(searchText) > -1) {
                        listItem.removeClass(CSSCLASS.HIDDEN).addClass(CSSCLASS.VISIBLE);
                    } else {
                        listItem.removeClass(CSSCLASS.VISIBLE).addClass(CSSCLASS.HIDDEN);
                    }
                    return true;
                });
                showActiveContainers(root);

            } else {
                listItems.each(function() {
                    var listItem = $(this);
                    listItem.removeClass(CSSCLASS.HIDDEN).addClass(CSSCLASS.VISIBLE);
                });
                resetContainers(root);
            }
        };

        searchInput.keyup(searchEventHandler);
    };

    /**
     * Initialise all of the modules for the overview block.
     *
     * @param {object} root The root element for the overview block.
     */
    var init = function(root) {
        registerEventListeners(root);
    };

    return {
        init: init
    };
});