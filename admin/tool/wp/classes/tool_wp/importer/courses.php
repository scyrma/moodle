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
 * Courses importer
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\importer;

use context_system;
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
class courses extends importer_base {

    /** @var string Element for selecting if course content needs to be imported */
    public const IMPORT_CONTENT = 'import_content';
    /** @var string Element for selecting whether all (except logs) course content should be imported. */
    public const IMPORT_CONTENT_ALL = 'import_content_all';
    /** @var string Element for selecting what to import. */
    public const IMPORT_INSTANCES = 'import_instances';
    /** @var string Element for import all courses. */
    public const IMPORT_INSTANCES_ALL = 'all';
    /** @var string Element for import manually selected courses. */
    public const IMPORT_INSTANCES_SELECTED = 'selected';
    /** @var string Element for selecting what to import. */
    public const IMPORT_SELECT_COURSES = 'select_courses';
    /** @var string Element for selecting course category. */
    public const IMPORT_SELECT_CATEGORY = 'select_category';

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
        return get_string('courses');
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
            core_course_category::has_capability_on_any('moodle/course:create');
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
        $this->register_entity('course', [
            self::ENTITY_DEPENDENCIES => [],
            self::ENTITY_LOGSUCCESS => function(int $id, array $importerdetails) {
                $importerdetails['url'] = (new \moodle_url('/course/view.php', ['id' => $id]))->out();
                return get_string('importlogsuccess', 'tool_wp',
                    (object)$importerdetails);
            },
            self::ENTITY_LOGERROR => function(array $importerdetails) {
                return get_string('importlogfailed', 'tool_wp', (object)$importerdetails);
            },
            self::ENTITY_NAMEPLURAL => get_string('courses'),
            self::ENTITY_INDIVIDUALIMPORT => function(array $ids, array $settings) {
                // What parameters to use when importing individual entity as part of some other import.
                // May be called from programs and tenants import.

                if (!isset($settings[self::IMPORT_SELECT_CATEGORY])) {
                    throw new \coding_exception('Import course category is missing');
                }

                // Allow import to include all course content, if selected and current user is admin (used in site import).
                $importcoursecontentall = !empty($settings[self::IMPORT_CONTENT_ALL]) &&
                    has_capability('moodle/site:config', context_system::instance());

                $defaults = [
                    self::IMPORT_CONTENT => 1,
                    self::IMPORT_CONTENT_ALL => $importcoursecontentall,
                    self::IMPORT_SELECT_CATEGORY => 0
                ];
                return array_intersect_key($settings, $defaults) + $defaults +
                    [
                        self::IMPORT_INSTANCES => self::IMPORT_INSTANCES_SELECTED,
                        self::IMPORT_SELECT_COURSES => $ids,
                    ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $logdetails, int $id) {
                return $logdetails['fullname'];
            },
        ]);

        $this->register_potential_error('course', 'shortnameconflict',
            [
                self::ERROR_LOG => function(array $details) {
                    $importerdetails = array_map('s', $details);
                    return get_string('errorcoursessameshortname', 'tool_wp',
                        (object)$importerdetails);
                },
                self::ERROR_CONFLICTHEADER => get_string('errorcoursessameshortname', 'tool_wp'),
                self::ERROR_CONFLICTSOLUTION => function(array $settings, bool $forform) {
                    if ($settings['action'] === 'increment') {
                        return get_string('conflictshortname', 'tool_wp');
                    }
                    return null;
                },
            ]);

        $this->register_potential_notice('course', 'shortnamechanged',
            [
                self::NOTICE_LOG => static function(array $details, array $noticedetails) {
                    $a = (object)array_map('s', $noticedetails);
                    return get_string('shortnamechanged', 'tool_wp', $a);
                }
            ]);

        $this->register_potential_notice('course', 'restorewarning', [
            self::NOTICE_LOG => static function(array $details, array $noticedetails): string {
                return $noticedetails['warning'];
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
        $settingsstr = get_string('importcoursecontent', 'tool_wp');
        $settings[] = ['name' => $settingsstr, 'value' => 1];

        // Show destination category name.
        $categoryname = !empty($data[self::IMPORT_SELECT_CATEGORY]) ?
            \core_course_category::get($data[self::IMPORT_SELECT_CATEGORY])->get_formatted_name() : '';
        $str = get_string('selectedcoursecategory', 'tool_wp', $categoryname);
        $settings[] = ['name' => $str, 'value' => !empty($data[self::IMPORT_SELECT_CATEGORY])];

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

        $mform->addElement('advcheckbox', self::IMPORT_CONTENT, get_string('importcoursecontent', 'tool_wp'));
        $mform->addHelpButton(self::IMPORT_CONTENT, 'importcoursecontent', 'tool_wp');
        $mform->setDefault(self::IMPORT_CONTENT, 1);
        $form->freeze_at(self::IMPORT_CONTENT, 1);

        $mform->addElement('header', 'coursesinstances', get_string('instances', 'tool_wp'));
        $mform->setExpanded('coursesinstances');

        $selectall = get_string('selectallcoursesinthisfile', 'tool_wp');
        $selectcourses = get_string('selectcoursesmanually', 'tool_wp');
        $mform->addElement('radio', self::IMPORT_INSTANCES, null, $selectall, self::IMPORT_INSTANCES_ALL);
        $mform->addElement('radio', self::IMPORT_INSTANCES, null, $selectcourses, self::IMPORT_INSTANCES_SELECTED);
        $mform->setType(self::IMPORT_INSTANCES, PARAM_ALPHANUM);
        $mform->setDefault(self::IMPORT_INSTANCES, self::IMPORT_INSTANCES_ALL);

        $courses = $this->get_entities_in_workplace_export_file('course');
        $courseslistinfile = [];
        foreach ($courses as $course) {
            $courseslistinfile[$course->get_original_id()] = $course->get_raw_field('fullname');
        }

        // Courses picker.
        $mform->addElement('autocomplete', self::IMPORT_SELECT_COURSES, get_string('courses'),
            $courseslistinfile, ['multiple' => true])->setHiddenLabel(true);
        $mform->setType(self::IMPORT_SELECT_COURSES, PARAM_INT);
        $mform->hideIf(self::IMPORT_SELECT_COURSES, self::IMPORT_INSTANCES, 'noteq', self::IMPORT_INSTANCES_SELECTED);

        $mform->addElement('header', 'destination', get_string('coursecategory'));
        $mform->setExpanded('destination');

        // Destination category picker. If tenant is specified only show categories for this tenant.
        $tenantid = $this->get_import_tenant_id();
        if (!$tenantid) {
            $categories = ['' => ''] + core_course_category::make_categories_list('moodle/course:create');
        } else {
            $categories = self::make_tenant_categories_list($tenantid);
        }

        // We need to ensure the select element is always added so it's reported in CLI & WS tools. If there are no categories
        // then add a warning and hide the form element (add 'd-none' class).
        if (empty($categories)) {
            $mform->addElement('static', 'nocategoriesavailable', '', $OUTPUT->render(
                (new notification(get_string('nocategoriesavailable', 'tool_wp'), notification::NOTIFY_ERROR))
                    ->set_show_closebutton(false)
            ));

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

        $allcategories = core_course_category::make_categories_list('moodle/course:create');
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
        if ($c1 = $this->get_entities_in_workplace_export_file('course')->count()) {
            $rv[] = get_string('courses') .
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
                && empty($data[self::IMPORT_SELECT_COURSES])) {
            $errors[self::IMPORT_SELECT_COURSES] = get_string('selectatleastonecourse', 'tool_wp');
        }

        // Check that user has restore capability on the selected destination course category.
        if (empty($data[self::IMPORT_SELECT_CATEGORY])) {
            $errors[self::IMPORT_SELECT_CATEGORY] = get_string('err_required', 'form');
        } else {
            $context = \context_coursecat::instance($data[self::IMPORT_SELECT_CATEGORY]);

            if (!has_all_capabilities(['moodle/course:create', 'moodle/restore:restorecourse'], $context)) {
                $errors[self::IMPORT_SELECT_CATEGORY] = get_string('nopermissioncategoryimport', 'tool_wp');
            }
        }

        return $errors;
    }

    /**
     * Perform the import
     *
     * @param string $entity
     * @return void
     */
    public function perform_import(string $entity): void {
        if ($entity === 'course') {
            $this->import_courses();
        }
    }

    /**
     * Import courses
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function import_courses(): void {
        $settingimporttype = $this->get_import_setting(self::IMPORT_INSTANCES);

        if ($settingimporttype === null) {
            return;
        }

        // Check if we are importing all programs in the file or custom ones set on the import form.
        $filter = ($settingimporttype === self::IMPORT_INSTANCES_ALL) ? [] : $this->get_import_setting(self::IMPORT_SELECT_COURSES);
        $courses = $this->get_entities_in_workplace_export_file('course', static function(array $entity) use ($filter) {
            return (empty($filter) || in_array($entity['id'], $filter));
        });

        foreach ($courses as $course) {
            $course
                ->set_validation_callback(function($data, wp_imported_entity $caller) {
                    $this->add_details_to_log(['fullname' => $data['fullname'], 'originalshortname' => $data['shortname'],
                        'originalfullname' => $data['fullname']]);

                    // Make sure shortname is unique.
                    if (!empty($data['shortname']) && $this->shortname_taken($data['shortname'])) {
                        $this->add_error_to_log('shortnameconflict');
                    }
                })
                ->set_import_callback(function($data, wp_imported_entity $entity) {
                    if ($this->shortname_taken($data['shortname'])) {
                        $data['shortname'] = $this->get_new_shortname($data, $entity);
                    }

                    // We take category id from options.
                    $categoryid = $this->get_import_setting(self::IMPORT_SELECT_CATEGORY) ?: 0;
                    $categoryid = $categoryid ?: key(\core_course_category::make_categories_list('moodle/course:create'));

                    $files = $entity->get_raw_files(['component' => 'tool_wp', 'filearea' => 'coursebackup',
                        'itemid' => $entity->get_original_id()]);
                    $file = reset($files);
                    $data['category'] = $categoryid;
                    $filepath = $entity->get_file_path($file);
                    return $this->restore_course($data, $filepath, $entity);
                })
                ->import($this);
        }
    }

    /**
     * Generates a new empty course
     *
     * @param array $data
     * @return \stdClass
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function create_empty_course(array $data): \stdClass {
        global $DB;

        // Build a course.
        $course = new \stdClass();
        $course->category = $data['category'];
        $course->shortname = '';
        $course->fullname = 'fullname';
        $course->summary = '';

        // Create a new course.
        $course = create_course($course);

        // Remove annoucements and forum.
        // TODO check for a better way to remove course content. Maybe we can use remove_course_contents.
        $DB->delete_records('course_modules', ['course' => $course->id]);
        $DB->delete_records('forum', ['course' => $course->id]);

        return $course;
    }

    /**
     * Restore course
     *
     * @param array $data course data including mandatory 'category' field
     * @param string $file path to file with the course backup
     * @param wp_imported_entity $importedentity
     * @return int $courseid
     * @throws \moodle_exception
     */
    public function restore_course(array $data, $file, wp_imported_entity $importedentity): int {
        global $CFG, $USER, $DB;

        require_once("$CFG->dirroot/backup/util/includes/backup_includes.php");
        require_once("$CFG->dirroot/backup/util/includes/restore_includes.php");

        $course = $this->create_empty_course($data);

        // Get a backup temp directory name and create it.
        $tempdir = \restore_controller::get_tempdir_name($course->id, $USER->id);
        $fulltempdir = make_backup_temp_directory($tempdir);

        // Extract the backup to tmpdir.
        $fb = get_file_packer('application/vnd.moodle.backup');
        $fb->extract_to_pathname($file, $fulltempdir);

        // Define the import.
        $controller = new \restore_controller(
            $tempdir,
            $course->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );

        // If user enrolments exist in the course backup, then we will enable them to be imported if importing everything.
        if ($controller->get_info()->root_settings['users']) {
            $controller->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
            $controller->get_plan()->get_setting('users')->set_value((bool) $this->get_import_setting(self::IMPORT_CONTENT_ALL));
        } else {
            $controller->get_plan()->get_setting('users')->set_value(false);
        }

        // Prechecks.
        if (!$controller->execute_precheck()) {
            $results = $controller->get_precheck_results();

            // Check if errors have been found, in which case we need to stop.
            if (!empty($results['errors'])) {
                // Delete the backup temp directory and course we previously created.
                fulldelete($fulltempdir);
                delete_course($course, false);

                // TODO: could be improved by moving the precheck to the import validation method, but would be quite expensive.
                throw new \moodle_exception('cannotrestore', 'error', '', null, implode(', ', $results['errors']));
            }

            // Warnings should be logged, but don't prevent the restore completing.
            if (!empty($results['warnings'])) {
                foreach ($results['warnings'] as $warning) {
                    $this->add_notice_to_log('restorewarning', ['warning' => $warning]);
                }
            }
        }

        // Run the import.
        $restoreid = $controller->get_restoreid();
        $importedentity->start_course_restore($restoreid);
        $controller->execute_plan();

        // Have finished with the controller, let's destroy it, freeing mem and resources.
        $controller->destroy();
        fulldelete($fulltempdir);

        // Get new course fullname.
        $fullname = $DB->get_field('course', 'fullname', ['id' => $course->id]);
        $this->add_details_to_log(['fullname' => $fullname]);
        $importedentity->end_course_restore($restoreid);

        return $course->id;
    }

    /**
     * Check if another course exists with the same shortname
     *
     * @param string $shortname
     * @return bool
     */
    private function shortname_taken(string $shortname): bool {
        global $DB;
        return $DB->record_exists('course', ['shortname' => $shortname]);
    }

    /**
     * Make sure that shortname is unique, otherwise append a number to the end
     *
     * @param array $record
     * @param wp_imported_entity $caller
     * @return mixed|string
     */
    protected function get_new_shortname(array $record, wp_imported_entity $caller) {
        $lookup = function($value) {
            return $this->shortname_taken($value);
        };
        $useincrement = $this->get_conflict_resolution_setting('course', 'shortnameconflict', 'action') === 'increment';
        $newvalue = helper::find_unique_value_for_field($record['shortname'], $lookup, $useincrement);

        if ($newvalue !== $record['shortname']) {
            $this->add_notice_to_log('shortnamechanged', ['from' => $record['shortname'], 'to' => $newvalue]);
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
        if ($errorcode === 'shortnameconflict') {
            $mform = $form->get_quick_form();
            $key = $this->get_conflict_form_element_name($importedentity, $errorcode);
            $mform->addElement('radio', $key, '',
                $this->get_conflict_solution('course', $errorcode, ['action' => 'increment']), 'increment');
            $mform->setDefault($key, 'increment');
            $mform->setType($key, PARAM_ALPHANUMEXT);
        }
    }
}
