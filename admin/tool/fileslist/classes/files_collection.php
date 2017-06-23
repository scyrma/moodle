<?php declare(strict_types=1);

namespace tool_fileslist;

use Iterator;
use IteratorIterator;

final class files_collection extends IteratorIterator implements collection {
    public function __construct(Iterator $iterator) {
        parent::__construct($iterator);
    }

    public function current() : file {
        return parent::current();
    }

    public function get_count() : int {
        return 7;
    }

    public function get_all() : array {
        return [];
    }
}