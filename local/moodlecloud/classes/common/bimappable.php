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
 * Bimappable interface.
 *
 * @package    local_moodlecloud
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\common;
defined('MOODLE_INTERNAL') || die();

/**
 * Interface that approximates a bifunctor.
 *
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface bimappable {

    /**
     * Bimap two functions over the bimappable.
     *
     * An intuitive way to think of bimap is that it's like array_map
     * but it takes two callables and applies them over "something".
     * In the array_map case "something" is an array, in this bimap
     * case "something" is the object implementing bimappable, and
     * what exactly happens when those functions are mapped over the
     * object is up to the implementation.
     *
     * @param callable $c1 First callable.
     * @param callable $c2 Second callable.
     * @return self
     */
    public function bimap(callable $c1, callable $c2) : self;
}
