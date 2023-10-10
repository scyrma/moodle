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

declare(strict_types=1);

namespace tool_custompage\external;

use core_collator;
use core_component;
use core_plugin_manager;
use renderer_base;
use core_reportbuilder\external\custom_report_menu_cards_exporter;
use tool_custompage\local\audience\base;

/**
 * Page audience cards exporter class
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class page_audience_cards_exporter extends custom_report_menu_cards_exporter {

    /**
     * Return a list of objects that are related to the exporter
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'global' => 'bool',
        ];
    }

    /**
     * Get the additional values to inject while exporting
     *
     * @param renderer_base $output
     * @return array
     */
    protected function get_other_values(renderer_base $output): array {
        /** @var boolean $global */
        $global = $this->related['global'];

        $menucards = [];

        // Iterate over all audience types.
        $audiences = core_component::get_component_classes_in_namespace(null, 'tool_custompage\\audience');
        $audiencekeyindex = 0;
        foreach ($audiences as $class => $path) {
            if (is_subclass_of($class, base::class)) {
                $audience = $class::instance();
                if (!$audience->user_can_add($global)) {
                    continue;
                }

                // The name of each card will be the component the audience belongs to.
                [$component] = explode('\\', $class);
                if ($plugininfo = core_plugin_manager::instance()->get_plugin_info($component)) {
                    $componentname = $plugininfo->displayname;
                } else {
                    $componentname = get_string('site');
                }

                // New menu card per component.
                if (!array_key_exists($componentname, $menucards)) {
                    $menucards[$componentname] = [
                        'name' => $componentname,
                        'key' => 'index' . ++$audiencekeyindex,
                        'items' => [],
                    ];
                }

                // Append menu card item per audience.
                $menucards[$componentname]['items'][] = [
                    'name' => $audience->get_name(),
                    'identifier' => get_class($audience),
                    'title' => get_string('addaudience', 'core_reportbuilder', $audience->get_name()),
                    'action' => 'add-audience',
                    'disabled' => !$audience->is_available(),
                ];
            }
        }

        // Order items in each menu card alphabetically.
        array_walk($menucards, static function(array &$menucard): void {
            core_collator::asort_array_of_arrays_by_key($menucard['items'], 'name');
            $menucard['items'] = array_values($menucard['items']);
        });

        return [
            'menucards' => array_values($menucards),
        ];
    }
}
