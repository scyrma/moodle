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
 * Class orgstructure
 *
 * @package     tool_organisation
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\tool_wp\importer;

use tool_organisation\department;
use tool_organisation\department_manager;
use tool_organisation\permission;
use tool_organisation\position;
use tool_organisation\position_manager;
use tool_tenant\tenancy;
use tool_wp\local\exportimport\forms\import_base_form;
use tool_wp\local\exportimport\forms\import_conflict_form;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\wp_imported_entity;

defined('MOODLE_INTERNAL') || die();

/**
 * Class orgstructure
 *
 * @package     tool_organisation
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class orgstructure extends \tool_wp\importer_base {

    /** @var string */
    const NAME_DEPARTMENT = 'tool_organisation_department';
    /** @var string */
    const NAME_DEPARTMENT_FRAMEWORK = 'tool_organisation_department_framework';
    /** @var string */
    const NAME_POSITION = 'tool_organisation_position';
    /** @var string */
    const NAME_POSITION_FRAMEWORK = 'tool_organisation_position_framework';

    /** @var string */
    const IMPORT_CONTENT = 'import_content';
    /** @var string */
    const IMPORT_INSTANCES = 'import_instances';
    /** @var int */
    const IMPORT_INSTANCES_ALL = 'all';
    /** @var int */
    const IMPORT_INSTANCES_DEPARTMENTS = 'departments';
    /** @var int */
    const IMPORT_INSTANCES_POSITIONS = 'positions';
    /** @var int */
    const IMPORT_INSTANCES_SELECTED = 'selected';
    /** @var string */
    const IMPORT_SELECT_FRAMEWORKS = 'select_frameworks';

    /** @var array */
    protected $positionframeworks = null;
    /** @var array */
    protected $departmentframeworks = null;

    /**
     * Exporter format
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
        return get_string('exporterorgstructure', 'tool_organisation');
    }

    /**
     * Can user import departments
     *
     * @return bool
     */
    public static function can_import_departments() {
        return permission::can_create_department();
    }

    /**
     * Can user import positions
     *
     * @return bool
     */
    public static function can_import_positions() {
        return permission::can_create_position();
    }

    /**
     * Allows to mark importer as not available
     *
     * By default every importer is available for general import and not available for any entrypoint
     *
     * @return bool
     */
    public function is_available(): bool {
        if (!self::can_import_departments() && !self::can_import_positions()) {
            return false;
        }
        return !strlen($this->entrypoint) || $this->is_chained_entrypoint();
    }

    /**
     * Register all entities that can be imported by this importer, all potential errors and notices
     */
    protected function initialise() {

        $this->register_entity(self::NAME_DEPARTMENT_FRAMEWORK, [
            self::ENTITY_DEPENDENCIES => ['tool_tenant'],
            self::ENTITY_NAMEPLURAL => get_string('departmentframeworks', 'tool_organisation'),
            self::ENTITY_LOGERROR => static function(array $details) {
                $a = (object)['name' => format_string($details['name'])];
                return get_string('importlogdeptfrmfailed', 'tool_organisation', $a);
            },
            self::ENTITY_LOGSUCCESS => static function(int $id, array $details) {
                $a = (object)['name' => format_string($details['name'])];
                $a->url = department_manager::get_department_url($id)->out();
                return get_string('importlogdeptfrmsuccess', 'tool_organisation', $a);
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                return \tool_organisation\tool_wp\exporter\orgstructure::get_formatted_department_framework_name($record['name']);
            },
            self::ENTITY_INDIVIDUALIMPORT => function(array $ids, array $settings) {
                return [
                        self::IMPORT_INSTANCES => self::IMPORT_INSTANCES_SELECTED,
                        self::IMPORT_SELECT_FRAMEWORKS => array_map(function($id) {
                            return 'd' . $id;
                        }, $ids),
                    ];
            },
        ]);

        $this->register_entity(self::NAME_DEPARTMENT, [
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
        ]);

        $this->register_entity(self::NAME_POSITION_FRAMEWORK, [
            self::ENTITY_DEPENDENCIES => ['tool_tenant'],
            self::ENTITY_NAMEPLURAL => get_string('positionframeworks', 'tool_organisation'),
            self::ENTITY_LOGERROR => static function(array $details) {
                $a = (object)['name' => format_string($details['name'])];
                return get_string('importlogposfrmfailed', 'tool_organisation', $a);
            },
            self::ENTITY_LOGSUCCESS => static function(int $id, array $details) {
                $a = (object)['name' => format_string($details['name'])];
                $a->url = position_manager::get_position_url($id)->out();
                return get_string('importlogposfrmsuccess', 'tool_organisation', $a);
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                return \tool_organisation\tool_wp\exporter\orgstructure::get_formatted_position_framework_name($record['name']);
            },
            self::ENTITY_INDIVIDUALIMPORT => function(array $ids, array $settings) {
                return [
                    self::IMPORT_INSTANCES => self::IMPORT_INSTANCES_SELECTED,
                    self::IMPORT_SELECT_FRAMEWORKS => array_map(function($id) {
                        return 'p' . $id;
                    }, $ids),
                ];
            },
        ]);

        $this->register_entity(self::NAME_POSITION, [
            self::ENTITY_DEPENDENCIES => ['tool_tenant'],
            self::ENTITY_NAMEPLURAL => get_string('positions', 'tool_organisation'),
            self::ENTITY_LOGERROR => static function(array $details) {
                $a = (object)['name' => format_string($details['name'])];
                return get_string('importlogposfailed', 'tool_organisation', $a);
            },
            self::ENTITY_LOGSUCCESS => static function(int $id, array $details) {
                $a = (object)['name' => format_string($details['name'])];
                return get_string('importlogpossuccess', 'tool_organisation', $a);
            },
        ]);

        $conflictsolution = [self::ERROR_CONFLICTSOLUTION => function(array $settings, bool $forform) {
            if ($settings['action'] === 'empty') {
                return get_string('importsetidnumbertoempty', 'tool_wp');
            } else if ($settings['action'] === 'increment') {
                return get_string('importincrementidnumber', 'tool_wp');
            }
            return null;
        }];
        $this->register_potential_error(self::NAME_DEPARTMENT_FRAMEWORK, 'idnumberconflict',
            [
                self::ERROR_CONFLICTHEADER => get_string('departmentfrmidnumberconflict', 'tool_organisation'),
                self::ERROR_LOG => function(array $details) {
                    return get_string('erroridnumberdepartment', 'tool_organisation', $details['originalidnumber']);
                },
            ] + $conflictsolution);
        $this->register_potential_error(self::NAME_DEPARTMENT, 'idnumberconflict',
            [
                self::ERROR_CONFLICTHEADER => get_string('departmentidnumberconflict', 'tool_organisation'),
                self::ERROR_LOG => function(array $details) {
                    return get_string('erroridnumberdepartment', 'tool_organisation', $details['originalidnumber']);
                },
            ] + $conflictsolution);
        $this->register_potential_error(self::NAME_POSITION_FRAMEWORK, 'idnumberconflict',
            [
                self::ERROR_CONFLICTHEADER => get_string('positionfrmidnumberconflict', 'tool_organisation'),
                self::ERROR_LOG => function(array $details) {
                    return get_string('erroridnumberposition', 'tool_organisation', $details['originalidnumber']);
                },
            ] + $conflictsolution);
        $this->register_potential_error(self::NAME_POSITION, 'idnumberconflict',
            [
                self::ERROR_CONFLICTHEADER => get_string('positionidnumberconflict', 'tool_organisation'),
                self::ERROR_LOG => function(array $details) {
                    return get_string('erroridnumberposition', 'tool_organisation', $details['originalidnumber']);
                },
            ] + $conflictsolution);

        $noticelog = [self::NOTICE_LOG => function(array $details, array $noticedetails) {
            $a = (object)array_map('s', $noticedetails);
            return get_string('idnumberchanged', 'tool_wp', $a);
        }];
        $this->register_potential_notice(self::NAME_DEPARTMENT_FRAMEWORK, 'idnumberchanged', $noticelog);
        $this->register_potential_notice(self::NAME_DEPARTMENT, 'idnumberchanged', $noticelog);
        $this->register_potential_notice(self::NAME_POSITION_FRAMEWORK, 'idnumberchanged', $noticelog);
        $this->register_potential_notice(self::NAME_POSITION, 'idnumberchanged', $noticelog);
        $this->register_potential_error(self::NAME_POSITION, 'unknownparent',
            [
                self::ERROR_CONFLICTHEADER => get_string('errorparentnotfound', 'tool_organisation'),
                self::ERROR_LOG => function(array $details) {
                    return get_string('errorparentnotfoundposition', 'tool_organisation', $details['originalidnumber']);
                },
            ] + $conflictsolution);
        $this->register_potential_error(self::NAME_DEPARTMENT, 'unknownparent',
            [
                self::ERROR_CONFLICTHEADER => get_string('errorparentnotfound', 'tool_organisation'),
                self::ERROR_LOG => function(array $details) {
                    return get_string('errorparentnotfounddepartment', 'tool_organisation', $details['originalidnumber']);
                },
            ] + $conflictsolution);
    }

    /**
     * Get all position frameworks present in the file and available for import
     *
     * @return array
     */
    protected function get_all_position_frameworks(): array {
        if ($this->positionframeworks === null) {
            $this->positionframeworks = self::can_import_positions() ?
                $this->get_entities_in_workplace_export_file(self::NAME_POSITION_FRAMEWORK)->get_menu() : [];
        }
        return $this->positionframeworks;
    }

    /**
     * Get all department frameworks in the file available for import
     *
     * @return array
     */
    protected function get_all_department_frameworks(): array {
        if ($this->departmentframeworks === null) {
            $this->departmentframeworks = self::can_import_departments() ?
                $this->get_entities_in_workplace_export_file(self::NAME_DEPARTMENT_FRAMEWORK)->get_menu() : [];
        }
        return $this->departmentframeworks;
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
        $mform->addElement('header', 'content', get_string('content', 'tool_wp'));
        $mform->setExpanded('content');

        $settingsstr = get_string('exportframeworkssettings', 'tool_organisation');
        $mform->addElement('advcheckbox', self::IMPORT_CONTENT, $settingsstr);
        $mform->setDefault(self::IMPORT_CONTENT, 1);
        $form->freeze_at(self::IMPORT_CONTENT, 1);

        $mform->addElement('header', 'headerorgstructure', get_string('instances', 'tool_wp'));
        $mform->setExpanded('headerorgstructure');
        $alldepatments = $this->get_all_department_frameworks();
        $allpositions = $this->get_all_position_frameworks();
        $default = null; // By default select first available radio button.

        if ($alldepatments && $allpositions) {
            $mform->addElement('radio', self::IMPORT_INSTANCES, '',
                get_string('selectallframeworks', 'tool_organisation'), self::IMPORT_INSTANCES_ALL);
            $default = self::IMPORT_INSTANCES_ALL;
        }

        if ($alldepatments) {
            $mform->addElement('radio', self::IMPORT_INSTANCES, '',
                get_string('selectalldepartmentframeworks', 'tool_organisation'), self::IMPORT_INSTANCES_DEPARTMENTS);
            $default = $default ?? self::IMPORT_INSTANCES_DEPARTMENTS;
        }

        if ($allpositions) {
            $mform->addElement('radio', self::IMPORT_INSTANCES, '',
                get_string('selectallpositionframeworks', 'tool_organisation'), self::IMPORT_INSTANCES_POSITIONS);
            $default = $default ?? self::IMPORT_INSTANCES_POSITIONS;
        }

        $mform->addElement('radio', self::IMPORT_INSTANCES, '',
            get_string('selectmanually', 'tool_wp'), self::IMPORT_INSTANCES_SELECTED);
        $default = $default ?? self::IMPORT_INSTANCES_SELECTED;
        $fulllist = [];
        foreach ($alldepatments as $id => $name) {
            $fulllist['d' . $id] = \tool_organisation\tool_wp\exporter\orgstructure::get_formatted_department_framework_name($name);
        }
        foreach ($allpositions as $id => $name) {
            $fulllist['p' . $id] = \tool_organisation\tool_wp\exporter\orgstructure::get_formatted_position_framework_name($name);
        }
        asort($fulllist);

        // Frameworks picker.
        $mform->addElement('autocomplete', self::IMPORT_SELECT_FRAMEWORKS, get_string('frameworks', 'tool_organisation'),
            $fulllist, ['multiple' => true])->setHiddenLabel(true);
        $mform->hideIf(self::IMPORT_SELECT_FRAMEWORKS, self::IMPORT_INSTANCES, 'noteq', self::IMPORT_INSTANCES_SELECTED);

        $mform->setDefault(self::IMPORT_INSTANCES, $default);

        $form->add_validation_callback(static function(array $data, array $files) {
            $errors = [];
            if ($data[self::IMPORT_INSTANCES] === self::IMPORT_INSTANCES_SELECTED &&
                    (!is_array($data[self::IMPORT_SELECT_FRAMEWORKS]) || empty($data[self::IMPORT_SELECT_FRAMEWORKS]))) {
                $errors[self::IMPORT_SELECT_FRAMEWORKS] = get_string('required');
            }
            return $errors;
        });
    }

    /**
     * Summary of the import settings for the review step and also for the report page
     *
     * @param bool $importiscompleted
     * @return string
     */
    public function get_summary_for_review_step(bool $importiscompleted): string {
        global $OUTPUT;
        $settings = [];
        $settingsstr = get_string('exportframeworkssettings', 'tool_organisation');
        $settings[] = ['name' => $settingsstr, 'value' => 1];

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
        if ($c1 = $this->get_entities_in_workplace_export_file(self::NAME_DEPARTMENT_FRAMEWORK)->count()) {
            $rv[] = get_string('departmentframeworks', 'tool_organisation') .
                get_string('entitiescountpostfix', 'tool_wp', $c1);
        }
        if ($c2 = $this->get_entities_in_workplace_export_file(self::NAME_POSITION_FRAMEWORK)->count()) {
            $rv[] = get_string('positionframeworks', 'tool_organisation') .
                get_string('entitiescountpostfix', 'tool_wp', $c2);
        }
        return $rv;
    }

    /**
     * Import executed from an ad-hoc task
     *
     * @param string $entity
     */
    public function perform_import(string $entity): void {
        if ($entity === self::NAME_POSITION_FRAMEWORK) {
            $this->import_position_frameworks();
        }
        if ($entity === self::NAME_DEPARTMENT_FRAMEWORK) {
            $this->import_department_frameworks();
        }
        // Positions and departments will be imported as part of their frameworks.
    }

    /**
     * Validation callback for imported entities (applies to positions, departments and all frameworks)
     *
     * @param array $record
     * @param wp_imported_entity $caller
     * @param array|null $validateparent
     */
    protected function validation_callback(array $record, wp_imported_entity $caller, array $validateparent = null) {
        $this->add_details_to_log(['name' => $record['name'], 'originalidnumber' => $record['idnumber']]);
        if ($validateparent !== null && !in_array($record['parentid'], $validateparent)) {
            // We don't use get_mapping() here because we need to be more strict and make sure that the parent
            // is inside the same framework. Also there is no conflict resolution for this type of error.
            $this->add_error_to_log('unknownparent', false);
            return;
        }
        if ($this->idnumber_already_used($caller, $record['idnumber'])) {
            $this->add_error_to_log('idnumberconflict');
        }
    }

    /**
     * Get the list of department frameworks ids selected for import
     *
     * @return array
     */
    protected function get_selected_department_frameworks_ids() {
        $alldepts = $this->get_all_department_frameworks();
        $s = $this->get_import_setting(self::IMPORT_INSTANCES);
        $ids = [];
        if ($s === self::IMPORT_INSTANCES_ALL || $s === self::IMPORT_INSTANCES_DEPARTMENTS) {
            $ids = array_keys($alldepts);
        } else if ($s === self::IMPORT_INSTANCES_SELECTED) {
            $selected = $this->get_import_setting(self::IMPORT_SELECT_FRAMEWORKS);
            foreach ($alldepts as $id => $name) {
                if (in_array('d' . $id, $selected)) {
                    $ids[] = $id;
                }
            }
        }
        return $ids;
    }

    /**
     * Get the list of position frameworks ids selected for import
     *
     * @return array
     */
    protected function get_selected_position_frameworks_ids() {
        $alldepts = $this->get_all_position_frameworks();
        $s = $this->get_import_setting(self::IMPORT_INSTANCES);
        $ids = [];
        if ($s === self::IMPORT_INSTANCES_ALL || $s === self::IMPORT_INSTANCES_POSITIONS) {
            $ids = array_keys($alldepts);
        } else if ($s === self::IMPORT_INSTANCES_SELECTED) {
            $selected = $this->get_import_setting(self::IMPORT_SELECT_FRAMEWORKS);
            foreach ($alldepts as $id => $name) {
                if (in_array('p' . $id, $selected)) {
                    $ids[] = $id;
                }
            }
        }
        return $ids;
    }

    /**
     * Import department frameworks
     */
    protected function import_department_frameworks() {
        if (!$ids = $this->get_selected_department_frameworks_ids()) {
            return;
        }
        $entities = $this->get_entities_in_workplace_export_file(self::NAME_DEPARTMENT_FRAMEWORK,
            function($entity) use ($ids) {
                return in_array($entity['id'], $ids);
            });

        // Import.
        $deptmanager = new department_manager();
        $depts = [];
        foreach ($entities as $entity) {
            $entity
                ->add_mapping('tenantid', 'tool_tenant')
                ->exclude_fields(['path', 'pathlevel', 'parentid'])
                ->set_validation_callback(function(array $record, wp_imported_entity $caller) {
                    $this->validation_callback($record, $caller);
                })
                ->set_import_callback(function(array $record, wp_imported_entity $caller) use ($deptmanager) {
                    $record['idnumber'] = $this->get_new_idnumber($record, $caller);
                    return $deptmanager->create_department((object)$record, false)->get('id');
                })
                ->import_files_for_itemid(['filearea' => 'departmentdescription', 'component' => 'tool_organisation'])
                ->import($this);

            $depts[] = $entity->get_original_id();
            $newid = $this->get_mapping(self::NAME_DEPARTMENT_FRAMEWORK, $entity->get_original_id(), IGNORE_MISSING);
            if ($newid) {
                // Import manager will set mapping to the 'tool_organisation_deparment_framework' entity but we also
                // need to set it to the 'tool_organisation_department' entity (for parent lookup).
                $this->set_mapping(self::NAME_DEPARTMENT, $entity->get_original_id(), $newid);
            }

            // Now import all departments in this framework.
            if ($this->is_collecting_errors() || $newid) {
                $this->import_departments($entity->get_original_id());
            }
        }
    }

    /**
     * Import all departments in the given framework
     *
     * @param int $originalfrmid
     */
    protected function import_departments(int $originalfrmid) {
        $entities = $this->get_entities_in_workplace_export_file(self::NAME_DEPARTMENT,
            function($entity) use ($originalfrmid) {
                return preg_match('|^/' . $originalfrmid . '/|', $entity['path']);
            },
            function($entity) {
                // Sort by pathlevel so that parents are always before children.
                return $entity['pathlevel'];
            });

        $deptmanager = new department_manager();
        $depts = [$originalfrmid];
        foreach ($entities as $entity) {
            $entity
                ->add_mapping('tenantid', 'tool_tenant')
                ->exclude_fields(['path', 'pathlevel'])
                ->set_validation_callback(function(array $record, wp_imported_entity $caller) use ($depts) {
                    $this->validation_callback($record, $caller, $depts);
                })
                ->set_import_callback(function(array $record, wp_imported_entity $caller) use ($deptmanager) {
                    $record['idnumber'] = $this->get_new_idnumber($record, $caller);
                    $record['parentid'] = $this->get_mapping(self::NAME_DEPARTMENT, $record['parentid']);
                    return $deptmanager->create_department((object)$record, false)->get('id');
                })
                ->import_files_for_itemid(['filearea' => 'departmentdescription', 'component' => 'tool_organisation'])
                ->import($this);
            $depts[] = $entity->get_original_id();
        }
    }

    /**
     * Check if idnumber is already used by another entity (position/department)
     *
     * @param wp_imported_entity $caller
     * @param string $idnumber
     * @return bool
     */
    private function idnumber_already_used(wp_imported_entity $caller, ?string $idnumber) {
        global $DB;
        $tenantid = $this->get_import_tenant_id() ?: tenancy::get_tenant_id();
        if (in_array($caller->get_entity_name(), [self::NAME_POSITION_FRAMEWORK, self::NAME_POSITION])) {
            $tablename = position::TABLE;
        } else if (in_array($caller->get_entity_name(), [self::NAME_DEPARTMENT_FRAMEWORK, self::NAME_DEPARTMENT])) {
            $tablename = department::TABLE;
        } else {
            return false;
        }

        return strlen($idnumber) &&
                $DB->record_exists($tablename, ['idnumber' => $idnumber, 'tenantid' => $tenantid]);
    }

    /**
     * Make sure that idnumber is unique, otherwise append a number to the end
     *
     * @param array $record
     * @param wp_imported_entity $caller
     * @return string
     */
    protected function get_new_idnumber(array $record, wp_imported_entity $caller) {
        $lookup = function($value) use ($caller) {
            return $this->idnumber_already_used($caller, $value);
        };
        $useincrement =
            $this->get_conflict_resolution_setting($caller->get_entity_name(), 'idnumberconflict', 'action') === 'increment';
        $newvalue = helper::find_unique_value_for_field($record['idnumber'], $lookup, $useincrement);

        if ($newvalue !== $record['idnumber']) {
            $this->add_notice_to_log('idnumberchanged', ['from' => $record['idnumber'], 'to' => $newvalue]);
        }
        return $newvalue;
    }

    /**
     * Import position frameworks
     */
    protected function import_position_frameworks() {
        if (!$ids = $this->get_selected_position_frameworks_ids()) {
            return;
        }
        $entities = $this->get_entities_in_workplace_export_file(self::NAME_POSITION_FRAMEWORK,
            function($entity) use ($ids) {
                return in_array($entity['id'], $ids);
            });

        // Import.
        $posmanager = new position_manager();
        $depts = [];
        foreach ($entities as $entity) {
            $entity
                ->add_mapping('tenantid', 'tool_tenant')
                ->exclude_fields(['path', 'pathlevel', 'parentid'])
                ->set_validation_callback(function(array $record, wp_imported_entity $caller) {
                    $this->validation_callback($record, $caller);
                })
                ->set_import_callback(function(array $record, wp_imported_entity $caller) use ($posmanager) {
                    $record['idnumber'] = $this->get_new_idnumber($record, $caller);
                    return $posmanager->create_position((object)$record, false)->get('id');
                })
                ->import_files_for_itemid(['filearea' => 'positiondescription', 'component' => 'tool_organisation'])
                ->import($this);

            $newid = $this->get_mapping(self::NAME_POSITION_FRAMEWORK, $entity->get_original_id(), IGNORE_MISSING);
            if ($newid) {
                // Import manager will set mapping to the 'tool_organisation_position_framework' entity but we also
                // need to set it to the 'tool_organisation_position' entity (for parent lookup).
                $this->set_mapping(self::NAME_POSITION, $entity->get_original_id(), $newid);
            }

            // Now import all departments in this framework.
            if ($this->is_collecting_errors() || $newid) {
                $this->import_positions($entity->get_original_id());
            }
        }
    }

    /**
     * Import all positions in the given framework
     *
     * @param int $originalfrmid
     */
    protected function import_positions(int $originalfrmid) {
        $entities = $this->get_entities_in_workplace_export_file(self::NAME_POSITION,
            function($entity) use ($originalfrmid) {
                return preg_match('|^/' . $originalfrmid . '/|', $entity['path']);
            },
            function($entity) {
                // Sort by pathlevel so that parents are always before children.
                return $entity['pathlevel'];
            });

        $posmanager = new position_manager();
        $depts = [$originalfrmid];
        foreach ($entities as $entity) {
            $entity
                ->add_mapping('tenantid', 'tool_tenant')
                ->exclude_fields(['path', 'pathlevel'])
                ->set_validation_callback(function(array $record, wp_imported_entity $caller) use ($depts) {
                    $this->validation_callback($record, $caller, $depts);
                })
                ->set_import_callback(function(array $record, wp_imported_entity $caller) use ($posmanager) {
                    $record['idnumber'] = $this->get_new_idnumber($record, $caller);
                    $record['parentid'] = $this->get_mapping(self::NAME_POSITION, $record['parentid']);
                    return $posmanager->create_position((object)$record, false)->get('id');
                })
                ->import_files_for_itemid(['filearea' => 'positiondescription', 'component' => 'tool_organisation'])
                ->import($this);
            $depts[] = $entity->get_original_id();
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

        if ($errorcode === 'idnumberconflict') {
            $mform = $form->get_quick_form();
            $allowskip = ($importedentity == self::NAME_POSITION_FRAMEWORK || $importedentity == self::NAME_DEPARTMENT_FRAMEWORK);
            parent::add_to_conflict_form($form, $importedentity, $errorcode, $detailsarray, $allowskip);
            $key = $this->get_conflict_form_element_name($importedentity, $errorcode);
            foreach (['empty', 'increment'] as $action) {
                $mform->addElement('radio', $key, '',
                    $this->get_conflict_solution($importedentity, $errorcode, ['action' => $action]), $action);
            }
            $mform->setDefault($key, $allowskip ? 'skip' : 'empty');
            $mform->setType($key, PARAM_ALPHANUMEXT);
        } else {
            parent::add_to_conflict_form($form, $importedentity, $errorcode, $detailsarray);
        }
    }

}
