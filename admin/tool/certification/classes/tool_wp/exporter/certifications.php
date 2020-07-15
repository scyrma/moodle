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
 * Certifications exporter
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\tool_wp\exporter;

use tool_certification\certification;
use tool_certification\certification_completion;
use tool_certification\certification_user;
use tool_certification\permission;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use tool_program\tool_wp\exporter\programs;
use tool_wp\exporter_base;
use tool_wp\local\exportimport\forms\export_settings_form;

defined('MOODLE_INTERNAL') || die;

/**
 * Exporter class
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certifications extends exporter_base {

    /** @var string Element for selecting what to export. */
    const EXPORT_INSTANCES = 'export_instances';
    /** @var int Element for export all programs. */
    const EXPORT_INSTANCES_ALL = 'all';
    /** @var int Element for export only active programs. */
    const EXPORT_INSTANCES_ACTIVE = 'active';
    /** @var int Element for export manually selected programs. */
    const EXPORT_INSTANCES_SELECTED = 'selected';
    /** @var string Element for selecting if certification settings have to be exported. */
    const EXPORT_CONTENT = 'export_content';
    /** @var string Element for selecting what to export. */
    const EXPORT_SELECT_CERTIFICATIONS = 'select_certifications';
    /** @var string Element for exporting programs */
    const EXPORT_PROGRAMS = 'export_programs';
    /** @var string Element for selecting if certification user allocations have to be exported. */
    const EXPORT_USER_ALLOCATIONS = 'export_user_allocations';
    /** @var string Element for selecting if courses have to be exported. */
    const EXPORT_COURSE_BACKUPS = 'export_course_backups';
    /** @var string Element for selecting if certification dynamic rules have to be exported. */
    const EXPORT_DYNAMICRULES = 'export_dynamic_rules';

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
     * Exporter description to show in the list of available exporters
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('exportcertificationsdescription', 'tool_certification');
    }

    /**
     * Allows to mark exporter as not available, checks capabilities and entry point
     *
     * @return bool
     */
    public function is_available(): bool {
        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint()) && permission::can_create();
    }

    /**
     * Initialise the class, register entities that can be exported from other places
     */
    protected function initialise() {
        $this->register_entity(certification::TABLE, [
            self::ENTITY_INDIVIDUALEXPORT => function(array $ids, array $settings) {
                // What parameters to use when exporting individual entity as part of some other export.
                // May be called from tenants export.
                $defaults = [
                    self::EXPORT_CONTENT => 1,
                    self::EXPORT_PROGRAMS => 1,
                    self::EXPORT_USER_ALLOCATIONS => 0,
                    self::EXPORT_COURSE_BACKUPS => 1,
                    self::EXPORT_DYNAMICRULES => 1,
                ];
                return array_intersect_key($settings, $defaults) + $defaults +
                    [
                        self::EXPORT_INSTANCES => self::EXPORT_INSTANCES_SELECTED,
                        self::EXPORT_SELECT_CERTIFICATIONS => $ids,
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
        $mform->addElement('header', 'content', get_string('content', 'tool_certification'));
        $mform->setExpanded('content');

        $mform->addElement('advcheckbox', self::EXPORT_CONTENT, get_string('export_content', 'tool_certification'));
        $mform->setDefault(self::EXPORT_CONTENT, 1);
        $mform->addHelpButton(self::EXPORT_CONTENT, 'export_content', 'tool_certification');
        $form->freeze_at(self::EXPORT_CONTENT, 1);

        $programsstr = get_string('export_programs', 'tool_certification');
        $canexportprograms = $this->can_export_chained_entity(program::TABLE);
        $mform->addElement('advcheckbox', self::EXPORT_PROGRAMS,
            $programsstr . ($canexportprograms ? '' : get_string('noavailablepostfix', 'tool_wp')));
        $mform->setDefault(self::EXPORT_PROGRAMS, 1);
        $mform->addHelpButton(self::EXPORT_PROGRAMS, 'export_programs', 'tool_certification');
        if (!$canexportprograms) {
            $form->freeze_at(self::EXPORT_PROGRAMS, 0);
        }

        $coursebackupsstr = get_string('export_course_backups', 'tool_certification');
        $mform->addElement('advcheckbox', self::EXPORT_COURSE_BACKUPS, $coursebackupsstr);
        $mform->setDefault(self::EXPORT_COURSE_BACKUPS, 1);
        $mform->addHelpButton(self::EXPORT_COURSE_BACKUPS, 'export_course_backups', 'tool_certification');
        $mform->hideIf(self::EXPORT_COURSE_BACKUPS, self::EXPORT_PROGRAMS, 'noteq', 1);
        if (!$canexportprograms || !$this->can_export_chained_entity('course')) {
            $form->freeze_at(self::EXPORT_COURSE_BACKUPS, 0);
        }

        $usersstr = get_string('export_user_allocations', 'tool_certification');
        $mform->addElement('advcheckbox', self::EXPORT_USER_ALLOCATIONS, $usersstr);
        $mform->setDefault(self::EXPORT_USER_ALLOCATIONS, 0);
        $mform->addHelpButton(self::EXPORT_USER_ALLOCATIONS, 'export_user_allocations', 'tool_certification');
        if (!permission::has_allocateuser_capability()) {
            $form->freeze_at(self::EXPORT_USER_ALLOCATIONS, 0);
        }

        $dynamicrulesstr = get_string('export_dynamic_rules', 'tool_certification');
        $mform->addElement('advcheckbox', self::EXPORT_DYNAMICRULES, $dynamicrulesstr);
        $mform->setDefault(self::EXPORT_DYNAMICRULES, 1);
        $mform->addHelpButton(self::EXPORT_DYNAMICRULES, 'export_dynamic_rules', 'tool_certification');
        if (!$this->can_export_chained_entity('tool_dynamicrule')) {
            $form->freeze_at(self::EXPORT_DYNAMICRULES, 0);
        }

        $certinstancesstr = get_string('instances', 'tool_wp');
        $mform->addElement('header', 'instances', $certinstancesstr);
        $mform->setExpanded('instances');

        $selectactive = get_string('selectallactivecertifications', 'tool_certification');
        $selectall = get_string('selectactiveandarchivedcertifications', 'tool_certification');
        $selectmanually = get_string('selectmanually', 'tool_certification');
        $mform->addElement('radio', self::EXPORT_INSTANCES, null, $selectactive, self::EXPORT_INSTANCES_ACTIVE);
        $mform->addElement('radio', self::EXPORT_INSTANCES, null, $selectall, self::EXPORT_INSTANCES_ALL);
        $mform->addElement('radio', self::EXPORT_INSTANCES, null, $selectmanually, self::EXPORT_INSTANCES_SELECTED);
        $mform->setType(self::EXPORT_INSTANCES, PARAM_ALPHANUM);
        $mform->setDefault(self::EXPORT_INSTANCES, self::EXPORT_INSTANCES_ACTIVE);

        // Certifications picker.
        $mform->addElement('autocomplete', self::EXPORT_SELECT_CERTIFICATIONS,
            get_string('certifications', 'tool_certification'),
            $this->get_certifications_list(), ['multiple' => true])->setHiddenLabel(true);
        $mform->setType(self::EXPORT_SELECT_CERTIFICATIONS, PARAM_INT);
        $mform->hideIf(self::EXPORT_SELECT_CERTIFICATIONS, self::EXPORT_INSTANCES, 'noteq', self::EXPORT_INSTANCES_SELECTED);

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

        $settings = [];
        $settingsstr = get_string('export_content', 'tool_certification');
        $settings[] = ['name' => $settingsstr, 'value' => !empty($data[self::EXPORT_CONTENT])];

        $programsstr = get_string('export_programs', 'tool_certification');
        $settings[] = ['name' => $programsstr, 'value' => !empty($data[self::EXPORT_PROGRAMS])];

        $coursebackupsstr = get_string('export_course_backups', 'tool_certification');
        $canexportcoursebackups = (!empty($data[self::EXPORT_COURSE_BACKUPS]) && !empty($data[self::EXPORT_PROGRAMS]));
        $settings[] = ['name' => $coursebackupsstr, 'value' => $canexportcoursebackups];

        $usersstr = get_string('export_user_allocations', 'tool_certification');
        $settings[] = ['name' => $usersstr, 'value' => !empty($data[self::EXPORT_USER_ALLOCATIONS])];

        $dynamicrulesstr = get_string('export_dynamic_rules', 'tool_certification');
        $settings[] = ['name' => $dynamicrulesstr, 'value' => !empty($data[self::EXPORT_DYNAMICRULES])];

        return $OUTPUT->render_from_template(
            'tool_wp/exportimport_summary',
            ['settings' => $settings]
        );
    }

    /**
     * Returns the list of entities that will be exported
     *
     * Implement also {@link get_summary_for_review_step()}
     *
     * @param string $entityname
     * @return array array where each element is array that can be passed through self::ENTITY_INSTANCENAME_FOR_REVIEW
     *     callback
     */
    public function get_instances_for_review_step(string $entityname): array {
        if ($entityname === certification::TABLE) {
            return array_map(function(certification $certification) {
                return $certification->to_record();
            }, $this->get_certifications_to_export($this->get_export_settings()));
        }
        return [];
    }

    /**
     * Returns a list with all certifications (active and archived)
     *
     * @return array
     * @throws \dml_exception
     */
    private function get_certifications_list(): array {
        global $DB;

        $certifications = $DB->get_records('tool_certification', ['tenantid' => $this->get_export_tenant_id()]);
        $certificationslist = [];
        foreach ($certifications as $certification) {
            $certificationslist[$certification->id] = $certification->fullname;
        }
        return $certificationslist;
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

        if ($data[self::EXPORT_INSTANCES] === self::EXPORT_INSTANCES_SELECTED && empty($data[self::EXPORT_SELECT_CERTIFICATIONS])) {
            $errors[self::EXPORT_SELECT_CERTIFICATIONS] = get_string('selectatleastonecertification', 'tool_certification');
        }

        return $errors;
    }

    /**
     * Returns list of certifications selected to export
     *
     * @param array $data
     * @return array
     */
    public function get_certifications_to_export(array $data): array {
        global $DB;

        $params = ['tenantid' => $this->get_export_tenant_id()];

        switch ($data[self::EXPORT_INSTANCES]) {
            case self::EXPORT_INSTANCES_ACTIVE:
                $params += ['archived' => 0];
                $certifications = certification::get_records($params);
                break;
            case self::EXPORT_INSTANCES_ALL:
                $certifications = certification::get_records($params);
                break;
            case self::EXPORT_INSTANCES_SELECTED:
                [$where1, $params1] = $DB->get_in_or_equal($data[self::EXPORT_SELECT_CERTIFICATIONS], SQL_PARAMS_NAMED);
                $certifications = certification::get_records_select("tenantid = :tenantid AND id $where1", $params + $params1);
                break;
            default:
                throw new \coding_exception('Invalid export option');
        }

        return $certifications;
    }

    /**
     * Performs the export
     *
     * @return void
     */
    public function perform_export(): void {
        global $DB;
        $data = $this->get_export_settings();

        // Check whether user has selected to export programs.
        if (!empty($data[self::EXPORT_INSTANCES])) {

            $certifications = $this->get_certifications_to_export($data);

            // Export chained programs, if applicable.
            $programstoexport = [];
            if ($this->get_export_setting(self::EXPORT_PROGRAMS) && $this->can_export_chained_entity(program::TABLE)) {
                foreach ($certifications as $certification) {
                    $programstoexport[$certification->get('program')] = true;
                    if ($pid = $certification->get('recertificationprogram')) {
                        $programstoexport[$pid] = true;
                    }
                }
                $settings = [];
                if ($this->get_export_setting(self::EXPORT_COURSE_BACKUPS) && $this->can_export_chained_entity('course')) {
                    $settings[programs::EXPORT_COURSE_BACKUPS] = 1;
                } else {
                    $settings[programs::EXPORT_COURSE_BACKUPS] = 0;
                }
                if ($this->get_export_setting(self::EXPORT_DYNAMICRULES) &&
                    $this->can_export_chained_entity('tool_dynamicrule')) {
                    $settings[programs::EXPORT_PROGRAM_DYNAMICRULES] = 1;
                } else {
                    $settings[programs::EXPORT_PROGRAM_DYNAMICRULES] = 0;
                }
                $this->process_chained_entities(program::TABLE, array_keys($programstoexport), $settings);
            }

            // Export certifications.
            foreach ($certifications as $certification) {
                $certificationrecord = (array) $certification->to_record();

                // Copy certification tags.
                $tags = \core_tag_tag::get_item_tags_array('tool_certification', 'tool_certification', $certification->get('id'));
                if (!empty($tags)) {
                    $certificationrecord['certification_tags'] = implode(',', $tags);
                }

                $this->prepare_data_for_workplace_export(certification::TABLE, $certificationrecord)
                    ->exclude_fields(['timecreated', 'timemodified'])
                    ->add_mappings('tenantid', 'tool_tenant')
                    ->add_mappings('program', program::TABLE)
                    ->add_mappings('recertificationprogram', program::TABLE)
                    ->export();

                // Export certification custom fields.
                $this->process_chained_entities('customfield_data', [], [
                    'component' => 'tool_certification',
                    'area' => 'certification',
                    'instanceid' => $certification->get('id')
                ]);

                // Export certification dynamic rules.
                if ($this->get_export_setting(self::EXPORT_DYNAMICRULES)
                        && $this->can_export_chained_entity('tool_dynamicrule')) {
                    $this->process_chained_entities('tool_dynamicrule', [], [
                        'component' => 'tool_certification',
                        'area' => 'certification',
                        'itemid' => $certification->get('id')
                    ]);
                }

                // Check if we need to export certification user allocations.
                if ($this->get_export_setting(self::EXPORT_USER_ALLOCATIONS) && permission::has_allocateuser_capability()) {

                    $viewfullnames = has_capability('moodle/site:viewfullnames', \context_system::instance());
                    [$sql, $params] = \tool_reportbuilder\db::sql_fullname('u', $viewfullnames);
                    $sql1 = "
                        SELECT cu.*, $sql AS userfullname
                        FROM {tool_certification_users} cu
                        JOIN {user} u ON u.id = cu.userid
                        WHERE cu.certificationid = :certificationid
                    ";
                    $params['certificationid'] = $certification->get('id');
                    $certificationusers = $DB->get_records_sql($sql1, $params);

                    // Replace null with a zero to avoid problems with exporter mapping. Will revert it in the importer.
                    $recordstoexport = array_map(function($certificationuser) {
                        $record = (array)$certificationuser;
                        $record['currentprogramid'] = ($record['currentprogramid']) ?: 0;
                        return $record;
                    }, $certificationusers);

                    foreach ($recordstoexport as $certificationuserrecord) {
                        $exportallocations = $this->prepare_data_for_workplace_export(certification_user::TABLE,
                            $certificationuserrecord)
                            ->exclude_fields(['timecreated', 'timemodified'])
                            ->add_mappings('userid', 'user')
                            ->add_mappings('certificationid', certification::TABLE)
                            ->add_mappings('currentprogramid', program::TABLE);

                        // Export user allocation completions.
                        $usercompletions = certification_completion::get_records(['certificationid' => $certification->get('id'),
                            'userid' => $certificationuserrecord['userid']]);
                        $exportallocations->add_nested_entities(certification_completion::TABLE,
                            \tool_wp\local\exportimport\helper::persistents_to_array($usercompletions),
                            ['timecreated', 'timemodified'])
                            ->add_mappings_for_nested_entities(certification_completion::TABLE, 'userid', 'user')
                            ->add_mappings_for_nested_entities(certification_completion::TABLE, 'certificationid',
                                certification::TABLE)
                            ->add_mappings_for_nested_entities(certification_completion::TABLE, 'programid', program::TABLE);

                        // Export program allocations.
                        $programusers = program_user::get_records(['certificationid' => $certification->get('id'),
                            'userid' => $certificationuserrecord['userid']]);
                        $exportallocations->add_nested_entities(program_user::TABLE,
                            \tool_wp\local\exportimport\helper::persistents_to_array($programusers),
                            ['timecreated', 'timemodified'])
                            ->add_mappings_for_nested_entities(program_user::TABLE, 'userid', 'user')
                            ->add_mappings_for_nested_entities(program_user::TABLE, 'certificationid', certification::TABLE)
                            ->add_mappings_for_nested_entities(program_user::TABLE, 'programid', program::TABLE);

                        $exportallocations->export();
                    }
                }
            }
        }
    }
}
