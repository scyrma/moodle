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
 * Touchpoints container.
 *
 * @package    local_moodlecloud
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\touchpoints;
defined('MOODLE_INTERNAL') || die();

use DateTimeImmutable;
use Exception;
use local_moodlecloud\moodlecloud_api;
use local_moodlecloud\touchpoints\exceptions\not_found;
use local_moodlecloud\touchpoints\touchpoint_factory;
use local_moodlecloud\touchpoints\touchpoint_repository;
use stdClass;

/**
 * A PSR-ish looking container.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class container {

    /** @var array $definitions An array of container definitions (a dictionary basically). */
    private $definitions;

    /** @var array $pool A pool of already created objects to be reused as requested. */
    private $pool;

    /**
     * Constructor.
     *
     * @param array $definitions An array of container definitions (a dictionary basically).
     */
    public function __construct(array $definitions) {
        $this->definitions = $definitions;

    }

    /**
     * Does this container have an object with the given ID?
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id) : bool {
        return isset($this->definitions[$id]);
    }


    /**
     * Get the object with the given id.
     *
     * @param string $id
     * @throws Exception when the container doesn't have an object with the given id.
     * @return mixed The object.
     */
    public function get(string $id) {
        if (!$this->has($id)) {
            throw new \Exception($id . ' is not in this container');
        }

        if (!is_callable($this->definitions[$id])) {
            return $this->definitions[$id];
        }

        return $this->pool[$id] = $this->pool[$id] ?? $this->definitions[$id]($this);
    }
}
