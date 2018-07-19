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
 * Admin notification action.
 *
 * @package    local_moodlecloud
 * @copyright  2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\touchpoints\actions;
defined('MOODLE_INTERNAL') || die();

use local_moodlecloud\common\computation_result;
use local_moodlecloud\common\functions;
use local_moodlecloud\touchpoints\action;

final class admin_notification implements action {
    private $notificationcall;
    private $name;
    private $body;
    private $level;

    public function __construct(
        callable $notificationcall,
        string $name,
        string $body,
        int $level
    ) {
        $this->notificationcall = $notificationcall;
        $this->name = $name;
        $this->body = $body;
        $this->level = $level;
    }

    public function get_name() : string {
        return 'admin_notification';
    }

    public function execute(array $previous =[]) : computation_result {
        return functions::try_catch(
            ($this->notificationcall)(
                $this->name,
                $this->body,
                $this->level
            )
        );
    }
}
