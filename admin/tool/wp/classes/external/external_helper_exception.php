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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_wp\external;

/**
 * Exception that can be thrown when we are looking up users or other items. Can be exported as WS warning
 *
 * @package    tool_wp
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class external_helper_exception extends \moodle_exception {
    /**
     * Constructor
     *
     * @param string $item item that caused exception
     * @param string $code error code
     * @param string $message error message in English (WS error messages are not translated)
     */
    public function __construct(protected string $item, string $code, string $message) {
        $this->errorcode = $code;
        \Exception::__construct($message);
    }

    /**
     * Returns information to be used as a warning in the external API.
     *
     * See also {@see \core_external\external_warnings}
     *
     * @param int $itemid
     * @return array
     */
    public function export_as_warning(int $itemid): array {
        return [
            'itemid' => $itemid,
            'item' => $this->item,
            'warningcode' => $this->errorcode,
            'message' => $this->message,
        ];
    }
}
