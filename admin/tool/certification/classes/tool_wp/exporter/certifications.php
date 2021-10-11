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
 * Certifications exporter
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\tool_wp\exporter;

use tool_certification\certification;
use tool_certification\certification_completion;
use tool_certification\certification_user;
use tool_certification\permission;
use tool_dynamicrule\tool_wp\exporter\rules;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use tool_program\tool_wp\exporter\programs;
use tool_tenant\hierarchy;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use tool_wp\exporter_base;
use tool_wp\local\exportimport\forms\export_settings_form;

defined('MOODLE_INTERNAL') || die;

/**
 * Exporter class
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certifications extends exporter_base {

    /** @var string Element for selecting what to export. */
    const EXPORT_INSTANCES = 'export_instances';
    /** @var string Element for export all programs. */
    const EXPORT_INSTANCES_ALL = 'all';
    /** @var string Element for export only active programs. */
    const EXPORT_INSTANCES_ACTIVE = 'active';
    /** @var string Element for export manually selected programs. */
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
    /** @var string Element for selecting if courses content have to be exported. */
    const EXPORT_COURSE_BACKUPS_CONTENT = 'export_courses_content';
    /** @var string Element for selecting if certification dynamic rules have to be exported. */
    const EXPORT_DYNAMICRULES = 'export_dynamic_rules';
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
     * Exporter icon url
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('menu/certifications', 'theme')->out(false);
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
                    self::EXPORT_INSTANCES => self::EXPORT_INSTANCES_SELECTED,
                    self::EXPORT_CONTENT => 1,
                    self::EXPORT_PROGRAMS => 1,
                    self::EXPORT_USER_ALLOCATIONS => 0,
                    self::EXPORT_COURSE_BACKUPS => 1,
                    self::EXPORT_COURSE_BACKUPS_CONTENT => 0,
                    self::EXPORT_DYNAMICRULES => 1,
                    self::INCLUDE_SHARED_ENTITIES => 0,
                ];
                return array_intersect_key($settings, $defaults) + $defaults + [
                    self::EXPORT_SELECT_CERTIFICATIONS => $ids,
                ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                $rv = format_string($record['fullname']);
                $rv .= (!empty($record['onlyallocations'])) ?
                    ' ' . get_string('exportonlyallocationspostfix', 'tool_certification') : '';
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
        $mform->addElement('header', 'content', get_string('content', 'tool_certification'));
        $mform->setExpanded('content');

        $mform->addElement('advcheckbox', self::EXPORT_CONTENT, get_string('export_content', 'tool_certification'));
        $mform->setDefault(self::EXPORT_CONTENT, 1);
        $mform->addHelpButton(self::EXPORT_CONTENT, 'export_content', 'tool_certification');
        $form->freeze_at(self::EXPORT_CONTENT, 1);

        $programsstr = get_string('export_programs', 'tool_certification');
        $canexportprograms = $this->can_export_chained_entity(program::TABLE);
        $mform->addElement('advcheckbox', self::EXPORT_PROGRAMS, $programsstr);
        $mform->setDefault(self::EXPORT_PROGRAMS, 1);
        $mform->addHelpButton(self::EXPORT_PROGRAMS, 'export_programs', 'tool_certification');
        if (!$canexportprograms) {
            $form->freeze_at(self::EXPORT_PROGRAMS, 0);
        }

        $coursebackupsstr = get_string('exportcoursecontent', 'tool_wp');
        $mform->addElement('advcheckbox', self::EXPORT_COURSE_BACKUPS, $coursebackupsstr);
        $mform->setDefault(self::EXPORT_COURSE_BACKUPS, 1);
        $mform->addHelpButton(self::EXPORT_COURSE_BACKUPS, 'exportcoursecontent', 'tool_wp');
        $mform->hideIf(self::EXPORT_COURSE_BACKUPS, self::EXPORT_PROGRAMS, 'noteq', 1);
        if (!$canexportprograms || !$this->can_export_chained_entity('course')) {
            $form->freeze_at(self::EXPORT_COURSE_BACKUPS, 0);
        }

        $coursebackupsstr = get_string('includecoursecontent', 'tool_wp');
        $mform->addElement('advcheckbox', self::EXPORT_COURSE_BACKUPS_CONTENT, $coursebackupsstr);
        $mform->setDefault(self::EXPORT_COURSE_BACKUPS_CONTENT, 0);
        $mform->addHelpButton(self::EXPORT_COURSE_BACKUPS_CONTENT, 'includecoursecontent', 'tool_wp');
        if (!$canexportprograms || !$this->can_export_chained_entity('course') ||
                !\tool_wp\permission::can_export_course_content()) {
            $form->freeze_at(self::EXPORT_COURSE_BACKUPS_CONTENT, 0);
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

        $mform->addElement('static', 'sharedentities', '', \html_writer::empty_tag('hr'));

        if (!sharedspace::is_shared_space()) {
            $str = get_string('include_shared_entities', 'tool_certification');
            $mform->addElement('advcheckbox', self::INCLUDE_SHARED_ENTITIES, $str);
            $mform->setDefault(self::INCLUDE_SHARED_ENTITIES, 0);
            $mform->addHelpButton(self::INCLUDE_SHARED_ENTITIES, 'include_shared_entities', 'tool_certification');
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

        $settings = [];
        $settingsstr = get_string('export_content', 'tool_certification');
        $settings[] = ['name' => $settingsstr, 'value' => !empty($data[self::EXPORT_CONTENT])];

        $programsstr = get_string('export_programs', 'tool_certification');
        $settings[] = ['name' => $programsstr, 'value' => !empty($data[self::EXPORT_PROGRAMS])];

        $coursebackupsstr = get_string('exportcoursecontent', 'tool_wp');
        $canexportcoursebackups = (!empty($data[self::EXPORT_COURSE_BACKUPS]) && !empty($data[self::EXPORT_PROGRAMS]));
        $settings[] = ['name' => $coursebackupsstr, 'value' => $canexportcoursebackups];

        if ($canexportcoursebackups) {
            $str = get_string('includecoursecontent', 'tool_wp');
            $settings[] = ['name' => $str, 'value' => !empty($data[self::EXPORT_COURSE_BACKUPS_CONTENT])];
        }

        $usersstr = get_string('export_user_allocations', 'tool_certification');
        $settings[] = ['name' => $usersstr, 'value' => !empty($data[self::EXPORT_USER_ALLOCATIONS])];

        $dynamicrulesstr = get_string('export_dynamic_rules', 'tool_certification');
        $settings[] = ['name' => $dynamicrulesstr, 'value' => !empty($data[self::EXPORT_DYNAMICRULES])];

        if (!sharedspace::is_shared_space()) {
            $sharedstr = get_string('include_shared_entities', 'tool_certification');
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
     * Implement also {$see get_summary_for_review_step()}
     *
     * @param string $entityname
     * @return array array where each element is array that can be passed through self::ENTITY_INSTANCENAME_FOR_REVIEW
     *     callback
     */
    public function get_instances_for_review_step(string $entityname): array {
        if ($entityname === certification::TABLE) {
            return array_map(function(certification $certification) {
                $record = $certification->to_record();
                if (!$this->get_export_setting(self::INCLUDE_SHARED_ENTITIES) && $this->certification_is_shared($certification)) {
                    $record->onlyallocations = true;
                }
                return $record;
            }, $this->get_certifications_to_export());
        }
        return [];
    }

    /**
     * Is the given certification is shared certification.
     *
     * @param certification $certification
     * @return bool
     */
    protected function certification_is_shared(certification $certification) {
        $exporttenant = $this->get_export_tenant_id() ?: tenancy::get_tenant_id();
        return $certification->get('tenantid') != $exporttenant;
    }

    /**
     * Returns a list with all certifications (active and archived)
     *
     * @return array
     */
    private function get_certifications_list(): array {
        $certificationslist = [];

        // Tenant condition.
        [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid', 'shared=1',
            $this->get_export_tenant_id());
        $certifications = certification::get_records_select($sql, $params);

        foreach ($certifications as $certification) {
            $certificationslist[$certification->get('id')] = format_string($certification->get('fullname'), true,
                ['context' => $certification->get_context()->id]);
        }

        return $certificationslist;
    }

    /**
     * Are we exporting certification allocations?
     *
     * @return bool
     */
    protected function get_setting_export_user_allocations(): bool {
        return $this->get_export_setting(self::EXPORT_USER_ALLOCATIONS) && permission::has_allocateuser_capability();
    }

    /**
     * Export one certification
     *
     * @param certification $certification
     */
    protected function export_certification(certification $certification): void {
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
                rules::EXPORT_INSTANCES => rules::EXPORT_INSTANCES_COMPONENT,
                rules::EXPORT_SELECT_COMPONENT => 'tool_certification',
                rules::EXPORT_SELECT_COMPONENT_AREA => 'certification',
                rules::EXPORT_SELECT_COMPONENT_ITEMID => $certification->get('id'),
            ]);
        }
    }

    /**
     * Returns SQL for direct allocations to certification (starting with FROM)
     *
     * @param certification $certification
     * @return array
     */
    protected function get_certification_allocations_sql(certification $certification) {
        $usertenantsql = tenancy::get_users_subquery(false, true, 'u.id',
            $this->get_export_tenant_id());

        $sql1 = "FROM {tool_certification_users} cu
                        JOIN {user} u ON u.id = cu.userid
                        JOIN {tool_certification} c ON c.id = cu.certificationid
                        WHERE {$usertenantsql} cu.certificationid = :certificationid
                    ";
        return [$sql1, ['certificationid' => $certification->get('id')]];
    }

    /**
     * Export user allocations for a given certification
     *
     * @param certification $certification
     */
    protected function export_certification_allocations(certification $certification) {
        global $DB;
        $viewfullnames = has_capability('moodle/site:viewfullnames', \context_system::instance());
        [$sqlfn, $paramsfn] = \tool_reportbuilder\db::sql_fullname('u', $viewfullnames);
        [$sql, $params] = $this->get_certification_allocations_sql($certification);

        $sql1 = "SELECT cu.*, $sqlfn AS userfullname, c.fullname AS certificationfullname $sql";
        $certificationusers = $DB->get_records_sql($sql1, $params + $paramsfn);

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

        if ($data[self::EXPORT_INSTANCES] === self::EXPORT_INSTANCES_SELECTED && empty($data[self::EXPORT_SELECT_CERTIFICATIONS])) {
            $errors[self::EXPORT_SELECT_CERTIFICATIONS] = get_string('selectatleastonecertification', 'tool_certification');
        }

        if ($data[self::INCLUDE_SHARED_ENTITIES] == 0 && $data[self::EXPORT_INSTANCES] === self::EXPORT_INSTANCES_SELECTED &&
            !empty($data[self::EXPORT_SELECT_CERTIFICATIONS])) {
            // When user manually selected the certifications, check that there is something to export for each of them.
            [$sql, $params] = $DB->get_in_or_equal($data[self::EXPORT_SELECT_CERTIFICATIONS], SQL_PARAMS_NAMED, 'id');
            $params += ['tenantid' => $this->get_export_tenant_id() ?: tenancy::get_tenant_id()];
            $certifications = certification::get_records_select("id $sql AND tenantid <> :tenantid", $params);
            $certifications = array_filter($certifications, function(certification $certification) use ($data) {
                global $DB;
                [$sql, $params] = $this->get_certification_allocations_sql($certification);
                return $data[self::EXPORT_USER_ALLOCATIONS] == 0 || !$DB->record_exists_sql('SELECT 1 ' . $sql, $params);
            });

            if ($certifications) {
                // We found the shared certifications without any user allocations.
                $names = array_map(function($certification) {
                    return format_string($certification->get('fullname'), true, ['escape' => false]);
                }, $certifications);
                $errors[self::EXPORT_SELECT_CERTIFICATIONS] = get_string('errornothingtoexportforcertifications',
                    'tool_certification', join(', ', $names));
            }
        }

        return $errors;
    }

    /**
     * Returns list of certifications selected to export
     *
     * @return array
     */
    public function get_certifications_to_export(): array {
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
                $certifications = certification::get_records_select($sql . ' AND archived = :archived', $params);
                break;
            case self::EXPORT_INSTANCES_ALL:
                $certifications = certification::get_records_select($sql, $params);
                break;
            case self::EXPORT_INSTANCES_SELECTED:
                [$where1, $params1] = $DB->get_in_or_equal(
                    $this->get_export_setting(self::EXPORT_SELECT_CERTIFICATIONS), SQL_PARAMS_NAMED);
                $certifications = certification::get_records_select("$sql AND id $where1", $params + $params1);
                break;
            default:
                throw new \coding_exception('Invalid export option');
        }

        if (!$this->get_export_setting(self::INCLUDE_SHARED_ENTITIES)) {
            $certifications = array_filter($certifications, function(certification $certification) {
                global $DB;
                [$sql, $params] = $this->get_certification_allocations_sql($certification);
                return !$this->certification_is_shared($certification) ||
                    $DB->record_exists_sql("SELECT 1 " . $sql, $params);
            });
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

        // Check whether user has selected to export programs.
        if (!empty($this->get_export_setting(self::EXPORT_INSTANCES))) {

            $certifications = $this->get_certifications_to_export();

            // Export chained programs, if applicable.
            $programstoexport = [];
            if ($this->get_export_setting(self::EXPORT_PROGRAMS) && $this->can_export_chained_entity(program::TABLE)) {
                foreach ($certifications as $certification) {
                    if (!$this->get_export_setting(self::INCLUDE_SHARED_ENTITIES) &&
                            $this->certification_is_shared($certification)) {
                        continue;
                    }
                    $programstoexport[$certification->get('program')] = true;
                    if ($pid = $certification->get('recertificationprogram')) {
                        $programstoexport[$pid] = true;
                    }
                }
                $settings = [];
                if ($this->get_export_setting(self::EXPORT_COURSE_BACKUPS) && $this->can_export_chained_entity('course')) {
                    $settings[programs::EXPORT_COURSE_BACKUPS] = 1;
                    $settings[programs::EXPORT_COURSE_BACKUPS_CONTENT] =
                        $this->get_export_setting(self::EXPORT_COURSE_BACKUPS_CONTENT);
                } else {
                    $settings[programs::EXPORT_COURSE_BACKUPS] = 0;
                    $settings[programs::EXPORT_COURSE_BACKUPS_CONTENT] = 0;
                }
                if ($this->get_export_setting(self::EXPORT_DYNAMICRULES) &&
                    $this->can_export_chained_entity('tool_dynamicrule')) {
                    $settings[programs::EXPORT_PROGRAM_DYNAMICRULES] = 1;
                } else {
                    $settings[programs::EXPORT_PROGRAM_DYNAMICRULES] = 0;
                }
                $settings[programs::INCLUDE_SHARED_ENTITIES] = $this->get_export_setting(self::INCLUDE_SHARED_ENTITIES);
                $this->process_chained_entities(program::TABLE, array_keys($programstoexport), $settings);
            }

            // Export certifications.
            foreach ($certifications as $certification) {
                if ($this->get_export_setting(self::INCLUDE_SHARED_ENTITIES) || !$this->certification_is_shared($certification)) {
                    $this->export_certification($certification);
                }
                // Export certification user allocations, if applicable.
                if ($this->get_setting_export_user_allocations()) {
                    $this->export_certification_allocations($certification);
                }
            }
        }
    }
}
