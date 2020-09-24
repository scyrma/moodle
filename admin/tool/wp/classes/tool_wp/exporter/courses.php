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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Courses exporter
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\tool_wp\exporter;

use tool_tenant\tenancy;
use tool_wp\exporter_base;
use tool_wp\local\exportimport\forms\export_settings_form;
use tool_wp\local\exportimport\wp_exported_entity;

defined('MOODLE_INTERNAL') || die;

/**
 * Exporter class
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class courses extends exporter_base {

    /** @var string */
    const EXPORT_COURSES = 'export_content';
    /** @var string Element for selecting if course content needs to be exported */
    const EXPORT_COURSES_CONTENT = 'export_courses_content';
    /** @var string Element for selecting what to export. */
    const EXPORT_INSTANCES = 'export_instances';
    /** @var int Element for export all courses. */
    const EXPORT_INSTANCES_ALL = 'all';
    /** @var int Element for export manually selected course categories. */
    const EXPORT_INSTANCES_CATEGORY = 'incategories';
    /** @var int Element for export manually selected courses. */
    const EXPORT_INSTANCES_SELECTED = 'selected';
    /** @var string Element for selecting what to export. */
    const EXPORT_SELECT_CATEGORIES = 'select_categories';
    /** @var string Element for selecting what to export. */
    const EXPORT_SELECT_COURSES = 'select_courses';

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
        return get_string('courses');
    }

    /**
     * Exporter description to show in the list of available exporters
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('exporterdescription', 'tool_wp');
    }

    /**
     * Exporter icon url
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('menu/courses', 'theme')->out(false);
    }

    /**
     * Initialise the class, register entities that can be exported from other places
     */
    protected function initialise() {
        $this->register_entity('course', [
            self::ENTITY_INDIVIDUALEXPORT => function(array $ids, array $settings) {
                // What parameters to use when exporting individual entity as part of some other export.
                // May be called from programs and tenants export.

                if (!\tool_wp\permission::can_export_course_content()) {
                    // Explicitly disable content export if there are no permissions to include it.
                    $settings[self::EXPORT_COURSES_CONTENT] = 0;
                }
                return [
                        self::EXPORT_COURSES => 1,
                        self::EXPORT_COURSES_CONTENT => $settings[self::EXPORT_COURSES_CONTENT] ?? 0,
                        self::EXPORT_INSTANCES => self::EXPORT_INSTANCES_SELECTED,
                        self::EXPORT_SELECT_COURSES => $ids,
                    ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                return $record['fullname'];
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
            \core_course_category::search_courses([], ['limit' => 1], ['moodle/backup:backupcourse']);
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

        $mform->addElement('advcheckbox', self::EXPORT_COURSES, get_string('exportcoursecontent', 'tool_wp'));
        $mform->addHelpButton(self::EXPORT_COURSES, 'exportcoursecontent', 'tool_wp');
        $mform->setDefault(self::EXPORT_COURSES, 1);
        $form->freeze_at(self::EXPORT_COURSES, 1);

        $mform->addElement('advcheckbox', self::EXPORT_COURSES_CONTENT, get_string('includecoursecontent', 'tool_wp'));
        $mform->addHelpButton(self::EXPORT_COURSES_CONTENT, 'includecoursecontent', 'tool_wp');
        $mform->setDefault(self::EXPORT_COURSES_CONTENT, 0);

        if (!\tool_wp\permission::can_export_course_content()) {
            $form->freeze_at(self::EXPORT_COURSES_CONTENT, 0);
        }

        $mform->addElement('header', 'coursesinstances', get_string('instances', 'tool_wp'));
        $mform->setExpanded('coursesinstances');

        $selectcourses = get_string('selectcoursesmanually', 'tool_wp');
        $selectcategories = get_string('selectcategoriesmanually', 'tool_wp');

        if ($category = $this->get_tenant_category()) {
            $selectall = get_string('selectallcourses', 'tool_wp', $category->get_formatted_name());
            $mform->addElement('radio', self::EXPORT_INSTANCES, null, $selectall, self::EXPORT_INSTANCES_ALL);
        }

        // EXPORT_TYPE_CATEGORIES Categories picker.
        $mform->addElement('radio', self::EXPORT_INSTANCES, null, $selectcategories, self::EXPORT_INSTANCES_CATEGORY);
        $categories = \core_course_category::make_categories_list('moodle/course:create');
        $mform->addElement('autocomplete', self::EXPORT_SELECT_CATEGORIES, get_string('categories'),
            $categories, ['multiple' => true])->setHiddenLabel(true);
        $mform->setType(self::EXPORT_SELECT_CATEGORIES, PARAM_INT);
        $mform->hideIf(self::EXPORT_SELECT_CATEGORIES, self::EXPORT_INSTANCES, 'noteq', self::EXPORT_INSTANCES_CATEGORY);

        // EXPORT_TYPE_COURSES Courses picker.
        $mform->addElement('radio', self::EXPORT_INSTANCES, null, $selectcourses, self::EXPORT_INSTANCES_SELECTED);
        $mform->setType(self::EXPORT_INSTANCES, PARAM_ALPHANUM);
        $mform->setDefault(self::EXPORT_INSTANCES, self::EXPORT_INSTANCES_CATEGORY);
        $options = [
            'requiredcapabilities' => ['moodle/backup:backupcourse'],
            'multiple' => true,
            'exclude' => [],
        ];
        $mform->addElement('course', self::EXPORT_SELECT_COURSES, get_string('courses'), $options)->setHiddenLabel(true);
        $mform->setType(self::EXPORT_SELECT_COURSES, PARAM_INT);
        $mform->hideIf(self::EXPORT_SELECT_COURSES, self::EXPORT_INSTANCES, 'noteq', self::EXPORT_INSTANCES_SELECTED);

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

        if ($data[self::EXPORT_INSTANCES] === self::EXPORT_INSTANCES_CATEGORY &&
            empty($data[self::EXPORT_SELECT_CATEGORIES])) {
            $errors[self::EXPORT_SELECT_CATEGORIES] = get_string('selectatleastonecategory', 'tool_wp');
        }

        if ($data[self::EXPORT_INSTANCES] === self::EXPORT_INSTANCES_SELECTED && empty($data[self::EXPORT_SELECT_COURSES])) {
            $errors[self::EXPORT_SELECT_COURSES] = get_string('selectatleastonecourse', 'tool_wp');
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
        $settings = [];
        $settingsstr = get_string('exportcoursecontent', 'tool_wp');
        $settings[] = ['name' => $settingsstr, 'value' => 1];

        $str = get_string('includecoursecontent', 'tool_wp');
        $settings[] = ['name' => $str, 'value' => $this->get_export_setting(self::EXPORT_COURSES_CONTENT)];

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
        if ($entityname === 'course') {
            return array_map(function($record) {
                if ($record instanceof \core_course_list_element) {
                    return iterator_to_array($record);
                }
                return (array)$record;
            }, $this->get_courses_to_export($this->get_export_settings()));
        }
        return [];
    }

    /**
     * Performs the export
     *
     * @return void
     */
    public function perform_export(): void {
        global $CFG;
        $data = $this->get_export_settings();

        if (!empty($data[self::EXPORT_INSTANCES])) {
            $fs = get_file_storage();

            $courses = $this->get_courses_to_export($data);
            foreach ($courses as $courseelement) {
                $course = get_course($courseelement->id);
                $context = \context_system::instance();

                $export = $this->prepare_data_for_workplace_export('course', (array)$course)
                    ->exclude_fields(['timecreated', 'timemodified']);

                if (!\tool_wp\permission::can_export_course_content()) {
                    // Explicitly disable content export if there are no permissions to include it.
                    $data[self::EXPORT_COURSES_CONTENT] = 0;
                }
                $this->backup_course($course->id, $export, (bool)($data[self::EXPORT_COURSES_CONTENT] ?? false));
                $export->add_area_files($context, 'tool_wp', 'coursebackup', $course->id);

                $export->export();

                // Clean files.
                $fs->delete_area_files($context->id, 'tool_wp', 'coursebackup', $course->id);
            }
        }
    }

    /**
     * Return current tenant category
     *
     * @return \core_course_category|null
     */
    protected function get_tenant_category(): ?\core_course_category {
        $tenantid = $this->get_export_tenant_id();
        $categoryid = $tenantid ? (tenancy::get_tenants()[$tenantid]->categoryid) : null;
        return $categoryid ? \core_course_category::get($categoryid) : null;
    }

    /**
     * Returns list of courses selected to export
     *
     * @param array $data
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_courses_to_export(array $data): array {
        global $DB;

        switch ($data[self::EXPORT_INSTANCES]) {
            case self::EXPORT_INSTANCES_ALL:
                // Export all courses in current tenant course category and subcategories.
                $categories = [$this->get_tenant_category()];
                $courses = $this->get_courses_from_categories($categories, true);
                break;
            case self::EXPORT_INSTANCES_CATEGORY:
                // Export only selected categories.
                $categories = \core_course_category::get_many($data[self::EXPORT_SELECT_CATEGORIES]);
                $courses = $this->get_courses_from_categories($categories, false);
                break;
            case self::EXPORT_INSTANCES_SELECTED:
                $courses = $DB->get_records_list('course', 'id', $data[self::EXPORT_SELECT_COURSES]);

                // Check if user has capability to backup these courses.
                foreach ($courses as $key => $course) {
                    \context_helper::preload_from_record($course); // TODO there is no context info in the records.
                    $context = \context_course::instance($course->id);
                    if (!has_capability('moodle/backup:backupcourse', $context)
                        || !\core_course_category::can_view_course_info($course)) {
                        unset($courses[$key]);
                    }
                }
                break;
            default:
                throw new \coding_exception('Invalid export option');
        }

        return $courses;
    }

    /**
     * Gets list of courses from a list of categories that user has permission to backup
     *
     * @param array $categories
     * @param bool $recursive
     * @return array
     * @throws \coding_exception
     */
    private function get_courses_from_categories(array $categories, bool $recursive = false): array {
        $courses = [];
        foreach ($categories as $category) {
            foreach ($category->get_courses(['recursive' => (int)$recursive]) as $catcourse) {
                $courses[] = $catcourse;
            }
        }
        // Check if user has capability to backup these courses.
        foreach ($courses as $key => $course) {
            $context = \context_course::instance($course->id);
            if (!has_capability('moodle/backup:backupcourse', $context)
                || !\core_course_category::can_view_course_info($course)) {
                unset($courses[$key]);
            }
        }

        return $courses;
    }

    /**
     * Set backup settings.
     *
     * This function is used to configure backup. We are trying to address edge
     * case here when user will have only backupcourse capability
     * (but not backupconfigure). This happens in case if you manually allocate
     * backupcourse to some user who normally not able to backup courses (notice
     * that manager and teacher archetype roles have full set of backup capabilities
     * by default). If this user will be backing up course, default configuration will
     * be used, which includes content, but setting tool_wp/coursecontentbackup may
     * not allow to backup course content, so we override defaults in true->false
     * direction to exclude content and proceed, rather than throw an error.
     *
     * @param \backup_controller $bc
     * @param array $settings associative array of settings (name=>value)
     */
    protected function set_backup_settings(\backup_controller $bc, array $settings) {
        foreach ($settings as $key => $value) {
            $setting = $bc->get_plan()->get_setting($key);

            if ($setting->get_value() == $value) {
                // Skip if setting is already set to required value.
                continue;
            }

            if ($setting->get_status() !== \base_setting::NOT_LOCKED) {
                // Some setting might be locked by capability (e.g. when user does
                // not have backupconfig capability). For the purpose of excluding
                // course content in export we override those only in true -> false
                // direction.
                if ((bool) $setting->get_value() === true && $value === false) {
                    $setting->set_status(\base_setting::NOT_LOCKED);
                }
            }
            $setting->set_value($value);
        }
    }

    /**
     * Backup course
     *
     * @param int $courseid
     * @param wp_exported_entity $exportedentity
     * @param bool $includecoursecontent
     * @return \stored_file
     */
    public function backup_course(int $courseid, wp_exported_entity $exportedentity,
                                  bool $includecoursecontent = true): \stored_file {
        global $CFG, $USER;
        require_once("$CFG->dirroot/backup/util/includes/backup_includes.php");
        require_once("$CFG->dirroot/backup/util/includes/restore_includes.php");

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id);

        // We want to exclude user information.
        $bc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value(false);
        $backupid = $bc->get_backupid();
        $exportedentity->start_course_backup($backupid);

        // These settings must be always enabled on export course.
        $this->set_backup_settings($bc, [
            'files' => true,
            'customfield' => true,
            'filters' => true,
            'groups' => true,
        ]);

        if (!$includecoursecontent) {
            // Settings to disable if export without course content is chosen.
            $this->set_backup_settings($bc, [
                'imscc11' => false,
                'anonymize' => false,
                'role_assignments' => false,
                'activities' => false,
                'blocks' => false,
                'comments' => false,
                'badges' => false,
                'calendarevents' => false,
                'userscompletion' => false,
                'logs' => false,
                'grade_histories' => false,
                'questionbank' => false,
                'competencies' => false,
                'contentbankcontent' => false,
            ]);
        }

        // Execute when ready.
        $bc->execute_plan();

        $results = $bc->get_results();
        /** @var \stored_file $file */
        $file = $results['backup_destination'];

        $fs = get_file_storage();

        // Move course backups into component: tool_wp, filearea: coursebackup,itemid: courseid.
        $newfileinfo = array(
            'component' => 'tool_wp',
            'filearea' => 'coursebackup',
            'itemid' => $courseid,
            'contextid' => \context_system::instance()->id,
            'filepath' => '/',
            'filename' => $file->get_filename()
        );
        $newfile = $fs->create_file_from_storedfile($newfileinfo, $file);

        $file->delete();
        $bc->destroy();
        $bc = null;
        $exportedentity->end_course_backup($backupid);

        return $newfile;
    }
}
