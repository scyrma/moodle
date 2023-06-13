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
 * This file contains the backend class for Is member of cohort audience type
 *
 * @package    tool_reportbuilder
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\tool_reportbuilder\audiences;

use context;
use context_system;
use core_course_category;
use MoodleQuickForm;
use tool_reportbuilder\audience_base;
use tool_wp\db;
use tool_wp\exporter_base;
use tool_wp\importer_base;

/**
 * The backend class for Is member of cohort audience type
 *
 * @package    tool_reportbuilder
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class cohortmember extends audience_base {

    /**
     * Adds audience's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        $cohorts = self::get_cohorts();
        $mform->addElement('autocomplete', 'cohorts', get_string('selectcohorts', 'tool_reportbuilder'),
            $cohorts, ['multiple' => true]);
        $mform->addRule('cohorts', null, 'required', null, 'client');
        $mform->addHelpButton('cohorts', 'addusers', 'tool_reportbuilder'); // TODO.
    }

    /**
     * Helps to build SQL to retrieve users that matches the current audience
     *
     * @param string $usertablealias
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(string $usertablealias): array {
        global $DB;

        $cm = db::generate_alias();
        $cohorts = $this->get_configdata()['cohorts'];
        [$insql, $inparams] = $DB->get_in_or_equal($cohorts, SQL_PARAMS_NAMED);

        $join = "JOIN {cohort_members} {$cm}
                   ON ({$cm}.userid = {$usertablealias}.id)";

        return [$join, "{$cm}.cohortid " . $insql, $inparams];
    }

    /**
     * Returns the title of the audience
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('ismemberofcohort', 'tool_reportbuilder');
    }

    /**
     * Return the description for the audience.
     *
     * @return string
     */
    public function get_description(): string {
        global $DB;

        $cohortlist = [];

        $cohortids = $this->get_configdata()['cohorts'];
        $cohorts = $DB->get_records_list('cohort', 'id', $cohortids, 'name');
        foreach ($cohorts as $cohort) {
            $cohortlist[] = format_string($cohort->name, true, ['context' => $cohort->contextid, 'escape' => false]);
        }

        return $this->format_description_for_multiselect($cohortlist);
    }

    /**
     * If the current user is able to add this audience.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        // Check system context first.
        if (has_capability('moodle/cohort:view', context_system::instance())) {
            return true;
        }
        // If there is at least one category with given permissions, user can add.
        return !empty(core_course_category::make_categories_list('moodle/cohort:view'));
    }

    /**
     * If the current user is able to edit this audience.
     *
     * @return bool
     */
    public function user_can_edit(): bool {
        global $DB;

        $canedit = true;
        $cohortids = $this->get_configdata()['cohorts'];
        $cohorts = $DB->get_records_list('cohort', 'id', $cohortids);
        foreach ($cohorts as $cohort) {
            $context = context::instance_by_id($cohort->contextid, MUST_EXIST);
            $canedit = $canedit && has_capability('moodle/cohort:view', $context);
        }

        return $canedit;
    }

    /**
     * Returns if this audience type is available for the user
     *
     * Check if there are available cohorts in the system for this user to use.
     *
     * @return bool
     */
    public function is_available(): bool {
        return !empty(self::get_cohorts());
    }

    /**
     * Audience type not available label.
     *
     * Audience types may provide more detailed information why it is not available when
     * is_available returns false.
     *
     * @return string
     */
    public function get_not_available_label(): string {
        return get_string('noavailablecohorts', 'tool_reportbuilder');
    }

    /**
     * Cohorts selector.
     *
     * @return array
     */
    public static function get_cohorts(): array {
        global $CFG;
        require_once($CFG->dirroot.'/cohort/lib.php');

        $capability = 'moodle/cohort:view';

        // Put together system and category contexts user can access.
        $categories = core_course_category::make_categories_list($capability);
        $contextids = [];
        foreach (array_keys($categories) as $categoryid) {
            $contextids[\context_coursecat::instance($categoryid)->id] = '';
        }
        if (has_capability($capability, context_system::instance())) {
            $contextids[SYSCONTEXTID] = '';
        }

        if (count($contextids)) {
            // Search cohorts user can view.
            $cohorts = cohort_get_all_cohorts(0, 0);

            // Remove cohorts user can't access.
            $cohorts = array_filter($cohorts['cohorts'], function($cohort) use ($contextids) {
                return array_key_exists($cohort->contextid, $contextids);
            });

            $cohortslist = [];
            foreach ($cohorts as $cohort) {
                $params = ['context' => $cohort->contextid, 'escape' => false];
                $cohortslist[$cohort->id] = format_string($cohort->name, true, $params);
            }
            return $cohortslist;
        }
        return [];
    }

    /**
     * Add cohort field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $cohortids = $this->get_configdata()['cohorts'];
        foreach ($cohortids as $cohortid) {
            $exporter->add_mapping('cohort', $cohortid);
        }
    }

    /**
     * Get cohort field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $mappedcohortids = array_map(static function(int $cohortid) use ($importer): int {
            return $importer->get_mapping('cohort', $cohortid, IGNORE_MISSING) ?? -1;
        }, $this->get_configdata()['cohorts']);

        $this->update_configdata(['cohorts' => $mappedcohortids]);
    }
}
