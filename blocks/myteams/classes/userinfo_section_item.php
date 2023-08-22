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

/**
 * Class for userinfo section items
 *
 * @package     block_myteams
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class userinfo_section_item {

    /** @var string $title */
    private $title;

    /** @var string $subtitle */
    private $subtitle;

    /** @var array $badges */
    private $badges = [];

    /** @var bool $overdue */
    private $overdue = false;

    /**
     * Constructor.
     *
     * @param string $title
     * @param string $subtitle
     */
    public function __construct(string $title, string $subtitle) {
        $this->title = $title;
        $this->subtitle = $subtitle;
    }

    /**
     * Get item title
     *
     * @return string
     */
    public function get_title(): string {
        return $this->title;
    }

    /**
     * Get item subtitle
     *
     * @return string
     */
    public function get_subtitle(): string {
        return $this->subtitle;
    }

    /**
     * Get item overdue status
     *
     * @return bool
     */
    public function get_overdue(): bool {
        return $this->overdue;
    }

    /**
     * Set item overdue status, to indicate that the user linked to the item should be flagged in the block as requiring
     * further attention. Plugins can define their own rules as to whether they consider items as overdue/requiring attention
     *
     * Items flagged as such will be listed with higher priority within the block
     *
     * @param bool $overdue
     */
    public function set_overdue(bool $overdue): void {
        $this->overdue = $overdue;
    }

    /**
     * Get item badges
     *
     * @return array
     */
    public function get_badges(): array {
        return $this->badges;
    }

    /**
     * Add a badge to the item
     *
     * @param string $label of badge
     * @param string $type of badge
     */
    public function add_badge(string $label, string $type): void {
        $this->badges[] = ['label' => $label, 'type' => $type];
    }
}
