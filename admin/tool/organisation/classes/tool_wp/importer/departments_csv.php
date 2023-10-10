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
 * Class departments_csv
 *
 * @package     tool_organisation
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\tool_wp\importer;

use tool_organisation\department;
use tool_organisation\department_manager;
use tool_wp\importer_base;
use tool_wp\local\exportimport\csv\csv_imported_entity;
use tool_wp\local\exportimport\forms\import_conflict_form;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\helper;

/**
 * Class departments_csv
 *
 * @package     tool_organisation
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class departments_csv extends importer_base {

    /** @var string */
    const IMPORT_TARGET_FRAMEWORK = 'target_framework';
    /** @var string */
    const IMPORT_TARGET_FRAMEWORK_NEW = 'new';
    /** @var string */
    const IMPORT_TARGET_FRAMEWORK_SELECTED = 'selected';
    /** @var string */
    const IMPORT_SELECT_FRAMEWORK = 'select_framework';
    /** @var string */
    const IMPORT_SELECT_FRAMEWORKIDNUMBER = 'select_frameworkidnumber';
    /** @var string */
    const IMPORT_HIERARCHY = 'hierarchy';
    /** @var string */
    const IMPORT_HIERARCHY_SELECTED = 'selected';
    /** @var string */
    const IMPORT_HIERARCHY_NONE = 'none';
    /** @var string */
    const IMPORT_HIERARCHY_IDENTIFIER = 'identifier';

    /** @var array */
    protected $departmentframeworks;

    /**
     * Initialise the importer
     */
    protected function initialise() {
        $this->register_entity(self::CSV_DATA, [
            self::ENTITY_DEPENDENCIES => ['tool_tenant'],
            self::ENTITY_NAMEPLURAL => get_string('departments', 'tool_organisation'),
            self::ENTITY_LOGERROR => static function(array $details) {
                $a = (object)['name' => format_string($details['name'])];
                return get_string('importlogdeptfailed', 'tool_organisation', $a);
            },
            self::ENTITY_LOGSUCCESS => static function(int $id, array $details) {
                $a = (object)['name' => format_string($details['name'])];
                return get_string('importlogdeptsuccess', 'tool_organisation', $a);
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                return format_string($record['name'], true, ['escape' => false]);
            },
        ]);

        $this->register_potential_error(self::CSV_DATA, 'idnumberconflict',
            [
                self::ERROR_LOG => function(array $details) {
                    $importerdetails = array_map('s', $details);
                    return get_string('importlogidnumberexistsdepartment', 'tool_organisation',
                        (object)$importerdetails);
                },
                self::ERROR_CONFLICTHEADER => get_string('errorsameidnumberdepartment', 'tool_organisation'),
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

        $this->register_potential_notice(self::CSV_DATA, 'idnumberchanged',
            [
                self::NOTICE_LOG => static function(array $details, array $noticedetails) {
                    $a = (object)array_map('s', $noticedetails);
                    return get_string('idnumberchanged', 'tool_wp', $a);
                }
            ]);

        $this->register_potential_notice(self::CSV_DATA, 'descriptionformatchanged',
            [
                self::NOTICE_LOG => static function(array $details, array $noticedetails) {
                    $a = (object)array_map('s', $noticedetails);
                    return get_string('exportimportfieldchanged', 'tool_wp', $a);
                }
            ]);

        // Register the framework entity because we need to show message in the log when a framework was
        // automatically created.
        $this->register_entity(orgstructure::NAME_DEPARTMENT_FRAMEWORK, [
            self::ENTITY_LOGSUCCESS => static function(int $id, array $details) {
                $a = (object)['name' => format_string($details['name'])];
                $a->url = department_manager::get_department_url($id)->out();
                return get_string('importlogdeptfrmsuccess', 'tool_organisation', $a);
            },
            self::ENTITY_DEPENDENCIES => [], // Not used.
            self::ENTITY_NAMEPLURAL => get_string('departmentframeworks', 'tool_organisation'),
            self::ENTITY_LOGERROR => '', // Not used.
        ]);
    }

    /**
     * Importer format
     *
     * @return int
     */
    public function get_format(): int {
        return self::FORMAT_CSV;
    }

    /**
     * Allows to mark importer as not available
     *
     * By default every importer is available for general import and not available for any entrypoint
     *
     * @return bool
     */
    public function is_available(): bool {
        if (!orgstructure::can_import_departments() && !orgstructure::can_import_positions()) {
            return false;
        }
        return !strlen($this->entrypoint) || $this->is_chained_entrypoint();
    }

    /**
     * Importer name
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('importerdepartmentscsv', 'tool_organisation');
    }

    /**
     * Importer description
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('importerdepartmentscsvdesc', 'tool_organisation');
    }

    /**
     * Importer icon url
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('menu/organization_structure', 'theme')->out(false);
    }

    /**
     * Add elements to the options form
     *
     * @param import_settings_form $form
     */
    public function add_to_options_form(import_settings_form $form): void {
        global $OUTPUT;
        $mform = $form->get_quick_form();
        $mform->addElement('header', 'targetframeworkheader', get_string('departmentframework', 'tool_organisation'));
        $mform->setExpanded('targetframeworkheader');

        $mform->addElement('radio', self::IMPORT_TARGET_FRAMEWORK, '', get_string('creategenericframework', 'tool_organisation'),
            self::IMPORT_TARGET_FRAMEWORK_NEW);
        $mform->addElement('radio', self::IMPORT_TARGET_FRAMEWORK, '', get_string('selectexistingframework', 'tool_organisation'),
            self::IMPORT_TARGET_FRAMEWORK_SELECTED);
        $mform->setDefault(self::IMPORT_TARGET_FRAMEWORK, self::IMPORT_TARGET_FRAMEWORK_NEW);

        // Framework picker.
        $mform->addElement('select', self::IMPORT_SELECT_FRAMEWORK, get_string('departmentframework', 'tool_organisation'),
            $this->get_all_department_frameworks_menu())->setHiddenLabel(true);
        $mform->hideIf(self::IMPORT_SELECT_FRAMEWORK,
            self::IMPORT_TARGET_FRAMEWORK, 'noteq', self::IMPORT_TARGET_FRAMEWORK_SELECTED);

        // Frameworkidnumber, only used in CSV import, thus not ever shown in the form.
        $mform->addElement('text', self::IMPORT_SELECT_FRAMEWORKIDNUMBER,
            get_string('departmentframeworkidnumber', 'tool_organisation'), ['class' => 'hidden']);
        $mform->setType(self::IMPORT_SELECT_FRAMEWORKIDNUMBER, PARAM_RAW);

        $mform->addElement('header', 'hierarchyheader', get_string('hierarchy', 'tool_organisation'));
        $mform->setExpanded('hierarchyheader');

        $fields = $this->get_csv_reader()->get_columns();
        $fields = array_combine($fields, $fields);

        $mform->addElement('radio', self::IMPORT_HIERARCHY, '', get_string('hierarchydepartments', 'tool_organisation'),
            self::IMPORT_HIERARCHY_SELECTED);

        $deptidentifierstr = get_string('departmentidentifier', 'tool_organisation');
        $group = [];
        $html = \html_writer::span($deptidentifierstr, 'mr-2 my-2');
        $group[] = $mform->createElement('static', 'selector', '', $html);
        $group[] = $mform->createElement('select', self::IMPORT_HIERARCHY_IDENTIFIER, $deptidentifierstr, $fields);
        // We can't use addHelpButton for group element, it won't show, so we output help icon as separate element.
        $helpicon = $OUTPUT->help_icon('departmentidentifier', 'tool_organisation');
        $group[] = $mform->createElement('static', 'selector', '', $helpicon);

        $mform->setDefault(self::IMPORT_HIERARCHY_IDENTIFIER, reset($fields));
        $mform->addGroup($group, 'hierarchygroup', '', '', false);
        $mform->hideIf('hierarchygroup', self::IMPORT_HIERARCHY, 'noteq', self::IMPORT_HIERARCHY_SELECTED);

        $mform->addElement('radio', self::IMPORT_HIERARCHY, '',
            get_string('listdeptsnohierarchy', 'tool_organisation'), self::IMPORT_HIERARCHY_NONE);
        $mform->setDefault(self::IMPORT_HIERARCHY, self::IMPORT_HIERARCHY_SELECTED);

        $this->add_csv_mapping_element($form,
            [
                'name' => get_string('departmentname', 'tool_organisation'),
                'idnumber' => get_string('departmentidnumber', 'tool_organisation'),
                'description' => get_string('departmentdescription', 'tool_organisation'),
                'descriptionformat' => get_string('descriptionformat', 'tool_wp'),
                'parentid' => get_string('departmentparent', 'tool_organisation'),
            ],
            function($key, $elementnamedef) use ($form) {
                $els = [];
                $mform = $form->get_quick_form();
                if ($key === 'descriptionformat') {
                    $els[] = $mform->createElement('select', $elementnamedef,
                        get_string('descriptionformatdefault', 'tool_wp'), format_text_menu());
                    $mform->setDefault($elementnamedef, FORMAT_HTML);
                }
                return $els;
            }
        );

        $form->add_validation_callback([static::class, 'validate_options_form'], $this->get_import_tenant_id());
    }

    /**
     * Perform some extra moodle validation
     *
     * @param array $data
     * @param array $files
     * @param int $tenantid
     * @return array
     */
    public static function validate_options_form(array $data, array $files, int $tenantid): array {
        global $DB;
        $errors = [];
        $keyparent = self::SETTING_CSV_COLUMNS_MAPPING.':parentid';
        $importhierarchy = $data[self::IMPORT_HIERARCHY] == self::IMPORT_HIERARCHY_SELECTED;
        if ($importhierarchy && empty($data[$keyparent])) {
            $errors[self::SETTING_CSV_COLUMNS_MAPPING.'_group'] = get_string('errorcsvnoparent', 'tool_organisation');
        } else if (!$importhierarchy && !empty($data[$keyparent])) {
            $errors[self::SETTING_CSV_COLUMNS_MAPPING.'_group'] = get_string('errorcsvnohierarchy', 'tool_organisation');
        } else if ($importhierarchy && $data[self::IMPORT_HIERARCHY_IDENTIFIER] == $data[$keyparent]) {
            $errors[self::SETTING_CSV_COLUMNS_MAPPING.'_group'] =
                get_string('errorcsvinvalidparentmapping', 'tool_organisation');
        }

        // Don't allow using both valid framework id and idnumber. If framework id is invalid,
        // it will be unset earlier and user will be offered to import to null.
        if (!empty($data[self::IMPORT_SELECT_FRAMEWORKIDNUMBER]) && !empty($data[self::IMPORT_SELECT_FRAMEWORK])) {
            $errors[self::IMPORT_TARGET_FRAMEWORK] = get_string('errorcsvcantuseframeworkidnumber', 'tool_organisation');
            return $errors;
        }

        // Check that framework with given idnumber exists.
        $frameworkidnumber = $data[self::IMPORT_SELECT_FRAMEWORKIDNUMBER] ?? false;
        if (!empty($frameworkidnumber) && !$DB->record_exists(department::TABLE,
                ['tenantid' => $tenantid, 'parentid' => null, 'idnumber' => $frameworkidnumber])) {
            $errors[self::IMPORT_TARGET_FRAMEWORK] = get_string('errorcsvinvalidframeworkidnumber', 'tool_organisation');
        }

        return $errors;
    }

    /**
     * Summary for review
     *
     * @param bool $importiscompleted
     * @return string
     */
    public function get_summary_for_review_step(bool $importiscompleted): string {
        return '';
    }

    /**
     * Returns all department frameworks present in the system and available for export (as a menu)
     *
     * @return array
     */
    protected function get_all_department_frameworks_menu(): array {
        global $DB;
        if ($this->departmentframeworks === null) {
            $this->departmentframeworks = [];
            if (orgstructure::can_import_departments()) {
                $this->departmentframeworks = $DB->get_records_menu(department::TABLE,
                    ['tenantid' => $this->get_import_tenant_id(), 'parentid' => null], '', 'id, name');
            }
        }
        return array_map('format_string', $this->departmentframeworks);
    }

    /**
     * Framework where departments will be created
     *
     * @return int
     */
    protected function get_target_framework_id(): int {
        global $DB;
        $frm = $this->get_import_setting(self::IMPORT_TARGET_FRAMEWORK);
        if ($frm == self::IMPORT_TARGET_FRAMEWORK_SELECTED) {
            $frameworkidnumber = $this->get_import_setting(self::IMPORT_SELECT_FRAMEWORKIDNUMBER);
            if (!empty($frameworkidnumber)) {
                return (int) $DB->get_field(department::TABLE, 'id',
                    ['tenantid' => $this->get_import_tenant_id(), 'parentid' => null, 'idnumber' => $frameworkidnumber]);
            }
            return (int)$this->get_import_setting(self::IMPORT_SELECT_FRAMEWORK);
        } else if ($this->is_collecting_errors()) {
            return -1;
        } else {
            $name = preg_replace('/[_\\.]/', ' ', pathinfo($this->get_file_name(), PATHINFO_FILENAME));
            $id = (new department_manager())->create_department((object)[
                'tenantid' => $this->get_import_tenant_id(),
                'name' => $name,
            ], false)->get('id');
            $this->add_to_log_raw(self::ENTITY_LOGSUCCESS, orgstructure::NAME_DEPARTMENT_FRAMEWORK, [
                'name' => $name
            ], $id);
            return $id;
        }
    }

    /**
     * Performs import
     *
     * @param string $entity - ignored in CSV import
     */
    public function perform_import(string $entity): void {
        if ($entity !== self::CSV_DATA) {
            // We register additional entity in initialise() function but here we need to ignore it.
            return;
        }

        $depmanager = new department_manager();
        $frmid = $this->get_target_framework_id();
        $identifier = ($this->get_import_setting(self::IMPORT_HIERARCHY) == self::IMPORT_HIERARCHY_SELECTED) ?
            $this->get_import_setting(self::IMPORT_HIERARCHY_IDENTIFIER) : null;
        $map = [];

        foreach ($this->get_csv_reader()->get_rows() as $csvimportedentity) {
            $csvimportedentity
                ->set_validation_callback(function(array $row, csv_imported_entity $caller) {
                    $this->add_details_to_log(['name' => $row['name'], 'originalidnumber' => $row['idnumber'] ?? null]);

                    // Make sure the idnumber is unique, if not unique and conflict resolution was not set,
                    // raise an error. Raised error will fail validation.
                    if (!empty($row['idnumber']) &&
                        department::get_records(['tenantid' => $this->get_import_tenant_id(), 'idnumber' => $row['idnumber']])) {
                        $this->add_error_to_log('idnumberconflict');
                    }
                })
                ->set_import_callback(function(array $record, csv_imported_entity $caller)
                        use ($depmanager, $frmid, $identifier, $map) {
                    // We need to increment or remove idnumber according to the choosen option in the form.
                    $record['idnumber'] = $this->get_new_idnumber($record, $caller);
                    $record['tenantid'] = $this->get_import_tenant_id();
                    $record['parentid'] = (strlen($record['parentid']) && array_key_exists($record['parentid'], $map)) ?
                        $map[$record['parentid']] : $frmid;
                    $record['descriptionformat'] = $this->get_descriptionformat($record);

                    return $depmanager->create_department((object)$record, false)->get('id');
                })
                ->import($this);

            if ($identifier && ($newid = $csvimportedentity->get_new_id())
                    && strlen($csvimportedentity->get_raw_field($identifier))) {
                $map[$csvimportedentity->get_raw_field($identifier)] = $newid;
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
     * Check if another department exists with the same idnumber
     *
     * @param string $idnumber
     * @return bool
     */
    private function idnumber_taken(string $idnumber): bool {
        global $DB;
        return $DB->record_exists(department::TABLE, ['idnumber' => $idnumber, 'tenantid' => $this->get_import_tenant_id()]);
    }

    /**
     * Make sure that descriptionformat is valid, otherwise use default.
     *
     * @param array $record
     * @return string
     */
    protected function get_descriptionformat(array $record): string {
        if (!array_key_exists($record['descriptionformat'], format_text_menu())) {
            $this->add_notice_to_log('descriptionformatchanged', ['field' => 'descriptionformat',
                'from' => $record['descriptionformat'], 'to' => FORMAT_HTML]);
            return FORMAT_HTML;
        }
        return $record['descriptionformat'];
    }

    /**
     * Make sure that idnumber is unique, otherwise append a number to the end
     *
     * @param array $record
     * @param csv_imported_entity $caller
     * @return mixed|string
     */
    protected function get_new_idnumber(array $record, csv_imported_entity $caller) {
        $lookup = function($value) {
            return $this->idnumber_taken($value);
        };
        $useincrement = $this->get_conflict_resolution_setting(self::CSV_DATA, 'idnumberconflict', 'action') === 'increment';
        $newvalue = helper::find_unique_value_for_field($record['idnumber'], $lookup, $useincrement);

        if ($newvalue !== $record['idnumber']) {
            $this->add_notice_to_log('idnumberchanged', ['from' => $record['idnumber'], 'to' => $newvalue]);
        }
        return $newvalue;
    }
}
