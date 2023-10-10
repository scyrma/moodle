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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_organisation\tool_wp\exporter;

use core_reportbuilder\local\helpers\database;
use core_user\fields;
use tool_organisation\department;
use tool_organisation\helper;
use tool_organisation\job;
use tool_organisation\permission;
use tool_organisation\position;
use tool_tenant\tenancy;
use tool_wp\local\exportimport\forms\export_settings_form;

/**
 * Export for jobs (CSV format)
 *
 * @package     tool_organisation
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Roberto Bravo <roberto.bravo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class jobs_csv extends jobs {

    /** @var string */
    const NAME_JOB = 'tool_organisation_job';

    /**
     * Exporter format
     *
     * @return int
     */
    public function get_format(): int {
        return self::FORMAT_CSV;
    }

    /**
     * Exporter name
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('exporterjobscsv', 'tool_organisation');
    }

    /**
     * Allows to mark exporter as not available, checks capabilities and entry point
     *
     * @return bool
     */
    public function is_available(): bool {
        return !strlen($this->entrypoint) && permission::can_assign_job_to_anybody();
    }

    /**
     * Register all entities that can be exported by this exporter, all potential errors and notices
     *
     * @return void
     */
    protected function initialise(): void {
        $this->register_entity(self::NAME_JOB, [
            self::ENTITY_INSTANCENAME_FOR_REVIEW => function(array $record) {
                if ($record['id'] === 0) {
                    // No jobs records. We set id to 0 for dummy record that is needed
                    // to produce header row in csv.
                    return '';
                }
                $a = (object)['position' => $record['positionname'], 'department' => $record['departmentname']];
                return $record['userfullname'] . ' - ' .
                    get_string('positionanddepartmentdisplay', 'tool_organisation', $a);
            },
        ]);
    }

    /**
     * Export configuration form
     *
     * @param export_settings_form $form
     * @return void
     */
    public function add_to_options_form(export_settings_form $form): void {
        $mform = $form->get_quick_form();
        $mform->addElement('header', 'exporterheader', get_string('content', 'tool_wp'));
        $mform->setExpanded('exporterheader');

        $mform->addElement('advcheckbox', self::EXPORT_CONTENT, get_string('jobs', 'tool_organisation'));
        $mform->setDefault(self::EXPORT_CONTENT, 1);
        $form->freeze_at(self::EXPORT_CONTENT, 1);

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

        return $OUTPUT->render_from_template(
            'tool_wp/exportimport_summary',
            ['settings' => $settings]
        );
    }

    /**
     * Get list of jobs that need to be exported
     *
     * @return array
     */
    protected function get_jobs(): array {
        global $DB;
        $exporttypesql = '';
        $params = [];
        if ($this->get_export_setting(self::EXPORT_TYPE) === self::EXPORT_TYPE_MANUALLY) {
            $sqls = ['p' => [], 'd' => []];
            foreach ($this->get_export_setting(self::EXPORT_FROM_SELECTED_FRAMEWORKS) as $id) {
                if (preg_match('/^([p|d])(\d+)$/', $id, $matches)) {
                    $paramname = database::generate_param_name();
                    $sqls[$matches[1]][] = $DB->sql_like($matches[1] . '.path', ':' . $paramname);
                    $params[$paramname] = "/{$matches[2]}/%";
                }
            }
            if ($sqls['p']) {
                $exporttypesql .= ' AND ((' . join(') OR (', $sqls['p']) . '))';
            }
            if ($sqls['d']) {
                $exporttypesql .= ' AND ((' . join(') OR (', $sqls['d']) . '))';
            }
        }

        if ($this->get_export_setting(self::EXPORT_TYPE) === self::EXPORT_TYPE_CURRENT) {
            $ptime = database::generate_param_name();
            $exporttypesql .= "AND (j.enddate = 0 OR j.enddate >= :{$ptime})";
            $params[$ptime] = helper::round_time(time());
        }

        $context = \context_system::instance();
        // We add user/structure fullname properties so they can be referenced in the importer.
        $viewfullnames = has_capability('moodle/site:viewfullnames', $context);
        [$usersql, $userparams] = fields::get_sql_fullname('u', $viewfullnames);
        $params += $userparams + ['tenantid' => $this->get_export_tenant_id() ?: tenancy::get_tenant_id()];

        // Get user identity sql. Required capability (moodle/site:viewuseridentity) is checked in 'fields' api functions.
        $userfieldsapi = fields::for_identity($context);
        $useridentitysql = $userfieldsapi->get_sql('u', true, '', '', false);
        $params += $useridentitysql->params;

        $sqldf = helper::get_framework_id_sql("d.path");
        $sqlpf = helper::get_framework_id_sql("p.path");
        $sqluser = tenancy::get_users_subquery(false, true, 'u.id', $this->get_export_tenant_id());
        $sql = "SELECT j.id, j.userid, $usersql AS userfullname, {$useridentitysql->selects},
                       j.startdate, j.enddate,
                       d.idnumber AS departmentidnumber, d.path AS departmentpath,
                       p.idnumber AS positionidnumber, p.path AS positionpath,
                       df.id AS depfrid, pf.id AS posfrid,
                       p.name AS positionname, d.name AS departmentname
                FROM {".job::TABLE."} j
                JOIN {user} u ON $sqluser u.id = j.userid
                JOIN {".department::TABLE."} d ON d.id = j.departmentid
                JOIN {".position::TABLE."} p ON p.id = j.positionid
                JOIN {".department::TABLE."} df ON df.id = $sqldf
                JOIN {".position::TABLE."} pf ON pf.id = $sqlpf
                {$useridentitysql->joins}
                WHERE j.tenantid = :tenantid $exporttypesql
                ORDER BY j.userid";
        $result = $DB->get_records_sql($sql, $params);

        if (empty($result)) {
            // For empty jobs listing we need empty record, so that CSV header is populated.
            return [(object) [
                'id' => 0,
                'userid' => '',
                'userfullname' => '',
                'startdate' => 0,
                'enddate' => 0,
                'departmentidnumber' => '',
                'departmentpath' => '',
                'positionidnumber' => '',
                'positionpath' => '',
                'depfrid' => 0,
                'posfrid' => 0,
                'positionname' => '',
                'departmentname' => '',
            ], ];
        }
        return $result;
    }

    /**
     * Performs the export
     *
     * @return void
     */
    public function perform_export(): void {
        $excludefields = ['id', 'depfrid', 'posfrid', 'departmentname', 'positionname'];
        $depidnumbermap = [];
        $posidnumbermap = [];

        $buildpath = function (string $path, array $idnumbermap, int $frid): string {
            // Remove frameworkid from the path and replace elements with idnumbers where possible.
            $path = preg_replace("/^\/{$frid}\//", '', $path);
            $pathparts = explode('/', $path);
            foreach ($pathparts as $key => $pathpart) {
                if (!empty($idnumbermap[(int) $pathpart])) {
                    $pathparts[$key] = $idnumbermap[(int) $pathpart];
                }
            }
            return join('/', $pathparts);
        };

        foreach ($this->get_jobs() as $record) {
            $record->jobid = $record->id ?: '';

            if ($record->depfrid && !isset($depidnumbermap[$record->depfrid])) {
                $manager = new \tool_organisation\department_manager();
                $depframework = $manager->get_department($record->depfrid, false);
                $depidnumbermap[$record->depfrid] = $depframework->get('idnumber');

                $children = $manager->get_all_children($depframework, false);
                foreach ($children as $child) {
                    $depidnumbermap[$child->get('id')] = $child->get('idnumber');
                }
            }
            $record->departmentpath = $buildpath($record->departmentpath, $depidnumbermap, $record->depfrid);

            if ($record->posfrid && !isset($posidnumbermap[$record->posfrid])) {
                $manager = new \tool_organisation\position_manager();
                $posframework = $manager->get_position($record->posfrid, false);
                $posidnumbermap[$record->posfrid] = $posframework->get('idnumber');

                $children = $manager->get_all_children($posframework, false);
                foreach ($children as $child) {
                    $posidnumbermap[$child->get('id')] = $child->get('idnumber');
                }
            }
            $record->positionpath = $buildpath($record->positionpath, $posidnumbermap, $record->posfrid);

            // Store the "startdate" and "enddate" as YYYY-MM-DD because they may be restored into a different TZ.
            $record->startdate = helper::get_job_time_for_export($record->startdate);
            $record->enddate = helper::get_job_time_for_export($record->enddate);
            $this->prepare_data_for_csv_export(self::NAME_JOB, (array)$record)
                ->store_instances_for_review()
                ->exclude_fields($excludefields)
                ->export();
        }
    }
}
