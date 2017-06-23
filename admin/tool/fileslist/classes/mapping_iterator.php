<?php declare(strict_types=1);

namespace tool_fileslist;

use Traversable;
use IteratorIterator;

final class mapping_iterator extends IteratorIterator {
    private $callback;

    public function __construct(Traversable $iterator, callable $callback) {
        parent::__construct($iterator);

        $this->callback = $callback;
    }

    public function current() {
        return ($this->callback)(parent::current());
    }
}
