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
 * MoodleCloud utility functions. Takes some inspiration from pure functional languages.
 *
 * @package    local_moodlecloud
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\common;
defined('MOODLE_INTERNAL') || die();

use Exception;
use InvalidArgumentException;
use Traversable;
use local_moodlecloud\common\{bimappable, computation_result, success, failure};

class functions {

    /**
     * Just like array_reduce but works with any Traversable.
     *
     * See PHP docs for array_reduce for a decent explanation of the idea.
     *
     * NB: Arrays do *not* implement Traversable.
     *
     * TODO: Would be really cool if the final param was `foldable` instead of traversable.
     *
     * @param callable $callback Callback to apply to each element.
     * @param mixed $initial Initial value for the fold process.
     * @param Traversable $traversable Traversable to iterate over.
     * @return mixed
     */
    public static function iterator_reduce(callable $callback, $initial, Traversable $traversable) {
        foreach ($traversable as $value) {
            $carry = $callback($carry ?? $initial, $value);
        }

        return $carry;
    }

    /**
     * Find the first value in a Traversable that satisfies some criterion.
     *
     * @param callable $criterion Criterion array elements against.
     * @param Traversable $traversable Traverable to check.
     * @return mixed The first value matching $criterion, null if no such element exists.
     */
    public static function find(callable $criterion, Traversable $traversable) {
        foreach($traversable as $element) {
            if($criterion($element)) {
                return $element;
            }
        }

        return null;
    }

    /**
     * A function that always returns null no matter what.
     *
     * @return null
     */
    public static function ignore() {
        return null;
    }

    /**
     * The identity function.
     *
     * @param mixed $x
     * @return mixed Always returns $x
     */
    public static function identity($x) {
        return $x;
    }

    /**
     * Function composition.
     *
     * @param callable $fs,...
     * @return callable The composition, $f1 ∘ $f1 ∘ ... ∘ $fn
     */
    public static function compose(callable ...$fs) : callable {
        return function($arg) use ($fs) {
            return array_reduce(array_reverse($fs), function($c, $f) {
                return $f($c);
            }, $arg);
        };
    }

    /**
     * Pipe the output of one function to the input of the next.
     *
     * @param callable $callables,... The callables to pipe through.
     * @return mixed
     */
    public static function pipe_forward(callable ...$callables) {
        return array_reduce(array_slice($callables, 1), function($c, $v) {
            return $v($c);
        }, $callables[0]());
    }

    /**
     * Partial function application.
     *
     * @param callable $f The function to partially apply.
     * @param mixed $args,... The arguments to partially apply.
     * @return callable A new function that with whatever parameters are left over after applying $args.
     */
    public static function partial(callable $f, ...$args) {
        return function(...$args2) use ($f, $args) {
            return $f(...array_merge($args, $args2));
        };
    }

    /**
     * Create a class instance from a string.
     *
     * @param string $classname Name of the class to instantiate.
     * @param mixed $arguments,... Arguments to be passed to the class constructor.
     * @throws InvalidArgumentException if the class does not exist.
     * @return mixed
     */
    public static function instance_from_string(string $classname, ...$arguments) {
        if (!class_exists($classname)) {
            throw new InvalidArgumentException('Invalid classname: ' . $classname);
        }

        return new $classname(...$arguments);
    }

    /**
     * Create a succesful computation_result.
     *
     * @param mixed $a The value to wrap.
     * @return computation_result
     */
    public static function success($a) : computation_result {
        return computation_result::success($a);
    }

    /**
     * Create a failed computation result.
     *
     * @param $a The value to wrap.
     * @return computation_result
     */
    public static function failure($a) : computation_result {
        return computation_result::failure($a);
    }

    /**
     * Wrap an exception-throwing computation.
     *
     * @param callable $f The computation to execute.
     * @return computation_result The result of $f as a success if it succeded, a failure otherwise.
     */
    public static function try_catch(callable $f) : computation_result {
        try {
            return self::success($f());
        } catch (Exception $e) {
            return self::failure($e->getMessage());
        }
    }

    /**
     * Extract all successes from a list of computation results.
     *
     * @param array $l List of computation_results
     * @return array An array of all the values that were successes.
     */
    public static function successes(array $l) : array {
        return self::compose(
            // After extract is run, values that were failures will be null. Filter will remove those.
            'array_filter',
            self::partial('array_map', function(computation_result $a) {
                return $a->extract(self::class . '::ignore', self::class . '::identity');
            })
        )($l);
    }

    /**
     * Extract all failures from a list of computation results.
     *
     * @param array $l List of computation_results
     * @return array An array of all the values that were failures.
     */
    public static function failures(array $l) : array {
        return self::compose(
            // After extract is run, values that were successes will be null. Filter will remove those.
            'array_filter',
            self::partial('array_map', function(computation_result $a) {
                return $a->extract(self::class . '::identity', self::class . '::ignore');
            })
        )($l);
    }

    /**
     * Bimap two functions over a bimappable.
     *
     * @param callable $c1
     * @param callable $c2
     * @return bimappable
     */
    public static function bimap(callable $c1, callable $c2, bimappable $p) : bimappable {
        return $p->bimap($c1, $c2);
    }

    public static function join_on_comma(array $strings) : string {
        return join(',', $strings);
    }

    public static function split_on_comma(string $commadelim) : array {
        return explode(',', $commadelim);
    }

    public static function export(string ...$what) {
        return array_map(function($what) {
            return self::class . '::' . $what;
        }, $what);
    }
}
