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
 * Users importer
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\importer;

use context_coursecat;
use context_system;
use context_user;
use core_user;
use stdClass;
use tool_tenant\manager;
use tool_tenant\tenancy;
use tool_wp\importer_base;
use tool_wp\local\exportimport\forms\import_conflict_form;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\wp_imported_entity;

/**
 * Importer class
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class users extends importer_base {

    /** @var string Name used for imported entity. */
    const ENTITY_NAME = 'user';

    /** @var string Element for including import of user profiles. */
    const IMPORT_PROFILE = 'import_profile';

    /** @var string Element for including import of user pictures. */
    const IMPORT_PICTURE = 'import_picture';

    /** @var string Element for selecting which users to import. */
    const IMPORT_INSTANCES = 'import_instances';

    /** @var string Import all users. */
    const IMPORT_INSTANCES_ALL = 'all';

    /** @var string Import manually selected users. */
    const IMPORT_INSTANCES_MANUAL = 'manual';

    /** @var string Element for selecting which users to import. */
    const IMPORT_SELECT_MANUAL = 'select_users';

    /**
     * Importer format
     *
     * @return int
     */
    public function get_format(): int {
        return self::FORMAT_WORKPLACE;
    }

    /**
     * Importer name
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('users');
    }

    /**
     * Define availability of importer, checks capabilities and entry point
     *
     * @return bool
     */
    public function is_available(): bool {
        $tenant = tenancy::get_tenants()[tenancy::get_tenant_id()];
        $tenantcontext = $tenant->categoryid ? context_coursecat::instance($tenant->categoryid) :
            context_system::instance();

        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint())
            && has_all_capabilities(['moodle/restore:createuser', 'moodle/restore:userinfo'], $tenantcontext);
    }

    /**
     * Initialise the importer, register all entities/error/notices
     */
    protected function initialise() {
        $this->register_entity(self::ENTITY_NAME, [
            self::ENTITY_DEPENDENCIES => ['tool_tenant'],
            self::ENTITY_INDIVIDUALIMPORT => static function(array $ids, array $settings): array {
                $defaults = [
                    self::IMPORT_PROFILE => 1,
                    self::IMPORT_PICTURE => 1,
                ];

                return array_intersect_key($settings, $defaults) + $defaults + [
                    self::IMPORT_INSTANCES => self::IMPORT_INSTANCES_MANUAL,
                    self::IMPORT_SELECT_MANUAL => $ids,
                ];
            },
            self::ENTITY_LOGSUCCESS => static function(int $id, array $details): string {
                return get_string('exportimportuserssuccess', 'tool_wp', $details['fullname']);
            },
            self::ENTITY_LOGERROR => static function(array $details): string {
                return get_string('exportimportuserserror', 'tool_wp', $details['fullname']);
            },
            self::ENTITY_NAMEPLURAL => $this->get_name(),
            self::ENTITY_INSTANCENAME_FOR_REVIEW => static function(array $details): string {
                return $details['fullname'];
            },
        ]);

        // Username conflict.
        $this->register_potential_error(self::ENTITY_NAME, 'usernameconflict', [
            self::ERROR_LOG => static function(array $details): string {
                return get_string('exportimporterrorentityexists', 'tool_wp', 'username');
            },
            self::ERROR_CONFLICTHEADER => get_string('exportimporterrorentityexists', 'tool_wp', 'username'),
            self::ERROR_CONFLICTSOLUTION => static function(array $settings, bool $forform): ?string {
                if (strcmp($settings['action'], 'increment') === 0) {
                    return get_string('exportimportconflictsuffix', 'tool_wp', 'username');
                }
                return null;
            },
        ]);

        // Email conflict.
        $this->register_potential_error(self::ENTITY_NAME, 'emailconflict', [
            self::ERROR_LOG => static function(array $details): string {
                return get_string('exportimporterrorentityexists', 'tool_wp', 'email');
            },
            self::ERROR_CONFLICTHEADER => get_string('exportimporterrorentityexists', 'tool_wp', 'email'),
        ]);

        // MNet host conflict.
        $this->register_potential_error(self::ENTITY_NAME, 'mnethostconflict', [
            self::ERROR_LOG => static function(array $details): string {
                return get_string('exportimportusersmnetconflict', 'tool_wp');
            },
            self::ERROR_CONFLICTHEADER => get_string('exportimportusersmnetconflict', 'tool_wp'),
            self::ERROR_CONFLICTSOLUTION => static function(array $settings, bool $forform): ?string {
                switch ($settings['action']) {
                    case 'useexisting':
                        return get_string('exportimportusersmnetuseexisting', 'tool_wp');
                    case 'matchlocal':
                        return get_string('exportimportusersmnetmatchlocal', 'tool_wp');
                    default:
                        return null;
                }
            }
        ]);

        // Missing lang.
        $this->register_potential_error(self::ENTITY_NAME, 'missinglangerror', [
            self::ERROR_LOG => static function(array $details): string {
                return get_string('exportimportusersmissinglangerrorlog', 'tool_wp', $details);
            },
            self::ERROR_CONFLICTHEADER => get_string('exportimportusersmissinglangerror', 'tool_wp'),
            self::ERROR_CONFLICTSOLUTION => static function(array $settings, bool $forform): ?string {
                switch ($settings['action']) {
                    case 'useselected':
                        return get_string('exportimportuserslanguseselected', 'tool_wp');
                    default:
                        return null;
                }
            }
        ]);

        // Register a notice if fields are changed in order to resolve conflicts.
        $this->register_potential_notice(self::ENTITY_NAME, 'fieldchanged', [
            self::NOTICE_LOG => static function(array $details, array $noticedetails): string {
                return get_string('exportimportfieldchanged', 'tool_wp', array_map('s', $noticedetails));
            }
        ]);
    }

    /**
     * Add elements to the import form
     *
     * @param import_settings_form $form
     */
    public function add_to_options_form(import_settings_form $form): void {
        $mform = $form->get_quick_form();

        $mform->addElement('header', 'headerconfig', get_string('content', 'tool_wp'));

        // This field is always set, it's here for informational purposes only.
        $mform->addElement('advcheckbox', self::IMPORT_PROFILE, new \lang_string('exportimportusersprofile', 'tool_wp'));
        $mform->setDefault(self::IMPORT_PROFILE, 1);
        $form->freeze_at(self::IMPORT_PROFILE, 1);

        // Whether user pictures should be imported.
        $mform->addElement('advcheckbox', self::IMPORT_PICTURE, new \lang_string('exportimportuserspicture', 'tool_wp'));
        $mform->setType(self::IMPORT_PICTURE, PARAM_INT);
        $mform->setDefault(self::IMPORT_PICTURE, 1);

        // User selection.
        $mform->addElement('header', 'headerinstances', get_string('instances', 'tool_wp'));

        $mform->addElement('radio', self::IMPORT_INSTANCES, null,
            new \lang_string('exportimportusersall', 'tool_wp'), self::IMPORT_INSTANCES_ALL);

        $mform->addElement('radio', self::IMPORT_INSTANCES, null,
            new \lang_string('exportimportusersmanual', 'tool_wp'), self::IMPORT_INSTANCES_MANUAL);

        $mform->setType(self::IMPORT_INSTANCES, PARAM_ALPHANUM);
        $mform->setDefault(self::IMPORT_INSTANCES, self::IMPORT_INSTANCES_ALL);

        // Create elements to allow user to limit which users to import, sorted by name.
        $userfields = \core_user\fields::get_name_fields();
        $viewfullnames = has_capability('moodle/site:viewfullnames', context_system::instance());

        $userselect = [];
        foreach ($this->get_entities_in_workplace_export_file(self::ENTITY_NAME) as $userentity) {
            // Grab enough fields to pass to fullname.
            $user = new stdClass();
            foreach ($userfields as $userfield) {
                $user->{$userfield} = $userentity->get_raw_field($userfield);
            }

            $userselect[$userentity->get_original_id()] = fullname($user, $viewfullnames);
        }
        \core_collator::asort($userselect);

        $mform->addElement('autocomplete', self::IMPORT_SELECT_MANUAL, new \lang_string('users'),
            $userselect, ['multiple' => true])->setHiddenLabel(true);
        $mform->setType(self::IMPORT_SELECT_MANUAL, PARAM_INT);
        $mform->hideIf(self::IMPORT_SELECT_MANUAL, self::IMPORT_INSTANCES, 'ne', self::IMPORT_INSTANCES_MANUAL);

        $form->add_validation_callback([$this, 'validate_options_form']);
    }

    /**
     * Summary of the import settings for the review step and also for the report page
     *
     * @param bool $importiscompleted
     * @return string
     */
    public function get_summary_for_review_step(bool $importiscompleted): string {
        global $OUTPUT;

        $data = $this->get_import_settings();

        $strs = get_strings(['exportimportusersprofile', 'exportimportuserspicture'], 'tool_wp');
        $settings = [
            ['name' => $strs->exportimportusersprofile, 'value' => !empty($data[self::IMPORT_PROFILE])],
            ['name' => $strs->exportimportuserspicture, 'value' => !empty($data[self::IMPORT_PICTURE])],
        ];

        return $OUTPUT->render_from_template('tool_wp/exportimport_summary', ['settings' => $settings]);
    }

    /**
     * Summary of entities included in the workplace file (human-readable), displayed in the "Step 2 General settings"
     *
     * Returns array of strings, where each string will be displayed as a separate 'static' element
     * in the form.
     *
     * @return array
     */
    public function get_export_file_content_for_overview_page(): array {
        $overview = [];

        // User count.
        if ($usercount = $this->get_entities_in_workplace_export_file(self::ENTITY_NAME)->count()) {
            $overview[] = $this->get_entity_display_name_plural(self::ENTITY_NAME) .
                get_string('entitiescountpostfix', 'tool_wp', $usercount);
        }

        return $overview;
    }

    /**
     * Import form element validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validate_options_form(array $data, array $files): array {
        $errors = [];

        // Ensure user has selected something to import.
        if ($data[self::IMPORT_INSTANCES] === self::IMPORT_INSTANCES_MANUAL
                && empty($data[self::IMPORT_SELECT_MANUAL])) {

            $errors[self::IMPORT_SELECT_MANUAL] = new \lang_string('required');
        }

        return $errors;
    }

    /**
     * Add importer errors to the conflict resolution form
     *
     * @param import_conflict_form $form
     * @param string $importedentity
     * @param string $errorcode
     * @param array $detailsarray
     * @param bool $addskipaction
     */
    public function add_to_conflict_form(import_conflict_form $form, string $importedentity, string $errorcode, array $detailsarray,
            bool $addskipaction = true): void {
        global $CFG;

        parent::add_to_conflict_form($form, $importedentity, $errorcode, $detailsarray, $addskipaction);

        $mform = $form->get_quick_form();

        // Offer to increment username, not email conflicts.
        if (strcmp($errorcode, 'usernameconflict') === 0) {
            $actionelement = $this->get_conflict_form_element_name($importedentity, $errorcode);

            $mform->addElement('radio', $actionelement, null,
                $this->get_conflict_solution($importedentity, $errorcode, ['action' => 'increment']), 'increment');
            $mform->setType($actionelement, PARAM_ALPHANUMEXT);

            $mform->setDefault($actionelement, 'skip');
        }

        // For mismatching MNet host, offer to use existing value or match the local site value.
        if (strcmp($errorcode, 'mnethostconflict') === 0) {
            $actionelement = $this->get_conflict_form_element_name($importedentity, $errorcode);

            foreach (['useexisting', 'matchlocal'] as $action) {
                $mform->addElement('radio', $actionelement, null,
                    $this->get_conflict_solution($importedentity, $errorcode, ['action' => $action]), $action);
                $mform->setType($actionelement, PARAM_ALPHANUMEXT);
            }

            $mform->setDefault($actionelement, 'skip');
        }

        // For missing language, offer to choose existing language.
        if (strcmp($errorcode, 'missinglangerror') === 0) {
            $actionelement = $this->get_conflict_form_element_name($importedentity, $errorcode);
            $mform->addElement('radio', $actionelement, null,
                $this->get_conflict_solution($importedentity, $errorcode, ['action' => 'useselected']), 'useselected');
            $mform->setType($actionelement, PARAM_ALPHANUMEXT);
            $mform->setDefault($actionelement, 'useselected');

            $langelement = $this->get_conflict_form_element_name($importedentity, $errorcode, 'lang');
            $translations = get_string_manager()->get_list_of_translations();
            $mform->addElement('select', $langelement, '', $translations);
            $mform->hideIf($langelement, $actionelement, 'noteq', 'useselected');
            $mform->setDefault($langelement, $CFG->lang);
        }
    }

    /**
     * Perform the import
     *
     * @param string $entityname
     */
    public function perform_import(string $entityname): void {
        if (strcmp($entityname, self::ENTITY_NAME) !== 0) {
            return;
        }

        if ($this->get_import_setting(self::IMPORT_INSTANCES)) {
            $userids = $this->get_import_setting(self::IMPORT_SELECT_MANUAL, []);
            if (count($userids) > 0) {
                $filter = static function(array $entity) use ($userids): bool {
                    return in_array($entity['id'], $userids);
                };
            } else {
                $filter = null;
            }

            $users = $this->get_entities_in_workplace_export_file(self::ENTITY_NAME, $filter);
            $installedlangs = array_keys(get_string_manager()->get_list_of_translations());
            foreach ($users as $user) {
                $user
                    ->exclude_fields(['firstaccess', 'lastaccess', 'lastlogin', 'lastip'])
                    ->set_validation_callback(function(array $data) use ($installedlangs): void {
                        global $CFG;

                        // Grab enough fields to pass to fullname.
                        $userfields = \core_user\fields::get_name_fields();
                        $userfullnamefields = array_intersect_key($data, array_fill_keys($userfields, 1));
                        $this->add_details_to_log(['fullname' => fullname((object) $userfullnamefields,
                            has_capability('moodle/site:viewfullnames', context_system::instance()))]);

                        if ($this->user_username_exists($data['username'])) {
                            $this->add_error_to_log('usernameconflict');
                        }

                        if ($data['mnethostid'] != $CFG->mnet_localhost_id) {
                            $this->add_error_to_log('mnethostconflict');
                        }

                        if (!in_array($data['lang'], $installedlangs)) {
                            $this->add_error_to_log('missinglangerror');
                            $this->add_details_to_log(['lang' => $data['lang']]);
                        }

                        // We don't need to raise an error if the site allows users with the same email to exist.
                        if (empty($CFG->allowaccountssameemail) && $this->user_email_exists($data['email'])) {
                            $this->add_error_to_log('emailconflict');
                        }
                    })
                    ->set_import_callback(function(array $data, wp_imported_entity $entity): int {
                        global $CFG;

                        require_once("{$CFG->dirroot}/user/lib.php");

                        // When importing, set users authentication method to 'manual', and ensure username/email are unique.
                        $user = (object) array_merge($data, [
                            'auth' => 'manual',
                            'mnethostid' => $this->get_mnethostid($data['mnethostid']),
                            'lang' => $this->get_lang($data['lang']),
                            'username' => $this->unique_value_for_field($data, 'username', function(string $username): bool {
                                return $this->user_username_exists($username);
                            }),
                            'email' => $this->unique_value_for_field($data, 'email', function(string $email) use ($CFG): bool {
                                return empty($CFG->allowaccountssameemail) && $this->user_email_exists($email);
                            }),
                        ]);

                        $user->id = user_create_user($user, false);

                        // Are we importing user pictures?
                        if ($this->get_import_setting(self::IMPORT_PICTURE)) {
                            $files = $entity->get_raw_files(['component' => 'user', 'filearea' => 'icon']);
                            foreach ($files as $file) {
                                $storedfile = get_file_storage()->create_file_from_pathname([
                                    'contextid' => context_user::instance($user->id)->id,
                                    'component' => 'user',
                                    'filearea' => 'icon',
                                    'itemid' => 0,
                                    'filepath' => '/',
                                    'filename' => $file['filename'],
                                ], $entity->get_file_path($file));

                                // Get first icon (f1) and set that as the user picture.
                                if (\core_text::substr($storedfile->get_filename(), 0, 2) == 'f1') {
                                    $user->picture = $storedfile->get_id();
                                    user_update_user($user, false, false);
                                }
                            }
                        }

                        // Allocate user to the import tenant.
                        (new manager())->allocate_user($user->id, $this->get_import_tenant_id(), 'tool_tenant', 'manual', true);

                        return $user->id;
                    })
                    ->import($this);

                if (!$this->is_collecting_errors() && !$user->get_new_id()) {
                    continue;
                }

                // Import user profile fields.
                $this->process_chained_entities(userfields::ENTITY_NAME, [], [
                    userfields::IMPORT_USERID => $user->get_original_id(),
                ]);
            }
        }
    }

    /**
     * Return appropriate MNet host value according to conflict resolution for those users whose value differs from the site
     *
     * @param string $usermnethostid
     * @return string
     */
    private function get_mnethostid(string $usermnethostid): string {
        global $CFG;

        $resolution = $this->get_conflict_resolution_setting(self::ENTITY_NAME, 'mnethostconflict', 'action');
        if (strcmp($usermnethostid, $CFG->mnet_localhost_id) !== 0 && strcmp($resolution, 'matchlocal') === 0) {
            $this->add_notice_to_log('fieldchanged', [
                'field' => 'mnethostid',
                'from' => $usermnethostid,
                'to' => $CFG->mnet_localhost_id,
            ]);

            $usermnethostid = $CFG->mnet_localhost_id;
        }

        return $usermnethostid;
    }

    /**
     * Return appropriate lang according to conflict resolution for those users whose lang is missing in the system.
     *
     * @param string $lang
     * @return string
     */
    private function get_lang(string $lang): string {
        $resolution = $this->get_conflict_resolution_setting(self::ENTITY_NAME, 'missinglangerror', 'action');
        $newlang = $this->get_conflict_resolution_setting(self::ENTITY_NAME, 'missinglangerror', 'lang');

        if (strcmp($lang, $newlang) !== 0 && strcmp($resolution, 'useselected') === 0) {
            $this->add_notice_to_log('fieldchanged', [
                'field' => 'lang',
                'from' => $lang,
                'to' => $newlang,
            ]);

            $lang = $newlang;
        }

        return $lang;
    }

    /**
     * Helper method to find a unique value for a field
     *
     * @param array $data
     * @param string $field
     * @param callable $lookup
     * @return string
     */
    private function unique_value_for_field(array $data, string $field, callable $lookup): string {
        $uniquevalue = helper::find_unique_value_for_field($data[$field], $lookup);

        // Inform user if field value changed.
        if (strcmp($uniquevalue, $data[$field]) !== 0) {
            $this->add_notice_to_log('fieldchanged', [
                'field' => $field,
                'from' => $data[$field],
                'to' => $uniquevalue,
            ]);
        }

        return $uniquevalue;
    }

    /**
     * Determine whether a given username is already in use
     *
     * @param string $username
     * @return bool
     */
    private function user_username_exists(string $username): bool {
        return core_user::get_user_by_username($username) !== false;
    }

    /**
     * Determine whether a given email address is already in use
     *
     * @param string $email
     * @return bool
     */
    private function user_email_exists(string $email): bool {
        return core_user::get_user_by_email($email) !== false;
    }
}
