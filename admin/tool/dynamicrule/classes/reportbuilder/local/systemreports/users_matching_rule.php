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

use core_user\fields;
use lang_string;
use stdClass;
use html_writer;
use tool_dynamicrule\permission;
use core_reportbuilder\system_report;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\local\report\action;
use tool_tenant\tenancy;
use core_reportbuilder\local\entities\user;

/**
 * System report with users that matched and unmatched a given rule.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Paul Holden <paulh@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class users_matching_rule extends system_report {

    /**
     * The name of our internal report entity
     *
     * @return string
     */
    private function get_matched_user_entity_name(): string {
        return 'matcheduser';
    }

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $entityuser = new user();
        $entityuseralias = $entityuser->get_table_alias('user');

        $this->set_main_table('user', $entityuseralias);
        $this->add_entity($entityuser);

        $this->add_base_fields("{$entityuseralias}.suspended"); // Required by get_row_class method.

        // Define our internal entity for matched user elements.
        $this->annotate_entity($this->get_matched_user_entity_name(),
            new lang_string('match', 'tool_dynamicrule'));

        $ruleid = $this->get_parameter('ruleid', 0, PARAM_INT);

        // Using DISTINCT ON query also possible, but nearly 2 times more expensive (verified).
        $ruleidparam = database::generate_param_name();
        $this->add_join("JOIN (SELECT MAX(id) AS id, ruleid, userid
                                      FROM {tool_dynamicrule_match}
                                  GROUP BY ruleid, userid) mi ON (mi.userid = {$entityuseralias}.id AND mi.ruleid = :{$ruleidparam})
                              JOIN {tool_dynamicrule_match} m ON (m.id = mi.id)", [$ruleidparam => $ruleid]);
        $this->add_base_fields('m.id');

        // Show only users from the current tenant and subtenants.
        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, "{$entityuseralias}.id"));

        $this->add_columns($entityuser);
        $this->add_filters($entityuser);

        $this->set_downloadable(true);
    }

    /**
     * Define columns.
     *
     * @param user $userentity
     */
    protected function add_columns(user $userentity): void {
        $tablealias = $this->get_main_table_alias();

        // The matched user.
        $this->add_column_from_entity('user:fullnamewithpicturelink')
            ->add_field("{$tablealias}.suspended")
            ->add_callback([$this, 'apply_suspended_label']);

        // Get additional fields.
        $identityfields = fields::for_identity($this->get_context(), true)->get_required_fields();
        foreach ($identityfields as $identityfield) {
            $column = $userentity->get_identity_column($identityfield);
            $this->add_column($column);
        }

        // Matched time.
        $this->add_column((new column(
            'matchedtime',
            new lang_string('matchedtime', 'tool_dynamicrule'),
            $this->get_matched_user_entity_name()
        ))
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field('m.matchedtime')
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate'], get_string('strftimedatetimeshort', 'core_langconfig'))
        );

        // Status.
        $this->add_column((new column(
            'status',
            new lang_string('matchstatus', 'tool_dynamicrule'),
            $this->get_matched_user_entity_name()
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_field('m.status')
            ->set_is_sortable(true)
            ->add_callback([$this, 'status_label'])
        );

        // Add details action icon.
        $this->add_action(new action(
            new \moodle_url('#'),
            new \pix_icon('bar-chart', '', 'tool_wp'),
            ['title' => new lang_string('seedetails', 'tool_dynamicrule'), 'data-action' => 'showdetails', 'data-id' => ':id']
        ));

        // Default sorting.
        $this->set_initial_sort_column('user:fullnamewithpicturelink', SORT_ASC);
    }

    /**
     * Show status label.
     *
     * @param int $value
     * @param \stdClass $row
     * @return string
     */
    public function status_label(int $value, \stdClass $row): string {
        $statusmap = $this->matching_status();
        switch ($value) {
            case \tool_dynamicrule\api::STATUS_IN_PROGRESS:
                return \html_writer::span($statusmap[$value],
                    'tool_dynamicrule_match_status_in_progress');
            case \tool_dynamicrule\api::STATUS_DONE:
                return \html_writer::span($statusmap[$value],
                    'tool_dynamicrule_match_status_done');
            case \tool_dynamicrule\api::STATUS_ERROR:
                return \html_writer::span($statusmap[$value],
                    'tool_dynamicrule_match_status_error');
        }
        return '';
    }

    /**
     * Statuses for matching filter.
     *
     * @return array
     */
    public function matching_status(): array {
        return [
            \tool_dynamicrule\api::STATUS_DONE => get_string('matchstatusdone', 'tool_dynamicrule'),
            \tool_dynamicrule\api::STATUS_IN_PROGRESS => get_string('matchstatusprogress', 'tool_dynamicrule'),
            \tool_dynamicrule\api::STATUS_ERROR => get_string('matchstatuserror', 'tool_dynamicrule'),
        ];
    }

    /**
     * Set the filters for the report.
     *
     * @param user $userentity
     */
    protected function add_filters(user $userentity): void {
        $this->add_filter_from_entity('user:fullname');

        // Other userfields filters (limited to only username/email, if they are also identity fields).
        $userfieldfilters = ['username', 'email'];
        $identityfields = fields::for_identity($this->get_context(), false)->get_required_fields();
        foreach ($userfieldfilters as $userfield) {
            $filter = $userentity->get_identity_filter($userfield);
            $this->add_filter($filter)
                ->set_is_available(in_array($userfield, $identityfields));
        }

        // Filter matchedtime.
        $this->add_filter((new filter(
            date::class,
            'matchedtime',
            new lang_string('matchedtime', 'tool_dynamicrule'),
            $this->get_matched_user_entity_name()
        ))
            ->set_field_sql('m.matchedtime')
            ->set_limited_operators([
                date::DATE_ANY,
                date::DATE_RANGE,
                date::DATE_PREVIOUS,
                date::DATE_CURRENT,
            ])
        );

        // Filter status.
        $this->add_filter((new filter(
            select::class,
            'status',
            new lang_string('matchstatus', 'tool_dynamicrule'),
            $this->get_matched_user_entity_name()
        ))
            ->set_field_sql("m.status")
            ->set_options_callback([$this, 'matching_status'])
        );
    }

    /**
     * Return if the user can view current report.
     *
     * @return bool
     */
    protected function can_view(): bool {
        $ruleid = $this->get_parameter('ruleid', 0, PARAM_INT);
        try {
            $rule = \tool_dynamicrule\api::get_rule($ruleid);
        } catch (\Exception $e) {
            return false;
        }
        return permission::can_view_matched_users_report($rule);
    }

    /**
     * Dim the table row for invalid datasource
     *
     * @param stdClass $row
     * @return string
     */
    public function get_row_class(stdClass $row): string {
        return $row->suspended ? 'text-muted' : '';
    }

    /**
     * Callback for the fullname to display and add badge for suspended users when apply.
     *
     * @param string $userfullname
     * @param stdClass $row
     * @return string
     */
    public function apply_suspended_label($userfullname, stdClass $row) {
        // Display the "Suspended" badge if user is suspended.
        if ($row->suspended) {
            $userfullname .= ' ' . html_writer::span(get_string('suspended'), 'badge badge-pill badge-secondary');
        }
        return $userfullname;
    }
}
