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

            public function dump() : array {
                return $this->arr;
            }

            public function get(int $index) {
                return $this->arr[$index];
            }
        };
    }
}