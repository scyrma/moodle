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
 * Class datasource
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

use core_customfield\handler;
use core_reportbuilder\local\helpers\report as reporthelper;
use core_reportbuilder\local\helpers\schedule;
use core_reportbuilder\local\models\schedule as model;
use Throwable;
use tool_reportbuilder\local\helpers\conditions as conditions_helper;

/**
 * Class datasource. Must be used as a base class for all datasources
 *
 * Datasources should be located in plugindir/classes/tool_reportbuilder/datasources/classname.php
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class datasource extends report_base {

    /**
     * When converting custom report to core reportbuilder which class corresponds to this datasource
     *
     * @return string name of the class extending {@see \core_reportbuilder\datasource}
     * @throws convert_not_implemented
     */
    public function convert_get_datasource_class(): string {
        throw new convert_not_implemented('Datasource class ' .
            get_class($this) . ' must implement ' . __FUNCTION__);
    }

    /**
     * Get the entity name that corresponds to the given entity in the converted datasource
     *
     * @param string $oldentityname entity name in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string entity name in the converted datasource
     */
    public function convert_get_entity_name(string $oldentityname, \core_reportbuilder\datasource $newsource): string {
        $oldentity = $this->addedentities[$oldentityname] ?? null;
        if ($oldentity) {
            $entityclass = $oldentity->convert_get_entity_class();
            if ($entityclass) {
                /** @var \tool_reportbuilder\entity_base $entity */
                $entity = new $entityclass();
                return $entity->get_entity_name();
            }
        }
        try {
            if ($newsource->get_entity_title($oldentityname)) {
                return $oldentityname;
            }
        } catch (\coding_exception $e) {
            null;
        }
        throw new convert_not_implemented('Entity '.$oldentityname.' is not mapped');
    }

    /**
     * When converting this report to core_reportbuilder which column corresponds to the given column
     *
     * @param report_column $oldcolumn the column in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string the full name (unique identifier) of the corresponding column in the converted datasource
     * @throws convert_not_implemented
     */
    public function convert_get_column_unique_identifier(report_column $oldcolumn,
                                                         \core_reportbuilder\datasource $newsource): string {
        $entityname = $this->convert_get_entity_name($oldcolumn->get_entity(), $newsource);
        $newname = $entityname . ':' . $oldcolumn->get_name();
        if ($newsource->get_column($newname)) {
            return $newname;
        }
        $error = "Class '{" . get_class($this) . "}' must implement function '".__FUNCTION__.
            "()' and specify mapping for the column '" . $oldcolumn->get_unique_identifier() . "'";
        if ($entity = $this->addedentities[$oldcolumn->get_entity()] ?? null) {
            $newname = $entityname . ':' . $entity->convert_get_column_name($oldcolumn, $newsource);
            if ($newsource->get_column($newname)) {
                return $newname;
            }
            throw new convert_not_implemented("Class '".get_class($entity).
                "' must implement function 'convert_get_column_name()' and specify mapping for the column '" .
                $oldcolumn->get_name() . "'. Alternatively, " . $error);
        }
        throw new convert_not_implemented($error);
    }

    /**
     * When converting this report to core_reportbuilder which filter corresponds to the given filter
     *
     * @param report_filter $oldfilter the filter in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string the full name (unique identifier) of the corresponding filter in the converted datasource
     * @throws convert_not_implemented
     */
    public function convert_get_filter_unique_identifier(\tool_reportbuilder\report_filter $oldfilter,
                                                         \core_reportbuilder\datasource $newsource): string {
        $entityname = $this->convert_get_entity_name($oldfilter->get_entity(), $newsource);
        $newname = $entityname . ':' . $oldfilter->get_name();
        if ($newsource->get_filter($newname)) {
            return $newname;
        }
        $error = "Class '{" . get_class($this) . "}' must implement function '".__FUNCTION__.
            "()' and specify mapping for the filter '" . $oldfilter->get_unique_identifier() . "'";
        if ($entity = $this->addedentities[$oldfilter->get_entity()] ?? null) {
            $newname = $entityname . ':' . $entity->convert_get_filter_name($oldfilter, $newsource);
            if ($newsource->get_filter($newname)) {
                return $newname;
            }
            throw new convert_not_implemented("Class '".get_class($entity).
                "' must implement function 'convert_get_filter_name()' and specify mapping for the filter '" .
                $oldfilter->get_name() . "'. Alternatively, " . $error);
        }
        throw new convert_not_implemented($error);
    }

    /**
     * When converting this report to core_reportbuilder which condition corresponds to the given condition
     *
     * @param report_filter $oldcondition the condition in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string the full name (unique identifier) of the corresponding condition in the converted datasource
     * @throws convert_not_implemented
     */
    public function convert_get_condition_unique_identifier(\tool_reportbuilder\report_filter $oldcondition,
                                                            \core_reportbuilder\datasource $newsource): string {
        $entityname = $this->convert_get_entity_name($oldcondition->get_entity(), $newsource);
        $newname = $entityname . ':' . $oldcondition->get_name();
        if ($newsource->get_condition($newname)) {
            return $newname;
        }
        if ($entity = $this->addedentities[$oldcondition->get_entity()] ?? null) {
            $newname = $entityname . ':' . $entity->convert_get_condition_name($oldcondition, $newsource);
            if ($newsource->get_condition($newname)) {
                return $newname;
            }
        }
        throw new convert_not_implemented('Condition '.$oldcondition->get_unique_identifier().' is not mapped');
    }

    /**
     * When converting a column with aggregation method, what should be the aggregation method in the converted report
     *
     * @param reportbuilder_column $oldcolumn
     * @return string|null
     * @throws \coding_exception
     */
    public function convert_get_aggregation(reportbuilder_column $oldcolumn): ?string {
        $aggregation = $oldcolumn->get('aggregate'); // TODO make sure all aggregation methods are called the same.
        if ($aggregation === 'unique') {
            $aggregation = null;
        }
        return $aggregation;
    }

    /**
     * Convert a custom report with this datasource to the core reportbuilder
     *
     * Can throw exceptions {@see \tool_reportbuilder\convert_not_implemented} and/or
     * {@see \tool_reportbuilder\convert_not_possible}.
     *
     * @param bool $deleteoriginal
     * @return int id of the core reportbuilder custom report or throws an exception
     * @throws convert_not_implemented
     * @throws convert_not_possible
     */
    public function convert(bool $deleteoriginal = true): int {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        try {
            $hasunique = false;
            foreach ($this->get_active_columns() as $oldcolumn) {
                if ($oldcolumn->get('aggregate') === 'unique') {
                    $hasunique = true;
                }
            }
            $source = $this->convert_get_datasource_class();
            $data = (object)[
                'source' => $source,
                'name' => $this->get_reportname(),
                'uniquerows' => $hasunique,
                'component' => 'tool_tenant',
                'itemid' => $this->get_persistent()->get('tenantid'),
                'area' => $this->get_persistent()->get('shared') ? 'shared' : '',
            ];
            $reportpersistent = reporthelper::create_report($data, false);
            $reportid = $reportpersistent->get('id');

            /** @var \tool_program\reportbuilder\datasource\programs $newreport */
            $newreport = new $source($reportpersistent, []);

            // Convert the columns.
            foreach ($this->get_active_columns() as $oldcolumnpersistent) {
                if (!$oldcolumn = $this->get_columns()[$oldcolumnpersistent->get_unique_identifier()] ?? null) {
                    continue;
                }
                $columnpersistent = reporthelper::add_report_column($reportid,
                    $this->convert_get_column_unique_identifier($oldcolumn, $newreport));
                $columnpersistent->set_many([
                    'aggregation' => $this->convert_get_aggregation($oldcolumnpersistent),
                    'heading' => $oldcolumnpersistent->get('heading'),
                    'sortenabled' => $oldcolumnpersistent->get('sortenabled'),
                    'sortdirection' => $oldcolumnpersistent->get('sortdirection'),
                    'usercreated' => $oldcolumnpersistent->get('usermodified'), // There was no usercreated in tool.
                    'usermodified' => $oldcolumnpersistent->get('usermodified'),
                ]);
                $columnpersistent->save();
            }

            // Convert filters.
            foreach ($this->get_active_filters() as $oldfilterpersistent) {
                $oldfilter = $this->get_filters()[$oldfilterpersistent->get_unique_identifier()];
                $filterpersistent = reporthelper::add_report_filter($reportid,
                    $this->convert_get_filter_unique_identifier($oldfilter, $newreport));
                $filterpersistent->set_many([
                    'heading' => $oldfilterpersistent->get('heading'),
                    'usercreated' => $oldfilterpersistent->get('usermodified'), // There was no usercreated in tool.
                    'usermodified' => $oldfilterpersistent->get('usermodified'),
                ]);
                $filterpersistent->save();
            }

            // Convert conditions.
            foreach ($this->get_active_conditions() as $oldconditionpersistent) {
                $oldcondition = $this->get_conditions()[$oldconditionpersistent->get_unique_identifier()];
                $conditionpersistent = reporthelper::add_report_condition($reportid,
                    $this->convert_get_condition_unique_identifier($oldcondition, $newreport));
                $newcondition = $newreport->get_condition($conditionpersistent->get('uniqueidentifier'));
                $newcondition->set_persistent($conditionpersistent);
                $conditionpersistent->set_many([
                    'heading' => $oldconditionpersistent->get('heading'),
                    'usercreated' => $oldconditionpersistent->get('usermodified'), // There was no usercreated in tool.
                    'usermodified' => $oldconditionpersistent->get('usermodified'),
                ]);
                $conditionpersistent->save();

                $values = $this->convert_condition_values($oldconditionpersistent, $newcondition, $newreport) +
                    $newreport->get_condition_values();
                $newreport->set_condition_values($values);
            }

            // Map old tool RB audience IDs to new core RB audience IDs.
            $audiencesmap = [];

            // Convert audiences.
            $audiences = $DB->get_records('tool_reportbuilder_audiences', ['reportid' => $this->get_id()]);
            foreach ($audiences as $audience) {
                $oldaudience = audience_base::instance(0, $audience);
                $newaudienceclass = $oldaudience->convert_get_audience_class();
                $newadudience = $newaudienceclass::create($reportid, (array)json_decode($audience->configdata));
                $audiencesmap[$audience->id] = $newadudience->get_persistent()->get('id');
            }

            // Convert schedules.
            $schedules = $DB->get_records('tool_reportbuilder_schedule', ['reportid' => $this->get_id()]);
            foreach ($schedules as $schedule) {
                unset ($schedule->id);
                $schedule->reportid = $reportid;
                $schedule->userviewas = $schedule->usercreated;
                $schedule->timescheduled = $schedule->scheduled;
                // Tool RB uses -1 for 'never sent' and core RB uses 0.
                $schedule->timelastsent = max(0, $schedule->lastsenton);
                $schedule->timenextsend = $schedule->nextsend;

                switch ($schedule->recurrence) {
                    case constants::RECURRENCE_NONE:
                        $schedule->recurrence = model::RECURRENCE_NONE;
                        break;
                    case constants::RECURRENCE_DAILY:
                        $schedule->recurrence = model::RECURRENCE_DAILY;
                        break;
                    case constants::RECURRENCE_WEEKLY:
                        $schedule->recurrence = model::RECURRENCE_WEEKLY;
                        break;
                    case constants::RECURRENCE_MONTHLY:
                        $schedule->recurrence = model::RECURRENCE_MONTHLY;
                        break;
                    case constants::RECURRENCE_ANNUALLY:
                        $schedule->recurrence = model::RECURRENCE_ANNUALLY;
                        break;
                    case constants::RECURRENCE_DAILY_WEEKDAY:
                        $schedule->recurrence = model::RECURRENCE_WEEKDAYS;
                        break;
                }

                // Map each schedule to the new audiences.
                $newaudiences = [];
                $oldaudiences = json_decode($schedule->audiences);
                foreach ($oldaudiences as $oldaudience) {
                    if (isset($audiencesmap[$oldaudience])) {
                        $newaudiences[] = $audiencesmap[$oldaudience];
                    }
                }
                $schedule->audiences = json_encode($newaudiences);

                schedule::create_schedule($schedule);
            }
        } catch (Throwable $e) {
            $transaction->rollback($e);
        }

        $transaction->allow_commit();

        set_config('converted-'.$this->get_id(), $reportid, 'tool_reportbuilder');
        if ($deleteoriginal) {
            $this->get_persistent()->delete();
        }
        \core_reportbuilder\manager::reset_caches();
        return $reportid;
    }

    /**
     * Converts values of the condition from a tool_reportbuilder report to the core_reportbuilder report
     *
     * @param local\models\reportbuilder_conditions $oldconditionpers
     * @param \core_reportbuilder\local\report\filter $newcondition
     * @param \core_reportbuilder\datasource $newreport
     * @return array
     * @throws convert_not_implemented
     */
    protected function convert_condition_values(\tool_reportbuilder\local\models\reportbuilder_conditions $oldconditionpers,
                                                \core_reportbuilder\local\report\filter $newcondition,
                                                \core_reportbuilder\datasource $newreport): array {
        $oldcondition = $this->get_conditions()[$oldconditionpers->get_unique_identifier()];
        $oldinstance = filter_base::create($oldcondition->get_classname(), $oldcondition,
            $oldconditionpers->get('id'), $oldconditionpers->get('heading'));

        $conditionshelper = new conditions_helper($this);
        $oldvalues = $conditionshelper->get_condition_values($oldconditionpers->get_unique_identifier());

        $newinstance = \core_reportbuilder\local\filters\base::create($newcondition);

        return $oldinstance->convert_condition_values($oldvalues, $newinstance);
    }

    /**
     * Get the report conditions definition.
     *
     * @return report_filter[]
     */
    final public function get_conditions() {
        $conditions = parent::get_conditions();
        return $conditions;
    }
}
