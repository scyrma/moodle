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
 * Full site exporter
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\exporter;

use context_system;
use core_component;
use core_course_category;
use lang_string;
use tool_tenant\tool_wp\exporter\tenants;
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
class site extends exporter_base {

    /** @var string Name to use for exported entity. */
    const ENTITY_NAME = 'site';

    /** @var string Element for including tenant details. */
    const EXPORT_TENANT_DETAILS = 'export_details';

    /** @var string Element for including tenant appearance. */
    const EXPORT_TENANT_APPEARANCE = 'export_appearance';

    /** @var string Element for including tenant users. */
    const EXPORT_TENANT_USERS = 'export_users';

    /** @var string Element for including tenant content (categories, courses, programs & certifications). */
    const EXPORT_TENANT_CONTENT = 'export_content';

    /** @var string Element for including cohorts. */
    const EXPORT_COHORTS = 'export_cohorts';

    /** @var string Element for including certificates. */
    const EXPORT_CERTIFICATES = 'export_certificates';

    /** @var string Element for including organisation structure (frameworks & jobs). */
    const EXPORT_ORGANISATION = 'export_organisation';

    /** @var string Element for including dynamic rules. */
    const EXPORT_RULES = 'export_rules';

    /** @var string Element for including custom reports. */
    const EXPORT_REPORTS = 'export_reports';

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
        return get_string('site');
    }

    /**
     * Exporter description to show in the list of available exporters
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('exportimportsitedescription', 'tool_wp');
    }

    /**
     * Exporter icon URL
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;

        return $OUTPUT->image_url('icon', 'tool_tenant')->out(false);
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
        return !strlen($this->entrypoint) && has_capability('moodle/site:config', context_system::instance());
    }

    /**
     * Initialise the exporter, register all entities
     */
    public function initialise() {
        $this->register_entity(self::ENTITY_NAME, [
            self::ENTITY_INSTANCENAME_FOR_REVIEW => static function(array $details): string {
                return format_string($details['fullname'], true, ['context' => context_system::instance()]);
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

        // Whether tenant details should be included (locked).
        $mform->addElement('advcheckbox', self::EXPORT_TENANT_DETAILS, new lang_string('tenantdetails', 'tool_tenant'));
        $mform->setType(self::EXPORT_TENANT_DETAILS, PARAM_INT);
        $form->freeze_at(self::EXPORT_TENANT_DETAILS, 1);

        // Whether tenant appearance should be included (locked).
        $mform->addElement('advcheckbox', self::EXPORT_TENANT_APPEARANCE, new lang_string('appearance'));
        $mform->setType(self::EXPORT_TENANT_APPEARANCE, PARAM_INT);
        $form->freeze_at(self::EXPORT_TENANT_APPEARANCE, 1);

        // Whether tenant users should be included (locked).
        $mform->addElement('advcheckbox', self::EXPORT_TENANT_USERS, new lang_string('users'));
        $mform->setType(self::EXPORT_TENANT_USERS, PARAM_INT);
        $form->freeze_at(self::EXPORT_TENANT_USERS, 1);

        // Whether tenant content should be included (locked).
        $mform->addElement('advcheckbox', self::EXPORT_TENANT_CONTENT, new lang_string('exportimportsitecontent', 'tool_wp'));
        $mform->setType(self::EXPORT_TENANT_CONTENT, PARAM_INT);
        $form->freeze_at(self::EXPORT_TENANT_CONTENT, 1);

        // Whether cohorts should be included.
        $mform->addElement('advcheckbox', self::EXPORT_COHORTS, new lang_string('cohortdetailswithmembers', 'tool_wp'));
        $mform->setType(self::EXPORT_COHORTS, PARAM_INT);
        $mform->setDefault(self::EXPORT_COHORTS, 1);
        if (!$this->can_export_chained_entity('cohort')) {
            $form->freeze_at(self::EXPORT_COHORTS, 0);
        }

        // Whether certificates should be included.
        $mform->addElement('advcheckbox', self::EXPORT_CERTIFICATES, new lang_string('certificatetemplates', 'tool_wp'));
        $mform->setType(self::EXPORT_CERTIFICATES, PARAM_INT);
        $mform->setDefault(self::EXPORT_CERTIFICATES, 1);
        if (!$this->can_export_chained_entity('tool_certificate_templates')) {
            $form->freeze_at(self::EXPORT_CERTIFICATES, 0);
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
            ['name' => get_string('tenantdetails', 'tool_tenant'), 'value' => !empty($settings[self::EXPORT_TENANT_DETAILS])],
            ['name' => get_string('appearance'), 'value' => !empty($settings[self::EXPORT_TENANT_APPEARANCE])],
            ['name' => get_string('users'), 'value' => !empty($settings[self::EXPORT_TENANT_USERS])],
            ['name' => get_string('exportimportsitecontent', 'tool_wp'), 'value' => !empty($settings[self::EXPORT_TENANT_CONTENT])],
            ['name' => get_string('cohortdetailswithmembers', 'tool_wp'), 'value' => !empty($settings[self::EXPORT_COHORTS])],
            ['name' => get_string('certificatetemplates', 'tool_wp'), 'value' => !empty($settings[self::EXPORT_CERTIFICATES])],
        ];

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
     * @return array[] Data returned for self::ENTITY_INSTANCENAME_FOR_REVIEW callback
     */
    public function get_instances_for_review_step(string $entityname): array {
        if (strcmp($entityname, self::ENTITY_NAME) === 0) {
            return [
                self::get_site_export(),
            ];
        }

        return [];
    }

    /**
     * Performs the export
     *
     * @return void
     */
    public function perform_export(): void {
        $settings = $this->get_export_settings();

        $this->prepare_data_for_workplace_export(self::ENTITY_NAME, self::get_site_export())
            ->export();

        // Most of the export can be delegated to the tenant exporter.
        $this->process_chained_entities('tool_tenant', [], [
            tenants::EXPORT_INSTANCES => tenants::EXPORT_INSTANCES_ALL,
            tenants::EXPORT_APPEARANCE => !empty($settings[self::EXPORT_TENANT_APPEARANCE]),
            tenants::EXPORT_USERS => !empty($settings[self::EXPORT_TENANT_USERS]),
            // Included as part of tenant content.
            tenants::EXPORT_PROGRAMS => !empty($settings[self::EXPORT_TENANT_CONTENT]),
            tenants::EXPORT_CERTIFICATIONS => !empty($settings[self::EXPORT_TENANT_CONTENT]),
            // Will be handled separately.
            tenants::EXPORT_CATEGORIES => false,
            tenants::EXPORT_CERTIFICATES => false,
            // Optional component content.
            tenants::EXPORT_ORGANISATION => !empty($settings[self::EXPORT_ORGANISATION]),
            tenants::EXPORT_RULES => !empty($settings[self::EXPORT_RULES]),
            tenants::EXPORT_REPORTS => !empty($settings[self::EXPORT_REPORTS]),
        ]);

        // Use the course category exporter to export all top level categories.
        $categories = array_map(static function(core_course_category $category): int {
            return $category->id;
        }, core_course_category::top()->get_children());

        $this->process_chained_entities('course_categories', $categories, [
            // Included as part of tenant content.
            coursecategories::EXPORT_COURSES => !empty($settings[self::EXPORT_TENANT_CONTENT]),
            coursecategories::EXPORT_COURSES_CONTENT => !empty($settings[self::EXPORT_TENANT_CONTENT]),
            coursecategories::EXPORT_COURSES_CONTENT_ALL => !empty($settings[self::EXPORT_TENANT_CONTENT]),
            // Will be handled separately.
            coursecategories::EXPORT_COHORTS => false,
            coursecategories::EXPORT_CERTIFICATE_TEMPLATES => false,
        ]);

        // Finally export all cohorts and certificates.
        if (!empty($settings[self::EXPORT_COHORTS]) && $this->can_export_chained_entity('cohort')) {
            $this->process_chained_entities('cohort', [], [
                cohorts::EXPORT_INSTANCES => cohorts::EXPORT_INSTANCES_ALL,
            ]);
        }

        if (!empty($settings[self::EXPORT_CERTIFICATES]) && $this->can_export_chained_entity('tool_certificate_templates')) {
            $this->process_chained_entities('tool_certificate_templates', [], [
                certificates::EXPORT_TYPE => certificates::EXPORT_TYPE_ALL,
                certificates::EXPORT_ISSUED_CERTIFICATES => false,
            ]);
        }
    }

    /**
     * Include enough information in the export for reporting at various migration review steps
     *
     * @return array
     */
    private static function get_site_export(): array {
        global $SITE;

        return [
            'id' => $SITE->id,
            'fullname' => $SITE->fullname,
        ];
    }
}
