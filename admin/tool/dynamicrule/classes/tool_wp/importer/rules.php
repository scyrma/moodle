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
 * Dynamic rules importer.
 *
 * @package     tool_dynamicrule
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_wp\importer;

use tool_dynamicrule\output\tab_archivedrules;
use tool_dynamicrule\permission;
use tool_dynamicrule\api;
use tool_dynamicrule\rule;
use tool_dynamicrule\condition;
use tool_dynamicrule\condition_base;
use tool_dynamicrule\outcome;
use tool_dynamicrule\outcome_base;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\wp_imported_entity;

/**
 * Importer class for dynamic rules.
 *
 * @package     tool_dynamicrule
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class rules extends \tool_wp\importer_base {

    /** @var string Element for selecting what to import. The import type should be one of the class FILTER_ values. */
    const IMPORT_INSTANCES = 'import_instances';
    /** @var string Filter value for selecting all. */
    const IMPORT_INSTANCES_ALL = 'all';
    /** @var string Filter value for selecting specified. */
    const IMPORT_INSTANCES_SELECTED = 'selected';
    /** @var string Element for component. Needed to import chained dynamic rules. */
    const IMPORT_INSTANCES_COMPONENT = 'component';
    /** @var string Element for selecting if rule settings have to be importer. */
    const IMPORT_CONTENT = 'import_content';
    /** @var string Element for import selected rule ids. */
    const IMPORT_SELECT_RULES = 'select_rules';
    /** @var string Element for component. Needed to import chained dynamic rules. */
    const IMPORT_SELECT_COMPONENT = 'select_component';
    /** @var string Element for component area. Needed to import chained dynamic rules. */
    const IMPORT_SELECT_COMPONENT_AREA = 'select_component_area';
    /** @var string Element for component itemid. Needed to import chained dynamic rules. */
    const IMPORT_SELECT_COMPONENT_ITEMID = 'select_component_itemid';
    /** @var string Element for component mapper. Needed to map itemid during chained import.*/
    const IMPORT_SELECT_COMPONENT_MAPPER = 'select_component_mapper';

    /**
     * Importer format
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
     * Allows to mark importer as not available
     *
     * By default every importer is available for general import and not available for any entrypoint
     *
     * @return bool
     */
    public function is_available(): bool {
        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint()) && permission::can_create_rule();
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
        $this->register_entity(rule::TABLE, [
            self::ENTITY_DEPENDENCIES => ['tool_tenant'],
            self::ENTITY_LOGSUCCESS => function(int $id, array $details) {
                $a = (object) [
                    'name' => format_string($details['name']),
                    'conditionscount' => $details['conditionscount'],
                    'outcomescount' => $details['outcomescount'],
                ];

                // Don't include link to the rule if we are importing via component filter.
                if ($details['importtype'] == self::IMPORT_INSTANCES_COMPONENT) {
                    return get_string('importlogsuccess', 'tool_dynamicrule', $a);
                } else {
                    // An archived rule doesn't have a URL, so link to the archived rules tab.
                    $a->url = (empty($details['archived']) ?
                        new \moodle_url('/admin/tool/dynamicrule/rule.php', ['id' => $id]) :
                        new \moodle_url('/admin/tool/dynamicrule/index.php', null,
                            (new tab_archivedrules([]))->get_tab_id()))->out();

                    return get_string('importlogsuccesslink', 'tool_dynamicrule', $a);
                }
            },
            self::ENTITY_LOGERROR => function(array $details) {
                return get_string('importlogerror', 'tool_dynamicrule', format_string($details['name']));
            },
            self::ENTITY_NAMEPLURAL => get_string('pluginname', 'tool_dynamicrule'),
            self::ENTITY_INDIVIDUALIMPORT => function(array $ids, array $settings) {
                // What parameters to use when importing individual entity as part of some other import.
                // May be called from programs and certifications import.
                $defaults = [
                    self::IMPORT_INSTANCES => self::IMPORT_INSTANCES_SELECTED,
                    self::IMPORT_CONTENT => 1,
                    self::IMPORT_SELECT_COMPONENT => 0,
                    self::IMPORT_SELECT_COMPONENT_AREA => 0,
                    self::IMPORT_SELECT_COMPONENT_ITEMID => 0,
                    self::IMPORT_SELECT_COMPONENT_MAPPER => 0,
                ];

                $settings = array_intersect_key($settings, $defaults) + $defaults + [
                    self::IMPORT_SELECT_RULES => $ids,
                ];

                // If we are importing component rules, we require component/area/itemid/mapper to be specified.
                if ($settings[self::IMPORT_INSTANCES] == self::IMPORT_INSTANCES_COMPONENT && (
                        empty($settings[self::IMPORT_SELECT_COMPONENT]) ||
                        empty($settings[self::IMPORT_SELECT_COMPONENT_AREA]) ||
                        empty($settings[self::IMPORT_SELECT_COMPONENT_ITEMID]) ||
                        empty($settings[self::IMPORT_SELECT_COMPONENT_MAPPER])
                    )) {

                    throw new \coding_exception('Caller must specify component, area, itemid and mapper');
                }

                return $settings;
            },
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $logdetails, int $id) {
                return $logdetails['name'];
            },
        ]);

        $this->register_potential_error(rule::TABLE, 'invalidcondition', [
            self::ERROR_CONFLICTHEADER => get_string('importlogerrorinvalidcondition', 'tool_dynamicrule'),
            self::ERROR_LOG => get_string('importlogerrorinvalidcondition', 'tool_dynamicrule'),
        ]);

        $this->register_potential_error(rule::TABLE, 'invalidoutcome', [
            self::ERROR_CONFLICTHEADER => get_string('importlogerrorinvalidoutcome', 'tool_dynamicrule'),
            self::ERROR_LOG => get_string('importlogerrorinvalidoutcome', 'tool_dynamicrule'),
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
        $mform->addElement('header', 'content', get_string('content', 'tool_wp'));
        $mform->setExpanded('content');

        $settingsstr = get_string('exportsettings', 'tool_dynamicrule');
        $mform->addElement('advcheckbox', self::IMPORT_CONTENT, $settingsstr);
        $mform->setDefault(self::IMPORT_CONTENT, 1);
        $mform->addHelpButton(self::IMPORT_CONTENT, 'exportsettings', 'tool_dynamicrule');
        $form->freeze_at(self::IMPORT_CONTENT, 1);

        $mform->addElement('header', 'instances', get_string('instances', 'tool_wp'));
        $mform->setExpanded('instances');

        $mform->addElement('radio', self::IMPORT_INSTANCES, null,
            new \lang_string('importselectall', 'tool_dynamicrule'), self::IMPORT_INSTANCES_ALL);
        $mform->addElement('radio', self::IMPORT_INSTANCES, null,
            new \lang_string('importselectspecified', 'tool_dynamicrule'), self::IMPORT_INSTANCES_SELECTED);

        $mform->setType(self::IMPORT_INSTANCES, PARAM_ALPHA);
        $mform->setDefault(self::IMPORT_INSTANCES, self::IMPORT_INSTANCES_ALL);

        // Create elements to allow user to limit which rules to import, sorted by name.
        $rules = $this->get_entities_in_workplace_export_file(rule::TABLE, null, function(array $entity) {
            return $entity['name'];
        });

        $rulesselect = [];
        $rulesarchived = [];
        foreach ($rules as $rule) {
            $name = $rule->get_raw_field('name');
            if ((bool) $rule->get_raw_field('archived')) {
                $name = new \lang_string('ruleselectitemarchived', 'tool_dynamicrule', $name);
                $rulesarchived[$rule->get_original_id()] = $name;
                continue;
            }
            $rulesselect[$rule->get_original_id()] = $name;
        }
        // Move archived rules at the end of list.
        $rulesselect += $rulesarchived;

        $mform->addElement('autocomplete', self::IMPORT_SELECT_RULES, $this->get_name(), $rulesselect, ['multiple' => true])
            ->setHiddenLabel(true);
        $mform->setType(self::IMPORT_SELECT_RULES, PARAM_INT);
        $mform->hideIf(self::IMPORT_SELECT_RULES, self::IMPORT_INSTANCES, 'neq', self::IMPORT_INSTANCES_SELECTED);

        $form->add_validation_callback([$this, 'validate_options_form']);
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
        $settingsstr = get_string('exportsettings', 'tool_dynamicrule');
        $settings[] = ['name' => $settingsstr, 'value' => !empty($data[self::IMPORT_CONTENT])];

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
        if ($c1 = $this->get_entities_in_workplace_export_file(rule::TABLE)->count()) {
            $rv[] = get_string('pluginname', 'tool_dynamicrule') .
                get_string('entitiescountpostfix', 'tool_wp', $c1);
        }
        return $rv;
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

        if (empty($data[self::IMPORT_INSTANCES])) {
            $errors[self::IMPORT_INSTANCES] = new \lang_string('required');
        } else if ($data[self::IMPORT_INSTANCES] === self::IMPORT_INSTANCES_SELECTED &&
                empty($data[self::IMPORT_SELECT_RULES])) {
            $errors[self::IMPORT_SELECT_RULES] = new \lang_string('required');
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
        if (!in_array($entity, $this->get_entities())) {
            return;
        }

        $settingfilterrules = $this->get_import_setting(self::IMPORT_INSTANCES);

        if ($settingfilterrules != self::IMPORT_INSTANCES_COMPONENT) {
            // Import comes from main UI. Filter by entity id.
            // Set filter if we are importing specific rules.
            $filter = ($settingfilterrules === self::IMPORT_INSTANCES_ALL) ?
                [] : $this->get_import_setting(self::IMPORT_SELECT_RULES);

            $rules = $this->get_entities_in_workplace_export_file(rule::TABLE, function(array $entity) use ($filter) {
                return (empty($filter) || in_array($entity['id'], $filter));
            });
        } else {
            // Import comes from component (chaining). Filter by component/area/itemid.
            $settings = $this->get_import_settings();
            $rules = $this->get_entities_in_workplace_export_file(rule::TABLE, function(array $entity) use ($settings) {
                return $entity['component'] == $settings[self::IMPORT_SELECT_COMPONENT] &&
                    $entity['componentarea'] == $settings[self::IMPORT_SELECT_COMPONENT_AREA] &&
                    $entity['itemid'] == $settings[self::IMPORT_SELECT_COMPONENT_ITEMID];
            });
        }

        foreach ($rules as $rule) {
            $rule
                ->add_mapping('tenantid', 'tool_tenant')
                ->set_validation_callback(function(array $record, wp_imported_entity $rule): void {
                    $this->add_details_to_log(['name' => $record['name']]);

                    // Validate conditions, class must be valid and user must be able to add it.
                    $conditions = $rule->get_nested_entities(condition::TABLE);
                    foreach ($conditions as $condition) {
                        $conditioninstance = condition_base::instance(0,
                            (object) array_intersect_key($condition, condition::properties_definition()));

                        // When at least one condition can't be imported, we do not import the whole rule. The "invalidcondition"
                        // error does not have any other conflict resolution options except for "Skip the whole rule".
                        if (!$conditioninstance || !$conditioninstance->user_can_add()) {
                            $this->add_error_to_log('invalidcondition');
                        }
                    }

                    // Validate outcomes, class name must be valid and user must be able to add it.
                    $outcomes = $rule->get_nested_entities(outcome::TABLE);
                    foreach ($outcomes as $outcome) {
                        // Accept old tool_certificate outcomes. Update them later.
                        if ($outcome['classname'] == 'tool_certificate\tool_dynamicrule\outcome\certificate') {
                            continue;
                        }
                        $outcomeinstance = outcome_base::instance(0,
                            (object) array_intersect_key($outcome, outcome::properties_definition()));

                        // When at least one outcome can't be imported, we do not import the whole rule. The "invalidoutcome"
                        // error does not have any other conflict resolution options except for "Skip the whole rule".
                        if (!$outcomeinstance || !$outcomeinstance->user_can_add()) {
                            $this->add_error_to_log('invalidoutcome');
                        }
                    }
                })
                ->set_import_callback(function(array $data, wp_imported_entity $rule): int {
                    // If import type is component we must apply new entity id (ex. program id) in itemid.
                    $importtype = $this->get_import_setting(self::IMPORT_INSTANCES);
                    if ($importtype == self::IMPORT_INSTANCES_COMPONENT) {
                        $entitymapper = $this->get_import_setting(self::IMPORT_SELECT_COMPONENT_MAPPER);
                        $data['itemid'] = $this->get_mapping($entitymapper, $data['itemid']);
                    } else {
                        // We need to re-check user can still create rules and hasn't gone over any configured limits.
                        permission::require_can_create_rule();
                    }

                    $newrule = api::create_rule((object) $data);

                    $this->add_details_to_log([
                        'name' => $newrule->get('name'),
                        'archived' => $newrule->get('archived'),
                        'importtype' => $importtype,
                    ]);

                    // Conditions.
                    $conditions = $rule->get_nested_entities(condition::TABLE);
                    foreach ($conditions as $condition) {
                        $condition['ruleid'] = $newrule->get('id');
                        $conditioninstance = condition_base::instance(0,
                            (object) array_intersect_key($condition, condition::properties_definition()));
                        $conditioninstance->get_importer_mapping($this);

                        // After applying field mappings to the instance, we need to ensure user can still edit it.
                        $configdata = $conditioninstance->get_configdata();
                        if (!$conditioninstance->is_configuration_valid() || !$conditioninstance->user_can_edit($configdata)) {
                            $configdata = [];
                        }

                        api::create_rule_condition($newrule->get('id'), get_class($conditioninstance), $configdata, true);
                    }
                    $this->add_details_to_log(['conditionscount' => count($conditions)]);

                    // Outcomes.
                    $outcomes = $rule->get_nested_entities(outcome::TABLE);
                    foreach ($outcomes as $outcome) {
                        $outcome['ruleid'] = $newrule->get('id');
                        // Update old tool_certificate outcomes to tool_dynamicrule.
                        if ($outcome['classname'] == 'tool_certificate\tool_dynamicrule\outcome\certificate') {
                            $outcome['classname'] = 'tool_dynamicrule\tool_dynamicrule\outcome\certificate';
                            $configdata = @json_decode($outcome['configdata'], true);
                            $configdata['instanceclass'] = 'tool_dynamicrule:certificate';
                            $outcome['configdata'] = json_encode($configdata);
                        }
                        $outcomeinstance = outcome_base::instance(0,
                            (object) array_intersect_key($outcome, outcome::properties_definition()));
                        $outcomeinstance->get_importer_mapping($this);

                        // After applying field mappings to the instance, we need to ensure user can still edit it.
                        $configdata = $outcomeinstance->get_configdata();
                        if (!$outcomeinstance->is_configuration_valid() || !$outcomeinstance->user_can_edit($configdata)) {
                            $configdata = [];
                        }

                        api::create_rule_outcome($newrule->get('id'), get_class($outcomeinstance), $configdata, true);
                    }
                    $this->add_details_to_log(['outcomescount' => count($outcomes)]);

                    return $newrule->get('id');
                })
                ->import($this);
        }
    }
}
