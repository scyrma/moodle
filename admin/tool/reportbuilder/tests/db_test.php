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
 * File containing tests for tool_reportbuilder\db class.
 *
 * @package     tool_reportbuilder
 * @category    test
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the tool_reportbuilder\db class methods.
 *
 * @package    tool_reportbuilder
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_db_testcase extends advanced_testcase {

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

        // Test with a simple field.
        $sql = \tool_reportbuilder\db::sql_group_concat('t.firstname', ';');
        $result = $DB->get_field_sql("SELECT $sql AS name FROM {user} t WHERE id > 1", []);
        // The sequence may be random.
        $names = preg_split('/;/', $result);
        $this->assertEquals(['Admin', 'a', 'b', 'c', 'd'], $names, '', 0, 10, true);

        // Test with SQL expression.
        list($sqlname, $paramsname) = \tool_reportbuilder\db::sql_fullname('t');
        $sql = \tool_reportbuilder\db::sql_group_concat($sqlname, ';');
        $result = $DB->get_field_sql("SELECT $sql AS name FROM {user} t WHERE id > 1", $paramsname);
        $names = preg_split('/;/', $result);
        $this->assertEquals(['Admin User', 'a 4', 'b 3', 'c 2', 'd 1'], $names, '', 0, 10, true);
    }

    /**
     * Test for sql_group_concat_distinct()
     */
    public function test_sql_group_concat_distinct() {
        global $DB;
        $this->resetAfterTest();
        $this->getDataGenerator()->create_user(['firstname' => 'a', 'lastname' => '4']);
        $this->getDataGenerator()->create_user(['firstname' => 'b', 'lastname' => '3']);
        $this->getDataGenerator()->create_user(['firstname' => 'c x', 'lastname' => '2']);
        $this->getDataGenerator()->create_user(['firstname' => 'c', 'lastname' => 'x 2']);
        $this->getDataGenerator()->create_user(['firstname' => 'd', 'lastname' => '1']);
        $this->getDataGenerator()->create_user(['firstname' => 'b', 'lastname' => '3']);

        // Test with a simple field.
        $sql = \tool_reportbuilder\db::sql_group_concat_distinct('t.firstname', ';');
        $result = $DB->get_field_sql("SELECT $sql AS name FROM {user} t WHERE id > 1", []);
        // The sequence may be random.
        $names = preg_split('/;/', $result);
        $expected = ['Admin', 'a', 'b', 'c x', 'c', 'd', 'b'];
        if ($DB->get_dbfamily() === 'mysql') {
            $expected = array_unique($expected);
        }
        $this->assertEquals($expected, $names, '', 0, 10, true);

        // Test with SQL expression.
        list($sqlname, $paramsname) = \tool_reportbuilder\db::sql_fullname('t');
        $sql = \tool_reportbuilder\db::sql_group_concat_distinct($sqlname, ';');
        $result = $DB->get_field_sql("SELECT $sql AS name FROM {user} t WHERE id > 1", $paramsname);
        $names = preg_split('/;/', $result);
        $expected = ['Admin User', 'a 4', 'b 3', 'c x 2', 'c x 2', 'd 1', 'b 3'];
        if ($DB->get_dbfamily() === 'mysql') {
            $expected = array_unique($expected);
        }
        $this->assertEquals($expected, $names, '', 0, 10, true);
    }
}
