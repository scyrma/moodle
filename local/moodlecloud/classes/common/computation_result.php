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
 * Computation result.
 *
 * @package    local_moodlecloud
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\common;
defined('MOODLE_INTERNAL') || die();

use local_moodlecloud\common\bimappable;

/**
 * Implementation of the Either type.
 *
 * This implementation is used to represent a computation that
 * maybe have "gone wrong". It can be an instance of success
 * or failure only, each of which wrap a value.
 *
 * To create a computation_result the named constructors success
 * and failure must be used.
 *
 * By convention a value wrapped in a success should be the
 * correct return value of a successful computation, a value
 * wrapped in a failure represents a computation that failed.
 *
 * Aa good example of such a computation is an HTTP request to
 * an external API.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class computation_result implements bimappable {

    /** @var mixed The wrapped value. */
    protected $value;

    /**
     * Constructor.
     *
     * @param mixed $value The value to wrap.
     */
    final private function __construct($value) {
        $this->value = $value;
    }

    /**
     * Named constructor.
     *
     * @param mixed $value The value to wrap.
     * @return self;
     */
    final public static function failure($value) : self {
        return new failure($value);
    }

    /**
     * Named constructor.
     *
     * @param mixed $value The value to wrap.
     * @return self
     */
    final public static function success($value) : self {
        return new success($value);
    }

    /**
     * Apply a callback and return the wrapped value.
     *
     * @param callable $c1 Callable to apply if the wrapped value is a failure.
     * @param callable $c2 Callable to apply if the wrapped value is a success.
     * @return mixed In practice the same type as what was originally wrapped should be returned.
     */
    public abstract function extract(callable $c1, callable $c2);
}
