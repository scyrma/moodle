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
 * Course categories importer
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\importer;

use core\output\notification;
use core_course_category;
use tool_tenant\tenancy;
use tool_tenant\tenant;
use tool_wp\importer_base;
use tool_wp\local\exportimport\forms\import_conflict_form;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\wp_imported_entity;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/course/lib.php');

/**
 * Importer class
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class coursecategories extends importer_base {

    /** @var string */
    public const IMPORT_CONTENT = 'import_content';
    /** @var string Element for selecting what to import. */
    public const IMPORT_INSTANCES = 'import_instances';
    /** @var string */
    public const IMPORT_CERTIFICATE_TEMPLATES = 'import_certificate_templates';
    /** @var string Element for import all courses. */
    public const IMPORT_INSTANCES_ALL = 'all';
    /** @var string Element for import manually selected course categories. */
    public const IMPORT_INSTANCES_SELECTED = 'selected';
    /** @var string Element for selecting what to import. */
    public const IMPORT_SELECT_COURSE_CATEGORIES = 'select_courses';
    /** @var string Element for selecting the base course category where the others will be imported. */
    public const IMPORT_SELECT_CATEGORY = 'select_category';
    /** @var string */
    public const IMPORT_COURSES = 'import_courses';
    /** @var string */
    public const IMPORT_COURSES_CONTENT_ALL = 'import_courses_content_all';
    /** @var string */
    public const IMPORT_COHORTS = 'import_cohorts';
    /** @var string */
    public const IMPORT_COHORT_MEMBERS = 'import_cohort_members';

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
        return get_string('coursecategories');
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
     * Allows to mark importer as not available
     *
     * By default every importer is available for general import and not available for any entrypoint
     *
     * @return bool
     */
    public function is_available(): bool {
        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint()) &&
            \core_course_category::has_capability_on_any('moodle/category:manage');
    }

    /**
     * Called when an instance of the importer is created
     *
     * Must register all entities, potential errors and potential notices by calling
     * $this->register_entity()
     * $this->register_potential_errors()
     * $this->register_potential_notices()
     */
    protected function initialise() {
        $this->register_entity('course_categories', [
            self::ENTITY_DEPENDENCIES => [],
            self::ENTITY_LOGSUCCESS => function(int $id, array $importerdetails) {
                $importerdetails['url'] = (new \moodle_url('/course/management.php', ['categoryid' => $id]))->out();
                return get_string('importlogsuccesscoursecategory', 'tool_wp',
                    (object)$importerdetails);
            },
            self::ENTITY_LOGERROR => function(array $importerdetails) {
                return get_string('importlogfailedcoursecategory', 'tool_wp', (object)$importerdetails);
            },
            self::ENTITY_NAMEPLURAL => get_string('coursecategories'),
            self::ENTITY_INDIVIDUALIMPORT => function(array $ids, array $settings) {
                // What parameters to use when importing individual entity as part of some other import.
                // May be called from programs and tenants import.
                $defaults = [
                    self::IMPORT_CONTENT => 1,
                    self::IMPORT_COURSES => 1,
                    self::IMPORT_COURSES_CONTENT_ALL => 0,
                    self::IMPORT_CERTIFICATE_TEMPLATES => 1,
                    self::IMPORT_COHORTS => 1,
                    self::IMPORT_COHORT_MEMBERS => 1,
                    self::IMPORT_SELECT_CATEGORY => 0
                ];

                return array_intersect_key($settings, $defaults) + $defaults +
                    [
                        self::IMPORT_INSTANCES => self::IMPORT_INSTANCES_SELECTED,
                        self::IMPORT_SELECT_COURSE_CATEGORIES => $ids,
                    ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $logdetails, int $id) {
                return $logdetails['nestedname'];
            },
        ]);

        $this->register_potential_error('course_categories', 'idnumberconflict',
            [
                self::ERROR_LOG => function(array $details) {
                    return get_string('exportimporterrorentityexists', 'tool_wp', 'idnumber');
                },
                self::ERROR_CONFLICTHEADER => get_string('exportimporterrorentityexists', 'tool_wp', 'idnumber'),
                self::ERROR_CONFLICTSOLUTION => function(array $settings, bool $forform) {
                    if ($settings['action'] === 'increment') {
                        return get_string('exportimportconflictsuffix', 'tool_wp', 'idnumber');
                    }
                    return null;
                },
            ]);

        $this->register_potential_notice('course_categories', 'idnumberchanged',
            [
                self::NOTICE_LOG => static function(array $details, array $noticedetails) {
                    $a = (object)array_map('s', $noticedetails);
                    return get_string('idnumberchanged', 'tool_wp', $a);
                }
            ]);
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

        $settings = [];
        $str = get_string('exportcoursecategoriescontent', 'tool_wp');
        $settings[] = ['name' => $str, 'value' => 1];

        $str = get_string('importcoursecontent', 'tool_wp');
        $settings[] = ['name' => $str, 'value' => !empty($data[self::IMPORT_COURSES])];

        $str = get_string('certificatetemplates', 'tool_wp');
        $settings[] = ['name' => $str, 'value' => !empty($data[self::IMPORT_CERTIFICATE_TEMPLATES])];

        $str = get_string('cohortdetailswithmembers', 'tool_wp');
        $settings[] = ['name' => $str, 'value' => !empty($data[self::IMPORT_COHORTS])];

        // Show destination category name.
        $categoryname = !empty($data[self::IMPORT_SELECT_CATEGORY]) ?
            \core_course_category::get($data[self::IMPORT_SELECT_CATEGORY])->get_formatted_name() : get_string('top');
        $str = get_string('selectedcoursecategory', 'tool_wp', $categoryname);
        $settings[] = ['name' => $str, 'value' => 1];

        return $OUTPUT->render_from_template(
            'tool_wp/exportimport_summary',
            ['settings' => $settings]
        );
    }

    /**
     * Add settings to the import form (Step 3. Options, "what to import")
     *
     * To retrive QuickForm:
     * $mform = $form->get_quick_form()
     * To add validation use:
     * $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param import_settings_form $form
     */
    public function add_to_options_form(import_settings_form $form): void {
        global $OUTPUT;

        $mform = $form->get_quick_form();
        $mform->addElement('header', 'headercontent', get_string('content', 'tool_wp'));
        $mform->setExpanded('headercontent');

        $mform->addElement('advcheckbox', self::IMPORT_CONTENT, get_string('exportcoursecategoriescontent', 'tool_wp'));
        $mform->addHelpButton(self::IMPORT_CONTENT, 'exportcoursecategoriescontent', 'tool_wp');
        $mform->setDefault(self::IMPORT_CONTENT, 1);
        $form->freeze_at(self::IMPORT_CONTENT, 1);

        $mform->addElement('advcheckbox', self::IMPORT_COURSES, get_string('importcoursecontent', 'tool_wp'));
        $mform->addHelpButton(self::IMPORT_COURSES, 'importcoursecontent', 'tool_wp');
        $mform->setDefault(self::IMPORT_COURSES, 1);
        if (!$this->can_import_chained_entity('course') ||
                !$this->get_entities_in_workplace_export_file('course')->count()) {

            $form->freeze_at(self::IMPORT_COURSES, 0);
        }

        $mform->addElement('advcheckbox', self::IMPORT_CERTIFICATE_TEMPLATES, get_string('certificatetemplates', 'tool_wp'));
        $mform->addHelpButton(self::IMPORT_CERTIFICATE_TEMPLATES, 'certificatetemplates', 'tool_wp');
        $mform->setDefault(self::IMPORT_CERTIFICATE_TEMPLATES, 1);
        if (!$this->can_import_chained_entity('tool_certificate_templates') ||
                !$this->get_entities_in_workplace_export_file('tool_certificate_templates')->count()) {

            $form->freeze_at(self::IMPORT_CERTIFICATE_TEMPLATES, 0);
        }

        $mform->addElement('advcheckbox', self::IMPORT_COHORTS, get_string('cohortdetailswithmembers', 'tool_wp'));
        $mform->addHelpButton(self::IMPORT_COHORTS, 'cohortdetailswithmembers', 'tool_wp');
        $mform->setDefault(self::IMPORT_COHORTS, 1);
        if (!$this->can_import_chained_entity('cohort') ||
                !$this->get_entities_in_workplace_export_file('cohort')->count()) {

            $form->freeze_at(self::IMPORT_COHORTS, 0);
        }

        $mform->addElement('header', 'instances', get_string('instances', 'tool_wp'));
        $mform->setExpanded('instances');

        $selectall = get_string('selectallcategoriesinthisfile', 'tool_wp');
        $selectcourses = get_string('selectmanuallycategories', 'tool_wp');
        $mform->addElement('radio', self::IMPORT_INSTANCES, null, $selectall, self::IMPORT_INSTANCES_ALL);
        $mform->addElement('radio', self::IMPORT_INSTANCES, null, $selectcourses, self::IMPORT_INSTANCES_SELECTED);
        $mform->setType(self::IMPORT_INSTANCES, PARAM_ALPHANUM);
        $mform->setDefault(self::IMPORT_INSTANCES, self::IMPORT_INSTANCES_ALL);

        // Create elements to allow user to limit which categories to import, sorted by nested name.
        $categorieslistinfile = array_map('format_string',
            $this->get_entities_in_workplace_export_file('course_categories')->get_menu('nestedname'));
        \core_collator::asort($categorieslistinfile);

        $mform->addElement('autocomplete', self::IMPORT_SELECT_COURSE_CATEGORIES, get_string('coursecategories'),
            $categorieslistinfile, ['multiple' => true])->setHiddenLabel(true);
        $mform->setType(self::IMPORT_SELECT_COURSE_CATEGORIES, PARAM_INT);
        $mform->hideIf(self::IMPORT_SELECT_COURSE_CATEGORIES, self::IMPORT_INSTANCES, 'noteq', self::IMPORT_INSTANCES_SELECTED);

        $mform->addElement('header', 'destination', get_string('parentcategory'));
        $mform->setExpanded('destination');

        // Destination category picker. If tenant is specified only show categories for this tenant.
        $tenantid = $this->get_import_tenant_id();
        if (!$tenantid) {
            $categories = [0 => get_string('top')] + core_course_category::make_categories_list('moodle/category:manage');
        } else {
            $categories = self::make_tenant_categories_list($tenantid);
        }

        // We need to ensure the select element is always added so it's reported in CLI & WS tools. If there are no categories
        // then add a warning and hide the form element (add 'd-none' class).
        if (empty($categories)) {
            $mform->addElement('static', 'nocategoriesavailable', '', $OUTPUT->render(
                (new notification(get_string('nocategoriesavailable', 'tool_wp'), notification::NOTIFY_ERROR))
                    ->set_show_closebutton(false)
                )
            );

            $selectcategoryattr = ['class' => 'd-none'];
        } else {
            $selectcategoryattr = [];
        }

        $mform->addElement('select', self::IMPORT_SELECT_CATEGORY, get_string('selectcoursecategory', 'tool_wp'), $categories,
            $selectcategoryattr)->setHiddenLabel(true);
        if ($tenantid && ($tenantcategory = tenancy::get_tenants()[$tenantid]->categoryid)) {
            $mform->setDefault(self::IMPORT_SELECT_CATEGORY, $tenantcategory);
        }

        // Manage course categories link.
        if (core_course_category::has_capability_on_any('moodle/category:manage')) {
            $manageurl = new \moodle_url('/course/management.php');
            $html = \html_writer::link($manageurl, get_string('managecoursecategories', 'tool_wp'));
            $mform->addElement('static', 'managecategories', '', $html);
        }

        $form->add_validation_callback([$this, 'validate_options_form']);
    }

    /**
     * Returns the list of available tenant categories for this user
     *
     * @param int $tenantid
     * @return array List of strings with categories and subcategories
     */
    public static function make_tenant_categories_list(int $tenantid): array {
        $tenant = new tenant($tenantid);
        $categoryid = $tenant->get('categoryid');
        if (!$categoryid) {
            return [];
        }

        $allcategories = core_course_category::make_categories_list('moodle/category:manage');
        $tenantcategory = core_course_category::get($categoryid);

        // We need to return the intersection of all categories user has appropriate capability, and tenant category children.
        $tenantcategories = array_intersect_key($allcategories,
            array_flip($tenantcategory->get_all_children_ids()));

        return [$tenantcategory->id => $tenantcategory->get_formatted_name()]
            + $tenantcategories;
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
        $rv = [];
        if ($c1 = $this->get_entities_in_workplace_export_file('course_categories')->count()) {
            $rv[] = get_string('coursecategories') .
                get_string('entitiescountpostfix', 'tool_wp', $c1);
        }
        return $rv;
    }

    /**
     * Perform some extra moodle validation. Called from $form->add_validation_callback();.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validate_options_form(array $data, array $files): array {
        $errors = [];

        if (empty($data[self::IMPORT_INSTANCES])) {
            $errors[self::IMPORT_INSTANCES] = get_string('required');
        } else if ($data[self::IMPORT_INSTANCES] === self::IMPORT_INSTANCES_SELECTED
            && empty($data[self::IMPORT_SELECT_COURSE_CATEGORIES])) {
            $errors[self::IMPORT_SELECT_COURSE_CATEGORIES] = get_string('selectatleastonecourse', 'tool_wp');
        }

        // Check that user has restore capability on the selected destination course category. Note we differentiate between
        // 'null' (nothing selected) and '0' (top level category).
        if ($data[self::IMPORT_SELECT_CATEGORY] === null) {
            $errors[self::IMPORT_SELECT_CATEGORY] = get_string('err_required', 'form');
        } else {
            $context = $data[self::IMPORT_SELECT_CATEGORY] == 0
                ? \context_system::instance()
                : \context_coursecat::instance($data[self::IMPORT_SELECT_CATEGORY]);

            if (!has_capability('moodle/category:manage', $context)) {
                $errors[self::IMPORT_SELECT_CATEGORY] = get_string('nopermissioncategoryimport', 'tool_wp');
            }
        }

        return $errors;
    }

    /**
     * Perform the import
     *
     * @param string $entity
     */
    public function perform_import(string $entity): void {
        if ($entity === 'course_categories') {
            $this->import_course_categories();
        }
    }

    /**
     * Return filtered categories, or those categories that have filtered categories as one of their parents
     *
     * @param array $entity
     * @param array $filter
     * @return bool
     */
    private function import_categories_filter(array $entity, array $filter): bool {
        return empty($filter) || in_array($entity['id'], $filter) ||
            preg_match('/\/(' . implode('|', $filter) . ')\//', $entity['path']);
    }

    /**
     * Return category entity element to use for sorting, ensures parent categories are created before their children
     *
     * @param array $entity
     * @return string
     */
    private function import_categories_sort(array $entity): string {
        return $entity['path'];
    }

    /**
     * Import course categories
     */
    protected function import_course_categories(): void {
        $settingimporttype = $this->get_import_setting(self::IMPORT_INSTANCES);
        if ($settingimporttype === null) {
            return;
        }

        // Check if we are importing all categories in the file or custom ones set on the import form.
        $filter = ($settingimporttype === self::IMPORT_INSTANCES_ALL) ? [] :
            $this->get_import_setting(self::IMPORT_SELECT_COURSE_CATEGORIES);

        $categories = $this->get_entities_in_workplace_export_file('course_categories', function(array $entity) use ($filter) {
            return $this->import_categories_filter($entity, $filter);
        }, function(array $entity) {
            return $this->import_categories_sort($entity);
        });

        foreach ($categories as $category) {
            $category
                ->set_validation_callback(function($data, wp_imported_entity $caller) use ($category) {
                    $this->add_details_to_log([
                        'nestedname' => $data['nestedname'],
                        'name' => $data['name'],
                        'originalidnumber' => $data['idnumber'],
                        'originalname' => $data['name'],
                    ]);

                    // Make sure idnumber is unique.
                    if (!empty($data['idnumber']) && $this->idnumber_taken($data['idnumber'])) {
                        $this->add_error_to_log('idnumberconflict');
                    }

                    // Set mapping for old contexts. Will be used when importing certificates and cohorts.
                    $this->set_mapping('catcontext', $category->get_original_id(), $data['contextid']);
                })
                ->set_import_callback(function($data, wp_imported_entity $entity) use ($category) {
                    if (!empty($data['idnumber']) && $this->idnumber_taken($data['idnumber'])) {
                        $data['idnumber'] = $this->get_new_idnumber($data, $entity);
                    }

                    // Map the parent of the current category, which should have already been imported.
                    $data['parent'] = $this->get_mapping('course_categories', $data['parent'], IGNORE_MISSING) ?:
                        $this->get_import_setting(self::IMPORT_SELECT_CATEGORY);

                    unset($data['contextid'], $data['nestedname']);

                    return \core_course_category::create($data)->id;
                })
                ->import($this);

            if (!$this->is_collecting_errors() && !$category->get_new_id()) {
                continue;
            } else if (($categorynewid = $category->get_new_id()) > 0) {
                $category->import_files([
                    'newcontextid' => \context_coursecat::instance($categorynewid)->id,
                    'component' => 'coursecat',
                    'filearea' => 'description',
                    'itemid' => 0,
                ]);
            }

            // Import course backups if option is selected and user can import courses.
            if ($this->get_import_setting(self::IMPORT_COURSES) && $this->can_import_chained_entity('course')) {

                $courses = $this->get_entities_in_workplace_export_file('course',
                static function(array $entity) use ($category) {
                    return ((int)$entity['category'] === $category->get_original_id());
                });

                if ($courses->count() > 0) {

                    $courseids = [];
                    foreach ($courses as $course) {
                        $courseids[$course->get_original_id()] = true;
                    }

                    // Avoid getting categoryid error when validating.
                    $categoryid = $this->is_collecting_errors() ?
                        $this->get_import_setting(self::IMPORT_SELECT_CATEGORY) : $category->get_new_id();

                    $this->process_chained_entities('course', array_keys($courseids), [
                        courses::IMPORT_SELECT_CATEGORY => $categoryid,
                        courses::IMPORT_CONTENT_ALL => $this->get_import_setting(self::IMPORT_COURSES_CONTENT_ALL),
                    ]);
                }
            }

            // Import certificate templates.
            if ($this->get_import_setting(self::IMPORT_CERTIFICATE_TEMPLATES) &&
                    $this->can_import_chained_entity('tool_certificate_templates')) {

                // Get templates for this category id.
                $templates = $this->get_entities_in_workplace_export_file('tool_certificate_templates',
                    function(array $entity) use ($category) {
                        return ((int)$entity['contextid'] === (int)$this->get_mapping('catcontext', $category->get_original_id()));
                    });

                if ($templates->count() > 0) {

                    $templateids = [];
                    foreach ($templates as $template) {
                        $templateids[] = $template->get_original_id();
                    }

                    // Avoid getting categoryid error when validating.
                    $categoryid = $this->is_collecting_errors() ?
                        $this->get_import_setting(self::IMPORT_SELECT_CATEGORY) : $category->get_new_id();

                    $this->process_chained_entities('tool_certificate_templates', $templateids, [
                        certificates::IMPORT_SELECT_CATEGORY => $categoryid,
                        certificates::IMPORT_CERTIFICATE_ISSUES => 0,
                    ]);
                }
            }

            // Import cohorts.
            if ($this->get_import_setting(self::IMPORT_COHORTS) &&
                $this->can_import_chained_entity('cohort')) {

                // Get cohorts for this category id.
                $cohorts = $this->get_entities_in_workplace_export_file('cohort',
                    function(array $entity) use ($category) {
                        return ((int)$entity['contextid'] === (int)$this->get_mapping('catcontext', $category->get_original_id()));
                    });

                if ($cohorts->count() > 0) {
                    $cohortids = [];
                    foreach ($cohorts as $cohort) {
                        $cohortids[] = $cohort->get_original_id();
                    }

                    $this->process_chained_entities('cohort', $cohortids, [
                        cohorts::IMPORT_CONTEXT => cohorts::IMPORT_CONTEXT_CATEGORY,
                        cohorts::IMPORT_SELECT_CATEGORY => $category->get_new_id(),
                        cohorts::IMPORT_USERS => $this->get_import_setting(self::IMPORT_COHORT_MEMBERS, true),
                    ]);
                }
            }
        }
    }

    /**
     * Check if another course category exists with the same idnumber
     *
     * @param string $idnumber
     * @return bool
     */
    private function idnumber_taken(string $idnumber): bool {
        global $DB;
        return $DB->record_exists('course_categories', ['idnumber' => $idnumber]);
    }

    /**
     * Make sure that idnumber is unique, otherwise append a number to the end
     *
     * @param array $record
     * @param wp_imported_entity $caller
     * @return mixed|string
     */
    protected function get_new_idnumber(array $record, wp_imported_entity $caller) {
        $lookup = function($value) {
            return $this->idnumber_taken($value);
        };
        $useincrement = $this->get_conflict_resolution_setting('course_categories', 'idnumberconflict', 'action') === 'increment';
        $newvalue = helper::find_unique_value_for_field($record['idnumber'], $lookup, $useincrement);

        if ($newvalue !== $record['idnumber']) {
            $this->add_notice_to_log('idnumberchanged', ['from' => $record['idnumber'], 'to' => $newvalue]);
            return $newvalue;
        }
        return $newvalue;
    }

    /**
     * Add the importer error to the conflict resolution form (Step 5. Conflicts)
     *
     * To retrieve QuickForm:
     * $mform = $form->get_quick_form();
     * To add validation use:
     * $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param import_conflict_form $form
     * @param string $importedentity
     * @param string $errorcode code of an error that this importer raises using $this->add_error_to_log()
     * @param array $detailsarray array of arrays: for each occurrence of error stores the details that were logged
     *     in add_details_to_log()
     * @param bool $addskipaction  can be used by overridding methods when calling parent
     */
    public function add_to_conflict_form(import_conflict_form $form, string $importedentity,
                                         string $errorcode, array $detailsarray, bool $addskipaction = true): void {

        parent::add_to_conflict_form($form, $importedentity, $errorcode, $detailsarray, true);
        if ($errorcode === 'idnumberconflict') {
            $mform = $form->get_quick_form();
            $key = $this->get_conflict_form_element_name($importedentity, $errorcode);
            $mform->addElement('radio', $key, '',
                $this->get_conflict_solution('course_categories', $errorcode, ['action' => 'increment']), 'increment');
            $mform->setDefault($key, 'increment');
            $mform->setType($key, PARAM_ALPHANUMEXT);
        }
    }
}
