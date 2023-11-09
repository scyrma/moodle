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

declare(strict_types=1);

namespace tool_catalogue\external;

use core\external\exporter;
use renderer_base;
use tool_catalogue\configuration;

/**
 * Catalogue categories list exporter class
 *
 * Exports the list of categories tree.
 *
 * @package    tool_catalogue
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Mohamed A. Shehata <mohamed.shehata@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class category_selector_exporter extends exporter {
    /**
     * Return the list of additional, generated dynamically from the given properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
            'categories' => [
                'type' => [
                    'id' => ['type' => PARAM_INT],
                    'name' => ['type' => PARAM_TEXT],
                    'url' => ['type' => PARAM_URL],
                    'haschildren' => ['type' => PARAM_BOOL],
                    'subcategories' => [
                        'type' => PARAM_RAW,
                        'multiple' => true,
                        'optional' => true,
                    ],
                ],
                'multiple' => true,
                'optional' => true,
            ],
        ];
    }

    /**
     * Other values
     *
     * @param renderer_base $output
     * @return array
     */
    protected function get_other_values(renderer_base $output): array {
        return [
            'categories' => $this->get_categories_list(),
        ];
    }

    /**
     * Returns categories list in tree structure.
     * @return array
     */
    public function get_categories_list(): array {
        // Get flat categories list. Each element has 'path' ('/parent1/parent2/id') and 'name' properties.
        // This list is not freely accessible, but we can take it from cache.
        // First call to make_categories_list() to make sure that cache is populated.
        \core_course_category::make_categories_list('moodle/category:viewcourselist');
        // Use the same cache and the same key to retrieve a list of categories.
        $coursecatcache = \cache::make('core', 'coursecat');
        $basecachekey = current_language() . '_catlist';
        $baselist = $coursecatcache->get($basecachekey);

        // Now we need to convert the flat list into a tree. We rely on the fact that parents
        // are always present in the flat list before their children.
        // Some categories may not have parents, we will add them to the
        // category with the closest match (or to the root).

        $tree = ['subcategories' => []];

        // List of skipped category ids that not permitted to access.
        $skippedids = [];

        foreach ($baselist as $categoryid => $categoryitem) {
            $path = preg_split("/\//", $categoryitem['path'], -1, PREG_SPLIT_NO_EMPTY);
            $path = array_diff($path, $skippedids);
            $id = array_pop($path); // Remove the actual id, we already know it.
            if ($categoryitem['name']) {
                $this->process_subcategories($tree, $path, (int)$categoryid, $categoryitem['name']);
            } else {
                $skippedids[] = $id;
            }
        }

        return $tree['subcategories'];
    }

    /**
     * Helper function to recursively process subcategories.
     *
     * @param array $tree all categories that we already organised into a tree.
     * @param array $path list of parents of the category we want to add.
     * @param int $categoryid id of the category we want to add.
     * @param string $categoryname name of the category we want to add.
     * @param int $currentlevel current level depth of the categories.
     */
    protected function process_subcategories(
        array &$tree, array $path, int $categoryid, string $categoryname, int $currentlevel = 0
    ): void {
        $maxsamelevel = configuration::get_categories_limit();
        $maxdepth = configuration::get_categories_depth_limit();

        // Take the first parent of this category and try to find it in the tree.
        if ($path) {
            $firstparent = (int)array_shift($path);
            foreach ($tree['subcategories'] as &$child) {
                if ($child['id'] == $firstparent) {
                    // If found, add the category to this branch.
                    $this->process_subcategories($child, $path, $categoryid, $categoryname, ++$currentlevel);
                    return;
                }
            }
        }
        if ($currentlevel < $maxdepth && count($tree['subcategories']) < $maxsamelevel) {
            // We didn't find the parent, so we need to add the category to the tree root.
            $tree['subcategories'][] = [
                'id' => $categoryid,
                'url' => new \moodle_url('/course/index.php', ['categoryid' => $categoryid]),
                'subcategories' => [],
                'name' => $categoryname,
                'haschildren' => false,
            ];
            // Mark the root as having children.
            $tree['haschildren'] = true;
        }
    }

}
