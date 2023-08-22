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
 * File containing tests for tool_reportbuilder\db class.
 *
 * @package     tool_reportbuilder
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

use advanced_testcase;

/**
 * Tests for the tool_reportbuilder\db class methods.
 *
 * @package    tool_reportbuilder
 * @covers     \tool_reportbuilder\db
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class db_test extends advanced_testcase {

    /**
     * Test for sql_fullname() with various name format settings
     */
    public function test_sql_fullname() {
        global $CFG;
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user([
            'firstname' => 'Fn',
            'lastname' => 'Ln',
            'firstnamephonetic' => 'Fnp',
            'lastnamephonetic' => 'Lnp',
            'middlename' => 'Mn',
            'alternatename' => 'An',
        ]);

        // Default format 'firstname lastname'.
        $this->assertEquals(fullname(fullclone($user1)), $this->get_user_name($user1->id));
        $this->assertEquals('Fn Ln', $this->get_user_name($user1->id));
        $this->assertEquals(fullname(fullclone($user1), true), $this->get_user_name($user1->id, true));
        $this->assertEquals('Fn Ln', $this->get_user_name($user1->id, true));

        // Set custom fullnamedisplay and alternativefullnameformat.
        $CFG->fullnamedisplay = 'lastname firstname firstnamephonetic';
        $CFG->alternativefullnameformat = 'lastname firstname middlename';
        $this->assertEquals(fullname(fullclone($user1)), $this->get_user_name($user1->id));
        $this->assertEquals('Ln Fn Fnp', $this->get_user_name($user1->id));
        $this->assertEquals(fullname(fullclone($user1), true), $this->get_user_name($user1->id, true));
        $this->assertEquals('Ln Fn Mn', $this->get_user_name($user1->id, true));
        // Setting $CFG->fullnamedisplay with one or more field(s) that is NULL should
        // still display the fields with value. The test below ensures that we still have a value for user's name.
        $user1->firstnamephonetic = null;
        user_update_user($user1, false, true);
        $this->assertEquals('Ln Fn ', $this->get_user_name($user1->id));

        // Add some characters.
        $CFG->fullnamedisplay = 'alternatename';
        $CFG->alternativefullnameformat = 'firstname (alternatename) lastname';
        $this->assertEquals(fullname(fullclone($user1)), $this->get_user_name($user1->id));
        $this->assertEquals('An', $this->get_user_name($user1->id));
        $this->assertEquals(fullname(fullclone($user1), true), $this->get_user_name($user1->id, true));
        $this->assertEquals('Fn (An) Ln', $this->get_user_name($user1->id, true));

        // Add some characters.
        $CFG->fullnamedisplay = 'language';
        $CFG->alternativefullnameformat = 'language';
        $CFG->forcefirstname = 'I';
        $this->assertEquals(fullname(fullclone($user1)), $this->get_user_name($user1->id));
        $this->assertEquals('I Ln', $this->get_user_name($user1->id));
        $this->assertEquals(fullname(fullclone($user1), true), $this->get_user_name($user1->id, true));
        $this->assertEquals('Fn Ln', $this->get_user_name($user1->id, true));
    }

    /**
     * Helper method to do simple SQL query
     *
     * @param int $userid
     * @param bool $override
     * @return string|null
     */
    protected function get_user_name($userid, bool $override = false) {
        global $DB;
        list($sql, $params) = \tool_reportbuilder\db::sql_fullname('u', $override);
        $params['id'] = $userid;
        return $DB->get_field_sql("SELECT $sql FROM {user} u WHERE id = :id", $params);
    }

    /**
     * Test for sql_fullname with sorting
     */
    public function test_sql_fullname_sort() {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->getDataGenerator()->create_user(['firstname' => 'c', 'lastname' => '1']);
        $this->getDataGenerator()->create_user(['firstname' => 'b', 'lastname' => '2']);

        list($sql1, $params1) = \tool_reportbuilder\db::sql_fullname('t');
        list($sql2, $params2) = \tool_reportbuilder\db::sql_fullname('t');
        $users = $DB->get_fieldset_sql("SELECT $sql1 AS name FROM {user} t WHERE id > 1 ORDER BY $sql2",
            $params1 + $params2);
        $this->assertEquals(['Admin User', 'b 2', 'c 1'], $users);
        $users = $DB->get_fieldset_sql("SELECT $sql1 AS name FROM {user} t WHERE id > 1 ORDER BY name",
            $params1 + $params2);
        $this->assertEquals(['Admin User', 'b 2', 'c 1'], $users);

        $CFG->fullnamedisplay = 'lastname firstname';
        list($sql1, $params1) = \tool_reportbuilder\db::sql_fullname('t');
        list($sql2, $params2) = \tool_reportbuilder\db::sql_fullname('t');
        $users = $DB->get_fieldset_sql("SELECT $sql1 AS name FROM {user} t WHERE id > 1 ORDER BY $sql2",
            $params1 + $params2);
        $this->assertEquals(['1 c', '2 b', 'User Admin'], $users);
        $users = $DB->get_fieldset_sql("SELECT $sql1 AS name FROM {user} t WHERE id > 1 ORDER BY name",
            $params1 + $params2);
        $this->assertEquals(['1 c', '2 b', 'User Admin'], $users);
    }

    /**
     * Test for sql_fullname with $ascsv parameter (used in "GROUP BY")
     */
    public function test_sql_fullname_ascsv() {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->getDataGenerator()->create_user(['firstname' => 'c', 'lastname' => '1']);
        $this->getDataGenerator()->create_user(['firstname' => 'b', 'lastname' => '2']);

        list($sql1, $params1) = \tool_reportbuilder\db::sql_fullname('t');
        $sql2 = \tool_reportbuilder\db::sql_fullname('t', false, true);
        $users = $DB->get_fieldset_sql("SELECT $sql1 AS name FROM {user} t WHERE id > 1 GROUP BY $sql2 ORDER BY $sql2",
            $params1);
        $this->assertEquals(['Admin User', 'b 2', 'c 1'], $users);

        $CFG->fullnamedisplay = 'lastname firstname';
        list($sql1, $params1) = \tool_reportbuilder\db::sql_fullname('t');
        $sql2 = \tool_reportbuilder\db::sql_fullname('t', false, true);
        $users = $DB->get_fieldset_sql("SELECT $sql1 AS name FROM {user} t WHERE id > 1 GROUP BY $sql2 ORDER BY $sql2",
            $params1);

        $this->assertEquals(['1 c', '2 b', 'User Admin'], $users);
    }

    /**
     * Tests for sql_string_with_placeholders
     */
    public function test_sql_string_with_placeholders() {
        global $DB;
        $this->resetAfterTest();
        $this->getDataGenerator()->create_user(['firstname' => 'c', 'lastname' => '1']);
        $this->getDataGenerator()->create_user(['firstname' => 'b', 'lastname' => '2']);

        list($sql1, $params) = \tool_reportbuilder\db::sql_string_with_placeholders('{p1} and {p2}',
            ['{p1}' => 't.firstname', '{p2}' => 't.lastname']);
        $sql2 = \tool_reportbuilder\db::sql_string_with_placeholders('{p1} and {p2}',
            ['{p1}' => 't.firstname', '{p2}' => 't.lastname'], true);
        $users = $DB->get_fieldset_sql("SELECT $sql1 AS name FROM {user} t WHERE id > 1 GROUP BY $sql2 ORDER BY $sql2",
            $params);
        $this->assertEquals(['Admin and User', 'b and 2', 'c and 1'], $users);

        // When parameters are not in the same order as placeholders in the string.
        list($sql1, $params) = \tool_reportbuilder\db::sql_string_with_placeholders('{p1} and {p2}',
            ['{p2}' => 't.lastname', '{p1}' => 't.firstname']);
        $sql2 = \tool_reportbuilder\db::sql_string_with_placeholders('{p1} and {p2}',
            ['{p2}' => 't.lastname', '{p1}' => 't.firstname'], true);
        $users = $DB->get_fieldset_sql("SELECT $sql1 AS name FROM {user} t WHERE id > 1 GROUP BY $sql2 ORDER BY $sql2",
            $params);
        $this->assertEquals(['Admin and User', 'b and 2', 'c and 1'], $users);

        // There are parameters that are not used in the string - they should never be used in SQL.
        list($sql1, $params) = \tool_reportbuilder\db::sql_string_with_placeholders('{p1} and {p2}',
            ['{p2}' => 't.lastname', '{p1}' => 't.firstname', '{p3}' => 't.nonexistingfield']);
        $sql2 = \tool_reportbuilder\db::sql_string_with_placeholders('{p1} and {p2}',
            ['{p2}' => 't.lastname', '{p1}' => 't.firstname', '{p3}' => 't.nonexistingfield'], true);
        $users = $DB->get_fieldset_sql("SELECT $sql1 AS name FROM {user} t WHERE id > 1 GROUP BY $sql2 ORDER BY $sql2",
            $params);
        $this->assertEquals(['Admin and User', 'b and 2', 'c and 1'], $users);
    }

    /**
     * Tests for sql_get_string
     */
    public function test_sql_get_string() {
        global $DB;
        $this->resetAfterTest();
        $this->getDataGenerator()->create_user(['firstname' => 'c', 'lastname' => '1']);
        $this->getDataGenerator()->create_user(['firstname' => 'b', 'lastname' => '2']);

        // Complex placeholders.
        list($sql1, $params) = \tool_reportbuilder\db::sql_get_string('and', 'moodle',
            ['one' => 't.firstname', 'two' => 't.lastname']);
        $sql2 = \tool_reportbuilder\db::sql_get_string('and', 'moodle',
            ['one' => 't.firstname', 'two' => 't.lastname'], true);
        $users = $DB->get_fieldset_sql("SELECT $sql1 AS name FROM {user} t WHERE id > 1 GROUP BY $sql2 ORDER BY $sql2",
            $params);
        $this->assertEquals(['Admin and User', 'b and 2', 'c and 1'], $users);

        // Complex placeholders with the wrong order of parameters.
        list($sql1, $params) = \tool_reportbuilder\db::sql_get_string('and', 'moodle',
            ['two' => 't.lastname', 'one' => 't.firstname']);
        $sql2 = \tool_reportbuilder\db::sql_get_string('and', 'moodle',
            ['two' => 't.lastname', 'one' => 't.firstname'], true);
        $users = $DB->get_fieldset_sql("SELECT $sql1 AS name FROM {user} t WHERE id > 1 GROUP BY $sql2 ORDER BY $sql2",
            $params);
        $this->assertEquals(['Admin and User', 'b and 2', 'c and 1'], $users);

        // Simple placeholders.
        list($sql1, $params) = \tool_reportbuilder\db::sql_get_string('backto', 'moodle',
            't.firstname');
        $sql2 = \tool_reportbuilder\db::sql_get_string('backto', 'moodle',
            't.firstname', true);
        $users = $DB->get_fieldset_sql("SELECT $sql1 AS name FROM {user} t WHERE id > 1 GROUP BY $sql2 ORDER BY $sql2",
            $params);
        $this->assertEquals(['Back to Admin', 'Back to b', 'Back to c'], $users);
    }

    /**
     * Test for combination of sql functions
     */
    public function test_sql_fullname_with_link() {
        global $DB;
        $this->resetAfterTest();
        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'c', 'lastname' => '1']);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'b', 'lastname' => '2']);

        $str = '<a href="http://example.com/user/profile.php?id={{id}}">{{name}}</a>';
        list($sqlname, $paramsname) = \tool_reportbuilder\db::sql_fullname('t');
        list($sql, $params) = \tool_reportbuilder\db::sql_string_with_placeholders($str,
            ['{{id}}' => 't.id', '{{name}}' => $sqlname]);
        $params += $paramsname;
        $users = $DB->get_fieldset_sql("SELECT $sql AS name FROM {user} t WHERE id > 1 ORDER BY t.id",
            $params);
        $this->assertEquals(['<a href="http://example.com/user/profile.php?id=2">Admin User</a>',
            '<a href="http://example.com/user/profile.php?id=' . $user1->id . '">c 1</a>',
            '<a href="http://example.com/user/profile.php?id=' . $user2->id . '">b 2</a>'
        ], $users);
    }

    /**
     * Test for sql_group_concat()
     */
    public function test_sql_group_concat() {
        global $DB;
        $this->resetAfterTest();
        $this->getDataGenerator()->create_user(['firstname' => 'a', 'lastname' => '4']);
        $this->getDataGenerator()->create_user(['firstname' => 'b', 'lastname' => '3']);
        $this->getDataGenerator()->create_user(['firstname' => 'c', 'lastname' => '2']);
        $this->getDataGenerator()->create_user(['firstname' => 'd', 'lastname' => '1']);

        $separator = ';';

        // Exclude accounts other than the ones we just created.
        list($ignoreusers, $ignoreusersparams) = $DB->get_in_or_equal(['guest', 'admin'], SQL_PARAMS_NAMED, 'u', false);

        // Test with a simple field without sorting (should fallback to sorting by specified field).
        $sql = \tool_reportbuilder\db::sql_group_concat('t.firstname', $separator);
        $result = $DB->get_field_sql("SELECT $sql AS name FROM {user} t WHERE t.username $ignoreusers", $ignoreusersparams);
        $this->assertEquals(['a', 'b', 'c', 'd'], explode($separator, $result));

        // Test with sorting by varchar field specifying the order.
        $sql = \tool_reportbuilder\db::sql_group_concat('t.firstname', $separator, 't.firstname DESC');
        $result = $DB->get_field_sql("SELECT $sql AS name FROM {user} t WHERE t.username $ignoreusers", $ignoreusersparams);
        $this->assertEquals(['d', 'c', 'b', 'a'], explode($separator, $result));

        // Test with sorting by varchar field without specifying the order.
        $sql = \tool_reportbuilder\db::sql_group_concat('t.firstname', $separator, 't.lastname');
        $result = $DB->get_field_sql("SELECT $sql AS name FROM {user} t WHERE t.username $ignoreusers", $ignoreusersparams);
        $this->assertEquals(['d', 'c', 'b', 'a'], explode($separator, $result));

        // Test with sorting by number field.
        $sql = \tool_reportbuilder\db::sql_group_concat('t.firstname', $separator, 't.id DESC');
        $result = $DB->get_field_sql("SELECT $sql AS name FROM {user} t WHERE t.username $ignoreusers", $ignoreusersparams);
        $this->assertEquals(['d', 'c', 'b', 'a'], explode($separator, $result));

        // Test with SQL expression (short fallback to sorting by specified field).
        list($sqlname, $paramsname) = \tool_reportbuilder\db::sql_fullname('t');
        $sql = \tool_reportbuilder\db::sql_group_concat($sqlname, $separator);
        $result = $DB->get_field_sql("SELECT $sql AS name FROM {user} t WHERE t.username $ignoreusers",
            $ignoreusersparams + $paramsname);
        $this->assertEquals(['a 4', 'b 3', 'c 2', 'd 1'], explode($separator, $result));

        // Test with sorting.
        $sql = \tool_reportbuilder\db::sql_group_concat($sqlname, $separator, 't.firstname DESC');
        $result = $DB->get_field_sql("SELECT $sql AS name FROM {user} t WHERE t.username $ignoreusers",
            $ignoreusersparams + $paramsname);
        $this->assertEquals(['d 1', 'c 2', 'b 3', 'a 4'], explode($separator, $result));

        // Test that sorting doesn't fallback to sorting by specified field if field contains parameters.
        $sqlname = $DB->sql_concat(':greeting', 't.firstname');
        $sql = \tool_reportbuilder\db::sql_group_concat($sqlname, $separator);
        $result = $DB->get_field_sql("SELECT $sql AS name FROM {user} t WHERE t.username $ignoreusers",
            $ignoreusersparams + ['greeting' => 'Hello ']);
        $this->assertEqualsCanonicalizing(['Hello a', 'Hello b', 'Hello c', 'Hello d'], explode($separator, $result));
    }

    /**
     * Test for sql_group_concat_distinct()
     */
    public function test_sql_group_concat_distinct() {
        global $DB;

        // We need to check that the current database implements the group concat distinct method.
        if (empty(\tool_reportbuilder\db::sql_group_concat_distinct(''))) {
            $this->markTestSkipped($DB->get_dbfamily() . ' does not currently support group concat distinct');
        }

        $this->resetAfterTest();
        $this->getDataGenerator()->create_user(['firstname' => 'a', 'lastname' => '4']);
        $this->getDataGenerator()->create_user(['firstname' => 'b', 'lastname' => '3']);
        $this->getDataGenerator()->create_user(['firstname' => 'c x', 'lastname' => '2']);
        $this->getDataGenerator()->create_user(['firstname' => 'c', 'lastname' => 'x 2']);
        $this->getDataGenerator()->create_user(['firstname' => 'd', 'lastname' => '1']);
        $this->getDataGenerator()->create_user(['firstname' => 'b', 'lastname' => '3']);

        $separator = ';';

        // Exclude accounts other than the ones we just created.
        list($ignoreusers, $ignoreusersparams) = $DB->get_in_or_equal(['guest', 'admin'], SQL_PARAMS_NAMED, 'u', false);

        // Test with a simple field without sorting (should fallback to sorting by specified field).
        $sql = \tool_reportbuilder\db::sql_group_concat_distinct('t.firstname', $separator);
        $result = $DB->get_field_sql("SELECT $sql AS name FROM {user} t WHERE t.username $ignoreusers", $ignoreusersparams);
        $this->assertEquals(['a', 'b', 'c', 'c x', 'd'], explode($separator, $result));

        // Test with sorting.
        $sql = \tool_reportbuilder\db::sql_group_concat_distinct('t.firstname', $separator, 't.firstname DESC');
        $result = $DB->get_field_sql("SELECT $sql AS name FROM {user} t WHERE t.username $ignoreusers", $ignoreusersparams);
        $this->assertEquals(['d', 'c x', 'c', 'b', 'a'], explode($separator, $result));

        // Test with SQL expression (should fallback to sorting by specified field).
        list($sqlname, $paramsname) = \tool_reportbuilder\db::sql_fullname('t');

        $sql = \tool_reportbuilder\db::sql_group_concat_distinct($sqlname, $separator);
        $result = $DB->get_field_sql("SELECT $sql AS name FROM {user} t WHERE t.username $ignoreusers",
            $ignoreusersparams + $paramsname);
        $this->assertEquals(['a 4', 'b 3', 'c x 2', 'd 1'], explode($separator, $result));

        // Test with sorting.
        $sql = \tool_reportbuilder\db::sql_group_concat_distinct($sqlname, $separator, 't.firstname');
        $result = $DB->get_field_sql("SELECT $sql AS name FROM {user} t WHERE t.username $ignoreusers",
            $ignoreusersparams + $paramsname);
        $this->assertEquals(['a 4', 'b 3', 'c x 2', 'd 1'], explode($separator, $result));

        // Test with sorting decending.
        $sql = \tool_reportbuilder\db::sql_group_concat_distinct($sqlname, $separator, 't.firstname DESC');
        $result = $DB->get_field_sql("SELECT $sql AS name FROM {user} t WHERE t.username $ignoreusers",
            $ignoreusersparams + $paramsname);
        $this->assertEquals(['d 1', 'c x 2', 'b 3', 'a 4'], explode($separator, $result));

        // Test that sorting doesn't fallback to sorting by specified field if field contains parameters.
        $sqlname = $DB->sql_concat(':greeting', 't.firstname');
        $sql = \tool_reportbuilder\db::sql_group_concat_distinct($sqlname, $separator);
        $result = $DB->get_field_sql("SELECT $sql AS name FROM {user} t WHERE t.username $ignoreusers",
            $ignoreusersparams + ['greeting' => 'Hello ']);
        $this->assertEqualsCanonicalizing(['Hello a', 'Hello b', 'Hello c', 'Hello c x', 'Hello d'], explode($separator, $result));
    }
}
