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
 * moodleorg specific renderers.
 *
 * @package   theme_school
 * @copyright 2018 Moodle
 * @author    Bas Brands
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_school\output;

defined('MOODLE_INTERNAL') || die();
define('MEDIA_IMAGE', false);
define('MEDIA_VIDEO', true);
define('MEDIA_POSITION_LEFT', false);
define('MEDIA_POSITION_RIGHT', true);

use renderable;
use templatable;
use renderer_base;
use stdClass;
use html_writer;
use moodle_url;

class herostatic implements renderable, templatable {


    public function __construct() {

    }
    /**
     * Export this data so it can be used as the context for a mustache template.
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {

        global $PAGE;

        $media = get_config('theme_school', 'frontpagestaticcontentselect');
        $image = null;
        $videourl = null;
        $video = null;
        $hasmedia = null;
        if ($media == MEDIA_IMAGE) {
            $image = $PAGE->theme->setting_file_url('frontpagemediaimage', 'frontpagemediaimage');
        } else {
            $videourl = get_config('theme_school', 'video');
            $video = $PAGE->theme->setting_file_url('uploadvideo', 'uploadvideo');
        }

        if (($image || $videourl || $video) != null) {
            $hasmedia = true;
        }


        $mediaposition = get_config('theme_school', 'frontpagemediaalignment');

        $right = false;
        $left = false;
        if ($mediaposition == MEDIA_POSITION_RIGHT) {
            $right = true;
        } else {
            $left = true;
        }

        $text = get_config('theme_school', 'addtext');

        $context = [
                'image' => $image,
                'videourl' => $videourl,
                'video' => $video,
                'right' => $right,
                'left' => $left,
                'text' => format_string($text),
                'hasmedia' => $hasmedia
        ];

        return $context;
    }
}
