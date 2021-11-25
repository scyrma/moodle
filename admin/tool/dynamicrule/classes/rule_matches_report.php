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
 * This file contains the definition o rules matches report.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule;

defined('MOODLE_INTERNAL') || die;

use tool_reportbuilder\report_column;
use tool_reportbuilder\system_report;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_tenant\tenancy;

/**
 * System report with users that matched and unmatched a given rule.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class rule_matches_report extends system_report {

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_main_table('user', 'u');

        $ruleid = $this->get_parameter('ruleid', 0, PARAM_INT);
        $ruleidparam = \tool_wp\db::generate_param_name();
        // Using DISTINCT ON query also possible, but nearly 2 times more expensive (verified).
        $this->add_base_join("JOIN (SELECT MAX(id) AS id, ruleid, userid
                                      FROM {tool_dynamicrule_match}
                                  GROUP BY ruleid, userid) mi ON (mi.userid = u.id AND mi.ruleid = :{$ruleidparam})
                              JOIN {tool_dynamicrule_match} m ON (m.id = mi.id)", [$ruleidparam => $ruleid]);
        // Show only users from the current tenant and subtenants.
        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, 'u.id'));
        $this->add_base_fields(\core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects);
        $userentity = new user_entity();
        $this->add_entity($userentity);
        $this->set_columns($userentity);
        $this->set_filters($userentity);
    }

    /**
     * Define columns.
     *
     * @param user_entity $userentity
     */
    protected function set_columns(user_entity $userentity) {
        global $CFG;

        $this->get_column('user:fullnamewithpicturelink')
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true, 2)
            ->set_visiblename(new \lang_string('fullname'));

        // Get additional fields.
        $context = \context_system::instance();
        $additionaluserfields = \core_user\fields::for_identity($context, true)->get_required_fields();
        $extra = preg_split('/,/', $CFG->showuseridentity, -1, PREG_SPLIT_NO_EMPTY);
        $order = 2;
        foreach ($extra as $userfield) {
            if ($column = $this->get_column('user:'.$userentity->resolve_column_name($userfield))) {
                $column
                    ->set_is_default(true, $order++)
                    ->set_is_sortable(true)
                    ->set_is_available(in_array($userfield, $additionaluserfields));
            }
        }

        $this->annotate_entity('match', new \lang_string('match', 'tool_dynamicrule'));

        $newcolumn = (new report_column(
            'matchedtime',
            new \lang_string('matchedtime', 'tool_dynamicrule'),
            'match'
        ))
            ->add_field('m.matchedtime')
            ->set_is_default(true, $order++)
            ->set_is_sortable(true, true, 1, SORT_DESC)
            ->add_callback([format::class, 'userdate'], get_string('strftimedatetimeshort'));
        $this->add_column($newcolumn);

        $newcolumn = (new report_column(
            'status',
            new \lang_string('matchstatus', 'tool_dynamicrule'),
            'match'
        ))
            ->add_field('m.status')
            ->set_is_default(true, $order++)
            ->set_is_sortable(true)
            ->add_callback([$this, 'status_label']);
        $this->add_column($newcolumn);
    }

    /**
     * Show status label.
     *
     * @param string $value
     * @param \stdClass $row
     * @return string
     */
    public function status_label(string $value, \stdClass $row): string {
        $statusmap = self::matching_status();
        switch ($value) {
            case \tool_dynamicrule\api::STATUS_IN_PROGRESS:
                return \html_writer::span($statusmap[$value],
                    'tool_dynamicrule_match_status_in_progress');
            case \tool_dynamicrule\api::STATUS_DONE:
                return \html_writer::span($statusmap[$value],
                    'tool_dynamicrule_match_status_done'
                );
            case \tool_dynamicrule\api::STATUS_ERROR:
                return \html_writer::span($statusmap[$value],
                    'tool_dynamicrule_match_status_error'
                );
        }
        return '';
    }

    /**
     * Statuses for matching filter.
     *
     * @return array
     */
    public static function matching_status(): array {
        return [
            \tool_dynamicrule\api::STATUS_DONE => get_string('matchstatusdone', 'tool_dynamicrule'),
            \tool_dynamicrule\api::STATUS_IN_PROGRESS => get_string('matchstatusprogress', 'tool_dynamicrule'),
            \tool_dynamicrule\api::STATUS_ERROR => get_string('matchstatuserror', 'tool_dynamicrule'),
        ];
    }

    /**
     * Set the filters for the report.
     *
     * @param user_entity $userentity
     */
    protected function set_filters(user_entity $userentity) {
        $this->get_filter('user:fullname')->set_is_default(true);

        // Other userfields filters.
        $userfieldfilters = ['username', 'email'];
        $additionaluserfields = \core_user\fields::for_identity(\context_system::instance(), true)->get_required_fields();
        foreach ($userfieldfilters as $userfield) {
            if ($filter = $this->get_filter('user:'.$userentity->resolve_filter_name($userfield))) {
                $filter->set_is_default(true)
                    ->set_is_available(in_array($userfield, $additionaluserfields));
            }
        }

        // Filter matchedtime.
        $filter = (new report_filter(
            date_filter::class,
            'matchedtime',
            new \lang_string('matchedtime', 'tool_dynamicrule'),
            'match'
        ))
            ->set_field_sql('m.matchedtime')
            ->set_options([
                date_filter::DATE_ANY   => new \lang_string('dateanyvalue', 'tool_reportbuilder'),
                date_filter::DATE_RANGE => new \lang_string('daterange', 'tool_reportbuilder'),
            ])
            ->set_is_default(true);
        $this->add_filter($filter);

        // Filter status.
        $filter = (new report_filter(
            select::class,
            'status',
            new \lang_string('matchstatus', 'tool_dynamicrule'),
            'match'
        ))
            ->set_field_sql("m.status")
            ->set_options_callback([static::class, 'matching_status'])
            ->set_is_default(true);
        $this->add_filter($filter);
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
     * Visible name of the data source.
     *
     * @return string
     * @throws \moodle_exception
     */
    public static function get_name() {
        return get_string('reportrulematches', 'tool_dynamicrule');
    }
}
