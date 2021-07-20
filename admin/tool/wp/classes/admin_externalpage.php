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

/**
 * Class admin_externalpage
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Class admin_externalpage
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class admin_externalpage extends \admin_externalpage {

    /** @var callable */
    protected $accesscheckcallback;

    /**
     * admin_externalpage constructor.
     *
     * @param string $name
     * @param string $visiblename
     * @param string $url
     * @param callable $accesscheckcallback a method that will be executed to check if user has permission
     *     to access this item. The instance of this setting ($this) is passed as an argument to this callback.
     * @param bool $hidden
     */
    public function __construct(string $name, string $visiblename, string $url, callable $accesscheckcallback,
                                bool $hidden = false) {
        parent::__construct($name, $visiblename, $url, [], $hidden);
        $this->accesscheckcallback = $accesscheckcallback;
    }

    /**
     * see \admin_externalpage
     *
     * @return bool Returns true for yes false for no
     */
    public function check_access() {
        $callback = $this->accesscheckcallback;
        return $callback($this);
    }
}
