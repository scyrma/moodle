<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
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
//
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

declare(strict_types=1);

namespace block_myteams;

use moodle_url;

/**
 * Class for userinfo sections
 *
 * @package     block_myteams
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class userinfo_section {

    /** @var string $name */
    private $name;

    /** @var userinfo_section_item[] $items */
    private $items = [];

    /** @var moodle_url|null $link */
    private $link;

    /** @var int $order */
    private $order;

    /**
     * Constructor.
     *
     * @param string $name
     * @param moodle_url|null $link
     * @param int $order
     */
    public function __construct(string $name, ?moodle_url $link = null, int $order = 0) {
        $this->name = $name;
        $this->link = $link;
        $this->order = $order;
    }

    /**
     * Get section name
     *
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Get section link
     *
     * @return string
     */
    public function get_link(): ?moodle_url {
        return $this->link;
    }

    /**
     * Get section items, returning ordered by {@see userinfo_section_item::get_overdue} and title
     *
     * @return userinfo_section_item[]
     */
    public function get_items(): array {
        usort($this->items, static function(userinfo_section_item $a, userinfo_section_item $b): int {
            if (($result = $a->get_overdue() <=> $b->get_overdue()) !== 0) {
                return $result * -1; // Invert for descending order.
            }
            return strcasecmp($a->get_title(), $b->get_title());
        });

        return $this->items;
    }

    /**
     * Get section order
     *
     * @return int
     */
    public function get_order(): int {
        return $this->order;
    }

    /**
     * Get section overdue status, that being calculated based on whether any of it's items are considered overdue in order
     * to indicate that the user linked to the section should be flagged as requiring further attention
     *
     * @return bool
     */
    public function get_overdue(): bool {
        $overdueitems = array_filter($this->get_items(), static function(userinfo_section_item $item): bool {
            return $item->get_overdue();
        });

        return !empty($overdueitems);
    }

    /**
     * Set section overdue status
     *
     * @param bool $overdue
     *
     * @deprecated Please set overdue state on individual section items
     */
    public function set_overdue(bool $overdue): void {
        debugging(__FUNCTION__ . " is deprecated, please set overdue state on individual section items", DEBUG_DEVELOPER);
    }

    /**
     * Add a section item
     *
     * @param userinfo_section_item $item
     */
    public function add_item(userinfo_section_item $item): void {
        $this->items[] = $item;
    }
}
