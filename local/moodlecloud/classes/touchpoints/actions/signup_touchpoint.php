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
 * Signup call action.
 *
 * @package    local_moodlecloud
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\touchpoints\actions;
defined('MOODLE_INTERNAL') || die();

use local_moodlecloud\common\computation_result;
use local_moodlecloud\common\functions;
use local_moodlecloud\touchpoints\action;

/**
 * Class for calling signup when a touchpoint is executed.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class signup_touchpoint implements action {

    /** @var callable $signupapicall A function that will call signup (or any API really) */
    private $signupapicall;

    /** @var string $event The event we are calling signup for. */
    private $event;

    /** @var array $data Data to accompany the call. */
    private $data;

    /**
     * Constructor.
     *
     * @param callable $signupapicall A function that will call signup.
     * @param string $event The event we want to call signup for.
     * @param array $data Data to accompany the call.
     */
    public function __construct(callable $signupapicall, string $event, array $data) {
        $this->signupapicall = $signupapicall;
        $this->event = $event;
        $this->data = $data;
    }

    public function get_name() : string {
        return 'signup_touchpoint';
    }

    public function execute(array $previous = []) : computation_result {
        // Typically these sort of API calls throw exceptions when things go wrong
        // and return some sort of success response when things go well, so we wrap
        // the call in try_catch to get a computation_result.
        return functions::try_catch(
            ($this->signupapicall)(
                [
                    'event' => $this->event,
                    'data' => json_encode($this->data)
                ]
            )
        );
    }
}
