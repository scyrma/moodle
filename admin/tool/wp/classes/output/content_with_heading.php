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

use renderer_base;

/**
 * Class content_with_heading
 *
 * @package    tool_wp
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
     * @param bool $addbuttonicon
     * @return content_with_heading
     */
    public function add_button(string $addbuttontitle, \moodle_url $addbuttonurl = null, array $attributes = [],
                               bool $addbuttonicon = true) : content_with_heading {
        $this->data['addbutton'] = true;
        $this->data['addbuttontitle'] = $addbuttontitle;
        $this->data['addbuttonurl'] = $addbuttonurl ? $addbuttonurl->out(false) : '';
        $this->data['addbuttonattrs'] = [];
        $this->data['addbuttonclasses'] = '';
        $this->data['addbuttonicon'] = $addbuttonicon;
        foreach ($attributes as $key => $value) {
            if ($key === 'class') {
                $this->data['addbuttonclasses'] .= ' ' . $value;
                continue;
            }
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
