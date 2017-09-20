<?php declare(strict_types=1);
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
 * Container for files list API.
 *
 * The purpose of this class is to wire together the various components
 * to produce a solution to retrieving a list of relevant files.
 *
 * @package    tool_fileslist
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fileslist;

defined('MOODLE_INTERNAL') || die();

use core_user;
use stdClass;

/**
 * Container.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class container {
    /**
     * @var array $usercache Cache of user objects.
     */
    private static $usercache;

    /**
     * Get a file repository that sorts files by size.
     *
     * Note that unlike a traditional get method from a DI-esque container
     * this method returns a new intance of the repository every time. This
     * is because most of the components of it are Iterators, and some are Generators
     * meaning that rewinding the collections is not possible.
     *
     * If the results of a call to one of the repository's methods need to be used
     * in several places, a good idea is to use iterator_to_array to copy the results
     * in to an array.
     *
     * @return file_repository
     */
    public static function get_files_by_size_repository() : file_repository {
        global $DB;
        return new file_repository(
            new db_rows($DB, 'files', 'filesize DESC'),
            new file_factory(
                get_file_storage(),
                function(int $uid) : stdClass {
                    return self::get_userrecords()[$uid];
                }
            )
        );
    }

    /**
     * Helper function to populare the user cache. We simply get every single
     * user that is featured in the files table, since at most there will be 500
     * of them.
     *
     * The alternative is doing a DB query in a loop as we come accross unseen users.
     * This seems less evil.
     */
    private static function get_userrecords() : array {
        global $DB;
        return self::$usercache = self::$usercache ?? ($DB->get_records_sql('SELECT DISTINCT u.* from {user} u INNER JOIN {files} f on u.id = f.userid'));
    }
}
