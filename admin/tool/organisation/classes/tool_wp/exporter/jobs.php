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

namespace tool_organisation\tool_wp\exporter;

use tool_organisation\department;
use tool_organisation\helper;
use tool_organisation\job;
use tool_organisation\permission;
use tool_organisation\position;
use tool_tenant\hierarchy;
use tool_tenant\tenancy;
use tool_wp\local\exportimport\forms\export_settings_form;

defined('MOODLE_INTERNAL') || die();

/**
 * Export for jobs
 *
 * @package     tool_organisation
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class jobs extends \tool_wp\exporter_base {

    /** @var string */
    const EXPORT_CONTENT = 'export_content';
    /** @var string */
    const EXPORT_FRAMEWORKS = 'export_frameworks';

    /** @var string */
    const EXPORT_TYPE = 'export_instances';
    /** @var string */
    const EXPORT_TYPE_CURRENT = 'current';
    /** @var string */
    const EXPORT_TYPE_ALL = 'all';
    /** @var string */
    const EXPORT_TYPE_MANUALLY = 'selectedframeworks';
    /** @var string */
    const EXPORT_FROM_SELECTED_FRAMEWORKS = 'select_frameworks';

    /** @var array */
    protected $positionframeworks = null;
    /** @var array */
    protected $departmentframeworks = null;

    /**
     * Exporter format
     *
     * @return int
     */
    public function get_format(): int {
        return self::FORMAT_WORKPLACE;
    }

    /**
     * Exporter name
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('exporterjobs', 'tool_organisation');
    }

    /**
     * Exporter description to show in the list of available exporters
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('exporterjobsdesc', 'tool_organisation');
    }

    /**
     * Exporter icon url
     *
     * @return string
     */
    public function get_icon_url(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('menu/organization_structure', 'theme')->out(false);
    }

    /**
     * Allows to mark exporter as not available, checks capabilities and entry point
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
                self::ENTITY_INDIVIDUALEXPORT => static function(array $ids, array $settings): array {
                    $defaults = [
                        self::EXPORT_CONTENT => 1,
                        self::EXPORT_FRAMEWORKS => 1,
                    ];

                    return array_intersect_key($settings, $defaults) + $defaults + [
                        self::EXPORT_TYPE => self::EXPORT_TYPE_CURRENT,
                    ];
                },
                self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                    $a = (object)['position' => $record['positionname'], 'department' => $record['departmentname']];
                    return $record['userfullname'] . ' - ' .
                        get_string('positionanddepartmentdisplay', 'tool_organisation', $a);
                },
            ]);
    }

    /**
     * Export configuration form
     *
     * To retrieve QuickForm:
     *   $mform = $form->get_quick_form();
     * To add form validation:
     *   $form->add_validation_callback(function(array $data, array $file) { return []; });
     *
     * @param export_settings_form $form
     */
    public function add_to_options_form(export_settings_form $form): void {
        $mform = $form->get_quick_form();
        $mform->addElement('header', 'exporterheader', get_string('content', 'tool_wp'));
        $mform->setExpanded('exporterheader');

        $mform->addElement('advcheckbox', self::EXPORT_CONTENT, get_string('jobs', 'tool_organisation'));
        $mform->setDefault(self::EXPORT_CONTENT, 1);
        $form->freeze_at(self::EXPORT_CONTENT, 1);

        $mform->addElement('advcheckbox', self::EXPORT_FRAMEWORKS, get_string('allframeworks', 'tool_organisation'));
        $mform->setDefault(self::EXPORT_FRAMEWORKS, 1);
        if (!(orgstructure::can_export_departments() && orgstructure::can_export_positions())) {
            $form->freeze_at(self::EXPORT_FRAMEWORKS, 0);
        }

        $mform->addElement('header', 'headerorgstructure', get_string('instances', 'tool_wp'));
        $mform->setExpanded('headerorgstructure');

        $selectall = get_string('selectalljobs', 'tool_organisation');
        $selectcurrent = get_string('selectallactivejobs', 'tool_organisation');
        $selectmanually = get_string('selectalljobsinframeworks', 'tool_organisation');
        $mform->addElement('radio', self::EXPORT_TYPE, null, $selectcurrent, self::EXPORT_TYPE_CURRENT);
        $mform->addElement('radio', self::EXPORT_TYPE, null, $selectall, self::EXPORT_TYPE_ALL);
        $mform->addElement('radio', self::EXPORT_TYPE, null, $selectmanually, self::EXPORT_TYPE_MANUALLY);
        $mform->setType(self::EXPORT_TYPE, PARAM_ALPHANUM);
        $mform->setDefault(self::EXPORT_TYPE, self::EXPORT_TYPE_CURRENT);

        // Frameworks picker.
        $mform->addElement('autocomplete', self::EXPORT_FROM_SELECTED_FRAMEWORKS, get_string('frameworks', 'tool_organisation'),
            $this->get_all_frameworks_menu(), ['multiple' => true])->setHiddenLabel(true);
        $mform->hideIf(self::EXPORT_FROM_SELECTED_FRAMEWORKS, self::EXPORT_TYPE, 'noteq', self::EXPORT_TYPE_MANUALLY);

        $form->add_validation_callback(static function(array $data, array $files) {
            $errors = [];
            if ($data[self::EXPORT_TYPE] === self::EXPORT_TYPE_MANUALLY &&
                    (empty($data[self::EXPORT_FROM_SELECTED_FRAMEWORKS]) ||
                        !is_array($data[self::EXPORT_FROM_SELECTED_FRAMEWORKS]))) {
                $errors[self::EXPORT_FROM_SELECTED_FRAMEWORKS] = get_string('required');
            }
            return $errors;
        });

    }

    /**
     * Summary of the export settings for the review step and also for the report page
     *
     * @param bool $exportcompleted
     * @return string
     */
    public function get_summary_for_review_step(bool $exportcompleted): string {
        global $OUTPUT;
        $settings = [];
        $settingsstr = get_string('jobs', 'tool_organisation');
        $settings[] = ['name' => $settingsstr, 'value' => 1];

        $frmstr = get_string('allframeworks', 'tool_organisation');
        $settings[] = ['name' => $frmstr, 'value' => $this->get_export_setting(self::EXPORT_FRAMEWORKS)];

        return $OUTPUT->render_from_template(
            'tool_wp/exportimport_summary',
            ['settings' => $settings]
        );
    }

    /**
     * Returns the list of entities that will be exported
     *
     * Implement also {@see get_summary_for_review_step()}
     *
     * @param string $entityname
     * @return array array where each element is array that can be passed through self::ENTITY_INSTANCENAME_FOR_REVIEW
     *     callback
     */
    public function get_instances_for_review_step(string $entityname): array {
        if ($entityname === 'tool_organisation_job') {
            return $this->get_jobs();
        }
        return [];
    }

    /**
     * Get list of jobs scheduled for the export
     *
     * @return array
     */
    protected function get_jobs(): array {
        global $DB;
        $sql = '';
        $params = [];
        if ($this->get_export_setting(self::EXPORT_TYPE) === self::EXPORT_TYPE_MANUALLY) {
            $sqls = ['p' => [], 'd' => []];
            foreach ($this->get_export_setting(self::EXPORT_FROM_SELECTED_FRAMEWORKS) as $id) {
                if (preg_match('/^([p|d])(\d+)$/', $id, $matches)) {
                    $paramname = \tool_wp\db::generate_param_name();
                    $sqls[$matches[1]][] = $DB->sql_like($matches[1] . '.path', ':' . $paramname);
                    $params[$paramname] = "/{$matches[2]}/%";
                }
            }
            if ($sqls['p']) {
                $sql .= ' AND ((' . join(') OR (', $sqls['p']) . '))';
            }
            if ($sqls['d']) {
                $sql .= ' AND ((' . join(') OR (', $sqls['d']) . '))';
            }
        }

        if ($this->get_export_setting(self::EXPORT_TYPE) === self::EXPORT_TYPE_CURRENT) {
            $ptime = \tool_wp\db::generate_param_name();
            $sql .= "AND (j.enddate = 0 OR j.enddate >= :{$ptime})";
            $params[$ptime] = helper::round_time(time());
        }

        $viewfullnames = has_capability('moodle/site:viewfullnames', \context_system::instance());
        list($usersql, $paramsuser) = \tool_reportbuilder\db::sql_fullname('u', $viewfullnames);
        $params += $paramsuser + ['tenantid' => $this->get_export_tenant_id() ?: tenancy::get_tenant_id()];
        $sqldf = helper::get_framework_id_sql("d.path");
        $sqlpf = helper::get_framework_id_sql("p.path");
        $sqluser = tenancy::get_users_subquery(false, true, 'u.id', $this->get_export_tenant_id());
        $sql = "SELECT j.*, $usersql AS userfullname,
                    d.name AS departmentname, p.name AS positionname,
                    df.id AS departmentfrmid, df.name AS depframeworkname,
                    pf.id AS positionfrmid, pf.name AS posframeworkname
                FROM {".job::TABLE."} j
                JOIN {user} u ON $sqluser u.id = j.userid
                JOIN {".department::TABLE."} d ON d.id = j.departmentid
                JOIN {".position::TABLE."} p ON p.id = j.positionid
                JOIN {".department::TABLE."} df ON df.id = $sqldf
                JOIN {".position::TABLE."} pf ON pf.id = $sqlpf
                WHERE j.tenantid = :tenantid $sql";
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Performs the export
     *
     * @return void
     */
    public function perform_export(): void {

        $jobs = $this->get_jobs();
        if ($this->get_export_setting(self::EXPORT_FRAMEWORKS) && orgstructure::can_export_departments()
                && orgstructure::can_export_positions()) {
            $depframeworks = [];
            $posframeworks = [];
            foreach ($jobs as $record) {
                $depframeworks[$record->departmentfrmid] = 1;
                $posframeworks[$record->positionfrmid] = 1;
            }
            // Some jobs might be using shared department or position frameworks.
            $settings = [orgstructure::INCLUDE_SHARED_ENTITIES => 1];
            $this->process_chained_entities(orgstructure::NAME_DEPARTMENT_FRAMEWORK, array_keys($depframeworks), $settings);
            $this->process_chained_entities(orgstructure::NAME_POSITION_FRAMEWORK, array_keys($posframeworks), $settings);
        }

        foreach ($jobs as $record) {
            // Store the "startdate" and "enddate" as YYYY-MM-DD because they may be restored into a different TZ.
            $record->startdate = helper::get_job_time_for_export($record->startdate);
            $record->enddate = helper::get_job_time_for_export($record->enddate);
            $this->prepare_data_for_workplace_export(job::TABLE, (array)$record)
                ->exclude_fields(['timecreated', 'timemodified', 'deppath', 'pospath'])
                ->add_mappings('tenantid', 'tool_tenant')
                ->add_mappings('departmentid', 'tool_organisation_department')
                ->add_mappings('positionid', 'tool_organisation_position')
                ->add_mappings('userid', 'user')
                ->export();
        }
    }

    /**
     * Returns all position frameworks present in the system and available for export (as a menu)
     *
     * @return array
     */
    protected function get_all_position_frameworks(): array {
        if ($this->positionframeworks === null) {
            // Converting to array the first time to ensure we are returning consistent type and the result of this method
            // called previously will be used and prevent hitting the database again.
            $this->positionframeworks = [];
            $tenantid = $this->get_export_tenant_id() ?: tenancy::get_tenant_id();
            [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid', 'shared=1',
                $tenantid);
            $positionframeworks = position::get_records_select($sql . ' AND parentid IS NULL', $params);
            foreach ($positionframeworks as $pf) {
                $this->positionframeworks[$pf->get('id')] = $pf->get_formatted_name();
            }
        }
        return $this->positionframeworks;
    }

    /**
     * Returns all department frameworks present in the system and available for export (as a menu)
     *
     * @return array
     */
    protected function get_all_department_frameworks(): array {
        if ($this->departmentframeworks === null) {
            // Converting to array the first time to ensure we are returning consistent type and the result of this method
            // called previously will be used and prevent hitting the database again.
            $this->departmentframeworks = [];
            $tenantid = $this->get_export_tenant_id() ?: tenancy::get_tenant_id();
            [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid', 'shared=1',
                $tenantid);
            $departmentframeworks = department::get_records_select($sql . ' AND parentid IS NULL', $params);
            foreach ($departmentframeworks as $df) {
                $this->departmentframeworks[$df->get('id')] = $df->get_formatted_name();
            }
        }
        return $this->departmentframeworks;
    }

    /**
     * Returns list of all frameworks available for export (as a menu)
     *
     * @return array
     */
    protected function get_all_frameworks_menu(): array {
        $rv = [];
        foreach ($this->get_all_department_frameworks() as $id => $name) {
            $rv['d'.$id] = orgstructure::get_formatted_department_framework_name($name);
        }
        foreach ($this->get_all_position_frameworks() as $id => $name) {
            $rv['p'.$id] = orgstructure::get_formatted_position_framework_name($name);
        }
        \core_collator::asort($rv);
        return $rv;
    }

}
