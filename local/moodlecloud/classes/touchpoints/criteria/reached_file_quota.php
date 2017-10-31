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
 * File quota percentage reached criterion.
 *
 * @package    local_moodlecloud
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\touchpoints\criteria;
defined('MOODLE_INTERNAL') || die();

use local_moodlecloud\touchpoints\criterion;
use local_filestorage\file_storage\file_system_s3;

/**
 * Class representing whether or not a site has exhausted its file quota.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reached_file_quota_percentage implements criterion {

    /**
     * Has the site exhausted its file quota?
     *
     * @return bool
     */
    public function is_met() : bool {
        return file_system_s3::unique_storage_size_used() >= FILESTORAGE_QUOTA;
    }
}
