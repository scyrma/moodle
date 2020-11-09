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
 * MoodleCloud site notification.
 *
 * @package    local_moodlecloud
 * @copyright  2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\notifications;
defined('MOODLE_INTERNAL') || die();

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A notification.
 *
 * @copyright  2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notification {
    /** @var int Info notification. */
    const INFO = 1;

    /** @var int Warning notification. */
    const WARNING = 2;

    /** @var int Error notification. */
    const ERROR = 3;

    /** @var array List of possible notification levels */
    const LEVELS = [
        self::INFO,
        self::WARNING,
        self::ERROR
    ];

    /** @var int $id The notification's ID in the database. */
    private $id;

    /** @var string $name The notification name, displayed in the popover. */
    private $name;

    /** @var string $body The notification body. Displayed in the full list. */
    private $body;

    /** @var DateTimeImmutable $date The notification date. */
    private $date;

    /** @var int $level The notification level. */
    private $level;

    /** @var string $source The notification source. */
    private $source;

    /**
     * Constructor.
     *
     * @param int $id The ID of the notification in the database.
     * @param string $name The notification name.
     * @param string $body The notification body.
     * @param DateTimeImmutable $date The notification date.
     * @param int $level The notification level.
     * @param string $source The notification source.
     * @throws InvalidArgumentException
     */
    public function __construct(
        int $id = null,
        string $name,
        string $body,
        DateTimeImmutable $date,
        int $level,
        string $source
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->body = $body;
        $this->date = $date;
        $this->level = $level;
        $this->source = $source;

        if (!in_array($level, self::LEVELS)) {
            throw new InvalidArgumentException("Invalid notification level.");
        }
    }

    /**
     * Get the notification's ID.
     *
     * @return int The notification's ID. -1 if it isn't in the database yet.
     */
    public function get_id() : int {
        return $this->id ?? -1;
    }

    /**
     * Get the notification's name.
     *
     * @return string The notification name.
     */
    public function get_name() : string {
        return $this->name;
    }

    /**
     * Get the notification's body text.
     *
     * @return string The notification body text.
     */
    public function get_body() : string {
        return $this->body;
    }

    /**
     * Get the notification date.
     *
     * @return DateTimeImmutable The notification date.
     */
    public function get_date() : DateTimeImmutable {
        return $this->date;
    }

    /**
     * Get the notification level.
     *
     * @return int
     */
    public function get_level() : int {
        return $this->level;
    }

    /**
     * Get the notification source.
     *
     * @return string The notification source.
     */
    public function get_source() : string {
        return $this->source;
    }
}
