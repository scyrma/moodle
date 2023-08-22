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

use core\notification;
use moodle_url;

/**
 * Class for global notifications
 *
 * @package     block_myteams
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Mikel Martín <mikel@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class global_notification {

    /** @var string $message */
    private $message;

    /** @var moodle_url $url */
    private $url;

    /** @var string $type */
    private $type;

    /**
     * Constructor.
     *
     * @param string $message
     * @param moodle_url $url
     * @param string $type type of the alert (notification::{WARNING/ERROR/INFO/SUCCESS})
     */
    public function __construct(string $message, moodle_url $url, string $type = notification::INFO) {
        $this->message = $message;
        $this->url = $url;
        $this->type = $type;
    }

    /**
     * Get notification message
     *
     * @return string
     */
    public function get_message(): string {
        return $this->message;
    }

    /**
     * Get notification link
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        return $this->url;
    }

    /**
     * Get notification type
     *
     * @return string type of the alert (notification::{WARNING/ERROR/INFO/SUCCESS})
     */
    public function get_type(): string {
        return $this->type;
    }
}
