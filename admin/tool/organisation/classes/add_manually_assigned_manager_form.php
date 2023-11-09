<?php
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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_organisation;

use context_system;
use core_form\dynamic_form;
use core_user;
use core_user\fields;
use html_writer;
use moodle_url;
use stdClass;
use tool_organisation\local\helpers\user_manager;
use tool_organisation\local\persistent\user_manager as user_manager_model;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/admin/tool/organisation/lib.php');

/**
 * Class add_manually_assigned_manager_form
 *
 * @package     tool_organisation
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class add_manually_assigned_manager_form extends dynamic_form {
    /** @var int Keep existing managers */
    const KEEP = 1;

    /** @var int Replace existing managers with the new ones */
    const REPLACE = 2;

    /**
     * Form definition
     */
    public function definition(): void {
        global $OUTPUT;
        $mform = $this->_form;
        $mform->setDisableShortforms();

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('hidden', 'managerid');
        $mform->setType('managerid', PARAM_INT);

        $userids = $this->optional_param('userids', '', PARAM_RAW);
        $mform->addElement('hidden', 'userids', $userids);
        $mform->setType('userids', PARAM_RAW);

        $action = $this->optional_param('title', '', PARAM_RAW);
        $mform->addElement('hidden', 'action', $action);
        $mform->setType('action', PARAM_RAW);

        $htmltext = $this->optional_param('htmltext', '', PARAM_RAW);
        $mform->addElement('hidden', 'htmltext', $htmltext);
        $mform->setType('htmltext', PARAM_RAW);

        if (!empty($htmltext)) {
            $mform->addElement('html', html_writer::div(get_string($htmltext, 'tool_organisation'), 'pb-6'));
        }

        // Users selector, send the userid as itemid in order to retrieve all users except the current one.
        $userid = $this->optional_param('userid', 0, PARAM_RAW);
        $options = [
            'ajax' => 'tool_wp/form-potential-user-selector',
            'data-component' => 'tool_organisation',
            'data-area' => 'manuallyassigned',
            'data-itemid' => $userid,
            'multiple' => true,
            'valuehtmlcallback' => function ($value) {
                global $OUTPUT;

                // Check if the user can be assigned as a mam.
                if (!permission::can_assign_mam_to_user($value)) {
                    return '';
                }

                $userfieldsapi = fields::for_name();
                $allusernames = $userfieldsapi->get_sql('', false, '', '', false)->selects;
                $fields = 'id, email, ' . $allusernames;
                $user = core_user::get_user($value, $fields);
                $context = context_system::instance();
                $useroptiondata = [
                        'fullname' => fullname($user, has_capability('moodle/site:viewfullnames', $context)),
                        'identity' => $user->email,
                        'hasidentity' => has_capability('moodle/site:viewuseridentity', $context),
                ];

                return $OUTPUT->render_from_template('tool_wp/form-user-selector-suggestion', $useroptiondata);
            },
        ];

        $mform->addElement('autocomplete', 'users', get_string('users', 'tool_organisation'), [], $options);
        $mform->addRule('users', get_string('missingusers', 'tool_organisation'), 'required', null, 'client');

        if ($action == 'assignmanagers') {
            $mform->addHelpButton('users', 'managersdropdown', 'tool_organisation');
        }

        $optionclasses = ['class' => "my-2"];

        // Set existing managers radio buttons.
        $existingmanagers = [];
        $existingmanagers[] =& $mform->createElement(
            'radio',
            'existingmanagers',
            null,
            get_string('keepexistingmanagers', 'tool_organisation'),
            self::KEEP,
            $optionclasses
        );
        $existingmanagers[] =& $mform->createElement(
            'radio',
            'existingmanagers',
            null,
            get_string('replaceexistingmanagers', 'tool_organisation'),
            self::REPLACE,
            $optionclasses
        );
        $mform->setDefault("existingmanagers", self::KEEP);

        $mform->addGroup(
            $existingmanagers,
            'existingmanagersgroup',
            get_string('existingmanagers', 'tool_organisation'),
            '<br>',
            false
        );
        $mform->hideIf('existingmanagersgroup', 'action', 'neq', 'assignmanagers');
        $mform->addHelpButton('existingmanagersgroup', 'existingmanagers', 'tool_organisation');

        // Set the manually assigned manager permissions checkboxes.
        $manuallyassignedmanagerpermissions = organisation::get_manually_assigned_manager_permissions();
        $permissiongroup = [];
        foreach ($manuallyassignedmanagerpermissions as $index => $permission) {
            $title = html_writer::span($OUTPUT->render($permission['icon'])) . $permission['title'];
            $permissiongroup[] =& $mform->createElement(
                'advcheckbox',
                "manmgrpermission[$index]",
                '',
                $title,
                $optionclasses,
                [0, $index]
            );
            $mform->addHelpButton("manmgrpermission[$index]", $permission['name'], 'tool_organisation');
        }

        $permissionstitle = get_string('positionpermissions', 'tool_organisation');
        $mform->addGroup($permissiongroup, 'manmgrpermissiongroup', $permissionstitle, '<br>', false);
    }

    /**
     * Modify elements after data is available.
     */
    public function definition_after_data(): void {
        $mform = $this->_form;
        $managerid = $mform->getElementValue('managerid');
        if ($managerid) {
            $mform->removeElement('users');
        }
    }

    /**
     * Check access
     */
    protected function check_access_for_dynamic_submission(): void {
        permission::require_can_assign_manually_assigned_manager();
    }

    /**
     * Process form submission
     *
     * @return void
     */
    public function process_dynamic_submission(): void {
        $data = $this->get_data();
        $manager = new stdClass();
        $mgrassignment = new user_manager();

        // If id is not set, then it is a new record.
        if (empty($data->id)) {
            $manager->permissions = 0;
            foreach ($data->manmgrpermission as $index => $mgrpermission) {
                if (!empty($mgrpermission)) {
                    $manager->permissions = $manager->permissions + $index;
                }
            }
            $manageruserids = !empty($data->users) ? $data->users : [];
            $existingmanagers = self::KEEP;
            if ($data->action === "assignmanagers") {
                $managerids = $manageruserids;
                $staffids = !empty($data->userids) ? explode(',', $data->userids) : [];
                $existingmanagers = (int) $data->existingmanagers;
            } else if ($data->action === "assignmanager") {
                $managerids = $manageruserids;
                $staffids = [$data->userid];
            } else {
                $managerids = [$data->userid];
                $staffids = $manageruserids;
            }
            foreach ($staffids as $staffuserid) {
                if ($existingmanagers === self::REPLACE) {
                    $mgrassignment->delete_user_manager(['userid' => $staffuserid]);
                }
                foreach ($managerids as $manageruserid) {
                    $manager->id = $manageruserid;
                    $mgrassignment->add_assigned_manager((int) $staffuserid, $manager);
                }
            }
        } else {
            $manager->id = $data->managerid;
            $manager->permissions = array_sum($data->manmgrpermission);
            $mgrassignment->update_assigned_manager($data->userid, $manager, $manager->id);
        }
    }

    /**
     * Set data in the modal form
     */
    public function set_data_for_dynamic_submission(): void {
        $data = (object)$this->_ajaxformdata;
        if (!empty($data->userid)) {
            $this->set_data(['userid' => $data->userid]);
        }
        if (!empty($data->id)) {
            $this->set_data(['id' => $data->id]);
            $this->set_data(['managerid' => $data->managerid]);
            $mamrecord = user_manager_model::get_record(['userid' => $data->userid, 'managerid' => $data->managerid]);
            $manuallyassignedmanagerpermissions = organisation::get_manually_assigned_manager_permissions();
            foreach ($manuallyassignedmanagerpermissions as $index => $mgrpermission) {
                if ($mamrecord->get('permissions') & $index) {
                    $this->set_data(["manmgrpermission[$index]" => $index]);
                }
            }
        }
    }

    /**
     * Performs validation of the form information
     *
     * @param array $data
     * @param array $files
     * @return array $errors An array of $errors
     */
    public function validation($data, $files) {
        global $DB;
        $errors = [];

        // Check if users array was created, this validation only applies to new records.
        if (isset($data['users'])) {
            [$useridswhere, $useridsparams] = $DB->get_in_or_equal($data['users'], SQL_PARAMS_NAMED);
            [$manageridswhere, $manageridsparams] = $DB->get_in_or_equal($data['users'], SQL_PARAMS_NAMED);
            $currentuserparams = [
                'currentuser' => $data['userid'],
                'currentmanager' => $data['userid'],
            ];

            $sql = "
                SELECT 1
                FROM {tool_organisation_manual_mgr} mam
                WHERE (mam.userid = :currentuser AND mam.managerid {$manageridswhere})
                OR (mam.managerid = :currentmanager AND mam.userid {$useridswhere})
            ";

            $usersmamcreated = $DB->record_exists_sql($sql, $useridsparams + $manageridsparams + $currentuserparams);

            if ($usersmamcreated) {
                $errors['users'] = get_string('usermanagednotallowed', 'tool_organisation');
            }

            // We need to check that the user sent to assignment belongs to the same tenant as the current one.
            $usersnotinsametenant = array_filter($data['users'], function (int $manager) {
                return !permission::can_assign_mam_to_user($manager);
            });

            if (!empty($usersnotinsametenant)) {
                $errors['users'] = get_string('notinsametenant', 'tool_organisation');
            }

        }
        return $errors;
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return moodle_url
     */
    public function get_page_url_for_dynamic_submission(): moodle_url {
        $id = $this->optional_param('id', 0, PARAM_INT);
        return new moodle_url('/admin/tool/organisation/user.php', ['id' => $id]);
    }

    /**
     * Returns context where this form is used
     *
     * @return context_system
     */
    public function get_context_for_dynamic_submission(): context_system {
        return context_system::instance();
    }
}
