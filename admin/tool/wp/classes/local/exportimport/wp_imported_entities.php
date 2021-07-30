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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class wp_imported_entities
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport;

defined('MOODLE_INTERNAL') || die();

/**
 * Represents a collection of entities of the given type defined in the import file
 *
 * We avoid loading all entities into the memory, instead we use this collection to iterate through them
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class wp_imported_entities implements \Iterator, \Countable {
    /** @var import_manager */
    protected $importmanager;
    /** @var string */
    protected $entityname;
    /** @var array */
    protected $ids;
    /** @var int */
    protected $position = 0;

    /**
     * wp_imported_entities constructor.
     *
     * @param import_manager $importmanager
     * @param string $entityname
     * @param callable|null $filter function that takes an array (entity) as parameter and returns boolean,
     *     if not specified all entities ids will be returned. Example:
     *     function($entity) use ($selectedids) { return in_array($entity['id'], $selectedids); }
     * @param callable|null $sorter function that takes an array (entity) as parameter and returns a field
     *     (or expression) that should be used for sorting of entities. Example:
     *     function($entity) use ($selectedids) { return $entity['pathlevel']; }
     *     By default list will be sorted by id.
     */
    public function __construct(import_manager $importmanager, string $entityname,
            ?callable $filter = null, ?callable $sorter = null) {
        $this->importmanager = $importmanager;
        $this->entityname = $entityname;
        $this->ids = $importmanager->get_entities_ids_in_workplace_export_file($entityname, $filter, $sorter);
    }

    /**
     * Retrieves the entity by id
     *
     * @param int $id
     * @return null|wp_imported_entity
     */
    protected function get_by_id($id): ?wp_imported_entity {
        if (in_array($id, $this->ids)) {
            return new wp_imported_entity($this->importmanager, $this->entityname, $this->ids[$this->position]);
        }
        return null;
    }

    /**
     * Returns the list of all the names of the entities (similar to array_column() function)
     *
     * @param string $field
     * @return array
     */
    public function get_menu(string $field = 'name') {
        $menu = [];
        foreach ($this->ids as $id) {
            $record = $this->importmanager->get_raw_data_from_workplace_export_file($this->entityname, $id);
            $menu[$id] = $record[$field];
        }
        return $menu;
    }

    /**
     * count (from interface Countable)
     *
     * @return int
     */
    public function count() {
        return count($this->ids);
    }

    /**
     * current (from interface Iterator)
     *
     * @return mixed|null|wp_imported_entity
     */
    public function current() {
        return $this->get_by_id($this->ids[$this->position]);
    }

    /**
     * next (from interface Iterator)
     */
    public function next() {
        $this->position++;
    }

    /**
     * key (from interface Iterator)
     *
     * @return int
     */
    public function key() : int {
        return $this->ids[$this->position];
    }

    /**
     * valid (from interface Iterator)
     *
     * @return bool
     */
    public function valid() : bool {
        return array_key_exists($this->position, $this->ids);
    }

    /**
     * rewind (from interface Iterator)
     */
    public function rewind() {
        $this->position = 0;
    }
}
