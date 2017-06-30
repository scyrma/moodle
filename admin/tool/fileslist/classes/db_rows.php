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
 * Database row collection.
 *
 * @package    local_cloud
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fileslist;

defined('MOODLE_INTERNAL') || die();

use Generator;
use Iterator;
use IteratorIterator;
use moodle_database;

/**
 * Class representing rows of data from the Moodle database.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class db_rows extends IteratorIterator {
    CONST QUERY_LIMIT = 100;

    /**
     * @var moodle_database $db Moodle database connection.
     */
    private $db;

    /**
     * @var string $table Table which the rows are from.
     */
    private $table;

    /**
     * @var string $sortfield Field to sort on.
     */
    private $sortfield;

    public function __construct(moodle_database $db, string $table, string $sortfield = null) {
        $this->db = $db;
        $this->table = $table;
        $this->sortfield = $sortfield;

        // See the comment on load_records for a bit more detail about
        // what is going on here. See also the documentation for IteratorIterator
        parent::__construct(
            // A function that yields returns an instance of Generator, which implements
            // Iterator, so we can make an Iterator really easily using a self executing
            // function and pass it to the IteratorIterator.
            (function() : Iterator {
                foreach ($this->load_records() as $records) {
                    // See PHP "generator delegation" docs.
                    yield from $records;
                }
            })()
        );
    }

    // This method yields 100 records from the database at a time, what this means is
    // that if the result of this function is used in a foreach loop, 100 DB records will
    // be loaded on each iteration of the foreach loop.
    //
    // The intended way to use this is to iterate over this iterator, then yield
    // each record in turn so that the consumer of this class can iterate over
    // the records individually as if this was a regular flat collection of
    // objects. PHP provides a class for doing exactly this, IteratorIterator.
    private function load_records(int $start = null) : Generator {
        while ($records = $this->db->get_records(
            $this->table,
            null,
            $this->sortfield ?? '',
            '*',
            $start ?? 0,
            100
        )) {
            yield $records;
            $start += self::QUERY_LIMIT;
        }
    }
}
