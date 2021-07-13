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
 * Class rules_list
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule;

use tool_reportbuilder\local\filter\checkbox;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\report_action;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\system_report;
use tool_tenant\tenancy;
use tool_wp\db;

defined('MOODLE_INTERNAL') || die();

/**
 * Class rules_list
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class rules_list extends system_report {

    /** @var rule current rule (calculated for each row in the row_callback) */
    protected $currentrule = null;
    /** @var bool is current rule event based? (calculated for each row in the row_callback) */
    protected $iscurrentrulescheduledtask = null;

    /**
     * Initialise report.
     */
    public function initialise() {
        $this->set_main_table(rule::TABLE, 'r', false);
        $this->add_base_condition_simple('r.tenantid', tenancy::get_tenant_id());
        $showarchived = $this->get_parameter('archived', false, PARAM_BOOL);
        // When parameter 'readonly' is true report shows only enabled rules without the edit and the enable/disable buttons.
        // If this parameter is not sent will be false by default.
        $readonly = $this->get_parameter('readonly', false, PARAM_BOOL);
        $this->add_base_condition_simple('r.archived', $showarchived ? 1 : 0);
        if ($readonly) {
            $this->add_base_condition_simple('r.enabled', 1);
        }
        $this->set_downloadable(false);
        $this->set_attributes(['class' => 'dynamicrules-list']);
        // Add all fields needed for actions and for row_callback (except usermodified).
        $this->add_base_fields('r.'.join(', r.', array_diff(array_keys(rule::properties_definition()), ['usermodified'])));
        $this->add_base_fields('\'\' AS title');

        // Add subqueries for rules inside the component or rules without component respectively.
        $component = $this->get_parameter('component', null, PARAM_COMPONENT);
        $componentarea = $this->get_parameter('componentarea', null, PARAM_ALPHANUMEXT);
        $itemid = $this->get_parameter('itemid', 0, PARAM_INT);
        if ($component) {
            $p1 = db::generate_param_name();
            $p2 = db::generate_param_name();
            $p3 = db::generate_param_name();
            $this->add_base_condition_sql("r.component = :{$p1} AND r.componentarea = :{$p2} AND r.itemid = :{$p3}",
                [$p1 => $component, $p2 => $componentarea, $p3 => $itemid]);
        } else {
            $this->add_base_condition_sql("r.component IS NULL");
        }

        // Add columns.
        $this->annotate_entity('rule', new \lang_string('pluginname', 'tool_dynamicrule'));

        $this->add_column((new report_column(
            'enabled',
            null,
            'rule'
        ))
            ->add_fields('r.id')
            ->set_is_default(true)
            ->set_is_available(!($showarchived || $readonly))
            ->add_attributes(['class' => 'wp-toggle'])
            ->add_callback([$this, 'get_enabled']));

        $this->add_column((new report_column(
            'name',
            new \lang_string('name'),
            'rule'
        ))
            ->add_fields('r.name')
            ->set_is_default(true)
            ->set_is_available(empty($component))
            ->set_is_sortable(true, true)
            ->add_callback([$this, 'get_display_name']));

        $this->add_column((new report_column(
            'conditions',
            new \lang_string('conditions', 'tool_dynamicrule'),
            'rule'
        ))
            ->add_fields('r.id')
            ->set_is_default(true)
            ->add_callback([$this, 'get_display_conditions']));

        $this->add_column((new report_column(
            'actions',
            new \lang_string('outcomes', 'tool_dynamicrule'),
            'rule'
        ))
            ->add_fields('r.id')
            ->set_is_default(true)
            ->add_callback([$this, 'get_display_actions']));

        $this->add_filters();

        if (!$readonly) {
            $this->add_actions();
        }
    }

    /**
     * Adds filters to report, note they are only available when viewing the list of user-created rules
     */
    public function add_filters(): void {
        $component = $this->get_parameter('component', null, PARAM_COMPONENT);
        $showarchived = $this->get_parameter('archived', false, PARAM_BOOL);

        // Filter for rule enabled (not available on the archived rules tab).
        $this->add_filter((new report_filter(
            checkbox::class,
            'enabled',
            new \lang_string('enabled', 'tool_dynamicrule'),
            'rule'
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql('r.enabled')
            ->set_is_available(!$showarchived && empty($component))
            ->set_is_default(true));

        // Filter for rule name.
        $this->add_filter((new report_filter(
            text::class,
            'rulename',
            new \lang_string('filterrulename', 'tool_dynamicrule'),
            'rule'
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql('r.name')
            ->set_is_available(empty($component))
            ->set_is_default(true));

        // Filter for conditions.
        $this->add_filter((new report_filter(
            select::class,
            'conditions',
            new \lang_string('conditions', 'tool_dynamicrule'),
            'rule'
        ))
            ->add_join('INNER JOIN {tool_dynamicrule_condition} rc ON rc.ruleid = r.id')
            ->add_joins($this->get_joins())
            ->set_field_sql("rc.classname")
            ->set_options_callback(function() {
                $conditions = \tool_dynamicrule\api::get_conditions();
                $list = [];
                foreach ($conditions as $condition) {
                    $list[get_class($condition)] = $condition->get_title();
                }
                return $list;
            })
            ->set_operators([
                select::ANY_VALUE => get_string('isanyvalue', 'filters'),
                select::EQUAL_TO => get_string('isequalto', 'filters')
            ])
            ->set_is_available(empty($component))
            ->set_is_default(true));

        // Filter for actions.
        $this->add_filter((new report_filter(
            select::class,
            'actions',
            new \lang_string('outcomes', 'tool_dynamicrule'),
            'rule'
        ))
            ->add_join('INNER JOIN {tool_dynamicrule_outcome} ro ON ro.ruleid = r.id')
            ->add_joins($this->get_joins())
            ->set_field_sql("ro.classname")
            ->set_options_callback(function() {
                $outcomes = \tool_dynamicrule\api::get_outcomes();
                $list = [];
                foreach ($outcomes as $outcome) {
                    $list[get_class($outcome)] = $outcome->get_title();
                }
                return $list;
            })
            ->set_operators([
                select::ANY_VALUE => get_string('isanyvalue', 'filters'),
                select::EQUAL_TO => get_string('isequalto', 'filters')
            ])
            ->set_is_available(empty($component))
            ->set_is_default(true));
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
        $this->iscurrentrulescheduledtask = api::is_rule_scheduled_task($this->currentrule->get('id'));
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
        $formattedname = $rule->get_formatted_name();
        if (permission::can_enable_rule($rule)) {
            // Rule is enabled icon.
            $title = get_string('disablerulemsg', 'tool_dynamicrule');
            $disablelink = new \action_link(
                new \moodle_url('#'), '', null,
                ['title' => $title, 'data-action' => 'disable', 'data-id' => $row->id, 'data-wp-toggle' => 'on'],
                new \pix_icon('toggle-on', '', 'tool_wp')
            );

            // Rule is disabled icon.
            $title = get_string('enablerulemsg', 'tool_dynamicrule');
            $attributes = [
                'title' => $title,
                'data-action' => 'enable',
                'data-id' => $row->id,
                'data-wp-toggle' => 'off'
            ];
            $enablelink = new \action_link(
                new \moodle_url('#'), '', null,
                $attributes,
                new \pix_icon('toggle-off', '', 'tool_wp')
            );

            // Hide one that is not relevant to current state.
            if ($rule->is_enabled()) {
                $enablelink->add_class('hidden');
            } else {
                $disablelink->add_class('hidden');
            }

            return $OUTPUT->render_from_template('core/action_menu_link', $disablelink->export_for_template($OUTPUT)) .
                $OUTPUT->render_from_template('core/action_menu_link', $enablelink->export_for_template($OUTPUT));
        } else {
            // Check Rule is enabled or disabled, howerver it will not have editable action for the rule here.
            if ($rule->is_enabled()) {
                // Add title not allow to disable this rule.
                $title = get_string('cannotdisablerule', 'tool_dynamicrule', $formattedname);;
                // Show rule as enabled but no action for user.
                $link = new \action_link(
                    new \moodle_url('#'), '', null,
                    ['title' => $title, 'data-id' => $row->id, 'data-wp-toggle' => 'on'],
                    new \pix_icon('toggle-on', '', 'tool_wp')
                );
            } else {
                if ($this->get_parameter('component', null, PARAM_COMPONENT)) {
                    $title = get_string('cannotenablecomponentrule', 'tool_dynamicrule', $formattedname);
                } else {
                    $title = get_string('cannotenablerule', 'tool_dynamicrule', $formattedname);
                }
                // Show rule as disabled.
                $link = new \action_link(
                    new \moodle_url('#'), '', null,
                    ['title' => $title, 'data-action' => 'enable',
                        'data-id' => $row->id, 'data-wp-toggle' => 'off'],
                    new \pix_icon('toggle-off', '', 'tool_wp')
                );
            }
            $link->add_class('disabled');
            return $OUTPUT->render_from_template('core/action_menu_link', $link->export_for_template($OUTPUT));
        }
    }

    /**
     * Report name
     * @return string
     */
    public static function get_name() {
        return get_string('reportruleslist', 'tool_dynamicrule');
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
        global $OUTPUT;
        $content = (\tool_dynamicrule\api::get_name_inplace_editable($this->currentrule))->render($OUTPUT);
        if ($this->iscurrentrulescheduledtask) {
            $content .= \html_writer::span(get_string('scheduledtask', 'tool_dynamicrule'), 'badge badge-secondary');
        }
        return $content;
    }

    /**
     * Callback for the conditions display
     *
     * @param string $value
     * @param \stdClass $row
     * @param mixed $additionalarguments
     * @return string
     */
    public function get_display_conditions($value, \stdClass $row, $additionalarguments) {
        global $OUTPUT;
        $context = new \tool_dynamicrule\output\rules_list_item_conditions_description($row->id);
        $content = $OUTPUT->render_from_template('tool_dynamicrule/rules_list_item_description',
            $context->export_for_template($OUTPUT));
        // Show 'Scheduled task' badge in conditions column for component Dynamic rules tabs.
        if (!empty($this->get_parameter('component', null, PARAM_COMPONENT)) && $this->iscurrentrulescheduledtask) {
            $content .= \html_writer::span(get_string('scheduledtask', 'tool_dynamicrule'), 'badge badge-secondary');
        }
        return $content;
    }

    /**
     * Callback for the actions display
     *
     * @param string $value
     * @param \stdClass $row
     * @param mixed $additionalarguments
     * @return string
     */
    public function get_display_actions($value, \stdClass $row, $additionalarguments) {
        global $OUTPUT;
        $context = new \tool_dynamicrule\output\rules_list_item_outcomes_description($row->id);
        return $OUTPUT->render_from_template('tool_dynamicrule/rules_list_item_description',
            $context->export_for_template($OUTPUT));
    }

    /**
     * Add actions
     */
    protected function add_actions() {
        global $CFG;

        // Add edit action icon (used in plugins).
        $this->add_action((new report_action(new \moodle_url('#'),
            new \pix_icon('i/settings', '', 'core'),
            ['title' => ':title', 'data-action' => 'editactions', 'data-id' => ':id']))
            ->add_callback(function(\stdClass $row) {
                return rules_list::action_callback($row, 'editactions', false);
            }));

        // Proceed to contents.
        $editurl = new \moodle_url('/admin/tool/dynamicrule/rule.php', ['id' => ':id']);
        $this->add_action((new report_action($editurl,
            new \pix_icon('t/right', '', 'core'),
            ['title' => ':title', 'data-action' => 'editcontent', 'data-id' => ':id']))
        ->add_callback(function(\stdClass $row) {
            return rules_list::action_callback($row, 'editrule', false);
        }));

        // Add edit details icon.
        $this->add_action((new report_action(new \moodle_url('#'),
            new \pix_icon('i/settings', '', 'core'),
            ['title' => ':title', 'data-action' => 'editdetails', 'data-id' => ':id']))
            ->add_callback(function(\stdClass $row) {
                return rules_list::action_callback($row, 'editdetails', false);
            }));

        // Add duplicate icon.
        $attr = ['title' => ':title', 'data-rulename' => ':name', 'data-action' => 'duplicate', 'data-id' => ':id'];
        if (permission::is_site_limit_reached()) {
            $attr['data-limitreached'] = 0;
        } else if (permission::is_tenant_limit_reached()) {
            $attr['data-limitreached'] = $CFG->tool_dynamicrule_tenantlimit;
        }
        $this->add_action((new report_action(new \moodle_url('#'),
            new \pix_icon('e/manage_files', '', 'core'),
            $attr))
            ->add_callback(function(\stdClass $row) {
                return rules_list::action_callback($row, 'duplicaterule', false);
            }));

        // Add report icon.
        $editurl = new \moodle_url('/admin/tool/dynamicrule/report.php', ['id' => ':id']);
        $this->add_action((new report_action($editurl,
            new \pix_icon('bar-chart', '', 'tool_wp'),
            ['title' => ':title', 'data-action' => 'report', 'data-id' => ':id']))
            ->add_callback(function(\stdClass $row) {
                return rules_list::action_callback($row, 'viewreport');
            }));

        // Add archive icon.
        $this->add_action((new report_action(new \moodle_url('#'),
            new \pix_icon('archive', '', 'tool_wp'),
            ['title' => ':title', 'data-rulename' => ':name', 'data-action' => 'archive', 'data-id' => ':id']))
            ->add_callback(function(\stdClass $row) {
                return rules_list::action_callback($row, 'archiverule', false);
            }));

        // Add unarchive icon.
        $this->add_action((new report_action(new \moodle_url('#'),
            new \pix_icon('restorearchived', '', 'tool_wp'),
            ['title' => ':title', 'data-rulename' => ':name', 'data-action' => 'unarchive', 'data-id' => ':id']))
            ->add_callback(function(\stdClass $row) {
                return rules_list::action_callback($row, 'unarchiverule', true);
            }));

        // Add delete icon.
        $this->add_action((new report_action(new \moodle_url('#'),
            new \pix_icon('i/trash', '', 'core'),
            ['title' => ':title', 'data-rulename' => ':name', 'data-action' => 'delete', 'data-id' => ':id']))
            ->add_callback(function(\stdClass $row) {
                return rules_list::action_callback($row, 'deleterule', true);
            }));
    }

    /**
     * Callback for actions
     *
     * @param \stdClass $row
     * @param string $stringkey
     * @param bool|null $archived
     * @return bool
     */
    public static function action_callback(\stdClass $row, string $stringkey, ?bool $archived = null) {
        $rule = new rule(0, $row);
        $row->name = format_string($row->name, true, ['escape' => false]);
        $row->title = get_string($stringkey, 'tool_dynamicrule', $row->name);
        $component = $rule->get('component');

        if ($stringkey === 'editactions') {
            return $component && permission::can_edit_rule_outcomes($rule);
        } else if ($stringkey === 'editrule' || $stringkey === 'editdetails') {
            return !$component && permission::can_edit_rule($rule);
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
        return $row->enabled ? '' : 'dimmed_text';
    }
}
