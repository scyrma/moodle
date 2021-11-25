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
 * Tenants importer
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant\tool_wp\importer;

use context_system;
use core_component;
use lang_string;
use moodle_exception;
use moodle_url;
use tool_certification\tool_wp\importer\certifications;
use tool_organisation\tool_wp\importer\jobs;
use tool_program\tool_wp\importer\programs;
use tool_reportbuilder\tool_wp\exporter\customreports;
use tool_tenant\manager;
use tool_tenant\permission;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use tool_tenant\tenant;
use tool_tenant\tenant_user;
use tool_wp\importer_base;
use tool_wp\local\exportimport\forms\import_conflict_form;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\wp_imported_entity;
use tool_wp\tool_wp\importer\coursecategories;

/**
 * Importer class
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tenants extends importer_base {

    /** @var string Element for including tenant details. */
    const IMPORT_DETAILS = 'import_details';

    /** @var string Element for including tenant appearance. */
    const IMPORT_APPEARANCE = 'import_appearance';

    /** @var string Element for including tenant users. */
    const IMPORT_USERS = 'import_users';

    /** @var string Element for including categories (& cohorts/course structure). */
    const IMPORT_CATEGORIES = 'import_categories';

    /** @var string Element for selecting whether all (except logs) course content should be imported. */
    const IMPORT_COURSES_CONTENT_ALL = 'import_courses_content_all';

    /** @var string Element for including cohorts (overrides previous setting). */
    const IMPORT_COHORTS = 'import_cohorts';

    /** @var string Element for including certificates. */
    const IMPORT_CERTIFICATES = 'import_certificates';

    /** @var string Element for including programs. */
    const IMPORT_PROGRAMS = 'import_programs';

    /** @var string Element for including certifications. */
    const IMPORT_CERTIFICATIONS = 'import_certifications';

    /** @var string Element for including organisation structure (frameworks & jobs). */
    const IMPORT_ORGANISATION = 'import_organisation';

    /** @var string Element for including dynamic rules. */
    const IMPORT_RULES = 'import_rules';

    /** @var string Element for including custom reports. */
    const IMPORT_REPORTS = 'import_reports';

    /** @var string Element for selecting destination of tenants. */
    const IMPORT_DESTINATION = 'import_destination';

    /** @var string Import as new tenants. */
    const IMPORT_DESTINATION_NEW = 'new';

    /** @var string Import/merge into existing tenant. */
    const IMPORT_DESTINATION_MERGE = 'merge';

    /** @var string Element for manually selecting which tenant to merge into. */
    const IMPORT_SELECT_DESTINATION_TENANT = 'destination_tenant';

    /** @var string Element for selecting which tenants to include. */
    const IMPORT_INSTANCES = 'import_instances';

    /** @var string Include all tenants. */
    const IMPORT_INSTANCES_ALL = 'all';

    /** @var string Include manually selected tenants. */
    const IMPORT_INSTANCES_MANUAL = 'manual';

    /** @var string Element for manually selecting which tenants to include. */
    const IMPORT_SELECT_MANUAL = 'select_tenants';

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
        return get_string('tenants', 'tool_tenant');
    }

    /**
     * Define availability of importer, checks capabilities and entry point
     *
     * @return bool
     */
    public function is_available(): bool {
        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint()) && permission::can_create_tenant();
    }

    /**
     * Does this importer "work" only within one tenant? The import of tenants themselves should return false
     *
     * @return bool
     */
    public function is_tenant_required(): bool {
        return false;
    }

    /**
     * Don't prompt the user to select a "destination" tenant during import, we'll handle this ourselves
     *
     * @return bool
     */
    public function is_destination_tenant_required(): bool {
        return false;
    }

    /**
     * Initialise the importer, register all entities/error/notices
     */
    protected function initialise() {
        $this->register_entity(tenant::TABLE, [
            self::ENTITY_DEPENDENCIES => [],
            self::ENTITY_INDIVIDUALIMPORT => static function(array $ids, array $settings): array {
                $defaults = [
                    self::IMPORT_INSTANCES => self::IMPORT_INSTANCES_MANUAL,
                    self::IMPORT_APPEARANCE => 1,
                    self::IMPORT_USERS => 1,
                    self::IMPORT_CATEGORIES => 1,
                    self::IMPORT_COURSES_CONTENT_ALL => 0,
                    self::IMPORT_COHORTS => 1,
                    self::IMPORT_CERTIFICATES => 1,
                    self::IMPORT_PROGRAMS => 1,
                    self::IMPORT_CERTIFICATIONS => 1,
                    self::IMPORT_ORGANISATION => 1,
                    self::IMPORT_RULES => 1,
                    self::IMPORT_REPORTS => 1,
                ];

                return array_intersect_key($settings, $defaults) + $defaults + [
                    self::IMPORT_DESTINATION => self::IMPORT_DESTINATION_NEW,
                    self::IMPORT_SELECT_MANUAL => $ids,
                ];
            },
            self::ENTITY_LOGSUCCESS => static function(int $id, array $details): string {
                return get_string('migrationlogsuccess', 'tool_tenant', [
                    'url' => (new moodle_url('/admin/tool/tenant/edit.php', ['id' => $id]))->out(),
                    'name' => format_string($details['name']),
                ]);
            },
            self::ENTITY_LOGERROR => static function(array $details): string {
                return get_string('migrationlogerror', 'tool_tenant', format_string($details['name']));
            },
            self::ENTITY_NAMEPLURAL => $this->get_name(),
            self::ENTITY_INSTANCENAME_FOR_REVIEW => static function(array $details): string {
                return format_string($details['name']);
            },
        ]);

        $this->register_potential_error(tenant::TABLE, 'idnumberconflict', [
            self::ERROR_LOG => static function(array $details): string {
                return get_string('exportimporterrorentityexists', 'tool_wp', 'idnumber');
            },
            self::ERROR_CONFLICTHEADER => get_string('exportimporterrorentityexists', 'tool_wp', 'idnumber'),
            self::ERROR_CONFLICTSOLUTION => static function(array $settings, bool $forform): ?string {
                if (strcmp($settings['action'], 'increment') === 0) {
                    return get_string('exportimportconflictsuffix', 'tool_wp', 'idnumber');
                }
                return null;
            },
        ]);

        $this->register_potential_notice(tenant::TABLE, 'idnumberchanged', [
            self::NOTICE_LOG => static function(array $details, array $noticedetails): string {
                return get_string('exportimportfieldchanged', 'tool_wp', array_map('s', $noticedetails));
            },
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
        $mform->addElement('advcheckbox', self::IMPORT_DETAILS, new lang_string('tenantdetails', 'tool_tenant'));
        $mform->setDefault(self::IMPORT_DETAILS, 1);
        $form->freeze_at(self::IMPORT_DETAILS, 1);

        // Whether tenant appearance data should be included.
        $mform->addElement('advcheckbox', self::IMPORT_APPEARANCE, new lang_string('appearance'));
        $mform->setType(self::IMPORT_APPEARANCE, PARAM_INT);
        $mform->setDefault(self::IMPORT_APPEARANCE, 1);

        // Whether tenant users should be included (note that users cannot be imported back into the same site).
        $mform->addElement('advcheckbox', self::IMPORT_USERS, new lang_string('users'));
        $mform->setType(self::IMPORT_USERS, PARAM_INT);
        $mform->setDefault(self::IMPORT_USERS, 1);
        if (!$this->can_import_chained_entity('user') || $this->get_entities_in_workplace_export_file('user')->count() == 0) {

            $form->freeze_at(self::IMPORT_USERS, 0);
        }

        // Whether categories should be included (& cohorts/course structure).
        $mform->addElement('advcheckbox', self::IMPORT_CATEGORIES, new lang_string('migrationcoursecategories', 'tool_tenant'));
        $mform->setType(self::IMPORT_CATEGORIES, PARAM_INT);
        $mform->setDefault(self::IMPORT_CATEGORIES, 1);
        if (!$this->can_import_chained_entity('course_categories') ||
                $this->get_entities_in_workplace_export_file('course_categories')->count() == 0) {

            $form->freeze_at(self::IMPORT_CATEGORIES, 0);
        }

        // Whether certificates should be included.
        $mform->addElement('advcheckbox', self::IMPORT_CERTIFICATES, new lang_string('certificatetemplates', 'tool_wp'));
        $mform->setType(self::IMPORT_CERTIFICATES, PARAM_INT);
        $mform->setDefault(self::IMPORT_CERTIFICATES, 1);
        $mform->disabledIf(self::IMPORT_CERTIFICATES, self::IMPORT_CATEGORIES, 'eq', 0);
        if (!$this->can_import_chained_entity('tool_certificate_templates') ||
                $this->get_entities_in_workplace_export_file('tool_certificate_templates')->count() == 0) {

            $form->freeze_at(self::IMPORT_CERTIFICATES, 0);
        }

        // Whether programs should be included.
        if (core_component::get_component_directory('tool_program')) {
            $mform->addElement('advcheckbox', self::IMPORT_PROGRAMS, new lang_string('programs', 'tool_program'));
            $mform->setType(self::IMPORT_PROGRAMS, PARAM_INT);
            $mform->setDefault(self::IMPORT_PROGRAMS, 1);
            if (!$this->can_import_chained_entity('tool_program') ||
                    $this->get_entities_in_workplace_export_file('tool_program')->count() == 0) {

                $form->freeze_at(self::IMPORT_PROGRAMS, 0);
            }
        }

        // Whether certifications should be included.
        if (core_component::get_component_directory('tool_certification')) {
            $mform->addElement('advcheckbox', self::IMPORT_CERTIFICATIONS, new lang_string('certifications', 'tool_certification'));
            $mform->setType(self::IMPORT_CERTIFICATIONS, PARAM_INT);
            $mform->setDefault(self::IMPORT_CERTIFICATIONS, 1);
            if (!$this->can_import_chained_entity('tool_certification') ||
                    $this->get_entities_in_workplace_export_file('tool_certification')->count() == 0) {

                $form->freeze_at(self::IMPORT_CERTIFICATIONS, 0);
            }
        }

        // Whether organisation structure should be included.
        if (core_component::get_component_directory('tool_organisation')) {
            $mform->addElement('advcheckbox', self::IMPORT_ORGANISATION, new lang_string('orgstructure', 'tool_organisation'));
            $mform->setType(self::IMPORT_ORGANISATION, PARAM_INT);
            $mform->setDefault(self::IMPORT_ORGANISATION, 1);

            $canimportdepartment = $this->can_import_chained_entity('tool_organisation_department_framework') &&
                $this->get_entities_in_workplace_export_file('tool_organisation_department_framework')->count() > 0;
            $canimportposition = $this->can_import_chained_entity('tool_organisation_position_framework') &&
                $this->get_entities_in_workplace_export_file('tool_organisation_position_framework')->count() > 0;

            if (!$canimportdepartment || !$canimportposition) {
                $form->freeze_at(self::IMPORT_ORGANISATION, 0);
            }
        }

        // Whether dynamic rules should be included.
        if (core_component::get_component_directory('tool_dynamicrule')) {
            $mform->addElement('advcheckbox', self::IMPORT_RULES, new lang_string('pluginname', 'tool_dynamicrule'));
            $mform->setType(self::IMPORT_RULES, PARAM_INT);
            $mform->setDefault(self::IMPORT_RULES, 1);
            if (!$this->can_import_chained_entity('tool_dynamicrule') ||
                    $this->get_entities_in_workplace_export_file('tool_dynamicrule')->count() == 0) {

                $form->freeze_at(self::IMPORT_RULES, 0);
            }
        }

        // Whether custom reports should be included.
        if (core_component::get_component_directory('tool_reportbuilder')) {
            $mform->addElement('advcheckbox', self::IMPORT_REPORTS, new lang_string('customreports', 'tool_reportbuilder'));
            $mform->setType(self::IMPORT_REPORTS, PARAM_INT);
            $mform->setDefault(self::IMPORT_REPORTS, 1);
            if (!$this->can_import_chained_entity('tool_reportbuilder') ||
                    $this->get_entities_in_workplace_export_file('tool_reportbuilder')->count() == 0) {

                $form->freeze_at(self::IMPORT_REPORTS, 0);
            }
        }

        // Tenant destination. If we are importing a single tenant then allow user to "merge" it into an existing tenant.
        $mform->addElement('header', 'destinationheader', get_string('importdestination', 'tool_wp'));
        $mform->setExpanded('destinationheader');

        $mform->addElement('radio', self::IMPORT_DESTINATION, null,
            new lang_string('migrationmerge', 'tool_tenant'), self::IMPORT_DESTINATION_MERGE);

        // Create element to specify which tenant they want to merge into (excluding the "Shared space").
        $tenantmergeselect = array_map(static function(tenant $tenant): string {
            return $tenant->get_formatted_name();
        }, tenant::get_records_select('id != :sharedspace', [
            'sharedspace' => sharedspace::get_shared_space_id() ?? 0,
        ], 'name'));

        $mform->addElement('autocomplete', self::IMPORT_SELECT_DESTINATION_TENANT,
            new lang_string('migrationmergeselecttenant', 'tool_tenant'), $tenantmergeselect, ['multiple' => false])
            ->setHiddenLabel(true);
        $mform->setType(self::IMPORT_SELECT_DESTINATION_TENANT, PARAM_INT);
        $mform->setDefault(self::IMPORT_SELECT_DESTINATION_TENANT, tenancy::get_tenant_id());
        $mform->hideIf(self::IMPORT_SELECT_DESTINATION_TENANT, self::IMPORT_DESTINATION, 'ne', self::IMPORT_DESTINATION_MERGE);

        $mform->addElement('radio', self::IMPORT_DESTINATION, null,
            new lang_string('migrationcreate', 'tool_tenant'), self::IMPORT_DESTINATION_NEW);

        $mform->setType(self::IMPORT_DESTINATION, PARAM_ALPHANUM);
        $mform->setDefault(self::IMPORT_DESTINATION, self::IMPORT_DESTINATION_NEW);
        $mform->addHelpButton(self::IMPORT_DESTINATION, 'migrationmerge', 'tool_tenant');

        // Tenant selection, to import from exported file.
        $mform->addElement('header', 'headerinstances', get_string('instances', 'tool_wp'));
        $mform->setExpanded('headerinstances');

        $mform->addElement('radio', self::IMPORT_INSTANCES, null,
            new lang_string('migrationselectalltenants', 'tool_tenant'), self::IMPORT_INSTANCES_ALL);
        $mform->addElement('radio', self::IMPORT_INSTANCES, null,
            new lang_string('migrationselectmanually', 'tool_tenant'), self::IMPORT_INSTANCES_MANUAL);

        $mform->setType(self::IMPORT_INSTANCES, PARAM_ALPHANUM);
        $mform->setDefault(self::IMPORT_INSTANCES, self::IMPORT_INSTANCES_ALL);

        // Create element to allow user to limit which tenants to export.
        $tenantimportselect = array_map('format_string',
            $this->get_entities_in_workplace_export_file(tenant::TABLE)->get_menu('name'));
        \core_collator::asort($tenantimportselect);

        $mform->addElement('autocomplete', self::IMPORT_SELECT_MANUAL, new lang_string('tenants', 'tool_tenant'),
            $tenantimportselect, ['multiple' => true])->setHiddenLabel(true);
        $mform->setType(self::IMPORT_SELECT_MANUAL, PARAM_INT);
        $mform->hideIf(self::IMPORT_SELECT_MANUAL, self::IMPORT_INSTANCES, 'ne', self::IMPORT_INSTANCES_MANUAL);

        $form->add_validation_callback([$this, 'validate_options_form']);
    }

    /**
     * Summary of the import settings for the review step and also for the report page
     *
     * @param bool $importcompleted
     * @return string
     */
    public function get_summary_for_review_step(bool $importcompleted): string {
        global $OUTPUT;

        $settings = $this->get_import_settings();

        $settingssummary = [
            ['name' => get_string('tenantdetails', 'tool_tenant'), 'value' => !empty($settings[self::IMPORT_DETAILS])],
            ['name' => get_string('appearance'), 'value' => !empty($settings[self::IMPORT_APPEARANCE])],
            ['name' => get_string('users'), 'value' => !empty($settings[self::IMPORT_USERS])],
            ['name' => get_string('migrationcoursecategories', 'tool_tenant'),
                'value' => !empty($settings[self::IMPORT_CATEGORIES])],
            ['name' => get_string('certificatetemplates', 'tool_wp'), 'value' => !empty($settings[self::IMPORT_CERTIFICATES])],
        ];

        if (core_component::get_component_directory('tool_program')) {
            $settingssummary[] = [
                'name' => get_string('programs', 'tool_program'),
                'value' => !empty($settings[self::IMPORT_PROGRAMS]),
            ];
        }

        if (core_component::get_component_directory('tool_certification')) {
            $settingssummary[] = [
                'name' => get_string('certifications', 'tool_certification'),
                'value' => !empty($settings[self::IMPORT_CERTIFICATIONS]),
            ];
        }

        if (core_component::get_component_directory('tool_organisation')) {
            $settingssummary[] = [
                'name' => get_string('orgstructure', 'tool_organisation'),
                'value' => !empty($settings[self::IMPORT_ORGANISATION]),
            ];
        }

        if (core_component::get_component_directory('tool_dynamicrule')) {
            $settingssummary[] = [
                'name' => get_string('pluginname', 'tool_dynamicrule'),
                'value' => !empty($settings[self::IMPORT_RULES]),
            ];
        }

        if (core_component::get_component_directory('tool_reportbuilder')) {
            $settingssummary[] = [
                'name' => get_string('customreports', 'tool_reportbuilder'),
                'value' => !empty($settings[self::IMPORT_REPORTS]),
            ];
        }

        // Destination.
        $settingssummary[] = [
            'name' => get_string('migrationdestinationsummary', 'tool_tenant',
                $settings[self::IMPORT_DESTINATION] == self::IMPORT_DESTINATION_NEW
                    ? get_string('migrationcreate', 'tool_tenant')
                    : tenancy::get_tenant_name_from_id($settings[self::IMPORT_SELECT_DESTINATION_TENANT])
            ),
            'value' => 1,
        ];

        return $OUTPUT->render_from_template('tool_wp/exportimport_summary', ['settings' => $settingssummary]);
    }

    /**
     * Summary of entities included in the export file
     *
     * @return array
     */
    public function get_export_file_content_for_overview_page(): array {
        $overview = [];

        if ($tenantcount = $this->get_entities_in_workplace_export_file(tenant::TABLE)->count()) {
            $overview[] = $this->get_entity_display_name_plural(tenant::TABLE) .
                get_string('entitiescountpostfix', 'tool_wp', $tenantcount);
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

        // Ensure user has selected a destination for the tenants.
        if ($data[self::IMPORT_DESTINATION] === self::IMPORT_DESTINATION_MERGE) {
            if (empty($data[self::IMPORT_SELECT_DESTINATION_TENANT])) {
                $errors[self::IMPORT_SELECT_DESTINATION_TENANT] = new lang_string('required');
            } else {
                // When choosing to merge imported tenants into an existing tenant, there must only be a single instance.
                if ($data[self::IMPORT_INSTANCES] === self::IMPORT_INSTANCES_ALL
                        && count($this->get_entities_in_workplace_export_file(tenant::TABLE)) > 1) {

                    $errors[self::IMPORT_SELECT_DESTINATION_TENANT] = new lang_string('migrationmergetoomany', 'tool_tenant');
                } else if ($data[self::IMPORT_INSTANCES] === self::IMPORT_INSTANCES_MANUAL
                        && count($data[self::IMPORT_SELECT_MANUAL]) > 1) {

                    $errors[self::IMPORT_SELECT_DESTINATION_TENANT] = new lang_string('migrationmergetoomany', 'tool_tenant');
                }
            }
        }

        // Ensure user has selected something to export.
        if ($data[self::IMPORT_INSTANCES] === self::IMPORT_INSTANCES_MANUAL
                && empty($data[self::IMPORT_SELECT_MANUAL])) {

            $errors[self::IMPORT_SELECT_MANUAL] = new lang_string('required');
        }

        return $errors;
    }

    /**
     * Add importer errors to the conflict resolution form
     *
     * @param import_conflict_form $form
     * @param string $importedentity
     * @param string $errorcode
     * @param array $details
     * @param bool $addskipaction
     */
    public function add_to_conflict_form(import_conflict_form $form, string $importedentity, string $errorcode, array $details,
            bool $addskipaction = true): void {

        parent::add_to_conflict_form($form, $importedentity, $errorcode, $details, $addskipaction);

        $mform = $form->get_quick_form();

        if (strcmp($errorcode, 'idnumberconflict') === 0) {
            $elementname = $this->get_conflict_form_element_name($importedentity, $errorcode);

            $mform->addElement('radio', $elementname, null,
                $this->get_conflict_solution($importedentity, $errorcode, ['action' => 'increment']), 'increment');
            $mform->setType($elementname, PARAM_ALPHANUMEXT);
            $mform->setDefault($elementname, 'increment');
        }
    }

    /**
     * Perform the import
     *
     * @param string $entityname
     * @return void
     */
    public function perform_import(string $entityname): void {
        if (strcmp($entityname, tenant::TABLE) !== 0) {
            return;
        }

        $settings = $this->get_import_settings();
        if (empty($settings[self::IMPORT_INSTANCES])) {
            return;
        }

        // Preserve the current tenant ID, so we can switch back once complete.
        $currenttenantid = tenancy::get_tenant_id();
        $manager = new manager();

        $tenantids = $settings[self::IMPORT_SELECT_MANUAL] ?? [];
        if (count($tenantids) > 0) {
            $filter = static function(array $entity) use ($tenantids): bool {
                return in_array($entity['id'], $tenantids);
            };
        } else {
            $filter = null;
        }

        $tenants = $this->get_entities_in_workplace_export_file(tenant::TABLE, $filter);
        foreach ($tenants as $tenant) {
            $tenant
                ->exclude_fields(['isdefault'])
                ->set_validation_callback(function(array $data): void {
                    $this->add_details_to_log([
                        'name' => $data['name'],
                    ]);

                    // If tenant idnumber is set, ensure uniqueness.
                    if (!empty($data['idnumber']) && tenant::record_exists_select('idnumber = ?', [$data['idnumber']])) {
                        $this->add_error_to_log('idnumberconflict');
                    }
                })
                ->set_import_callback(function(array $data, wp_imported_entity $entity) use ($settings, $manager): int {
                    $record = (object) array_intersect_key($data, tenant::properties_definition());
                    $record->idnumber = $this->unique_idnumber($record->idnumber);

                    // If we aren't importing appearance, then remove it.
                    if (empty($settings[self::IMPORT_APPEARANCE])) {
                        unset($record->cssconfig);
                    }

                    // Category will be updated later on.
                    unset($record->categoryid);

                    // If merging then update the destination tenant (excluding "Shared space"), otherwise create a new one.
                    if ($settings[self::IMPORT_DESTINATION] == self::IMPORT_DESTINATION_MERGE) {
                        if (sharedspace::is_shared_space($settings[self::IMPORT_SELECT_DESTINATION_TENANT])) {
                            throw new moodle_exception('tenantnotfound', 'tool_tenant');
                        }

                        return $manager->update_tenant($settings[self::IMPORT_SELECT_DESTINATION_TENANT], $record)->get('id');
                    } else {
                        // We need to re-check user can still create tenants and hasn't gone over any configured limits.
                        permission::require_can_create_tenant();

                        return $manager->create_tenant($record)->get('id');
                    }
                })
                ->import($this);

            if (!$this->is_collecting_errors() && !$tenant->get_new_id()) {
                continue;
            }

            // If importing appearance and there are some files present in the export, replace current tenant files.
            $importedtenantid = $tenant->get_new_id();
            if ($importedtenantid > 0 &&
                    !empty($settings[self::IMPORT_APPEARANCE]) && $tenant->get_raw_field('_files')) {

                $manager->remove_tenant_images($importedtenantid);

                $tenant->import_files_for_itemid([
                    'contextid' => context_system::instance()->id,
                    'component' => 'tool_tenant',
                    'itemid' => $tenant->get_original_id(),
                ]);
            }

            // The chained entities rely on the "current" tenant, so switch to it.
            if ($importedtenantid > 0) {
                tenancy::set_switched_tenant_id($importedtenantid);
            }

            // Determine whether to include users, and also whether chained entities should also include user data.
            $importusers = !empty($settings[self::IMPORT_USERS]) && $this->can_import_chained_entity('user');

            // Create a filter to use for entities to extract only those related to the tenant being imported.
            $entitytenantfilter = static function(array $data) use ($tenant): bool {
                return $data['tenantid'] == $tenant->get_original_id();
            };

            // If we are importing users, import user entity according to "tenant user" data.
            $tenantuserids = [];
            if ($importusers) {
                $tenantusers = $this->get_entities_in_workplace_export_file(tenant_user::TABLE, $entitytenantfilter);

                // If we have any tenant users, transform the entities into an array of user ID's and import them.
                if ($tenantusers->count() > 0) {
                    $tenantuserids = array_map(static function(wp_imported_entity $entity): int {
                        return $entity->get_raw_field('userid');
                    }, iterator_to_array($tenantusers, false));

                    $this->process_chained_entities('user', $tenantuserids);
                }
            }

            // Whether categories should be included (& cohorts/course structure), plus certificates that belong to them.
            if (!empty($settings[self::IMPORT_CATEGORIES]) && $this->can_import_chained_entity('course_categories') &&
                    $tenantcategoryid = $tenant->get_raw_field('categoryid')) {

                $this->process_chained_entities('course_categories', [$tenantcategoryid], [
                    coursecategories::IMPORT_COURSES_CONTENT_ALL => !empty($settings[self::IMPORT_COURSES_CONTENT_ALL]),
                    coursecategories::IMPORT_CERTIFICATE_TEMPLATES => !empty($settings[self::IMPORT_CERTIFICATES]),
                    coursecategories::IMPORT_COHORTS => $this->get_import_setting(self::IMPORT_COHORTS, true),
                    coursecategories::IMPORT_COHORT_MEMBERS => $importusers,
                ]);

                if ($importedtenantid > 0) {
                    $manager->update_tenant($importedtenantid, (object) [
                        'categoryid' => $this->get_mapping('course_categories', $tenantcategoryid),
                    ]);
                }
            }

            // Whether programs should be included.
            if (!empty($settings[self::IMPORT_PROGRAMS]) && $this->can_import_chained_entity('tool_program')) {
                $programs = $this->get_entities_in_workplace_export_file('tool_program', $entitytenantfilter);

                // Transform program entities into an array of ID's.
                $programids = array_map(static function(wp_imported_entity $entity): int {
                    return $entity->get_original_id();
                }, iterator_to_array($programs, false));

                $this->process_chained_entities('tool_program', $programids, [
                    programs::IMPORT_COURSE_BACKUPS => 0,
                    programs::IMPORT_USER_ALLOCATIONS => $importusers,
                    programs::IMPORT_PROGRAM_DYNAMICRULES => !empty($settings[self::IMPORT_RULES]),
                ]);

                if ($importusers) {
                    // Find all shared programs with user allocations that need to be imported and import them.
                    $allocationsids = $this->get_entities_in_workplace_export_file('tool_program_users',
                        function($programuser) use ($tenantuserids, $programids) {
                            // Filter only for users who belong to this tenant and for programs that were not imported.
                            return in_array($programuser['userid'], $tenantuserids) &&
                                !in_array($programuser['programid'], $programids);
                        })->get_menu('id');

                    if ($allocationsids) {
                        $this->process_chained_entities('tool_program_users', array_values($allocationsids),
                            [programs::IMPORT_USER_ALLOCATIONS => $importusers]);
                    }
                }
            }

            // Whether certifications should be included.
            if (!empty($settings[self::IMPORT_CERTIFICATIONS]) && $this->can_import_chained_entity('tool_certification')) {
                $certifications = $this->get_entities_in_workplace_export_file('tool_certification', $entitytenantfilter);

                // Transform certification entities into an array of ID's.
                $certificationids = array_map(static function(wp_imported_entity $entity): int {
                    return $entity->get_original_id();
                }, iterator_to_array($certifications, false));

                $this->process_chained_entities('tool_certification', $certificationids, [
                    certifications::IMPORT_PROGRAMS => 0,
                    certifications::IMPORT_COURSE_BACKUPS => 0,
                    certifications::IMPORT_USER_ALLOCATIONS => $importusers,
                    certifications::IMPORT_DYNAMICRULES => !empty($settings[self::IMPORT_RULES]),
                ]);

                if ($importusers) {
                    // Find all shared certifications with user allocations that need to be imported and import them.
                    $allocationsids = $this->get_entities_in_workplace_export_file('tool_certification_users',
                        function($certificationuser) use ($tenantuserids, $certificationids) {
                            // Filter only for users who belong to this tenant and for certifications that were not imported.
                            return in_array($certificationuser['userid'], $tenantuserids) &&
                                !in_array($certificationuser['certificationid'], $certificationids);
                        })->get_menu('id');

                    if ($allocationsids) {
                        $this->process_chained_entities('tool_certification_users', array_values($allocationsids),
                            [certifications::IMPORT_USER_ALLOCATIONS => $importusers]);
                    }
                }
            }

            // Whether organisation structure should be included (plus jobs when including users).
            if (!empty($settings[self::IMPORT_ORGANISATION]) &&
                    $this->can_import_chained_entity('tool_organisation_department_framework') &&
                    $this->can_import_chained_entity('tool_organisation_position_framework')) {

                // Department frameworks.
                $deptframeworks = $this->get_entities_in_workplace_export_file('tool_organisation_department_framework',
                    $entitytenantfilter);

                // Transform department framework entities into an array of ID's.
                $deptframeworkids = array_map(static function(wp_imported_entity $entity): int {
                    return $entity->get_original_id();
                }, iterator_to_array($deptframeworks, false));

                $this->process_chained_entities('tool_organisation_department_framework', $deptframeworkids);

                // Now position frameworks.
                $posframeworks = $this->get_entities_in_workplace_export_file('tool_organisation_position_framework',
                    $entitytenantfilter);

                // Transform position framework entities into an array of ID's.
                $posframeworkids = array_map(static function(wp_imported_entity $entity): int {
                    return $entity->get_original_id();
                }, iterator_to_array($posframeworks, false));

                $this->process_chained_entities('tool_organisation_position_framework', $posframeworkids);

                // Finally jobs.
                if ($importusers && $this->can_import_chained_entity('tool_organisation_job')) {
                    $jobs = $this->get_entities_in_workplace_export_file('tool_organisation_job', $entitytenantfilter);

                    // To import jobs, we need to get their accompanying framework (position/department) ID's.
                    $frameworkids = [];
                    foreach ($jobs as $job) {
                        $frameworkids = array_merge($frameworkids, [
                            'p' . $job->get_raw_field('positionfrmid'),
                            'd' . $job->get_raw_field('departmentfrmid'),
                        ]);
                    }

                    $this->process_chained_entities('tool_organisation_job', $frameworkids, [
                        jobs::IMPORT_FRAMEWORKS => 0,
                    ]);
                }
            }

            // Whether dynamic rules should be included.
            if (!empty($settings[self::IMPORT_RULES]) && $this->can_import_chained_entity('tool_dynamicrule')) {
                $rules = $this->get_entities_in_workplace_export_file('tool_dynamicrule', $entitytenantfilter);

                // Transform report entities into an array of ID's.
                $ruleids = array_map(static function(wp_imported_entity $entity): int {
                    return $entity->get_original_id();
                }, iterator_to_array($rules, false));

                $this->process_chained_entities('tool_dynamicrule', $ruleids);
            }

            // Whether custom reports should be included.
            if (!empty($settings[self::IMPORT_REPORTS]) && $this->can_import_chained_entity('tool_reportbuilder')) {
                $reports = $this->get_entities_in_workplace_export_file('tool_reportbuilder', $entitytenantfilter);

                // Transform report entities into an array of ID's.
                $reportids = array_map(static function(wp_imported_entity $entity): int {
                    return $entity->get_original_id();
                }, iterator_to_array($reports, false));

                $this->process_chained_entities('tool_reportbuilder', $reportids, [
                    customreports::EXPORT_AUDIENCES => $importusers,
                    customreports::EXPORT_SCHEDULES => $importusers,
                ]);
            }
        }

        tenancy::set_switched_tenant_id($currenttenantid);
    }

    /**
     * Helper method to find a unique idnumber
     *
     * @param string $originalvalue
     * @return string
     */
    private function unique_idnumber(?string $originalvalue): ?string {
        $uniquevalue = helper::find_unique_value_for_field($originalvalue, static function(string $value): bool {
            return !empty($value) && tenant::record_exists_select('idnumber = ?', [$value]);
        });

        // Inform user if field value changed.
        if (strcmp($originalvalue, $uniquevalue) !== 0) {
            $this->add_notice_to_log('idnumberchanged', [
                'field' => 'idnumber',
                'from' => $originalvalue,
                'to' => $uniquevalue,
            ]);
        }

        return $uniquevalue;
    }
}
