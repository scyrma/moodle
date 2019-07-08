<?php
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
 * Class report_action
 *
 * @package tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder;

defined('MOODLE_INTERNAL') || die();

/**
 * Class report_action
 *
 * @package tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_action {
    /** @var \moodle_url $url */
    protected $url;
    /** @var \pix_icon $pix */
    protected $pix;
    /** @var array $attributes Attributes to be render as data-* */
    protected $attributes;
    /** @var callable[] $callbacks Functions to check if the user can see the action icon. */
    protected $callbacks = [];
    /** @var callable */
    protected $preprocessingcallback;

    /**
     * Constructor
     *
     * Creates an instance of an action that will be added to automatically created 'Actions' column in the report.
     * Both URL parameters and attributes can have placeholder values. For example:
     *
     * $url = new \moodle_url('/somepath.php', ['id' => ':id']);
     * $pixicon = new \pix_icon('a/wp-cog', '', 'theme');
     * $attributes = ['data-id' => ':id', 'data-name' => ':name', 'data-action' => 'edit', 'title' => get_string('edit')];
     *
     * Use system_report::add_base_sql() to select fields that are used in placeholders
     *
     * See also add_callback() that allows to check permissions and pre-process values for placeholders
     *
     * @param \moodle_url $url URL for the action
     * @param \pix_icon   $pixicon Icon element to display, it is recommended not to have 'alt' or 'title' there
     * @param array       $attributes Additional attributes
     */
    public function __construct(\moodle_url $url, \pix_icon $pixicon, array $attributes = []) {
        $this->url = $url;
        $this->pix = $pixicon;
        $this->attributes = $attributes;

        $pixattr = $this->pix->attributes + ['title' => null, 'alt' => null];
        if ($pixattr['title'] === $pixattr['alt']) {
            // Ignore 'alt' attribute on the pix_icon if it is the same as title. See also MDL-46226.
            unset($this->pix->attributes['alt']);
        }

        if (!empty($pixattr['title'])) {
            if (!empty($attributes['title']) && $attributes['title'] !== $pixattr['title']) {
                throw new \coding_exception('Different titles in the pix_icon and in the attributes');
            } else {
                // Title is not displayed correctly on the pix_icon.
                $this->attributes['title'] = $this->pix->attributes['title'];
                unset($this->pix->attributes['title']);
            }
        }
    }

    /**
     * Render the action icon.
     *
     * @param \stdClass $row
     *
     * @return string
     */
    public function get_icon(\stdClass $row) {
        global $OUTPUT;

        // Check permissions and pre-process values.
        if ($this->callbacks) {
            $row = (object)(array)$row; // Clone so we don't modify the shared row object inside the callback.
            foreach ($this->callbacks as $callback) {
                if (!$callback($row)) {
                    return '';
                }
            }
        }

        $dataattributes = array();
        $url = new \moodle_url($this->url);
        foreach ($url->params() as $key => $value) {
            $url->param($key, $this->replace_placeholder($value, $row));
        }
        foreach ($this->attributes as $name => $value) {
            $dataattributes[$name] = $this->replace_placeholder($value, $row);
        }

        return $OUTPUT->action_icon($url, $this->pix, null, $dataattributes);
    }

    /**
     * Replace a placeholder (:placeholder) with the match property in the row.
     *
     * @param string $value
     * @param \stdClass $row
     *
     * @return string
     */
    private function replace_placeholder($value, \stdClass $row) {
        if (preg_match("#^:(.*)$#", $value, $matches) && property_exists($row, $matches[1])) {
            return $row->{$matches[1]};
        }
        return $value;
    }

    /**
     * Adds an action callback. Useful to check permissions or preprocess values used in placeholders.
     *
     * More than one callback can be added. If at least one callback returns false the action will not be displayed.
     * It can also be used to modify some properties - call format_string(), userdate(), fullname() and other useful
     * pre-processing methods before replacing placeholders
     *
     * Chainable
     *
     * @param callable $callable function that takes arguments (\stdClass $row) and returns bool
     * @return report_action
     */
    public function add_callback(callable $callable) : report_action {
        $this->callbacks[] = $callable;
        return $this;
    }

    /**
     * Returns the list of all placeholders used in this action (for validation purposes)
     *
     * @return array
     */
    public function get_all_placeholders() : array {
        $placeholders = [];
        $allvalues = array_merge(array_values($this->url->params()), array_values($this->attributes));
        foreach ($allvalues as $value) {
            if (preg_match("#^:(.*)$#i", $value, $matches)) {
                $placeholders[$matches[1]] = 1;
            }
        }
        return array_keys($placeholders);
    }
}