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

declare(strict_types=1);

namespace tool_dynamicrule\reportbuilder\local\systemreports;

use lang_string;
use moodle_url;
use stdClass;
use core_reportbuilder\system_report;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\action;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use tool_dynamicrule\api;
use tool_dynamicrule\condition;
use tool_dynamicrule\outcome;
use tool_dynamicrule\permission;
use tool_dynamicrule\rule;
use tool_dynamicrule\reportbuilder\local\filters\condition as condition_filter;
use tool_dynamicrule\reportbuilder\local\filters\outcome as outcome_filter;
use tool_tenant\hierarchy;

/**
 * System report class to define rules listing report
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class rules extends system_report {

    /** @var rule current rule (calculated for each row in the row_callback) */
    protected $currentrule = null;
    /** @var bool is current rule event based? (calculated for each row in the row_callback) */
    protected $iscurrentrulescheduledtask = null;

    /**
     * Initialise report.
     */
    public function initialise(): void {
        $this->set_main_table(rule::TABLE, 'r');

        // Tenant condition.
        [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('r.tenantid', 'r.shared=1');
        $this->add_base_condition_sql($sql, $params);

        $showarchived = $this->get_parameter('archived', false, PARAM_BOOL);
        // When parameter 'readonly' is true report shows only enabled rules without the edit and the enable/disable buttons.
        // If this parameter is not sent will be false by default.
        $readonly = $this->get_parameter('readonly', false, PARAM_BOOL);
        $this->add_base_condition_simple('r.archived', $showarchived ? 1 : 0);
        if ($readonly) {
            $this->add_base_condition_simple('r.enabled', 1);
        }
        $this->set_downloadable(false);

        // Add all fields needed for actions and for row_callback (except usermodified).
        $this->add_base_fields('r.'.join(', r.', array_diff(array_keys(rule::properties_definition()), ['usermodified'])));
        $this->add_base_fields('\'\' AS title');

        // Add subqueries for rules inside the component or rules without component respectively.
        $component = $this->get_parameter('component', null, PARAM_COMPONENT);
        $componentarea = $this->get_parameter('componentarea', null, PARAM_ALPHANUMEXT);
        $itemid = $this->get_parameter('itemid', 0, PARAM_INT);
        if ($component) {
            $p1 = database::generate_param_name();
            $p2 = database::generate_param_name();
            $p3 = database::generate_param_name();
            $this->add_base_condition_sql("r.component = :{$p1} AND r.componentarea = :{$p2} AND r.itemid = :{$p3}",
                [$p1 => $component, $p2 => $componentarea, $p3 => $itemid]);
        } else {
            $this->add_base_condition_sql("r.component IS NULL");
        }

        // Sortable columns are available.
        $isavailableandsortable = empty($component);

        // Add columns.
        $this->annotate_entity('rule', new \lang_string('pluginname', 'tool_dynamicrule'));

        $this->add_column((new column(
            'enabled',
            null,
            'rule'
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_fields('r.id')
            ->set_is_sortable(false)
            ->set_is_available(!($showarchived || $readonly))
            ->add_callback([$this, 'get_enabled']));

        $this->add_column((new column(
            'name',
            new \lang_string('name'),
            'rule'
        ))
            ->set_type(column::TYPE_TEXT)
            ->add_fields('r.name')
            ->set_is_available($isavailableandsortable)
            ->set_is_sortable($isavailableandsortable)
            ->add_callback([$this, 'get_display_name']));

        $this->add_column((new column(
            'conditions',
            new \lang_string('conditions', 'tool_dynamicrule'),
            'rule'
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_fields('r.id')
            ->set_is_sortable(false)
            ->add_callback([$this, 'get_display_conditions']));

        $this->add_column((new column(
            'actions',
            new \lang_string('outcomes', 'tool_dynamicrule'),
            'rule'
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_fields('r.id')
            ->set_is_sortable(false)
            ->add_callback([$this, 'get_display_actions']));

        $this->add_column((new column(
            'timecreated',
            new \lang_string('timecreated', 'tool_dynamicrule'),
            'rule'
        ))
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field('r.timecreated')
            ->set_is_available($isavailableandsortable)
            ->set_is_sortable($isavailableandsortable)
            ->add_callback([format::class, 'userdate'], get_string('strftimedatefullshort', 'core_langconfig'))
        );

        $this->add_filters();

        if (!$readonly) {
            $this->add_actions();
        }

        // When the sortable columns are available, add the default sort.
        // This will hide the "Reset table preferences" button when the sorting cannot be changed.
        if ($isavailableandsortable) {
            $this->set_initial_sort_column('rule:timecreated', SORT_DESC);
        }
    }

    /**
     * Adds filters to report, note they are only available when viewing the list of user-created rules
     */
    public function add_filters(): void {
        $component = $this->get_parameter('component', null, PARAM_COMPONENT);
        $showarchived = $this->get_parameter('archived', false, PARAM_BOOL);

        // Filter for rule enabled (not available on the archived rules tab).
        $this->add_filter((new filter(
            boolean_select::class,
            'enabled',
            new \lang_string('enabled', 'tool_dynamicrule'),
            'rule'
        ))
            ->set_field_sql('r.enabled')
            ->set_is_available(!$showarchived && empty($component)));

        // Filter for rule name.
        $this->add_filter((new filter(
            text::class,
            'rulename',
            new \lang_string('filterrulename', 'tool_dynamicrule'),
            'rule'
        ))
            ->set_field_sql('r.name')
            ->set_is_available(empty($component)));

        // Filter for conditions.
        $this->add_filter((new filter(
            condition_filter::class,
            'conditions',
            new \lang_string('conditions', 'tool_dynamicrule'),
            'rule'
        ))
            ->set_field_sql('r.id')
            ->set_is_available(empty($component)));

        // Filter for actions.
        $this->add_filter((new filter(
            outcome_filter::class,
            'actions',
            new \lang_string('outcomes', 'tool_dynamicrule'),
            'rule'
        ))
            ->set_field_sql('r.id')
            ->set_is_available(empty($component)));

        // Filter for time created.
        $this->add_filter((new filter(
            date::class,
            'timecreated',
            new \lang_string('timecreated', 'tool_dynamicrule'),
            'rule'
        ))
            ->set_field_sql('r.timecreated')
            ->set_is_available(empty($component))
            ->set_limited_operators([
                date::DATE_ANY,
                date::DATE_RANGE,
                date::DATE_PREVIOUS,
                date::DATE_CURRENT,
            ]));
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * This is necessary to implement here and not on the page that embeds the system report
     * because second and consequtive pages of the report are rendered via web services.
     *
     * To retrieve parameters values call $this->get_parameter()
     */
    public function can_view(): bool {
        $component = $this->get_parameter('component', null, PARAM_COMPONENT);
        if ($component) {
            $componentarea = $this->get_parameter('componentarea', null, PARAM_ALPHANUMEXT);
            $itemid = $this->get_parameter('itemid', 0, PARAM_INT);
            return component_callback($component, 'can_view_dynamic_rules', [$componentarea, $itemid]);
        }
        $showarchived = $this->get_parameter('archived', false, PARAM_BOOL);
        if ($showarchived) {
            return permission::can_view_archived_rules_list();
        } else {
            return permission::can_view_rules_list();
        }
    }

    /**
     * Verify rule data prior to row rendering.
     *
     * @param \stdClass $row
     */
    public function row_callback(\stdClass $row): void {
        $this->currentrule = new rule(0, $row);
        if (!api::is_rule_configuration_valid($this->currentrule) ||
            !condition::count_records(['ruleid' => $this->currentrule->get('id')]) ||
            !outcome::count_records(['ruleid' => $this->currentrule->get('id')])) {
            // Update current rule instance.
            $this->currentrule->set('enabled', 0);
        }
        $this->iscurrentrulescheduledtask = api::show_rule_scheduled_task_badge($this->currentrule);
    }

    /**
     * Get enabled switch
     *
     * @param mixed $value
     * @param \stdClass $row
     * @param mixed $additionalarguments
     * @return mixed
     */
    public function get_enabled($value, \stdClass $row, $additionalarguments) {
        global $OUTPUT;

        $rule = $this->currentrule;

        $attributes = [
            ['name' => 'id', 'value' => $row->id],
            ['name' => 'action', 'value' => 'rule-toggle'],
            ['name' => 'state', 'value' => $rule->is_enabled()],
        ];
        $label = $rule->is_enabled() ? get_string('disablerulemsg', 'tool_dynamicrule')
            : get_string('enablerulemsg', 'tool_dynamicrule');

        $templateparams = [
            'id' => 'rule-toggle-' . $row->id,
            'checked' => $rule->is_enabled(),
            'dataattributes' => $attributes,
            'label' => $label,
            'labelclasses' => 'sr-only'
        ];

        if (!permission::can_enable_rule($rule)) {
            $formattedname = $rule->get_formatted_name();
            // Check Rule is enabled or disabled, however it will not have editable action for the rule here.
            if ($rule->is_enabled()) {
                // Add title not allow to disable this rule.
                if ($rule->is_shared() && !\tool_tenant\sharedspace::is_shared_space()) {
                    $title = get_string('cannotdisablesharedrule', 'tool_dynamicrule', $formattedname);
                } else {
                    $title = get_string('cannotdisablerule', 'tool_dynamicrule', $formattedname);
                }
            } else {
                if ($rule->is_shared() && !\tool_tenant\sharedspace::is_shared_space()) {
                    $title = get_string('cannotenablesharedrule', 'tool_dynamicrule', $formattedname);
                } else if ($this->get_parameter('component', null, PARAM_COMPONENT)) {
                    $title = get_string('cannotenablecomponentrule', 'tool_dynamicrule', $formattedname);
                } else {
                    $title = get_string('cannotenablerule', 'tool_dynamicrule', $formattedname);
                }
            }
            $templateparams['title'] = $title;
            $templateparams['label'] = $title;
            $templateparams['disabled'] = 1;
        }

        return $OUTPUT->render_from_template('core/toggle', $templateparams);
    }

    /**
     * Callback for the name display
     *
     * @param string $value
     * @param \stdClass $row
     * @param mixed $additionalarguments
     * @return string
     */
    public function get_display_name($value, \stdClass $row, $additionalarguments) {
        global $PAGE;
        $issharedspacerule = in_array($this->currentrule->get('tenantid'), hierarchy::get_parent_tenants_ids());
        $content = (\tool_dynamicrule\api::get_name_inplace_editable($this->currentrule))->render(
            $PAGE->get_renderer('core'));
        if ($this->iscurrentrulescheduledtask) {
            $content .= $issharedspacerule ? ' ' : '';
            $content .= \html_writer::span(get_string('scheduledtask', 'tool_dynamicrule'), 'badge badge-pill badge-secondary');
        }
        // Display the "Shared space" badge if applicable.
        if ($issharedspacerule) {
            $content .= ' ' . \html_writer::span(get_string('sharedspace', 'tool_tenant'), 'badge badge-pill badge-secondary');
        }
        return $content;
    }

    /**
     * Callback for the conditions display
     *
     * @param int $ruleid
     * @return string
     */
    public function get_display_conditions(int $ruleid): string {
        global $OUTPUT;
        $context = new \tool_dynamicrule\output\rules_list_item_conditions_description($ruleid);
        $content = $OUTPUT->render_from_template('tool_dynamicrule/rules_list_item_description',
            $context->export_for_template($OUTPUT));
        // Show 'Scheduled task' badge in conditions column for component Dynamic rules tabs.
        if (!empty($this->get_parameter('component', null, PARAM_COMPONENT)) && $this->iscurrentrulescheduledtask) {
            $content .= \html_writer::span(get_string('scheduledtask', 'tool_dynamicrule'), 'badge badge-pill badge-secondary');
        }
        return $content;
    }

    /**
     * Callback for the actions display
     *
     * @param int $ruleid
     * @return string
     */
    public function get_display_actions(int $ruleid): string {
        global $OUTPUT;
        $context = new \tool_dynamicrule\output\rules_list_item_outcomes_description($ruleid);
        return $OUTPUT->render_from_template('tool_dynamicrule/rules_list_item_description',
            $context->export_for_template($OUTPUT));
    }

    /**
     * Add actions
     */
    protected function add_actions() {
        global $CFG;

        // Add edit action icon (used in plugins).
        $this->add_action((new action(
            new moodle_url('#'),
            new \pix_icon('i/settings', '', 'core'),
            ['data-action' => 'editactions', 'data-id' => ':id'],
            false,
            new lang_string('ruleeditactions', 'tool_dynamicrule')
        ))
            ->add_callback(static function(stdClass $row): bool {
                return static::action_callback($row, 'editactions', false);
            })
        );

        // Add duplicate icon.
        $attr = ['data-rulename' => ':name', 'data-action' => 'duplicate', 'data-id' => ':id'];
        if (permission::is_site_limit_reached()) {
            $attr['data-limitreached'] = 0;
        } else if (permission::is_tenant_limit_reached()) {
            $attr['data-limitreached'] = $CFG->tool_dynamicrule_tenantlimit;
        }
        $this->add_action((new action(
            new moodle_url('#'),
            new \pix_icon('e/manage_files', '', 'core'),
            $attr,
            false,
            new lang_string('duplicate')
        ))
            ->add_callback(static function(stdClass $row): bool {
                return static::action_callback($row, 'duplicaterule', false);
            })
        );

        // Add archive icon.
        $this->add_action((new action(
            new moodle_url('#'),
            new \pix_icon('archive', '', 'tool_wp'),
            ['data-rulename' => ':name', 'data-action' => 'archive', 'data-id' => ':id'],
            false,
            new lang_string('rulearchive', 'tool_dynamicrule')
        ))
            ->add_callback(static function(stdClass $row): bool {
                return static::action_callback($row, 'archiverule', false);
            })
        );

        // Add unarchive icon.
        $this->add_action((new action(
            new moodle_url('#'),
            new \pix_icon('restorearchived', '', 'tool_wp'),
            ['data-rulename' => ':name', 'data-action' => 'unarchive', 'data-id' => ':id'],
            false,
            new lang_string('ruleunarchive', 'tool_dynamicrule')
        ))
            ->add_callback(static function(stdClass $row): bool {
                return static::action_callback($row, 'unarchiverule', true);
            })
        );

        // Add delete icon.
        $this->add_action((new action(
            new moodle_url('#'),
            new \pix_icon('i/trash', '', 'core'),
            ['data-rulename' => ':name', 'data-action' => 'delete', 'data-id' => ':id'],
            false,
            new lang_string('delete')
        ))
            ->add_callback(static function(stdClass $row): bool {
                return static::action_callback($row, 'deleterule', true);
            })
        );

        // Add divider.
        $this->add_action_divider();

        // Add report icon.
        $this->add_action((new action(
            new moodle_url('/admin/tool/dynamicrule/report.php', ['id' => ':id']),
            new \pix_icon('bar-chart', '', 'tool_wp'),
            ['data-action' => 'report', 'data-id' => ':id'],
            false,
            new lang_string('ruleviewreport', 'tool_dynamicrule')
        ))
            ->add_callback(static function(stdClass $row): bool {
                return static::action_callback($row, 'viewreport');
            })
        );
    }

    /**
     * Callback for actions
     *
     * @param stdClass $row
     * @param string $stringkey
     * @param bool|null $archived
     * @return bool
     */
    public static function action_callback(stdClass $row, string $stringkey, ?bool $archived = null): bool {
        $rule = new rule(0, $row);

        // Format rule name property for those actions that require it.
        $row->name = $rule->get_formatted_name();

        $component = $rule->get('component');
        if ($stringkey === 'editactions') {
            return $component && permission::can_edit_rule_outcomes($rule);
        } else if ($stringkey === 'viewreport') {
            // TODO Show inside component rules (programs/certifications) WP-2482.
            return !$component && permission::can_view_matched_users_report($rule);
        } else if ($stringkey === 'duplicaterule') {
            return permission::can_duplicate_rule($rule, true);
        } else if ($stringkey === 'archiverule') {
            return permission::can_archive_rule($rule);
        } else if ($stringkey === 'unarchiverule') {
            return permission::can_restore_rule($rule);
        } else if ($stringkey === 'deleterule') {
            return permission::can_delete_rule($rule);
        } else {
            return false;
        }
    }

    /**
     * Row class
     *
     * @param \stdClass $row
     * @return string
     */
    public function get_row_class(\stdClass $row): string {
        return $row->enabled ? '' : 'text-muted';
    }
}
