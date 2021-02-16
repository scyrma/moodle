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
 * Array builder interface.
 *
 * @package    tool_fileslist
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fileslist;

defined('MOODLE_INTERNAL') || die();

/**
 * Interface for an array builder class.
 *
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface array_builder {
    /**
     * Push a value in to the array.
     *
     * @param mixed $value The value to push.
     * @return self
     */
    public function push_from_value($value) : array_builder;

    /**
     * Push a value from a callback in to the array.
     *
     * @param callable $callable A callable to provide a value to push. It is passed as arguments
     *                           the inner class and the current instance of array_builder
     * @return self
     */
    public function push_from_callable(callable $callable) : array_builder;

    /**
     * Push a value from the inner class.
     *
     * @param string $methodname The name of the method from the inner class to call.
     * @param mixed ...$args Arguments to apply to the method.
     * @return self
     */
    public function push_from_inner_class(string $methodname, ...$args) : array_builder;

    /**
     * Transform a value that has been pushed.
     *
     * @param callable $callable The callable to transform the value. It is passed as an argument the previous
     *                           value pushed.
     * @return self
     */
    public function transform(callable $callable) : self;

    /**
     * Convert the last pushed value to an int.
     *
     * @return self
     */
    public function to_int() : array_builder;

    /**
     * Return the value that was pushed at $index.
     *
     * @param int $index The index from which to retrieve the value.
     * @return mixed The value at $index.
     */
    public function get(int $index);

    /**
     * Build the array from all the values/transformations that have been pushed.
     * @return array The built array.
     */
    public function build() : array;
}
