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
 * User quota percentage reached criterion.
 *
 * @package    local_moodlecloud
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\touchpoints\criteria;
defined('MOODLE_INTERNAL') || die();

use local_moodlecloud\touchpoints\criterion;
use local_moodlecloud\restrictions\userquota;

/**
 * Class representing whether or not a site hasuse more than a given percentage
 * of its available user slots.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reached_user_quota_percentage implements criterion {

    /** @var float $threshold Threshold to test against. */
    private $threshold;

    /**
     * Constructor.
     *
     * @param float $threshold Threshold to test against.
     */
    public function __construct(float $threshold) {
        $this->threshold = $threshold;
    }

    /**
     * Is the site over the threshold?
     *
     * @return bool
     */
    public function is_met() : bool {
        return !empty(userquota::number_of_user_slots_remaining()) &&
               1 - userquota::number_of_user_slots_remaining() / MOODLECLOUD_USER_QUOTA >= $this->threshold;;
    }
}
