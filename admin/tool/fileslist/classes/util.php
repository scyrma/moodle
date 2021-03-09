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
 * Lambda util class.
 *
 * @package    tool_fileslist
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fileslist;

defined('MOODLE_INTERNAL') || die();

use Traversable;

/**
 * Util class to facilitate better programming practises.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class util {
    /**
     * Argument forwarding, use like so:
     *
     * lambda::pipe($result)->strtoupper('hello')->strrev($result);
     * echo $result; // OLLEH
     */
    public static function pipe(&$_) {
        return new class($_) {
            private $_;

            public function __construct(&$_) {
                $this->_ = &$_;
            }

            public function __call(string $name, array $args) : self {
                $this->_ = $name(...$args);
                return $this;
            }
        };
    }


    /**
     * Convenience method to get a mapping iterator.
     */
    public static function mapping_iterator(Traversable $traversable, callable $callable) {
        return new mapping_iterator($traversable, $callable);
    }

    public static function array_builder($class) {
        return new class($class) implements array_builder {
            private $class;
            private $arr;
            private $previous;

            public function __construct($class) {
                $this->class = $class;
            }

            public function push_from_value($value) : array_builder {
                $this->arr[] = $value;
                return $this;
            }

            public function push_from_callable(callable $callable) : array_builder {
                $this->arr[] = $callable($this->class, $this);
                return $this;
            }

            public function push_from_inner_class(string $methodname, ...$args) : array_builder {
                $this->arr[] = ($this->class)->{$methodname}(...$args);
                return $this;
            }

            public function transform(callable $callable) : array_builder {
                $this->arr[count($this->arr) - 1] = $callable($this->arr[count($this->arr) - 1], $this);
                return $this;
            }

            public function to_int() : array_builder {
                return $this->transform(function($item) {return (int)$item;});
            }

            // Get the internal array.
            public function build() : array {
                return $this->arr;
            }

            // Get the value of the internal array at "index".
            public function get(int $index) {
                return $this->arr[$index];
            }
        };
    }
}
