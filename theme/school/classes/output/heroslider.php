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

use renderable;
use templatable;
use renderer_base;
use stdClass;
use html_writer;
use moodle_url;

class heroslider implements renderable, templatable {


    public function __construct() {

    }
    /**
     * Export this data so it can be used as the context for a mustache template.
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {

        global $PAGE;
        $numberofslides = \get_config('theme_school', 'slidercount');
        $frontpagesettingsurl = new \moodle_url('/admin/settings.php', array('section' => 'themesettingschool', 'activetab' => 'theme_school_frontpage'));
        $frontpagesiteadminurl = new \moodle_url('/admin');
        $hascontent = true;

        if (empty($numberofslides)) {
            $hascontent = false;;
        }

        $context = array(
                'slides' => array(),
                'slideinterval' => get_config('theme_school', 'slideinterval'),
                'slideautoplay' => get_config('theme_school', 'sliderautoplay'),
                'isadmin' => is_siteadmin(),
                'frontpagesettingsurl' => $frontpagesettingsurl->out(),
                'frontpagesiteadminurl' => $frontpagesiteadminurl->out(),
                'hascontent' => $hascontent,
        );

        for ($slidecount = 1; $slidecount <= $numberofslides; $slidecount++) {
            $imageurl = $PAGE->theme->setting_file_url('slideimage'.$slidecount, 'slideimage'.$slidecount);
            $title = get_config('theme_school', 'slidertitle'.$slidecount);
            $text = get_config('theme_school', 'slidertext'.$slidecount);
            $linkurl = get_config('theme_school', 'sliderurl'.$slidecount);
            $linktext = get_config('theme_school', 'sliderbuttontext'.$slidecount);

            if (!empty($text) || !empty($linkurl) || !empty($imageurl)) {
                $context['slides'][] = array(
                        'imageurl' => $imageurl,
                        'title' => format_string($title),
                        'text' => format_string($text),
                        'linkurl' => $linkurl,
                        'linktext' => format_string($linktext)
                );
            }
        }

        return $context;
    }
}
