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
 * Tabs UI element with AJAX loading of tabs content. See README for the plugin
 *
 * @module     tool_wp/tabs
 * @package    tool_wp
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define(['jquery', 'jqueryui', 'core/ajax', 'core/templates', 'core/notification', 'core/fragment',
        'tool_wp/ajax_form'],
function($, jqui, Ajax, Templates, Notification, Fragment, AjaxForm) {

    var
        /**
         * Replace node contents with the template output
         *
         * @param {$} node
         * @param {String} html
         * @param {String} js
         * @param {String} appendToHead will be appended to the head (normally <script>)
         * @return {Promise}
         */
        replaceNodeContents = function(node, html, js, appendToHead) {
            var promise = $.Deferred();
            node.fadeOut("fast", function() {
                Templates.replaceNodeContents(node, html, js);
                $('head').append(appendToHead);
                node.fadeIn("fast", function() {
                    promise.resolve();
                });
            });

            return promise.promise();
        },

        /**
         * Show "loading" template instead of a node
         *
         * @param {$} node
         * @return {Promise}
         */
        indicateNodeIsLoading = function(node) {
            return Templates.render('tool_wp/loading', {})
                .then(function(loadinghtml, loadingjs) {
                    // TODO use replaceNodeContents here?
                    return Templates.replaceNodeContents(node, loadinghtml, loadingjs);
                });
        },

        /**
         * Returns id of the currently active tab (or the first tab)
         *
         * @return {String}
         */
        getActiveTab = function() {
            var active = $('.wptabs .nav-link.active');
            if (active.length) {
                return active.attr('aria-controls');
            }
            // Active tab not found, return name of the first tab content.
            return $('.wptabs .tab-pane [data-tab-content]').attr('data-tab-content');
        },

        /**
         * Loads contents of a tab using an AJAX request
         *
         * @param {String} tabName
         * @param {Object} additionalData additional data to pass to WS
         */
        loadTab = function(tabName, additionalData) {
            if (!tabName) {
                // If tabName is not specified find the active tab.
                tabName = getActiveTab();
            }
            var tab = $('.wptabs [data-tab-content="' + tabName + '"]');
            if (tab.length !== 1) {
                return;
            }
            // TODO prevent race conditions when this function is invoked multiple times asynchronously.

            var dataAttrs = tab.closest('.wptabs').data(),
                tabjs = '',
                wsData = $.extend({}, dataAttrs, additionalData || {});
            M.util.js_pending('tool_wp_load_tab_' + tabName); // This will make behat wait for tab to load.
            $('.wptabs .tab-pane [data-tab-content]').text('');
            indicateNodeIsLoading(tab)
            .then(function() {
                return Ajax.call([{
                    methodname: 'tool_wp_get_tab_content',
                    args: {tab: tab.attr('data-tab-class'), jsondata: JSON.stringify(wsData)}
                }])[0];
            }).then(function(data) {
                tabjs = data.javascript;
                return Templates.render(data.template, JSON.parse(data.content));
            }).then(function(html, js) {
                return replaceNodeContents(tab, html, js, tabjs);
            }).then(function() {
                M.util.js_complete('tool_wp_load_tab_' + tabName);
                // TODO we might need something for accessibility that notifies that page content was updated.
                return null;
            }).fail(Notification.exception);
        },

        /**
         * Get the location hash if the tab with the same name exists
         *
         * @return {string}
         */
        getLocationHash = function() {
            var hash = document.location.hash;
            if (hash.match(/^#!\w+$/g)) {
                // In Workplace 3.7 we used the ! in the beginning of the hash. In case some links were not fixed
                // let's redirect to the old location. However if this happens in behat test - fail it.
                hash = hash.replace(/^#!/g, '#');
                if (history.replaceState) {
                    history.replaceState(null, null, hash);
                } else {
                    location.hash = hash;
                }
                M.util.js_pending('tool_wp_error_wrong_link'); // This does not have the matching "complete" - it will fail behat!
                require(['theme_boost/tab'], function() {
                    $('.wptabs [data-toggle="tab"][href="' + hash + '"]').first().tab('show');
                });
            }

            if (hash !== '' && hash.match(/^#\w+$/g) && ($('.wptabs [data-toggle="tab"][href="' + hash + '"]').length)) {
                return hash;
            }
            return '';
        },

        /**
         * Initialises the tabs view on the page.
         *
         * This function is called after the theme_boost/loader initialises everything.
         */
        init = function() {

            // Add listener to the event when bootstrap tab is shown.
            $('a[data-toggle="tab"]').on('shown.bs.tab', function() {
                var tab = $($(this).attr('href'));
                if (tab.length !== 1) {
                    return;
                }
                // TODO call M.core_formchangechecker.report_form_dirty_state() .
                loadTab(tab.attr('id'));
            });

            // Open the tab from the document hash or the first tab.
            var hash = getLocationHash(),
                tab = (hash !== "") ?
                $('.wptabs [data-toggle="tab"][href="' + hash + '"]:not(.disabled)') : $();
            if (tab.length) {
                loadTab(tab.attr('aria-controls'));
            } else {
                var tabs = $('.wptabs .nav-link[data-toggle="tab"]:not(.disabled)');
                if (tabs.length) {
                    // There are several tabs present, show the first enabled tab.
                    tabs.first().tab('show');
                } else {
                    // We may hide tabs if there is only one available, just load the contents of the first tab panel.
                    var tabpane = $('.wptabs .tab-pane').first();
                    if (tabpane.length) {
                        tabpane.addClass('active').addClass('show');
                        loadTab(tabpane.attr('id'));
                    }
                }
            }
        };

    return {
        /**
         * Initialises the tabs view on the page (only one tabs view per page is supported)
         *
         * We must execute it after the theme_boost/loader initialises everything and the only way
         * to make sure about it is to wait for 'theme_boost/loader:children' to appear in
         * M.util.complete_js because the loader is asynchronous.
         */
        init: function() {
            M.util.js_pending('tool_wp_tabs_init_int');
            var timer = setInterval(function() {
                for (var i = 0; i < M.util.complete_js.length; i++) {
                    if (M.util.complete_js[i][0] === 'theme_boost/loader:children') {
                        init();
                        clearInterval(timer);
                        M.util.js_complete('tool_wp_tabs_init_int');
                    }
                }
            }, 100);
        },

        /**
         * Loads contents of a tab using an AJAX request
         *
         * @param {String} tabName leave blank for current tab
         * @param {Object} additionalData additional data to pass to WS
         */
        loadTab: function(tabName, additionalData) {
            loadTab(tabName, additionalData);
        },

        /**
         * Switch to a tab
         *
         * @param {String} tabName
         */
        switchToTab: function(tabName) {
            $('.nav-link[href="#' + tabName + '"]').click();
        },

        /**
         * Adds a callback for "Add" button
         *
         * @param {Function} callback
         */
        addButtonOnClick: function(callback) {
            $('.wptabs .tab-pane.active [data-tabs-element="addbutton"]').on('click', callback);
        },

        /**
         * Returns id of the currently active tab (or the first tab)
         *
         * @return {String}
         */
        getActiveTab: function() {
            return getActiveTab();
        },

        initForm: function(onSubmitSuccess) {
            var wrapper = $('.wptabs .tab-pane.active [data-region=tabformwrapper][data-formclass]');
            var formClass = wrapper.attr('data-formclass');
            var form = new AjaxForm(wrapper, formClass);
            form.onSubmitSuccess = onSubmitSuccess;
        }
    };
});
