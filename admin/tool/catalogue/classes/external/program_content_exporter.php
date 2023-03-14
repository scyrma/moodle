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

namespace tool_catalogue\external;

use context;
use context_course;
use core\external\exporter;
use html_writer;
use renderer_base;
use stdClass;
use tool_catalogue\external\program\course_exporter;
use tool_catalogue\external\program\set_exporter;
use tool_catalogue\router;
use tool_program\persistent\program_set;
use tool_program\program_item;
use tool_program\program_tree_progress;

/**
 * Program content exporter class
 *
 * This exporter returns the structure of the program content (sets and courses) with all the information needed.
 *
 * @package    tool_catalogue
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_content_exporter extends exporter {

    /** @var int The program id */
    private $programid;

    /** @var array mosaic images */
    private $mosaicimages = [];

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'context' => 'context',
            'treeprogress' => program_tree_progress::class,
            'courses' => 'stdclass[]?',
        ];
    }

    /**
     * Return the list of additional, generated dynamically from the given properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
            'baseset' => [
                'type' => set_exporter::read_properties_definition(),
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
        /** @var context $context */
        $context = $this->related['context'];

        return [
            'baseset' => $this->export_program_structure($output, $context),
        ];
    }

    /**
     * Exports the program structure starting from the base set
     *
     * @param renderer_base $output
     * @param context $context
     * @return stdClass
     */
    private function export_program_structure(renderer_base $output, context $context): stdClass {
        /** @var program_tree_progress $programtreeprogress */
        $programtreeprogress = $this->related['treeprogress'];
        $this->programid = $programtreeprogress->get_program()->get('id');
        $baseset = $programtreeprogress->get_baseset();

        $exporter = new set_exporter($baseset->get_persistent(), [
            'context' => $context,
            'programitemset' => $baseset,
        ]);
        $exportedset = $exporter->export($output);

        $exportedset->items = [];
        foreach ($baseset->items as $childitem) {
            if ($childitem->is_set()) {
                $exportedset->items[] = $this->export_program_set_with_progress($childitem, $output, $context); // Recursion.
            } else if ($childitem->is_course()) {
                $exportedset->items[] = $this->export_program_course_with_progress($childitem, $output);
            }
        }

        $this->export_unlock_requirements($exportedset);
        $this->export_mosaic_images($exportedset->items);

        return $exportedset;
    }

    /**
     * Exports program set (recursively)
     *
     * @param program_item $set
     * @param renderer_base $output
     * @param context $context
     * @return stdClass
     */
    private function export_program_set_with_progress(program_item $set, renderer_base $output, context $context): stdClass {
        $exporter = new set_exporter($set->get_persistent(), [
            'context' => $context,
            'programitemset' => $set,
        ]);
        $exporteset = $exporter->export($output);

        $exporteset->items = [];
        foreach ($set->items as $childitem) {
            if ($childitem->is_set()) {
                $exporteset->items[] = $this->export_program_set_with_progress($childitem, $output, $context); // Recursion.
            } else if ($childitem->is_course()) {
                $exporteset->items[] = $this->export_program_course_with_progress($childitem, $output);
            }
        }

        return $exporteset;
    }

    /**
     * Export mosaic images for all sets in this program.
     * Loops through all sets and subsets in the program and gets the mosaic images for each.
     *
     * @param array $items
     */
    private function export_mosaic_images(array &$items): void {
        foreach ($items as $item) {
            if ($item->isset && count($item->items) > 0) {
                // Reset the mosaic images array for each set.
                $this->mosaicimages = [];
                $item->mosaicimages = $this->mosaic_image($item->items);
                // Recursion: export mosaic images for all subsets.
                $this->export_mosaic_images($item->items);
            }
        }
    }

    /**
     * Get 4 mosaic images per set.
     * Fetches up to 4 course images per set, if needed it will fetch course images from subsets too.
     *
     * @param array $items
     * @return array of images
     */
    private function mosaic_image(array $items): array {
        foreach ($items as $item) {
            if (!$item->isset) {
                // Add the course image to the mosaic images array.
                $this->mosaicimages[] = $item->image;
            } else {
                // Recursion: get mosaic images from subsets.
                $this->mosaic_image($item->items);
            }
            // We have collected 4 images from courses, return them.
            if (count($this->mosaicimages) === 4) {
                return $this->mosaicimages;
            }
        }
        // If we have less than 4 images, return the rest.
        return $this->mosaicimages;
    }

    /**
     * Exports program course
     *
     * @param program_item $course
     * @param renderer_base $output
     * @return stdClass
     */
    private function export_program_course_with_progress(program_item $course, renderer_base $output): stdClass {
        $exporter = new course_exporter(null, [
            'context' => context_course::instance($course->get_courseid()),
            'programitemcourse' => $course,
            'course' => $this->related['courses'][$course->get_courseid()],
        ]);
        return $exporter->export($output);
    }

    /**
     * Calculates recursively the requirement for a locked course/set to be unlocked by the user.
     *
     * @param stdClass $exportedset
     * @return void
     */
    private function export_unlock_requirements(stdClass $exportedset): void {
        /** @var stdClass|null $previousitem */
        $previousitem = null;

        foreach ($exportedset->items as $childitem) {

            if ($exportedset->islocked) {
                // Current set is locked. All items are unavailable.
                $url = router::build_program_set_url($this->programid, $exportedset->setid);
                $link = html_writer::link($url, $exportedset->fullname);
                $childitem->unlockrequirement = get_string('notavailableunless', 'tool_catalogue', $link);

            } else if ($exportedset->setcriteria === program_set::COMPLETION_ALL_IN_ORDER
                && $previousitem !== null && $childitem->islocked) {
                // Do not show course name or course link if the course is hidden for the user.
                if (!$previousitem->isset && $previousitem->ishidden) {
                    // Course name ($previousitem->fullname) has already been hidden in the course exporter.
                    $childitem->unlockrequirement = get_string('notavailableuntil', 'tool_catalogue', $previousitem->fullname);
                } else {
                    $url = $previousitem->isset ? router::build_program_set_url($this->programid, $previousitem->setid) :
                        router::build_course_url((int) $previousitem->courseid);
                    $link = html_writer::link($url, $previousitem->fullname);
                    $childitem->unlockrequirement = get_string('notavailableuntil', 'tool_catalogue', $link);
                }
            }

            if (empty($childitem->courseid)) {
                // Calculate unlock requirements for this set children.
                $this->export_unlock_requirements($childitem);
            }

            $previousitem = $childitem;
        }
    }
}
