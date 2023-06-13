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

namespace tool_organisation\tool_dynamicrule\outcome;

use MoodleQuickForm;
use lang_string;
use tool_dynamicrule\outcome_base;
use tool_organisation\department;
use tool_organisation\helper;
use tool_organisation\job_manager;
use tool_organisation\organisation;
use tool_organisation\permission;
use tool_organisation\position;
use tool_wp\exporter_base;
use tool_wp\importer_base;

/**
 * End all jobs rule outcome
 *
 * @package    tool_organisation
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Odei Alba <odei.alba@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class end_jobs extends outcome_base {

    /** @var int End job at the date of rule execution. */
    public const END_EXECUTION = 0;
    /** @var int End job a day before the date of rule execution. */
    public const END_DAY_BEFORE_EXECUTION = 1;
    /** @var int End job at defined date. */
    public const END_ABSOLUTE = 2;
    /** @var int End only active jobs. */
    public const ACTIVE_JOBS = 0;
    /** @var int End all jobs. */
    public const ALL_JOBS = 1;
    /** @var int Any position or department. */
    public const ANY = 0;

    /** @var job_manager Job manager */
    private job_manager $jobmanager;

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
            organisation::get_all_departments_menu([self::ANY => new lang_string('anydepartment', 'tool_organisation')]));
        $mform->setType('departmentid', PARAM_INT);
        $mform->addElement('advcheckbox', 'includesubdepartments', new lang_string('withsubdepartments', 'tool_organisation'));
        $mform->disabledIf('includesubdepartments', 'departmentid', 'eq', self::ANY);
        $mform->setDefault('includesubdepartments', true);

        $mform->addElement('selectgroups', 'positionid', new lang_string('position', 'tool_organisation'),
            organisation::get_all_positions_menu([self::ANY => new lang_string('anyposition', 'tool_organisation')]));
        $mform->setType('positionid', PARAM_INT);
        $mform->addElement('advcheckbox', 'includesubpositions', new lang_string('withsubpositions', 'tool_organisation'));
        $mform->disabledIf('includesubpositions', 'positionid', 'eq', self::ANY);
        $mform->setDefault('includesubpositions', true);

        // End date (one day before date of execution, date of execution, absolute date).
        $enddateoptions = [
            self::END_DAY_BEFORE_EXECUTION => new lang_string('ruleoutcomedaybeforeruledate', 'tool_organisation'),
            self::END_EXECUTION => new lang_string('ruleoutcomeruledate', 'tool_organisation'),
            self::END_ABSOLUTE => new lang_string('selectdate', 'tool_organisation'),
        ];

        $enddategroup = [
            $mform->createElement('select', 'enddate', null, $enddateoptions),
            $mform->createElement('date_selector', 'enddateabsolute')
        ];

        $mform->addGroup($enddategroup, 'enddategroup', new lang_string('enddate', 'tool_organisation'), ' ', false);
        $mform->addHelpButton('enddategroup', 'enddate', 'tool_organisation');
        $mform->hideIf('enddateabsolute', 'enddate', 'neq', self::END_ABSOLUTE);
        $mform->setDefault('enddate', self::END_DAY_BEFORE_EXECUTION);

        // Target (active jobs only, all jobs).
        $targetoptions = [
            self::ACTIVE_JOBS => new lang_string('ruleoutcomeactive', 'tool_organisation'),
            self::ALL_JOBS => new lang_string('ruleoutcomeall', 'tool_organisation'),
        ];

        $mform->addElement('select', 'target', new lang_string('ruleoutcometarget', 'tool_organisation'), $targetoptions);
        $mform->setType('target', PARAM_INT);
        $mform->addHelpButton('target', 'ruleoutcometarget', 'tool_organisation');
    }

    /**
     * Validates the outcome config form
     *
     * @param array $data
     * @return string[] Array of errors ['fieldname' => 'error']
     */
    public function validate_config_form(array $data): array {
        return [];
    }

    /**
     * Returns the title of the outcome
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('ruleoutcomeendjobs', 'tool_organisation');
    }

    /**
     * Return the description for the outcome
     *
     * @return string
     */
    public function get_description(): string {
        $config = $this->get_configdata();

        $department = $this->get_departmentid() === self::ANY
            ? get_string('anydepartment', 'tool_organisation')
            : (new department($this->get_departmentid()))->get_formatted_name();
        $includesubdepartments = $config['includesubdepartments'] ? get_string('yes') : get_string('no');
        $position = $this->get_positionid() === self::ANY
            ? get_string('anyposition', 'tool_organisation')
            : (new position($this->get_positionid()))->get_formatted_name();
        $includesubpositions = $config['includesubpositions'] ? get_string('yes') : get_string('no');
        $target = $this->get_target() === self::ACTIVE_JOBS
            ? get_string('ruleoutcomeactive', 'tool_organisation')
            : get_string('ruleoutcomeall', 'tool_organisation');

        $enddate = '';
        switch ($config['enddate']) {
            case self::END_EXECUTION:
                $enddate = get_string('ruleoutcomeruledate', 'tool_organisation');
                break;
            case self::END_ABSOLUTE:
                global $CFG;
                $enddate = userdate($config['enddateabsolute'], '%Y-%m-%d', $CFG->timezone, false, false);
                break;
            case self::END_DAY_BEFORE_EXECUTION:
                $enddate = get_string('ruleoutcomedaybeforeruledate', 'tool_organisation');
                break;
        }

        return get_string('ruleoutcomeendjobsdesc', 'tool_organisation', [
            'department' => $department,
            'includesubdepartments' => $includesubdepartments,
            'position' => $position,
            'includesubpositions' => $includesubpositions,
            'target' => $target,
            'enddate' => $enddate,
        ]);
    }

    /**
     * Configuration validity check
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        return ($this->get_departmentid() === self::ANY || department::record_exists($this->get_departmentid()))
            && ($this->get_positionid() === self::ANY || position::record_exists($this->get_positionid()));
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

        // Get end date for jobs.
        $enddate = 0;
        switch ($config['enddate']) {
            case self::END_EXECUTION:
                $enddate = helper::round_time(time());
                break;
            case self::END_ABSOLUTE:
                $enddate = $config['enddateabsolute'];
                break;
            case self::END_DAY_BEFORE_EXECUTION:
                $enddate = helper::round_time(time() - DAYSECS);
                break;
        }

        $userwithjobs = organisation::get_user_with_jobs($user->id, null);
        $jobs = $userwithjobs ? $userwithjobs->get_jobs() : [];
        foreach ($jobs as $job) {
            // Ignore if start date is after the defined end date.
            if ($enddate !== 0 && $enddate < $job->get('startdate')) {
                continue;
            }
            // Get only jobs with "enddate = 0" for "only active jobs" target.
            if ($this->get_target() === self::ACTIVE_JOBS && $job->get('enddate') !== 0) {
                continue;
            }
            // Get only jobs in defined department or subdepartments. If any is defined, don't ignore anything.
            if ($this->get_departmentid() !== self::ANY
                && $job->get_department()->get('id') !== $this->get_departmentid()
                && (
                    !$config['includesubdepartments']
                    || strpos($job->get_department()->get('path'), '/' . $this->get_departmentid() . '/') === false
                )
            ) {
                continue;
            }
            // Get only jobs in defined position or subpositions. If any is defined, don't ignore anything.
            if ($this->get_positionid() !== self::ANY
                && $job->get_position()->get('id') !== $this->get_positionid()
                && (
                    !$config['includesubpositions']
                    || strpos($job->get_position()->get('path'), '/' . $this->get_positionid() . '/') === false
                )
            ) {
                continue;
            }
            $this->jobmanager->update_job($job->get('id'), (object) ['enddate' => $enddate]);
        }
    }

    /**
     * Add outcome field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        if ($this->get_departmentid() !== self::ANY) {
            $exporter->add_mapping('tool_organisation_department', $this->get_departmentid());
        }
        if ($this->get_positionid() !== self::ANY) {
            $exporter->add_mapping('tool_organisation_position', $this->get_positionid());
        }
    }

    /**
     * Get outcome field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $datatomap = [];
        if ($this->get_departmentid() !== self::ANY) {
            $departmentidmapped = (int) $importer->get_mapping('tool_organisation_department', $this->get_departmentid(),
                IGNORE_MISSING);
            $datatomap['departmentid'] = $departmentidmapped;
        }
        if ($this->get_positionid() !== self::ANY) {
            $positionidmapped = (int) $importer->get_mapping('tool_organisation_position', $this->get_positionid(),
                IGNORE_MISSING);
            $datatomap['positionid'] = $positionidmapped;
        }

        $configdata = array_merge($this->get_configdata(), $datatomap);

        $this->update_configdata($configdata);
    }

    /**
     * Return configured department ID
     *
     * @return int
     */
    public function get_departmentid(): int {
        return $this->get_configdata()['departmentid'] ?? self::ANY;
    }

    /**
     * Return configured position ID
     *
     * @return int
     */
    public function get_positionid(): int {
        return $this->get_configdata()['positionid'] ?? self::ANY;
    }

    /**
     * Return configured target
     *
     * @return int
     */
    public function get_target(): int {
        return $this->get_configdata()['target'] ?? self::ACTIVE_JOBS;
    }
}
