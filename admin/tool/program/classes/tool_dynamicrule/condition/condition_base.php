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

namespace tool_program\tool_dynamicrule\condition;

use tool_program\api;
use tool_program\constants;
use tool_program\persistent\program;
use tool_program\permission;
use tool_program\persistent\program_user;
use tool_wp\exporter_base;
use tool_wp\importer_base;
use tool_program\local\helpers\dynamic_rules as helper;
use tool_dynamicrule\rule;

/**
 * The base class for program conditions
 *
 * @package    tool_program
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @author     2020 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class condition_base extends \tool_dynamicrule\condition_sql {

    /**
     * Validates the configform of the condition
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        $errors = [];
        if (!$program = helper::get_program_if_valid($data['programid'], $this->get_rule())) {
            $errors['programid'] = get_string('errorinvalidprogram', 'tool_program');
            return $errors;
        }

        if (!permission::can_edit_dynamicrule_condition(reset($program), $this->get_rule())) {
            // We need to check permission here as listed program might be viewable to user,
            // but user does not have capability to view allocates users.
            $errors['programid'] = get_string('errornopermissionviewallocatedusers', 'tool_program');
        }

        return $errors;
    }

    /**
     * Return the configured programid
     *
     * @return int
     */
    protected function get_programid(): int {
        return $this->get_configdata()['programid'] ?? 0;
    }

    /**
     * Return the program object from configured programid
     *
     * @return program
     */
    protected function get_program(): program {
        return new program($this->get_programid());
    }

    /**
     * Check if program still exists.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        return (bool)helper::get_program_if_valid($this->get_programid(), $this->get_rule());
    }

    /**
     * If the current user is able to add this outcome.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return permission::can_add_dynamicrule_condition();
    }

    /**
     * If the current user is able to edit this outcome.
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        $program = new program($configdata['programid']);
        return permission::can_edit_dynamicrule_condition($program, $this->get_rule());
    }

    /**
     * Return the description for the condition when current user does not have permission to edit it
     *
     * @return string
     */
    public function get_uneditable_description(): string {
        $program = new program($this->get_configdata()['programid']);
        if (permission::can_view_details($program)) {
            return $this->get_description();
        }
        return parent::get_uneditable_description();
    }

    /**
     * Add programid condition field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $exporter->add_mapping('tool_program', $this->get_programid());
    }

    /**
     * Get programid condition field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $configdata = $this->get_configdata();
        $configdata['programid'] = $importer->get_mapping('tool_program', $this->get_programid(), IGNORE_MISSING) ?? 0;

        $this->update_configdata($configdata);
    }

    /**
     * Returns program due date for this user
     *
     * @param program $program
     * @param int $userid
     * @return string
     * @throws \coding_exception
     */
    public function get_program_user_duedate(program $program, int $userid): string {
        $programuser = program_user::get_record([
            'userid' => $userid,
            'programid' => $this->get_programid(),
            'certificationid' => constants::ALLOCATION_MANUAL,
        ]);
        if ((int)$programuser->get('duedatelocked') === constants::DATE_LOCKED &&
            $programuser->get('duedate') == constants::DATE_NONE) {
            $duedate = get_string('never', 'tool_program');
        } else if ((int)$programuser->get('duedatelocked') === constants::DATE_LOCKED && $programuser->get('duedate') > 0) {
            $duedate = userdate($programuser->get('duedate'), get_string('strftimedatefullshort'));
        } else {
            $defaultdates = api::get_default_program_dates($program);
            $duedate = $defaultdates->duedate;
        }
        return $duedate;
    }

    /**
     * Which rule types this condition supports.
     *
     * @return int Rule types bitwise added.
     */
    public function supports_rule_types(): int {
        return rule::TYPE_NORMAL + rule::TYPE_SHARED;
    }
}
