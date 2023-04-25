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
 * Users exporter
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\exporter;

use context_system;
use context_user;
use core_course_category;
use stdClass;
use tool_tenant\tenancy;
use tool_wp\exporter_base;
use tool_wp\local\exportimport\forms\export_settings_form;

/**
 * Exporter class
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class users extends exporter_base {

    /** @var string Name to use for exported entity. */
    const ENTITY_NAME = 'user';

    /** @var string Element for including export of user profiles. */
    const EXPORT_PROFILE = 'export_profile';

    /** @var string Element for including export of user pictures. */
    const EXPORT_PICTURE = 'export_picture';

    /** @var string Element for including suspended users in the export. */
    const EXPORT_SUSPENDED = 'export_suspended';

    /** @var string Element for selecting which users to export. */
    const EXPORT_INSTANCES = 'export_instances';

    /** @var string Export all users. */
    const EXPORT_INSTANCES_ALL = 'all';

    /** @var string Export users from current tenant. */
    const EXPORT_INSTANCES_TENANT = 'tenant';

    /** @var string Export manually selected users. */
    const EXPORT_INSTANCES_MANUAL = 'manual';

    /** @var string Element for selecting which users to export. */
    const EXPORT_SELECT_MANUAL = 'select_users';

    /**
     * Exporter format
     *
     * @return int
     */
    public function get_format(): int {
        return self::FORMAT_WORKPLACE;
    }

    /**
     * Exporter name
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('users');
    }

    /**
     * Exporter description to show in the list of available exporters
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('exportimportusersdescription', 'tool_wp');
    }

    /**
     * Exporter icon URL
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;

        return $OUTPUT->image_url('menu/users', 'theme')->out(false);
    }

    /**
     * Does this exporter "work" only within one tenant?
     *
     * @return bool
     */
    public function is_tenant_required(): bool {
        return false;
    }

    /**
     * Define availability of exporter, checks capabilities and entry point
     *
     * @return bool
     */
    public function is_available(): bool {
        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint()) &&
            (has_capability('moodle/backup:userinfo', context_system::instance()) ||
                core_course_category::has_capability_on_any('moodle/backup:userinfo'));
    }

    /**
     * Initialise the exporter, register all entities
     */
    public function initialise() {
        $this->register_entity(self::ENTITY_NAME, [
            self::ENTITY_INDIVIDUALEXPORT => static function(array $ids, array $settings): array {
                $defaults = [
                    self::EXPORT_PROFILE => 1,
                    self::EXPORT_PICTURE => 1,
                    self::EXPORT_SUSPENDED => 0,
                ];

                return array_intersect_key($settings, $defaults) + $defaults + [
                    self::EXPORT_INSTANCES => self::EXPORT_INSTANCES_MANUAL,
                    self::EXPORT_SELECT_MANUAL => $ids,
                ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => static function(array $record): string {
                $viewfullnames = has_capability('moodle/site:viewfullnames', context_system::instance());

                return fullname((object) $record, $viewfullnames);
            },
        ]);
    }

    /**
     * Add elements to the export form
     *
     * @param export_settings_form $form
     */
    public function add_to_options_form(export_settings_form $form): void {
        $mform = $form->get_quick_form();

        $mform->addElement('header', 'headerconfig', get_string('content', 'tool_wp'));

        // This field is always set, it's here for informational purposes only.
        $mform->addElement('advcheckbox', self::EXPORT_PROFILE, new \lang_string('exportimportusersprofile', 'tool_wp'));
        $mform->setDefault(self::EXPORT_PROFILE, 1);
        $form->freeze_at(self::EXPORT_PROFILE, 1);

        // Whether user pictures should be exported.
        $mform->addElement('advcheckbox', self::EXPORT_PICTURE, new \lang_string('exportimportuserspicture', 'tool_wp'));
        $mform->setType(self::EXPORT_PICTURE, PARAM_INT);
        $mform->setDefault(self::EXPORT_PICTURE, 1);

        // Whether suspended users should be included.
        $mform->addElement('advcheckbox', self::EXPORT_SUSPENDED, new \lang_string('exportimportuserssuspended', 'tool_wp'));
        $mform->setType(self::EXPORT_SUSPENDED, PARAM_INT);
        $mform->setDefault(self::EXPORT_SUSPENDED, 0);

        // User selection.
        $mform->addElement('header', 'headerinstances', get_string('instances', 'tool_wp'));

        // If exporting for a tenant, just offer than tenant otherwise offer "All users".
        if ($tenantid = $this->get_export_tenant_id()) {
            $tenantname = tenancy::get_tenant_name_from_id($tenantid);

            $mform->addElement('radio', self::EXPORT_INSTANCES, null,
                new \lang_string('exportimportuserstenant', 'tool_wp', $tenantname), self::EXPORT_INSTANCES_TENANT);
            $mform->setDefault(self::EXPORT_INSTANCES, self::EXPORT_INSTANCES_TENANT);
        } else {
            $mform->addElement('radio', self::EXPORT_INSTANCES, null,
                new \lang_string('exportimportusersall', 'tool_wp'), self::EXPORT_INSTANCES_ALL);
            $mform->setDefault(self::EXPORT_INSTANCES, self::EXPORT_INSTANCES_ALL);
        }

        $mform->addElement('radio', self::EXPORT_INSTANCES, null,
            new \lang_string('exportimportusersmanual', 'tool_wp'), self::EXPORT_INSTANCES_MANUAL);

        $mform->setType(self::EXPORT_INSTANCES, PARAM_ALPHANUM);

        // TODO: WP-2027 improve the potential user selector.
        $options = array(
            'ajax' => 'tool_wp/form-potential-user-selector',
            'data-component' => 'tool_wp',
            'data-area' => 'users',
            'data-itemid' => (int) $tenantid,
            'multiple' => true,
            'valuehtmlcallback' => static function($userid): string {
                if (!$userid = clean_param($userid, PARAM_INT)) {
                    return '';
                }

                $user = \core_user::get_user($userid);
                return '<span>' . fullname($user) . '</span> <span><small>' . $user->email . '</small></span>';
            }
        );

        $mform->addElement('autocomplete', self::EXPORT_SELECT_MANUAL, new \lang_string('users'),
            [], $options)->setHiddenLabel(true);
        $mform->setType(self::EXPORT_SELECT_MANUAL, PARAM_INT);
        $mform->hideIf(self::EXPORT_SELECT_MANUAL, self::EXPORT_INSTANCES, 'ne', self::EXPORT_INSTANCES_MANUAL);

        $form->add_validation_callback([$this, 'validate_options_form']);
    }

    /**
     * Summary of the export settings for the review step and also for the report page
     *
     * @param bool $exportcompleted
     * @return string
     */
    public function get_summary_for_review_step(bool $exportcompleted): string {
        global $OUTPUT;

        $data = $this->get_export_settings();

        $strs = get_strings(['exportimportusersprofile', 'exportimportuserspicture', 'exportimportuserssuspended'], 'tool_wp');
        $settings = [
            ['name' => $strs->exportimportusersprofile, 'value' => !empty($data[self::EXPORT_PROFILE])],
            ['name' => $strs->exportimportuserspicture, 'value' => !empty($data[self::EXPORT_PICTURE])],
            ['name' => $strs->exportimportuserssuspended, 'value' => !empty($data[self::EXPORT_SUSPENDED])],
        ];

        return $OUTPUT->render_from_template('tool_wp/exportimport_summary', ['settings' => $settings]);
    }

    /**
     * Returns the list of entities that will be exported
     *
     * @param string $entityname
     * @return array[] Each element is array that can be passed through self::ENTITY_INSTANCENAME_FOR_REVIEW callback
     */
    public function get_instances_for_review_step(string $entityname): array {
        if (strcmp($entityname, self::ENTITY_NAME) === 0) {
            return $this->get_users_for_export();
        }

        return [];
    }

    /**
     * Export form element validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validate_options_form(array $data, array $files): array {
        $errors = [];

        // Ensure user has selected something to export.
        if ($data[self::EXPORT_INSTANCES] === self::EXPORT_INSTANCES_MANUAL
                && empty($data[self::EXPORT_SELECT_MANUAL])) {

            $errors[self::EXPORT_SELECT_MANUAL] = new \lang_string('required');
        }

        return $errors;
    }

    /**
     * Performs the export
     *
     * @return void
     */
    public function perform_export(): void {
        $settings = $this->get_export_settings();

        if (!empty($settings[self::EXPORT_INSTANCES])) {
            foreach ($this->get_users_for_export() as $user) {
                $export = $this->prepare_data_for_workplace_export(self::ENTITY_NAME,
                    $this->process_user_for_export($user));

                // Are we exporting user pictures?
                if (!empty($settings[self::EXPORT_PICTURE])) {
                    $usercontext = context_user::instance($user->id);
                    $export->add_area_files($usercontext, 'user', 'icon', 0);
                }

                $export->export();

                // Export user profile fields.
                $this->process_chained_entities(userfields::ENTITY_NAME, [], [
                    userfields::EXPORT_USERID => $user->id,
                ]);
            }
        }
    }

    /**
     * Perform processing of a complete user record prior to export
     *
     * @param stdClass $user
     * @return array
     */
    private function process_user_for_export(stdClass $user): array {
        global $CFG;

        $user = (array) $user;

        // Observe config for including user passwords.
        if (empty($CFG->includeuserpasswordsinbackup)) {
            $user['password'] = AUTH_PASSWORD_NOT_CACHED;
        }

        // For all available identity fields, determine which the user is able to access. Note that 'username' & 'email' are
        // excluded from this list because we require them in order to match users and avoid conflicts during import.
        $removeidentityfields = array_diff([
            'idnumber',
            'phone1',
            'phone2',
            'department',
            'institution',
            'city',
            'country',
        ], \core_user\fields::for_identity(\context_system::instance(), false)->get_required_fields());

        foreach ($removeidentityfields as $removeidentityfield) {
            unset($user[$removeidentityfield]);
        }

        return $user;
    }

    /**
     * Return array of users to export
     *
     * @return stdClass[]
     */
    private function get_users_for_export(): array {
        global $DB, $CFG;

        $systemcontext = context_system::instance();

        // Determine whether we are exporting users for the site, or a given tenant.
        $tenantid = $this->get_export_tenant_id();
        if ($tenantid === null && has_capability('moodle/backup:userinfo', $systemcontext)) {
            $join = '';
            $where = 'id != :guest AND deleted = 0';
            $params = ['guest' => $CFG->siteguest];
        } else {
            [$join, $where, $params] = tenancy::get_users_sql('u', (int) $tenantid);
        }

        $userids = $this->get_export_setting(self::EXPORT_SELECT_MANUAL, []);
        if (count($userids) > 0) {
            list($useridwhere, $useridparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $where .= " AND u.id {$useridwhere}";
            $params += $useridparams;
        }

        if (!$this->get_export_setting(self::EXPORT_SUSPENDED)) {
            $where .= ' AND u.suspended = 0';
        }

        [$sort, $sortparams] = users_order_by_sql('u', null, $systemcontext);

        return $DB->get_records_sql("SELECT u.* FROM {user} u {$join} WHERE {$where} ORDER BY {$sort}", $params + $sortparams);
    }
}
