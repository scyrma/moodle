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
 * Full site importer
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\importer;

use context_system;
use core_component;
use core_course_category;
use lang_string;
use tool_tenant\tool_wp\importer\tenants;
use tool_wp\importer_base;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\wp_imported_entities;
use tool_wp\local\exportimport\wp_imported_entity;

/**
 * Importer class
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class site extends importer_base {

    /** @var string Name used for imported entity. */
    const ENTITY_NAME = 'site';

    /** @var string Element for including tenant details. */
    const IMPORT_TENANT_DETAILS = 'import_details';

    /** @var string Element for including tenant appearance. */
    const IMPORT_TENANT_APPEARANCE = 'import_appearance';

    /** @var string Element for including tenant users. */
    const IMPORT_TENANT_USERS = 'import_users';

    /** @var string Element for including tenant content (categories, courses, programs & certifications). */
    const IMPORT_TENANT_CONTENT = 'import_content';

    /** @var string Element for including cohorts. */
    const IMPORT_COHORTS = 'import_cohorts';

    /** @var string Element for including certificates. */
    const IMPORT_CERTIFICATES = 'import_certificates';

    /** @var string Element for including organisation structure (frameworks & jobs). */
    const IMPORT_ORGANISATION = 'import_organisation';

    /** @var string Element for including dynamic rules. */
    const IMPORT_RULES = 'import_rules';

    /** @var string Element for including custom reports. */
    const IMPORT_REPORTS = 'import_reports';

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
        return get_string('site');
    }

    /**
     * Does this importer "work" only within one tenant?
     *
     * @return bool
     */
    public function is_tenant_required(): bool {
        return false;
    }

    /**
     * Don't prompt the user to select a "destination" tenant during import
     *
     * @return bool
     */
    public function is_destination_tenant_required(): bool {
        return false;
    }

    /**
     * Define availability of importer, checks capabilities and entry point
     *
     * @return bool
     */
    public function is_available(): bool {
        return !strlen($this->entrypoint) && has_capability('moodle/site:config', context_system::instance());
    }

    /**
     * Initialise the importer, register all entities/error/notices
     */
    protected function initialise() {
        $this->register_entity(self::ENTITY_NAME, [
            self::ENTITY_DEPENDENCIES => [],
            self::ENTITY_LOGSUCCESS => static function(int $id, array $details): string {
                return get_string('exportimportsitesuccess', 'tool_wp');
            },
            self::ENTITY_LOGERROR => static function(array $details): string {
                return get_string('exportimportsiteerror', 'tool_wp');
            },
            self::ENTITY_NAMEPLURAL => $this->get_name(),
            self::ENTITY_INSTANCENAME_FOR_REVIEW => static function(array $details): string {
                return format_string($details['fullname'], true, ['context' => context_system::instance()]);
            },
        ]);

        // Error triggered when trying to import into the same site as the export originated from.
        $this->register_potential_error(self::ENTITY_NAME, 'exportsamesite', [
            self::ERROR_LOG => static function(array $details): string {
                return get_string('exportimportsitesame', 'tool_wp');
            },
            self::ERROR_CONFLICTHEADER => get_string('exportimportsitesame', 'tool_wp'),
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

        // Whether tenant details should be included (locked).
        $mform->addElement('advcheckbox', self::IMPORT_TENANT_DETAILS, new lang_string('tenantdetails', 'tool_tenant'));
        $mform->setType(self::IMPORT_TENANT_DETAILS, PARAM_INT);
        $form->freeze_at(self::IMPORT_TENANT_DETAILS, 1);

        // Whether tenant appearance should be included (locked).
        $mform->addElement('advcheckbox', self::IMPORT_TENANT_APPEARANCE, new lang_string('appearance'));
        $mform->setType(self::IMPORT_TENANT_APPEARANCE, PARAM_INT);
        $form->freeze_at(self::IMPORT_TENANT_APPEARANCE, 1);

        // Whether tenant users should be included (locked).
        $mform->addElement('advcheckbox', self::IMPORT_TENANT_USERS, new lang_string('users'));
        $mform->setType(self::IMPORT_TENANT_USERS, PARAM_INT);
        $form->freeze_at(self::IMPORT_TENANT_USERS, 1);

        // Whether tenant content should be included (locked).
        $mform->addElement('advcheckbox', self::IMPORT_TENANT_CONTENT, new lang_string('exportimportsitecontent', 'tool_wp'));
        $mform->setType(self::IMPORT_TENANT_CONTENT, PARAM_INT);
        $form->freeze_at(self::IMPORT_TENANT_CONTENT, 1);

        // Whether cohorts should be included.
        $mform->addElement('advcheckbox', self::IMPORT_COHORTS, new lang_string('cohortdetailswithmembers', 'tool_wp'));
        $mform->setType(self::IMPORT_COHORTS, PARAM_INT);
        $mform->setDefault(self::IMPORT_COHORTS, 1);
        if (!$this->can_import_chained_entity('cohort') ||
                $this->get_entities_in_workplace_export_file('cohort')->count() === 0) {

            $form->freeze_at(self::IMPORT_COHORTS, 0);
        }

        // Whether certificates should be included.
        $mform->addElement('advcheckbox', self::IMPORT_CERTIFICATES, new lang_string('certificatetemplates', 'tool_wp'));
        $mform->setType(self::IMPORT_CERTIFICATES, PARAM_INT);
        $mform->setDefault(self::IMPORT_CERTIFICATES, 1);
        if (!$this->can_import_chained_entity('tool_certificate_templates') ||
                $this->get_entities_in_workplace_export_file('tool_certificate_templates')->count() === 0) {

            $form->freeze_at(self::IMPORT_CERTIFICATES, 0);
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
                    $this->get_entities_in_workplace_export_file('tool_dynamicrule')->count() === 0) {

                $form->freeze_at(self::IMPORT_RULES, 0);
            }
        }

        // Whether custom reports should be included.
        if (core_component::get_component_directory('tool_reportbuilder')) {
            $mform->addElement('advcheckbox', self::IMPORT_REPORTS, new lang_string('customreports', 'tool_reportbuilder'));
            $mform->setType(self::IMPORT_REPORTS, PARAM_INT);
            $mform->setDefault(self::IMPORT_REPORTS, 1);
            if (!$this->can_import_chained_entity('tool_reportbuilder') ||
                    $this->get_entities_in_workplace_export_file('tool_reportbuilder')->count() === 0) {

                $form->freeze_at(self::IMPORT_REPORTS, 0);
            }
        }
    }

    /**
     * Summary of the import settings for the review step and also for the report page
     *
     * @param bool $importiscompleted
     * @return string
     */
    public function get_summary_for_review_step(bool $importiscompleted): string {
        global $OUTPUT;

        $settings = $this->get_import_settings();

        $settingssummary = [
            ['name' => get_string('tenantdetails', 'tool_tenant'), 'value' => !empty($settings[self::IMPORT_TENANT_DETAILS])],
            ['name' => get_string('appearance'), 'value' => !empty($settings[self::IMPORT_TENANT_APPEARANCE])],
            ['name' => get_string('users'), 'value' => !empty($settings[self::IMPORT_TENANT_USERS])],
            ['name' => get_string('exportimportsitecontent', 'tool_wp'), 'value' => !empty($settings[self::IMPORT_TENANT_CONTENT])],
            ['name' => get_string('cohortdetailswithmembers', 'tool_wp'), 'value' => !empty($settings[self::IMPORT_COHORTS])],
            ['name' => get_string('certificatetemplates', 'tool_wp'), 'value' => !empty($settings[self::IMPORT_CERTIFICATES])],
        ];

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

        return $OUTPUT->render_from_template('tool_wp/exportimport_summary', ['settings' => $settingssummary]);
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

        // Site count.
        if ($count = $this->get_entities_in_workplace_export_file(self::ENTITY_NAME)->count()) {
            $overview[] = $this->get_entity_display_name_plural(self::ENTITY_NAME) .
                get_string('entitiescountpostfix', 'tool_wp', $count);
        }

        return $overview;
    }

    /**
     * Perform the import
     *
     * @param string $entityname
     * @return void
     */
    public function perform_import(string $entityname): void {
        if (strcmp($entityname, self::ENTITY_NAME) !== 0) {
            return;
        }

        $settings = $this->get_import_settings();

        // There can only be a single site in the export file, we use this for consistency with other exporters.
        $sites = $this->get_entities_in_workplace_export_file(self::ENTITY_NAME);
        foreach ($sites as $site) {
            $site
                ->set_validation_callback(function(array $data): void {
                    $this->add_details_to_log([
                        'fullname' => $data['fullname'],
                    ]);

                    // Make sure the export isn't from the same site.
                    if ($this->exported_from_same_site()) {
                        $this->add_error_to_log('exportsamesite');
                    }
                })
                ->set_import_callback(static function(array $data, wp_imported_entity $entity): int {
                    global $SITE;

                    // We're not importing the site, just it's content.
                    return $SITE->id;
                })
                ->import($this);

            if (!$this->is_collecting_errors() && !$site->get_new_id()) {
                continue;
            }

            // Import all tenants.
            $this->process_chained_entities('tool_tenant', [], [
                tenants::IMPORT_INSTANCES => tenants::IMPORT_INSTANCES_ALL,
                tenants::IMPORT_APPEARANCE => !empty($settings[self::IMPORT_TENANT_APPEARANCE]),
                tenants::IMPORT_USERS => !empty($settings[self::IMPORT_TENANT_USERS]),
                // Included as part of tenant content.
                tenants::IMPORT_PROGRAMS => !empty($settings[self::IMPORT_TENANT_CONTENT]),
                tenants::IMPORT_CERTIFICATIONS => !empty($settings[self::IMPORT_TENANT_CONTENT]),
                tenants::IMPORT_CATEGORIES => !empty($settings[self::IMPORT_TENANT_CONTENT]),
                tenants::IMPORT_COURSES_CONTENT_ALL => !empty($settings[self::IMPORT_TENANT_CONTENT]),
                tenants::IMPORT_CERTIFICATES => !empty($settings[self::IMPORT_TENANT_CONTENT]),
                // Optional component content.
                tenants::IMPORT_COHORTS => !empty($settings[self::IMPORT_COHORTS]),
                tenants::IMPORT_ORGANISATION => !empty($settings[self::IMPORT_ORGANISATION]),
                tenants::IMPORT_RULES => !empty($settings[self::IMPORT_RULES]),
                tenants::IMPORT_REPORTS => !empty($settings[self::IMPORT_REPORTS]),
            ]);

            // In order to import all top-level course categories not associated with tenants, first find those that are associated.
            $tenants = $this->get_entities_in_workplace_export_file('tool_tenant');
            $tenantcategoryids = array_map(static function(wp_imported_entity $entity): ?int {
                return $entity->get_raw_field('categoryid');
            }, iterator_to_array($tenants, false));

            // Now get all top-level categories, excluding those associated with a tenant.
            $nontenantcategoryfilter = static function(array $data) use ($tenantcategoryids): bool {
                return ($data['parent'] == core_course_category::top()->id) && !in_array($data['id'], $tenantcategoryids);
            };
            $nontenantcategories = $this->get_entities_in_workplace_export_file('course_categories', $nontenantcategoryfilter);

            if ($nontenantcategories->count() > 0) {
                $this->process_chained_entities('course_categories', self::entitites_to_original_id($nontenantcategories), [
                    coursecategories::IMPORT_COURSES_CONTENT_ALL => !empty($settings[self::IMPORT_TENANT_CONTENT]),
                    coursecategories::IMPORT_COHORTS => !empty($settings[self::IMPORT_COHORTS]),
                    coursecategories::IMPORT_CERTIFICATE_TEMPLATES => !empty($settings[self::IMPORT_CERTIFICATES]),
                ]);
            }

            // Import system context level cohorts.
            if (!empty($settings[self::IMPORT_COHORTS]) && $this->can_import_chained_entity('cohort')) {
                $cohorts = $this->get_entities_in_workplace_export_file('cohort', [$this, 'entity_filter_system_context']);

                if ($cohorts->count() > 0) {
                    $this->process_chained_entities('cohort', self::entitites_to_original_id($cohorts), [
                        cohorts::IMPORT_CONTEXT => cohorts::IMPORT_CONTEXT_SYSTEM,
                    ]);
                }
            }

            // Import system context level certificates.
            if (!empty($settings[self::IMPORT_CERTIFICATES]) && $this->can_import_chained_entity('tool_certificate_templates')) {
                $certificates = $this->get_entities_in_workplace_export_file('tool_certificate_templates',
                    [$this, 'entity_filter_system_context']);

                if ($certificates->count() > 0) {
                    $this->process_chained_entities('tool_certificate_templates', self::entitites_to_original_id($certificates), [
                        certificates::IMPORT_SELECT_CATEGORY => false,
                        certificates::IMPORT_CERTIFICATE_ISSUES => false,
                    ]);
                }
            }
        }
    }

    /**
     * Transform imported entities into an array of their original ID's
     *
     * @param wp_imported_entities $entities
     * @return int[]
     */
    private static function entitites_to_original_id(wp_imported_entities $entities): array {
        return array_map(static function(wp_imported_entity $entity): int {
            return $entity->get_original_id();
        }, iterator_to_array($entities, false));
    }

    /**
     * Helper method to be used as a callback for filtering entities from the system context
     *
     * @param array $data
     * @return bool
     */
    public static function entity_filter_system_context(array $data): bool {
        return $data['contextid'] == context_system::instance()->id;
    }
}
