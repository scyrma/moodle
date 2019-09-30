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
 * Class tabs
 *
 * @package    tool_wp
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_wp\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;

/**
 * Class tabs
 *
 * @package    tool_wp
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tabs implements \templatable {

    /** @var array */
    protected $attributes;
    /** @var tab[]  */
    protected $tabs = [];

    /**
     * tabs constructor.
     *
     * @param array $attributes additional attributes that will be passed to the callback PLUGINNAME_get_tab_content
     * @param tab[] $tabs array of tab
     * @throws \coding_exception
     */
    public function __construct(array $attributes = [], array $tabs = []) {
        $this->attributes = $attributes;
        foreach ($tabs as $tab) {
            $this->add_tab($tab);
        }
    }

    /**
     * Add a tab
     *
     * @param tab $tab
     */
    public function add_tab(tab $tab) {
        $this->tabs[] = $tab;
    }

    /**
     * Implementation of exporter from templatable interface
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $data = [
            'dataattributes' => [],
            'tabs' => []
        ];

        foreach ($this->attributes as $name => $value) {
            $data['dataattributes'][] = ['name' => $name, 'value' => $value];
        }

        foreach ($this->tabs as $tab) {
            $data['tabs'][] = [
                'shortname' => $tab->get_tab_id(),
                'displayname' => $tab->get_tab_label(),
                'enabled' => $tab->is_available(),
                'tabclass' => get_class($tab)
            ];
        }

        $data['showtabsnavigation'] = (count($data['tabs']) > 1) ? 1 : 0;

        return $data;
    }
}
