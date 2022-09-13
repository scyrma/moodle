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

use moodle_page;
use renderer_base;
use core\navigation\views\secondary;

/**
 * Class secondary_tabs
 *
 * @package    tool_wp
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class secondary_tabs implements \templatable {

    /** @var array */
    protected $attributes;
    /** @var tab[]  */
    protected $tabs = [];
    /** @var secondary */
    protected $secondarynav;

    /**
     * tabs constructor.
     *
     * @param array $attributes additional attributes that will be passed to the callback PLUGINNAME_get_tab_content
     * @param tab[] $tabs array of tab
     * @param moodle_page|null $page
     */
    public function __construct(array $attributes = [], array $tabs = [], \moodle_page $page = null) {
        global $PAGE, $SESSION;
        $this->attributes = $attributes;
        $page = $page ?? $PAGE;
        $this->secondarynav = new secondary($page);
        $page->set_secondarynav($this->secondarynav);
        $page->set_secondary_navigation(true, true);
        foreach ($tabs as $tab) {
            $this->add_tab($tab);
        }
        if (empty($SESSION->wpadmin) && is_siteadmin() && !defined('BEHAT_SITE_RUNNING')) {
            $page->requires->js_amd_inline('M.cfg.wpadmin=1');
            $SESSION->wpadmin = 1;
        }
    }

    /**
     * Add a tab
     *
     * @param tab $tab
     */
    public function add_tab(tab $tab) {
        $this->tabs[] = $tab;

        // Add tab to secondary navigation.
        $node = $this->secondarynav->add($tab->get_tab_label(), '#'.$tab->get_tab_id(), null, null,
            $tab->get_tab_id());
        $node->action = false;
        $node->tab = '#'.$tab->get_tab_id();

        // Disable tab if it is not available.
        if (!$tab->is_available()) {
            $node->add_class('disabled');
        }

        // Add tab classes.
        foreach ($tab->get_tab_classes() as $class) {
            $node->add_class($class);
        }
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
                'tabclass' => get_class($tab),
                'label' => $tab->get_tab_label(),
            ];
        }
        return $data;
    }
}
