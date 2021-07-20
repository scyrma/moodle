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
 * This module instantiates the functionality to manage certifications list.
 *
 * @module     tool_certification/certification_manager
 * @package    tool_certification
 * @author     2018 David Matamoros <davidmc@moodle.com>
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

define([
    'jquery',
    'core/ajax',
    'core/notification',
    'core/str',
    'tool_wp/tabs',
    'core_form/modalform',
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
            {key: 'confirm', component: 'moodle'},
            {key: 'archivedconfirmation', component: 'tool_certification', param: name},
            {key: 'archive', component: 'tool_certification'},
            {key: 'cancel', component: 'moodle'}
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
            {key: 'confirm', component: 'moodle'},
            {key: 'confirmdeletecertification', component: 'tool_certification', param: name},
            {key: 'delete', component: 'moodle'},
            {key: 'cancel', component: 'moodle'}
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
            returnFocus: triggerElement[0],
            saveButtonText: Str.get_string('save')
        });
        modal.show();
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
            {key: 'confirm', component: 'moodle'},
            {key: 'confirmduplicate', component: 'tool_certification', param: name},
            {key: 'ok', component: 'moodle'},
            {key: 'cancel', component: 'moodle'}
        ]).done(function(s) {
            Notification.confirm(s[0], s[1], s[2], s[3], function() {
                var str = Str.get_string('newcertification', 'tool_certification');
                var modal = showDetailsModal($(e.currentTarget), 0, certificationid, str);
                modal.addEventListener(modal.events.FORM_SUBMITTED, (e) => {
                    window.location.href = e.detail;
                });
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
        modal.addEventListener(modal.events.FORM_SUBMITTED, (e) => {
            window.location.href = e.detail;
        });
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
        modal.addEventListener(modal.events.FORM_SUBMITTED, () => {
            element.closest(SELECTORS.REPORTCONTAINER).trigger(ReportEvents.RELOADTABLE);
        });
    };

    return CertsManager;
});