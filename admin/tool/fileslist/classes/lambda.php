<?php declare(strict_types=1);

namespace tool_fileslist;

use Traversable;

final class lambda {
    public static function pipe(&$_) {
        return new class($_) {
            private $_;

            public function __construct(&$_) {
                $this->$_ = &$_;
            }

            public function __call(string $name, array $args) : self {
                $this->_ = $name(...$args);
                return $this;
            }
        };
    }

    public static function mapping_iterator(Traversable $traversable, callable $callable) {
        return new mapping_iterator($traversable, $callable);
    }

    // The purpose of this function is to return an anonymous class which proxies another class
    // it can be used to assign the result of calling the proxied classes methods to an element
    // in an array (which can be returned via the dump method).
    //
    // As an example, consider a simple class with a get_prop1 and get_prop2 method. You can do:
    // lambda::array_accumulator($class)->get_prop1()->get_prop2()->dump() which produces an array:
    // ['prop1', 'prop2'] (where those strings are the return values of the getters).
    //
    // You can further augment the values by using the transform method directly after calling the
    // getter you're interested in. e.g.
    //
    // lambda::array_accumulator($class)
    //         ->get_prop1()->transform(function($value) {return $value . ' hello';})
    //         ->get_prop2()
    //         ->dump()
    //
    // Producing ['prop1 hello', 'prop2']
    //
    // The insert method can be used to introduce a new value in to the array, the callback
    // is provided with the proxied class and the anonymous class:
    //
    // lambda::array_accumulator($class)
    //         ->get_prop1()->transform(function($value) {return $value . ' hello';})
    //         ->insert(function($class, $acc) {return 'inserted hello';})
    //         ->get_prop2()
    //         ->dump()
    //
    // Producing ['prop1 hello', 'inserted hello', 'prop2']
    //
    // This sort of thing can be useful for transforming data from the database in to a more strictly typed
    // set of data, which can then be passed to the constructor of a strongly typed entity or something similar.
    //
    // The primary use case is factories. This technique provides a consistent approach to transforming data from
    // Moodle's DML to more strongly typed entities.
    public static function array_accumulator($class) {
        return new class($class) {
            private $class;
            private $arr;
            private $previous;

            public function __construct($class) {
                $this->class = $class;
            }

            public function __call(string $name, array $args) : self {
                $this->arr[] = ($this->class)->{$name}(...$args);
                return $this;
            }

            public function transform(callable $callable) : self {
                $this->arr[count($this->arr) - 1] = $callable($this->arr[count($this->arr) - 1], $this);
                return $this;
            }

            public function insert(callable $callable) : self {
                $this->arr[] = $callable($this->class, $this);
                return $this;
            }

            public function to_int() : self {
                return $this->transform(function($item) {return (int)$item;});
            }

            // Get the internal array.
            public function dump() : array {
                return $this->arr;
            }

            // Get the value of the internal array at "index".
            public function get(int $index) {
                return $this->arr[$index];
            }
        };
    }
}