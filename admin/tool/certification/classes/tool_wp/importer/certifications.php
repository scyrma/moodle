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
 * Certifications importer
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\tool_wp\importer;

use tool_certification\api;
use tool_certification\certification;
use tool_certification\certification_completion;
use tool_certification\certification_user;
use tool_certification\constants;
use tool_certification\output\tab\certification_manager_list_archived_tab;
use tool_certification\permission;
use tool_dynamicrule\tool_wp\importer\rules;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use tool_program\tool_wp\importer\programs;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use tool_wp\importer_base;
use tool_wp\local\exportimport\forms\import_conflict_form;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\wp_imported_entity;

/**
 * Exporter class
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certifications extends importer_base {

    /** @var string Element for selecting what to import. */
    const IMPORT_INSTANCES = 'import_instances';
    /** @var string Element for import all certifications. */
    const IMPORT_INSTANCES_ALL = 'all';
    /** @var string Element for import manually selected certifications. */
    const IMPORT_INSTANCES_SELECTED = 'selected';
    /** @var string Element for selecting if certification settings have to be imported. */
    const IMPORT_CONTENT = 'import_content';
    /** @var string Element for selecting what to import. */
    const IMPORT_SELECT_CERTIFICATIONS = 'select_certifications';
    /** @var string Element for importing programs */
    const IMPORT_PROGRAMS = 'import_programs';
    /** @var string Element for selecting if certification user allocations have to be imported. */
    const IMPORT_USER_ALLOCATIONS = 'import_user_allocations';
    /** @var string Element for selecting if courses have to be imported. */
    const IMPORT_COURSE_BACKUPS = 'import_course_backups';
    /** @var string Element for selecting if certification dynamic rules have to be imported. */
    const IMPORT_DYNAMICRULES = 'import_dynamic_rules';
    /** @var string Element for selecting the category where courses have to be imported. */
    const IMPORT_SELECT_CATEGORY = 'select_category';
    /** @var string Import only specific certification allocations (list of ids) - used only in chained import */
    const IMPORT_SELECTED_USER_ALLOCATIONS = 'select_user_allocations';

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
        return get_string('certifications', 'tool_certification');
    }

    /**
     * Allows to mark importer as not available
     *
     * By default every importer is available for general import and not available for any entrypoint
     *
     * @return bool
     */
    public function is_available(): bool {
        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint()) && permission::can_create();
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
        $this->register_entity(certification::TABLE, [
            self::ENTITY_DEPENDENCIES => ['tool_tenant', 'tool_program'],
            self::ENTITY_LOGSUCCESS => function(int $id, array $importerdetails) {
                $importerdetails['url'] = (empty($importerdetails['archived']) ?
                    new \moodle_url('/admin/tool/certification/edit.php', ['id' => $id]) :
                    new \moodle_url('/admin/tool/certification/index.php', null,
                        (new certification_manager_list_archived_tab([]))->get_tab_id()))->out();
                $importerdetails['fullname'] = format_string($importerdetails['fullname']);
                return get_string('importlogsuccess', 'tool_certification',
                    (object)$importerdetails);
            },
            self::ENTITY_LOGERROR => function(array $importerdetails) {
                $importerdetails['fullname'] = format_string($importerdetails['fullname']);
                return get_string('importlogfailed', 'tool_certification',
                    (object)$importerdetails);
            },
            self::ENTITY_NAMEPLURAL => get_string('certifications', 'tool_certification'),
            self::ENTITY_INDIVIDUALIMPORT => function(array $ids, array $settings) {
                // What parameters to use when importing individual entity as part of some other import.
                // May be called from tenants import.
                $defaults = [
                    self::IMPORT_CONTENT => 1,
                    self::IMPORT_PROGRAMS => 1,
                    self::IMPORT_USER_ALLOCATIONS => 0,
                    self::IMPORT_COURSE_BACKUPS => 0,
                    self::IMPORT_DYNAMICRULES => 0,
                    self::IMPORT_SELECT_CATEGORY => 0
                ];
                return array_intersect_key($settings, $defaults) + $defaults +
                    [
                        self::IMPORT_INSTANCES => self::IMPORT_INSTANCES_SELECTED,
                        self::IMPORT_SELECT_CERTIFICATIONS => $ids,
                    ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                return format_string($record['fullname']);
            },
        ]);

        $this->register_entity(certification_user::TABLE, [
            self::ENTITY_DEPENDENCIES => [certification::TABLE, program::TABLE, 'user'],
            self::ENTITY_LOGSUCCESS => function(int $id, array $importerdetails) {
                $importerdetails['certification'] = format_string($importerdetails['certification']);
                return get_string('importlogsuccessuserallocations', 'tool_certification',
                    (object)$importerdetails);
            },
            self::ENTITY_LOGERROR => function(array $importerdetails) {
                $a = (object)[
                    'originaluserfullname' => s($importdetails['userfullname'] ?? $importerdetails['originaluserfullname']),
                    'certification' => format_string($importerdetails['certification']),
                ];
                return get_string('errorcouldnotallocate', 'tool_certification', $a);
            },
            self::ENTITY_NAMEPLURAL => get_string('import_user_allocations', 'tool_certification'),
            self::ENTITY_INDIVIDUALIMPORT => function(array $ids, array $settings) {
                // What parameters to use when importing individual entity as part of some other import.
                // May be called from tenants import.
                $defaults = [
                    self::IMPORT_CONTENT => 1,
                    self::IMPORT_PROGRAMS => 1,
                    self::IMPORT_USER_ALLOCATIONS => 0,
                    self::IMPORT_COURSE_BACKUPS => 1,
                    self::IMPORT_DYNAMICRULES => 1,
                ];
                return array_intersect_key($settings, $defaults) + $defaults +
                    [
                        self::IMPORT_INSTANCES => self::IMPORT_INSTANCES_SELECTED,
                        self::IMPORT_SELECT_CERTIFICATIONS => $ids,
                        self::IMPORT_SELECTED_USER_ALLOCATIONS => $ids,
                    ];
            }
        ]);

        $this->register_potential_error(certification::TABLE, 'idnumberconflict',
            [
                self::ERROR_LOG => function(array $details) {
                    $importerdetails = array_map('s', $details);
                    return get_string('importlogidnumberexists', 'tool_certification',
                        (object)$importerdetails);
                },
                self::ERROR_CONFLICTHEADER => get_string('errorsameidnumber', 'tool_certification'),
                self::ERROR_CONFLICTSOLUTION => function(array $settings, bool $forform) {
                    switch ($settings['action']) {
                        case 'increment':
                            return get_string('importincrementidnumber', 'tool_wp');
                        case 'empty':
                            return get_string('importsetidnumbertoempty', 'tool_wp');
                    }
                    return null;
                },
            ]);

        $this->register_potential_notice(certification::TABLE, 'idnumberchanged',
            [
                self::NOTICE_LOG => static function(array $details, array $noticedetails) {
                    $a = (object)array_map('s', $noticedetails);
                    return get_string('idnumberchanged', 'tool_wp', $a);
                }
            ]);

        $this->register_potential_error(certification_user::TABLE, 'cannotallocate',
            [
                self::ERROR_LOG => function(array $details) {
                    $importerdetails = array_map('s', $details);
                    return get_string('importcannotallocate', 'tool_certification',
                        (object)$importerdetails);
                },
                self::ERROR_CONFLICTHEADER => get_string('errorcannotallocate', 'tool_certification'),
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
        $mform->addElement('header', 'content', get_string('content', 'tool_certification'));
        $mform->setExpanded('content');

        $settingsstr = get_string('import_content', 'tool_certification');
        $mform->addElement('advcheckbox', self::IMPORT_CONTENT, $settingsstr);
        $mform->setDefault(self::IMPORT_CONTENT, 1);
        $mform->setType(self::IMPORT_CONTENT, PARAM_INT);
        $mform->addHelpButton(self::IMPORT_CONTENT, 'import_content', 'tool_certification');
        $mform->freeze(self::IMPORT_CONTENT);
        $mform->setConstant(self::IMPORT_CONTENT, 1);

        $programsstr = get_string('import_programs', 'tool_certification');
        $hasprograms = $this->get_entities_in_workplace_export_file('tool_program')->count();
        $canimportprograms = $hasprograms && $this->can_import_chained_entity(program::TABLE);
        $mform->addElement('advcheckbox', self::IMPORT_PROGRAMS, $programsstr);
        $mform->setDefault(self::IMPORT_PROGRAMS, 1);
        $mform->setType(self::IMPORT_PROGRAMS, PARAM_INT);
        $mform->addHelpButton(self::IMPORT_PROGRAMS, 'import_programs', 'tool_certification');
        if (!$canimportprograms) {
            $form->freeze_at(self::IMPORT_PROGRAMS, 0);
        }

        $hascourses = $this->get_entities_in_workplace_export_file('course')->count();
        $canimportcourses = $hascourses && $this->can_import_chained_entity('course');
        $coursebackupsstr = get_string('importcoursecontent', 'tool_wp');
        $mform->addElement('advcheckbox', self::IMPORT_COURSE_BACKUPS, $coursebackupsstr);
        $mform->setDefault(self::IMPORT_COURSE_BACKUPS, 1);
        $mform->setType(self::IMPORT_COURSE_BACKUPS, PARAM_INT);
        $mform->addHelpButton(self::IMPORT_COURSE_BACKUPS, 'importcoursecontent', 'tool_wp');
        $mform->hideIf(self::IMPORT_COURSE_BACKUPS, self::IMPORT_PROGRAMS, 'noteq', 1);

        $tenantid = $this->get_import_tenant_id();
        $tenantcategory = tenancy::get_tenants()[$tenantid]->categoryid;

        // Destination category picker. Show all categories where this user is able to create courses.
        // A program can have shared courses, a user can import courses in other categories than just only the tenant category.
        $categories = \core_course_category::make_categories_list('moodle/course:create');

        $group = [];
        $html = \html_writer::span(get_string('selectcoursecategory', 'tool_wp') . ':', 'mr-2 my-2');
        $group[] = $mform->createElement('static', 'selector', '', $html);
        $group[] = $coursecategory = $mform->createElement('select', self::IMPORT_SELECT_CATEGORY,
            get_string('selectcoursecategory', 'tool_wp'), $categories);
        $coursecategory->setHiddenLabel(true);
        $mform->addGroup($group, 'selectcatgroup', '', '', false);
        $mform->hideIf('selectcatgroup', self::IMPORT_COURSE_BACKUPS, 'noteq', 1);
        $mform->hideIf('selectcatgroup', self::IMPORT_PROGRAMS, 'noteq', 1);
        // If tenant has a category set default to this category.
        if ($tenantcategory) {
            $mform->setDefault(self::IMPORT_SELECT_CATEGORY, $tenantcategory);
        }

        // Manage course categories link.
        $manageurl = new \moodle_url('/course/management.php');
        $html = \html_writer::link($manageurl, get_string('managecoursecategories', 'tool_wp'));
        $group = [];
        $group[] = $mform->createElement('static', 'managecat', '', $html);
        $mform->addGroup($group, 'managecatgroup', '', ' ', false);
        $mform->hideIf('managecatgroup', self::IMPORT_COURSE_BACKUPS, 'noteq', 1);
        $mform->hideIf('managecatgroup', self::IMPORT_PROGRAMS, 'noteq', 1);

        if (!$canimportprograms || !$canimportcourses) {
            $form->freeze_at(self::IMPORT_COURSE_BACKUPS, 0);
            $form->freeze_at(self::IMPORT_SELECT_CATEGORY, null);
        }

        $usersstr = get_string('import_user_allocations', 'tool_certification');
        $mform->addElement('advcheckbox', self::IMPORT_USER_ALLOCATIONS, $usersstr);
        $mform->setDefault(self::IMPORT_USER_ALLOCATIONS, 0);
        $mform->setType(self::IMPORT_USER_ALLOCATIONS, PARAM_INT);
        $mform->addHelpButton(self::IMPORT_USER_ALLOCATIONS, 'import_user_allocations', 'tool_certification');
        if (!$this->get_entities_in_workplace_export_file(certification_user::TABLE)->count() ||
            !permission::has_allocateuser_capability()) {
            $form->freeze_at(self::IMPORT_USER_ALLOCATIONS, 0);
        }

        $dynamicrulesstr = get_string('import_dynamic_rules', 'tool_certification');
        $mform->addElement('advcheckbox', self::IMPORT_DYNAMICRULES, $dynamicrulesstr);
        $mform->setDefault(self::IMPORT_DYNAMICRULES, 1);
        $mform->setType(self::IMPORT_DYNAMICRULES, PARAM_INT);
        $mform->addHelpButton(self::IMPORT_DYNAMICRULES, 'import_dynamic_rules', 'tool_certification');
        if (!$this->get_entities_in_workplace_export_file('tool_dynamicrule')->count() ||
            !$this->can_import_chained_entity('tool_dynamicrule')) {
            $form->freeze_at(self::IMPORT_DYNAMICRULES, 0);
        }

        $mform->addElement('header', 'instances', get_string('instances', 'tool_wp'));
        $mform->setExpanded('instances');

        $selectall = get_string('selectallcertificationsinthisfile', 'tool_certification');
        $selectmanually = get_string('selectmanually', 'tool_certification');
        $mform->addElement('radio', self::IMPORT_INSTANCES, null, $selectall, self::IMPORT_INSTANCES_ALL);
        $mform->addElement('radio', self::IMPORT_INSTANCES, null, $selectmanually, self::IMPORT_INSTANCES_SELECTED);
        $mform->setType(self::IMPORT_INSTANCES, PARAM_ALPHANUM);
        $mform->setDefault(self::IMPORT_INSTANCES, self::IMPORT_INSTANCES_ALL);

        // Certification picker to allow user to limit which certifications to import, sorted by fullname.
        $certslistinfile = $this->get_all_certifications_in_export_file_as_menu();

        $mform->addElement('autocomplete', self::IMPORT_SELECT_CERTIFICATIONS,
            get_string('certifications', 'tool_certification'),
            $certslistinfile, ['multiple' => true])->setHiddenLabel(true);
        $mform->setType(self::IMPORT_SELECT_CERTIFICATIONS, PARAM_INT);
        $mform->hideIf(self::IMPORT_SELECT_CERTIFICATIONS, self::IMPORT_INSTANCES, 'noteq', self::IMPORT_INSTANCES_SELECTED);

        $form->add_validation_callback([$this, 'validate_options_form']);
    }

    /**
     * Get all certifications present in this file and also certifications that were not exported but with exported user allocations
     *
     * @return array
     */
    protected function get_all_certifications_in_export_file_as_menu(): array {
        $certslistinfile = array_map('format_string',
            $this->get_entities_in_workplace_export_file(certification::TABLE)->get_menu('fullname'));

        foreach ($this->get_entities_in_workplace_export_file(certification_user::TABLE) as $certificationuser) {
            $id = $certificationuser->get_raw_field('certificationid');
            if (!array_key_exists($id, $certslistinfile)) {
                $name = $certificationuser->get_raw_field('certificationfullname');
                $certslistinfile[$id] = ($name !== null ? format_string($name) : $id) .
                    ' ' . get_string('exportonlyallocationspostfix', 'tool_certification');
            }
        }
        \core_collator::asort($certslistinfile);
        return $certslistinfile;
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
        $settingsstr = get_string('import_content', 'tool_certification');
        $settings[] = ['name' => $settingsstr, 'value' => !empty($data[self::IMPORT_CONTENT])];

        $programsstr = get_string('import_programs', 'tool_certification');
        $settings[] = ['name' => $programsstr, 'value' => !empty($data[self::IMPORT_PROGRAMS])];

        if (!empty($data[self::IMPORT_PROGRAMS])) {
            $coursebackupsstr = get_string('importcoursecontent', 'tool_wp');
            $settings[] = ['name' => $coursebackupsstr, 'value' => !empty($data[self::IMPORT_COURSE_BACKUPS])];
        }

        $usersstr = get_string('import_user_allocations', 'tool_certification');
        $settings[] = ['name' => $usersstr, 'value' => !empty($data[self::IMPORT_USER_ALLOCATIONS])];

        $dynamicrulesstr = get_string('import_dynamic_rules', 'tool_certification');
        $settings[] = ['name' => $dynamicrulesstr, 'value' => !empty($data[self::IMPORT_DYNAMICRULES])];

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
        if ($c1 = $this->get_entities_in_workplace_export_file('tool_certification')->count()) {
            $rv[] = get_string('certifications', 'tool_certification') .
                get_string('entitiescountpostfix', 'tool_wp', $c1);
        }
        if ($c1 = $this->get_entities_in_workplace_export_file('tool_certification_users')->count()) {
            $rv[] = get_string('import_user_allocations', 'tool_certification') .
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

        if ($data[self::IMPORT_INSTANCES] === self::IMPORT_INSTANCES_SELECTED && empty($data[self::IMPORT_SELECT_CERTIFICATIONS])) {
            $errors[self::IMPORT_SELECT_CERTIFICATIONS] = get_string('selectatleastonecertification', 'tool_certification');
        }

        if (!empty($data[self::IMPORT_COURSE_BACKUPS])) {
            // TODO error must be on the element, not group...
            if (empty($data[self::IMPORT_SELECT_CATEGORY])) {
                $errors['managecatgroup'] = get_string('err_required', 'form');
            } else if (!($coursecategoryctx = \context_coursecat::instance($data[self::IMPORT_SELECT_CATEGORY], IGNORE_MISSING)) ||
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
        if ($entity === 'tool_certification') {
            $this->import_certifications();
        }

        if ($entity === 'tool_certification_users' && $this->get_import_setting(self::IMPORT_SELECTED_USER_ALLOCATIONS)
                && $this->get_import_setting(self::IMPORT_USER_ALLOCATIONS)) {
            $this->import_user_allocations();
        }
    }

    /**
     * Are we importing certification allocations?
     *
     * @return bool
     */
    protected function get_setting_import_user_allocations(): bool {
        return $this->get_import_setting(self::IMPORT_USER_ALLOCATIONS) && permission::has_allocateuser_capability();
    }

    /**
     * Import one certification with programs and dynamic rules (if applicable)
     *
     * @param wp_imported_entity $certification
     */
    protected function import_certification(wp_imported_entity $certification) {
        $certification
            ->add_mapping('tenantid', 'tool_tenant')
            ->add_mapping('program', 'tool_program')
            ->add_mapping('recertificationprogram', 'tool_program')
            ->set_validation_callback(function($data, wp_imported_entity $entity) {
                $this->add_details_to_log([
                    'fullname' => $data['fullname'],
                    'originalidnumber' => $data['idnumber']
                ]);

                // Make sure the idnumber is unique, if not unique and conflict resolution was not set,
                // raise an error. Raised error will fail validation.
                if (!empty($data['idnumber']) &&
                        certification::get_records(['tenantid' => $data['tenantid'], 'idnumber' => $data['idnumber']])) {
                    $this->add_error_to_log('idnumberconflict');
                }
            })
            ->set_import_callback(function($data, wp_imported_entity $entity) {
                // We need to increment or remove idnumber according to the choosen option in the form.
                $data['idnumber'] = $this->get_new_idnumber($data, $entity);
                // If this is not shared space shared property should be 0.
                $data['shared'] = sharedspace::is_shared_space($data['tenantid']) ? 1 : 0; // Hardcoded for now.

                $certificationrecord = array_intersect_key($data, certification::properties_definition());
                ($certificationpersistent = new certification(0, (object) $certificationrecord))->save();

                $this->add_details_to_log([
                    'id' => $certificationpersistent->get('id'),
                    'archived' => $certificationpersistent->get('archived'),
                ]);

                if (isset($data['certification_tags'])) {
                    $tags = explode(',', $data['certification_tags']);
                    $context = \context_system::instance();
                    \core_tag_tag::set_item_tags('tool_certification', 'tool_certification',
                        $certificationpersistent->get('id'), $context, $tags);
                }

                // Return new certification id.
                return $certificationpersistent->get('id');
            })
            ->import($this);

        if (!$this->is_collecting_errors() && !$certification->get_new_id()) {
            return;
        }

        // Import certification custom fields.
        $this->process_chained_entities('customfield_data', [], [
            'component' => 'tool_certification',
            'area' => 'certification',
            'instancemapper' => 'tool_certification',
            'instanceid' => $certification->get_original_id(),
        ]);

        // Check if we need to import certification dynamic rules.
        if ($this->get_import_setting(self::IMPORT_DYNAMICRULES) && $this->can_import_chained_entity('tool_dynamicrule')) {
            $this->process_chained_entities('tool_dynamicrule', [], [
                rules::IMPORT_INSTANCES => rules::IMPORT_INSTANCES_COMPONENT,
                rules::IMPORT_SELECT_COMPONENT => 'tool_certification',
                rules::IMPORT_SELECT_COMPONENT_AREA => 'certification',
                rules::IMPORT_SELECT_COMPONENT_ITEMID => $certification->get_original_id(),
                rules::IMPORT_SELECT_COMPONENT_MAPPER => 'tool_certification',
            ]);

            // Make sure certification has all dynamic rules, if not create missing.
            if ($certification->get_new_id() && !$this->is_collecting_errors()) {
                $this->add_missing_default_dynamicrule_conditions_to_certification($certification->get_new_id());
            }

        } else if ($certification->get_new_id() && !$this->is_collecting_errors()) {
            // If no DR were imported create the default ones for the certification.
            api::add_default_dynamicrule_conditions_to_certification($certification->get_new_id(),
                $this->get_import_tenant_id() ?: tenancy::get_tenant_id());
        }
    }


    /**
     * Import one user certification allocation
     *
     * @param wp_imported_entity $userallocation
     * @param certification|null $certificationpersistent
     */
    protected function import_user_allocation(wp_imported_entity $userallocation, ?certification $certificationpersistent = null) {
        $userallocation
            ->add_mapping('userid', 'user')
            ->add_mapping('certificationid', 'tool_certification')
            ->set_validation_callback(function ($data, wp_imported_entity $entity) use (&$certificationpersistent) {
                $this->add_details_to_log([
                    'certificationid' => $data['certificationid'],
                    'userid' => $data['userid'],
                    'originaluserfullname' => $data['userfullname'],
                    'certification' => $data['certificationfullname'] ?? $data['certificationid']
                ]);
                if ($data['userid'] > 0) {
                    $this->add_details_to_log(['userfullname' => fullname(\core_user::get_user($data['userid']))]);
                }
                if (!$certificationpersistent && $data['certificationid'] > 0) {
                    $certificationpersistent = new certification($data['certificationid']);
                }
                if ($certificationpersistent && $data['userid'] &&
                        !permission::can_allocate($certificationpersistent, $data['userid'], false)) {
                    $this->add_error_to_log('cannotallocate');
                }
            })
            ->set_import_callback(function ($data, wp_imported_entity $entity) use ($certificationpersistent) {
                $userrecord = array_intersect_key($data, certification_user::properties_definition());
                $certificationpersistent = $certificationpersistent ?: new certification($userrecord['certificationid']);

                // User allocation completions.
                $usercompletions = $entity->get_nested_entities(certification_completion::TABLE);
                foreach ($usercompletions as $usercompletion) {
                    $usercomprecord = array_intersect_key($usercompletion,
                        certification_completion::properties_definition());
                    $newprogramid = $this->get_mapping(program::TABLE, $usercomprecord['programid']);

                    (new certification_completion(0, (object) $usercomprecord))
                        ->set('certificationid', $certificationpersistent->get('id'))
                        ->set('userid', $userrecord['userid'])
                        ->set('programid', $newprogramid)
                        ->save();
                }

                // Certification user allocations.
                $newcurrentprogramid = $this->get_mapping(program::TABLE, $data['currentprogramid']);
                // We changed currentprogramid in export from null to 0. We need to revert it.
                $newcurrentprogramid = ((int)$newcurrentprogramid === 0) ? null : $newcurrentprogramid;

                // Allocations on this exported file can be manual or from dynamic rules. Convert all to manual.
                ($certificationuser = new certification_user(0, (object) $userrecord))
                    ->set('certificationid', $certificationpersistent->get('id'))
                    ->set('userid', $userrecord['userid'])
                    ->set('currentprogramid', $newcurrentprogramid)
                    ->set('allocationtype', constants::ALLOCATION_MANUAL)
                    ->save();

                $this->add_details_to_log(['certification' => $certificationpersistent->get('fullname')]);

                // Related Program user allocations.
                $users = $entity->get_nested_entities(program_user::TABLE);
                foreach ($users as $user) {
                    $userrecord = array_intersect_key($user, program_user::properties_definition());
                    $newprogramid = $this->get_mapping(program::TABLE, $userrecord['programid']);

                    (new program_user(0, (object) $userrecord))
                        ->set('certificationid', $certificationpersistent->get('id'))
                        ->set('userid', $userrecord['userid'])
                        ->set('programid', $newprogramid)
                        ->save();
                }

                return $certificationuser->get('id');

            })->import($this);
    }

    /**
     * Import user allocations
     *
     * @param int $originalcertificationid
     * @param int $newid
     */
    protected function import_user_allocations_for_certification(int $originalcertificationid, ?int $newid) {
        $userallocations = $this->get_entities_in_workplace_export_file(certification_user::TABLE,
        static function(array $entity) use ($originalcertificationid) {
            return (int)$entity['certificationid'] === $originalcertificationid;
        });

        // The new id can be "-1" if we are collecting errors.
        if ($newid && !$this->is_collecting_errors()) {
            $certificationpersistent = new certification($newid);
        } else {
            // Setting to null to avoid set_import_callback to fail.
            $certificationpersistent = null;
        }

        foreach ($userallocations as $userallocation) {
            $this->import_user_allocation($userallocation, $certificationpersistent);
        }
    }

    /**
     * Import certifications
     *
     * @throws \coding_exception
     */
    protected function import_certifications(): void {
        $settingimporttype = $this->get_import_setting(self::IMPORT_INSTANCES);

        if ($settingimporttype === null) {
            return;
        }

        // Check if we are importing all certifications in the file or custom ones set on the import form.
        $filter = ($settingimporttype !== self::IMPORT_INSTANCES_SELECTED) ?
            [] : $this->get_import_setting(self::IMPORT_SELECT_CERTIFICATIONS);
        $certifications = $this->get_entities_in_workplace_export_file('tool_certification',
            function(array $entity) use ($filter) {
                return (empty($filter) || in_array($entity['id'], $filter));
            });

        // Check if we selected to import programs and if user has permission to import them.
        if ($this->get_import_setting(self::IMPORT_PROGRAMS) && $this->can_import_chained_entity(program::TABLE)) {
            $programids = [];
            foreach ($certifications as $certification) {
                if ($pid = $certification->get_raw_field('program')) {
                    $programids[$pid] = true;
                }
                if ($pid = $certification->get_raw_field('recertificationprogram')) {
                    $programids[$pid] = true;
                }
            }
            $settings = [];
            // Check if we selected to import courses and if user has permission to import them.
            if ($this->get_import_setting(self::IMPORT_COURSE_BACKUPS) && $this->can_import_chained_entity('course')) {
                $settings = [
                    programs::IMPORT_COURSE_BACKUPS => 1,
                    programs::IMPORT_COURSE_CATEGORY => $this->get_import_setting(self::IMPORT_SELECT_CATEGORY),
                ];
            } else {
                $settings = [
                    programs::IMPORT_COURSE_BACKUPS => 0,
                    programs::IMPORT_COURSE_CATEGORY => 0,
                ];
            }
            // Check if we selected to import dynamic rules and if user has permission to import them.
            if ($this->get_import_setting(self::IMPORT_DYNAMICRULES) && $this->can_import_chained_entity('tool_dynamicrule')) {
                $settings[programs::IMPORT_PROGRAM_DYNAMICRULES] = 1;
            } else {
                $settings[programs::IMPORT_PROGRAM_DYNAMICRULES] = 0;
            }
            $this->process_chained_entities(program::TABLE, $programids, $settings);
        }

        $importedcertificationids = [];
        // Import selected certifications.
        foreach ($certifications as $certification) {
            $this->import_certification($certification);
            $importedcertificationids[] = $certification->get_original_id();

            if ($this->get_setting_import_user_allocations()) {
                $this->import_user_allocations_for_certification($certification->get_original_id(), $certification->get_new_id());
            }
        }

        if ($this->get_setting_import_user_allocations()) {
            // Find all certifications that were not imported but had certification allocations (such as shared certifications).
            $allcertificationids = $this->get_entities_in_workplace_export_file(certification_user::TABLE)
                ->get_menu('certificationid');
            $allcertificationids = array_unique(array_values($allcertificationids));
            $othercertificationids = array_diff($allcertificationids, $importedcertificationids);
            foreach ($othercertificationids as $id) {
                if ($settingimporttype === self::IMPORT_INSTANCES_ALL || in_array($id, $filter)) {
                    // If we import all certifications or this certification was selected,
                    // try to find it and import user allocations for it.
                    $newid = $this->get_mapping('tool_certification', $id, IGNORE_MISSING);
                    if ($newid) {
                        $this->import_user_allocations_for_certification($id, $newid);
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
        $certificationusers = $this->get_entities_in_workplace_export_file(certification_user::TABLE,
        function($certificationuser) use ($ids) {
            return in_array($certificationuser['id'], $ids);
        });
        foreach ($certificationusers as $certificationuser) {
            $this->import_user_allocation($certificationuser);
        }
    }

    /**
     * Adds missing default rules to certification.
     *
     * @param int $certificationid
     */
    public function add_missing_default_dynamicrule_conditions_to_certification(int $certificationid): void {
        global $DB;
        $tenantid = $this->get_import_tenant_id() ?: tenancy::get_tenant_id();
        $configdata = ['certificationid' => $certificationid];

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
                'component' => 'tool_certification',
                'componentarea' => 'certification',
                'itemid' => $certificationid,
                'classname' => 'tool_certification\\tool_dynamicrule\\condition\\' . $condition,
            ];
            $exists = $DB->record_exists_sql($sql, $params0);

            if (!$exists) {
                // Create rule.
                $name = get_string($stringid, 'tool_certification');
                $ruleid = \tool_dynamicrule\api::create_rule_for_component('tool_certification', 'certification',
                    $certificationid, $tenantid, $name);
                // Create condition. No need to verify user tenancy,
                // we are creating condition for rule that was just created.
                $conditionclass = '\\tool_certification\\tool_dynamicrule\\condition\\' . $condition;
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
        $mform = $form->get_quick_form();
        parent::add_to_conflict_form($form, $importedentity, $errorcode, $detailsarray);
        if ($errorcode === 'idnumberconflict') {
            $key = $this->get_conflict_form_element_name($importedentity, $errorcode);
            foreach (['empty', 'increment'] as $action) {
                $mform->addElement('radio', $key, '',
                    $this->get_conflict_solution($importedentity, $errorcode, ['action' => $action]), $action);
            }
            $mform->setType($key, PARAM_ALPHANUMEXT);
        }
    }

    /**
     * Check if another certification exists with the same idnumber
     *
     * @param string $idnumber
     * @return bool
     */
    private function idnumber_taken(string $idnumber): bool {
        global $DB;
        return $DB->record_exists(certification::TABLE, [
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
        $useincrement = $this->get_conflict_resolution_setting(certification::TABLE, 'idnumberconflict', 'action') === 'increment';
        $newvalue = helper::find_unique_value_for_field($record['idnumber'], $lookup, $useincrement);

        if ($newvalue !== $record['idnumber']) {
            $this->add_notice_to_log('idnumberchanged', ['from' => $record['idnumber'], 'to' => $newvalue]);
            return $newvalue;
        }
        return $newvalue;
    }
}
