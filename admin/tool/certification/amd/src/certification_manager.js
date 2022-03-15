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
 * This module instantiates the functionality to manage certifications list.
 *
 * @module     tool_certification/certification_manager
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define([
    'jquery',
    'core/ajax',
    'core/notification',
    'core/str',
    'tool_wp/tabs',
    'tool_wp/modal_form',
    'tool_reportbuilder/reportbuilder_events'
], function($,
            ajax,
            Notification,
            Str,
            Tabs,
            ModalForm,
            ReportEvents) {

    /** @type {Object} The list of selectors for the certification area. */
    var SELECTORS = {
        EDITDETAILS: "[data-action='editdetails']",
        ARCHIVECERTIFICATION: "[data-action='archive']",
        RESTORECERTIFICATION: "[data-action='restore']",
        DUPLICATECERTIFICATION: "[data-action='duplicate']",
        DELETECERTIFICATION: "[data-action='delete']",
        REPORTCONTAINER: "[data-region='system-report'] [data-region='data-report']"
    },
    SERVICES = {
        ARCHIVECERTIFICATION: 'tool_certification_archive_certification',
        RESTORECERTIFICATION: 'tool_certification_restore_certification',
        DELETECERTIFICATION: 'tool_certification_delete_certification',
    };

    /**
     * Messagearea class.
     *
     * @param {String} selector The selector for the page region containing the certifications area.
     */
    function CertsManager(selector) {
        this.node = $(selector);
        this._init();
    }

    /** @type {jQuery} The jQuery node for the page region containing the certification area. */
    CertsManager.prototype.node = null;

    /**
     * Initialise the other objects we require.
     */
    CertsManager.prototype._init = function() {
        this.node.on('click', SELECTORS.ARCHIVECERTIFICATION, this._archiveCertificationHandler.bind(this));
        this.node.on('click', SELECTORS.RESTORECERTIFICATION, this._restoreCertificationHandler.bind(this));
        this.node.on('click', SELECTORS.DUPLICATECERTIFICATION, this._duplicateCertificationHandler.bind(this));
        this.node.on('click', SELECTORS.DELETECERTIFICATION, this._deleteCertificationHandler.bind(this));
        this.node.on('click', SELECTORS.EDITDETAILS, this._editCertificationDetailsHandler.bind(this));
        Tabs.addButtonOnClick(this._addCertification.bind(this));
    };

    /**
     * Handles archive a Certification.
     *
     * @param {Event} e The jquery event
     * @private
     */
    CertsManager.prototype._archiveCertificationHandler = function(e) {
        e.preventDefault();

        var element = $(e.currentTarget);
        var certificationid = element.data('certificationid');
        var name = element.closest('tr')
            .find('.inplaceeditable[data-itemtype=certificationname]').text();
        Str.get_strings([
            {key: 'confirm'},
            {key: 'archivedconfirmation', component: 'tool_certification', param: name},
            {key: 'archive', component: 'tool_certification'},
            {key: 'cancel'}
        ]).done(function(s) {
            Notification.confirm(s[0], s[1], s[2], s[3], function() {
                var promises = ajax.call([
                    {methodname: SERVICES.ARCHIVECERTIFICATION, args: {certificationid: certificationid}}
                ]);
                promises[0].done(function(data) {
                    if (data) {
                        element.closest(SELECTORS.REPORTCONTAINER).trigger(ReportEvents.RELOADTABLE);
                    }
                }).fail(Notification.exception);
            });
        }).fail(Notification.exception);
    };

    /**
     * Handles restore a Certification.
     *
     * @param {Event} e The jquery event
     * @private
     */
    CertsManager.prototype._restoreCertificationHandler = function(e) {
        e.preventDefault();

        var element = $(e.currentTarget);
        var certificationid = element.data('certificationid');
        var request = {
            methodname: SERVICES.RESTORECERTIFICATION,
            args: {
                certificationid: certificationid
            }
        };

        ajax.call([request])[0].done(function(data) {
            if (data) {
                element.closest(SELECTORS.REPORTCONTAINER).trigger(ReportEvents.RELOADTABLE);
            }
        }).fail(Notification.exception);
    };

    /**
     * Handles delete a Certification.
     *
     * @param {Event} e The jquery event
     * @private
     */
    CertsManager.prototype._deleteCertificationHandler = function(e) {
        e.preventDefault();

        var element = $(e.currentTarget);
        var certificationid = element.data('certificationid');
        var name = element.closest('tr').find('.c0').text();
        Str.get_strings([
            {key: 'confirm'},
            {key: 'confirmdeletecertification', component: 'tool_certification', param: name},
            {key: 'delete'},
            {key: 'cancel'}
        ]).done(function(s) {
            Notification.confirm(s[0], s[1], s[2], s[3], function() {
                var promises = ajax.call([
                    {methodname: SERVICES.DELETECERTIFICATION, args: {certificationid: certificationid}}
                ]);
                promises[0].done(function(data) {
                    if (data) {
                        element.closest(SELECTORS.REPORTCONTAINER).trigger(ReportEvents.RELOADTABLE);
                    }
                }).fail(Notification.exception);
            });
        }).fail(Notification.exception);
    };

    /**
     * Popup to edit certification details
     *
     * @param {jQuery} triggerElement
     * @param {Number} certificationid
     * @param {Number} duplicate
     * @param {Promise} title
     * @return {ModalForm}
     * @private
     */
    var showDetailsModal = function(triggerElement, certificationid, duplicate, title) {
        var modal = new ModalForm({
            formClass: 'tool_certification\\edit_certification_details_form',
            args: {id: certificationid, duplicatecertification: duplicate},
            modalConfig: {title: title, scrollable: false},
            triggerElement: triggerElement,
            saveButtonText: Str.get_string('save')
        });
        return modal;
    };

    /**
     * Handles duplicate a Certification.
     *
     * @param {Event} e The jquery event
     * @private
     */
    CertsManager.prototype._duplicateCertificationHandler = function(e) {
        e.preventDefault();

        var element = $(e.currentTarget);
        var certificationid = element.data('certificationid');
        var name = element.closest('tr')
            .find('.inplaceeditable[data-itemtype=certificationname]').text();
        Str.get_strings([
            {key: 'confirm'},
            {key: 'confirmduplicate', component: 'tool_certification', param: name},
            {key: 'ok'},
            {key: 'cancel'}
        ]).done(function(s) {
            Notification.confirm(s[0], s[1], s[2], s[3], function() {
                var str = Str.get_string('newcertification', 'tool_certification');
                var modal = showDetailsModal($(e.currentTarget), 0, certificationid, str);
                modal.onSubmitSuccess = function(response) {
                    window.location.href = response;
                };
            });
        }).fail(Notification.exception);
    };

    /**
     * Handles add a new certification.
     *
     * @param {Event} e Click event
     * @private
     */
    CertsManager.prototype._addCertification = function(e) {
        e.preventDefault();
        var str = Str.get_string('newcertification', 'tool_certification');
        var modal = showDetailsModal($(e.currentTarget), 0, 0, str);
        modal.onSubmitSuccess = function(response) {
            window.location.href = response;
        };
    };

    /**
     * Handles edit certification details event
     *
     * @param {Event} e The jquery event
     * @private
     */
    CertsManager.prototype._editCertificationDetailsHandler = function(e) {
        e.preventDefault();
        var element = $(e.currentTarget);
        var id = element.data('id');
        var certname = element.closest('tr')
            .find('.inplaceeditable[data-itemtype=certificationname]').text();
        var str = Str.get_string('editcertification', 'tool_certification', certname);
        var modal = showDetailsModal($(e.currentTarget), id, 0, str);
        modal.onSubmitSuccess = function() {
            element.closest(SELECTORS.REPORTCONTAINER).trigger(ReportEvents.RELOADTABLE);
        };
    };

    return CertsManager;
});