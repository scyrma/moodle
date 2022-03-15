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

namespace tool_wp\output;

/**
 * Class tab_base
 *
 * @package    tool_wp
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class tab implements \templatable {

    /** @var array */
    protected $data;

    /**
     * tab constructor.
     *
     * @param array $data
     */
    final public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * HTML "id" attribute that should be used for this tab, by default the last part of class name without tab_ prefix
     *
     * @return string
     */
    public function get_tab_id(): string {
        $parts = preg_split('/\\\\/', static::class);
        $tabid = array_pop($parts);
        return preg_replace('/^tab_/', '', $tabid);
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    abstract public function get_tab_label(): string;

    /**
     * Check permission of the current user to access this tab
     *
     * @return mixed
     */
    abstract public function is_available(): bool;

    /**
     * Check that tab is accessible, throw exception otherwise - used from WS requesting tab contents
     *
     * @throws \moodle_exception
     */
    public function require_access() {
        if (!$this->is_available()) {
            throw new \moodle_exception('nopermissiontab', 'tool_wp');
        }
    }

    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    abstract public function get_template(): string;
}
