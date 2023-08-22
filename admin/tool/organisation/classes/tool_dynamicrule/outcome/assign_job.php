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
 * Class containing implementation of the assign job rule outcome
 *
 * @package     tool_organisation
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\tool_dynamicrule\outcome;

use MoodleQuickForm;
use lang_string;
use stdClass;
use tool_dynamicrule\outcome_base;
use tool_organisation\department;
use tool_organisation\helper;
use tool_organisation\job_manager;
use tool_organisation\organisation;
use tool_organisation\permission;
use tool_organisation\position;
use tool_tenant\tenancy;
use tool_wp\exporter_base;
use tool_wp\importer_base;

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->dirroot}/{$CFG->admin}/tool/wp/periodduration.php");

/**
 * Assign job rule outcome
 *
 * @package     tool_organisation
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class assign_job extends outcome_base {

    /** @var int Start job at the date of rule execution. */
    public const START_EXECUTION = 0;
    /** @var int Start job at the date of user creation. */
    public const START_USER_CREATION = 1;
    /** @var int Start job at defined date. */
    public const START_ABSOLUTE = 2;

    /** @var int Don't end the job (leave end date empty). */
    public const END_NONE = 0;
    /** @var int End job relative to the start date. */
    public const END_RELATIVE = 1;
    /** @var int End job at defined date. */
    public const END_ABSOLUTE = 2;

    /** @var \tool_organisation\job_manager Job manager */
    private $jobmanager;

    /**
     * If the current user is able to add this outcome
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return permission::can_assign_job_to_anybody();
    }

    /**
     * If the current user is able to edit this outcome
     *
     * @param array $configdata
     * @return bool
     */
    public function user_can_edit(array $configdata): bool {
        return $this->user_can_add();
    }

    /**
     * Adds outcome elements to the given mform
     *
     * @param MoodleQuickForm $mform
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        $mform->addElement('selectgroups', 'departmentid', new lang_string('department', 'tool_organisation'),
            organisation::get_all_departments_menu(['' => '']));
        $mform->addRule('departmentid', null, 'required', null, 'client');
        $mform->setType('departmentid', PARAM_INT);

        $mform->addElement('selectgroups', 'positionid', new lang_string('position', 'tool_organisation'),
            organisation::get_all_positions_menu(['' => '']));
        $mform->addRule('positionid', null, 'required', null, 'client');
        $mform->setType('positionid', PARAM_INT);

        // Start date (date of execution, date of user creation, absolute date).
        $startdateoptions = [
            self::START_EXECUTION => new lang_string('ruleoutcomeassignjobstartruledate', 'tool_organisation'),
            self::START_USER_CREATION => new lang_string('ruleoutcomeassignjobstartuserdate', 'tool_organisation'),
            self::START_ABSOLUTE => new lang_string('selectdate', 'tool_organisation'),
        ];

        $startdategroup = [
            $mform->createElement('select', 'startdate', null, $startdateoptions),
            $mform->createElement('date_selector', 'startdateabsolute')
        ];

        $mform->addGroup($startdategroup, 'startdategroup', new lang_string('startdate', 'tool_organisation'), ' ', false);
        $mform->addHelpButton('startdategroup', 'startdate', 'tool_organisation');
        $mform->hideIf('startdateabsolute', 'startdate', 'neq', self::START_ABSOLUTE);

        // End date (none, relative to start date, absolute date).
        $enddateoptions = [
            self::END_NONE => new lang_string('none'),
            self::END_RELATIVE => new lang_string('enddaterelativetostart', 'tool_organisation'),
            self::END_ABSOLUTE => new lang_string('selectdate', 'tool_organisation'),
        ];

        $enddategroup = [
            $mform->createElement('select', 'enddate', null, $enddateoptions),
            $mform->createElement('periodduration', 'enddaterelative'),
            $mform->createElement('date_selector', 'enddateabsolute'),
        ];

        $mform->addGroup($enddategroup, 'enddategroup', new lang_string('enddate', 'tool_organisation'), ' ', false);
        $mform->addHelpButton('enddategroup', 'enddate', 'tool_organisation');
        $mform->hideIf('enddaterelative', 'enddate', 'neq', self::END_RELATIVE);
        $mform->hideIf('enddateabsolute', 'enddate', 'neq', self::END_ABSOLUTE);
    }

    /**
     * Validates the outcome config form
     *
     * @param array $data
     * @return string[] Array of errors ['fieldname' => 'error']
     */
    public function validate_config_form(array $data): array {
        $errors = [];

        // If we've set an end date for the job, make sure it's after the start date.
        if ($data['startdate'] == self::START_ABSOLUTE && $data['enddate'] != self::END_NONE) {
            $startdate = $data['startdateabsolute'];
            $enddate = ($data['enddate'] == self::END_ABSOLUTE)
                ? $data['enddateabsolute']
                : strtotime("@{$startdate} +{$data['enddaterelative']}");

            if ($startdate >= $enddate) {
                $errors['enddategroup'] = get_string('errorinvalidenddate', 'tool_organisation');
            }
        }

        return $errors;
    }

    /**
     * Returns the title of the outcome
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('ruleoutcomeassignjob', 'tool_organisation');
    }

    /**
     * Return the description for the outcome
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('ruleoutcomeassignjobdesc', 'tool_organisation', [
            'department' => (new department($this->get_departmentid()))->get_formatted_name(),
            'position' => (new position($this->get_positionid()))->get_formatted_name(),
        ]);
    }

    /**
     * Configuration validity check
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        return department::record_exists($this->get_departmentid()) && position::record_exists($this->get_positionid());
    }

    /**
     * Helper function called before outcome is applied to user.
     */
    public function setup_for_applying(): void {
        $this->jobmanager = new job_manager();
    }

    /**
     * Apply this outcome to a given user.
     *
     * @param \stdClass $user The user object to apply the outcome to
     */
    public function apply_to_user(\stdClass $user): void {
        $config = $this->get_configdata();
        switch ($config['startdate']) {
            case self::START_EXECUTION :
                $startdate = helper::round_time(time());
            break;
            case self::START_USER_CREATION :
                $startdate = helper::round_time($user->timecreated);
            break;
            case self::START_ABSOLUTE :
                $startdate = $config['startdateabsolute'];
            break;
        }

        switch ($config['enddate']) {
            case self::END_NONE :
                $enddate = 0;
            break;
            case self::END_RELATIVE :
                $enddate = strtotime("@{$startdate} +{$config['enddaterelative']}");
            break;
            case self::END_ABSOLUTE :
                $enddate = $config['enddateabsolute'];
            break;
        }

        $this->jobmanager->create_job((object) [
            'userid' => $user->id,
            'departmentid' => $this->get_departmentid(),
            'positionid' => $this->get_positionid(),
            'startdate' => $startdate,
            'enddate' => $enddate,
            'tenantid' => tenancy::get_tenant_id($user->id),
        ], false);
    }

    /**
     * Add outcome field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $exporter->add_mapping('tool_organisation_department', $this->get_departmentid());
        $exporter->add_mapping('tool_organisation_position', $this->get_positionid());
    }

    /**
     * Get outcome field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $departmentidmapped = $importer->get_mapping('tool_organisation_department', $this->get_departmentid(), IGNORE_MISSING);
        $positionidmapped = $importer->get_mapping('tool_organisation_position', $this->get_positionid(), IGNORE_MISSING);

        $configdata = array_merge($this->get_configdata(), [
            'departmentid' => (int) $departmentidmapped,
            'positionid' => (int) $positionidmapped,
        ]);

        $this->update_configdata($configdata);
    }

    /**
     * Return configured department ID
     *
     * @return int
     */
    public function get_departmentid(): int {
        return $this->get_configdata()['departmentid'] ?? 0;
    }

    /**
     * Return configured position ID
     *
     * @return int
     */
    public function get_positionid(): int {
        return $this->get_configdata()['positionid'] ?? 0;
    }
}
