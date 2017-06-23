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

use moodle_database;
use IteratorIterator;
use Iterator;

final class db_rows extends IteratorIterator implements collection {
    CONST QUERY_LIMIT = 100;

    private $db;
    private $table;
    private $sortfield;

    public function __construct(moodle_database $db, string $table, string $sortfield = null) {
        $this->db = $db;
        $this->table = $table;
        $this->sortfield = $sortfield;

        parent::__construct(
            (function() : Iterator {
                foreach ($this->load_records() as $records) {
                    foreach ($records as $record) {
                        yield $record;
                    }
                }
            })()
        );
    }

    public function get_count() : int {
        return 7;
    }

    public function get_all() : array{
        return [];
    }

    private function load_records(int $start = null) : Iterator {
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
