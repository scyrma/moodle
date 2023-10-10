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
 * Tenants exporter
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\tool_wp\exporter;

use context_system;
use core_component;
use lang_string;
use tool_certification\tool_wp\exporter\certifications;
use tool_organisation\tool_wp\exporter\jobs;
use tool_organisation\tool_wp\exporter\orgstructure;
use tool_program\tool_wp\exporter\programs;
use tool_reportbuilder\tool_wp\exporter\customreports;
use tool_tenant\permission;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use tool_tenant\tenant;
use tool_tenant\tenant_user;
use tool_wp\exporter_base;
use tool_wp\local\exportimport\forms\export_settings_form;
use tool_wp\tool_wp\exporter\coursecategories;

/**
 * Exporter class
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tenants extends exporter_base {

    /** @var string Element for including tenant details. */
    const EXPORT_DETAILS = 'export_details';

    /** @var string Element for including tenant appearance. */
    const EXPORT_APPEARANCE = 'export_appearance';

    /** @var string Element for including tenant users. */
    const EXPORT_USERS = 'export_users';

    /** @var string Element for including categories (& cohorts/course structure). */
    const EXPORT_CATEGORIES = 'export_categories';

    /** @var string Element for including cohorts (overrides previous setting). */
    const EXPORT_COHORTS = 'export_cohorts';

    /** @var string Element for including course content. */
    const EXPORT_COURSE_CONTENT = 'export_course_content';

    /** @var string Element for including certificates. */
    const EXPORT_CERTIFICATES = 'export_certificates';

    /** @var string Element for including programs. */
    const EXPORT_PROGRAMS = 'export_programs';

    /** @var string Element for including certifications. */
    const EXPORT_CERTIFICATIONS = 'export_certifications';

    /** @var string Element for including organisation structure (frameworks & jobs). */
    const EXPORT_ORGANISATION = 'export_organisation';

    /** @var string Element for including dynamic rules. */
    const EXPORT_RULES = 'export_rules';

    /** @var string Element for including custom reports. */
    const EXPORT_REPORTS = 'export_reports';

    /** @var string Element for selecting which tenants to include. */
    const EXPORT_INSTANCES = 'export_instances';

    /** @var string Include all non-archived tenants. */
    const EXPORT_INSTANCES_NO_ARCHIVED = 'noarchived';

    /** @var string Include all tenants. */
    const EXPORT_INSTANCES_ALL = 'all';

    /** @var string Include manually selected tenants. */
    const EXPORT_INSTANCES_MANUAL = 'manual';

    /** @var string Element for manually selecting which tenants to include. */
    const EXPORT_SELECT_MANUAL = 'select_tenants';

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
        return get_string('tenants', 'tool_tenant');
    }

    /**
     * Exporter description to show in the list of available exporters
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('migrationexporterdescription', 'tool_tenant');
    }

    /**
     * Exporter icon URL
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;

        return $OUTPUT->image_url('menu/tenants', 'theme')->out(false);
    }

    /**
     * Define availability of exporter, checks capabilities and entry point
     *
     * @return bool
     */
    public function is_available(): bool {
        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint()) && permission::can_export_tenants();
    }

    /**
     * Does this exporter "work" only within one tenant? The export of tenants themselves should return false
     *
     * @return bool
     */
    public function is_tenant_required(): bool {
        return false;
    }

    /**
     * Initialise the exporter, register all entities
     */
    public function initialise() {
        $this->register_entity(tenant::TABLE, [
            self::ENTITY_INDIVIDUALEXPORT => static function(array $ids, array $settings): array {
                $defaults = [
                    self::EXPORT_INSTANCES => self::EXPORT_INSTANCES_MANUAL,
                    self::EXPORT_APPEARANCE => 1,
                    self::EXPORT_USERS => 1,
                    self::EXPORT_CATEGORIES => 1,
                    self::EXPORT_COHORTS => 1,
                    self::EXPORT_COURSE_CONTENT => 1,
                    self::EXPORT_CERTIFICATES => 1,
                    self::EXPORT_PROGRAMS => 1,
                    self::EXPORT_CERTIFICATIONS => 1,
                    self::EXPORT_ORGANISATION => 1,
                    self::EXPORT_RULES => 1,
                    self::EXPORT_REPORTS => 1,
                ];

                return array_intersect_key($settings, $defaults) + $defaults + [
                    self::EXPORT_SELECT_MANUAL => $ids,
                ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => static function(array $details): string {
                return $details['name'];
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
        $mform->addElement('advcheckbox', self::EXPORT_DETAILS, new lang_string('tenantdetails', 'tool_tenant'));
        $mform->setDefault(self::EXPORT_DETAILS, 1);
        $form->freeze_at(self::EXPORT_DETAILS, 1);

        // Whether tenant appearance data should be included.
        $mform->addElement('advcheckbox', self::EXPORT_APPEARANCE, new lang_string('appearance'));
        $mform->setType(self::EXPORT_APPEARANCE, PARAM_INT);
        $mform->setDefault(self::EXPORT_APPEARANCE, 1);

        // Whether tenant users should be included.
        $mform->addElement('advcheckbox', self::EXPORT_USERS, new lang_string('users'));
        $mform->setType(self::EXPORT_USERS, PARAM_INT);
        $mform->setDefault(self::EXPORT_USERS, 1);
        if (!$this->can_export_chained_entity('user')) {
            $form->freeze_at(self::EXPORT_USERS, 0);
        }

        // Whether categories should be included (& cohorts/course structure).
        $mform->addElement('advcheckbox', self::EXPORT_CATEGORIES, new lang_string('migrationcoursecategories', 'tool_tenant'));
        $mform->setType(self::EXPORT_CATEGORIES, PARAM_INT);
        $mform->setDefault(self::EXPORT_CATEGORIES, 1);
        if (!$this->can_export_chained_entity('course_categories')) {
            $form->freeze_at(self::EXPORT_CATEGORIES, 0);
        }

        // Whether course content should be included.
        $mform->addElement('advcheckbox', self::EXPORT_COURSE_CONTENT, new lang_string('includecoursecontent', 'tool_wp'));
        $mform->setType(self::EXPORT_COURSE_CONTENT, PARAM_INT);
        $mform->setDefault(self::EXPORT_COURSE_CONTENT, 1);
        $mform->hideIf(self::EXPORT_COURSE_CONTENT, self::EXPORT_CATEGORIES, 'eq', 0);
        if (!$this->can_export_chained_entity('course')) {
            $form->freeze_at(self::EXPORT_COURSE_CONTENT, 0);
        }

        // Whether certificates should be included.
        $mform->addElement('advcheckbox', self::EXPORT_CERTIFICATES, new lang_string('certificatetemplates', 'tool_wp'));
        $mform->setType(self::EXPORT_CERTIFICATES, PARAM_INT);
        $mform->setDefault(self::EXPORT_CERTIFICATES, 1);
        $mform->hideIf(self::EXPORT_CERTIFICATES, self::EXPORT_CATEGORIES, 'eq', 0);
        if (!$this->can_export_chained_entity('tool_certificate_templates')) {
            $form->freeze_at(self::EXPORT_CERTIFICATES, 0);
        }

        // Whether programs should be included.
        if (core_component::get_component_directory('tool_program')) {
            $mform->addElement('advcheckbox', self::EXPORT_PROGRAMS, new lang_string('programs', 'tool_program'));
            $mform->setType(self::EXPORT_PROGRAMS, PARAM_INT);
            $mform->setDefault(self::EXPORT_PROGRAMS, 1);
            if (!$this->can_export_chained_entity('tool_program')) {
                $form->freeze_at(self::EXPORT_PROGRAMS, 0);
            }
        }

        // Whether certifications should be included.
        if (core_component::get_component_directory('tool_certification')) {
            $mform->addElement('advcheckbox', self::EXPORT_CERTIFICATIONS, new lang_string('certifications', 'tool_certification'));
            $mform->setType(self::EXPORT_CERTIFICATIONS, PARAM_INT);
            $mform->setDefault(self::EXPORT_CERTIFICATIONS, 1);
            if (!$this->can_export_chained_entity('tool_certification')) {
                $form->freeze_at(self::EXPORT_CERTIFICATIONS, 0);
            }
        }

        // Whether organisation structure should be included.
        if (core_component::get_component_directory('tool_organisation')) {
            $mform->addElement('advcheckbox', self::EXPORT_ORGANISATION, new lang_string('orgstructure', 'tool_organisation'));
            $mform->setType(self::EXPORT_ORGANISATION, PARAM_INT);
            $mform->setDefault(self::EXPORT_ORGANISATION, 1);
            if (!$this->can_export_chained_entity('tool_organisation_department_framework') ||
                    !$this->can_export_chained_entity('tool_organisation_position_framework')) {

                $form->freeze_at(self::EXPORT_ORGANISATION, 0);
            }
        }

        // Whether dynamic rules should be included.
        if (core_component::get_component_directory('tool_dynamicrule')) {
            $mform->addElement('advcheckbox', self::EXPORT_RULES, new lang_string('pluginname', 'tool_dynamicrule'));
            $mform->setType(self::EXPORT_RULES, PARAM_INT);
            $mform->setDefault(self::EXPORT_RULES, 1);
            if (!$this->can_export_chained_entity('tool_dynamicrule')) {
                $form->freeze_at(self::EXPORT_RULES, 0);
            }
        }

        // Whether custom reports should be included.
        if (core_component::get_component_directory('tool_reportbuilder')) {
            $mform->addElement('advcheckbox', self::EXPORT_REPORTS, new lang_string('customreports', 'tool_reportbuilder'));
            $mform->setType(self::EXPORT_REPORTS, PARAM_INT);
            $mform->setDefault(self::EXPORT_REPORTS, 1);
            if (!$this->can_export_chained_entity('tool_reportbuilder')) {
                $form->freeze_at(self::EXPORT_REPORTS, 0);
            }
        }

        // Tenant selection (either the current tenant only, or a selection from all tenants).
        if ($tenantid = $this->get_export_tenant_id()) {
            $mform->addElement('hidden', self::EXPORT_INSTANCES);
            $mform->setType(self::EXPORT_INSTANCES, PARAM_ALPHANUM);
            $mform->setConstant(self::EXPORT_INSTANCES, self::EXPORT_INSTANCES_MANUAL);

            $mform->addElement('hidden', self::EXPORT_SELECT_MANUAL);
            $mform->setType(self::EXPORT_SELECT_MANUAL, PARAM_INT);
            $mform->setConstant(self::EXPORT_SELECT_MANUAL, $tenantid);
        } else {
            $mform->addElement('header', 'headerinstances', get_string('instances', 'tool_wp'));

            $mform->addElement('radio', self::EXPORT_INSTANCES, null,
                new lang_string('migrationselectexcludingarchived', 'tool_tenant'), self::EXPORT_INSTANCES_NO_ARCHIVED);
            $mform->addElement('radio', self::EXPORT_INSTANCES, null,
                new lang_string('migrationselectincludingarchived', 'tool_tenant'), self::EXPORT_INSTANCES_ALL);
            $mform->addElement('radio', self::EXPORT_INSTANCES, null,
                new lang_string('migrationselectmanually', 'tool_tenant'), self::EXPORT_INSTANCES_MANUAL);

            $mform->setType(self::EXPORT_INSTANCES, PARAM_ALPHANUM);
            $mform->setDefault(self::EXPORT_INSTANCES, self::EXPORT_INSTANCES_NO_ARCHIVED);

            // Create element to allow user to limit which tenants to export (excluding the "Shared space").
            $tenantselect = array_map(static function(tenant $tenant): string {
                return $tenant->get_formatted_name();
            }, tenant::get_records_select('id != :sharedspace', [
                'sharedspace' => sharedspace::get_shared_space_id() ?? 0,
            ], 'name'));

            $mform->addElement('autocomplete', self::EXPORT_SELECT_MANUAL, new lang_string('tenants', 'tool_tenant'),
                $tenantselect, ['multiple' => true])->setHiddenLabel(true);
            $mform->setType(self::EXPORT_SELECT_MANUAL, PARAM_INT);
            $mform->hideIf(self::EXPORT_SELECT_MANUAL, self::EXPORT_INSTANCES, 'ne', self::EXPORT_INSTANCES_MANUAL);

            $form->add_validation_callback([$this, 'validate_options_form']);
        }
    }

    /**
     * Summary of the export settings for the review step and also for the report page
     *
     * @param bool $exportcompleted
     * @return string
     */
    public function get_summary_for_review_step(bool $exportcompleted): string {
        global $OUTPUT;

        $settings = $this->get_export_settings();

        $settingssummary = [
            ['name' => get_string('tenantdetails', 'tool_tenant'), 'value' => !empty($settings[self::EXPORT_DETAILS])],
            ['name' => get_string('appearance'), 'value' => !empty($settings[self::EXPORT_APPEARANCE])],
            ['name' => get_string('users'), 'value' => !empty($settings[self::EXPORT_USERS])],
            ['name' => get_string('migrationcoursecategories', 'tool_tenant'),
                'value' => !empty($settings[self::EXPORT_CATEGORIES])],
        ];

        if (!empty($settings[self::EXPORT_CATEGORIES])) {
            $settingssummary[] = [
                'name' => get_string('includecoursecontent', 'tool_wp'),
                'value' => !empty($settings[self::EXPORT_COURSE_CONTENT])
            ];

            $settingssummary[] = [
                'name' => get_string('certificatetemplates', 'tool_wp'),
                'value' => !empty($settings[self::EXPORT_CERTIFICATES])
            ];
        }

        if (core_component::get_component_directory('tool_program')) {
            $settingssummary[] = [
                'name' => get_string('programs', 'tool_program'),
                'value' => !empty($settings[self::EXPORT_PROGRAMS]),
            ];
        }

        if (core_component::get_component_directory('tool_certification')) {
            $settingssummary[] = [
                'name' => get_string('certifications', 'tool_certification'),
                'value' => !empty($settings[self::EXPORT_CERTIFICATIONS]),
            ];
        }

        if (core_component::get_component_directory('tool_organisation')) {
            $settingssummary[] = [
                'name' => get_string('orgstructure', 'tool_organisation'),
                'value' => !empty($settings[self::EXPORT_ORGANISATION]),
            ];
        }

        if (core_component::get_component_directory('tool_dynamicrule')) {
            $settingssummary[] = [
                'name' => get_string('pluginname', 'tool_dynamicrule'),
                'value' => !empty($settings[self::EXPORT_RULES]),
            ];
        }

        if (core_component::get_component_directory('tool_reportbuilder')) {
            $settingssummary[] = [
                'name' => get_string('customreports', 'tool_reportbuilder'),
                'value' => !empty($settings[self::EXPORT_REPORTS]),
            ];
        }

        return $OUTPUT->render_from_template('tool_wp/exportimport_summary', ['settings' => $settingssummary]);
    }

    /**
     * Returns the list of entities that will be exported
     *
     * @param string $entityname
     * @return array[]
     */
    public function get_instances_for_review_step(string $entityname): array {
        if (strcmp($entityname, tenant::TABLE) !== 0) {
            return [];
        }

        // Return enough data as used by the instance review callback.
        return array_map(static function(tenant $tenant): array {
            return [
                'id' => $tenant->get('id'),
                'name' => $tenant->get_formatted_name(),
            ];
        }, $this->get_tenants_for_export());
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

            $errors[self::EXPORT_SELECT_MANUAL] = new lang_string('required');
        }

        return $errors;
    }

    /**
     * Performs the export
     *
     * @return void
     */
    public function perform_export(): void {
        global $DB;

        $settings = $this->get_export_settings();
        if (empty($settings[self::EXPORT_INSTANCES])) {
            return;
        }

        // Preserve the current tenant ID, so we can switch back once complete.
        $currenttenantid = tenancy::get_tenant_id();

        foreach ($this->get_tenants_for_export() as $tenant) {
            $record = $tenant->to_record();

            // The chained entities rely on the "current" tenant, so switch to it.
            tenancy::set_switched_tenant_id($record->id);

            // If we aren't exporting appearance, then remove it.
            if (empty($settings[self::EXPORT_APPEARANCE])) {
                unset($record->cssconfig);
            }

            $export = $this->prepare_data_for_workplace_export(tenant::TABLE, (array) $record);

            // Include any appearance related tenant files.
            if (!empty($settings[self::EXPORT_APPEARANCE])) {
                $filestorage = get_file_storage();

                $files = $DB->get_records_select('files', 'contextid = ? AND component = ? AND itemid = ? AND filename != ?', [
                    context_system::instance()->id,
                    'tool_tenant',
                    $record->id,
                    '.',
                ]);

                foreach ($files as $file) {
                    $export->add_file($filestorage->get_file_instance($file));
                }
            }

            $export->export();

            // Determine whether to include users, and also whether chained entities should also include user data.
            $exportusers = !empty($settings[self::EXPORT_USERS]) && $this->can_export_chained_entity('user');

            // Whether categories should be included (& cohorts/course structure), plus certificates that belong to them.
            if (!empty($settings[self::EXPORT_CATEGORIES]) && $this->can_export_chained_entity('course_categories') &&
                    $category = $tenant->get_category()) {

                $this->process_chained_entities('course_categories', [$category->id], [
                    coursecategories::EXPORT_COURSES_CONTENT => !empty($settings[self::EXPORT_COURSE_CONTENT]),
                    coursecategories::EXPORT_CERTIFICATE_TEMPLATES => !empty($settings[self::EXPORT_CERTIFICATES]),
                    coursecategories::EXPORT_COHORTS => $this->get_export_setting(self::EXPORT_COHORTS, true),
                    coursecategories::EXPORT_COHORT_MEMBERS => $exportusers,
                ]);
            }

            // If we are exporting users, export "tenant user" data following by each user entity.
            if ($exportusers) {
                [$join, $where, $params] = tenancy::get_users_sql('u', $record->id);

                // Tenant user records may not exist for users in default tenant, so construct manually.
                $tenantusers = $DB->get_fieldset_sql("SELECT u.id FROM {user} u {$join} WHERE {$where}", $params);
                foreach ($tenantusers as $tenantuser) {
                    $this->prepare_data_for_workplace_export(tenant_user::TABLE, [
                        'id' => $tenantuser,
                        'tenantid' => $record->id,
                        'userid' => $tenantuser,
                    ])
                        ->add_mappings('tenantid', 'tool_tenant')
                        ->add_mappings('userid', 'user')
                        ->export();
                }

                $this->process_chained_entities('user', $tenantusers);
            }

            // Whether programs should be included.
            if (!empty($settings[self::EXPORT_PROGRAMS]) && $this->can_export_chained_entity('tool_program')) {
                $this->process_chained_entities('tool_program', [], [
                    programs::EXPORT_INSTANCES => programs::EXPORT_INSTANCES_ALL,
                    programs::EXPORT_COURSE_BACKUPS => 0,
                    programs::EXPORT_USER_ALLOCATIONS => $exportusers,
                    programs::EXPORT_PROGRAM_DYNAMICRULES => !empty($settings[self::EXPORT_RULES]),
                ]);
            }

            // Whether certifications should be included.
            if (!empty($settings[self::EXPORT_CERTIFICATIONS]) && $this->can_export_chained_entity('tool_certification')) {
                $this->process_chained_entities('tool_certification', [], [
                    certifications::EXPORT_INSTANCES => certifications::EXPORT_INSTANCES_ALL,
                    certifications::EXPORT_PROGRAMS => 0,
                    certifications::EXPORT_COURSE_BACKUPS => 0,
                    certifications::EXPORT_USER_ALLOCATIONS => $exportusers,
                    certifications::EXPORT_DYNAMICRULES => !empty($settings[self::EXPORT_RULES]),
                ]);
            }

            // Whether organisation structure should be included (plus jobs when including users).
            if (!empty($settings[self::EXPORT_ORGANISATION]) &&
                    $this->can_export_chained_entity('tool_organisation_department_framework') &&
                    $this->can_export_chained_entity('tool_organisation_position_framework')) {

                $this->process_chained_entities('tool_organisation_department_framework', [], [
                    orgstructure::EXPORT_INSTANCES_ALL => orgstructure::EXPORT_INSTANCES_ALL,
                ]);
                $this->process_chained_entities('tool_organisation_position_framework', [], [
                    orgstructure::EXPORT_INSTANCES => orgstructure::EXPORT_INSTANCES_ALL,
                ]);

                if ($exportusers && $this->can_export_chained_entity('tool_organisation_job')) {
                    $this->process_chained_entities('tool_organisation_job', [
                        jobs::EXPORT_FRAMEWORKS => 0,
                    ]);
                }
            }

            // Whether dynamic rules should be included.
            if (!empty($settings[self::EXPORT_RULES]) && $this->can_export_chained_entity('tool_dynamicrule')) {
                $this->process_chained_entities('tool_dynamicrule', []);
            }

            // Whether custom reports should be included.
            if (!empty($settings[self::EXPORT_REPORTS]) && $this->can_export_chained_entity('tool_reportbuilder')) {
                $this->process_chained_entities('tool_reportbuilder', [], [
                    customreports::EXPORT_INSTANCES => customreports::EXPORT_INSTANCES_ALL,
                    customreports::EXPORT_AUDIENCES => $exportusers,
                    customreports::EXPORT_SCHEDULES => $exportusers,
                ]);
            }
        }

        tenancy::set_switched_tenant_id($currenttenantid);
    }

    /**
     * Return array of tenants to export (excluding the "Shared space")
     *
     * @return tenant[]
     */
    private function get_tenants_for_export(): array {
        global $DB;

        $select = 'id != :sharedspace';
        $params = [
            'sharedspace' => sharedspace::get_shared_space_id() ?? 0,
        ];

        // Set tenant query parameters based on "export instances" field.
        $exportinstances = $this->get_export_setting(self::EXPORT_INSTANCES);
        switch ($exportinstances) {
            case self::EXPORT_INSTANCES_NO_ARCHIVED :
                $select .= ' AND archived = :archived';
                $params['archived'] = 0;
            break;

            case self::EXPORT_INSTANCES_MANUAL :
                [$manualselect, $manualparams] = $DB->get_in_or_equal($this->get_export_setting(self::EXPORT_SELECT_MANUAL, []),
                    SQL_PARAMS_NAMED, 't');
                $select .= " AND id {$manualselect}";
                $params = array_merge($params, $manualparams);
            break;
        }

        return tenant::get_records_select($select, $params, 'name');
    }
}
