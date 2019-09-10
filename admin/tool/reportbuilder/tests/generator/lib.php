<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * tool_reportbuilder data generator.
 *
 * @package    tool_reportbuilder
 * @copyright  2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * tool_reportbuilder data generator class.
 *
 * @package    tool_reportbuilder
 * @copyright  2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     *
     * @return \tool_reportbuilder\report_base
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     */
    public function create_report($record) : \tool_reportbuilder\report_base {
        $record = (array)$record;
        if (!array_key_exists('source', $record) || !class_exists($record['source']) ||
            !is_subclass_of($record['source'], \tool_reportbuilder\datasource::class)) {
            throw new coding_exception('Record must contain "source" property that is a valid datasource class name');
        }
        if (!array_key_exists('name', $record)) {
            $record['name'] = 'New report ' . (++$this->reportcount);
        }
        $record += ['type' => \tool_reportbuilder\constants::TYPE_DATASOURCE, 'description' => '',
            'adddefault' => 1];
        $obj = \tool_reportbuilder\manager::save_report((object)$record);
        if (!empty($record['tenantid']) && $record['tenantid'] != $obj->get('tenantid')) {
            // The save_report() method will always use current tenant. Override it if necessary.
            $obj->set('tenantid', $record['tenantid']);
            $obj->save();
        }

        return \tool_reportbuilder\manager::get_report_from_persistent($obj);
    }

    /**
     * Add a column to the report
     *
     * @param int $reportid
     * @param string $columnkey
     * @return int column id
     * @throws coding_exception
     * @throws dml_exception
     */
    public function add_column(int $reportid, string $columnkey): int {
        $report = \tool_reportbuilder\manager::get_report($reportid);
        $column = tool_reportbuilder\local\helpers\columns::add_column_from_key($report, $columnkey);
        return $column->get('id');
    }

    /**
     * Add a condition to the report
     *
     * @param int $reportid
     * @param string $conditionkey
     * @return int condition id
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function add_condition(int $reportid, string $conditionkey): int {
        return tool_reportbuilder\local\helpers\conditions::add_condition($reportid, $conditionkey);
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
     * @param int $reportid
     * @param \tool_reportbuilder\report_filter $filter
     * @return int
     */
    public function add_filter(int $reportid, \tool_reportbuilder\report_filter $filter): int {
        return \tool_reportbuilder\local\helpers\filters::add_filter($reportid, $filter);
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
     *
     * @return stdClass schedule object (record from db)
     * @throws \core\invalid_persistent_exception
     * @throws coding_exception
     */
    public function create_schedule($record) : stdClass {
        global $USER;

        $record = (array)$record;
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
        if (!array_key_exists('audience', $record)) {
            $record['audience'] = json_encode([]);
        }
        if (!array_key_exists('recurrence', $record)) {
            $record['recurrence'] = 1;
        }
        if (!array_key_exists('reportid', $record)) {
            $newreport = $this->create_report([
                'source' => tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion::class,
            ]);
            $record['reportid'] = $newreport->get_id();
        }

        $persistent = \tool_reportbuilder\local\helpers\schedules::add_schedule((object) $record);

        return $persistent->to_record();
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
     * Returns all aggregation types supported for the give db type
     *
     * @uses \tool_reportbuilder\local\helpers\aggregation::get_allowed_aggregations()
     * @param int $dbtype
     * @param array $exclude exclude these types
     * @return array
     */
    public function get_allowed_aggregation_types(?int $dbtype, array $exclude = []) : array {
        $manager = new  \tool_reportbuilder\local\helpers\aggregation();
        $rc = new \ReflectionClass(get_class($manager));
        $rcm = $rc->getMethod('get_allowed_aggregations');
        $rcm->setAccessible(true);

        return array_keys($rcm->invokeArgs($manager, [$dbtype, $exclude]));
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
        $activeconditions = tool_reportbuilder\local\helpers\conditions::get_active_conditions($reportid);
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
        $columns = \tool_reportbuilder\manager::get_report($reportid)->get_columns();
        foreach ($columns as $key => $column) {
            $this->add_column($reportid, $key);
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
        require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');

        // Check each column aggregation for SQL errors (without actual validation of the results).
        // Skip some for performance: 'unique' is tested anyway, max is similar to min, sum is similar to avg.
        $columns = \tool_reportbuilder\manager::get_report($reportid)->get_columns();
        $source = \tool_reportbuilder\manager::get_report($reportid);
        $activecolumns = \tool_reportbuilder\local\helpers\columns::get_active_columns($source);

        foreach ($activecolumns as $activecolumn) {
            $key = $activecolumn->get_unique_identifier();
            $column = $columns[$key];
            $columnid = $activecolumn->get('id');
            $exclude = array_merge($column->get_disabled_aggregations(), ['unique', 'max', 'sum']);
            foreach ($this->get_allowed_aggregation_types($column->get_type(), $exclude) as $aggre) {
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
        require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');
        // Check each condition.
        $conditions = \tool_reportbuilder\manager::get_report($reportid)->get_conditions();

        foreach ($conditions as $key => $condition) {
            $conditionid = $this->add_condition($reportid, $key);
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
            $this->remove_condition($conditionid);
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
        require_once($CFG->dirroot . '/admin/tool/reportbuilder/tests/fixtures/testable_report_exporter.php');
        // Check each filter (except for filters that are exactly the same as conditions).
        $conditions = \tool_reportbuilder\manager::get_report($reportid)->get_conditions();
        $filters = \tool_reportbuilder\manager::get_report($reportid)->get_filters();

        foreach ($filters as $key => $filter) {
            foreach ($conditions as $condition) {
                if ($this->are_filters_identical($filter, $condition)) {
                    // This filter is identical to a condition that we have already checked. Skip it.
                    continue 2;
                }
            }

            $filterid = $this->add_filter($reportid, $filter);
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
            $this->remove_filter($filterid);
        }
    }
}
