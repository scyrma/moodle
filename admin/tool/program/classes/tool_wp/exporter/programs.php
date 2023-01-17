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
 * Programs exporter
 *
 * @package   tool_program
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\tool_wp\exporter;

use core_user\fields;
use tool_dynamicrule\tool_wp\exporter\rules;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;
use tool_program\persistent\program_user;
use tool_tenant\hierarchy;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use tool_wp\exporter_base;
use tool_wp\local\exportimport\forms\export_settings_form;
use tool_wp\local\exportimport\helper;

/**
 * Exporter class
 *
 * @package   tool_program
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class programs extends exporter_base {

    /** @var string Element for selecting what to export. */
    const EXPORT_INSTANCES = 'export_instances';
    /** @var string Element for export all programs. */
    const EXPORT_INSTANCES_ALL = 'all';
    /** @var string Element for export only active programs. */
    const EXPORT_INSTANCES_ACTIVE = 'active';
    /** @var string Element for export manually selected programs. */
    const EXPORT_INSTANCES_SELECTED = 'selected';
    /** @var string Element for selecting what to export. */
    const EXPORT_SELECT_PROGRAMS = 'select_programs';
    /** @var string Element for selecting if program user allocations have to be exported. */
    const EXPORT_USER_ALLOCATIONS = 'export_user_allocations';
    /** @var string Element for selecting if courses have to be exported. */
    const EXPORT_COURSE_BACKUPS = 'export_courses';
    /** @var string Element for selecting if courses content have to be exported. */
    const EXPORT_COURSE_BACKUPS_CONTENT = 'export_courses_content';
    /** @var string Element for selecting if program settings have to be exported. */
    const EXPORT_CONTENT = 'export_content';
    /** @var string Element for selecting if program dynamic rules have to be exported. */
    const EXPORT_PROGRAM_DYNAMICRULES = 'export_dynamic_rules';
    /** @var string Element for including shared entities. */
    const INCLUDE_SHARED_ENTITIES = 'include_shared_entities';

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
        return get_string('programs', 'tool_program');
    }

    /**
     * Exporter description to show in the list of available exporters
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('exportprogramsdescription', 'tool_program');
    }

    /**
     * Exporter icon url
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('icon', 'tool_program')->out(false);
    }

    /**
     * Allows to mark exporter as not available
     *
     * By default every exporter is avilable for general export and not available for any entrypoint
     *
     * @return bool
     */
    public function is_available(): bool {
        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint())
            && permission::can_create();
    }

    /**
     * Initialise the class, register entities that can be exported from other places
     */
    protected function initialise() {
        $this->register_entity('tool_program', [
            self::ENTITY_INDIVIDUALEXPORT => function(array $ids, array $settings) {
                // What parameters to use when exporting individual entity as part of some other export.
                // May be called from certifications and tenants export.
                $defaults = [
                    self::EXPORT_INSTANCES => self::EXPORT_INSTANCES_SELECTED,
                    self::EXPORT_CONTENT => 1,
                    self::EXPORT_USER_ALLOCATIONS => 0,
                    self::EXPORT_COURSE_BACKUPS => 1,
                    self::EXPORT_COURSE_BACKUPS_CONTENT => 0,
                    self::EXPORT_PROGRAM_DYNAMICRULES => 1,
                    self::INCLUDE_SHARED_ENTITIES => 0,
                ];
                return array_intersect_key($settings, $defaults) + $defaults + [
                    self::EXPORT_SELECT_PROGRAMS => $ids,
                ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                $rv = format_string($record['fullname']);
                $rv .= (!empty($record['onlyallocations'])) ?
                    ' ' . get_string('exportonlyallocationspostfix', 'tool_program') : '';
                return $rv;
            },
        ]);
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

        $mform->addElement('header', 'content', get_string('content', 'tool_program'));
        $mform->setExpanded('content');

        $mform->addElement('advcheckbox', self::EXPORT_CONTENT, get_string('export_content', 'tool_program'));
        $mform->setDefault(self::EXPORT_CONTENT, 1);
        $mform->addHelpButton(self::EXPORT_CONTENT, 'export_content', 'tool_program');
        $form->freeze_at(self::EXPORT_CONTENT, 1);

        $coursebackupsstr = get_string('exportcoursecontent', 'tool_wp');
        $mform->addElement('advcheckbox', self::EXPORT_COURSE_BACKUPS, $coursebackupsstr);
        $mform->setDefault(self::EXPORT_COURSE_BACKUPS, 1);
        $mform->addHelpButton(self::EXPORT_COURSE_BACKUPS, 'exportcoursecontent', 'tool_wp');
        if (!$this->can_export_chained_entity('course')) {
            $form->freeze_at(self::EXPORT_COURSE_BACKUPS, 0);
        }

        $coursebackupsstr = get_string('includecoursecontent', 'tool_wp');
        $mform->addElement('advcheckbox', self::EXPORT_COURSE_BACKUPS_CONTENT, $coursebackupsstr);
        $mform->setDefault(self::EXPORT_COURSE_BACKUPS_CONTENT, 0);
        $mform->addHelpButton(self::EXPORT_COURSE_BACKUPS_CONTENT, 'includecoursecontent', 'tool_wp');
        $mform->hideIf(self::EXPORT_COURSE_BACKUPS_CONTENT, self::EXPORT_COURSE_BACKUPS, 'eq', 0);
        if (!$this->can_export_chained_entity('course') ||
                !\tool_wp\permission::can_export_course_content()) {
            $form->freeze_at(self::EXPORT_COURSE_BACKUPS_CONTENT, 0);
        }

        $usersstr = get_string('export_user_allocations', 'tool_program');
        $mform->addElement('advcheckbox', self::EXPORT_USER_ALLOCATIONS, $usersstr);
        $mform->setDefault(self::EXPORT_USER_ALLOCATIONS, 0);
        $mform->addHelpButton(self::EXPORT_USER_ALLOCATIONS, 'export_user_allocations', 'tool_program');
        if (!permission::has_allocateuser_capability()) {
            $form->freeze_at(self::EXPORT_USER_ALLOCATIONS, 0);
        }

        $dynamicrulesstr = get_string('export_dynamic_rules', 'tool_program');
        $mform->addElement('advcheckbox', self::EXPORT_PROGRAM_DYNAMICRULES, $dynamicrulesstr);
        $mform->setDefault(self::EXPORT_PROGRAM_DYNAMICRULES, 1);
        $mform->addHelpButton(self::EXPORT_PROGRAM_DYNAMICRULES, 'export_dynamic_rules', 'tool_program');
        if (!$this->can_export_chained_entity('tool_dynamicrule')) {
            $form->freeze_at(self::EXPORT_PROGRAM_DYNAMICRULES, 0);
        }

        $mform->addElement('header', 'instances', get_string('instances', 'tool_wp'));
        $mform->setExpanded('instances');

        $selectactive = get_string('selectallactiveprograms', 'tool_program');
        $selectall = get_string('selectactiveandarchivedprograms', 'tool_program');
        $selectmanually = get_string('selectmanually', 'tool_program');
        $mform->addElement('radio', self::EXPORT_INSTANCES, null, $selectactive, self::EXPORT_INSTANCES_ACTIVE);
        $mform->addElement('radio', self::EXPORT_INSTANCES, null, $selectall, self::EXPORT_INSTANCES_ALL);
        $mform->addElement('radio', self::EXPORT_INSTANCES, null, $selectmanually, self::EXPORT_INSTANCES_SELECTED);
        $mform->setType(self::EXPORT_INSTANCES, PARAM_ALPHANUM);
        $mform->setDefault(self::EXPORT_INSTANCES, self::EXPORT_INSTANCES_ACTIVE);

        // Programs picker.
        $mform->addElement('autocomplete', self::EXPORT_SELECT_PROGRAMS, get_string('programs', 'tool_program'),
            $this->get_programs_list(), ['multiple' => true])->setHiddenLabel(true);
        $mform->setType(self::EXPORT_SELECT_PROGRAMS, PARAM_INT);
        $mform->hideIf(self::EXPORT_SELECT_PROGRAMS, self::EXPORT_INSTANCES, 'noteq', self::EXPORT_INSTANCES_SELECTED);

        $mform->addElement('static', 'sharedentities', '', \html_writer::empty_tag('hr'));

        if (!sharedspace::is_shared_space()) {
            $str = get_string('include_shared_entities', 'tool_program');
            $mform->addElement('advcheckbox', self::INCLUDE_SHARED_ENTITIES, $str);
            $mform->setDefault(self::INCLUDE_SHARED_ENTITIES, 0);
            $mform->addHelpButton(self::INCLUDE_SHARED_ENTITIES, 'include_shared_entities', 'tool_program');
        } else {
            $mform->addElement('hidden', self::INCLUDE_SHARED_ENTITIES, 0);
            $mform->setType(self::INCLUDE_SHARED_ENTITIES, PARAM_INT);
        }

        $form->add_validation_callback([$this, 'validate_options_form']);
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

        // Settings.
        $settings = [];
        $settingsstr = get_string('export_content', 'tool_program');
        $settings[] = ['name' => $settingsstr, 'value' => !empty($data[self::EXPORT_CONTENT])];

        $coursebackupsstr = get_string('exportcoursecontent', 'tool_wp');
        $settings[] = ['name' => $coursebackupsstr, 'value' => !empty($data[self::EXPORT_COURSE_BACKUPS])];

        if (!empty($data[self::EXPORT_COURSE_BACKUPS])) {
            $str = get_string('includecoursecontent', 'tool_wp');
            $settings[] = ['name' => $str, 'value' => !empty($data[self::EXPORT_COURSE_BACKUPS_CONTENT])];
        }

        $usersstr = get_string('export_user_allocations', 'tool_program');
        $settings[] = ['name' => $usersstr, 'value' => !empty($data[self::EXPORT_USER_ALLOCATIONS])];

        $dynamicrulesstr = get_string('export_dynamic_rules', 'tool_program');
        $settings[] = ['name' => $dynamicrulesstr, 'value' => !empty($data[self::EXPORT_PROGRAM_DYNAMICRULES])];

        if (!sharedspace::is_shared_space()) {
            $sharedstr = get_string('include_shared_entities', 'tool_program');
            $settings[] = ['name' => $sharedstr, 'value' => !empty($data[self::INCLUDE_SHARED_ENTITIES])];
        }

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
        if ($entityname === 'tool_program') {
            return array_map(function(program $program) {
                $record = $program->to_record();
                if (!$this->get_export_setting(self::INCLUDE_SHARED_ENTITIES) && $this->program_is_shared($program)) {
                    $record->onlyallocations = true;
                }
                return $record;
            }, $this->get_programs_to_export());
        }
        return [];
    }

    /**
     * Is the given program shared program
     *
     * @param program $program
     * @return bool
     */
    protected function program_is_shared(program $program) {
        $exporttenant = $this->get_export_tenant_id() ?: tenancy::get_tenant_id();
        return $program->get('tenantid') != $exporttenant;
    }

    /**
     * Returns list of programs selected to export
     *
     * @return program[]
     */
    public function get_programs_to_export(): array {
        global $DB;

        $exporttenant = $this->get_export_tenant_id() ?: tenancy::get_tenant_id();
        if ($this->get_export_setting(self::INCLUDE_SHARED_ENTITIES) || $this->get_setting_export_user_allocations()) {
            [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid', 'shared=1',
                $exporttenant);
        } else {
            $sql = "tenantid = :tenantid";
            $params = ['tenantid' => $exporttenant];
        }

        switch ($this->get_export_setting(self::EXPORT_INSTANCES)) {
            case self::EXPORT_INSTANCES_ACTIVE:
                $params += ['archived' => 0];
                $programs = program::get_records_select($sql . ' AND archived = :archived', $params);
                break;
            case self::EXPORT_INSTANCES_ALL:
                $programs = program::get_records_select($sql, $params);
                break;
            case self::EXPORT_INSTANCES_SELECTED:
                $selected = $this->get_export_setting(self::EXPORT_SELECT_PROGRAMS);
                if (empty($selected)) {
                    $programs = [];
                } else {
                    [$where1, $params1] = $DB->get_in_or_equal($selected, SQL_PARAMS_NAMED);
                    $programs = program::get_records_select("$sql AND id $where1", $params + $params1);
                }
                break;
            default:
                throw new \coding_exception('Invalid export option');
        }

        if (!$this->get_export_setting(self::INCLUDE_SHARED_ENTITIES)) {
            $programs = array_filter($programs, function(program $program) {
                global $DB;
                [$sql, $params] = $this->get_program_allocations_sql($program);
                return !$this->program_is_shared($program) ||
                    $DB->record_exists_sql("SELECT 1 " . $sql, $params);
            });
        }

        return $programs;
    }

    /**
     * Perform some extra moodle validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validate_options_form(array $data, array $files): array {
        global $DB;
        $errors = [];

        if ($data[self::EXPORT_INSTANCES] === self::EXPORT_INSTANCES_SELECTED && empty($data[self::EXPORT_SELECT_PROGRAMS])) {
            $errors[self::EXPORT_SELECT_PROGRAMS] = get_string('selectatleastoneprogram', 'tool_program');
        }

        if ($data[self::INCLUDE_SHARED_ENTITIES] == 0 && $data[self::EXPORT_INSTANCES] === self::EXPORT_INSTANCES_SELECTED &&
            !empty($data[self::EXPORT_SELECT_PROGRAMS])) {
            // When user manually selected the programs, check that there is something to export for each of them.
            [$sql, $params] = $DB->get_in_or_equal($data[self::EXPORT_SELECT_PROGRAMS], SQL_PARAMS_NAMED, 'id');
            $params += ['tenantid' => $this->get_export_tenant_id() ?: tenancy::get_tenant_id()];
            $programs = program::get_records_select("id $sql AND tenantid <> :tenantid", $params);
            $programs = array_filter($programs, function(program $program) use ($data) {
                global $DB;
                [$sql, $params] = $this->get_program_allocations_sql($program);
                return $data[self::EXPORT_USER_ALLOCATIONS] == 0 || !$DB->record_exists_sql('SELECT 1 '.$sql, $params);
            });
            if ($programs) {
                // We found the shared programs without any user allocations.
                $names = array_map(function($program) {
                    return format_string($program->get('fullname'), true, ['escape' => false]);
                }, $programs);
                $errors[self::EXPORT_SELECT_PROGRAMS] = get_string('errornothingtoexportforprograms', 'tool_program',
                    join(', ', $names));
            }
        }

        return $errors;
    }

    /**
     * Returns a list with all programs (active and archived)
     *
     * @return array
     */
    private function get_programs_list(): array {
        $programslist = [];

        [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid', 'shared=1',
            $this->get_export_tenant_id());
        $programs = program::get_records_select($sql, $params);

        foreach ($programs as $program) {
            $programslist[$program->get('id')] = format_string($program->get('fullname'), true,
                ['context' => $program->get_context()->id]);
        }

        return $programslist;
    }

    /**
     * Are we exporting program allocations?
     *
     * @return bool
     */
    protected function get_setting_export_user_allocations(): bool {
        return $this->get_export_setting(self::EXPORT_USER_ALLOCATIONS) && permission::has_allocateuser_capability();
    }

    /**
     * Export one program
     *
     * @param program $program
     */
    protected function export_program(program $program): void {
        global $DB;

        // Get all sets for this program.
        $programsets = program_set::get_records(['programid' => $program->get('id')]);

        $setsids = [];
        foreach ($programsets as $set) {
            $setsids[] = $set->get('id');
        }
        [$insql, $inparams] = $DB->get_in_or_equal($setsids);

        // Get all program courses for this program.
        $programcourses = program_course::get_records_select("setid {$insql}", $inparams);

        $programrecord = (array) $program->to_record();

        // Copy program tags.
        $tags = \core_tag_tag::get_item_tags_array('tool_program', 'tool_program', $program->get('id'));
        if (!empty($tags)) {
            $programrecord['program_tags'] = implode(',', $tags);
        }

        $export = $this->prepare_data_for_workplace_export(program::TABLE, $programrecord)
            ->add_nested_entities(program_set::TABLE, helper::persistents_to_array($programsets),
                ['timecreated', 'timemodified', 'usermodified'])
            ->add_nested_entities(program_course::TABLE, helper::persistents_to_array($programcourses),
                ['timecreated', 'timemodified', 'usermodified'])
            ->add_mappings_for_nested_entities(program_set::TABLE, 'parent', program_set::TABLE)
            ->add_mappings_for_nested_entities(program_course::TABLE, 'courseid', 'course')
            ->add_mappings_for_nested_entities(program_course::TABLE, 'setid', program_set::TABLE)
            ->exclude_fields(['timecreated', 'timemodified'])
            ->add_mappings('tenantid', 'tool_tenant')
            ->add_files_from_text($program->get('description'), $program->get_context(), 'tool_program',
                'program_description', $program->get('id'))
            ->add_area_files($program->get_context(), 'tool_program', 'program_image', $program->get('id'));

        $export->export();

        // Export program custom fields.
        $this->process_chained_entities('customfield_data', [], [
            'component' => 'tool_program',
            'area' => 'program',
            'instanceid' => $program->get('id')
        ]);

        // Export program dynamic rules.
        if ($this->get_export_setting(self::EXPORT_PROGRAM_DYNAMICRULES)
            && $this->can_export_chained_entity('tool_dynamicrule')) {
            $this->process_chained_entities('tool_dynamicrule', [], [
                rules::EXPORT_INSTANCES => rules::EXPORT_INSTANCES_COMPONENT,
                rules::EXPORT_SELECT_COMPONENT => 'tool_program',
                rules::EXPORT_SELECT_COMPONENT_AREA => 'program',
                rules::EXPORT_SELECT_COMPONENT_ITEMID => $program->get('id'),
            ]);
        }

    }

    /**
     * Returns SQL for direct allocations to programs (starting with FROM)
     *
     * @param program $program
     * @return array
     */
    protected function get_program_allocations_sql(program $program) {
        $usertenantsql = tenancy::get_users_subquery(false, true, 'u.id',
            $this->get_export_tenant_id());

        $sql1 = "FROM {tool_program_users} pu
                        JOIN {user} u ON u.id = pu.userid
                        JOIN {tool_program} p ON p.id = pu.programid
                        WHERE {$usertenantsql} pu.programid = :programid AND pu.certificationid = 0
                    ";
        return [$sql1, ['programid' => $program->get('id')]];
    }

    /**
     * Export user allocations for a given program
     *
     * Export only direct allocations. Allocations through certifications can only be created if the certification
     * is imported together with the program and tool_certification will be responsible for creating them
     * dynamically on import.
     *
     * @param program $program
     */
    protected function export_program_allocations(program $program) {
        global $DB;

        // We add user/program fullname properties so they can be referenced in the importer.
        $viewfullnames = has_capability('moodle/site:viewfullnames', \context_system::instance());
        [$sqlfn, $paramsfn] = fields::get_sql_fullname('u', $viewfullnames);

        [$sql, $params] = $this->get_program_allocations_sql($program);

        $sql1 = "SELECT pu.*, $sqlfn AS userfullname, p.fullname AS programfullname $sql";
        $programusers = $DB->get_records_sql($sql1, $params + $paramsfn);
        foreach ($programusers as $programuser) {
            $this->prepare_data_for_workplace_export(program_user::TABLE, (array) $programuser)
                ->exclude_fields(['timecreated', 'timemodified', 'certificationid'])
                ->add_mappings('userid', 'user')
                ->add_mappings('programid', program::TABLE)
                ->export();
        }
    }

    /**
     * Performs the export
     *
     * @return void
     */
    public function perform_export(): void {
        global $DB;

        // Check whether user has selected to export programs.
        if (!empty($this->get_export_setting(self::EXPORT_INSTANCES))) {

            $programs = $this->get_programs_to_export();

            // Export chained courses, if applicable.
            if ($this->get_export_setting(self::EXPORT_COURSE_BACKUPS) && $this->can_export_chained_entity('course')) {
                $coursestoexport = [];
                foreach ($programs as $program) {
                    if (!$this->get_export_setting(self::INCLUDE_SHARED_ENTITIES) && $this->program_is_shared($program)) {
                        continue;
                    }
                    $coursesids = $program->get_courses_ids();
                    foreach ($coursesids as $courseid) {
                        // TODO not not export shared courses if self::INCLUDE_SHARED_ENTITIES is not selected.
                        $coursestoexport[$courseid] = true;
                    }
                }
                $this->process_chained_entities('course', array_keys($coursestoexport), [
                    self::EXPORT_COURSE_BACKUPS => 1,
                    self::EXPORT_COURSE_BACKUPS_CONTENT => $this->get_export_setting(self::EXPORT_COURSE_BACKUPS_CONTENT),
                ]);
            }

            foreach ($programs as $program) {

                if ($this->get_export_setting(self::INCLUDE_SHARED_ENTITIES) || !$this->program_is_shared($program)) {
                    $this->export_program($program);
                }

                // Export program user allocations, if applicable.
                if ($this->get_setting_export_user_allocations()) {
                    $this->export_program_allocations($program);
                }
            }
        }
    }
}
