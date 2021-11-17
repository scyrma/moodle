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
 * Import Export module
 *
 * @module     tool_wp/import_export
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define([
    'jquery',
    'core/str',
    'core/notification',
    'core/ajax',
    'core/templates',
    'core/str',
    'tool_wp/ajax_form',
    'tool_reportbuilder/reportbuilder_events',
    'tool_wp/events',
    'core/pubsub',
    'tool_wp/processing',
], function($, str, Notification, Ajax, Templates, Str, AjaxForm, ReportEvents, WpEvents, pubSub, Processing) {
    "use strict";

    /**
     * List of selectors.
     */
    var SELECTORS = {
        IMPORTPROGRESSBUTTON: "[data-action=update-import-progress]",
        IMPORTREPORT: "[data-region='import-report']",
        EXPORTPROGRESSBUTTON: "[data-action=update-export-progress]",
        EXPORTREPORT: "[data-region='export-report']",
        REPORTCONTAINER: "[data-region='system-report'] [data-region='data-report']",
        REPORTEXPORTS: "#exports [data-region=data-report]",
        REPORTIMPORTS: "#imports [data-region=data-report]",
        MAINREGION: "#region-main"
    };
    var STATUS = {
        DONE: 1,
        ERROR: 4
    };

    /**
     * Initialize spinner.
     */
    Processing.init(SELECTORS.MAINREGION);

    /**
     * Reload report
     *
     * @param {$} node that triggered the action.
     */
    var reloadReport = function(node) {
        var report = node.closest(SELECTORS.REPORTCONTAINER);

        report.trigger(ReportEvents.RELOADTABLE);
    };

    /**
     * Confirmation dialogue to delete export
     *
     * @param {Number} id
     * @param {jQuery} triggerElement
     */
    var deleteExport = function(id, triggerElement) {
        str.get_strings([
            {'key': 'confirm'},
            {'key': 'confirmdeleteexport', component: 'tool_wp'},
            {'key': 'yes'},
            {'key': 'no'}
        ]).done(function(s) {
            Notification.confirm(s[0], s[1], s[2], s[3], function() {
                var promises = Ajax.call([{
                    methodname: 'tool_wp_delete_export',
                    args: {
                        id: id
                    }
                }]);
                promises[0].done(function() {
                    reloadReport(triggerElement);
                    return null;
                }).fail(Notification.exception);
            });
            return null;
        }).fail(Notification.exception);
    };

    /**
     * Confirmation dialogue to delete import
     *
     * @param {Number} id
     * @param {jQuery} triggerElement
     */
    var deleteImport = function(id, triggerElement) {
        str.get_strings([
            {'key': 'confirm'},
            {'key': 'confirmdeleteimport', component: 'tool_wp'},
            {'key': 'yes'},
            {'key': 'no'}
        ]).done(function(s) {
            Notification.confirm(s[0], s[1], s[2], s[3], function() {
                var promises = Ajax.call([{
                    methodname: 'tool_wp_delete_import',
                    args: {
                        id: id
                    }
                }]);
                promises[0].done(function() {
                    reloadReport(triggerElement);
                    return null;
                }).fail(Notification.exception);
            });
            return null;
        }).fail(Notification.exception);
    };

    return {
        /**
         * Initialise Import Export listings
         */
        initListing: function() {

            // Delete export.
            $(SELECTORS.REPORTEXPORTS).on('click', '.confirm_delete_export', function(e) {
                e.preventDefault();
                deleteExport($(e.currentTarget).data('id'), $(e.currentTarget));
            });

            // Delete import.
            $(SELECTORS.REPORTIMPORTS).on('click', '.confirm_delete_import', function(e) {
                e.preventDefault();
                deleteImport($(e.currentTarget).data('id'), $(e.currentTarget));
            });

            $(SELECTORS.REPORTCONTAINER).on('click', '[data-action=download][data-url]', function(e) {
                e.preventDefault();
                window.location = $(e.currentTarget).data('url');
            });
        },

        /**
         * Initialise 'Show More' listings
         */
        initShowMoreList: function() {
            $('.showmorelist > button').click((e) => {
                e.preventDefault();
                const button = $(e.target);
                const list = button.closest('.showmorelist');
                if (button.hasClass('showmore')) {
                    list.find('li:hidden').show();
                    list.find('button.showless').show();
                }
                if (button.hasClass('showless')) {
                    list.find('ul li:nth-child(n+4)').hide();
                    list.find('button.showmore').show();
                }
                button.hide();
            });
        },

        /**
         * Initialise view of one export
         *
         * @param {String} formRegionSelector
         */
        initExport: function(formRegionSelector) {
            var onFormSubmit = function(formResults) {
                var xjs;
                var request = {methodname: 'tool_wp_export', args: {exportid: 0}};
                if (formResults.exportid !== undefined) {
                    request.args.exportid = formResults.exportid;
                } else {
                    request.args.newexportparams = JSON.stringify(formResults);
                }
                M.util.js_pending('tool_wp_export_form');
                pubSub.publish(WpEvents.LOADER_START);
                Ajax.call([request])[0]
                    .then(function(response) {
                        xjs = response.javascript;
                        if (response.url) {
                            window.history.replaceState(0, '', response.url);
                        }
                        return Templates.render('tool_wp/export', JSON.parse(response.content));
                    })
                    .then(function(html, js) {
                        Templates.replaceNode(formRegionSelector, html, js);
                        $('head').append(xjs);
                        window.scrollTo(0, 0);
                        M.util.js_complete('tool_wp_export_form');
                        return null;
                    })
                    .always(() => {
                        pubSub.publish(WpEvents.LOADER_STOP);
                        return null;
                    })
                    .fail(Notification.exception);
            };

            var onFormSubmitError = function(exception) {
                pubSub.publish(WpEvents.LOADER_STOP);
                Notification.exception(exception);
            };

            var onFormValidationError = function() {
                pubSub.publish(WpEvents.LOADER_STOP);
            };

            var onFormCancel = function() {
                window.location.href = M.cfg.wwwroot + '/admin/tool/wp/exportimport.php';
            };

            var onPrevButton = function(e, formElement) {
                e.preventDefault();
                if (formElement.data("changed")) {
                    Str.get_strings([
                        {key: 'confirmprevbutton', component: 'tool_wp'}
                    ]).done(function(s) {
                        // eslint-disable-next-line no-alert
                        if (confirm(s[0])) {
                            // Data-attributes of the "prevbutton" contain all attributes we need for displaying the
                            // previous screen.
                            onFormSubmit($(e.currentTarget).data());
                        }
                    }).fail(Notification.exception);
                } else {
                    // Data-attributes of the "prevbutton" contain all attributes we need for displaying the previous screen.
                    onFormSubmit($(e.currentTarget).data());
                }
            };

            var exportConfirmation = function(e, form) {
                e.preventDefault();
                Str.get_strings([
                    {key: 'confirm'},
                    {key: 'confirmprocess', component: 'tool_wp'},
                    {key: 'proceed', component: 'tool_wp'},
                    {key: 'cancel'}
                ]).done(function(s) {
                    Notification.confirm(s[0], s[1], s[2], s[3], () => {
                        form.submitFormAjax(e);
                    });
                }).fail(Notification.exception);
            };

            const wrapper = $(formRegionSelector + ' [data-region=formwrapper][data-formclass]');
            if (wrapper.length) {
                const formClass = wrapper.attr('data-formclass');
                const form = new AjaxForm(wrapper, formClass);
                const formElement = wrapper.children();
                form.onSubmitSuccess = onFormSubmit;
                form.onSubmitError = onFormSubmitError;
                form.onValidationError = onFormValidationError;
                form.onCancel = onFormCancel;
                // Check form input changes.
                formElement.find(':input').change(function() {
                    formElement.data("changed", true);
                });
                // Override listener for pressing the "Back" button.
                wrapper.on('click', 'form input[type=submit][name=prevbutton]', (e) => {
                    onPrevButton(e, formElement);
                });
                // Override listener for pressing the "Export" button.
                wrapper.on('click', 'form input[type=submit][name=exportbutton]', (e) => {
                    exportConfirmation(e, form);
                });
            }
        },

        /**
         * Initialise Export progress
         */
        initExportProgress: function() {
            const updateExportStatus = (status) => {
                const statusPill = $(SELECTORS.EXPORTREPORT + ' .export-status > span');
                // Convert status string to lowercase and remove spaces to generate the pill class.
                const statusPillClass = `tool_wp_status_${status.toLowerCase().replace(/\s/g, '')}`;
                statusPill.removeClass().addClass(statusPillClass);
                statusPill.html(status);
            };

            const updateExportProgress = (exportid) => {
                $(SELECTORS.EXPORTPROGRESSBUTTON).addClass('disabled').find('i').addClass('fa-spin');
                Ajax.call([{
                    methodname: 'tool_wp_get_export_status',
                    args: {
                        exportid: exportid
                    }
                }])[0].done((response) => {
                    if (response.status === STATUS.DONE || response.status === STATUS.ERROR) {
                        window.location.href = M.cfg.wwwroot + '/admin/tool/wp/export.php?exportid=' + exportid;
                    } else {
                        updateExportStatus(response.statusstr);
                        $(SELECTORS.EXPORTPROGRESSBUTTON).removeClass('disabled').find('i').removeClass('fa-spin');
                    }
                    return null;
                }).fail(Notification.exception);
            };

            // Update export progress.
            $(SELECTORS.EXPORTREPORT).on('click', SELECTORS.EXPORTPROGRESSBUTTON, function(e) {
                e.preventDefault();
                updateExportProgress($(e.currentTarget).data('exportid'));
            });
        },

        /**
         * Initialise view of one import
         *
         * @param {String} formRegionSelector
         */
        initImport: function(formRegionSelector) {
            var renderImport = function(data, xjs) {
                return Templates.render('tool_wp/import', data)
                    .then(function(html, js) {
                        Templates.replaceNode(formRegionSelector, html, js);
                        $('head').append(xjs);
                        window.scrollTo(0, 0);
                        return null;
                    });
            };

            var goBack = function(data) {
                var request = {
                    methodname: 'tool_wp_import',
                    args: {importid: data.importid, newimportparams: JSON.stringify(data)}
                };
                M.util.js_pending('tool_wp_import_form_back');
                pubSub.publish(WpEvents.LOADER_START);
                Ajax.call([request])[0]
                    .then((response) => {
                        M.util.js_complete('tool_wp_import_form_back');
                        return renderImport(JSON.parse(response.content), response.javascript);
                    })
                    .always(() => {
                        pubSub.publish(WpEvents.LOADER_STOP);
                        return null;
                    })
                    .fail(Notification.exception);
            };

            var onFormSubmit = function(formResults) {
                if (formResults.url) {
                    window.history.replaceState(0, '', formResults.url);
                }
                M.util.js_pending('tool_wp_import_form');
                pubSub.publish(WpEvents.LOADER_START);
                renderImport(formResults.content, formResults.javascript)
                    .then(() => {
                        M.util.js_complete('tool_wp_import_form');
                        return null;
                    })
                    .always(() => {
                        pubSub.publish(WpEvents.LOADER_STOP);
                        return null;
                    })
                    .fail(Notification.exception);
            };

            var onFormSubmitError = function(exception) {
                pubSub.publish(WpEvents.LOADER_STOP);
                Notification.exception(exception);
            };

            var onFormCancel = function() {
                window.location.href = M.cfg.wwwroot + '/admin/tool/wp/exportimport.php#imports';
            };

            var onFormValidationError = function() {
                pubSub.publish(WpEvents.LOADER_STOP);
            };

            var onPrevButton = function(e, formElement) {
                e.preventDefault();
                if (formElement.data("changed")) {
                    Str.get_strings([
                        {key: 'confirmprevbutton', component: 'tool_wp'}
                    ]).done(function(s) {
                        // eslint-disable-next-line no-alert
                        if (confirm(s[0])) {
                            // Data-attributes of the "prevbutton" contain all attributes we need for displaying the
                            // previous screen.
                            goBack($(e.currentTarget).data());
                        }
                    }).fail(Notification.exception);
                } else {
                    // Data-attributes of the "prevbutton" contain all attributes we need for displaying the previous screen.
                    goBack($(e.currentTarget).data());
                }
            };

            var importConfirmation = function(e, form) {
                e.preventDefault();
                Str.get_strings([
                    {key: 'confirm'},
                    {key: 'confirmprocess', component: 'tool_wp'},
                    {key: 'proceed', component: 'tool_wp'},
                    {key: 'cancel'}
                ]).done(function(s) {
                    Notification.confirm(s[0], s[1], s[2], s[3], () => {
                        form.submitFormAjax(e);
                    });
                }).fail(Notification.exception);
            };

            const wrapper = $(formRegionSelector + ' [data-region=formwrapper][data-formclass]');
            if (wrapper.length) {
                const formClass = wrapper.attr('data-formclass');
                const form = new AjaxForm(wrapper, formClass);
                const formElement = wrapper.children();
                form.onSubmitSuccess = onFormSubmit;
                form.onSubmitError = onFormSubmitError;
                form.onValidationError = onFormValidationError;
                form.onCancel = onFormCancel;
                // Check form input changes.
                formElement.find(':input').change(function() {
                    formElement.data("changed", true);
                });
                // Override listener for pressing the "Back" button.
                wrapper.on('click', 'form input[type=submit][name=prevbutton]', (e) => {
                    onPrevButton(e, formElement);
                });
                // Override listener for pressing the "Next" button.
                wrapper.on('click', 'form input[type=submit][name=submitbutton]', () => {
                    pubSub.publish(WpEvents.LOADER_START);
                });
                // Override listener for pressing the "Export" button.
                wrapper.on('click', 'form input[type=submit][name=importbutton]', (e) => {
                    importConfirmation(e, form);
                });
            }
        },

        /**
         * Initialise Import progress
         */
        initImportProgress: function() {
            const updateImportStatus = (status) => {
                const statusPill = $(SELECTORS.EXPORTREPORT + ' .import-status > span');
                // Convert status string to lowercase and remove spaces to generate the pill class.
                const statusPillClass = `tool_wp_status_${status.toLowerCase().replace(/\s/g, '')}`;
                statusPill.removeClass().addClass(statusPillClass);
                statusPill.html(status);
            };

            const updateImportProgress = (importid) => {
                $(SELECTORS.IMPORTPROGRESSBUTTON).addClass('disabled').find('i').addClass('fa-spin');
                Ajax.call([{
                    methodname: 'tool_wp_get_import_status',
                    args: {
                        importid: importid
                    }
                }])[0].done((response) => {
                    if (response.status === STATUS.DONE || response.status === STATUS.ERROR) {
                        window.location.href = M.cfg.wwwroot + '/admin/tool/wp/import.php?importid=' + importid;
                    } else {
                        updateImportStatus(response.statusstr);
                        $(SELECTORS.IMPORTPROGRESSBUTTON).removeClass('disabled').find('i').removeClass('fa-spin');
                    }
                    return null;
                }).fail(Notification.exception);
            };

            // Update export progress.
            $(SELECTORS.IMPORTREPORT).on('click', SELECTORS.IMPORTPROGRESSBUTTON, function(e) {
                e.preventDefault();
                updateImportProgress($(e.currentTarget).data('importid'));
            });
        }
    };
});
