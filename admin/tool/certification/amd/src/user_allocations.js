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
 * Potential user selector module.
 *
 * @module     tool_certification/user_allocations
 * @package    tool_certification
 * @copyright  2018 David Matamoros
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/ajax',
    'core/templates',
    'core/fragment',
    'core/notification',
    'tool_wp/modal_form',
    'core/str',
    'tool_wp/tabs'
], function($, ajax, Templates, Fragment, Notification, ModalForm, str, Tabs) {
    "use strict";
    var SELECTOR = {
        editCertificationViewRegion: '.wptabs',
        editCertificationUserList: '[data-region=edit-certification-user-list]',
        modalButton: '.wptabs',
        openbutton: '#userallocationbutton'
    };

    /**
     * Confirmation dialogue to de-allocate a user
     *
     * @param {Number} certificationid
     * @param {Number} id
     * @param {Number} contextid
     * @param {String} name
     */
    var confirmDeallocateUser = function(certificationid, id, contextid, name) {
        str.get_strings([
            {'key': 'deleteuserallocation', component: 'tool_certification'},
            {'key': 'confirmdeleteuserallocation', component: 'tool_certification', param: name},
            {'key': 'yes'},
            {'key': 'no'}
        ]).done(function(s) {
            Notification.confirm(s[0], s[1], s[2], s[3], function() {
                var promises = ajax.call([{
                    methodname: 'tool_certification_deallocate_user',
                    args: {
                        certificationid: certificationid,
                        userid: id
                    }
                }]);
                promises[0].done(function() {
                    // We reload only users list.
                    var args = {id: certificationid};
                    return Fragment.loadFragment('tool_certification', 'certifications_manager_users_list', contextid, args)
                        .then(function(html, js) {
                            return Templates.replaceNodeContents("[data-region='edit-certification-user-list']", html, js);
                        });

                });
            });
        }).fail(Notification.exception);
    };
    /**
     * Confirmation dialogue to revoke a user
     *
     * @param {Number} certificationid
     * @param {Number} id
     * @param {Number} contextid
     * @param {String} name
     */
    var confirmRevokeUser = function(certificationid, id, contextid, name) {
        str.get_strings([
            {'key': 'revokecertification', component: 'tool_certification'},
            {'key': 'revokewarning', component: 'tool_certification', param: name},
            {'key': 'yes'},
            {'key': 'no'}
        ]).done(function(s) {
            Notification.confirm(s[0], s[1], s[2], s[3], function() {
                var promises = ajax.call([{
                    methodname: 'tool_certification_revoke_certification',
                    args: {
                        certificationid: certificationid,
                        userid: id
                    }
                }]);
                promises[0].done(function() {
                    // We reload only users list.
                    var args = {id: certificationid};
                    return Fragment.loadFragment('tool_certification', 'certifications_manager_users_list', contextid, args)
                        .then(function(html, js) {
                            return Templates.replaceNodeContents("[data-region='edit-certification-user-list']", html, js);
                        });

                });
            });
        }).fail(Notification.exception);
    };
    return {
        init: function() {
            var $editCertificationUserList = $(SELECTOR.editCertificationUserList);
            var $editCertificationViewRegion = $(SELECTOR.editCertificationViewRegion);
            var contextid = $editCertificationViewRegion.data('contextid');
            /* Edit user modal */
            $editCertificationUserList.on('click', '.edit_user', function(e) {
                e.preventDefault();
                var certificationuserid = $(e.currentTarget).data('certificationuserid');
                var certificationid = $editCertificationViewRegion.data('id');
                var contextid = $editCertificationViewRegion.data('contextid');
                var name = $(e.currentTarget).closest('tr').children('td:first').text();
                var modal = new ModalForm({
                    formClass: 'tool_certification\\edit_certification_users_edit_form_modal',
                    args: {id: certificationid, certificationuserid: certificationuserid},
                    modalConfig: {title: str.get_string('allocationfor', 'tool_certification', name)},
                    contextId: contextid,
                    triggerElement: $(e.currentTarget),
                });
                // Override class method to reload user list after submit form.
                modal.onSubmitSuccess = function() {
                    Fragment.loadFragment('tool_certification', 'certifications_manager_users_list', contextid, {
                        id: certificationid
                    }).then(function(html, js) {
                        return Templates.replaceNodeContents($editCertificationUserList, html, js);
                    }).catch(Notification.exception);
                };
            });
            /* Deallocate user */
            $editCertificationUserList.on('click', '.confirm_deallocate_user', function(e) {
                e.preventDefault();
                var name = $(e.currentTarget).closest('tr').children('td:first').text();
                confirmDeallocateUser($(e.currentTarget).data('id'), $(e.currentTarget).data('userid'), contextid, name);
            });
            /* User allocation modal */
            Tabs.addButtonOnClick(function(e) {
                e.preventDefault();
                var certificationid = $(SELECTOR.modalButton).data('id');
                var contextid = $(SELECTOR.modalButton).data('contextid');
                var modal = new ModalForm({
                    formClass: 'tool_certification\\edit_certification_users_form_modal',
                    args: {id: certificationid, allocateuser: str.get_string('allocateusers', 'tool_certification')},
                    modalConfig: {title: str.get_string('allocateusers', 'tool_certification')},
                    contextId: contextid,
                    triggerElement: $(e.currentTarget),
                });
                // Override class method to reload user list after submit form.
                modal.onSubmitSuccess = function() {
                    Fragment.loadFragment('tool_certification', 'certifications_manager_users_list', contextid, {
                        id: certificationid
                    }).then(function(html, js) {
                        return Templates.replaceNodeContents($editCertificationUserList, html, js);
                    }).catch(Notification.exception);
                };
            });
            /* Revoke user certification modal */
            $editCertificationUserList.on('click', '.confirm_revoke_user', function(e) {
                e.preventDefault();
                var userid = $(e.currentTarget).data('userid');
                var certificationid = $editCertificationViewRegion.data('id');
                var contextid = $editCertificationViewRegion.data('contextid');
                var name = $(e.currentTarget).closest('tr').children('td:first').text();
                confirmRevokeUser(certificationid, userid, contextid, name);
            });
            /* Certify user certification modal */
            $editCertificationUserList.on('click', '.confirm_certify_user', function(e) {
                e.preventDefault();
                var certificationuserid = $(e.currentTarget).data('certificationuserid');
                var userid = $(e.currentTarget).data('userid');
                var certificationid = $editCertificationViewRegion.data('id');
                var contextid = $editCertificationViewRegion.data('contextid');
                var certifystr = str.get_string('certify', 'tool_certification');
                var modal = new ModalForm({
                    formClass: 'tool_certification\\edit_certification_users_certify_form_modal',
                    args: {id: certificationid, userid: userid, certificationuserid: certificationuserid},
                    modalConfig: {title: certifystr},
                    contextId: contextid,
                    triggerElement: $(e.currentTarget),
                });
                // Override save button with revoke text.
                modal.onInit = function() {
                    this.modal.setSaveButtonText(certifystr);
                    this.modal.getRoot().on('modal-save-cancel:save', this.submitForm.bind(this));
                };

                // Override class method to reload user list after submit form.
                modal.onSubmitSuccess = function() {
                    Fragment.loadFragment('tool_certification', 'certifications_manager_users_list', contextid, {
                        id: certificationid
                    }).then(function(html, js) {
                        return Templates.replaceNodeContents($editCertificationUserList, html, js);
                    }).catch(Notification.exception);
                };
            });
        }
    };
});