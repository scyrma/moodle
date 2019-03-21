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
 * File exporter.
 *
 * @package    local_moodlecloud
 * @copyright  2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\external;
defined('MOODLE_INTERNAL') || die();

use core\external\exporter;
use local_moodlecloud\notifications\notification;
use moodle_url;
use stdClass;

/**
 * File type exporter.
 *
 * @copyright 2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class file_type_exporter extends exporter {
    /**
     * Constructor.
     *
     * @param notification $notification The Notification to export.
     * @param array $related Array of related data.
     */
    public function __construct(stdClass $mimetypeandsize, array $related = []) {
        parent::__construct(
            (object)[
                'mimetype' => $mimetypeandsize->mimetype,
                'size' => $mimetypeandsize->size
            ],
            $related
        );
    }

    protected static function define_properties() : array  {
        return [
            'mimetype' => ['type' => PARAM_TEXT],
            'size' => ['type' => PARAM_INT]
        ];
    }

    protected static function define_related() : array {
        return ['context' => 'context'];
    }
}
