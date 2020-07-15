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
 * Import Export module
 *
 * @module     tool_wp/import_export
 * @package    tool_wp
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
    'tool_reportbuilder/reportbuilder_events'
], function($, str, Notification, Ajax, Templates, Str, AjaxForm, ReportEvents) {
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
        REPORTIMPORTS: "#imports [data-region=data-report]"
    };
    var STATUS = {
        DONE: 1,
        ERROR: 4
    };

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
                        M.util.js_complete('tool_wp_export_form');
                        return null;
                    })
                    .fail(Notification.exception);
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
                        window.scrollTo(0, 0);
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
                $(SELECTORS.EXPORTREPORT + ' .export-status').html(status);
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
                M.util.js_pending('tool_wp_import_form_render');
                return Templates.render('tool_wp/import', data)
                    .then(function(html, js) {
                        Templates.replaceNode(formRegionSelector, html, js);
                        $('head').append(xjs);
                        M.util.js_complete('tool_wp_import_form_render');
                        return null;
                    });
            };

            var goBack = function(data) {
                var request = {
                    methodname: 'tool_wp_import',
                    args: {importid: data.importid, newimportparams: JSON.stringify(data)}
                };
                M.util.js_pending('tool_wp_import_form_back');
                Ajax.call([request])[0]
                    .then(function(response) {
                        M.util.js_complete('tool_wp_import_form_back');
                        return renderImport(JSON.parse(response.content), response.javascript);
                    }).fail(Notification.exception);
            };

            var onFormSubmit = function(formResults) {
                if (formResults.url) {
                    window.history.replaceState(0, '', formResults.url);
                }
                renderImport(formResults.content, formResults.javascript).fail(Notification.exception);
            };

            var onFormCancel = function() {
                window.location.href = M.cfg.wwwroot + '/admin/tool/wp/exportimport.php#imports';
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
                        window.scrollTo(0, 0);
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
                $(SELECTORS.IMPORTREPORT + ' .import-status').html(status);
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
