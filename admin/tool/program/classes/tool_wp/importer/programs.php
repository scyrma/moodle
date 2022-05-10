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
 * Programs importer
 *
 * @package   tool_program
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\tool_wp\importer;

use tool_dynamicrule\tool_wp\importer\rules;
use tool_program\api;
use tool_program\constants;
use tool_program\output\tab\program_manager_list_archived_tab;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;
use tool_program\persistent\program_user;
use tool_tenant\tenancy;
use tool_wp\importer_base;
use tool_wp\local\exportimport\forms\import_conflict_form;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\wp_imported_entity;
use tool_wp\tool_wp\importer\courses;

defined('MOODLE_INTERNAL') || die;

/**
 * Importer class
 *
 * @package   tool_program
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class programs extends importer_base {

    /** @var string Element for selecting what to import. */
    const IMPORT_INSTANCES = 'import_instances';
    /** @var int Element for import all programs. */
    const IMPORT_INSTANCES_ALL = 'all';
    /** @var int Element for import manually selected programs. */
    const IMPORT_INSTANCES_SELECTED = 'selected';
    /** @var string Element for selecting what to import. */
    const IMPORT_SELECTED_PROGRAMS = 'select_programs';
    /** @var string Element for selecting if program user allocations have to be imported. */
    const IMPORT_USER_ALLOCATIONS = 'import_user_allocations';
    /** @var string Element for selecting if courses have to be imported. */
    const IMPORT_COURSE_BACKUPS = 'import_course_backups';
    /** @var string Element for selecting the category where courses have to be imported. */
    const IMPORT_COURSE_CATEGORY = 'select_category';
    /** @var string Element for selecting if program settings have to be imported. */
    const IMPORT_CONTENT = 'import_content';
    /** @var string Element for selecting if program dynamic rules have to be imported. */
    const IMPORT_PROGRAM_DYNAMICRULES = 'import_dynamic_rules';
    /** @var string Import only specific program allocations (list of ids) - used only in chained import */
    const IMPORT_SELECTED_USER_ALLOCATIONS = 'select_user_allocations';

    /**
     * Importer format
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
        return get_string('programs', 'tool_program');
    }

    /**
     * Exporter icon url
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('menu/programs', 'theme')->out(false);
    }

    /**
     * Allows to mark importer as not available
     *
     * By default every importer is available for general import and not available for any entrypoint
     *
     * @return bool
     */
    public function is_available(): bool {
        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint())
            && permission::can_create();
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
        $this->register_entity(program::TABLE, [
            self::ENTITY_DEPENDENCIES => ['tool_tenant'],
            self::ENTITY_LOGSUCCESS => function(int $id, array $importerdetails) {
                $importerdetails['url'] = (empty($importerdetails['archived']) ?
                    new \moodle_url('/admin/tool/program/edit.php', ['id' => $id]) :
                    new \moodle_url('/admin/tool/program/index.php', null,
                        (new program_manager_list_archived_tab([]))->get_tab_id()))->out();
                $importerdetails['fullname'] = format_string($importerdetails['fullname']);
                return get_string('importlogsuccess', 'tool_program',
                    (object)$importerdetails);
            },
            self::ENTITY_LOGERROR => function(array $importerdetails) {
                $a = (object)['fullname' => format_string($importerdetails['fullname'])];
                return get_string('importlogfailed', 'tool_program', $a);
            },
            self::ENTITY_NAMEPLURAL => get_string('programs', 'tool_program'),
            self::ENTITY_INDIVIDUALIMPORT => function(array $ids, array $settings) {
                // What parameters to use when importing individual entity as part of some other import.
                // May be called from certifications and tenants import.
                $defaults = [
                    self::IMPORT_CONTENT => 1,
                    self::IMPORT_USER_ALLOCATIONS => 0,
                    self::IMPORT_COURSE_BACKUPS => 1,
                    self::IMPORT_PROGRAM_DYNAMICRULES => 1,
                    self::IMPORT_COURSE_CATEGORY => 0,
                ];
                return array_intersect_key($settings, $defaults) + $defaults +
                    [
                        self::IMPORT_INSTANCES => self::IMPORT_INSTANCES_SELECTED,
                        self::IMPORT_SELECTED_PROGRAMS => $ids,
                    ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $logdetails, int $id) {
                return format_string($logdetails['fullname']);
            },
        ]);

        $this->register_entity(program_user::TABLE, [
            self::ENTITY_DEPENDENCIES => [program::TABLE, 'user'],
            self::ENTITY_LOGSUCCESS => function(int $id, array $importerdetails) {
                $importerdetails['program'] = format_string($importerdetails['program']);
                return get_string('importlogsuccessuserallocations', 'tool_program',
                    (object)$importerdetails);
            },
            self::ENTITY_LOGERROR => function(array $importerdetails) {
                $a = (object)[
                    'originaluserfullname' => $importerdetails['originaluserfullname'],
                    'program' => $importerdetails['program'],
                ];
                return get_string('errorcouldnotallocate', 'tool_program', $a);
            },
            self::ENTITY_NAMEPLURAL => get_string('programuserallocations', 'tool_program'),
            self::ENTITY_INDIVIDUALIMPORT => function(array $ids, array $settings) {
                $defaults = [
                    self::IMPORT_USER_ALLOCATIONS => 0,
                ];
                return array_intersect_key($settings, $defaults) + $defaults + [
                    self::IMPORT_SELECTED_USER_ALLOCATIONS => $ids
                ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                $fullname = $record['userfullname'] ?? $record['originaluserfullname'];
                return get_string('entityprogramusers', 'tool_program') . ': ' . format_string($fullname);
            },
        ]);

        $this->register_potential_error(program::TABLE, 'idnumberconflict',
            [
                self::ERROR_LOG => function(array $details) {
                    $importerdetails = array_map('s', $details);
                    return get_string('importlogidnumberexists', 'tool_program',
                        (object)$importerdetails);
                },
                self::ERROR_CONFLICTHEADER => get_string('errorsameidnumber', 'tool_program'),
                self::ERROR_CONFLICTSOLUTION => function(array $settings, bool $forform) {
                    switch ($settings['action']) {
                        case 'increment':
                            return get_string('importincrementidnumber', 'tool_wp');
                        case 'empty':
                            return get_string('importsetidnumbertoempty', 'tool_wp');
                    }
                    return null;
                }
            ]);

        $this->register_potential_notice(program::TABLE, 'idnumberchanged',
            [
                self::NOTICE_LOG => static function(array $details, array $noticedetails) {
                    $a = (object)array_map('s', $noticedetails);
                    return get_string('idnumberchanged', 'tool_wp', $a);
                }
            ]);

        $this->register_potential_error('tool_program_users', 'cannotallocate',
            [
                self::ERROR_LOG => function(array $details) {
                    $importerdetails = array_map('s', $details);
                    return get_string('importcannotallocate', 'tool_program',
                        (object)$importerdetails);
                },
                self::ERROR_CONFLICTHEADER => get_string('errorcannotallocate', 'tool_program'),
            ]);
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
        $mform = $form->get_quick_form();
        $mform->addElement('header', 'content', get_string('content', 'tool_program'));
        $mform->setExpanded('content');

        $mform->addElement('advcheckbox', self::IMPORT_CONTENT, get_string('import_content', 'tool_program'));
        $mform->setDefault(self::IMPORT_CONTENT, 1);
        $mform->addHelpButton(self::IMPORT_CONTENT, 'import_content', 'tool_program');
        $form->freeze_at(self::IMPORT_CONTENT, 1);

        // Course backups and categories.
        $hascourses = $this->get_entities_in_workplace_export_file('course')->count();
        $canimportcourses = $hascourses && $this->can_import_chained_entity('course');
        $coursebackupsstr = get_string('importcoursecontent', 'tool_wp');
        $mform->addElement('advcheckbox', self::IMPORT_COURSE_BACKUPS, $coursebackupsstr);
        $mform->setDefault(self::IMPORT_COURSE_BACKUPS, 1);
        $mform->setType(self::IMPORT_COURSE_BACKUPS, PARAM_INT);
        $mform->addHelpButton(self::IMPORT_COURSE_BACKUPS, 'importcoursecontent', 'tool_wp');

        $tenantid = $this->get_import_tenant_id();
        $tenantcategory = tenancy::get_tenants()[$tenantid]->categoryid;

        // Destination category picker. Show all categories where this user is able to create courses.
        // A program can have shared courses, a user can import courses in other categories than just only the tenant category.
        $categories = \core_course_category::make_categories_list('moodle/course:create');

        $group = [];
        $html = \html_writer::span(get_string('selectcoursecategory', 'tool_wp') . ':', 'mr-2 my-2');
        $group[] = $mform->createElement('static', 'selector', '', $html);
        $group[] = $coursecategory = $mform->createElement('select', self::IMPORT_COURSE_CATEGORY,
            get_string('selectcoursecategory', 'tool_wp'), $categories);
        $coursecategory->setHiddenLabel(true);
        // If tenant has a category set default to this category.
        if ($tenantcategory) {
            $mform->setDefault(self::IMPORT_COURSE_CATEGORY, $tenantcategory);
        }
        if (\core_course_category::make_categories_list('moodle/category:manage')) {
            // Manage course categories link.
            $manageurl = new \moodle_url('/course/management.php');
            $html = \html_writer::link($manageurl, get_string('managecoursecategories', 'tool_wp'));
            $group[] = $mform->createElement('static', 'managecat', '', '<br>' . $html);
        }
        $mform->addGroup($group, 'selectcatgroup', '', '', false);
        $mform->hideIf('selectcatgroup', self::IMPORT_COURSE_BACKUPS, 'noteq', 1);

        if (!$canimportcourses) {
            $form->freeze_at(self::IMPORT_COURSE_BACKUPS, 0);
            $form->freeze_at(self::IMPORT_COURSE_CATEGORY, null);
        }

        $usersstr = get_string('import_user_allocations', 'tool_program');
        $mform->addElement('advcheckbox', self::IMPORT_USER_ALLOCATIONS, $usersstr);
        $mform->setDefault(self::IMPORT_USER_ALLOCATIONS, 0);
        $mform->addHelpButton(self::IMPORT_USER_ALLOCATIONS, 'import_user_allocations', 'tool_program');
        if (!$this->get_entities_in_workplace_export_file(program_user::TABLE)->count() ||
            !permission::has_allocateuser_capability()) {
            $form->freeze_at(self::IMPORT_USER_ALLOCATIONS, 0);
        }

        $dynamicrulesstr = get_string('import_dynamic_rules', 'tool_program');
        $mform->addElement('advcheckbox', self::IMPORT_PROGRAM_DYNAMICRULES, $dynamicrulesstr);
        $mform->setDefault(self::IMPORT_PROGRAM_DYNAMICRULES, 1);
        $mform->addHelpButton(self::IMPORT_PROGRAM_DYNAMICRULES, 'import_dynamic_rules', 'tool_program');
        if (!$this->get_entities_in_workplace_export_file('tool_dynamicrule')->count() ||
            !$this->can_import_chained_entity('tool_dynamicrule')) {
            $form->freeze_at(self::IMPORT_PROGRAM_DYNAMICRULES, 0);
        }

        $mform->addElement('header', 'instances', get_string('instances', 'tool_wp'));
        $mform->setExpanded('instances');

        $selectall = get_string('selectallprogramsinthisfile', 'tool_program');
        $selectmanually = get_string('selectmanually', 'tool_program');
        $mform->addElement('radio', self::IMPORT_INSTANCES, null, $selectall, self::IMPORT_INSTANCES_ALL);
        $mform->addElement('radio', self::IMPORT_INSTANCES, null, $selectmanually, self::IMPORT_INSTANCES_SELECTED);
        $mform->setType(self::IMPORT_INSTANCES, PARAM_ALPHANUM);
        $mform->setDefault(self::IMPORT_INSTANCES, self::IMPORT_INSTANCES_ALL);

        // Programs picker to allow user to limit which programs to import, sorted by fullname.
        $programslistinfile = $this->get_all_programs_in_export_file_as_menu();

        $mform->addElement('autocomplete', self::IMPORT_SELECTED_PROGRAMS, get_string('programs', 'tool_program'),
            $programslistinfile, ['multiple' => true])->setHiddenLabel(true);
        $mform->setType(self::IMPORT_SELECTED_PROGRAMS, PARAM_INT);
        $mform->hideIf(self::IMPORT_SELECTED_PROGRAMS, self::IMPORT_INSTANCES, 'noteq', self::IMPORT_INSTANCES_SELECTED);

        $form->add_validation_callback([$this, 'validate_options_form']);
    }

    /**
     * Get all programs present in this file and also programs that were not exported but with exported user allocations
     *
     * @return array
     */
    protected function get_all_programs_in_export_file_as_menu(): array {
        $programslistinfile = array_map('format_string',
            $this->get_entities_in_workplace_export_file(program::TABLE)->get_menu('fullname'));

        foreach ($this->get_entities_in_workplace_export_file(program_user::TABLE) as $programuser) {
            $id = $programuser->get_raw_field('programid');
            if (!array_key_exists($id, $programslistinfile)) {
                $name = $programuser->get_raw_field('programfullname');
                $programslistinfile[$id] = ($name !== null ? format_string($name) : $id) .
                    ' ' . get_string('exportonlyallocationspostfix', 'tool_program');
            }
        }
        \core_collator::asort($programslistinfile);
        return $programslistinfile;
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
        $settingsstr = get_string('import_content', 'tool_program');
        $settings[] = ['name' => $settingsstr, 'value' => !empty($data[self::IMPORT_CONTENT])];

        $coursebackupsstr = get_string('importcoursecontent', 'tool_wp');
        $settings[] = ['name' => $coursebackupsstr, 'value' => $data[self::IMPORT_COURSE_BACKUPS]];

        $usersstr = get_string('import_user_allocations', 'tool_program');
        $settings[] = ['name' => $usersstr, 'value' => !empty($data[self::IMPORT_USER_ALLOCATIONS])];

        $dynamicrulesstr = get_string('import_dynamic_rules', 'tool_program');
        $settings[] = ['name' => $dynamicrulesstr, 'value' => !empty($data[self::IMPORT_PROGRAM_DYNAMICRULES])];

        return $OUTPUT->render_from_template(
            'tool_wp/exportimport_summary',
            ['settings' => $settings]
        );
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
        if ($c1 = $this->get_entities_in_workplace_export_file('tool_program')->count()) {
            $rv[] = get_string('programs', 'tool_program') .
                get_string('entitiescountpostfix', 'tool_wp', $c1);
        }
        if ($c1 = $this->get_entities_in_workplace_export_file('tool_program_users')->count()) {
            $rv[] = get_string('import_user_allocations', 'tool_program') .
                get_string('entitiescountpostfix', 'tool_wp', $c1);
        }
        return $rv;
    }

    /**
     * Perform some extra moodle validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validate_options_form(array $data, array $files): array {
        $errors = [];

        if ($data[self::IMPORT_INSTANCES] === self::IMPORT_INSTANCES_SELECTED && empty($data[self::IMPORT_SELECTED_PROGRAMS])) {
            $errors[self::IMPORT_SELECTED_PROGRAMS] = get_string('selectatleastoneprogram', 'tool_program');
        }

        if (!empty($data[self::IMPORT_COURSE_BACKUPS])) {
            // TODO error must be on the element, not group...
            if (empty($data[self::IMPORT_COURSE_CATEGORY])) {
                $errors['managecatgroup'] = get_string('err_required', 'form');
            } else if (!($coursecategoryctx = \context_coursecat::instance($data[self::IMPORT_COURSE_CATEGORY], IGNORE_MISSING)) ||
                    !has_capability('moodle/restore:restorecourse', $coursecategoryctx) ||
                    !has_capability('moodle/course:create', $coursecategoryctx)) {
                $errors['managecatgroup'] = get_string('nopermissioncategoryrestore', 'tool_wp');
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

        if ($entity === 'tool_program') {
            $this->import_programs();
        }

        if ($entity === 'tool_program_users' && $this->get_import_setting(self::IMPORT_SELECTED_USER_ALLOCATIONS)
                && $this->get_import_setting(self::IMPORT_USER_ALLOCATIONS)) {
            $this->import_user_allocations();
        }
    }

    /**
     * Returns if user has capability to restore courses
     *
     * @return bool
     * @throws \coding_exception
     */
    protected function can_restore_courses(): bool {
        $coursecategoryctx = \context_coursecat::instance($this->get_import_setting(self::IMPORT_COURSE_CATEGORY), IGNORE_MISSING);
        return $coursecategoryctx && has_capability('moodle/restore:restorecourse', $coursecategoryctx) &&
            has_capability('moodle/course:create', $coursecategoryctx);
    }

    /**
     * Import one program with courses and dynamic rules (if applicable)
     *
     * @param wp_imported_entity $program
     */
    protected function import_program(wp_imported_entity $program) {
        $program
            ->add_mapping('tenantid', 'tool_tenant')
            ->import_files_for_itemid([
                'filearea' => 'program_image',
                'component' => 'tool_program',
            ], 'tool_program')
            ->import_files_for_itemid([
                'filearea' => 'program_description',
                'component' => 'tool_program',
            ], 'tool_program')
            ->set_validation_callback(function($data, wp_imported_entity $entity) {
                $this->add_details_to_log(
                    ['fullname' => $data['fullname'], 'originalidnumber' => $data['idnumber']]);

                // Make sure the idnumber is unique, if not unique and conflict resolution was not set,
                // raise an error. Raised error will fail validation.
                if (!empty($data['idnumber']) &&
                    program::get_records(['tenantid' => $data['tenantid'], 'idnumber' => $data['idnumber']])) {
                    $this->add_error_to_log('idnumberconflict');
                }

                // Make sure courses exist, if they don't exist and there is no conflict resolution, the errors
                // will be raised and validation will fail.
                $courses = $entity->get_nested_entities(program_course::TABLE);
                foreach ($courses as $course) {
                    $this->get_mapping('course', $course['courseid']);
                }
            })
            ->set_import_callback(function($data, wp_imported_entity $entity) {
                // We need to increment or remove idnumber according to the choosen option in the form.
                $data['idnumber'] = $this->get_new_idnumber($data, $entity);

                $programrecord = array_intersect_key($data, program::properties_definition());
                $programrecord['program_tags'] = (isset($data['program_tags'])) ? explode(',', $data['program_tags']) : [];
                $programpersistent = api::create_program((object)$programrecord);

                $this->add_details_to_log([
                    'id' => $programpersistent->get('id'),
                    'archived' => $programpersistent->get('archived')
                ]);

                // Nested entities.
                $sets = $entity->get_nested_entities(program_set::TABLE);
                $baseset = $programpersistent->get_base_set();
                $createdsets = [];

                // We create new sets with empty parent id.
                foreach ($sets as $set) {
                    $originalsetid = $set['_originalid'];
                    $setrecord = array_intersect_key($set, program_set::properties_definition());
                    // Base set has already been created with api:create_program call.
                    if ((int)$setrecord['parent'] === 0) {
                        $this->set_mapping(program_set::TABLE, $originalsetid, $baseset->get('id'));
                        $createdsets[$baseset->get('id')] = $baseset;
                    } else {
                        ($setpersistent = new program_set(0, (object) $setrecord))
                            ->set('programid', $programpersistent->get('id'))
                            ->set('parent', 0)
                            ->save();
                        $this->set_mapping(program_set::TABLE, $originalsetid, $setpersistent->get('id'));
                        $createdsets[$setpersistent->get('id')] = $setpersistent;
                    }
                }
                $this->add_details_to_log(['setscount' => count($sets)]);

                // We fix parent id with the new ones we just created.
                foreach ($sets as $set) {
                    $newid = $this->get_mapping(program_set::TABLE, $set['_originalid']);
                    $persistent = $createdsets[$newid];

                    $oldparent = $set['parent'];
                    // If old parent id is zero it's a base set and we don't need to change it.
                    if ((int)$oldparent !== 0) {
                        $newparent = $this->get_mapping(program_set::TABLE, $oldparent);
                        $persistent->set('parent', $newparent);
                        $persistent->update();
                    }
                }

                // We create new program courses on the new sets.
                $courses = $entity->get_nested_entities(program_course::TABLE);
                $coursescount = 0;
                foreach ($courses as $course) {
                    $newsetid = $this->get_mapping(program_set::TABLE, $course['setid']);
                    if (!$newsetid) {
                        continue;
                    }
                    $newcourseid = $this->get_mapping('course', $course['courseid']);
                    if ($newcourseid) {
                        $courserecord = array_intersect_key($course, program_course::properties_definition());

                        $programcourse = api::add_course_to_parent_set($newsetid, $newcourseid);
                        $programcourse->set('sortorder', $courserecord['sortorder']);
                        $programcourse->update();

                        $coursescount++;
                    }
                }
                $this->add_details_to_log(['coursescount' => $coursescount]);

                // Return new program id.
                return $programpersistent->get('id');
            })
            ->import($this);

        if (!$this->is_collecting_errors() && !$program->get_new_id()) {
            return;
        }

        // Import program custom fields.
        $this->process_chained_entities('customfield_data', [], [
            'component' => 'tool_program',
            'area' => 'program',
            'instancemapper' => 'tool_program',
            'instanceid' => $program->get_original_id(),
        ]);

        // Check if we need to import program dynamic rules.
        if ($this->get_import_setting(self::IMPORT_PROGRAM_DYNAMICRULES) &&
            $this->can_import_chained_entity('tool_dynamicrule')) {
            if ($program->get_new_id() && !$this->is_collecting_errors()) {
                // When program has been created the default DRs have been set. We need to remove them and import new ones.
                api::delete_program_dynamic_rules($program->get_new_id());
            }

            $this->process_chained_entities('tool_dynamicrule', [], [
                rules::IMPORT_INSTANCES => rules::IMPORT_INSTANCES_COMPONENT,
                rules::IMPORT_SELECT_COMPONENT => 'tool_program',
                rules::IMPORT_SELECT_COMPONENT_AREA => 'program',
                rules::IMPORT_SELECT_COMPONENT_ITEMID => $program->get_original_id(),
                rules::IMPORT_SELECT_COMPONENT_MAPPER => 'tool_program',
            ]);

            // Make sure program has all dynamic rules, if not create missing.
            if ($program->get_new_id() && !$this->is_collecting_errors()) {
                $this->add_missing_default_dynamicrule_conditions_to_program($program->get_new_id());
            }
        }

    }

    /**
     * Import one user program allocation
     *
     * @param wp_imported_entity $userallocation
     * @param program|null $programpersistent
     */
    protected function import_user_allocation(wp_imported_entity $userallocation, ?program $programpersistent = null) {
        $userallocation
            ->add_mapping('userid', 'user')
            ->add_mapping('programid', 'tool_program')
            ->set_validation_callback(function ($data, wp_imported_entity $entity) use (&$programpersistent) {
                $this->add_details_to_log([
                    'programid' => $data['programid'],
                    'userid' => $data['userid'],
                    'originaluserfullname' => $data['userfullname'],
                    'program' => $data['programfullname'] ?? $data['programid']
                ]);
                if ($data['userid'] > 0) {
                    $this->add_details_to_log(['userfullname' => fullname(\core_user::get_user($data['userid']))]);
                }
                if (!$programpersistent && $data['programid'] > 0) {
                    $programpersistent = new program($data['programid']);
                }
                // Check permission to allocate user without having into consideration if allocation window is open or
                // if direct allocation setting is enabled.
                if ($programpersistent && $data['userid'] &&
                    !permission::can_allocate_user($programpersistent, $data['userid'], false, false)) {
                    $this->add_error_to_log('cannotallocate');
                }
            })
            ->set_import_callback(function ($data, wp_imported_entity $entity) use ($programpersistent) {
                $userrecord = array_intersect_key($data, program_user::properties_definition());
                // Allocations on this exported file can be manual or from dynamic rules. Convert all to manual.
                $userrecord['allocationtype'] = constants::ALLOCATION_MANUAL;
                $programpersistent = $programpersistent ?: new program($userrecord['programid']);
                $programuser = api::allocate_user($programpersistent, (object)$userrecord);

                return $programuser->get('id');
            })->import($this);
    }

    /**
     * Import user allocations
     *
     * @param int $originalprogramid
     * @param int $newid
     */
    protected function import_user_allocations_for_program(int $originalprogramid, ?int $newid) {

        if (!$this->is_collecting_errors() && !$newid) {
            // Program was not imported, can not import user allocations.
            return;
        }

        $userallocations = $this->get_entities_in_workplace_export_file(program_user::TABLE,
        static function(array $entity) use ($originalprogramid) {
            return ((int)$entity['programid'] === $originalprogramid) && empty($entity['certificationid']);
        });

        // The new id can be "-1" if we are collecting errors.
        if ($newid && !$this->is_collecting_errors()) {
            $programpersistent = new program($newid);
        } else {
            // Setting to null to avoid set_import_callback to fail.
            $programpersistent = null;
        }

        foreach ($userallocations as $userallocation) {
            $this->import_user_allocation($userallocation, $programpersistent);
        }

        // Recalculate program completion for all the users related to this program.
        if ($newid && !$this->is_collecting_errors()) {
            api::recalculate_all_users_program_progress($programpersistent);
        }
    }

    /**
     * Import programs
     *
     * @throws \coding_exception
     */
    protected function import_programs(): void {
        $settingimporttype = $this->get_import_setting(self::IMPORT_INSTANCES);

        if ($settingimporttype === null) {
            return;
        }

        // Check if we are importing all programs in the file or custom ones set on the import form.
        $filter = ($settingimporttype === self::IMPORT_INSTANCES_ALL) ?
            [] : $this->get_import_setting(self::IMPORT_SELECTED_PROGRAMS);
        $programs = $this->get_entities_in_workplace_export_file('tool_program', static function(array $entity) use ($filter) {
            return (empty($filter) || in_array($entity['id'], $filter));
        });

        // Import course backups if option is selected and user can import courses.
        if ($this->get_import_setting(self::IMPORT_COURSE_BACKUPS) && $this->can_import_chained_entity('course')) {
            if ($this->can_restore_courses()) {
                $courseids = [];
                foreach ($programs as $program) {
                    $courses = $program->get_nested_entities(program_course::TABLE);
                    foreach ($courses as $course) {
                        $courseids[$course['courseid']] = true;
                    }
                }
                $settings = [courses::IMPORT_SELECT_CATEGORY => $this->get_import_setting(self::IMPORT_COURSE_CATEGORY)];
                $this->process_chained_entities('course', $courseids, $settings);
            }
        }

        $importallocations = $this->get_import_setting(self::IMPORT_USER_ALLOCATIONS) && permission::has_allocateuser_capability();

        // Import selected programs.
        $importedprogramids = [];
        foreach ($programs as $program) {
            $this->import_program($program);
            $importedprogramids[] = $program->get_original_id();

            // Check if we need to import program user allocations.
            if ($importallocations) {
                $this->import_user_allocations_for_program($program->get_original_id(), $program->get_new_id());
            }
        }

        if ($importallocations) {
            // Find all programs that were not imported but had program allocations (such as shared programs).
            $allprogramids = $this->get_entities_in_workplace_export_file(program_user::TABLE)->get_menu('programid');
            $otherprogramids = array_diff(array_unique(array_values($allprogramids)), $importedprogramids);
            foreach ($otherprogramids as $id) {
                if ($settingimporttype === self::IMPORT_INSTANCES_ALL || in_array($id, $filter)) {
                    // If we import all programs or this program was selected, try to find it and import user allocations for it.
                    $newid = $this->get_mapping('tool_program', $id, IGNORE_MISSING);
                    if ($newid) {
                        $this->import_user_allocations_for_program($id, $newid);
                    }
                }
            }
        }

    }

    /**
     * Import only specified user allocations
     */
    protected function import_user_allocations() {
        $ids = $this->get_import_setting(self::IMPORT_SELECTED_USER_ALLOCATIONS) ?: [];
        $programusers = $this->get_entities_in_workplace_export_file(program_user::TABLE,
        function($programuser) use ($ids) {
            return in_array($programuser['id'], $ids);
        });
        foreach ($programusers as $programuser) {
            $this->import_user_allocation($programuser);
        }
    }

    /**
     * Add missing default conditions to program.
     *
     * @param int $programid
     */
    public function add_missing_default_dynamicrule_conditions_to_program(int $programid): void {
        global $DB;
        $tenantid = $this->get_import_tenant_id() ?: tenancy::get_tenant_id();
        $configdata = ['programid' => $programid];

        $conditions = api::get_available_dynamicrule_conditions();

        foreach ($conditions as $condition => $stringid) {
            $sql = '
                SELECT 1
                FROM {tool_dynamicrule_condition} drc
                JOIN {tool_dynamicrule} dr
                ON dr.id = drc.ruleid
                WHERE dr.itemid = :itemid AND dr.component = :component AND dr.componentarea = :componentarea
                AND drc.classname = :classname
            ';
            $params0 = [
                'component' => 'tool_program',
                'componentarea' => 'program',
                'itemid' => $programid,
                'classname' => 'tool_program\\tool_dynamicrule\\condition\\' . $condition,
            ];
            $exists = $DB->record_exists_sql($sql, $params0);

            if (!$exists) {
                // Create rule.
                $name = get_string($stringid, 'tool_program');
                $ruleid = \tool_dynamicrule\api::create_rule_for_component('tool_program', 'program',
                    $programid, $tenantid, $name);
                // Create condition. No need to verify user tenancy,
                // we are creating condition for rule that was just created.
                $conditionclass = '\\tool_program\\tool_dynamicrule\\condition\\' . $condition;
                \tool_dynamicrule\api::create_rule_condition($ruleid, $conditionclass, $configdata, true);
            }
        }
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

        parent::add_to_conflict_form($form, $importedentity, $errorcode, $detailsarray);
        if ($errorcode === 'idnumberconflict') {
            $mform = $form->get_quick_form();
            $key = $this->get_conflict_form_element_name($importedentity, $errorcode);
            foreach (['empty', 'increment'] as $action) {
                $mform->addElement('radio', $key, '',
                    $this->get_conflict_solution($importedentity, $errorcode, ['action' => $action]), $action);
            }
            $mform->setType($key, PARAM_ALPHANUMEXT);
        }
    }

    /**
     * Check if another program exists with the same idnumber
     *
     * @param string $idnumber
     * @return bool
     */
    private function idnumber_taken(string $idnumber): bool {
        global $DB;

        return $DB->record_exists('tool_program', [
            'idnumber' => $idnumber,
            'tenantid' => $this->get_import_tenant_id() ?: tenancy::get_tenant_id(),
        ]);
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
        $useincrement = $this->get_conflict_resolution_setting(program::TABLE, 'idnumberconflict', 'action') === 'increment';
        $newvalue = helper::find_unique_value_for_field($record['idnumber'], $lookup, $useincrement);

        if ($newvalue !== $record['idnumber']) {
            $this->add_notice_to_log('idnumberchanged', ['from' => $record['idnumber'], 'to' => $newvalue]);
        }
        return $newvalue;
    }
}
