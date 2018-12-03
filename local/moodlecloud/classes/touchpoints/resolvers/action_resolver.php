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
 * Action resolver.
 *
 * @package    local_moodlecloud
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\touchpoints\resolvers;
defined('MOODLE_INTERNAL') || die();

use local_moodlecloud\common\functions;
use local_moodlecloud\touchpoints\action;
use local_moodlecloud\touchpoints\resolver;

/**
 * Class to resolve actions from a simple string.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class action_resolver implements resolver {

    /** @var string $actionnamespace The namespace where actions live. */
    private $actionnamespace;

    /** @var callable $signupapicall A function that knows how to call signup. */
    private $signupapicall;

    /** @var callable $notificationcall A function that knows how to make admin notifications. */
    private $notificationcall;

    /**
     * Constructor.
     *
     * @param string $actionnamespace The namespace where actions live.
     * @param callable $signupapicall A function that knows how to call signup.
     * @param callable $notificationcall A function that knows how to make admin notifications.
     */
    public function __construct(string $actionnamespace, callable $signupapicall, callable $notificationcall) {
        $this->actionnamespace = $actionnamespace;
        $this->signupapicall = $signupapicall;
        $this->notificationcall = $notificationcall;
    }

    public function resolve(string $actionname, ...$arguments) : action {
        // Special case for signup_touchpoint action. Hardcoded for now.
        if ($actionname === 'signup_touchpoint') {
            return functions::instance_from_string(
                $this->actionnamespace . '\\' . $actionname, $this->signupapicall,
                ...$arguments
            );
        }

        if ($actionname == 'admin_notification') {
            return functions::instance_from_string(
                $this->actionnamespace . '\\' . $actionname, $this->notificationcall,
                ...$arguments
            );
        }

        return functions::instance_from_string($this->actionnamespace . '\\' . $actionname, ...$arguments);
    }
}
