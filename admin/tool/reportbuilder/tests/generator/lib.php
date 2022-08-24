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
 * tool_reportbuilder data generator.
 *
 * @package    tool_reportbuilder
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use core_reportbuilder\local\models\filter as filter_model;
use tool_reportbuilder\audience_base;
use tool_reportbuilder\convert_not_implemented;
use tool_reportbuilder\convert_not_possible;
use tool_reportbuilder\datasource;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\filter_base;
use tool_reportbuilder\manager;
use tool_reportbuilder\report_base;
use tool_reportbuilder\reportbuilder_column;
use tool_reportbuilder\local\models\reportbuilder_conditions;
use tool_reportbuilder\local\models\schedule;
use tool_reportbuilder\local\report\reportbuilder_filter;

/**
 * tool_reportbuilder data generator class.
 *
 * @package    tool_reportbuilder
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_generator extends component_generator_base {

    /**
     * Number of report instances created
     * @var int
     */
    protected $reportcount = 0;

    /**
     * Number of schedule instances created
     * @var int
     */
    protected $schedulecount = 0;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->reportcount = 0;
        $this->schedulecount = 0;
    }

    /**
     * Creates new report
     *
     * @param array|stdClass $record
     * @return report_base
     * @throws coding_exception
     */
    public function create_report($record): report_base {
        $record = (array)$record;

        // Required properties.
        if (!array_key_exists('source', $record) || !manager::report_source_valid($record['source'], datasource::class)) {
            throw new coding_exception('Record must contain "source" property that is a valid datasource class name');
        }

        // Populate defaults.
        if (!array_key_exists('name', $record)) {
            $record['name'] = 'New report ' . (++$this->reportcount);
        }
        $record += ['type' => \tool_reportbuilder\constants::TYPE_DATASOURCE, 'description' => '',
            'adddefault' => 1];
        $obj = \tool_reportbuilder\manager::save_report((object)$record, false);

        return \tool_reportbuilder\manager::get_report_from_persistent($obj);
    }

    /**
     * Create report audience record
     *
     * Note: Requires reportid, classname and configdata.
     *
     * @param array|stdClass $record
     * @return audience_base
     * @throws coding_exception
     */
    public function create_audience($record): audience_base {
        $record = (array)$record;

        // Required properties.
        if (!array_key_exists('reportid', $record)) {
            throw new coding_exception('Report id must be specified when creating an audience');
        }
        if (!array_key_exists('classname', $record)) {
            throw new coding_exception('Audience classname must be specified when creating an audience');
        }
        if (!array_key_exists('configdata', $record)) {
            throw new coding_exception('Audience configdata must be specified when creating an audience');
        }

        $record['classname'] = \tool_reportbuilder\tool_reportbuilder\audiences\manual::class;
        return ($record['classname'])::create($record['reportid'], $record['configdata']);
    }

    /**
     * Add a column to the report
     *
     * @param report_base $report
     * @param string $columnkey
     * @return reportbuilder_column
     */
    public function add_column(report_base $report, string $columnkey) : reportbuilder_column {
        return \tool_reportbuilder\local\helpers\columns::add_column_from_key($report, $columnkey);
    }

    /**
     * Add a condition to the report
     *
     * @param report_base $report
     * @param string $conditionkey
     * @return reportbuilder_conditions
     */
    public function add_condition(report_base $report, string $conditionkey): reportbuilder_conditions {
        return \tool_reportbuilder\local\helpers\conditions::add_condition_from_key($report, $conditionkey);
    }

    /**
     * Removes a condition from the report
     *
     * @param int $conditionid
     */
    public function remove_condition(int $conditionid) {
        tool_reportbuilder\local\helpers\conditions::remove_condition($conditionid);
    }

    /**
     * Sets conditions values for the report
     *
     * @param int $reportid
     * @param array $values
     */
    public function set_report_conditions_values(int $reportid, array $values) {
        $helper = new tool_reportbuilder\local\helpers\conditions(\tool_reportbuilder\manager::get_report($reportid));
        $helper->add_report_conditions(json_encode($values));
    }

    /**
     * Add a filter to the report
     *
     * @param report_base $report
     * @param string $filterkey
     * @return reportbuilder_filter
     */
    public function add_filter(report_base $report, string $filterkey) : reportbuilder_filter {
        return \tool_reportbuilder\local\helpers\filters::add_filter_from_key($report, $filterkey);
    }

    /**
     * Remove a filter from the report
     *
     * @param int $filterid
     */
    public function remove_filter(int $filterid) {
        $filterpersistent = new \tool_reportbuilder\local\report\reportbuilder_filter($filterid);
        $filterpersistent->delete();
    }

    /**
     * Set filter values
     *
     * @param int $reportid
     * @param array $values
     */
    public function set_report_filters_values(int $reportid, array $values) {
        \tool_reportbuilder\local\helpers\filters::set_filter($reportid, (object)$values);
    }

    /**
     * Creates new schedule
     *
     * @param array|stdClass $record
     * @return schedule
     */
    public function create_schedule($record): schedule {
        global $USER;

        $record = (array)$record;

        // Required properties.
        if (!array_key_exists('reportid', $record)) {
            throw new coding_exception('Report id must be specified when creating a schedule');
        }

        // Populate defaults.
        if (!array_key_exists('name', $record)) {
            $record['name'] = 'New schedule ' . (++$this->schedulecount);
        }
        if (!array_key_exists('format', $record)) {
            $record['format'] = 'excel';
        }
        if (!array_key_exists('subject', $record)) {
            $record['subject'] = 'Subject';
        }
        if (!array_key_exists('message', $record)) {
            $record['message'] = 'Message';
        }
        if (!array_key_exists('usercreated', $record)) {
            $record['usercreated'] = $USER->id;
        }
        if (!array_key_exists('audiences', $record)) {
            $record['audiences'] = json_encode([]);
        }
        if (!array_key_exists('recurrence', $record)) {
            $record['recurrence'] = \tool_reportbuilder\constants::RECURRENCE_NONE;
        }

        return \tool_reportbuilder\local\helpers\schedules::add_schedule((object) $record);
    }

    /**
     * Create a role if it does not exist
     *
     * @param string $shortname
     * @param string $capability
     * @return int
     */
    protected function get_or_create_role(string $shortname, string $capability) : int {
        global $DB;
        if ($roleid = $DB->get_field('role', 'id', ['shortname' => $shortname])) {
            return $roleid;
        }
        $roleid = create_role($shortname, $shortname, "");
        $context = context_system::instance();
        assign_capability($capability, CAP_ALLOW, $roleid, $context->id);
        return $roleid;
    }

    /**
     * Assigns 'tool/reportbuilder:read' capability to a user.
     *
     * Create a dummy role with the capability allowed.
     *
     * @param int $userid The ID of the user to assign the capability.
     */
    public function assign_read_capability(int $userid): void {
        $roleid = $this->get_or_create_role('tool_reportbuilder_read', 'tool/reportbuilder:read');
        role_assign($roleid, $userid, context_system::instance()->id);
    }

    /**
     * Assigns 'tool/reportbuilder:edit' capability.
     *
     * Create a dummy role with the capability allowed.
     *
     * @param int $userid The ID of the user to assign the capability.
     */
    public function assign_edit_capability(int $userid): void {
        $roleid = $this->get_or_create_role('tool_reportbuilder_edit', 'tool/reportbuilder:edit');
        role_assign($roleid, $userid, context_system::instance()->id);
    }

    /**
     * Assign a job with view reports permissions.
     *
     * @param int $userid
     * @param array $subordinates list of userids of other users this user should have be a manager of
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function assign_job_with_report_permissions(int $userid, array $subordinates) : void {
        $tenantid = \tool_tenant\tenancy::get_tenant_id($userid);

        /** @var tool_organisation_generator $orggenerator */
        $orggenerator = advanced_testcase::getDataGenerator()->get_plugin_generator('tool_organisation');

        $pf = $orggenerator->create_position(['tenantid' => $tenantid]);
        $pa = $orggenerator->create_position(['parentid' => $pf->id, 'globalmanager' => 1, 'globalpermissions' => 2]);
        $pb = $orggenerator->create_position(['parentid' => $pa->id]);
        $df = $orggenerator->create_department(['tenantid' => $tenantid]);
        $da = $orggenerator->create_department(['parentid' => $df->id]);

        $orggenerator->assign_job((object)['userid' => $userid,
            'positionid' => $pa->id, 'departmentid' => $da->id]);

        foreach ($subordinates as $subordinateid) {
            $orggenerator->assign_job((object)['userid' => $subordinateid,
                'positionid' => $pb->id, 'departmentid' => $da->id]);
        }
    }

    /**
     * Helper method to call private aggregation class method statically, returning all aggregation types supported
     * for the given column type
     *
     * @uses \tool_reportbuilder\local\helpers\aggregation::get_allowed_aggregations()
     * @param int $dbtype
     * @param array $exclude exclude these types
     * @return array
     */
    public function get_allowed_aggregation_types(?int $dbtype, array $exclude = []) : array {
        $rc = new \ReflectionClass(\tool_reportbuilder\local\helpers\aggregation::class);
        $rcm = $rc->getMethod('get_allowed_aggregations');
        $rcm->setAccessible(true);

        return $rcm->invokeArgs(null, [$dbtype, $exclude]);
    }

    /**
     * Creates known user filter if present
     *
     * @param int $reportid
     * @return \tool_reportbuilder\filter_base[]
     * @throws \coding_exception
     */
    private function get_active_conditions(int $reportid) {
        // TODO this code is copied from tool_reportbuilder\form\conditions::get_active_conditions and needs to be simpler.
        global $PAGE;
        $fields = [];
        $output = $PAGE->get_renderer('tool_reportbuilder');
        $source = \tool_reportbuilder\manager::get_report($reportid);
        $activeconditions = $source->get_active_conditions();
        $conditions = $source->get_conditions();
        $conditionsinuse = tool_reportbuilder\local\helpers\conditions::get_conditions($activeconditions, $conditions, $output);
        foreach ($conditionsinuse as $keyfilter => $filter) {
            $filterclass = $filter->classname;
            $reportcondition = $conditions[$keyfilter];
            /** @var \tool_reportbuilder\filter_base $userfilter */
            $userfilter = \tool_reportbuilder\filter_base::create($filterclass, $reportcondition, $filter->id, $filter->heading,
                $filter->default, true);
            $fields[$keyfilter] = $userfilter;
        }

        return $fields;
    }

    /**
     * Get test values for all conditions added to the report
     *
     * @param int $reportid
     * @param bool $extended
     * @return array
     */
    private function get_test_values_for_condition(int $reportid, bool $extended = false) {
        $filters = $this->get_active_conditions($reportid);
        $filter = reset($filters);
        return $filter->get_test_values($extended);
    }

    /**
     * Creates known user filter if present
     *
     * @param int $reportid
     * @return \tool_reportbuilder\filter_base[]
     * @throws \coding_exception
     */
    private function get_active_filters(int $reportid) {
        // TODO this code is copied from tool_reportbuilder\form::get_active_filters and needs to be simpler.
        $fields = [];
        $source = \tool_reportbuilder\manager::get_report($reportid);
        $activefilters = \tool_reportbuilder\local\helpers\filters::get_active_filters($reportid);
        $reportfilters = $source->get_filters();
        $filtersinuse = \tool_reportbuilder\local\helpers\filters::get_filters_with_data($activefilters, $reportfilters);
        foreach ($filtersinuse as $keyfilter => $filter) {
            $filterclass = $filter->classname;
            $reportcondition = $reportfilters[$keyfilter];
            /** @var \tool_reportbuilder\filter_base $userfilter */
            $userfilter = \tool_reportbuilder\filter_base::create($filterclass, $reportcondition, $filter->id, $filter->heading,
                null, false);
            $fields[$keyfilter] = $userfilter;
        }

        return $fields;
    }

    /**
     * Get test values for all conditions added to the report
     *
     * @param int $reportid
     * @param bool $extended
     * @return array
     */
    private function get_test_values_for_filter(int $reportid, bool $extended = false) {
        $filters = $this->get_active_filters($reportid);
        $filter = reset($filters);
        return $filter->get_test_values($extended);
    }

    /**
     * Compares two filters and makes sure they are identical
     *
     * Allows to skip testing a filter if it is identical to the one that was already tested
     *
     * @param \tool_reportbuilder\report_filter $filter1
     * @param \tool_reportbuilder\report_filter $filter2
     * @return bool
     */
    protected function are_filters_identical(\tool_reportbuilder\report_filter $filter1,
                                             tool_reportbuilder\report_filter $filter2) {
        if ($filter1->get_classname() !== $filter2->get_classname()) {
            return false;
        }
        if ($filter1->get_field_sql() !== $filter2->get_field_sql()) {
            return false;
        }
        if (json_encode($filter1->get_joins()) !== json_encode($filter2->get_joins())) {
            return false;
        }
        if (json_encode($filter1->get_options()) !== json_encode($filter2->get_options())) {
            return false;
        }
        return true;
    }

    /**
     * Add all available columns to the report.
     *
     * @param int $reportid
     */
    public function add_all_available_columns_to_report(int $reportid) {
        $report = \tool_reportbuilder\manager::get_report($reportid);
        foreach ($report->get_columns() as $key => $column) {
            $this->add_column($report, $key);
        }
    }

    /**
     * Allows to test all columns aggregations in a datasource (not really a generator)
     *
     * @param int $reportid
     * @param advanced_testcase $testcase
     */
    public function datasource_stress_test_aggregation(int $reportid, advanced_testcase $testcase) {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');

        // Check each column aggregation for SQL errors (without actual validation of the results).
        // Skip some for performance: 'unique' is tested anyway, max is similar to min, sum is similar to avg.
        $columns = \tool_reportbuilder\manager::get_report($reportid)->get_columns();
        $source = \tool_reportbuilder\manager::get_report($reportid);
        $activecolumns = $source->get_active_columns();

        foreach ($activecolumns as $activecolumn) {
            $key = $activecolumn->get_unique_identifier();
            $column = $columns[$key];
            $columnid = $activecolumn->get('id');
            $exclude = array_merge($column->get_disabled_aggregations(), ['unique', 'max', 'sum']);
            foreach ($this->get_allowed_aggregation_types($column->get_type(), $exclude) as $aggre => $unused) {
                // Aggregating column $key with $aggre .
                \tool_reportbuilder\local\helpers\aggregation::set_aggregation($columnid, $aggre);
                try {
                    $exporter = new testable_report_exporter($reportid);
                    $testcase->assertGreaterThan(0, count($exporter->get_table_rows()));
                } catch (Exception $e) {
                    $testcase->fail("Exception occured while aggregating column $key with aggregation method $aggre: " .
                        $e->getMessage() . "\n\n");
                }
                \tool_reportbuilder\local\helpers\aggregation::set_aggregation($columnid, '');
            }
        }
    }

    /**
     * Allows to test all conditions in a datasource (not really a generator)
     *
     * @param int $reportid
     * @param advanced_testcase $testcase
     */
    public function datasource_stress_test_conditions(int $reportid, advanced_testcase $testcase) {
        global $CFG;
        require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');
        // Check each condition.
        $report = \tool_reportbuilder\manager::get_report($reportid);

        foreach ($report->get_conditions() as $key => $condition) {
            $conditionadded = $this->add_condition($report, $key);
            foreach ($this->get_test_values_for_condition($reportid) as $testvalues) {
                // Setting condition $key to $testvalues .
                try {
                    $this->set_report_conditions_values($reportid, $testvalues);
                    $exporter = new testable_report_exporter($reportid);
                    $exporter->get_table_rows();
                } catch (Exception $e) {
                    $testcase->fail("Exception occured while setting condition $key to values " . json_encode($testvalues) . ": " .
                        $e->getMessage() . "\n\n");
                }
            }
            $this->remove_condition($conditionadded->get('id'));
        }
    }

    /**
     * Allows to test all filters in a datasource (not really a generator)
     *
     * @param int $reportid
     * @param advanced_testcase $testcase
     */
    public function datasource_stress_test_filters(int $reportid, advanced_testcase $testcase) {
        global $CFG;
        require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');
        // Check each filter (except for filters that are exactly the same as conditions).
        $report = \tool_reportbuilder\manager::get_report($reportid);
        $conditions = $report->get_conditions();

        foreach ($report->get_filters() as $key => $filter) {
            foreach ($conditions as $condition) {
                if ($this->are_filters_identical($filter, $condition)) {
                    // This filter is identical to a condition that we have already checked. Skip it.
                    continue 2;
                }
            }

            $filteradded = $this->add_filter($report, $key);
            foreach ($this->get_test_values_for_filter($reportid) as $testvalues) {
                // Setting filter $key to $testvalues .
                try {
                    $this->set_report_filters_values($reportid, $testvalues);
                    $exporter = new testable_report_exporter($reportid);
                    $exporter->get_table_rows();
                } catch (Exception $e) {
                    $testcase->fail("Exception occured while setting filter $key to values " . json_encode($testvalues) . ": " .
                        $e->getMessage() . "\n\n");
                }
            }
            $this->remove_filter($filteradded->get('id'));
        }
    }

    /**
     * Can be included in the datasources testcases to make sure that they can be converted to core reportbuilder
     *
     * If a datasource or any of its entities/columns/filters can not be converted, the test will fail and
     * the description of the problem will be returned.
     *
     * @param string $classname
     * @param advanced_testcase $test
     * @return void
     */
    public function datasource_test_convert_to_core_reportbuilder(string $classname, advanced_testcase $test): void {
        $errors = [];

        $test->assertTrue(is_subclass_of($classname, \tool_reportbuilder\datasource::class),
            'Class '.$classname.' is not a subclass of '. \core_reportbuilder\datasource::class);
        /** @var \tool_reportbuilder\datasource $oldreport */
        $oldreport = $this->create_report(['source' => $classname, 'adddefault' => 0]);

        // Make sure convert_get_datasource_class() is implemented.
        $test->assertNotEmpty($oldreport->convert_get_datasource_class(),
            "Class $classname must implement function convert_get_datasource_class() and return the name of ".
            "the corresponding class extending \core_reportbuilder\datasource");
        $newclassname = $oldreport->convert_get_datasource_class();
        $test->assertTrue(is_subclass_of($newclassname, \core_reportbuilder\datasource::class));

        /** @var \core_reportbuilder_generator $corerbgenerator */
        $corerbgenerator = $test::getDataGenerator()->get_plugin_generator('core_reportbuilder');
        /** @var \core_reportbuilder\local\models\report $newreportpers */
        $newreportpers = $corerbgenerator->create_report(['name' => 'Test', 'source' => $newclassname, 'default' => 0]);
        /** @var \core_reportbuilder\datasource $newreport */
        $newreport = new $newclassname($newreportpers);

        $oldentities = $oldreport->get_entities();

        // Various reflections to access private/protected functions or properties.
        $rc = new \ReflectionClass(\tool_reportbuilder\report_base::class);
        $rcoldaddedentities = $rc->getProperty('addedentities');
        $rcoldaddedentities->setAccessible(true);

        $rc = new \ReflectionClass(\core_reportbuilder\local\report\base::class);
        $rcp = $rc->getProperty('entitytitles');
        $rcp->setAccessible(true);
        $newentitytitles = $rcp->getValue($newreport);
        $rcp = $rc->getProperty('entities');
        $rcp->setAccessible(true);
        $newentities = $rcp->getValue($newreport);
        $newentityclasses = array_map(function($e) {
            return get_class($e);
        }, $newentities);

        // Make sure all entities are mapped.
        $mapped = [];
        foreach ($oldentities as $oldentityname => $title) {
            try {
                $newentityname = $oldreport->convert_get_entity_name($oldentityname, $newreport);
                if (empty($newentityname)) {
                    continue;
                }
            } catch (convert_not_implemented $t) {
                $newentityname = null;
            }
            $error = "Class '$classname' must implement function convert_get_entity_name() ".
                "and specify the new name for the entity '$oldentityname'. Available entities in the datasource '$newclassname': ".
                join(', ', array_keys($newentitytitles));
            /** @var entity_base $oldentity */
            $oldentity = $rcoldaddedentities->getValue($oldreport)[$oldentityname] ?? null;
            if ($oldentity) {
                try {
                    $newentityclass = $oldentity->convert_get_entity_class();
                } catch (convert_not_implemented $t) {
                    // This is a critical error, test will not continue.
                    $test->fail($t->getMessage() . ". Suggestions:\n- ". join("\n- ", $newentityclasses));
                }
                $test->assertEquals(true, is_subclass_of($newentityclass, \core_reportbuilder\local\entities\base::class));
            }
            if (empty($newentityname)) {
                $errors[] = $error;
                continue;
            }
            if (array_key_exists($newentityname, $mapped)) {
                $errors[] = "Two entities '$oldentityname' and '{$mapped[$newentityname]}'".
                    " are mapped to the same value '$newentityname'.\n$error";
                continue;
            }
            $mapped[$newentityname] = $oldentityname;
            try {
                $newentity = $newreport->get_entity_title($newentityname);
                if (empty($newentity)) {
                    $errors[] = $error;
                }
            } catch (\coding_exception $e) {
                $errors[] = $error;
            }
        }

        // Make sure all columns are mapped.
        foreach ($oldreport->get_columns() as $oldcolumn) {
            try {
                $newidentifier = $oldreport->convert_get_column_unique_identifier($oldcolumn, $newreport);
            } catch (convert_not_possible $t) {
                // This column was marked as "Conversion not possible".
                continue;
            } catch (convert_not_implemented $t) {
                 $errors[] = $t->getMessage();
                 continue;
            }
            if (!array_key_exists($newidentifier, $newreport->get_columns())) {
                $errors[] = "Class '$classname' must implement function 'convert_get_column_unique_identifier()' ".
                    "and specify mapping for the column '" . $oldcolumn->get_unique_identifier() . "'";
            }
        }

        // Make sure all filters are mapped.
        foreach ($oldreport->get_filters() as $oldfilter) {
            try {
                $newidentifier = $oldreport->convert_get_filter_unique_identifier($oldfilter, $newreport);
            } catch (convert_not_possible $t) {
                // This filter was marked as "Conversion not possible".
                continue;
            } catch (convert_not_implemented $t) {
                $errors[] = $t->getMessage();
                continue;
            }
            if (!array_key_exists($newidentifier, $newreport->get_filters())) {
                $errors[] = "Class '$classname' must implement function 'convert_get_filter_unique_identifier()' ".
                    "and specify mapping for the filter '" . $oldfilter->get_unique_identifier() . "'";
            }
        }

        // Make sure all conditions are mapped.
        foreach ($oldreport->get_conditions() as $oldcondition) {
            try {
                $newidentifier = $oldreport->convert_get_condition_unique_identifier($oldcondition, $newreport);
            } catch (convert_not_possible $t) {
                // This condition was marked as "Conversion not possible".
                continue;
            } catch (convert_not_implemented $t) {
                $errors[] = $t->getMessage();
                continue;
            }
            if (!array_key_exists($newidentifier, $newreport->get_conditions())) {
                $errors[] = "Class '$classname' must implement function 'convert_get_condition_unique_identifier()' ".
                    "and specify mapping for the condition '" . $oldcondition->get_unique_identifier() . "'";
                continue;
            }
            // Try to convert values for each condition (use the get_test_values() for sample values).
            $oldinstance = filter_base::create($oldcondition->get_classname(), $oldcondition, 1, 'test');
            $newcondition = $newreport->get_conditions()[$newidentifier];
            $newinstance = \core_reportbuilder\local\filters\base::create($newcondition);
            $newcondition->set_persistent(new filter_model(0, (object)['uniqueidentifier' => $newidentifier]));
            foreach ($oldinstance->get_test_values() as $v) {
                try {
                    $oldinstance->convert_condition_values($v, $newinstance);
                } catch (convert_not_possible $t) {
                    continue;
                } catch (convert_not_implemented $t) {
                    $errors[] = '[Condition '.$oldcondition->get_unique_identifier().']: '.$t->getMessage();
                    break; // Raise only one error per condition.
                }
            }
        }

        // Fail test if there are errors.
        if ($errors) {
            $test->fail("- ".join("\n\n- ", $errors));
        }
    }
}
