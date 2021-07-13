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
 * Class orgstructure
 *
 * @package     tool_organisation
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\tool_wp\exporter;

use tool_organisation\department;
use tool_organisation\department_manager;
use tool_organisation\permission;
use tool_organisation\position;
use tool_organisation\position_manager;
use tool_tenant\hierarchy;
use tool_tenant\sharedspace;
use tool_tenant\tenancy;
use tool_wp\db;
use tool_wp\local\exportimport\forms\export_settings_form;

defined('MOODLE_INTERNAL') || die();

/**
 * Export for organisation structure (departments and positions)
 *
 * @package     tool_organisation
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class orgstructure extends \tool_wp\exporter_base {

    /** @var string */
    const NAME_DEPARTMENT = 'tool_organisation_department';
    /** @var string */
    const NAME_DEPARTMENT_FRAMEWORK = 'tool_organisation_department_framework';
    /** @var string */
    const NAME_POSITION = 'tool_organisation_position';
    /** @var string */
    const NAME_POSITION_FRAMEWORK = 'tool_organisation_position_framework';

    /** @var string */
    const EXPORT_SETTINGS = 'export_content';
    /** @var string */
    const EXPORT_INSTANCES = 'export_instances';
    /** @var int */
    const EXPORT_INSTANCES_ALL = 'all';
    /** @var int */
    const EXPORT_INSTANCES_DEPARTMENTS = 'departments';
    /** @var int */
    const EXPORT_INSTANCES_POSITIONS = 'positions';
    /** @var int */
    const EXPORT_INSTANCES_SELECTED = 'selected';
    /** @var string */
    const EXPORT_SELECT_FRAMEWORKS = 'select_frameworks';
    /** @var string Element for including shared entities. */
    const INCLUDE_SHARED_ENTITIES = 'include_shared_entities';

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
     * Exporter name
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('exporterorgstructure', 'tool_organisation');
    }

    /**
     * Exporter description to show in the list of available exporters
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('exporterorgstructuredesc', 'tool_organisation');
    }

    /**
     * Exporter icon url
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('menu/organization_structure', 'theme')->out(false);
    }

    /**
     * User can export/import departments
     *
     * @return bool
     */
    public static function can_export_departments() {
        return permission::can_create_department();
    }

    /**
     * User can export/import positions
     *
     * @return bool
     */
    public static function can_export_positions() {
        return permission::can_create_position();
    }

    /**
     * Allows to mark exporter as not available, checks capabilities and entry point
     *
     * @return bool
     */
    public function is_available(): bool {
        if (!$this->can_export_departments() && !$this->can_export_positions()) {
            return false;
        }
        return !strlen($this->entrypoint) || $this->is_chained_entrypoint();
    }

    /**
     * Formatted position name for the lists
     *
     * @param string $name
     * @return string
     */
    public static function get_formatted_position_framework_name(string $name) {
        return get_string('positionframeworkpostfix', 'tool_organisation',
            format_string($name));
    }

    /**
     * Formatted department name for the lists
     *
     * @param string $name
     * @return string
     */
    public static function get_formatted_department_framework_name($name) {
        return get_string('departmentframeworkpostfix', 'tool_organisation',
            format_string($name));
    }

    /**
     * Register all entities that can be imported by this importer, all potential errors and notices
     */
    protected function initialise() {
        $this->register_entity(self::NAME_DEPARTMENT_FRAMEWORK, [
            self::ENTITY_INDIVIDUALEXPORT => static function(array $ids, array $settings) {
                $defaults = [
                    self::EXPORT_INSTANCES => self::EXPORT_INSTANCES_SELECTED,
                    self::INCLUDE_SHARED_ENTITIES => 0,
                ];

                return array_intersect_key($settings, $defaults) + $defaults + [
                    self::EXPORT_SELECT_FRAMEWORKS => array_map(function($id) {
                        return 'd' . $id;
                    }, $ids),
                ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => static function(array $record) {
                return static::get_formatted_department_framework_name($record['name']);
            },
        ]);
        $this->register_entity(self::NAME_DEPARTMENT, []);

        $this->register_entity(self::NAME_POSITION_FRAMEWORK, [
            self::ENTITY_INDIVIDUALEXPORT => static function(array $ids, array $settings) {
                $defaults = [
                    self::EXPORT_INSTANCES => self::EXPORT_INSTANCES_SELECTED,
                    self::INCLUDE_SHARED_ENTITIES => 0,
                ];

                return array_intersect_key($settings, $defaults) + $defaults + [
                    self::EXPORT_SELECT_FRAMEWORKS => array_map(function($id) {
                        return 'p' . $id;
                    }, $ids),
                ];
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => static function(array $record) {
                return static::get_formatted_position_framework_name($record['name']);
            },
        ]);
        $this->register_entity(self::NAME_POSITION, []);
    }

    /**
     * Export configuration form
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
        $mform->addElement('header', 'content', get_string('content', 'tool_wp'));
        $mform->setExpanded('content');

        $settingsstr = get_string('exportframeworkssettings', 'tool_organisation');
        $mform->addElement('advcheckbox', self::EXPORT_SETTINGS, $settingsstr);
        $mform->setDefault(self::EXPORT_SETTINGS, 1);
        $form->freeze_at(self::EXPORT_SETTINGS, 1);

        $mform->addElement('header', 'instances', get_string('instances', 'tool_wp'));
        $mform->setExpanded('instances');

        $selectall = get_string('selectallframeworks', 'tool_organisation');
        $selectdepartments = get_string('selectalldepartmentframeworks', 'tool_organisation');
        $selectpositions = get_string('selectallpositionframeworks', 'tool_organisation');
        $selectmanually = get_string('selectmanually', 'tool_wp');
        $mform->addElement('radio', self::EXPORT_INSTANCES, null, $selectall, self::EXPORT_INSTANCES_ALL);
        $mform->addElement('radio', self::EXPORT_INSTANCES, null, $selectdepartments, self::EXPORT_INSTANCES_DEPARTMENTS);
        $mform->addElement('radio', self::EXPORT_INSTANCES, null, $selectpositions, self::EXPORT_INSTANCES_POSITIONS);
        $mform->addElement('radio', self::EXPORT_INSTANCES, null, $selectmanually, self::EXPORT_INSTANCES_SELECTED);
        $mform->setType(self::EXPORT_INSTANCES, PARAM_ALPHANUM);
        $mform->setDefault(self::EXPORT_INSTANCES, self::EXPORT_INSTANCES_ALL);

        // Frameworks picker.
        $mform->addElement('autocomplete', self::EXPORT_SELECT_FRAMEWORKS, get_string('frameworks', 'tool_organisation'),
            $this->get_all_frameworks_menu(), ['multiple' => true])->setHiddenLabel(true);
        $mform->hideIf(self::EXPORT_SELECT_FRAMEWORKS, self::EXPORT_INSTANCES, 'noteq', self::EXPORT_INSTANCES_SELECTED);

        $mform->addElement('static', 'sharedentities', '', \html_writer::empty_tag('hr'));

        if (!sharedspace::is_shared_space()) {
            $str = get_string('include_shared_entities', 'tool_organisation');
            $mform->addElement('advcheckbox', self::INCLUDE_SHARED_ENTITIES, $str);
            $mform->setDefault(self::INCLUDE_SHARED_ENTITIES, 0);
            $mform->addHelpButton(self::INCLUDE_SHARED_ENTITIES, 'include_shared_entities', 'tool_organisation');
        } else {
            $mform->addElement('hidden', self::INCLUDE_SHARED_ENTITIES, 0);
            $mform->setType(self::INCLUDE_SHARED_ENTITIES, PARAM_INT);
        }

        $form->add_validation_callback(static function(array $data, array $files) {
            $errors = [];
            if ($data[self::EXPORT_INSTANCES] === self::EXPORT_INSTANCES_SELECTED &&
                    (!is_array($data[self::EXPORT_SELECT_FRAMEWORKS]) || empty($data[self::EXPORT_SELECT_FRAMEWORKS]))) {
                $errors[self::EXPORT_SELECT_FRAMEWORKS] = get_string('required');
            }
            return $errors;
        });
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
        $settingsstr = get_string('exportframeworkssettings', 'tool_organisation');
        $settings[] = ['name' => $settingsstr, 'value' => 1];

        if (!sharedspace::is_shared_space()) {
            $sharedstr = get_string('include_shared_entities', 'tool_organisation');
            $settings[] = ['name' => $sharedstr, 'value' => !empty($this->get_export_settings()[self::INCLUDE_SHARED_ENTITIES])];
        }

        return $OUTPUT->render_from_template(
            'tool_wp/exportimport_summary',
            ['settings' => $settings]
        );
    }

    /**
     * Returns the list of entities that will be exported
     *
     * Implement also {@see get_summary_for_review_step()}
     *
     * @param string $entityname
     * @return array array where each element is array that can be passed through self::ENTITY_INSTANCENAME_FOR_REVIEW
     *     callback
     */
    public function get_instances_for_review_step(string $entityname): array {
        $rv = [];
        if ($entityname === self::NAME_DEPARTMENT_FRAMEWORK) {
            $rv = $this->get_departments_frameworks();
        } else if ($entityname === self::NAME_POSITION_FRAMEWORK) {
            $rv = $this->get_positions_frameworks();
        }
        return array_map(function($id, $name) {
            return ['id' => $id, 'name' => $name];
        }, array_keys($rv), $rv);
    }

    /**
     * Returns all position frameworks present in the system and available for export (as a menu)
     *
     * @param bool $includeshared
     * @return array
     */
    protected function get_all_position_frameworks(bool $includeshared = false): array {
        global $DB;
        $this->positionframeworks = [];
        if (self::can_export_positions()) {

            $tenantid = $this->get_export_tenant_id() ?: tenancy::get_tenant_id();
            if ($this->get_export_setting(self::INCLUDE_SHARED_ENTITIES) || $includeshared) {
                [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid', 'shared=1',
                    $tenantid);
                $positionframeworks = position::get_records_select($sql . ' AND parentid IS NULL', $params);
                foreach ($positionframeworks as $pf) {
                    $this->positionframeworks[$pf->get('id')] = format_string($pf->get('name'));
                }
            } else {
                $this->positionframeworks = $DB->get_records_menu(position::TABLE,
                    ['tenantid' => $tenantid, 'parentid' => null], '', 'id, name');
            }
        }
        return $this->positionframeworks;
    }

    /**
     * Returns all department frameworks present in the system and available for export (as a menu)
     *
     * @param bool $includeshared
     * @return array
     */
    protected function get_all_department_frameworks(bool $includeshared = false): array {
        global $DB;
        $this->departmentframeworks = [];
        if (self::can_export_departments()) {
            $tenantid = $this->get_export_tenant_id() ?: tenancy::get_tenant_id();
            if ($this->get_export_setting(self::INCLUDE_SHARED_ENTITIES) || $includeshared) {
                [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid', 'shared=1',
                    $tenantid);
                $departmentframeworks = department::get_records_select($sql . ' AND parentid IS NULL', $params);
                foreach ($departmentframeworks as $df) {
                    $this->departmentframeworks[$df->get('id')] = format_string($df->get('name'));
                }
            } else {
                $this->departmentframeworks = $DB->get_records_menu(department::TABLE,
                    ['tenantid' => $tenantid, 'parentid' => null], '', 'id, name');
            }
        }
        return $this->departmentframeworks;
    }

    /**
     * Returns list of all frameworks available for export (as a menu)
     *
     * @return array
     */
    protected function get_all_frameworks_menu(): array {
        $rv = [];
        foreach ($this->get_all_department_frameworks(true) as $id => $name) {
            $rv['d'.$id] = self::get_formatted_department_framework_name($name);
        }
        foreach ($this->get_all_position_frameworks(true) as $id => $name) {
            $rv['p'.$id] = self::get_formatted_position_framework_name($name);
        }
        \core_collator::asort($rv);
        return $rv;
    }

    /**
     * Get list of department frameworks that need to be exported
     *
     * @return array
     */
    protected function get_departments_frameworks(): array {
        $s = $this->get_export_setting(self::EXPORT_INSTANCES);
        $ids = $this->get_export_setting(self::EXPORT_SELECT_FRAMEWORKS) ?: [];
        $rv = [];
        foreach ($this->get_all_department_frameworks() as $id => $name) {
            if ($s === self::EXPORT_INSTANCES_ALL || $s === self::EXPORT_INSTANCES_DEPARTMENTS ||
                    ($s === self::EXPORT_INSTANCES_SELECTED && in_array('d' . $id, $ids))) {
                $rv[$id] = $name;
            }
        }
        return $rv;
    }

    /**
     * Get list of departments that need to be exported
     *
     * @return array
     */
    protected function get_departments(): array {
        global $DB;
        if ($frameworksids = array_keys($this->get_departments_frameworks())) {
            [$tenantsql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid', 'shared=1',
                $this->get_export_tenant_id() ?: tenancy::get_tenant_id());

            $sql = [];
            foreach ($frameworksids as $id) {
                $p1 = db::generate_param_name();
                $p2 = db::generate_param_name();
                $params[$p1] = $id;
                $params[$p2] = '/' . $id .'/%';
                $sql[] = "id = :$p1";
                $sql[] = '(' . $DB->sql_like('path', ':' . $p2) . ')';
            }
            return $DB->get_records_select(department::TABLE,
                $tenantsql . ' AND (' . join(' OR ', $sql) . ')',
                $params);
        }
        return [];
    }

    /**
     * Get list of position frameworks that will be exported
     *
     * @return array
     */
    protected function get_positions_frameworks(): array {
        $s = $this->get_export_setting(self::EXPORT_INSTANCES);
        $ids = $this->get_export_setting(self::EXPORT_SELECT_FRAMEWORKS) ?: [];
        $rv = [];
        foreach ($this->get_all_position_frameworks() as $id => $name) {
            if ($s === self::EXPORT_INSTANCES_ALL || $s === self::EXPORT_INSTANCES_POSITIONS ||
                    ($s === self::EXPORT_INSTANCES_SELECTED && in_array('p' . $id, $ids))) {
                $rv[$id] = $name;
            }
        }
        return $rv;
    }

    /**
     * Get list of positions that will be exported
     *
     * @return array
     */
    protected function get_positions(): array {
        global $DB;
        if ($frameworksids = array_keys($this->get_positions_frameworks())) {
            [$tenantsql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid', 'shared=1',
                $this->get_export_tenant_id() ?: tenancy::get_tenant_id());
            $sql = [];
            foreach ($frameworksids as $id) {
                $p1 = db::generate_param_name();
                $p2 = db::generate_param_name();
                $params[$p1] = $id;
                $params[$p2] = '/' . $id .'/%';
                $sql[] = "id = :$p1";
                $sql[] = '(' . $DB->sql_like('path', ':' . $p2) . ')';
            }
            return $DB->get_records_select(position::TABLE,
                $tenantsql . ' AND (' . join(' OR ', $sql) . ')',
                $params);
        }
        return [];
    }

    /**
     * Performs the export
     *
     * @return void
     */
    public function perform_export(): void {

        $excludefields = ['timecreated', 'timemodified', 'archived', 'timearchived'];

        foreach ($this->get_departments() as $record) {
            if ($record->parentid) {
                $entityname = self::NAME_DEPARTMENT;
            } else {
                $entityname = self::NAME_DEPARTMENT_FRAMEWORK;
            }
            $this->prepare_data_for_workplace_export($entityname, (array)$record)
                ->exclude_fields($excludefields)
                ->add_mappings('tenantid', 'tool_tenant')
                ->add_files_from_text($record->description, \context_system::instance(), 'tool_organisation',
                    department_manager::get_description_filearea(), $record->id)
                ->export();
        }

        foreach ($this->get_positions() as $record) {
            if ($record->parentid) {
                $entityname = self::NAME_POSITION;
            } else {
                $entityname = self::NAME_POSITION_FRAMEWORK;
            }
            $this->prepare_data_for_workplace_export($entityname, (array)$record)
                ->exclude_fields($excludefields)
                ->add_mappings('tenantid', 'tool_tenant')
                ->add_files_from_text($record->description, \context_system::instance(), 'tool_organisation',
                    position_manager::get_description_filearea(), $record->id)
                ->export();
        }
    }
}
