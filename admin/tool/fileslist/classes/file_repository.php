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
 * Files list file API service.
 *
 * @package    tool_fileslist
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fileslist;

use CallbackFilterIterator;
use moodle_database;
use stdClass;

defined('MOODLE_INTERNAL') || die();

final class file_repository {
    private $collection;
    private $filefactory;

    public function __construct(
        collection $collection,
        file_factory $filefactory
    ) {
        $this->collection = $collection;
        $this->filefactory = $filefactory;
    }

    public function get(callable $filter = null) : collection {
        return new files_collection(
            new mapping_iterator(
                $filter ? new CallbackFilterIterator($this->collection, $filter)
                        : $this->collection,
                function(stdClass $filerecord) : file {
                    return $this->filefactory->create_instance($filerecord);
                }
            )
        );
    }

    public function get_valid_files() : collection {
        return $this->get(function(stdClass $filerecord) : bool {
                return !!$filerecord->filesize && !!$filerecord->userid;
            }
        );
    }
}
