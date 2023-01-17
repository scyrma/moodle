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
 * Courses categories exporter
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\exporter;

use core_course_category;
use tool_tenant\tenancy;
use tool_tenant\tenant;
use tool_wp\exporter_base;
use tool_wp\local\exportimport\forms\export_settings_form;

/**
 * Exporter class
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class coursecategories extends exporter_base {

    /** @var string */
    public const EXPORT_CONTENT = 'export_content';
    /** @var string */
    public const EXPORT_COURSES = 'export_courses';
    /** @var string */
    public const EXPORT_COURSES_CONTENT = 'export_courses_content';
    /** @var string */
    public const EXPORT_COURSES_CONTENT_ALL = 'export_courses_content_all';
    /** @var string */
    public const EXPORT_CERTIFICATE_TEMPLATES = 'export_certificate_templates';
    /** @var string */
    public const EXPORT_COHORTS = 'export_cohorts';
    /** @var string */
    public const EXPORT_COHORT_MEMBERS = 'export_cohort_members';
    /** @var string Element for selecting what to export. */
    public const EXPORT_SELECT_CATEGORIES = 'select_categories';

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
        return get_string('coursecategories');
    }

    /**
     * Exporter description to show in the list of available exporters
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('exporterdescriptioncategories', 'tool_wp');
    }

    /**
     * Exporter icon url
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('menu/workplace-file', 'theme')->out(false);
    }

    /**
     * Initialise the class, register entities that can be exported from other places
     */
    protected function initialise() {
        $this->register_entity('course_categories', [
            self::ENTITY_INDIVIDUALEXPORT => function(array $ids, array $settings) {
                // What parameters to use when exporting individual entity as part of some other export.
                // May be called from programs and tenants export.
                $defaults = [
                    self::EXPORT_CONTENT => 1,
                    self::EXPORT_COURSES => 1,
                    self::EXPORT_COURSES_CONTENT => 0,
                    self::EXPORT_COURSES_CONTENT_ALL => 0,
                    self::EXPORT_CERTIFICATE_TEMPLATES => 1,
                    self::EXPORT_COHORTS => 1,
                    self::EXPORT_COHORT_MEMBERS => 1,
                ];

                return array_intersect_key($settings, $defaults) + $defaults + [
                    self::EXPORT_SELECT_CATEGORIES => $ids,
                ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                return $record['nestedname'];
            },
        ]);
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
     * Allows to mark exporter as not available
     *
     * By default every exporter is avilable for general export and not available for any entrypoint
     *
     * @return bool
     */
    public function is_available(): bool {
        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint()) &&
            \core_course_category::has_capability_on_any('moodle/category:manage');
    }

    /**
     * Add elements to the export form
     *
     * To retrieve QuickForm:
     *   $mform = $form->get_quick_form();
     * To add form validation:
     *   $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param export_settings_form $form
     */
    public function add_to_options_form(export_settings_form $form): void {
        $mform = $form->get_quick_form();

        $mform->addElement('header', 'exporterheader', get_string('content', 'tool_wp'));
        $mform->setExpanded('exporterheader');

        $mform->addElement('advcheckbox', self::EXPORT_CONTENT, get_string('exportcoursecategoriescontent', 'tool_wp'));
        $mform->addHelpButton(self::EXPORT_CONTENT, 'exportcoursecategoriescontent', 'tool_wp');
        $mform->setDefault(self::EXPORT_CONTENT, 1);
        $form->freeze_at(self::EXPORT_CONTENT, 1);

        $mform->addElement('advcheckbox', self::EXPORT_COURSES, get_string('exportcoursecontent', 'tool_wp'));
        $mform->setDefault(self::EXPORT_COURSES, 1);
        $mform->addHelpButton(self::EXPORT_COURSES, 'exportcoursecontent', 'tool_wp');
        if (!$this->can_export_chained_entity('course')) {
            $form->freeze_at(self::EXPORT_COURSES, 0);
        }

        $mform->addElement('advcheckbox', self::EXPORT_COURSES_CONTENT, get_string('includecoursecontent', 'tool_wp'));
        $mform->addHelpButton(self::EXPORT_COURSES_CONTENT, 'includecoursecontent', 'tool_wp');
        $mform->setDefault(self::EXPORT_COURSES_CONTENT, 0);
        $mform->hideIf(self::EXPORT_COURSES_CONTENT, self::EXPORT_COURSES, 'eq', 0);
        if (!$this->can_export_chained_entity('course') || !\tool_wp\permission::can_export_course_content()) {
            $form->freeze_at(self::EXPORT_COURSES_CONTENT, 0);
        }

        $mform->addElement('advcheckbox', self::EXPORT_CERTIFICATE_TEMPLATES, get_string('certificatetemplates', 'tool_wp'));
        $mform->addHelpButton(self::EXPORT_CERTIFICATE_TEMPLATES, 'certificatetemplates', 'tool_wp');
        $mform->setDefault(self::EXPORT_CERTIFICATE_TEMPLATES, 1);
        if (!$this->can_export_chained_entity('tool_certificate_templates')) {
            $form->freeze_at(self::EXPORT_CERTIFICATE_TEMPLATES, 0);
        }

        $mform->addElement('advcheckbox', self::EXPORT_COHORTS, get_string('cohortdetailswithmembers', 'tool_wp'));
        $mform->addHelpButton(self::EXPORT_COHORTS, 'cohortdetailswithmembers', 'tool_wp');
        $mform->setDefault(self::EXPORT_COHORTS, 1);
        if (!$this->can_export_chained_entity('cohort')) {
            $form->freeze_at(self::EXPORT_COHORTS, 0);
        }

        $mform->addElement('header', 'instances', get_string('instances', 'tool_wp'));
        $mform->setExpanded('instances');

        $mform->addElement('autocomplete', self::EXPORT_SELECT_CATEGORIES, get_string('categories'),
            $this->get_category_list(), ['multiple' => true])->setHiddenLabel(true);
        $mform->setType(self::EXPORT_SELECT_CATEGORIES, PARAM_INT);

        // Set default tenant category.
        $tenantid = $this->get_export_tenant_id();
        if ($tenantid && $tenantcategory = tenancy::get_tenants()[$tenantid]->categoryid) {
            $mform->setDefault(self::EXPORT_SELECT_CATEGORIES, $tenantcategory);
        }

        $form->add_validation_callback([$this, 'validate_options_form']);
    }

    /**
     * Perform some extra moodle validation. Called from $form->add_validation_callback().
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validate_options_form(array $data, array $files): array {
        $errors = [];

        if (empty($data[self::EXPORT_SELECT_CATEGORIES])) {
            $errors[self::EXPORT_SELECT_CATEGORIES] = get_string('selectatleastonecategory', 'tool_wp');
        }

        return $errors;
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
        $settings = [];

        $str = get_string('exportcoursecategoriescontent', 'tool_wp');
        $settings[] = ['name' => $str, 'value' => 1];

        $str = get_string('exportcoursecontent', 'tool_wp');
        $settings[] = ['name' => $str, 'value' => !empty($data[self::EXPORT_COURSES])];

        if (!empty($data[self::EXPORT_COURSES])) {
            $str = get_string('includecoursecontent', 'tool_wp');
            $settings[] = ['name' => $str, 'value' => !empty($data[self::EXPORT_COURSES_CONTENT])];
        }

        $str = get_string('certificatetemplates', 'tool_wp');
        $settings[] = ['name' => $str, 'value' => !empty($data[self::EXPORT_CERTIFICATE_TEMPLATES])];

        $str = get_string('cohortdetailswithmembers', 'tool_wp');
        $settings[] = ['name' => $str, 'value' => !empty($data[self::EXPORT_COHORTS])];

        return $OUTPUT->render_from_template(
            'tool_wp/exportimport_summary',
            ['settings' => $settings]
        );
    }

    /**
     * Returns the list of entities that will be exported
     *
     * @param string $entityname
     * @return array array where each element is array that can be passed through self::ENTITY_INSTANCENAME_FOR_REVIEW
     *     callback
     */
    public function get_instances_for_review_step(string $entityname): array {
        if ($entityname === 'course_categories') {
            return array_map(static function(core_course_category $category): array {
                return [
                    'id' => $category->id,
                    'nestedname' => $category->get_nested_name(false)
                ];
            }, $this->get_export_categories());
        }

        return [];
    }

    /**
     * Performs the export
     *
     * @return void
     */
    public function perform_export(): void {
        $data = $this->get_export_settings();

        if (!empty($data[self::EXPORT_SELECT_CATEGORIES])) {
            $categories = $this->get_export_categories();
            foreach ($categories as $category) {
                $categorydata = (array) $category->get_db_record() + [
                    'contextid' => $category->get_context()->id,
                    'nestedname' => $category->get_nested_name(false)
                ];

                $this->prepare_data_for_workplace_export('course_categories', $categorydata)
                    ->add_files_from_text($category->description, $category->get_context(), 'coursecat', 'description', 0)
                    ->export();
            }

            // Export chained courses, if applicable.
            if ($this->get_export_setting(self::EXPORT_COURSES) && $this->can_export_chained_entity('course')) {
                $this->process_chained_entities('course', [], [
                    courses::EXPORT_INSTANCES => courses::EXPORT_INSTANCES_CATEGORY,
                    courses::EXPORT_SELECT_CATEGORIES => $data[self::EXPORT_SELECT_CATEGORIES],
                    courses::EXPORT_COURSES_CONTENT => $this->get_export_setting(self::EXPORT_COURSES_CONTENT),
                    courses::EXPORT_COURSES_CONTENT_ALL => $this->get_export_setting(self::EXPORT_COURSES_CONTENT_ALL),
                ]);
            }

            // Export chained certificate templates, if applicable.
            if ($this->get_export_setting(self::EXPORT_CERTIFICATE_TEMPLATES) &&
                    $this->can_export_chained_entity('tool_certificate_templates')) {

                $this->process_chained_entities('tool_certificate_templates', [], [
                    certificates::EXPORT_TYPE => certificates::EXPORT_TYPE_CAT_MANUALLY,
                    certificates::EXPORT_SELECTED_CATEGORIES => $data[self::EXPORT_SELECT_CATEGORIES],
                    certificates::EXPORT_ISSUED_CERTIFICATES => 0,
                ]);
            }

            // Export chained cohorts, if applicable.
            if ($this->get_export_setting(self::EXPORT_COHORTS) && $this->can_export_chained_entity('cohort')) {
                $this->process_chained_entities('cohort', [], [
                    cohorts::EXPORT_INSTANCES => cohorts::EXPORT_INSTANCES_CATEGORY,
                    cohorts::EXPORT_SELECT_CATEGORIES => $data[self::EXPORT_SELECT_CATEGORIES],
                    cohorts::EXPORT_USERS => $this->get_export_setting(self::EXPORT_COHORT_MEMBERS, true),
                ]);
            }
        }
    }

    /**
     * Return array of all categories selected for export
     *
     * @return core_course_category[]
     */
    protected function get_export_categories(): array {
        $settings = $this->get_export_settings();

        // Retrieve each selected category, plus all their sub-categories.
        $categories = core_course_category::get_many($settings[self::EXPORT_SELECT_CATEGORIES]);
        if ($categories) {
            $subcategories = [];
            foreach ($categories as $category) {
                $categorydescendents = $category->get_all_children_ids();
                $subcategories += core_course_category::get_many($categorydescendents);
            }
            $categories += $subcategories;
        }

        return $categories;
    }

    /**
     * Return array of categories current user is able to export
     *
     * @return string[]
     */
    private function get_category_list(): array {
        $categories = core_course_category::make_categories_list('moodle/category:manage');

        // If we are exporting for a tenant, filter categories to those within the tenant's own category.
        $tenantid = $this->get_export_tenant_id();
        if ($tenantid && $category = (new tenant($tenantid))->get_category()) {
            $categories = array_filter($categories, static function(int $categoryid) use ($category): bool {
                return $categoryid == $category->id || in_array($categoryid, $category->get_all_children_ids());
            }, ARRAY_FILTER_USE_KEY);
        }

        return $categories;
    }
}
