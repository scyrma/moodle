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
 * User exporter.
 *
 * @package    tool_fileslist
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fileslist\external;

defined('MOODLE_INTERNAL') || die();

use core\external\exporter;
use moodle_url;
use stdClass;

/**
 * Class for exporting a user object.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_exporter extends exporter {
    /**
     * @var stdClass $user User object to export.
     */
    private $user;

    public function __construct(stdClass $user, array $related = []) {
        $this->user = $user;

        parent::__construct(
            (object)[
                'fullname' => fullname($this->user),
                'profile' => (new moodle_url('/user/profile.php', ['id' => $this->user->id]))->out(true),
                'id' => $this->user->id
            ],
            $related
        );
    }

    protected static function define_properties() : array {
        return [
            'fullname' => ['type' => PARAM_TEXT],
            'id' => ['type' => PARAM_INT],
            'profile' => ['type' => PARAM_URL]
        ];
    }

    protected static function define_related() : array {
        return ['context' => 'context'];
    }
}
