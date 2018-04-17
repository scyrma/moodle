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
 * Notification exporter.
 *
 * @package    local_moodlecloud
 * @copyright  2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\notifications\external;
defined('MOODLE_INTERNAL') || die();

use core\external\exporter;
use local_moodlecloud\notifications\notification;
use moodle_url;

/**
 * Notification exporter.
 *
 * @copyright 2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notification_exporter extends exporter {
    /**
     * Constructor.
     *
     * @param notification $notification The Notification to export.
     * @param array $related Array of related data.
     */
    public function __construct(notification $notification, array $related = []) {
        parent::__construct(
            (object)[
                'id' => $notification->get_id(),
                'name' => $notification->get_name(),
                'body' => $notification->get_body(),
                'date' => $notification->get_date()->format('Y-m-d'),
                'viewmoreurl' => (new moodle_url('/local/moodlecloud/notifications.php'))->out(true),
                'is_info' => $notification->get_level() == notification::INFO,
                'is_warning' => $notification->get_level() == notification::WARNING,
                'is_error' => $notification->get_level() == notification::ERROR
            ],
            $related
        );
    }

    protected static function define_properties() : array  {
        return [
            'id' => ['type' => PARAM_INT],
            'name' => ['type' => PARAM_TEXT],
            'body' => ['type' => PARAM_TEXT],
            'date' => ['type' => PARAM_TEXT],
            'viewmoreurl' => ['type' => PARAM_URL],
            'is_info' => ['type' => PARAM_BOOL],
            'is_warning' => ['type' => PARAM_BOOL],
            'is_error' => ['type' => PARAM_BOOL]
        ];
    }

    protected static function define_related() : array {
        return ['context' => 'context'];
    }
}
