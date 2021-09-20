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
 * File containing tests for aggregation helper class.
 *
 * @package   tool_reportbuilder
 * @category  test
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

use tool_reportbuilder\helper;
use tool_reportbuilder\local\aggregate\groupconcatdistinct;

/**
 * Class tool_reportbuilder_helper_aggregation_testcase
 *
 * @package   tool_reportbuilder
 * @group     tool_reportbuilder
 * @category  test
 * @covers    \tool_reportbuilder\local\helpers\aggregation
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tool_reportbuilder_helper_aggregation_testcase extends advanced_testcase {

    /** @var string $dbfamily */
    private $dbfamily;

    /**
     * Initial config.
     */
    protected function setUp(): void {
        global $DB;

        $this->resetAfterTest();

        $this->dbfamily = $DB->get_dbfamily();
    }

    /**
     * Get a xmldb_table object for testing
     *
     * @param string $suffix table name suffix, use if you need more test tables
     * @param array $values values to populate
     * @return xmldb_table the table object.
     */
    private function create_test_table($suffix, $values) {
        global $DB;

        $dbman = $DB->get_manager();

        $tablename = "test_table_agg";
        if ($suffix !== '') {
            $tablename .= $suffix;
        }

        $table = new xmldb_table($tablename);
        $table->setComment("This is a test'n drop table. You can drop it safely");

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('intfield', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('decfield', XMLDB_TYPE_FLOAT, '10,5');
        $table->add_field('charfield', XMLDB_TYPE_CHAR, 255);
        $table->add_field('textfield', XMLDB_TYPE_TEXT, 'big');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $dbman->create_table($table);

        $tablename = $table->getName();
        foreach ($values as $value) {
            $DB->insert_record($tablename,
                [
                    'intfield' => ($value === null) ? null : (int)$value,
                    'decfield' => ($value === null) ? null : (float)$value,
                    'charfield' => ($value === null) ? null : ('' . $value),
                    'textfield' => ($value === null) ? null : ('' . $value)
                ]);
        }

        return $table;
    }

    /**
     * Helper function to assert aggregation function result
     *
     * @param mixed $expectedresult
     * @param xmldb_table $table
     * @param string $fieldname
     * @param string $aggfunction
     * @param int|null $dbtype
     * @throws coding_exception
     * @throws dml_exception
     */
    private function assert_aggregation_function_result($expectedresult, xmldb_table $table, string $fieldname, string $aggfunction,
                                                        ?int $dbtype = null) {
        global $DB;

        $tablename = $table->getName();
        $func = \tool_reportbuilder\local\helpers\aggregation::get_sql($aggfunction, $fieldname, $dbtype);
        $res = $DB->get_field_sql("SELECT $func AS v FROM {{$tablename}}");

        if ($aggfunction === 'groupconcat' || $aggfunction === 'groupconcatdistinct') {
            // Ignore the order of values.
            $expectedresultar = preg_split('/,/', $expectedresult);

            $separator = helper::get_list_separator();
            $resar = preg_split('/' . preg_quote($separator, '/') . '/', $res);

            if ($this->dbfamily === 'mysql' && preg_match('/decfield/', $fieldname)) {
                // Mysql adds 0s in the end of floats when converted to string.
                array_walk($expectedresultar, function(&$v) {
                    $v = strlen($v) ? sprintf("%.5f", $v) : '';
                });
            }
            if ($this->dbfamily === 'oracle') {
                // Oracle converts empty string to a space.
                array_walk($expectedresultar, function(&$v) {
                    $v = ($v === '') ? ' ' : $v;
                });
            }
            $this->assertEqualsCanonicalizing($expectedresultar, $resar);
        } else {
            $this->assertEqualsWithDelta($expectedresult, $res, 0.0001);
        }
    }

    /**
     * Assert that aggregation function supports types
     * @param array $expectedsupportedtypes
     * @param string $aggfunction
     */
    private function assert_agg_type_supports(array $expectedsupportedtypes, string $aggfunction) {
        $alltypes = [
            null,
            \tool_reportbuilder\constants::DB_TYPE_NUMBER,
            \tool_reportbuilder\constants::DB_TYPE_TEXT,
            \tool_reportbuilder\constants::DB_TYPE_DATETIME,
            \tool_reportbuilder\constants::DB_TYPE_TIMESTAMP,
            \tool_reportbuilder\constants::DB_TYPE_BOOLEAN,
            \tool_reportbuilder\constants::DB_TYPE_LONGTEXT,
        ];

        $generator = $this->get_plugin_generator();

        $actual = [];
        foreach ($alltypes as $dbtype) {
            $result = $generator->get_allowed_aggregation_types($dbtype);
            if (array_key_exists($aggfunction, $result)) {
                $actual[] = $dbtype;
            }
        }

        $this->assertEquals($expectedsupportedtypes, $actual);
    }

    /**
     * Basic test test_get_allowed_aggregations method.
     */
    public function test_get_allowed_aggregations() {
        $generator = $this->get_plugin_generator();

        foreach ([
                     null,
                     \tool_reportbuilder\constants::DB_TYPE_TEXT,
                     \tool_reportbuilder\constants::DB_TYPE_LONGTEXT
                 ] as $dbtype) {

            $expected = [
                '' => get_string('noaggregation', 'tool_reportbuilder'),
                'count' => get_string('aggregation_count', 'tool_reportbuilder'),
                'countdistinct' => get_string('aggregation_countdistinct', 'tool_reportbuilder'),
                'groupconcat' => get_string('aggregation_groupconcat', 'tool_reportbuilder'),
                'groupconcatdistinct' => get_string('aggregation_groupconcatdistinct', 'tool_reportbuilder'),
                'unique' => get_string('aggregation_unique', 'tool_reportbuilder')
            ];

            if (!groupconcatdistinct::is_compatible($dbtype)) {
                unset($expected['groupconcatdistinct']);
            }

            $result = $generator->get_allowed_aggregation_types($dbtype);
            $this->assertEquals($expected, $result);
        }

        // Same text DB types with disabled aggregation types.
        foreach ([
                     null,
                     \tool_reportbuilder\constants::DB_TYPE_TEXT,
                     \tool_reportbuilder\constants::DB_TYPE_LONGTEXT
                 ] as $dbtype) {

            $expected = [
                '' => get_string('noaggregation', 'tool_reportbuilder'),
                'groupconcat' => get_string('aggregation_groupconcat', 'tool_reportbuilder'),
                'groupconcatdistinct' => get_string('aggregation_groupconcatdistinct', 'tool_reportbuilder'),
                'unique' => get_string('aggregation_unique', 'tool_reportbuilder')
            ];

            if (!groupconcatdistinct::is_compatible($dbtype)) {
                unset($expected['groupconcatdistinct']);
            }

            $result = $generator->get_allowed_aggregation_types($dbtype, ['count', 'countdistinct']);
            $this->assertEquals($expected, $result);
        }

        foreach ([
                     \tool_reportbuilder\constants::DB_TYPE_NUMBER
                 ] as $dbtype) {

            $expected = [
                '' => get_string('noaggregation', 'tool_reportbuilder'),
                'count' => get_string('aggregation_count', 'tool_reportbuilder'),
                'countdistinct' => get_string('aggregation_countdistinct', 'tool_reportbuilder'),
                'groupconcat' => get_string('aggregation_groupconcat', 'tool_reportbuilder'),
                'groupconcatdistinct' => get_string('aggregation_groupconcatdistinct', 'tool_reportbuilder'),
                'unique' => get_string('aggregation_unique', 'tool_reportbuilder'),
                'min' => get_string('aggregation_min', 'tool_reportbuilder'),
                'max' => get_string('aggregation_max', 'tool_reportbuilder'),
                'avg' => get_string('aggregation_avg', 'tool_reportbuilder'),
                'sum' => get_string('aggregation_sum', 'tool_reportbuilder')
            ];

            if (!groupconcatdistinct::is_compatible($dbtype)) {
                unset($expected['groupconcatdistinct']);
            }

            $result = $generator->get_allowed_aggregation_types($dbtype);
            $this->assertEquals($expected, $result);
        }

        foreach ([
                     \tool_reportbuilder\constants::DB_TYPE_BOOLEAN
                 ] as $dbtype) {

            $expected = [
                '' => get_string('noaggregation', 'tool_reportbuilder'),
                'count' => get_string('aggregation_count', 'tool_reportbuilder'),
                'countdistinct' => get_string('aggregation_countdistinct', 'tool_reportbuilder'),
                'groupconcat' => get_string('aggregation_groupconcat', 'tool_reportbuilder'),
                'groupconcatdistinct' => get_string('aggregation_groupconcatdistinct', 'tool_reportbuilder'),
                'unique' => get_string('aggregation_unique', 'tool_reportbuilder'),
                'min' => get_string('aggregation_min', 'tool_reportbuilder'),
                'max' => get_string('aggregation_max', 'tool_reportbuilder'),
                'percent' => get_string('aggregation_percent', 'tool_reportbuilder'),
                'sum' => get_string('aggregation_sum', 'tool_reportbuilder'),
            ];

            if (!groupconcatdistinct::is_compatible($dbtype)) {
                unset($expected['groupconcatdistinct']);
            }

            $result = $generator->get_allowed_aggregation_types($dbtype);
            $this->assertEquals($expected, $result);
        }

        foreach ([
                     \tool_reportbuilder\constants::DB_TYPE_DATETIME,
                     \tool_reportbuilder\constants::DB_TYPE_TIMESTAMP
                 ] as $dbtype) {
            $result = $generator->get_allowed_aggregation_types($dbtype);
            $this->assertEquals($result, [
                '' => get_string('noaggregation', 'tool_reportbuilder'),
                'count' => get_string('aggregation_count', 'tool_reportbuilder'),
                'countdistinct' => get_string('aggregation_countdistinct', 'tool_reportbuilder'),
                'min' => get_string('aggregation_min', 'tool_reportbuilder'),
                'max' => get_string('aggregation_max', 'tool_reportbuilder'),
                'unique' => get_string('aggregation_unique', 'tool_reportbuilder')
            ]);
        }
    }

    /**
     * Returns a function from the long text column
     *
     * For the purpose of this text we need ANY valid expression on each column type
     * I could not find a universal function that would always work on all db engines for the 'text' db field
     *
     * @return string
     */
    protected function textfield_nvl() {
        if ($this->dbfamily === 'oracle') {
            return 'NVL(textfield, \'0\')';
        } else {
            return 'COALESCE(textfield, \'0\')';
        }
    }

    /**
     * Test avg aggregation function
     */
    public function test_avg_aggregation() {
        $table = $this->create_test_table('_avg', [1, 2.5, null, 4, 6, '']);

        $this->assert_agg_type_supports([
            tool_reportbuilder\constants::DB_TYPE_NUMBER,
        ], 'avg');

        $this->assert_aggregation_function_result(2.6, $table, 'intfield', 'avg',
            \tool_reportbuilder\constants::DB_TYPE_NUMBER);
        $this->assert_aggregation_function_result(2.7, $table, 'decfield', 'avg',
            \tool_reportbuilder\constants::DB_TYPE_NUMBER);
    }

    /**
     * Test count aggregation function
     */
    public function test_count_aggregation() {
        $table = $this->create_test_table('_count', [1, 2, 2.3, null, null, 2.3, 4, 6, '']);

        $this->assert_agg_type_supports([
            null,
            \tool_reportbuilder\constants::DB_TYPE_NUMBER,
            \tool_reportbuilder\constants::DB_TYPE_TEXT,
            \tool_reportbuilder\constants::DB_TYPE_DATETIME,
            \tool_reportbuilder\constants::DB_TYPE_TIMESTAMP,
            \tool_reportbuilder\constants::DB_TYPE_BOOLEAN,
            \tool_reportbuilder\constants::DB_TYPE_LONGTEXT,
        ], 'count');

        $this->assert_aggregation_function_result(7, $table, 'intfield', 'count');
        $this->assert_aggregation_function_result(7, $table, 'decfield', 'count');
        $this->assert_aggregation_function_result(7, $table, 'charfield', 'count');
        $this->assert_aggregation_function_result(7, $table, 'textfield',
            'count', \tool_reportbuilder\constants::DB_TYPE_LONGTEXT);

        // Use expression instead of just a field name.
        $this->assert_aggregation_function_result(9, $table, 'COALESCE(intfield, 0)',
            'count', \tool_reportbuilder\constants::DB_TYPE_NUMBER);
        $this->assert_aggregation_function_result(9, $table, 'COALESCE(decfield, 0)',
            'count', \tool_reportbuilder\constants::DB_TYPE_NUMBER);
        $this->assert_aggregation_function_result(9, $table, 'COALESCE(charfield, \'0\')',
            'count', \tool_reportbuilder\constants::DB_TYPE_TEXT);
        $this->assert_aggregation_function_result(9, $table, $this->textfield_nvl(),
            'count', \tool_reportbuilder\constants::DB_TYPE_LONGTEXT);
    }

    /**
     * Test countdistinct aggregation function
     */
    public function test_countdistinct_aggregation() {
        $table = $this->create_test_table('_countdistinct', [0, 2, 2.3, null, '2.30', 4, null, 6, 'c', '']);

        $this->assert_agg_type_supports([
            null,
            \tool_reportbuilder\constants::DB_TYPE_NUMBER,
            \tool_reportbuilder\constants::DB_TYPE_TEXT,
            \tool_reportbuilder\constants::DB_TYPE_DATETIME,
            \tool_reportbuilder\constants::DB_TYPE_TIMESTAMP,
            \tool_reportbuilder\constants::DB_TYPE_BOOLEAN,
            \tool_reportbuilder\constants::DB_TYPE_LONGTEXT,
        ], 'countdistinct');

        $this->assert_aggregation_function_result(4, $table, 'intfield', 'countdistinct');
        $this->assert_aggregation_function_result(5, $table, 'decfield', 'countdistinct');
        $this->assert_aggregation_function_result(8, $table, 'charfield', 'countdistinct');
        $this->assert_aggregation_function_result(8, $table, 'textfield',
            'countdistinct', \tool_reportbuilder\constants::DB_TYPE_LONGTEXT);

        // Use expression instead of just a field name.
        $this->assert_aggregation_function_result(4, $table, 'COALESCE(intfield, 0)',
            'countdistinct', \tool_reportbuilder\constants::DB_TYPE_NUMBER);
        $this->assert_aggregation_function_result(5, $table, 'COALESCE(decfield, 0)',
            'countdistinct', \tool_reportbuilder\constants::DB_TYPE_NUMBER);
        $this->assert_aggregation_function_result(8, $table, 'COALESCE(charfield, \'0\')',
            'countdistinct', \tool_reportbuilder\constants::DB_TYPE_TEXT);
        $this->assert_aggregation_function_result(8, $table, $this->textfield_nvl(),
            'countdistinct', \tool_reportbuilder\constants::DB_TYPE_LONGTEXT);
    }

    /**
     * Test groupconcat aggregation function
     */
    public function test_groupconcat_aggregation() {
        $table = $this->create_test_table('_groupconcat', [1, 2, null, 4.3, 2, null, 12, 6, '']);

        $this->assert_agg_type_supports([
            null,
            \tool_reportbuilder\constants::DB_TYPE_NUMBER,
            \tool_reportbuilder\constants::DB_TYPE_TEXT,
            \tool_reportbuilder\constants::DB_TYPE_BOOLEAN,
            \tool_reportbuilder\constants::DB_TYPE_LONGTEXT,
        ], 'groupconcat');

        $this->assert_aggregation_function_result('1,2,4,2,12,6,0', $table, 'intfield', 'groupconcat',
            \tool_reportbuilder\constants::DB_TYPE_NUMBER);
        $this->assert_aggregation_function_result('1,2,4.3,2,12,6,0',
                $table, 'decfield', 'groupconcat', \tool_reportbuilder\constants::DB_TYPE_NUMBER);
        $this->assert_aggregation_function_result('1,2,4.3,2,12,6,', $table,
            'charfield', 'groupconcat', \tool_reportbuilder\constants::DB_TYPE_TEXT);
        $this->assert_aggregation_function_result('1,2,4.3,2,12,6,', $table, 'textfield',
            'groupconcat', \tool_reportbuilder\constants::DB_TYPE_LONGTEXT);

        // Use expression instead of just a field name.
        $this->assert_aggregation_function_result('1,2,0,4,2,0,12,6,0', $table,
            'COALESCE(intfield, 0)', 'groupconcat', \tool_reportbuilder\constants::DB_TYPE_NUMBER);
        $this->assert_aggregation_function_result('1,2,0,4.3,2,0,12,6,0', $table,
                'COALESCE(decfield, 0)', 'groupconcat', \tool_reportbuilder\constants::DB_TYPE_NUMBER);
        $this->assert_aggregation_function_result('1,2,0,4.3,2,0,12,6,', $table,
            'COALESCE(charfield, \'0\')', 'groupconcat', \tool_reportbuilder\constants::DB_TYPE_TEXT);
        $this->assert_aggregation_function_result('1,2,0,4.3,2,0,12,6,', $table,
            $this->textfield_nvl(), 'groupconcat', \tool_reportbuilder\constants::DB_TYPE_LONGTEXT);
    }

    /**
     * Test groupconcatdistinct aggregation function
     */
    public function test_groupconcatdistinct_aggregation() {
        if (!groupconcatdistinct::is_compatible(null)) {
            $this->markTestSkipped($this->dbfamily . ' does not currently support group concat distinct');
        }

        $table = $this->create_test_table('_gcd', [1, 2, null, 4.3, 20, null, 6, 1, '']);

        $this->assert_agg_type_supports([
            null,
            \tool_reportbuilder\constants::DB_TYPE_NUMBER,
            \tool_reportbuilder\constants::DB_TYPE_TEXT,
            \tool_reportbuilder\constants::DB_TYPE_BOOLEAN,
            \tool_reportbuilder\constants::DB_TYPE_LONGTEXT,
        ], 'groupconcatdistinct');

        $this->assert_aggregation_function_result('1,2,4,20,6,0', $table, 'intfield', 'groupconcatdistinct');
        $this->assert_aggregation_function_result('0,1,2,4.3,6,20', $table, 'decfield', 'groupconcatdistinct');
        $this->assert_aggregation_function_result('1,2,4.3,20,6,', $table, 'charfield', 'groupconcatdistinct');
        $this->assert_aggregation_function_result('1,2,4.3,20,6,', $table, 'textfield', 'groupconcatdistinct');

        // Use expression instead of just a field name.
        $this->assert_aggregation_function_result('1,2,0,4,20,6', $table, 'COALESCE(intfield, 0)', 'groupconcatdistinct');
        $this->assert_aggregation_function_result('0,1,2,4.3,6,20', $table,
                'COALESCE(decfield, 0)', 'groupconcatdistinct');
        $this->assert_aggregation_function_result('1,2,0,4.3,20,6,', $table,
            'COALESCE(charfield, \'0\')', 'groupconcatdistinct');
        $this->assert_aggregation_function_result('1,2,0,4.3,20,6,', $table,
            'COALESCE(textfield, \'0\')', 'groupconcatdistinct', \tool_reportbuilder\constants::DB_TYPE_LONGTEXT);
    }

    /**
     * Test max aggregation function
     */
    public function test_max_aggregation() {
        $table = $this->create_test_table('_max', [1, 12, null, 24, 100, 0, -5]);

        $this->assert_agg_type_supports([
            \tool_reportbuilder\constants::DB_TYPE_NUMBER,
            \tool_reportbuilder\constants::DB_TYPE_DATETIME,
            \tool_reportbuilder\constants::DB_TYPE_TIMESTAMP,
            \tool_reportbuilder\constants::DB_TYPE_BOOLEAN,
        ], 'max');

        $this->assert_aggregation_function_result(100, $table, 'intfield', 'max');
        $this->assert_aggregation_function_result(100, $table, 'decfield', 'max');

        // Use expression instead of just a field name.
        $this->assert_aggregation_function_result(100, $table, 'COALESCE(intfield, 0)', 'max');
        $this->assert_aggregation_function_result(100, $table, 'COALESCE(decfield, 0)', 'max');
    }

    /**
     * Test min aggregation function
     */
    public function test_min_aggregation() {
        $table = $this->create_test_table('_min', [-1, -12, null, -24, -100, 0, 20]);

        $this->assert_agg_type_supports([
            \tool_reportbuilder\constants::DB_TYPE_NUMBER,
            \tool_reportbuilder\constants::DB_TYPE_DATETIME,
            \tool_reportbuilder\constants::DB_TYPE_TIMESTAMP,
            \tool_reportbuilder\constants::DB_TYPE_BOOLEAN,
        ], 'min');

        $this->assert_aggregation_function_result(-100, $table, 'intfield', 'min');
        $this->assert_aggregation_function_result(-100, $table, 'decfield', 'min');

        // Use expression instead of just a field name.
        $this->assert_aggregation_function_result(-100, $table, 'COALESCE(intfield, 0)', 'min');
        $this->assert_aggregation_function_result(-100, $table, 'COALESCE(decfield, 0)', 'min');
    }

    /**
     * Test percent aggregation function
     */
    public function test_percent_aggregation() {
        $table = $this->create_test_table('_percent', [0, 1, 1, null, null, null, 1, 0]);

        $this->assert_agg_type_supports([
            \tool_reportbuilder\constants::DB_TYPE_BOOLEAN,
        ], 'percent');

        $this->assert_aggregation_function_result(60, $table, 'intfield', 'percent');
        $this->assert_aggregation_function_result(60, $table, 'decfield', 'percent');
        // Char/text columns are not supported.

        // Use expression instead of just a field name.
        $this->assert_aggregation_function_result(37.5, $table, 'COALESCE(intfield, 0)', 'percent');
        $this->assert_aggregation_function_result(37.5, $table, 'COALESCE(decfield, 0)', 'percent');
    }

    /**
     * Test sum aggregation function
     */
    public function test_sum_aggregation() {
        $table = $this->create_test_table('_sum', [1, 2.3, null, 4, 12, '']);

        $this->assert_agg_type_supports([
            \tool_reportbuilder\constants::DB_TYPE_NUMBER,
            \tool_reportbuilder\constants::DB_TYPE_BOOLEAN,
        ], 'sum');

        $this->assert_aggregation_function_result(19, $table, 'intfield', 'sum', \tool_reportbuilder\constants::DB_TYPE_NUMBER);
        $this->assert_aggregation_function_result(19.3, $table, 'decfield', 'sum', \tool_reportbuilder\constants::DB_TYPE_DECIMAL);

        // Use expression instead of just a field name.
        $this->assert_aggregation_function_result(20, $table, 'COALESCE(intfield, 1)', 'sum');
        $this->assert_aggregation_function_result(20.3, $table, 'COALESCE(decfield, 1)', 'sum');
    }

    /**
     * Test unique aggregation function
     */
    public function test_unique_aggregation() {
        $this->assert_agg_type_supports([
            null,
            \tool_reportbuilder\constants::DB_TYPE_NUMBER,
            \tool_reportbuilder\constants::DB_TYPE_TEXT,
            \tool_reportbuilder\constants::DB_TYPE_DATETIME,
            \tool_reportbuilder\constants::DB_TYPE_TIMESTAMP,
            \tool_reportbuilder\constants::DB_TYPE_BOOLEAN,
            \tool_reportbuilder\constants::DB_TYPE_LONGTEXT,
        ], 'unique');

        // Unique 'aggregation' is handled separately from the actual aggregation class.
    }

    /**
     * Get Report builder generator
     *
     * @return tool_reportbuilder_generator
     */
    public function get_plugin_generator() : tool_reportbuilder_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_reportbuilder');
    }
}
