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
 * Mapping iterator.
 *
 * @package    tool_fileslist
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fileslist;

defined('MOODLE_INTERNAL') || die();

use IteratorIterator;
use Traversable;

/**
 * Iterator which applies a callback to each item.
 *
 * For some reason SPL provides a filtering iterator, but not a mapping one.
 * So here it is.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mapping_iterator extends IteratorIterator {
    /**
     * @var callable $callback Callback to apply to each element.
     */
    private $callback;

    public function __construct(Traversable $traversable, callable $callback) {
        parent::__construct($traversable);

        $this->callback = $callback;
    }

    public function current() {
        return ($this->callback)(parent::current());
    }
}
