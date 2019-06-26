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
 * File containing tests for user::sql_fullname()
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the function user::sql_fullname
 *
 * @package     tool_reportbuilder
 * @covers \tool_reportbuilder\local\entities\user
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_reportbuilder_sql_fullname_testcase extends advanced_testcase {

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
        list($sql, $params) = \tool_reportbuilder\local\entities\user::sql_fullname('u', $override);
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

        list($sql1, $params1) = \tool_reportbuilder\local\entities\user::sql_fullname('t');
        list($sql2, $params2) = \tool_reportbuilder\local\entities\user::sql_fullname('t');
        $users = $DB->get_fieldset_sql("SELECT $sql1 AS name FROM {user} t WHERE id > 1 ORDER BY $sql2",
            $params1 + $params2);
        $this->assertEquals(['Admin User', 'b 2', 'c 1'], $users);
        $users = $DB->get_fieldset_sql("SELECT $sql1 AS name FROM {user} t WHERE id > 1 ORDER BY name",
            $params1 + $params2);
        $this->assertEquals(['Admin User', 'b 2', 'c 1'], $users);

        $CFG->fullnamedisplay = 'lastname firstname';
        list($sql1, $params1) = \tool_reportbuilder\local\entities\user::sql_fullname('t');
        list($sql2, $params2) = \tool_reportbuilder\local\entities\user::sql_fullname('t');
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

        list($sql1, $params1) = \tool_reportbuilder\local\entities\user::sql_fullname('t');
        $sql2 = \tool_reportbuilder\local\entities\user::sql_fullname('t', false, true);
        $users = $DB->get_fieldset_sql("SELECT $sql1 AS name FROM {user} t WHERE id > 1 GROUP BY $sql2 ORDER BY $sql2",
            $params1);
        $this->assertEquals(['Admin User', 'b 2', 'c 1'], $users);

        $CFG->fullnamedisplay = 'lastname firstname';
        list($sql1, $params1) = \tool_reportbuilder\local\entities\user::sql_fullname('t');
        $sql2 = \tool_reportbuilder\local\entities\user::sql_fullname('t', false, true);
        $users = $DB->get_fieldset_sql("SELECT $sql1 AS name FROM {user} t WHERE id > 1 GROUP BY $sql2 ORDER BY $sql2",
            $params1);

        $this->assertEquals(['1 c', '2 b', 'User Admin'], $users);
    }
}