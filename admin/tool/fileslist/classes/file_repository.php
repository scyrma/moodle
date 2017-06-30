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
 * File repository.
 *
 * @package    tool_fileslist
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fileslist;

defined('MOODLE_INTERNAL') || die();

use CallbackFilterIterator;
use Traversable;
use moodle_database;
use stdClass;

/**
 * File repository.
 *
 * This repository operates on a traversable collection of data, and
 * transforms the data in to a file object via a factory.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class file_repository {
    /**
     * @var Traversable $collection The source collection to query for file data.
     */
    private $collection;

    /**
     * @var file_factory $filefactory A file factory.
     */
    private $filefactory;

    /**
     * Constructor.
     *
     * @param Traversable $collection The source collection to query for file data.
     * @param file_factory $filefactory A file factory.
     */
    public function __construct(
        Traversable $collection,
        file_factory $filefactory
    ) {
        $this->filefactory = $filefactory;
        $this->collection = $collection;
    }

    /**
     * Get all the files.
     *
     * @param callable $filter An optional filter to filter out irellevant files.
     * @return file_iterator
     */
    public function get(callable $filter = null) : file_iterator {
        return new file_iterator(
            new mapping_iterator(
                $filter ? new CallbackFilterIterator($this->collection, $filter) : $this->collection,
                function(stdClass $filerecord) : file {
                    return $this->filefactory->create_instance($filerecord);
                }
            )
        );
    }

    /**
     * Get files we consider "valid".
     *
     * A valid file has: size, an associated user, and is not a reference file.
     *
     * @return file_iterator
     */
    public function get_valid_files() : file_iterator {
        return $this->get(function(stdClass $filerecord) : bool {
                return !!$filerecord->filesize && !!$filerecord->userid && !$filerecord->referencefileid;
            }
        );
    }
}
