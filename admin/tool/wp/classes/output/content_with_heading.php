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
 * Class content_with_heading
 *
 * @package    tool_wp
 * @copyright  2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_wp\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;

/**
 * Class content_with_heading
 *
 * @package    tool_wp
 * @copyright  2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_with_heading implements \templatable {

    /** @var array */
    protected $data;

    /**
     * content_with_heading constructor.
     *
     * @param string $content
     * @param string $heading
     */
    public function __construct(string $content, string $heading = '') {
        $this->data = [
            'content' => $content,
            'tabheading' => $heading
        ];
    }

    /**
     * Adds the "+" button
     *
     * @param string $addbuttontitle
     * @param \moodle_url|null $addbuttonurl if omitted, '#' will be displayed
     * @param array $attributes
     * @return content_with_heading
     */
    public function add_button(string $addbuttontitle, \moodle_url $addbuttonurl = null,
                               array $attributes = []) : content_with_heading {
        $this->data['addbuttontitle'] = $addbuttontitle;
        $this->data['addbuttonurl'] = $addbuttonurl ? $addbuttonurl->out(false) : '';
        $this->data['addbuttonattrs'] = [];
        foreach ($attributes as $key => $value) {
            $this->data['addbuttonattrs'][] = ['name' => $key, 'value' => $value];
        }
        return $this;
    }

    /**
     * Adds a wrapper for the whole template
     *
     * @param array $attributes html attributes for the <div> element (class, id, data-... )
     * @return content_with_heading
     */
    public function add_wrapper(array $attributes) : content_with_heading {
        $this->data['haswrapper'] = 1;
        $this->data['wrapperattributes'] = [];
        foreach ($attributes as $key => $value) {
            $this->data['wrapperattributes'][] = ['name' => $key, 'value' => $value];
        }
        return $this;
    }

    /**
     * Adds a wrapper for the content
     *
     * @param array $attributes html attributes for the <div> element (class, id, data-... )
     * @return content_with_heading
     */
    public function add_content_wrapper(array $attributes) : content_with_heading {
        $this->data['hascontentwrapper'] = 1;
        $this->data['contentwrapperattributes'] = [];
        foreach ($attributes as $key => $value) {
            $this->data['contentwrapperattributes'][] = ['name' => $key, 'value' => $value];
        }
        return $this;
    }

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        return $this->data;
    }
}
