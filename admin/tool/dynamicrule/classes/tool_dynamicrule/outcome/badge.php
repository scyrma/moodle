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
 * This file contains the backend class for badge outcome.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\outcome;

use tool_wp\exporter_base;
use tool_wp\importer_base;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/badgeslib.php');

/**
 * The backend class for notification outcome
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class badge extends \tool_dynamicrule\outcome_base {
    /** @var \core_badges\badge */
    private $badge;

    /**
     * Returns the title of the outcome
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('outcomebadge', 'tool_dynamicrule');
    }

    /**
     * Adds outcome's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        // Badges selector.
        $options = [
            'ajax' => 'tool_dynamicrule/form_badge_selector',
            'multiple' => false,
            'class' => 'select_badge',
        ];
        $selected = $this->get_selected();
        $mform->addElement('autocomplete', 'badge', get_string('selectbadge', 'tool_dynamicrule'), $selected, $options);
        $mform->addHelpButton('badge', 'selectbadge', 'tool_dynamicrule');
        $mform->addRule('badge', get_string('required'), 'required', null, 'client');

        // Manage badges link.
        $manageprogurl = new \moodle_url('/badges/index.php?type=1');
        $manageprogramsstr = get_string('managebadges', 'tool_dynamicrule');
        $html = \html_writer::tag('a', $manageprogramsstr, ['href' => $manageprogurl, 'target' => '_blank']);
        $mform->addElement('static', 'managebadge', '', $html);
    }

    /**
     * Validates the configform of the outcome
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        global $DB, $USER;
        $errors = [];

        $badge = new \core_badges\badge($data['badge']);
        // Badge must be of type site, active and has manual issue criteria defined.
        if ($badge->type != BADGE_TYPE_SITE || !$badge->is_active() || !$badge->has_manual_award_criteria()) {
            $errors['badge'] = get_string('errorinvalidbadge', 'tool_dynamicrule');
            return $errors;
        }

        // Badge must have only manual award criteria, or use ANY aggregation method in overall criteria.
        // This may not be required if we use API method badges_award_handle_manual_criteria_review for issuing,
        // but since we issue directly, we must ensure there are no other criteria requirements in place.
        if (count($badge->criteria) > 2 && $badge->get_aggregation_method() != BADGE_CRITERIA_AGGREGATION_ANY) {
            $errors['badge'] = get_string('errorbadgehasextracriteria', 'tool_dynamicrule');
            return $errors;
        }

        // Check manual issue criteria permissions.
        if (!is_siteadmin()) {
            $acceptedroles = array_keys($badge->criteria[BADGE_CRITERIA_TYPE_MANUAL]->params);
            $roles = get_user_roles(\context_system::instance(), $USER->id);
            $roleids = array_column($roles, 'roleid');
            if (empty(array_intersect($acceptedroles, $roleids))) {
                $errors['badge'] = get_string('errorbadgenopermission', 'tool_dynamicrule');
            }
        }

        return $errors;
    }

    /**
     * Helper function called before outcome is applied to user.
     */
    public function setup_for_applying(): void {
        $this->badge = new \core_badges\badge($this->get_badgeid());
    }

    /**
     * Apply this outcome to a given user
     *
     * @param \stdClass $user The user object to apply the outcome to
     */
    public function apply_to_user(\stdClass $user): void {
        // Check if user has this badge already. In reality we need to trigger
        // badges_award_handle_manual_criteria_review, but that is not possible
        // since existing badge outcomes may have different criteria.
        // 'nobake' param is only used in unittest, there is no such form setting.
        if (!$this->badge->is_issued($user->id)) {
            $this->badge->issue($user->id, !empty($this->get_configdata()['nobake']));
        }
    }

    /**
     * Return the description for the outcome.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('outcomebadgedescription', 'tool_dynamicrule', $this->get_badge_name());
    }

    /**
     * Return subject formatted.
     *
     * @return string
     */
    private function get_badge_name(): string {
        global $DB;
        $badgename = $DB->get_field('badge', 'name', ['id' => $this->get_badgeid()]);
        return format_string($badgename, true, ['context' => \context_system::instance(), 'escape' => false]);
    }

    /**
     * Return configured badge id.
     *
     * @return null|int
     */
    public function get_badgeid() : ?int {
        if (isset($this->get_configdata()['badge'])) {
            return $this->get_configdata()['badge'];
        }
        return null;
    }

    /**
     * Check if badge is not empty.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        global $DB;

        if (!$badge = $DB->get_record('badge', ['id' => $this->get_badgeid()])) {
            // Badge does not exist.
            return false;
        }

        // Ensure that the badge is active. For backward compatibility with existing badges,
        // we don't do check site type and manual issue criteria presence here.
        $badge = new \core_badges\badge($this->get_badgeid());
        if (!$badge->is_active()) {
            return false;
        }

        return true;
    }

    /**
     * If the current user is able to add this outcome.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return has_capability('moodle/badges:awardbadge', \context_system::instance());
    }


    /**
     * If the current user is able to edit this outcome.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        $badge = new \core_badges\badge($configdata['badge']);
        return has_capability('moodle/badges:awardbadge', $badge->get_context());
    }

    /**
     * Return id and name of selected badges.
     *
     * @return array
     */
    private function get_selected(): array {
        if ($this->get_badgeid()) {
            $selected = [$this->get_badgeid() => $this->get_badge_name()];
        } else {
            $selected = [];
        }
        return $selected;
    }

    /**
     * Add badge outcome field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $exporter->add_mapping('badge', $this->get_badgeid());
    }

    /**
     * Get badge outcome field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $configdata = $this->get_configdata();
        $configdata['badge'] = $importer->get_mapping('badge', $this->get_badgeid(), IGNORE_MISSING) ?? 0;

        $this->update_configdata($configdata);
    }

    /**
     * If the current user is able to use this outcome.
     *
     * Return true if there are site badges with no criteria and not archived.
     *
     * @return bool
     */
    public static function is_available(): bool {
        global $DB;

        // We select badges of type site, active and with manual issue criteria.
        $query = 'SELECT COUNT(1)
                  FROM {badge} b
                  JOIN {badge_criteria} bc
                  ON b.id = bc.badgeid
                  WHERE (b.status = :badgestatusactive OR b.status = :badgestatusactivelocked)
                  AND b.type = :badgetype AND bc.criteriatype = :badgecriteriatype';

        $params = [
            'badgestatusactive' => BADGE_STATUS_ACTIVE,
            'badgestatusactivelocked' => BADGE_STATUS_ACTIVE_LOCKED,
            'badgetype' => BADGE_TYPE_SITE,
            'badgecriteriatype' => BADGE_CRITERIA_TYPE_MANUAL,
        ];

        return ($DB->count_records_sql($query, $params) > 0);
    }

    /**
     * Outcome not available label.
     *
     * @return string
     */
    public function get_not_available_label(): string {
        return get_string('noavailablebadges', 'tool_dynamicrule');
    }

    /**
     * Outcome broken label.
     *
     * Outcomes may provide more detailed information on what is broken when
     * is_configuration_valid returns false.
     *
     * @return string
     */
    public function get_broken_description(): string {
        return get_string('errorinvalidbadge', 'tool_dynamicrule');
    }
}
