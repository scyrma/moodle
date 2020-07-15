<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Programs exporter
 *
 * @package   tool_program
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\tool_wp\exporter;

use tool_program\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_course;
use tool_program\persistent\program_set;
use tool_program\persistent\program_user;
use tool_wp\exporter_base;
use tool_wp\local\exportimport\forms\export_settings_form;
use tool_wp\local\exportimport\helper;

defined('MOODLE_INTERNAL') || die;

/**
 * Exporter class
 *
 * @package   tool_program
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class programs extends exporter_base {

    /** @var string Element for selecting what to export. */
    const EXPORT_INSTANCES = 'export_instances';
    /** @var int Element for export all programs. */
    const EXPORT_INSTANCES_ALL = 'all';
    /** @var int Element for export only active programs. */
    const EXPORT_INSTANCES_ACTIVE = 'active';
    /** @var int Element for export manually selected programs. */
    const EXPORT_INSTANCES_SELECTED = 'selected';
    /** @var string Element for selecting what to export. */
    const EXPORT_SELECT_PROGRAMS = 'select_programs';
    /** @var string Element for selecting if program user allocations have to be exported. */
    const EXPORT_USER_ALLOCATIONS = 'export_user_allocations';
    /** @var string Element for selecting if courses have to be exported. */
    const EXPORT_COURSE_BACKUPS = 'export_courses';
    /** @var string Element for selecting if program settings have to be exported. */
    const EXPORT_CONTENT = 'export_content';
    /** @var string Element for selecting if program dynamic rules have to be exported. */
    const EXPORT_PROGRAM_DYNAMICRULES = 'export_dynamic_rules';

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
                    self::EXPORT_CONTENT => 1,
                    self::EXPORT_USER_ALLOCATIONS => 0,
                    self::EXPORT_COURSE_BACKUPS => 1,
                    self::EXPORT_PROGRAM_DYNAMICRULES => 1,
                ];
                return array_intersect_key($settings, $defaults) + $defaults +
                [
                    self::EXPORT_INSTANCES => self::EXPORT_INSTANCES_SELECTED,
                    self::EXPORT_SELECT_PROGRAMS => $ids,
                ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                return format_string($record['fullname']);
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

        $coursebackupsstr = get_string('export_courses', 'tool_program');
        $mform->addElement('advcheckbox', self::EXPORT_COURSE_BACKUPS, $coursebackupsstr);
        $mform->setDefault(self::EXPORT_COURSE_BACKUPS, 1);
        $mform->addHelpButton(self::EXPORT_COURSE_BACKUPS, 'export_courses', 'tool_program');
        if (!$this->can_export_chained_entity('course')) {
            $form->freeze_at(self::EXPORT_COURSE_BACKUPS, 0);
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

        $coursebackupsstr = get_string('export_courses', 'tool_program');
        $settings[] = ['name' => $coursebackupsstr, 'value' => !empty($data[self::EXPORT_COURSE_BACKUPS])];

        $usersstr = get_string('export_user_allocations', 'tool_program');
        $settings[] = ['name' => $usersstr, 'value' => !empty($data[self::EXPORT_USER_ALLOCATIONS])];

        $dynamicrulesstr = get_string('export_dynamic_rules', 'tool_program');
        $settings[] = ['name' => $dynamicrulesstr, 'value' => !empty($data[self::EXPORT_PROGRAM_DYNAMICRULES])];

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
                return $program->to_record();
            }, $this->get_programs_to_export());
        }
        return [];
    }

    /**
     * Returns list of programs selected to export
     *
     * @return program[]
     */
    public function get_programs_to_export(): array {
        global $DB;

        $params = ['tenantid' => $this->get_export_tenant_id()];

        switch ($this->get_export_setting(self::EXPORT_INSTANCES)) {
            case self::EXPORT_INSTANCES_ACTIVE:
                $params += ['archived' => 0];
                $programs = program::get_records($params);
                break;
            case self::EXPORT_INSTANCES_ALL:
                $programs = program::get_records($params);
                break;
            case self::EXPORT_INSTANCES_SELECTED:
                $selected = $this->get_export_setting(self::EXPORT_SELECT_PROGRAMS);
                [$where1, $params1] = $DB->get_in_or_equal($selected, SQL_PARAMS_NAMED);
                $programs = program::get_records_select("tenantid = :tenantid AND id $where1", $params + $params1);
                break;
            default:
                throw new \coding_exception('Invalid export option');
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
        $errors = [];

        if ($data[self::EXPORT_INSTANCES] === self::EXPORT_INSTANCES_SELECTED && empty($data[self::EXPORT_SELECT_PROGRAMS])) {
            $errors[self::EXPORT_SELECT_PROGRAMS] = get_string('selectatleastoneprogram', 'tool_program');
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

        $programs = program::get_records(['tenantid' => $this->get_export_tenant_id()], 'fullname');
        foreach ($programs as $program) {
            $programslist[$program->get('id')] = $program->get('fullname');
        }

        return $programslist;
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
                    $coursesids = $program->get_courses_ids();
                    foreach ($coursesids as $courseid) {
                        $coursestoexport[$courseid] = true;
                    }
                }
                $this->process_chained_entities('course', array_keys($coursestoexport));
            }

            foreach ($programs as $program) {

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
                        'component' => 'tool_program',
                        'area' => 'program',
                        'itemid' => $program->get('id')
                    ]);
                }

                // Export program user allocations, if applicable.
                if ($this->get_export_setting(self::EXPORT_USER_ALLOCATIONS) && permission::has_allocateuser_capability()) {
                    global $DB;
                    // Export only direct allocations. Allocations through certifications can only be created if the certification
                    // is imported together with the program and tool_certification will be responsible for creating them
                    // dynamically on import.
                    $viewfullnames = has_capability('moodle/site:viewfullnames', \context_system::instance());
                    [$sql, $params] = \tool_reportbuilder\db::sql_fullname('u', $viewfullnames);
                    $sql1 = "
                        SELECT pu.*, $sql AS userfullname
                        FROM {tool_program_users} pu
                        JOIN {user} u ON u.id = pu.userid
                        WHERE pu.programid = :programid AND pu.certificationid = 0
                    ";
                    $params['programid'] = $program->get('id');
                    $programusers = $DB->get_records_sql($sql1, $params);
                    foreach ($programusers as $programuser) {
                        $this->prepare_data_for_workplace_export(program_user::TABLE, (array) $programuser)
                            ->exclude_fields(['timecreated', 'timemodified', 'certificationid'])
                            ->add_mappings('userid', 'user')
                            ->add_mappings('programid', program::TABLE)
                            ->export();
                    }
                }
            }
        }
    }
}
