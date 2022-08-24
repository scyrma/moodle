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
 * Dynamic rules exporter.
 *
 * @package     tool_dynamicrule
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_wp\exporter;

use tool_dynamicrule\condition_base;
use tool_dynamicrule\permission;
use tool_dynamicrule\rule;
use tool_dynamicrule\condition;
use tool_dynamicrule\outcome;
use tool_dynamicrule\outcome_base;
use tool_tenant\tenancy;
use tool_wp\local\exportimport\forms\export_settings_form;
use tool_wp\local\exportimport\helper;

/**
 * Exporter class for dynamic rules.
 *
 * @package     tool_dynamicrule
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class rules extends \tool_wp\exporter_base {

    /** @var string Element for selecting what to export. The export type should be one of the class FILTER_ values. */
    const EXPORT_INSTANCES = 'export_instances';
    /** @var string Filter value for selecting active. */
    const EXPORT_INSTANCES_ACTIVE = 'active';
    /** @var string Filter value for selecting enabled rules. */
    const EXPORT_INSTANCES_ENABLED = 'enabled';
    /** @var string Filter value for selecting all. */
    const EXPORT_INSTANCES_ALL = 'all';
    /** @var string Filter value for selecting rules from a chained component. */
    const EXPORT_INSTANCES_COMPONENT = 'component';
    /** @var string Element for selecting if rule settings have to be exported. */
    const EXPORT_CONTENT = 'export_content';
    /** @var string Element for component. Needed for identifying chained dynamic rules. */
    const EXPORT_SELECT_COMPONENT = 'select_component';
    /** @var string Element for component area. Needed for identifying chained dynamic rules. */
    const EXPORT_SELECT_COMPONENT_AREA = 'select_component_area';
    /** @var string Element for component itemid. Needed for identifying chained dynamic rules. */
    const EXPORT_SELECT_COMPONENT_ITEMID = 'select_component_itemid';

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
        return get_string('pluginname', 'tool_dynamicrule');
    }

    /**
     * Exporter description to show in the list of available exporters
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('exporterdescription', 'tool_dynamicrule');
    }

    /**
     * Exporter icon url
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('icon', 'tool_dynamicrule')->out(false);
    }

    /**
     * Allows to mark exporter as not available, checks capabilities and entry point
     *
     * @return bool
     */
    public function is_available(): bool {
        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint()) && permission::can_create_rule(true);
    }

    /**
     * Initialise the class, register entities that can be exported from other places
     */
    protected function initialise() {
        $this->register_entity('tool_dynamicrule', [
            self::ENTITY_INDIVIDUALEXPORT => function(array $ids, array $settings) {
                // What parameters to use when exporting individual entity as part of some other export.
                // May be called from programs and certifications export.
                $defaults = [
                    self::EXPORT_INSTANCES => self::EXPORT_INSTANCES_ALL,
                    self::EXPORT_CONTENT => 1,
                    self::EXPORT_SELECT_COMPONENT => 0,
                    self::EXPORT_SELECT_COMPONENT_AREA => 0,
                    self::EXPORT_SELECT_COMPONENT_ITEMID => 0,
                ];

                $settings = array_intersect_key($settings, $defaults) + $defaults;

                // If we are exporting component rules, we require component/area/itemid to be specified.
                if ($settings[self::EXPORT_INSTANCES] == self::EXPORT_INSTANCES_COMPONENT && (
                        empty($settings[self::EXPORT_SELECT_COMPONENT]) ||
                        empty($settings[self::EXPORT_SELECT_COMPONENT_AREA]) ||
                        empty($settings[self::EXPORT_SELECT_COMPONENT_ITEMID])
                    )) {

                    throw new \coding_exception('Caller must specify component, area and itemid');
                }

                return $settings;
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                return $record['name'];
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
        $mform->addElement('header', 'content', get_string('content', 'tool_wp'));
        $mform->setExpanded('content');

        $settingsstr = get_string('exportsettings', 'tool_dynamicrule');
        $mform->addElement('advcheckbox', self::EXPORT_CONTENT, $settingsstr);
        $mform->setDefault(self::EXPORT_CONTENT, 1);
        $mform->addHelpButton(self::EXPORT_CONTENT, 'exportsettings', 'tool_dynamicrule');
        $form->freeze_at(self::EXPORT_CONTENT, 1);

        $mform->addElement('header', 'instances', get_string('instances', 'tool_wp'));
        $mform->setExpanded('instances');

        $mform->addElement('radio', self::EXPORT_INSTANCES, null,
            new \lang_string('exportselectactive', 'tool_dynamicrule'), self::EXPORT_INSTANCES_ACTIVE);
        $mform->addElement('radio', self::EXPORT_INSTANCES, null,
            new \lang_string('exportselectenabled', 'tool_dynamicrule'), self::EXPORT_INSTANCES_ENABLED);
        $mform->addElement('radio', self::EXPORT_INSTANCES, null,
            new \lang_string('exportselectall', 'tool_dynamicrule'), self::EXPORT_INSTANCES_ALL);

        $mform->setType(self::EXPORT_INSTANCES, PARAM_ALPHA);
        $mform->setDefault(self::EXPORT_INSTANCES, self::EXPORT_INSTANCES_ACTIVE);

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
        $settingsstr = get_string('exportsettings', 'tool_dynamicrule');
        $settings[] = ['name' => $settingsstr, 'value' => !empty($data[self::EXPORT_CONTENT])];

        return $OUTPUT->render_from_template(
            'tool_wp/exportimport_summary',
            ['settings' => $settings]
        );
    }

    /**
     * Returns the list of entities that will be exported
     *
     * Implement also {@see self::get_summary_for_review_step()}
     *
     * @param string $entityname
     * @return array array where each element is array that can be passed through self::ENTITY_INSTANCENAME_FOR_REVIEW
     *     callback
     */
    public function get_instances_for_review_step(string $entityname): array {
        if ($entityname === 'tool_dynamicrule') {
            return array_map(function(rule $rule) {
                return $rule->to_record();
            }, $this->get_rules_to_export($this->get_export_settings()));
        }
        return [];
    }

    /**
     * Returns list of rules selected to export
     *
     * @param array $data
     * @return rule[]
     */
    public function get_rules_to_export(array $data): array {
        $params = ['tenantid' => $this->get_export_tenant_id() ?: tenancy::get_tenant_id()];

        // When exporting directly, match records where component is NULL. For chained exports, match given component data.
        if ($data[self::EXPORT_INSTANCES] != self::EXPORT_INSTANCES_COMPONENT) {
            $params['component'] = null;

            if ($data[self::EXPORT_INSTANCES] === self::EXPORT_INSTANCES_ACTIVE) {
                $params['archived'] = 0;
            } else if ($data[self::EXPORT_INSTANCES] === self::EXPORT_INSTANCES_ENABLED) {
                $params['enabled'] = 1;
            }
        } else {
            $params = array_merge($params, [
                'component' => $data[self::EXPORT_SELECT_COMPONENT],
                'componentarea' => $data[self::EXPORT_SELECT_COMPONENT_AREA],
                'itemid' => $data[self::EXPORT_SELECT_COMPONENT_ITEMID],
            ]);
        }

        return rule::get_records($params, 'name');
    }

    /**
     * Export form element validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validate_options_form(array $data, array $files): array {
        $errors = [];

        if (empty($data[self::EXPORT_INSTANCES])) {
            $errors[self::EXPORT_INSTANCES] = new \lang_string('required');
        }

        return $errors;
    }

    /**
     * Performs the export
     *
     * @return void
     */
    public function perform_export(): void {
        $data = $this->get_export_settings();
        $rules = $this->get_rules_to_export($data);

        foreach ($rules as $rule) {
            $params = ['ruleid' => $rule->get('id')];
            $excludefields = ['timecreated', 'timemodified', 'broken', 'enabled'];

            // If it is a component rule, only export rule if it has actions defined.
            $outcomerecords = outcome::get_records($params);
            if (!empty($data[self::EXPORT_SELECT_COMPONENT]) && (count($outcomerecords) === 0)) {
                continue;
            }

            // User must be able to edit all of the rules conditions and outcomes.
            if (!permission::can_edit_all_rule_conditions($rule) || !permission::can_edit_all_rule_outcomes($rule)) {
                continue;
            }

            // Prepare rule export.
            $export = $this->prepare_data_for_workplace_export(rule::TABLE, (array) $rule->to_record())
                ->exclude_fields($excludefields)
                ->add_mappings('tenantid', 'tool_tenant');

            // Rule conditions.
            $conditions = helper::persistents_to_array(condition::get_records($params));
            $export->add_nested_entities(condition::TABLE, $conditions, $excludefields);
            foreach ($conditions as $condition) {
                // Allow each condition to add field mapping to the export.
                if ($conditioninstance = condition_base::instance(0, (object) $condition)) {
                    $conditioninstance->add_exporter_mapping($this);
                }
            }

            // Rule outcomes.
            $outcomes = helper::persistents_to_array($outcomerecords);
            $export->add_nested_entities(outcome::TABLE, $outcomes, $excludefields);
            foreach ($outcomes as $outcome) {
                // Allow each outcome to add field mapping to the export.
                if ($outcomeinstance = outcome_base::instance(0, (object) $outcome)) {
                    $outcomeinstance->add_exporter_mapping($this);
                }
            }

            $export->export();
        }
    }
}
