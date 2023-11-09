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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_organisation\tool_wp\exporter;

use core_reportbuilder\local\helpers\database;
use tool_organisation\department;
use tool_tenant\hierarchy;
use tool_tenant\tenancy;
use tool_wp\local\exportimport\forms\export_settings_form;

/**
 * Export for departments (CSV format)
 *
 * @package     tool_organisation
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Roberto Bravo <roberto.bravo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class departments_csv extends orgstructure {

    /** @var string */
    const EXPORT_SELECT_FRAMEWORK = 'select_framework';

    /**
     * Exporter format
     *
     * @return int
     */
    public function get_format(): int {
        return self::FORMAT_CSV;
    }

    /**
     * Exporter name
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('exporterdepartmentscsv', 'tool_organisation');
    }

    /**
     * Exporter description to show in the list of available exporters
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('exporterdepartmentsdesc', 'tool_organisation');
    }

    /**
     * Allows to mark exporter as not available, checks capabilities and entry point
     *
     * @return bool
     */
    public function is_available(): bool {
        return !strlen($this->entrypoint) && self::can_export_departments();
    }

    /**
     * Register all entities that can be exported by this exporter, all potential errors and notices
     *
     * @return void
     */
    protected function initialise(): void {
        $this->register_entity(self::NAME_DEPARTMENT_FRAMEWORK, [
            self::ENTITY_INSTANCENAME_FOR_REVIEW => static function(array $record) {
                if ($record['id'] === 0) {
                    // No department records. We set id to 0 for dummy record that is needed
                    // to produce header row in csv.
                    return '';
                }
                return orgstructure::get_formatted_department_framework_name($record['name']);
            },
        ]);
        $this->register_entity(self::NAME_DEPARTMENT, [
            self::ENTITY_INSTANCENAME_FOR_REVIEW => static function(array $record) {
                return format_string($record['name']);
            },
        ]);
    }

    /**
     * Export configuration form
     *
     * @param export_settings_form $form
     * @return void
     */
    public function add_to_options_form(export_settings_form $form): void {
        $mform = $form->get_quick_form();
        $mform->addElement('header', 'content', get_string('content', 'tool_wp'));
        $mform->setExpanded('content');

        $settingsstr = get_string('exportframeworkssettingsdescriptionshierarchy', 'tool_organisation');
        $mform->addElement('advcheckbox', self::EXPORT_SETTINGS, $settingsstr);
        $mform->setDefault(self::EXPORT_SETTINGS, 1);
        $form->freeze_at(self::EXPORT_SETTINGS, 1);

        $mform->addElement('header', 'instances', get_string('instances', 'tool_wp'));
        $mform->setExpanded('instances');

        // Framework picker.
        $mform->addElement('static', 'frameworkselection', '', get_string('selectdepartmentframework', 'tool_organisation'));
        $allframeworks = $this->get_all_frameworks_menu();
        $mform->addElement('select', self::EXPORT_SELECT_FRAMEWORK, '', $allframeworks);
        // The first listed framework will be the default one.
        $mform->setDefault(self::EXPORT_SELECT_FRAMEWORK, key($allframeworks));

        $form->add_validation_callback(static function(array $data) {
            $errors = [];
            if (empty($data[self::EXPORT_SELECT_FRAMEWORK])) {
                $errors[self::EXPORT_SELECT_FRAMEWORK] = get_string('required');
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
        $settingsstr = get_string('exportframeworkssettingsdescriptionshierarchy', 'tool_organisation');
        $settings[] = ['name' => $settingsstr, 'value' => 1];

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
            $rv = $this->get_departments_framework();
        }

        return array_map(function($id, $name) {
            return ['id' => $id, 'name' => $name];
        }, array_keys($rv), $rv);
    }

    /**
     * Returns list of all frameworks available for export (as a menu)
     *
     * @return array
     */
    protected function get_all_frameworks_menu(): array {
        $rv = [];
        foreach ($this->get_all_department_frameworks(true) as $id => $name) {
            $rv[$id] = orgstructure::get_formatted_department_framework_name($name);
        }
        \core_collator::asort($rv);
        return $rv;
    }

    /**
     * Get department framework that needs to be exported
     *
     * @return array
     */
    protected function get_departments_framework(): array {
        $rv = [];
        $frmselectedid = $this->get_export_setting(self::EXPORT_SELECT_FRAMEWORK) ?: 0;
        foreach ($this->get_all_department_frameworks(true) as $id => $name) {
            if ($id === (int)$frmselectedid) {
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
        if (!$frameworksids = array_keys($this->get_departments_framework())) {
            return [];
        }

        [$tenantsql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid', 'shared=1',
            $this->get_export_tenant_id() ?: tenancy::get_tenant_id());

        $sql = [];
        foreach ($frameworksids as $id) {
            $p1 = database::generate_param_name();
            $params[$p1] = '/' . $id .'/%';
            $sql[] = '(' . $DB->sql_like('path', ':' . $p1) . ')';
        }
        $result = $DB->get_records_select(department::TABLE,
            $tenantsql . ' AND (' . join(' OR ', $sql) . ')',
            $params, 'pathlevel', 'id,path,idnumber,parentid,name,description');

        if (empty($result)) {
            // For empty framework we need empty record, so that CSV header is populated.
            return [(object) [
                'id' => 0,
                'path' => '',
                'idnumber' => '',
                'parentid' => '',
                'name' => '',
                'description' => '',
            ], ];
        }
        return $result;
    }

    /**
     * Performs the export
     *
     * @return void
     */
    public function perform_export(): void {
        $frmselectedid = $this->get_export_setting(self::EXPORT_SELECT_FRAMEWORK);
        $excludefields = ['id', 'parentid'];
        $idnumbermap = [];

        $buildpath = function (string $path) use (&$idnumbermap, $frmselectedid): string {
            // Remove frameworkid from the path and replace elements with idnumbers where possible.
            $path = preg_replace("/^\/{$frmselectedid}\//", '', $path);
            $pathparts = explode('/', $path);
            foreach ($pathparts as $key => $pathpart) {
                if (!empty($idnumbermap[(int) $pathpart])) {
                    $pathparts[$key] = $idnumbermap[(int) $pathpart];
                }
            }
            return join('/', $pathparts);
        };

        foreach ($this->get_departments() as $record) {
            $idnumbermap[$record->id] = $record->idnumber;

            // Update fields to contain idnumbers.
            $record->parent = isset($idnumbermap[$record->parentid]) ? $idnumbermap[$record->parentid] : '';
            $record->path = $buildpath($record->path);

            $entityname = !empty($record->parentid) ? self::NAME_DEPARTMENT : self::NAME_DEPARTMENT_FRAMEWORK;
            $this->prepare_data_for_csv_export($entityname, (array)$record)
                ->store_instances_for_review()
                ->exclude_fields($excludefields)
                ->export();
        }
    }
}
