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
 * Class jobs
 *
 * @package     tool_organisation
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\tool_wp\importer;

use tool_organisation\department;
use tool_organisation\helper;
use tool_organisation\job_manager;
use tool_organisation\permission;
use tool_organisation\position;
use tool_tenant\sharedspace;
use tool_wp\local\exportimport\forms\import_settings_form;

defined('MOODLE_INTERNAL') || die();

/**
 * Class jobs
 *
 * @package     tool_organisation
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class jobs extends \tool_wp\importer_base {

    /** @var string */
    const IMPORT_JOBS = 'import_content';
    /** @var string */
    const IMPORT_FRAMEWORKS = 'import_frameworks';
    /** @var string */
    const IMPORT_TYPE = 'import_instances';
    /** @var string */
    const IMPORT_TYPE_ALL = 'all';
    /** @var string */
    const IMPORT_TYPE_MANUALLY = 'selected';
    /** @var string */
    const IMPORT_FROM_SELECTED_FRAMEWORKS = 'select_frameworks';

    /**
     * Exporter format
     *
     * @return int
     */
    public function get_format(): int {
        return self::FORMAT_WORKPLACE;
    }

    /**
     * Importer name
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('exporterjobs', 'tool_organisation');
    }

    /**
     * Allows to mark importer as not available
     *
     * By default every importer is available for general import and not available for any entrypoint
     *
     * @return bool
     */
    public function is_available(): bool {
        return (!strlen($this->entrypoint) || $this->is_chained_entrypoint()) && permission::can_assign_job_to_anybody();
    }

    /**
     * Register all entities that can be imported by this importer, all potential errors and notices
     */
    protected function initialise() {

        $this->register_entity('tool_organisation_job',
            [
                self::ENTITY_DEPENDENCIES => ['tool_organisation_department', 'tool_organisation_position', 'user', 'tool_tenant'],
                self::ENTITY_INDIVIDUALIMPORT => static function(array $ids, array $settings): array {
                    $defaults = [
                        self::IMPORT_JOBS => 1,
                        self::IMPORT_FRAMEWORKS => 1,
                    ];

                    return array_intersect_key($settings, $defaults) + $defaults + [
                        self::IMPORT_TYPE => self::IMPORT_TYPE_MANUALLY,
                        self::IMPORT_FROM_SELECTED_FRAMEWORKS => $ids,
                    ];
                },
                self::ENTITY_NAMEPLURAL => get_string('jobs', 'tool_organisation'),
                self::ENTITY_LOGERROR => static function(array $details) {
                    $a = (object)[
                        'userfullname' => $details['userfullname'] ?? $details['originaluserfullname'],
                        'position' => $details['positionname'] ?? $details['originalposname'],
                        'department' => $details['departmentname'] ?? $details['originaldepname'],
                    ];
                    return get_string('importlogjobfailed', 'tool_organisation', $a);
                },
                self::ENTITY_LOGSUCCESS => static function(int $id, array $details) {
                    $a = (object)[
                        'url' => (new \moodle_url('/user/profile.php', ['id' => $details['userid']]))->out(),
                        'userfullname' => $details['userfullname'],
                        'position' => $details['positionname'],
                        'department' => $details['departmentname'],
                    ];
                    return get_string('importlogjobsuccess', 'tool_organisation', $a);
                },
                self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                    $a = (object)[
                        'position' => $record['positionname'] ?? $record['originalposname'],
                        'department' => $record['departmentname'] ?? $record['originaldepname'],
                    ];
                    return ($record['userfullname'] ?? $record['originaluserfullname']) . ' - ' .
                        get_string('positionanddepartmentdisplay', 'tool_organisation', $a);

                },
            ]);
    }

    /**
     * User has permission to import frameworks
     *
     * @return bool
     */
    protected function can_import_frameworks() {
        if (!(orgstructure::can_import_departments() && orgstructure::can_import_positions())) {
            return false;
        }
        return $this->get_entities_in_workplace_export_file(orgstructure::NAME_DEPARTMENT_FRAMEWORK)->count() ||
            $this->get_entities_in_workplace_export_file(orgstructure::NAME_POSITION_FRAMEWORK)->count();
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
        $mform->addElement('header', 'headerorgstructure', get_string('content', 'tool_wp'));
        $mform->setExpanded('headerorgstructure');

        $mform->addElement('advcheckbox', self::IMPORT_JOBS,
            get_string('jobs', 'tool_organisation'));
        $mform->setDefault(self::IMPORT_JOBS, 1);
        $form->freeze_at(self::IMPORT_JOBS, 1);

        $mform->addElement('advcheckbox', self::IMPORT_FRAMEWORKS,  get_string('allframeworks', 'tool_organisation'));
        $mform->setDefault(self::IMPORT_FRAMEWORKS, 1);
        if (!$this->can_import_frameworks()) {
            $form->freeze_at(self::IMPORT_FRAMEWORKS, 0);
        }

        $mform->addElement('header', 'headerorgstructure', get_string('instances', 'tool_wp'));
        $mform->setExpanded('headerorgstructure');

        $selectall = get_string('selectalljobsinfile', 'tool_organisation');
        $selectmanually = get_string('selectjobsinframeworks', 'tool_organisation');
        $mform->addElement('radio', self::IMPORT_TYPE, null, $selectall, self::IMPORT_TYPE_ALL);
        $mform->addElement('radio', self::IMPORT_TYPE, null, $selectmanually, self::IMPORT_TYPE_MANUALLY);
        $mform->setType(self::IMPORT_TYPE, PARAM_ALPHANUM);
        $mform->setDefault(self::IMPORT_TYPE, self::IMPORT_TYPE_ALL);

        // Frameworks picker.
        $mform->addElement('autocomplete', self::IMPORT_FROM_SELECTED_FRAMEWORKS, get_string('frameworks', 'tool_organisation'),
            $this->get_all_frameworks_menu(), ['multiple' => true])->setHiddenLabel(true);
        $mform->hideIf(self::IMPORT_FROM_SELECTED_FRAMEWORKS, self::IMPORT_TYPE, 'noteq', self::IMPORT_TYPE_MANUALLY);

        $canimport = ($this->get_import_tenant_id() !== sharedspace::get_shared_space_id());
        $form->add_validation_callback(static function(array $data, array $files) use ($canimport){
            $errors = [];
            if ($data[self::IMPORT_TYPE] === self::IMPORT_TYPE_MANUALLY &&
                        (!is_array($data[self::IMPORT_FROM_SELECTED_FRAMEWORKS]) ||
                            empty($data[self::IMPORT_FROM_SELECTED_FRAMEWORKS]))) {
                $errors[self::IMPORT_FROM_SELECTED_FRAMEWORKS] = get_string('required');
            }
            if (!$canimport) {
                $errors[self::IMPORT_JOBS] = get_string('errorjobscannotbeimported', 'tool_organisation');
            }
            return $errors;
        });

    }

    /**
     * Summary of the import settings for the review step and also for the report page
     *
     * @param bool $importiscompleted
     * @return string
     */
    public function get_summary_for_review_step(bool $importiscompleted): string {
        global $OUTPUT;
        $settings = [];
        $settingsstr = get_string('jobs', 'tool_organisation');
        $settings[] = ['name' => $settingsstr, 'value' => 1];

        $frmstr = get_string('allframeworks', 'tool_organisation');
        $settings[] = ['name' => $frmstr, 'value' => $this->get_import_setting(self::IMPORT_FRAMEWORKS)];

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
        if ($c3 = $this->get_entities_in_workplace_export_file('tool_organisation_job')->count()) {
            $rv[] = get_string('jobs', 'tool_organisation') .
                get_string('entitiescountpostfix', 'tool_wp', $c3);
        }
        return $rv;
    }

    /**
     * Import executed from an ad-hoc task
     *
     * @param string $entity
     */
    public function perform_import(string $entity): void {
        // Can not import jobs from any tenant to Shared space.
        if ($entity === 'tool_organisation_job' && $this->get_import_tenant_id() !== sharedspace::get_shared_space_id()) {
            $this->import_jobs();
        }
    }

    /**
     * Import jobs
     */
    protected function import_jobs() {

        $depids = $this->get_selected_department_frameworks_ids();
        $posids = $this->get_selected_position_frameworks_ids();

        if ($this->get_import_setting(self::IMPORT_FRAMEWORKS)) {
            $this->process_chained_entities(orgstructure::NAME_POSITION_FRAMEWORK, $posids);
            $this->process_chained_entities(orgstructure::NAME_DEPARTMENT_FRAMEWORK, $depids);
        }

        $entities = $this->get_entities_in_workplace_export_file('tool_organisation_job',
            function($record) use ($depids, $posids) {
                return in_array($record['departmentfrmid'], $depids) && in_array($record['positionfrmid'], $posids);
            },
            null);

        $jobmanager = new job_manager();
        foreach ($entities as $job) {
            $job
                ->add_mapping('tenantid', 'tool_tenant')
                ->add_mapping('departmentid', 'tool_organisation_department')
                ->add_mapping('positionid', 'tool_organisation_position')
                ->add_mapping('userid', 'user')
                ->set_validation_callback(function($record, $caller) {
                    $this->add_details_to_log(['userid' => $record['userid'],
                        'originaluserfullname' => $record['userfullname'],
                        'originaldepname' => $record['departmentname'],
                        'originalposname' => $record['positionname']]);
                    if ($record['userid'] > 0) {
                        $this->add_details_to_log(['userfullname' => fullname(\core_user::get_user($record['userid']))]);
                    }
                })
                ->set_import_callback(function($record) use ($jobmanager) {
                    global $DB;
                    $depname = $DB->get_field(department::TABLE, 'name', ['id' => $record['departmentid']]);
                    $posname = $DB->get_field(position::TABLE, 'name', ['id' => $record['positionid']]);
                    $this->add_details_to_log([
                        'userid' => $record['userid'],
                        'userfullname' => fullname(\core_user::get_user($record['userid'])),
                        'departmentname' => $depname,
                        'positionname' => $posname,
                    ]);
                    // Convert the dates to the timezone of this server.
                    $record['startdate'] = helper::get_job_time_for_import($record['startdate']);
                    $record['enddate'] = helper::get_job_time_for_import($record['enddate']);
                    return $jobmanager->create_job((object)$record, false)->get('id');
                })
                ->import($this);
        }
    }

    /**
     * List of all frameworks in the file
     *
     * @return array
     */
    protected function get_all_frameworks_menu() {
        $depfrmids = $this->get_entities_in_workplace_export_file('tool_organisation_job')->get_menu('departmentfrmid');
        $depfrmnames = $this->get_entities_in_workplace_export_file('tool_organisation_job')->get_menu('depframeworkname');
        $posfrmids = $this->get_entities_in_workplace_export_file('tool_organisation_job')->get_menu('positionfrmid');
        $posfrmnames = $this->get_entities_in_workplace_export_file('tool_organisation_job')->get_menu('posframeworkname');
        $rv = [];
        foreach ($depfrmids as $id => $frmid) {
            $rv['d' . $frmid] =
                \tool_organisation\tool_wp\exporter\orgstructure::get_formatted_department_framework_name($depfrmnames[$id]);
        }
        foreach ($posfrmids as $id => $frmid) {
            $rv['p' . $frmid] =
                \tool_organisation\tool_wp\exporter\orgstructure::get_formatted_position_framework_name($posfrmnames[$id]);
        }
        asort($rv);
        return $rv;
    }

    /**
     * List of department frameworks ids (either selected or all)
     *
     * @return array
     */
    protected function get_selected_department_frameworks_ids() {
        $alldepts = array_unique(array_values($this->get_entities_in_workplace_export_file('tool_organisation_job')
            ->get_menu('departmentfrmid')));
        if ($this->get_import_setting(self::IMPORT_TYPE) === self::IMPORT_TYPE_ALL) {
            return $alldepts;
        }
        if ($this->get_import_setting(self::IMPORT_TYPE) === self::IMPORT_TYPE_MANUALLY) {
            $ids = [];
            $selected = $this->get_import_setting(self::IMPORT_FROM_SELECTED_FRAMEWORKS);
            foreach ($alldepts as $id) {
                if (in_array('d' . $id, $selected)) {
                    $ids[] = $id;
                }
            }
            return $ids;
        }
        return [];
    }

    /**
     * List of position frameworks ids (either selected or all)
     *
     * @return array
     */
    protected function get_selected_position_frameworks_ids(): array {
        $allpos = array_unique(array_values($this->get_entities_in_workplace_export_file('tool_organisation_job')
            ->get_menu('positionfrmid')));
        if ($this->get_import_setting(self::IMPORT_TYPE) === self::IMPORT_TYPE_ALL) {
            return $allpos;
        }
        if ($this->get_import_setting(self::IMPORT_TYPE) === self::IMPORT_TYPE_MANUALLY) {
            $ids = [];
            $selected = $this->get_import_setting(self::IMPORT_FROM_SELECTED_FRAMEWORKS);
            foreach ($allpos as $id) {
                if (in_array('p' . $id, $selected)) {
                    $ids[] = $id;
                }
            }
            return $ids ?: $allpos;
        }
        return [];
    }
}
